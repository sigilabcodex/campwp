<?php

declare(strict_types=1);

namespace CampWP\Admin\Metadata;

use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Domain\Metadata\MetadataSanitizer;

final class CoreMetadataMetaBox
{
    private const ALBUM_NONCE_ACTION = 'campwp_save_album_core_metadata';
    private const ALBUM_NONCE_NAME = 'campwp_album_core_metadata_nonce';
    private const TRACK_NONCE_ACTION = 'campwp_save_track_core_metadata';
    private const TRACK_NONCE_NAME = 'campwp_track_core_metadata_nonce';
    private const RELEASE_TYPE_OPTIONS = ['single', 'ep', 'album', 'compilation', 'other'];

    private MetadataSanitizer $sanitizer;

    public function __construct(?MetadataSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
    }

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
        add_action('save_post', [$this, 'saveMetadata'], 10, 2);
    }

    public function registerMetaBoxes(): void
    {
        add_meta_box(
            'campwp-album-core-metadata',
            __('Album Metadata', 'campwp'),
            [$this, 'renderAlbumMetaBox'],
            $this->getAlbumPostType(),
            'normal',
            'default'
        );

        add_meta_box(
            'campwp-track-core-metadata',
            __('Track Metadata', 'campwp'),
            [$this, 'renderTrackMetaBox'],
            $this->getTrackPostType(),
            'normal',
            'default'
        );
    }

    /**
     * @param \WP_Post $post
     */
    public function renderAlbumMetaBox($post): void
    {
        wp_nonce_field(self::ALBUM_NONCE_ACTION, self::ALBUM_NONCE_NAME);

        $subtitle = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_SUBTITLE);
        $releaseDate = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_RELEASE_DATE);
        $catalogNumber = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_CATALOG_NUMBER);
        $artistDisplayName = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_ARTIST_DISPLAY);
        $creditsOverride = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_CREDITS_OVERRIDE);
        $labelName = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_LABEL_NAME);
        $releaseNotes = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_RELEASE_NOTES);
        $releaseType = $this->getMetaValue((int) $post->ID, MetadataKeys::ALBUM_RELEASE_TYPE);
        $releaseType = $releaseType !== '' ? $releaseType : 'album';

        echo '<p>' . esc_html__('Featured image is used as album cover art.', 'campwp') . '</p>';

        $this->renderTextField('campwp_album_metadata[subtitle]', __('Subtitle', 'campwp'), $subtitle);
        $this->renderDateField('campwp_album_metadata[release_date]', __('Release Date', 'campwp'), $releaseDate, true);
        $this->renderTextField('campwp_album_metadata[catalog_number]', __('Catalog Number', 'campwp'), $catalogNumber);
        $this->renderSelectField('campwp_album_metadata[release_type]', __('Release Type', 'campwp'), $releaseType, self::RELEASE_TYPE_OPTIONS);
        $this->renderTextField('campwp_album_metadata[artist_display_name]', __('Artist Display Name', 'campwp'), $artistDisplayName, true);
        $this->renderTextField('campwp_album_metadata[label_name]', __('Label Name', 'campwp'), $labelName);
        $this->renderTextareaField('campwp_album_metadata[credits_override]', __('Credits / Liner Notes Override', 'campwp'), $creditsOverride);
        $this->renderTextareaField('campwp_album_metadata[release_notes]', __('Release Notes', 'campwp'), $releaseNotes);
        echo '<p><em>' . esc_html__('Tracks can remain standalone when no album assignment is set. In v1, each track can belong to at most one album.', 'campwp') . '</em></p>';
    }

    /**
     * @param \WP_Post $post
     */
    public function renderTrackMetaBox($post): void
    {
        wp_nonce_field(self::TRACK_NONCE_ACTION, self::TRACK_NONCE_NAME);

        $trackNumber = (string) $this->getMetaIntegerValue((int) $post->ID, MetadataKeys::TRACK_NUMBER);
        $subtitle = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_SUBTITLE);
        $duration = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_DURATION);
        $artistDisplayName = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_ARTIST_DISPLAY);
        $credits = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_CREDITS);
        $lyrics = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_LYRICS);
        $isrc = $this->getMetaValue((int) $post->ID, MetadataKeys::TRACK_ISRC);
        $artworkId = (string) $this->getMetaIntegerValue((int) $post->ID, MetadataKeys::TRACK_ARTWORK_ID);

        $this->renderNumberField('campwp_track_metadata[track_number]', __('Track Number', 'campwp'), $trackNumber, true);
        $this->renderTextField('campwp_track_metadata[subtitle]', __('Subtitle', 'campwp'), $subtitle);
        $this->renderTextField('campwp_track_metadata[duration]', __('Duration (MM:SS or HH:MM:SS)', 'campwp'), $duration);
        $this->renderTextField('campwp_track_metadata[artist_display_name]', __('Artist Display Override', 'campwp'), $artistDisplayName);
        $this->renderTextareaField('campwp_track_metadata[credits]', __('Credits', 'campwp'), $credits);
        $this->renderTextareaField('campwp_track_metadata[lyrics]', __('Lyrics', 'campwp'), $lyrics);
        $this->renderTextField('campwp_track_metadata[isrc]', __('ISRC', 'campwp'), $isrc);
        $this->renderNumberField('campwp_track_metadata[artwork_id]', __('Track Artwork Attachment ID', 'campwp'), $artworkId);
        echo '<p><em>' . esc_html__('Optional: store a Media Library attachment ID for track-specific artwork.', 'campwp') . '</em></p>';
    }

    /**
     * @param \WP_Post $post
     */
    public function saveMetadata(int $postId, $post): void
    {
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        if ($post->post_type === $this->getAlbumPostType()) {
            $this->saveAlbumMetadata($postId);
            return;
        }

        if ($post->post_type === $this->getTrackPostType()) {
            $this->saveTrackMetadata($postId);
        }
    }

    private function saveAlbumMetadata(int $postId): void
    {
        if (! $this->isValidNonce(self::ALBUM_NONCE_NAME, self::ALBUM_NONCE_ACTION)) {
            return;
        }

        $rawValues = $_POST['campwp_album_metadata'] ?? [];
        if (! is_array($rawValues)) {
            $rawValues = [];
        }

        $values = wp_unslash($rawValues);

        $this->updateMeta($postId, MetadataKeys::ALBUM_SUBTITLE, $this->sanitizer->sanitizeText((string) ($values['subtitle'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_RELEASE_DATE, $this->sanitizer->sanitizeReleaseDate((string) ($values['release_date'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_CATALOG_NUMBER, $this->sanitizer->sanitizeText((string) ($values['catalog_number'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_RELEASE_TYPE, $this->sanitizer->sanitizeReleaseType((string) ($values['release_type'] ?? 'album')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_ARTIST_DISPLAY, $this->sanitizer->sanitizeText((string) ($values['artist_display_name'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_LABEL_NAME, $this->sanitizer->sanitizeText((string) ($values['label_name'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_CREDITS_OVERRIDE, $this->sanitizer->sanitizeTextarea((string) ($values['credits_override'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_RELEASE_NOTES, $this->sanitizer->sanitizeTextarea((string) ($values['release_notes'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::ALBUM_BONUS_ITEMS, '[]');
    }

    private function saveTrackMetadata(int $postId): void
    {
        if (! $this->isValidNonce(self::TRACK_NONCE_NAME, self::TRACK_NONCE_ACTION)) {
            return;
        }

        $rawValues = $_POST['campwp_track_metadata'] ?? [];
        if (! is_array($rawValues)) {
            $rawValues = [];
        }

        $values = wp_unslash($rawValues);

        $this->updateMeta($postId, MetadataKeys::TRACK_NUMBER, $this->sanitizer->sanitizePositiveInteger((string) ($values['track_number'] ?? '0')));
        $this->updateMeta($postId, MetadataKeys::TRACK_SUBTITLE, $this->sanitizer->sanitizeText((string) ($values['subtitle'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_DURATION, $this->sanitizer->sanitizeDuration((string) ($values['duration'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_ARTIST_DISPLAY, $this->sanitizer->sanitizeText((string) ($values['artist_display_name'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_CREDITS, $this->sanitizer->sanitizeTextarea((string) ($values['credits'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_LYRICS, $this->sanitizer->sanitizeTextarea((string) ($values['lyrics'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_ISRC, $this->sanitizer->sanitizeIsrc((string) ($values['isrc'] ?? '')));
        $this->updateMeta($postId, MetadataKeys::TRACK_ARTWORK_ID, $this->sanitizer->sanitizeAttachmentId((string) ($values['artwork_id'] ?? '0')));
    }

    private function isValidNonce(string $nonceName, string $nonceAction): bool
    {
        if (! isset($_POST[$nonceName])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST[$nonceName]));

        return wp_verify_nonce($nonce, $nonceAction) === 1;
    }

    /**
     * @param int|string $value
     */
    private function updateMeta(int $postId, string $metaKey, $value): void
    {
        if ($value === '' || $value === 0) {
            delete_post_meta($postId, $metaKey);
            return;
        }

        update_post_meta($postId, $metaKey, $value);
    }

    private function getMetaValue(int $postId, string $metaKey): string
    {
        return (string) get_post_meta($postId, $metaKey, true);
    }

    private function getMetaIntegerValue(int $postId, string $metaKey): int
    {
        return (int) get_post_meta($postId, $metaKey, true);
    }

    private function renderTextField(string $name, string $label, string $value, bool $required = false): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<input type="text" class="widefat" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . ($required ? ' required="required"' : '') . ' />';
        echo '</label>';
        echo '</p>';
    }

    private function renderDateField(string $name, string $label, string $value, bool $required = false): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<input type="date" class="widefat" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . ($required ? ' required="required"' : '') . ' />';
        echo '</label>';
        echo '</p>';
    }

    private function renderNumberField(string $name, string $label, string $value, bool $required = false): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<input type="number" min="0" step="1" class="widefat" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . ($required ? ' required="required"' : '') . ' />';
        echo '</label>';
        echo '</p>';
    }

    private function renderTextareaField(string $name, string $label, string $value): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<textarea class="widefat" rows="6" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea>';
        echo '</label>';
        echo '</p>';
    }

    /**
     * @param list<string> $options
     */
    private function renderSelectField(string $name, string $label, string $value, array $options): void
    {
        echo '<p>';
        echo '<label>';
        echo '<strong>' . esc_html($label) . '</strong><br />';
        echo '<select class="widefat" name="' . esc_attr($name) . '">';

        foreach ($options as $option) {
            $label = $option === 'ep' ? 'EP' : ucfirst($option);
            echo '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>' . esc_html($label) . '</option>';
        }

        echo '</select>';
        echo '</label>';
        echo '</p>';
    }

    private function getAlbumPostType(): string
    {
        $postTypes = apply_filters('campwp_album_post_types', ['campwp_album']);

        if (! is_array($postTypes) || $postTypes === []) {
            return 'campwp_album';
        }

        $firstPostType = reset($postTypes);

        if (! is_string($firstPostType) || $firstPostType === '') {
            return 'campwp_album';
        }

        return sanitize_key($firstPostType);
    }

    private function getTrackPostType(): string
    {
        $postType = apply_filters('campwp_track_post_type', 'campwp_track');

        if (! is_string($postType) || $postType === '') {
            return 'campwp_track';
        }

        return sanitize_key($postType);
    }
}
