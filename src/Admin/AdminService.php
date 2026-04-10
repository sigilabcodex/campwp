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
    private const AUDIO_AJAX_ACTION = 'campwp_release_builder_add_audio';
    private const EXISTING_TRACK_AJAX_ACTION = 'campwp_release_builder_add_existing_track';

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
        add_action('wp_ajax_' . self::AUDIO_AJAX_ACTION, [$this, 'ajaxAddReleaseAudioTracks']);
        add_action('wp_ajax_' . self::EXISTING_TRACK_AJAX_ACTION, [$this, 'ajaxAddExistingReleaseTrack']);

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

        echo '<p>' . esc_html__('Build this release in three steps: add audio files and/or existing tracks, confirm they appear below, then edit one selected track at a time.', 'campwp') . '</p>';
        echo '<div class="notice notice-info inline"><p><strong>' . esc_html__('Source quality guidance:', 'campwp') . '</strong> ' . esc_html__('Lossless masters are preferred (WAV / FLAC). MP3 and other lossy files can be used but are suboptimal source material.', 'campwp') . '</p></div>';

        echo '<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:6px;">';
        echo '<button type="button" class="button button-secondary campwp-release-builder-add-audio" data-album-id="' . esc_attr((string) $post->ID) . '" data-audio-nonce="' . esc_attr(wp_create_nonce(self::AUDIO_AJAX_ACTION . '_' . (int) $post->ID)) . '">' . esc_html__('Add Audio Files', 'campwp') . '</button>';
        echo '<span class="description">' . esc_html__('Selected files are added immediately to this release track list.', 'campwp') . '</span>';
        echo '</div>';
        echo '<input type="hidden" id="campwp-release-builder-audio-ids" name="campwp_release_builder[new_audio_ids]" value="" />';
        echo '<div id="campwp-release-builder-audio-preview"><em>' . esc_html__('No new audio selected yet.', 'campwp') . '</em></div>';
        echo '<div id="campwp-release-builder-audio-feedback" class="notice inline" style="display:none;margin:8px 0 0;"></div>';

        echo '<hr style="margin:16px 0;" />';
        echo '<p><strong>' . esc_html__('Add Existing Tracks', 'campwp') . '</strong><br /><span class="description">' . esc_html__('Search and add from standalone/unassigned tracks. Added tracks appear instantly in the release list.', 'campwp') . '</span></p>';
        echo '<input type="search" id="campwp-release-builder-existing-search" class="regular-text" placeholder="' . esc_attr__('Search tracks by title or ID…', 'campwp') . '" style="max-width:420px;width:100%;" />';
        echo '<ul id="campwp-release-builder-existing-results" data-album-id="' . esc_attr((string) $post->ID) . '" data-existing-track-nonce="' . esc_attr(wp_create_nonce(self::EXISTING_TRACK_AJAX_ACTION . '_' . (int) $post->ID)) . '" style="max-height:180px;overflow:auto;border:1px solid #ccd0d4;padding:8px;margin:8px 0;">';

        $hasAvailableTracks = false;
        foreach ($trackPosts as $trackPost) {
            $trackId = (int) $trackPost->ID;
            if (in_array($trackId, $selectedTrackIds, true)) {
                continue;
            }

            $hasAvailableTracks = true;
            echo '<li class="campwp-existing-track-item" data-track-id="' . esc_attr((string) $trackId) . '" data-track-label="' . esc_attr(strtolower(get_the_title($trackId) . ' #' . $trackId)) . '">';
            echo '<button type="button" class="button button-small campwp-add-existing-track" data-track-id="' . esc_attr((string) $trackId) . '">' . esc_html__('Add', 'campwp') . '</button> ';
            echo '<span>' . esc_html(get_the_title($trackId)) . ' <code>#' . esc_html((string) $trackId) . '</code></span>';
            echo '</li>';
        }

        if (! $hasAvailableTracks) {
            echo '<li><em>' . esc_html__('No available standalone tracks found.', 'campwp') . '</em></li>';
        }

        echo '</ul>';

        echo '<p>' . esc_html__('Track list stays compact. Use “Edit” to load one track into the focused panel.', 'campwp') . '</p>';
        echo '<div style="max-height:360px; overflow:auto; border:1px solid #ccd0d4;">';
        echo '<table class="widefat striped" style="margin:0;">';
        echo '<thead><tr>';
        echo '<th scope="col">' . esc_html__('Order', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Track', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Summary', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Edit', 'campwp') . '</th>';
        echo '<th scope="col">' . esc_html__('Unassign', 'campwp') . '</th>';
        echo '</tr></thead><tbody id="campwp-track-list-body">';

        if ($selectedTracks === []) {
            echo '<tr id="campwp-track-list-empty"><td colspan="5"><em>' . esc_html__('No tracks assigned yet. Add audio files or existing tracks above.', 'campwp') . '</em></td></tr>';
        }

        $releaseDefaults = $this->inheritance->getReleaseDefaults((int) $post->ID);

        foreach ($selectedTracks as $trackPost) {
            $this->renderReleaseTrackRow((int) $trackPost->ID, $releaseDefaults);
        }

        echo '</tbody></table>';
        echo '</div>';

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

    public function ajaxAddReleaseAudioTracks(): void
    {
        $albumId = absint($_POST['album_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['nonce'] ?? '')));

        if ($albumId <= 0 || ! wp_verify_nonce($nonce, self::AUDIO_AJAX_ACTION . '_' . $albumId)) {
            wp_send_json_error(['message' => __('Could not verify request.', 'campwp')], 403);
        }

        if (! current_user_can('edit_post', $albumId)) {
            wp_send_json_error(['message' => __('You do not have permission to edit this release.', 'campwp')], 403);
        }

        $idsRaw = $_POST['audio_ids'] ?? '';
        if (is_array($idsRaw)) {
            $audioAttachmentIds = array_values(array_unique(array_filter(array_map('absint', $idsRaw))));
        } else {
            $audioAttachmentIds = array_values(array_unique(array_filter(array_map('absint', explode(',', (string) $idsRaw)))));
        }

        if ($audioAttachmentIds === []) {
            wp_send_json_error(['message' => __('No audio files selected.', 'campwp')], 400);
        }

        $trackIds = $this->releaseBuilder->ensureTracksForAudioAttachments($albumId, $audioAttachmentIds);
        if ($trackIds === []) {
            wp_send_json_error(['message' => __('Selected files were not eligible audio sources.', 'campwp')], 400);
        }

        $releaseDefaults = $this->inheritance->getReleaseDefaults($albumId);
        $tracks = [];

        foreach ($trackIds as $trackId) {
            $trackData = $this->getReleaseTrackData($trackId, $releaseDefaults);
            if ($trackData === null) {
                continue;
            }
            $tracks[] = $trackData;
        }

        wp_send_json_success([
            'message' => sprintf(_n('%d track added to release.', '%d tracks added to release.', count($tracks), 'campwp'), count($tracks)),
            'tracks' => $tracks,
        ]);
    }

    public function ajaxAddExistingReleaseTrack(): void
    {
        $albumId = absint($_POST['album_id'] ?? 0);
        $trackId = absint($_POST['track_id'] ?? 0);
        $nonce = sanitize_text_field(wp_unslash((string) ($_POST['nonce'] ?? '')));

        if ($albumId <= 0 || ! wp_verify_nonce($nonce, self::EXISTING_TRACK_AJAX_ACTION . '_' . $albumId)) {
            wp_send_json_error(['message' => __('Could not verify request.', 'campwp')], 403);
        }

        if (! current_user_can('edit_post', $albumId) || ! current_user_can('edit_post', $trackId)) {
            wp_send_json_error(['message' => __('You do not have permission to edit this release.', 'campwp')], 403);
        }

        $releaseDefaults = $this->inheritance->getReleaseDefaults($albumId);
        $trackData = $this->getReleaseTrackData($trackId, $releaseDefaults);

        if ($trackData === null) {
            wp_send_json_error(['message' => __('Invalid track selected.', 'campwp')], 400);
        }

        wp_send_json_success([
            'message' => __('Track added to release builder list.', 'campwp'),
            'track' => $trackData,
        ]);
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

    /**
     * @param array{artist_display_name:string,credits:string} $defaults
     */
    private function renderReleaseTrackRow(int $trackId, array $defaults): void
    {
        $trackData = $this->getReleaseTrackData($trackId, $defaults);
        if ($trackData === null) {
            return;
        }

        echo '<tr class="campwp-track-row" data-track-id="' . esc_attr((string) $trackId) . '">';
        echo '<td>';
        echo '<input type="hidden" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][id]" value="' . esc_attr((string) $trackId) . '" />';
        echo '<input type="number" min="1" step="1" class="small-text" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][order]" value="' . esc_attr((string) max((int) $trackData['order'], 1)) . '" />';
        $this->renderTrackFieldInput($trackId, 'title', (string) $trackData['title']);
        $this->renderTrackFieldInput($trackId, 'subtitle', (string) $trackData['subtitle']);
        $this->renderTrackFieldInput($trackId, 'track_number', (string) $trackData['track_number']);
        $this->renderTrackFieldInput($trackId, 'duration', (string) $trackData['duration']);
        $this->renderTrackFieldInput($trackId, 'artist_display_name', (string) $trackData['artist_display_name']);
        $this->renderTrackFieldInput($trackId, 'credits', (string) $trackData['credits']);
        $this->renderTrackFieldInput($trackId, 'audio_attachment_id', (string) $trackData['audio_attachment_id']);
        echo '</td>';
        echo '<td><strong class="campwp-track-title" data-track-id="' . esc_attr((string) $trackId) . '">' . esc_html((string) $trackData['title']) . '</strong></td>';
        echo '<td><small class="campwp-track-summary" data-track-id="' . esc_attr((string) $trackId) . '">' . esc_html((string) $trackData['summary']) . '</small><br /><small class="campwp-track-classification" data-track-id="' . esc_attr((string) $trackId) . '">' . esc_html((string) $trackData['classification_label']) . '</small></td>';
        echo '<td><button type="button" class="button button-secondary campwp-edit-track" data-track-id="' . esc_attr((string) $trackId) . '">' . esc_html__('Edit', 'campwp') . '</button></td>';
        echo '<td><button type="button" class="button button-link-delete campwp-unassign-track" data-track-id="' . esc_attr((string) $trackId) . '" data-confirming="0" aria-label="' . esc_attr__('Unassign track from this release', 'campwp') . '">' . esc_html__('Remove', 'campwp') . '</button></td>';
        echo '</tr>';
    }

    private function renderTrackFieldInput(int $trackId, string $field, string $value): void
    {
        echo '<input type="hidden" class="campwp-track-field" data-track-id="' . esc_attr((string) $trackId) . '" data-field="' . esc_attr($field) . '" name="campwp_release_builder[tracks][' . esc_attr((string) $trackId) . '][' . esc_attr($field) . ']" value="' . esc_attr($value) . '" />';
    }

    /**
     * @param array{artist_display_name:string,credits:string} $releaseDefaults
     * @return array<string, int|string>|null
     */
    private function getReleaseTrackData(int $trackId, array $releaseDefaults): ?array
    {
        if ($trackId <= 0 || get_post_type($trackId) !== $this->getTrackPostType()) {
            return null;
        }

        $order = (int) get_post_meta($trackId, MetadataKeys::TRACK_ORDER, true);
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

        return [
            'id' => $trackId,
            'order' => max($order, 1),
            'title' => (string) get_the_title($trackId),
            'subtitle' => $subtitle,
            'track_number' => $trackNumber,
            'duration' => $duration,
            'artist_display_name' => $artistDisplay,
            'credits' => $credits,
            'audio_attachment_id' => $audioAttachmentId,
            'summary' => implode(' · ', $summaryParts),
            'classification_label' => $this->getTrackClassificationLabel($audioFile),
        ];
    }

    private function getTrackClassificationLabel(?TrackAudioFile $audioFile): string
    {
        $classificationLabel = __('No source audio linked', 'campwp');

        if (! $audioFile instanceof TrackAudioFile) {
            return $classificationLabel;
        }

        $classification = $this->audioFormatClassifier->classifyAttachment($audioFile->getReferenceId());
        if ($classification['classification'] === AudioFormatClassification::LOSSLESS) {
            return sprintf(__('Audio: %s (preferred lossless source)', 'campwp'), strtoupper((string) $classification['format']));
        }

        if ($classification['classification'] === AudioFormatClassification::LOSSY) {
            return sprintf(__('Audio: %s (lossy source — usable, but suboptimal)', 'campwp'), strtoupper((string) $classification['format']));
        }

        return __('Audio: unsupported/unknown format', 'campwp');
    }

    private function getTrackPostType(): string
    {
        $postType = apply_filters('campwp_track_post_type', PostTypeRegistrar::TRACK_POST_TYPE);

        if (! is_string($postType) || $postType === '') {
            return PostTypeRegistrar::TRACK_POST_TYPE;
        }

        return sanitize_key($postType);
    }

    private function renderTrackEditorScript(int $albumId): void
    {
        $defaults = $this->inheritance->getReleaseDefaults($albumId);
        $defaultArtist = wp_json_encode((string) $defaults['artist_display_name']);
        $defaultCredits = wp_json_encode((string) $defaults['credits']);
        $ajaxUrl = wp_json_encode(admin_url('admin-ajax.php'));
        $action = wp_json_encode(self::AUDIO_AJAX_ACTION);
        $existingTrackAction = wp_json_encode(self::EXISTING_TRACK_AJAX_ACTION);

        $script = <<<JS
        jQuery(function($) {
                var activeTrackId = null;

            function getNextOrder() {
                var maxOrder = 0;
                $('#campwp-track-list-body input[name$="[order]"]').each(function(){
                    var value = parseInt($(this).val(), 10);
                    if (!Number.isNaN(value) && value > maxOrder) {
                        maxOrder = value;
                    }
                });

                return maxOrder + 1;
            }

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
                    artist = {$defaultArtist};
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

                var effectiveArtist = editorField('artist_display_name').val() || {$defaultArtist};
                var effectiveCredits = editorField('credits').val() || {$defaultCredits};
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

            function appendTrackRow(track) {
                if (!track || !track.id) {
                    return;
                }

                var trackId = String(track.id);
                var existingRow = $('.campwp-track-row[data-track-id="' + trackId + '"]');
                if (existingRow.length) {
                    return;
                }

                var rowHtml = '' +
                '<tr class="campwp-track-row" data-track-id="' + trackId + '">' +
                    '<td>' +
                        '<input type="hidden" name="campwp_release_builder[tracks][' + trackId + '][id]" value="' + trackId + '" />' +
                        '<input type="number" min="1" step="1" class="small-text" name="campwp_release_builder[tracks][' + trackId + '][order]" value="' + (track.order || getNextOrder()) + '" />' +
                        '<input type="hidden" class="campwp-track-field" data-track-id="' + trackId + '" data-field="title" name="campwp_release_builder[tracks][' + trackId + '][title]" value="' + $('<div/>').text(track.title || '').html() + '" />' +
                        '<input type="hidden" class="campwp-track-field" data-track-id="' + trackId + '" data-field="subtitle" name="campwp_release_builder[tracks][' + trackId + '][subtitle]" value="' + $('<div/>').text(track.subtitle || '').html() + '" />' +
                        '<input type="hidden" class="campwp-track-field" data-track-id="' + trackId + '" data-field="track_number" name="campwp_release_builder[tracks][' + trackId + '][track_number]" value="' + (track.track_number || '') + '" />' +
                        '<input type="hidden" class="campwp-track-field" data-track-id="' + trackId + '" data-field="duration" name="campwp_release_builder[tracks][' + trackId + '][duration]" value="' + $('<div/>').text(track.duration || '').html() + '" />' +
                        '<input type="hidden" class="campwp-track-field" data-track-id="' + trackId + '" data-field="artist_display_name" name="campwp_release_builder[tracks][' + trackId + '][artist_display_name]" value="' + $('<div/>').text(track.artist_display_name || '').html() + '" />' +
                        '<input type="hidden" class="campwp-track-field" data-track-id="' + trackId + '" data-field="credits" name="campwp_release_builder[tracks][' + trackId + '][credits]" value="' + $('<div/>').text(track.credits || '').html() + '" />' +
                        '<input type="hidden" class="campwp-track-field" data-track-id="' + trackId + '" data-field="audio_attachment_id" name="campwp_release_builder[tracks][' + trackId + '][audio_attachment_id]" value="' + (track.audio_attachment_id || 0) + '" />' +
                    '</td>' +
                    '<td><strong class="campwp-track-title" data-track-id="' + trackId + '">' + $('<div/>').text(track.title || '').html() + '</strong></td>' +
                    '<td><small class="campwp-track-summary" data-track-id="' + trackId + '">' + $('<div/>').text(track.summary || '').html() + '</small><br /><small class="campwp-track-classification" data-track-id="' + trackId + '">' + $('<div/>').text(track.classification_label || '').html() + '</small></td>' +
                    '<td><button type="button" class="button button-secondary campwp-edit-track" data-track-id="' + trackId + '">Edit</button></td>' +
                    '<td><button type="button" class="button button-link-delete campwp-unassign-track" data-track-id="' + trackId + '" data-confirming="0" aria-label="Unassign track from this release">Remove</button></td>' +
                '</tr>';

                $('#campwp-track-list-empty').remove();
                $('#campwp-track-list-body').append(rowHtml);
            }

            function clearEditor() {
                activeTrackId = null;
                $('#campwp-release-track-editor-fields').hide();
                $('#campwp-release-track-editor-heading').text('Track Editor');
                $('#campwp-release-track-editor-help').text('Select a track from the list above to edit its metadata.');
                $('#campwp-release-track-editor-effective').text('');
                $('.campwp-track-row').removeClass('campwp-track-row--active');
                $('[data-editor-field]').each(function() {
                    $(this).val('');
                });
            }

            function resequenceOrders() {
                $('#campwp-track-list-body .campwp-track-row').each(function(index){
                    $(this).find('input[name$=\"[order]\"]').first().val(index + 1);
                });
            }

            function resetRemoveConfirmState(exceptTrackId) {
                $('.campwp-unassign-track').each(function(){
                    var button = $(this);
                    var buttonTrackId = String(button.data('track-id'));
                    if (exceptTrackId && buttonTrackId === exceptTrackId) {
                        return;
                    }
                    if (button.data('confirming') === 1 || button.attr('data-confirming') === '1') {
                        button.data('confirming', 0);
                        button.attr('data-confirming', '0');
                        button.text('Remove');
                    }
                });
            }

            function restoreTrackToExistingList(row, trackId) {
                var existingList = $('#campwp-release-builder-existing-results');
                if (existingList.length === 0) {
                    return;
                }

                if (existingList.find('.campwp-existing-track-item[data-track-id=\"' + trackId + '\"]').length > 0) {
                    return;
                }

                var title = row.find('.campwp-track-title').first().text() || ('Track #' + trackId);
                var label = (title + ' #' + trackId).toLowerCase();
                var itemHtml = '' +
                    '<li class=\"campwp-existing-track-item\" data-track-id=\"' + trackId + '\" data-track-label=\"' + $('<div/>').text(label).html() + '\">' +
                    '<button type=\"button\" class=\"button button-small campwp-add-existing-track\" data-track-id=\"' + trackId + '\">Add</button> ' +
                    '<span>' + $('<div/>').text(title).html() + ' <code>#' + $('<div/>').text(trackId).html() + '</code></span>' +
                    '</li>';
                existingList.append(itemHtml);
                filterExistingTracks();
            }

            function updateAudioFeedback(type, text) {
                var feedback = $('#campwp-release-builder-audio-feedback');
                feedback.removeClass('notice-success notice-error');
                feedback.addClass(type === 'error' ? 'notice-error' : 'notice-success');
                feedback.html('<p>' + $('<div/>').text(text).html() + '</p>').show();
            }

            function filterExistingTracks() {
                var needle = ($('#campwp-release-builder-existing-search').val() || '').toString().toLowerCase();
                $('.campwp-existing-track-item').each(function(){
                    var label = ($(this).data('track-label') || '').toString();
                    $(this).toggle(label.indexOf(needle) !== -1);
                });
            }

            $(document).on('click', '.campwp-edit-track', function() {
                resetRemoveConfirmState();
                loadTrack($(this).data('track-id').toString());
            });

            $(document).on('click', '.campwp-unassign-track', function() {
                var button = $(this);
                var trackId = String(button.data('track-id'));
                var isConfirming = button.data('confirming') === 1 || button.attr('data-confirming') === '1';

                resetRemoveConfirmState(trackId);

                if (!isConfirming) {
                    button.data('confirming', 1);
                    button.attr('data-confirming', '1');
                    button.text('Confirm remove');
                    return;
                }

                var row = $('.campwp-track-row[data-track-id=\"' + trackId + '\"]');
                if (row.length === 0) {
                    return;
                }

                var wasActive = activeTrackId && String(activeTrackId) === trackId;
                var nextRow = row.nextAll('.campwp-track-row').first();
                if (nextRow.length === 0) {
                    nextRow = row.prevAll('.campwp-track-row').first();
                }

                restoreTrackToExistingList(row, trackId);
                row.remove();
                resequenceOrders();

                if ($('#campwp-track-list-body .campwp-track-row').length === 0) {
                    $('#campwp-track-list-body').append('<tr id=\"campwp-track-list-empty\"><td colspan=\"5\"><em>No tracks assigned yet. Add audio files or existing tracks above.</em></td></tr>');
                    clearEditor();
                    return;
                }

                if (wasActive) {
                    if (nextRow.length) {
                        loadTrack(String(nextRow.data('track-id')));
                    } else {
                        clearEditor();
                    }
                } else {
                    syncEditorToHidden();
                }
            });

            $(document).on('input change', '[data-editor-field]', syncEditorToHidden);
            $(document).on('input', '#campwp-release-builder-existing-search', filterExistingTracks);

            $(document).on('click', '.campwp-add-existing-track', function() {
                var trackId = String($(this).data('track-id'));
                var option = $('.campwp-existing-track-item[data-track-id="' + trackId + '"]');
                if (option.length === 0) {
                    return;
                }

                if ($('.campwp-track-row[data-track-id="' + trackId + '"]').length === 0) {
                    var list = $('#campwp-release-builder-existing-results');
                    var albumId = list.data('album-id');
                    var nonce = list.data('existing-track-nonce');

                    $.post({$ajaxUrl}, {
                        action: {$existingTrackAction},
                        album_id: albumId,
                        nonce: nonce,
                        track_id: trackId
                    }).done(function(response){
                        var track = response && response.success && response.data
                            ? (response.data.track || null)
                            : null;

                        if (!track) {
                            updateAudioFeedback('error', (response && response.data && response.data.message) ? response.data.message : 'Could not add selected track.');
                            return;
                        }

                        appendTrackRow(track);
                        option.remove();
                        loadTrack(trackId);
                        updateAudioFeedback('success', response.data.message || 'Track added to release builder list.');
                    }).fail(function(){
                        updateAudioFeedback('error', 'Failed to add selected track. Please try again.');
                    });
                    return;
                }

                option.remove();
                loadTrack(trackId);
            });

            $(document).on('campwp:release-audio-selected', function(event, payload) {
                if (!payload || !Array.isArray(payload.ids) || payload.ids.length === 0) {
                    return;
                }

                var button = $('.campwp-release-builder-add-audio').first();
                var albumId = button.data('album-id');
                var nonce = button.data('audio-nonce');

                $.post({$ajaxUrl}, {
                    action: {$action},
                    album_id: albumId,
                    nonce: nonce,
                    audio_ids: payload.ids.join(',')
                }).done(function(response){
                    if (!response || !response.success || !response.data || !Array.isArray(response.data.tracks)) {
                        updateAudioFeedback('error', 'Could not add selected audio to release tracks.');
                        return;
                    }

                    var firstTrackId = null;
                    response.data.tracks.forEach(function(track){
                        appendTrackRow(track);
                        if (!firstTrackId) {
                            firstTrackId = String(track.id);
                        }
                    });

                    if (firstTrackId) {
                        loadTrack(firstTrackId);
                    }

                    $('#campwp-release-builder-audio-ids').val('');
                    $('#campwp-release-builder-audio-preview').html('<em>No new audio selected yet.</em>');
                    updateAudioFeedback('success', response.data.message || 'Audio added to release tracks.');
                }).fail(function(){
                    updateAudioFeedback('error', 'Failed to add selected audio. Please try again.');
                });
            });

            var firstTrack = $('.campwp-edit-track').first();
            if (firstTrack.length) {
                loadTrack(firstTrack.data('track-id').toString());
            }
        });
JS;
        echo '<script>' . $script . '</script>';
    }
}
