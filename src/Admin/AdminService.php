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

    public function __construct(?AlbumTrackRelationshipService $albumTrackRelationships = null)
    {
        $this->albumTrackRelationships = $albumTrackRelationships ?? new AlbumTrackRelationshipService();
        $this->releaseBuilder = new ReleaseBuilderService();
        $this->trackAudioResolver = new TrackAudioResolver(new WordPressMediaLibraryProvider());
        $this->audioFormatClassifier = new AudioFormatClassifier();
        $this->defaultsSettings = new DefaultsSettings();
        $this->adminMenu = new AdminMenu($this->defaultsSettings);
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

        echo '<p>' . esc_html__('Use “Edit Track” to focus metadata updates on one track while keeping release ordering compact.', 'campwp') . '</p>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Order', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Title', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Track #', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Audio', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Edit', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Unassign', 'campwp') . '</th>';
        echo '</tr></thead><tbody>';

        if ($selectedTracks === []) {
            echo '<tr><td colspan="6"><em>' . esc_html__('No tracks assigned yet. Add audio files above to generate tracks automatically.', 'campwp') . '</em></td></tr>';
        }

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

            echo '<tr>';
            echo '<td>';
            echo '<input type="hidden" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][id]" value="' . esc_attr((string) $trackId) . '" />';
            echo '<input type="number" min="1" step="1" class="small-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][order]" value="' . esc_attr((string) max($orderValue, 1)) . '" />';
            echo '</td>';
            echo '<td>';
            echo '<input type="text" class="regular-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][title]" value="' . esc_attr(get_the_title($trackId)) . '" />';
            echo '</td>';
            echo '<td><input type="number" min="0" step="1" class="small-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][track_number]" value="' . esc_attr((string) $trackNumber) . '" /></td>';
            echo '<td>';
            echo '<input type="number" min="0" step="1" class="small-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][audio_attachment_id]" value="' . esc_attr((string) $audioAttachmentId) . '" />';
            if ($audioFile instanceof TrackAudioFile) {
                $classification = $this->audioFormatClassifier->classifyAttachment($audioFile->getReferenceId());
                echo '<br /><a href="' . esc_url($audioFile->getUrl()) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('View audio', 'campwp') . '</a>';
                echo '<br /><small>' . esc_html(strtoupper((string) $classification['format'])) . ' — ';
                if ($classification['classification'] === AudioFormatClassification::LOSSLESS) {
                    echo esc_html__('preferred lossless source master', 'campwp');
                } elseif ($classification['classification'] === AudioFormatClassification::LOSSY) {
                    echo esc_html__('lossy-only source (lossless preferred; no true hi-fi derivative)', 'campwp');
                } else {
                    echo esc_html__('unsupported/unknown format', 'campwp');
                }
                echo '</small>';
            }
            echo '</td>';
            echo '<td><button type="button" class="button button-secondary campwp-toggle-track-editor" data-target="campwp-track-editor-' . esc_attr((string) $trackId) . '">' . esc_html__('Edit Track', 'campwp') . '</button></td>';
            echo '<td><label><input type="checkbox" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][remove]" value="1" /> ' . esc_html__('Remove', 'campwp') . '</label></td>';
            echo '</tr>';

            echo '<tr id="campwp-track-editor-' . esc_attr((string) $trackId) . '" class="campwp-track-editor-row" style="display:none;">';
            echo '<td colspan="6">';
            echo '<div style="display:grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap: 12px;">';
            echo '<p><label><strong>' . esc_html__('Subtitle', 'campwp') . '</strong><br /><input type="text" class="regular-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][subtitle]" value="' . esc_attr($subtitle) . '" /></label></p>';
            echo '<p><label><strong>' . esc_html__('Duration', 'campwp') . '</strong><br /><input type="text" class="small-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][duration]" value="' . esc_attr($duration) . '" /></label></p>';
            echo '<p><label><strong>' . esc_html__('Artist Override', 'campwp') . '</strong><br /><input type="text" class="regular-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][artist_display_name]" value="' . esc_attr($artistDisplay) . '" /></label></p>';
            echo '<p><label><strong>' . esc_html__('Credits', 'campwp') . '</strong><br /><textarea rows="3" class="large-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][credits]">' . esc_textarea($credits) . '</textarea></label></p>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        $this->renderTrackEditorScript();
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

    private function renderTrackEditorScript(): void
    {
        $script = <<<JS
        jQuery(function($) {
            var buttons = $('.campwp-toggle-track-editor');
            buttons.on('click', function() {
                var targetId = $(this).attr('data-target');
                var target = $('#' + targetId);
                if (target.length === 0) {
                    return;
                }

                $('.campwp-track-editor-row').not(target).hide();
                target.toggle();
            });
        });
JS;
        wp_add_inline_script('jquery', $script);
    }

}
