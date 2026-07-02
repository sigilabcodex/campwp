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
        register_post_meta($trackPostType, MetadataKeys::TRACK_ALBUM_ID, $this->integerMetaArgs('absint'));
        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_ORDER,
            $this->integerMetaArgs(static fn ($value): int => max(0, absint($value)))
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
        $this->registerAlbumExternalSourceMetadata($albumPostType);

        register_post_meta(
            $albumPostType,
            MetadataKeys::ALBUM_RELEASE_DATE,
            $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeReleaseDate((string) $value))
        );
    }

    private function registerAlbumExternalSourceMetadata(string $albumPostType): void
    {
        register_post_meta($albumPostType, MetadataKeys::ALBUM_SOURCE_PROVIDER, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeProvider((string) $value), ''));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_CATALOG_IDENTITY, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeExternalId((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeExternalId((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_EXTERNAL_ITEM_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeRemoteUrl((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeInternetArchiveIdentifier((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_INTERNET_ARCHIVE_METADATA_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeRemoteUrl((string) $value, 'internet_archive')));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_BANDCAMP_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeRemoteUrl((string) $value, 'bandcamp')));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_PROJECT_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeRemoteUrl((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_LICENSE_NAME, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeText((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_LICENSE_CODE, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeFormatName((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_LICENSE_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeLicenseUrl((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_REMOTE_COVER_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeRemoteUrl((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_SOURCE_PAYLOAD_HASH, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeSourcePayloadHash((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_LAST_SYNCED_AT, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeIso8601Timestamp((string) $value)));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_SYNC_STATUS, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeSyncStatus((string) $value), 'never_synced'));
        register_post_meta($albumPostType, MetadataKeys::ALBUM_SYNC_MESSAGE, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeTextarea((string) $value)));
    }

    private function registerReleaseTypeMeta(string $albumPostType): void
    {
        register_post_meta(
            $albumPostType,
            MetadataKeys::ALBUM_RELEASE_TYPE,
            $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeReleaseType((string) $value), 'album')
        );
    }

    private function registerBonusItemsMeta(string $albumPostType): void
    {
        register_post_meta(
            $albumPostType,
            MetadataKeys::ALBUM_BONUS_ITEMS,
            $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeBonusItems($value), '[]')
        );
    }

    private function registerTrackMetadata(string $trackPostType): void
    {
        $this->registerTextMeta($trackPostType, MetadataKeys::TRACK_SUBTITLE);
        $this->registerTextMeta($trackPostType, MetadataKeys::TRACK_ARTIST_DISPLAY);
        $this->registerTextareaMeta($trackPostType, MetadataKeys::TRACK_CREDITS);
        $this->registerTextareaMeta($trackPostType, MetadataKeys::TRACK_LYRICS);

        register_post_meta($trackPostType, MetadataKeys::TRACK_NUMBER, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizePositiveInteger((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_DURATION, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeDuration((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_ISRC, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeIsrc((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_ARTWORK_ID, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_SOURCE_TYPE, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeTrackAudioSourceType((string) $value), 'attachment'));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_EXTERNAL_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeTrackAudioExternalUrl((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_OGG_ATTACHMENT_ID, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_STREAMING_ATTACHMENT_ID, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizeAttachmentId((string) $value)));
        register_post_meta(
            $trackPostType,
            MetadataKeys::TRACK_AUDIO_SOURCE_CLASSIFICATION,
            $this->stringMetaArgs(static function ($value): string {
                $classification = sanitize_key((string) $value);
                return in_array($classification, ['lossless', 'lossy', 'unknown'], true) ? $classification : 'unknown';
            }, 'unknown')
        );
        $this->registerTrackExternalSourceMetadata($trackPostType);
    }

    private function registerTrackExternalSourceMetadata(string $trackPostType): void
    {
        register_post_meta($trackPostType, MetadataKeys::TRACK_EXTERNAL_TRACK_ID, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeExternalId((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_EXTERNAL_TRACK_INDEX, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizePositiveInteger((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_SOURCE_PROVIDER, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeProvider((string) $value), ''));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_ORIGINAL_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeRemoteUrl((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_PLAYBACK_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeRemoteUrl((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeRemoteUrl((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_ORIGINAL_FORMAT, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeFormatName((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_PLAYBACK_FORMAT, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeFormatName((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_ORIGINAL_SIZE, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizePositiveFileSize((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_PLAYBACK_SIZE, $this->integerMetaArgs(fn ($value): int => $this->sanitizer->sanitizePositiveFileSize((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_ORIGINAL_CHECKSUM, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeChecksum((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_AUDIO_PLAYBACK_CHECKSUM, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeChecksum((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_REMOTE_DERIVATIVE_STATUS, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeDerivativeStatus((string) $value), 'unknown'));
        register_post_meta($trackPostType, MetadataKeys::TRACK_SOURCE_PAYLOAD_HASH, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeSourcePayloadHash((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_LAST_SYNCED_AT, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeIso8601Timestamp((string) $value)));
        register_post_meta($trackPostType, MetadataKeys::TRACK_SYNC_STATUS, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeSyncStatus((string) $value), 'never_synced'));
        register_post_meta($trackPostType, MetadataKeys::TRACK_SYNC_MESSAGE, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeTextarea((string) $value)));
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
            $this->integerMetaArgs(static fn ($value): int => in_array((string) $value, ['1', 'yes', 'on', 'true'], true) ? 1 : 0, $default)
        );
    }

    private function registerDownloadModeMeta(string $postType, string $metaKey): void
    {
        register_post_meta(
            $postType,
            $metaKey,
            $this->stringMetaArgs(static function ($value): string {
                $mode = sanitize_key((string) $value);
                return in_array($mode, ['public', 'restricted', 'purchase'], true) ? $mode : 'public';
            }, 'public')
        );
    }

    private function registerProductMeta(string $postType, string $metaKey): void
    {
        register_post_meta($postType, $metaKey, $this->integerMetaArgs('absint'));
    }

    private function registerTextMeta(string $postType, string $metaKey): void
    {
        register_post_meta($postType, $metaKey, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeText((string) $value)));
    }

    private function registerTextareaMeta(string $postType, string $metaKey): void
    {
        register_post_meta($postType, $metaKey, $this->stringMetaArgs(fn ($value): string => $this->sanitizer->sanitizeTextarea((string) $value)));
    }

    /**
     * @param callable|string $sanitizeCallback
     * @return array<string, mixed>
     */
    private function stringMetaArgs($sanitizeCallback, string $default = ''): array
    {
        return [
            'type' => 'string',
            'single' => true,
            'default' => $default,
            'show_in_rest' => true,
            'sanitize_callback' => $sanitizeCallback,
            'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
        ];
    }

    /**
     * @param callable|string $sanitizeCallback
     * @return array<string, mixed>
     */
    private function integerMetaArgs($sanitizeCallback, int $default = 0): array
    {
        return [
            'type' => 'integer',
            'single' => true,
            'default' => $default,
            'show_in_rest' => true,
            'sanitize_callback' => $sanitizeCallback,
            'auth_callback' => static fn (): bool => current_user_can('edit_posts'),
        ];
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
