<?php

declare(strict_types=1);

namespace CampWP\Admin;

use CampWP\Admin\Menu\AdminMenu;
use CampWP\Admin\Metadata\CoreMetadataMetaBox;
use CampWP\Admin\Settings\DefaultsSettings;
use CampWP\Domain\Audio\AudioFormatClassification;
use CampWP\Domain\Audio\AudioFormatClassifier;
use CampWP\Domain\Audio\TrackAudioFile;
use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\ContentModel\AlbumTrackRelationshipService;
use CampWP\Domain\ContentModel\PostTypeRegistrar;
use CampWP\Domain\ContentModel\ReleaseBuilderService;
use CampWP\Domain\ContentModel\TrackMetadataInheritanceService;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Infrastructure\Media\WordPressMediaLibraryProvider;

final class AdminService
{
    private const NONCE_ACTION = 'campwp_save_album_tracks';
    private const NONCE_NAME = 'campwp_album_tracks_nonce';

    private AlbumTrackRelationshipService $albumTrackRelationships;
    private ReleaseBuilderService $releaseBuilder;
    private TrackAudioResolver $trackAudioResolver;
    private AudioFormatClassifier $audioFormatClassifier;
    private DefaultsSettings $defaultsSettings;
    private AdminMenu $adminMenu;
    private TrackMetadataInheritanceService $inheritance;

    public function __construct(?AlbumTrackRelationshipService $albumTrackRelationships = null)
    {
        $this->albumTrackRelationships = $albumTrackRelationships ?? new AlbumTrackRelationshipService();
        $this->releaseBuilder = new ReleaseBuilderService();
        $this->trackAudioResolver = new TrackAudioResolver(new WordPressMediaLibraryProvider());
        $this->audioFormatClassifier = new AudioFormatClassifier();
        $this->defaultsSettings = new DefaultsSettings();
        $this->adminMenu = new AdminMenu($this->defaultsSettings);
        $this->inheritance = new TrackMetadataInheritanceService();
    }

    public function register(): void
    {
        (new CoreMetadataMetaBox(null, null, null, $this->defaultsSettings))->register();
        $this->defaultsSettings->register();
        $this->adminMenu->register();

        add_action('add_meta_boxes', [$this, 'registerAlbumTracksMetaBox']);
        add_action('add_meta_boxes', [$this, 'cleanupEditingScreens'], 99);
        add_filter('use_block_editor_for_post_type', [$this, 'disableBlockEditorForCampwpTypes'], 10, 2);

        foreach ($this->getAlbumPostTypes() as $albumPostType) {
            add_action('save_post_' . $albumPostType, [$this, 'saveAlbumTracksMetaBox'], 10, 2);
        }
    }

    public function registerAlbumTracksMetaBox(): void
    {
        foreach ($this->getAlbumPostTypes() as $albumPostType) {
            add_meta_box(
                'campwp-album-tracks',
                __('Release Builder', 'campwp'),
                [$this, 'renderAlbumTracksMetaBox'],
                $albumPostType,
                'normal',
                'high'
            );
        }
    }

    public function cleanupEditingScreens(): void
    {
        $postTypes = [PostTypeRegistrar::ALBUM_POST_TYPE, PostTypeRegistrar::TRACK_POST_TYPE];

        foreach ($postTypes as $postType) {
            remove_meta_box('slugdiv', $postType, 'normal');
            remove_meta_box('postcustom', $postType, 'normal');
            remove_meta_box('authordiv', $postType, 'normal');
            remove_meta_box('commentstatusdiv', $postType, 'normal');
            remove_meta_box('commentsdiv', $postType, 'normal');
        }
    }

    public function disableBlockEditorForCampwpTypes(bool $useBlockEditor, string $postType): bool
    {
        if (in_array($postType, [PostTypeRegistrar::ALBUM_POST_TYPE, PostTypeRegistrar::TRACK_POST_TYPE], true)) {
            return false;
        }

        return $useBlockEditor;
    }

    /**
     * @param \WP_Post $post
     */
    public function renderAlbumTracksMetaBox($post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $selectedTracks = $this->albumTrackRelationships->getTracksForAlbum((int) $post->ID);
        $trackPosts = $this->albumTrackRelationships->getAssignableTracks();
        $selectedTrackIds = array_map('absint', wp_list_pluck($selectedTracks, 'ID'));

        echo '<p>' . esc_html__('Add audio files directly to this release, add existing tracks, and edit one selected track at a time.', 'campwp') . '</p>';
        echo '<p><button type="button" class="button button-secondary campwp-release-builder-add-audio">' . esc_html__('Add Audio Files', 'campwp') . '</button></p>';
        echo '<input type="hidden" id="campwp-release-builder-audio-ids" name="campwp_release_builder[new_audio_ids]" value="" />';
        echo '<div id="campwp-release-builder-audio-preview"><em>' . esc_html__('No new audio selected yet.', 'campwp') . '</em></div>';

        echo '<p><label for="campwp-release-builder-existing-tracks"><strong>' . esc_html__('Add Existing Tracks', 'campwp') . '</strong></label><br />';
        echo '<select id="campwp-release-builder-existing-tracks" name="campwp_release_builder[add_existing_track_ids][]" multiple="multiple" size="6" style="width:100%;">';
        foreach ($trackPosts as $trackPost) {
            $trackId = (int) $trackPost->ID;
            if (in_array($trackId, $selectedTrackIds, true)) {
                continue;
            }

            echo '<option value="' . esc_attr((string) $trackId) . '">' . esc_html(get_the_title($trackId)) . ' (#' . esc_html((string) $trackId) . ')</option>';
        }
        echo '</select></p>';

        echo '<p>' . esc_html__('Track list stays compact. Use “Edit” to load one track into the focused panel.', 'campwp') . '</p>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Order', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Track', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Summary', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Edit', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Unassign', 'campwp') . '</th>';
        echo '</tr></thead><tbody id="campwp-track-list-body">';

        if ($selectedTracks === []) {
            echo '<tr><td colspan="5"><em>' . esc_html__('No tracks assigned yet. Add audio files above to generate tracks automatically.', 'campwp') . '</em></td></tr>';
        }

        $releaseDefaults = $this->inheritance->getReleaseDefaults((int) $post->ID);

        foreach ($selectedTracks as $trackPost) {
            $trackId = (int) $trackPost->ID;
            $orderValue = (int) get_post_meta($trackId, MetadataKeys::TRACK_ORDER, true);
            $subtitle = (string) get_post_meta($trackId, MetadataKeys::TRACK_SUBTITLE, true);
            $trackNumber = (int) get_post_meta($trackId, MetadataKeys::TRACK_NUMBER, true);
            $duration = (string) get_post_meta($trackId, MetadataKeys::TRACK_DURATION, true);
            $artistDisplay = (string) get_post_meta($trackId, MetadataKeys::TRACK_ARTIST_DISPLAY, true);
            $credits = (string) get_post_meta($trackId, MetadataKeys::TRACK_CREDITS, true);
            $audioAttachmentId = (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, true);
            $audioFile = $this->trackAudioResolver->getTrackAudioFile($trackId);
            $effectiveArtist = $artistDisplay !== '' ? $artistDisplay : $releaseDefaults['artist_display_name'];
            $summaryParts = array_filter([
                $trackNumber > 0 ? '#' . $trackNumber : '',
                $duration !== '' ? $duration : '',
                $effectiveArtist !== '' ? $effectiveArtist : '',
            ]);

            $classificationLabel = __('No source audio linked', 'campwp');
            if ($audioFile instanceof TrackAudioFile) {
                $classification = $this->audioFormatClassifier->classifyAttachment($audioFile->getReferenceId());
                if ($classification['classification'] === AudioFormatClassification::LOSSLESS) {
                    $classificationLabel = sprintf(__('Audio: %s (preferred source master)', 'campwp'), strtoupper((string) $classification['format']));
                } elseif ($classification['classification'] === AudioFormatClassification::LOSSY) {
                    $classificationLabel = sprintf(__('Audio: %s (lossy source)', 'campwp'), strtoupper((string) $classification['format']));
                } else {
                    $classificationLabel = __('Audio: unsupported/unknown format', 'campwp');
                }
            }

            echo '<tr class="campwp-track-row" data-track-id="' . esc_attr((string) $trackId) . '">';
            echo '<td>';
            echo '<input type="hidden" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][id]" value="' . esc_attr((string) $trackId) . '" />';
            echo '<input type="number" min="1" step="1" class="small-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][order]" value="' . esc_attr((string) max($orderValue, 1)) . '" />';
            echo '<input type="hidden" class="campwp-track-field" data-track-id="' . esc_attr((string) $trackId) . '" data-field="title" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][title]" value="' . esc_attr(get_the_title($trackId)) . '" />';
            echo '<input type="hidden" class="campwp-track-field" data-track-id="' . esc_attr((string) $trackId) . '" data-field="subtitle" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][subtitle]" value="' . esc_attr($subtitle) . '" />';
            echo '<input type="hidden" class="campwp-track-field" data-track-id="' . esc_attr((string) $trackId) . '" data-field="track_number" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][track_number]" value="' . esc_attr((string) $trackNumber) . '" />';
            echo '<input type="hidden" class="campwp-track-field" data-track-id="' . esc_attr((string) $trackId) . '" data-field="duration" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][duration]" value="' . esc_attr($duration) . '" />';
            echo '<input type="hidden" class="campwp-track-field" data-track-id="' . esc_attr((string) $trackId) . '" data-field="artist_display_name" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][artist_display_name]" value="' . esc_attr($artistDisplay) . '" />';
            echo '<input type="hidden" class="campwp-track-field" data-track-id="' . esc_attr((string) $trackId) . '" data-field="credits" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][credits]" value="' . esc_attr($credits) . '" />';
            echo '<input type="hidden" class="campwp-track-field" data-track-id="' . esc_attr((string) $trackId) . '" data-field="audio_attachment_id" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][audio_attachment_id]" value="' . esc_attr((string) $audioAttachmentId) . '" />';
            echo '</td>';
            echo '<td><strong class="campwp-track-title" data-track-id="' . esc_attr((string) $trackId) . '">' . esc_html(get_the_title($trackId)) . '</strong></td>';
            echo '<td><small class="campwp-track-summary" data-track-id="' . esc_attr((string) $trackId) . '">' . esc_html(implode(' · ', $summaryParts)) . '</small><br /><small>' . esc_html($classificationLabel) . '</small></td>';
            echo '<td><button type="button" class="button button-secondary campwp-edit-track" data-track-id="' . esc_attr((string) $trackId) . '">' . esc_html__('Edit', 'campwp') . '</button></td>';
            echo '<td><label><input type="checkbox" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][remove]" value="1" /> ' . esc_html__('Remove', 'campwp') . '</label></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<div id="campwp-release-track-editor" style="margin-top:16px;padding:12px;border:1px solid #ccd0d4;background:#fff;">';
        echo '<h3 style="margin-top:0;" id="campwp-release-track-editor-heading">' . esc_html__('Track Editor', 'campwp') . '</h3>';
        echo '<p class="description" id="campwp-release-track-editor-help">' . esc_html__('Select a track from the list above to edit its metadata.', 'campwp') . '</p>';
        echo '<div id="campwp-release-track-editor-fields" style="display:none;">';
        echo '<div style="display:grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap: 12px;">';
        echo '<p><label><strong>' . esc_html__('Title', 'campwp') . '</strong><br /><input type="text" class="regular-text" data-editor-field="title" /></label></p>';
        echo '<p><label><strong>' . esc_html__('Track #', 'campwp') . '</strong><br /><input type="number" min="0" step="1" class="small-text" data-editor-field="track_number" /></label></p>';
        echo '<p><label><strong>' . esc_html__('Subtitle', 'campwp') . '</strong><br /><input type="text" class="regular-text" data-editor-field="subtitle" /></label></p>';
        echo '<p><label><strong>' . esc_html__('Duration', 'campwp') . '</strong><br /><input type="text" class="small-text" data-editor-field="duration" /></label></p>';
        echo '<p><label><strong>' . esc_html__('Artist Override', 'campwp') . '</strong><br /><input type="text" class="regular-text" data-editor-field="artist_display_name" /></label></p>';
        echo '<p><label><strong>' . esc_html__('Audio Attachment ID', 'campwp') . '</strong><br /><input type="number" min="0" step="1" class="small-text" data-editor-field="audio_attachment_id" /></label></p>';
        echo '<p style="grid-column:1 / -1;"><label><strong>' . esc_html__('Credits', 'campwp') . '</strong><br /><textarea rows="3" class="large-text" data-editor-field="credits"></textarea></label></p>';
        echo '</div>';
        echo '<p class="description" id="campwp-release-track-editor-effective"></p>';
        echo '</div>';
        echo '</div>';

        $this->renderTrackEditorScript((int) $post->ID);
    }

    /**
     * @param \WP_Post $post
     */
    public function saveAlbumTracksMetaBox(int $postId, $post): void
    {
        if (! isset($_POST[self::NONCE_NAME]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (! in_array($post->post_type, $this->getAlbumPostTypes(), true)) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $payload = $_POST['campwp_release_builder'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        }

        $payload = wp_unslash($payload);
        $trackRows = isset($payload['tracks']) && is_array($payload['tracks']) ? $payload['tracks'] : [];
        $selectedIds = [];
        $rawOrders = [];

        foreach ($trackRows as $trackId => $row) {
            $trackId = absint($trackId);
            if ($trackId <= 0 || ! is_array($row)) {
                continue;
            }

            $this->releaseBuilder->saveInlineTrackFields($trackId, $row);

            if (isset($row['remove'])) {
                continue;
            }

            $selectedIds[] = $trackId;
            $rawOrders[$trackId] = max(1, absint($row['order'] ?? 0));
        }

        $existingTrackIds = [];
        if (isset($payload['add_existing_track_ids']) && is_array($payload['add_existing_track_ids'])) {
            $existingTrackIds = array_values(array_unique(array_filter(array_map('absint', $payload['add_existing_track_ids']))));
        }

        $audioAttachmentIds = [];
        if (isset($payload['new_audio_ids']) && is_string($payload['new_audio_ids'])) {
            $audioAttachmentIds = array_values(array_unique(array_filter(array_map('absint', explode(',', $payload['new_audio_ids'])))));
        }

        $generatedTrackIds = $this->releaseBuilder->ensureTracksForAudioAttachments($postId, $audioAttachmentIds);
        $mergedIds = array_values(array_unique(array_merge($selectedIds, $existingTrackIds, $generatedTrackIds)));
        $nextOrder = count($rawOrders) + 1;

        foreach ($mergedIds as $trackId) {
            if (! isset($rawOrders[$trackId])) {
                $rawOrders[$trackId] = $nextOrder;
                $nextOrder++;
            }
        }

        $this->albumTrackRelationships->saveAlbumTrackAssignments($postId, $mergedIds, $rawOrders);
    }

    /**
     * @return list<string>
     */
    private function getAlbumPostTypes(): array
    {
        $postTypes = apply_filters('campwp_album_post_types', ['campwp_album']);

        if (! is_array($postTypes)) {
            return ['campwp_album'];
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $postTypes))));
    }

    private function renderTrackEditorScript(int $albumId): void
    {
        $defaults = $this->inheritance->getReleaseDefaults($albumId);
        $defaultArtist = esc_js((string) $defaults['artist_display_name']);
        $defaultCredits = esc_js((string) $defaults['credits']);

        $script = <<<JS
        jQuery(function($) {
            var activeTrackId = null;

            function hiddenField(trackId, fieldName) {
                return $('.campwp-track-field[data-track-id="' + trackId + '"][data-field="' + fieldName + '"]');
            }

            function editorField(fieldName) {
                return $('[data-editor-field="' + fieldName + '"]');
            }

            function summarize(trackId) {
                var number = hiddenField(trackId, 'track_number').val() || '';
                var duration = hiddenField(trackId, 'duration').val() || '';
                var artist = hiddenField(trackId, 'artist_display_name').val() || '';
                if (!artist) {
                    artist = '{$defaultArtist}';
                }

                var parts = [];
                if (number) {
                    parts.push('#' + number);
                }
                if (duration) {
                    parts.push(duration);
                }
                if (artist) {
                    parts.push(artist);
                }

                $('.campwp-track-summary[data-track-id="' + trackId + '"]').text(parts.join(' · '));
            }

            function syncEditorToHidden() {
                if (!activeTrackId) {
                    return;
                }

                $('[data-editor-field]').each(function() {
                    var fieldName = $(this).data('editor-field');
                    hiddenField(activeTrackId, fieldName).val($(this).val());
                });

                $('.campwp-track-title[data-track-id="' + activeTrackId + '"]').text(editorField('title').val() || '(untitled)');
                summarize(activeTrackId);

                var effectiveArtist = editorField('artist_display_name').val() || '{$defaultArtist}';
                var effectiveCredits = editorField('credits').val() || '{$defaultCredits}';
                var description = '';

                if (effectiveArtist) {
                    description += 'Effective artist: ' + effectiveArtist + '. ';
                }

                if (effectiveCredits) {
                    description += 'Credits inherit from release when empty.';
                }

                $('#campwp-release-track-editor-effective').text(description);
            }

            function loadTrack(trackId) {
                activeTrackId = trackId;
                $('#campwp-release-track-editor-fields').show();

                $('[data-editor-field]').each(function() {
                    var fieldName = $(this).data('editor-field');
                    $(this).val(hiddenField(trackId, fieldName).val() || '');
                });

                var heading = $('.campwp-track-title[data-track-id="' + trackId + '"]').first().text();
                $('#campwp-release-track-editor-heading').text('Track Editor: ' + heading + ' (#' + trackId + ')');
                $('#campwp-release-track-editor-help').text('Editing one track at a time keeps large releases manageable.');

                $('.campwp-track-row').removeClass('campwp-track-row--active');
                $('.campwp-track-row[data-track-id="' + trackId + '"]').addClass('campwp-track-row--active');

                syncEditorToHidden();
            }

            $(document).on('click', '.campwp-edit-track', function() {
                loadTrack($(this).data('track-id').toString());
            });

            $(document).on('input change', '[data-editor-field]', syncEditorToHidden);

            var firstTrack = $('.campwp-edit-track').first();
            if (firstTrack.length) {
                loadTrack(firstTrack.data('track-id').toString());
            }
        });
JS;
        wp_add_inline_script('jquery', $script);
    }
}
