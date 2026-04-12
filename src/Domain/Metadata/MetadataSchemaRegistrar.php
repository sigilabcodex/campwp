<?php

declare(strict_types=1);

namespace CampWP\Domain\Metadata;

final class MetadataSchemaRegistrar
{
    private MetadataSanitizer $sanitizer;

    public function __construct(?MetadataSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerMeta']);
    }

    public function registerMeta(): void
    {
        $trackPostType = $this->getTrackPostType();
        $albumPostType = $this->getAlbumPostType();

        $this->registerTrackRelationshipMeta($trackPostType);
        $this->registerAlbumMetadata($albumPostType);
        $this->registerTrackMetadata($trackPostType);
        $this->registerDownloadMetadata($albumPostType, $trackPostType);
    }

    private function registerTrackRelationshipMeta(string $trackPostType): void
    {
        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_ALBUM_ID,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => 'absint',
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_ORDER,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => static fn ($value): int => max(0, absint($value)),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function registerAlbumMetadata(string $albumPostType): void
    {
        $this->registerTextMeta($albumPostType, MetadataKeys::ALBUM_SUBTITLE);
        $this->registerTextMeta($albumPostType, MetadataKeys::ALBUM_CATALOG_NUMBER);
        $this->registerTextMeta($albumPostType, MetadataKeys::ALBUM_ARTIST_DISPLAY);
        $this->registerTextMeta($albumPostType, MetadataKeys::ALBUM_LABEL_NAME);
        $this->registerTextareaMeta($albumPostType, MetadataKeys::ALBUM_CREDITS_OVERRIDE);
        $this->registerTextareaMeta($albumPostType, MetadataKeys::ALBUM_RELEASE_NOTES);
        $this->registerReleaseTypeMeta($albumPostType);
        $this->registerBonusItemsMeta($albumPostType);

        register_post_meta(
            $albumPostType,
            MetadataKeys::ALBUM_RELEASE_DATE,
            [
                'type' => 'string',
                'single' => true,
                'default' => '',
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): string => $this->sanitizer->sanitizeReleaseDate((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function registerReleaseTypeMeta(string $albumPostType): void
    {
        register_post_meta(
            $albumPostType,
            MetadataKeys::ALBUM_RELEASE_TYPE,
            [
                'type' => 'string',
                'single' => true,
                'default' => 'album',
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): string => $this->sanitizer->sanitizeReleaseType((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function registerBonusItemsMeta(string $albumPostType): void
    {
        register_post_meta(
            $albumPostType,
            MetadataKeys::ALBUM_BONUS_ITEMS,
            [
                'type' => 'string',
                'single' => true,
                'default' => '[]',
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): string => $this->sanitizer->sanitizeBonusItems($value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function registerTrackMetadata(string $trackPostType): void
    {
        $this->registerTextMeta($trackPostType, MetadataKeys::TRACK_SUBTITLE);
        $this->registerTextMeta($trackPostType, MetadataKeys::TRACK_ARTIST_DISPLAY);
        $this->registerTextareaMeta($trackPostType, MetadataKeys::TRACK_CREDITS);
        $this->registerTextareaMeta($trackPostType, MetadataKeys::TRACK_LYRICS);

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_NUMBER,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): int => $this->sanitizer->sanitizePositiveInteger((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_DURATION,
            [
                'type' => 'string',
                'single' => true,
                'default' => '',
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): string => $this->sanitizer->sanitizeDuration((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_ISRC,
            [
                'type' => 'string',
                'single' => true,
                'default' => '',
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): string => $this->sanitizer->sanitizeIsrc((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_ARTWORK_ID,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
        
        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE,
            [
                'type' => 'string',
                'single' => true,
                'default' => 'attachment',
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): string => $this->sanitizer->sanitizeTrackAudioSourceType((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_AUDIO_EXTERNAL_URL,
            [
                'type' => 'string',
                'single' => true,
                'default' => '',
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): string => $this->sanitizer->sanitizeTrackAudioExternalUrl((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_AUDIO_OGG_ATTACHMENT_ID,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_AUDIO_STREAMING_ATTACHMENT_ID,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );

        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_AUDIO_SOURCE_CLASSIFICATION,
            [
                'type' => 'string',
                'single' => true,
                'default' => 'unknown',
                'show_in_rest' => true,
                'sanitize_callback' => static function ($value): string {
                    $classification = sanitize_key((string) $value);
                    return in_array($classification, ['lossless', 'lossy', 'unknown'], true) ? $classification : 'unknown';
                },
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }


    private function registerDownloadMetadata(string $albumPostType, string $trackPostType): void
    {
        $this->registerDownloadMetaForPostType($albumPostType, MetadataKeys::ALBUM_DOWNLOAD_ENABLED, 0);
        $this->registerDownloadModeMeta($albumPostType, MetadataKeys::ALBUM_DOWNLOAD_MODE);
        $this->registerProductMeta($albumPostType, MetadataKeys::ALBUM_PRODUCT_ID);

        $this->registerDownloadMetaForPostType($trackPostType, MetadataKeys::TRACK_DOWNLOAD_ENABLED, 1);
        $this->registerDownloadModeMeta($trackPostType, MetadataKeys::TRACK_DOWNLOAD_MODE);
        $this->registerProductMeta($trackPostType, MetadataKeys::TRACK_PRODUCT_ID);
    }

    private function registerDownloadMetaForPostType(string $postType, string $metaKey, int $default): void
    {
        register_post_meta(
            $postType,
            $metaKey,
            [
                'type' => 'integer',
                'single' => true,
                'default' => $default,
                'show_in_rest' => true,
                'sanitize_callback' => static fn ($value): int => in_array((string) $value, ['1', 'yes', 'on', 'true'], true) ? 1 : 0,
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function registerDownloadModeMeta(string $postType, string $metaKey): void
    {
        register_post_meta(
            $postType,
            $metaKey,
            [
                'type' => 'string',
                'single' => true,
                'default' => 'public',
                'show_in_rest' => true,
                'sanitize_callback' => static function ($value): string {
                    $mode = sanitize_key((string) $value);
                    return in_array($mode, ['public', 'restricted', 'purchase'], true) ? $mode : 'public';
                },
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function registerProductMeta(string $postType, string $metaKey): void
    {
        register_post_meta(
            $postType,
            $metaKey,
            [
                'type' => 'integer',
                'single' => true,
                'default' => 0,
                'show_in_rest' => true,
                'sanitize_callback' => 'absint',
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function registerTextMeta(string $postType, string $metaKey): void
    {
        register_post_meta(
            $postType,
            $metaKey,
            [
                'type' => 'string',
                'single' => true,
                'default' => '',
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): string => $this->sanitizer->sanitizeText((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function registerTextareaMeta(string $postType, string $metaKey): void
    {
        register_post_meta(
            $postType,
            $metaKey,
            [
                'type' => 'string',
                'single' => true,
                'default' => '',
                'show_in_rest' => true,
                'sanitize_callback' => fn ($value): string => $this->sanitizer->sanitizeTextarea((string) $value),
                'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
            ]
        );
    }

    private function getAlbumPostType(): string
    {
        $postType = apply_filters('campwp_album_post_types', ['campwp_album']);

        if (! is_array($postType) || $postType === []) {
            return 'campwp_album';
        }

        $firstPostType = reset($postType);

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
