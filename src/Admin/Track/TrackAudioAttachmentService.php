<?php

declare(strict_types=1);

namespace CampWP\Admin\Track;

use CampWP\Infrastructure\Storage\AudioStorageAdapterInterface;
use RuntimeException;

final class TrackAudioAttachmentService
{
    private const TRACK_POST_TYPE = 'campwp_track';
    private const META_AUDIO_ATTACHMENT_ID = '_campwp_audio_attachment_id';
    private const META_ARTIST = '_campwp_track_artist';
    private const META_DURATION = '_campwp_track_duration';
    private const META_HAS_ARTWORK = '_campwp_track_has_artwork';
    private const NONCE_ACTION = 'campwp_track_audio_attachment';
    private const NONCE_NAME = 'campwp_track_audio_attachment_nonce';

    public function __construct(private readonly AudioStorageAdapterInterface $storageAdapter)
    {
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'registerMetaBox']);
        add_action('save_post_' . self::TRACK_POST_TYPE, [$this, 'saveMetaBox'], 10, 2);
    }

    public function registerMetaBox(): void
    {
        add_meta_box(
            'campwp-track-audio-file',
            __('Audio File', 'campwp'),
            [$this, 'renderMetaBox'],
            self::TRACK_POST_TYPE,
            'normal',
            'default'
        );
    }

    /**
     * @param \WP_Post $post
     */
    public function renderMetaBox($post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $attachmentId = (int) get_post_meta((int) $post->ID, self::META_AUDIO_ATTACHMENT_ID, true);
        $selectedValue = $attachmentId > 0 ? $attachmentId : 0;

        echo '<p>' . esc_html__('Attach an existing media library audio file or upload a new one. Metadata is imported once as initial values and can be edited later.', 'campwp') . '</p>';

        echo '<p><label for="campwp-audio-attachment-id"><strong>' . esc_html__('Existing audio file', 'campwp') . '</strong></label></p>';
        echo '<select id="campwp-audio-attachment-id" name="campwp_audio_attachment_id" class="widefat">';
        echo '<option value="0">' . esc_html__('— No audio file attached —', 'campwp') . '</option>';

        $attachments = get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => 100,
            'post_status' => 'inherit',
            'orderby' => 'date',
            'order' => 'DESC',
            'post_mime_type' => 'audio',
            'suppress_filters' => false,
        ]);

        foreach ($attachments as $attachment) {
            $id = (int) $attachment->ID;
            $title = get_the_title($id);
            $url = wp_get_attachment_url($id);

            $label = $title !== '' ? $title : sprintf(__('Audio #%d', 'campwp'), $id);
            if (is_string($url) && $url !== '') {
                $label .= ' (' . wp_basename($url) . ')';
            }

            echo '<option value="' . esc_attr((string) $id) . '" ' . selected($selectedValue, $id, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';

        if ($attachmentId > 0) {
            $url = $this->storageAdapter->getUrl($attachmentId);
            if ($url !== null) {
                echo '<p><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Preview current audio file', 'campwp') . '</a></p>';
            }
        }

        echo '<hr />';
        echo '<p><label for="campwp-track-audio-upload"><strong>' . esc_html__('Upload new audio file', 'campwp') . '</strong></label></p>';
        echo '<input type="file" id="campwp-track-audio-upload" name="campwp_track_audio_upload" accept="audio/*" />';
        echo '<p class="description">' . esc_html__('If a file is uploaded here while saving, it is imported to Media Library and attached to this track.', 'campwp') . '</p>';
    }

    /**
     * @param \WP_Post $post
     */
    public function saveMetaBox(int $postId, $post): void
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

        if ((string) $post->post_type !== self::TRACK_POST_TYPE) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $attachmentId = isset($_POST['campwp_audio_attachment_id']) ? absint(wp_unslash($_POST['campwp_audio_attachment_id'])) : 0;

        if (isset($_FILES['campwp_track_audio_upload']) && is_array($_FILES['campwp_track_audio_upload'])) {
            $file = $_FILES['campwp_track_audio_upload'];

            if (isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $attachmentId = $this->storageAdapter->store($file, ['post_id' => $postId]);
                } catch (RuntimeException $exception) {
                    add_filter(
                        'redirect_post_location',
                        static function (string $location) use ($exception): string {
                            return add_query_arg('campwp_audio_error', rawurlencode($exception->getMessage()), $location);
                        }
                    );

                    return;
                }
            }
        }

        if ($attachmentId <= 0) {
            delete_post_meta($postId, self::META_AUDIO_ATTACHMENT_ID);
            return;
        }

        if (! $this->storageAdapter->exists($attachmentId)) {
            return;
        }

        update_post_meta($postId, self::META_AUDIO_ATTACHMENT_ID, $attachmentId);

        $this->maybeImportMetadata($postId, $attachmentId);
    }

    private function maybeImportMetadata(int $trackId, int $attachmentId): void
    {
        $path = $this->storageAdapter->getPath($attachmentId);

        if ($path === null || ! file_exists($path)) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';

        $metadata = wp_read_audio_metadata($path);

        if (! is_array($metadata)) {
            return;
        }

        $track = get_post($trackId);
        if ($track instanceof \WP_Post) {
            $currentTitle = trim((string) $track->post_title);
            $detectedTitle = isset($metadata['title']) && is_string($metadata['title']) ? trim($metadata['title']) : '';

            if ($detectedTitle !== '' && ($currentTitle === '' || strcasecmp($currentTitle, __('Auto Draft')) === 0 || strcasecmp($currentTitle, 'Auto Draft') === 0)) {
                wp_update_post([
                    'ID' => $trackId,
                    'post_title' => $detectedTitle,
                ]);
            }
        }

        $existingArtist = trim((string) get_post_meta($trackId, self::META_ARTIST, true));
        if ($existingArtist === '' && isset($metadata['artist']) && is_string($metadata['artist']) && trim($metadata['artist']) !== '') {
            update_post_meta($trackId, self::META_ARTIST, trim($metadata['artist']));
        }

        $existingDuration = (float) get_post_meta($trackId, self::META_DURATION, true);
        $detectedDuration = isset($metadata['length']) ? (float) $metadata['length'] : 0.0;
        if ($existingDuration <= 0 && $detectedDuration > 0) {
            update_post_meta($trackId, self::META_DURATION, $detectedDuration);
        }

        $hasArtwork = false;
        if (isset($metadata['image']) && is_array($metadata['image'])) {
            $hasArtwork = ! empty($metadata['image']['data']) || ! empty($metadata['image']['mime']);
        }

        $existingArtworkValue = get_post_meta($trackId, self::META_HAS_ARTWORK, true);
        if ($existingArtworkValue === '') {
            update_post_meta($trackId, self::META_HAS_ARTWORK, $hasArtwork ? 1 : 0);
        }
    }
}
