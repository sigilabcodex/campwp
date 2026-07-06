<?php

declare(strict_types=1);

namespace CampWP\Application\Import;

use CampWP\Domain\Import\CoverManifest;
use CampWP\Domain\Import\ReleaseManifest;
use CampWP\Domain\Import\TrackManifest;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Domain\Metadata\MetadataSanitizer;

final class ManifestNormalizer
{
    private const SUPPORTED_SCHEMA_VERSIONS = ['campwp-release-manifest-v1', 'campwp-mdk-import-example-v0'];
    private const MAX_TRACKS = 300;
    private const MAX_STRING_LENGTH = 5000;
    private const ALLOWED_POST_STATUSES = ['draft', 'pending', 'private'];

    private MetadataSanitizer $sanitizer;

    public function __construct(?MetadataSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{manifest: ReleaseManifest|null, errors: list<string>, warnings: list<string>}
     */
    public function normalize(array $manifest): array
    {
        $errors = [];
        $warnings = [];

        $schemaVersion = $this->stringField($manifest, 'schema_version', false, $errors);
        if ($schemaVersion === '' || ! in_array($schemaVersion, self::SUPPORTED_SCHEMA_VERSIONS, true)) {
            $errors[] = 'schema_version is unsupported.';
        }

        $album = $this->albumShape($manifest, $errors);
        $externalReleaseId = $this->firstString($album, ['external_release_id', 'release_id'], true, $errors, 'release identity');
        $externalReleaseId = $this->sanitizer->sanitizeExternalId($externalReleaseId);
        if ($externalReleaseId === '') {
            $errors[] = 'release identity is missing or invalid.';
        }

        $provider = $this->providerFrom($album);
        $provider = $this->sanitizer->sanitizeProvider($provider);
        if ($provider === '' && ($this->hasRemoteMedia($album) || $this->hasRemoteMedia($manifest))) {
            $errors[] = 'provider is required when remote media is present.';
        }

        $catalogIdentity = $this->stringField($album, 'catalog_key', true, $errors);
        $catalogIdentity = $catalogIdentity !== '' ? $this->sanitizer->sanitizeExternalId($catalogIdentity) : $provider;
        if ($catalogIdentity === '') {
            $errors[] = 'catalog identity is required.';
        }

        $title = $this->firstString($album, ['title'], true, $errors, 'title');
        $title = $this->sanitizer->sanitizeText($title);
        if ($title === '') {
            $errors[] = 'title is required.';
        }

        $tracksRaw = $this->tracksShape($manifest, $errors);
        if (count($tracksRaw) > self::MAX_TRACKS) {
            $errors[] = 'tracks exceeds the maximum supported count.';
        }

        $albumMeta = $this->normalizeAlbumMeta($album, $provider, $errors);
        $content = $this->firstString($album, ['description', 'content'], true, $errors, 'description');
        $postStatus = $this->postStatus($album, $errors);
        $tracks = $this->normalizeTracks($tracksRaw, $externalReleaseId, $provider, $schemaVersion, $errors);
        $cover = $this->normalizeCover($album, $provider, $errors);

        if ($errors !== []) {
            return ['manifest' => null, 'errors' => array_values(array_unique($errors)), 'warnings' => $warnings];
        }

        return [
            'manifest' => new ReleaseManifest(
                $schemaVersion,
                $catalogIdentity,
                $provider,
                $externalReleaseId,
                $title,
                $this->sanitizer->sanitizeTextarea($content),
                $postStatus,
                $albumMeta,
                $tracks,
                $cover
            ),
            'errors' => [],
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<string> $errors
     * @return array<string, mixed>
     */
    private function albumShape(array $manifest, array &$errors): array
    {
        if (isset($manifest['album'])) {
            if (! is_array($manifest['album']) || array_is_list($manifest['album'])) {
                $errors[] = 'album must be an object.';
                return [];
            }

            return $manifest['album'];
        }

        return $manifest;
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<string> $errors
     * @return list<array<string, mixed>>
     */
    private function tracksShape(array $manifest, array &$errors): array
    {
        $tracks = $manifest['tracks'] ?? null;
        if (! is_array($tracks) || ! array_is_list($tracks)) {
            $errors[] = 'tracks must be an array.';
            return [];
        }

        $normalized = [];
        foreach ($tracks as $index => $track) {
            if (! is_array($track) || array_is_list($track)) {
                $errors[] = sprintf('tracks[%d] must be an object.', $index);
                continue;
            }

            $normalized[] = $track;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $album
     * @param list<string> $errors
     * @return array<string, mixed>
     */
    private function normalizeAlbumMeta(array $album, string $provider, array &$errors): array
    {
        $meta = [];
        $this->setIfNotEmpty($meta, MetadataKeys::ALBUM_SOURCE_PROVIDER, $provider);
        $catalogIdentity = $this->stringField($album, 'catalog_key', true, $errors, 'catalog identity');
        $catalogIdentity = $catalogIdentity !== '' ? $catalogIdentity : $provider;
        $this->setSanitizedExternalId($meta, MetadataKeys::ALBUM_CATALOG_IDENTITY, $catalogIdentity, $errors, 'catalog identity');
        $this->setSanitizedExternalId($meta, MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID, $this->firstString($album, ['external_release_id', 'release_id'], true, $errors, 'release identity'), $errors, 'release identity');
        $this->setRemoteUrl($meta, MetadataKeys::ALBUM_EXTERNAL_ITEM_URL, $this->firstString($album, ['external_item_url', 'internet_archive_url'], true, $errors, 'item URL'), $provider, $errors, 'item URL');
        $this->setInternetArchiveIdentifier($meta, $this->firstString($album, ['internet_archive_identifier', 'archive_identifier'], true, $errors, 'Internet Archive identifier'), $errors);

        $archiveMetadata = isset($album['archive_metadata']) && is_array($album['archive_metadata']) ? $album['archive_metadata'] : [];
        $this->setRemoteUrl($meta, MetadataKeys::ALBUM_INTERNET_ARCHIVE_METADATA_URL, $this->firstString($archiveMetadata, ['metadata_api_url'], true, $errors, 'IA metadata URL'), 'internet_archive', $errors, 'IA metadata URL');
        $this->setRemoteUrl($meta, MetadataKeys::ALBUM_BANDCAMP_URL, $this->firstString($album, ['bandcamp_url'], true, $errors, 'Bandcamp URL'), 'bandcamp', $errors, 'Bandcamp URL');
        $this->setRemoteUrl($meta, MetadataKeys::ALBUM_PROJECT_URL, $this->firstString($album, ['project_url'], true, $errors, 'project URL'), 'direct', $errors, 'project URL');

        $license = isset($album['license']) && is_array($album['license']) ? $album['license'] : [];
        $this->setText($meta, MetadataKeys::ALBUM_LICENSE_NAME, $this->firstString($license, ['name'], true, $errors, 'license name'));
        $this->setFormat($meta, MetadataKeys::ALBUM_LICENSE_CODE, $this->firstString($license, ['code', 'short_name'], true, $errors, 'license code'), $errors, 'license code');
        $this->setLicenseUrl($meta, MetadataKeys::ALBUM_LICENSE_URL, $this->firstString($license, ['url'], true, $errors, 'license URL'), $errors);

        $cover = isset($album['cover']) && is_array($album['cover']) ? $album['cover'] : [];
        $coverProvider = $provider;
        $coverSource = $this->sanitizer->sanitizeProvider($this->stringField($cover, 'source', true, $errors, 'cover.source'));
        if ($coverSource !== '') {
            $coverProvider = $coverSource;
        }
        $this->setRemoteUrl($meta, MetadataKeys::ALBUM_REMOTE_COVER_URL, $this->firstString($cover, ['url'], true, $errors, 'remote cover URL'), $coverProvider, $errors, 'remote cover URL');

        $this->setText($meta, MetadataKeys::ALBUM_ARTIST_DISPLAY, $this->firstString($album, ['artist', 'artist_display'], true, $errors, 'artist'));
        $this->setText($meta, MetadataKeys::ALBUM_RELEASE_DATE, $this->sanitizer->sanitizeReleaseDate($this->firstString($album, ['release_date'], true, $errors, 'release date')));
        $this->setText($meta, MetadataKeys::ALBUM_CREDITS_OVERRIDE, $this->firstString($album, ['credits'], true, $errors, 'credits'));
        $this->setText($meta, MetadataKeys::ALBUM_CATALOG_NUMBER, $this->firstString($album, ['catalog_number', 'release_id'], true, $errors, 'catalog number'));

        $provenance = isset($album['provenance']) && is_array($album['provenance']) ? $album['provenance'] : [];
        $this->setChecksum($meta, MetadataKeys::ALBUM_SOURCE_PAYLOAD_HASH, $this->firstString($provenance, ['payload_hash'], true, $errors, 'payload hash'), $errors, 'payload hash');

        $syncState = isset($album['sync_state']) && is_array($album['sync_state']) ? $album['sync_state'] : [];
        $this->setTimestamp($meta, MetadataKeys::ALBUM_LAST_SYNCED_AT, $this->firstString($syncState, ['last_synced_at'], true, $errors, 'last synced at'), $errors, 'last synced at');
        $this->setSyncStatus($meta, MetadataKeys::ALBUM_SYNC_STATUS, $this->firstString($syncState, ['sync_status'], true, $errors, 'sync status'), $errors, 'sync status');

        return $meta;
    }

    /**
     * @param array<string, mixed> $album
     * @param list<string> $errors
     */
    private function normalizeCover(array $album, string $provider, array &$errors): ?CoverManifest
    {
        if (! array_key_exists('cover', $album) || $album['cover'] === null) {
            return null;
        }

        if (! is_array($album['cover']) || array_is_list($album['cover'])) {
            $errors[] = 'cover must be an object.';
            return null;
        }

        $cover = $album['cover'];
        $strategy = $this->stringField($cover, 'strategy', true, $errors, 'cover.strategy');
        if ($strategy === '') {
            return null;
        }

        if ($strategy !== 'sideload_featured_image') {
            $errors[] = 'cover.strategy is unsupported.';
            return null;
        }

        $source = $this->sanitizer->sanitizeProvider($this->stringField($cover, 'source', true, $errors, 'cover.source'));
        if ($source === '') {
            $source = $provider;
        }
        if ($source === '') {
            $errors[] = 'cover.source is required.';
        }

        $externalId = $this->sanitizer->sanitizeExternalId($this->stringField($cover, 'external_id', false, $errors, 'cover.external_id'));
        if ($externalId === '') {
            $errors[] = 'cover.external_id is required or invalid.';
        }

        $url = $this->stringField($cover, 'url', false, $errors, 'cover.url');
        $url = $this->sanitizer->sanitizeRemoteUrl($url, $source !== '' ? $source : $provider);
        if ($url === '') {
            $errors[] = 'cover.url is unsafe or unsupported.';
        }

        $filename = $this->stringField($cover, 'filename', false, $errors, 'cover.filename');
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = $this->sanitizer->sanitizeText($filename);
        if ($filename === '' || str_contains($filename, '/') || str_contains($filename, '\\')) {
            $errors[] = 'cover.filename is required or invalid.';
        }

        $mimeType = strtolower($this->stringField($cover, 'mime_type', false, $errors, 'cover.mime_type'));
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            $errors[] = 'cover.mime_type is unsupported.';
        }

        $payloadHash = $this->stringField($cover, 'payload_hash', true, $errors, 'cover.payload_hash');
        if ($payloadHash !== '') {
            $payloadHash = $this->sanitizer->sanitizeChecksum($payloadHash);
            if ($payloadHash === '') {
                $errors[] = 'cover.payload_hash is invalid.';
            }
        }

        if ($source === '' || $externalId === '' || $url === '' || $filename === '' || $mimeType === '') {
            return null;
        }

        return new CoverManifest($source, $externalId, $url, $filename, $mimeType, $strategy, $payloadHash);
    }

    /**
     * @param list<array<string, mixed>> $tracksRaw
     * @param list<string> $errors
     * @return list<TrackManifest>
     */
    private function normalizeTracks(array $tracksRaw, string $releaseId, string $albumProvider, string $schemaVersion, array &$errors): array
    {
        $tracks = [];
        $ids = [];
        $indexes = [];

        foreach ($tracksRaw as $offset => $track) {
            $context = sprintf('tracks[%d]', $offset);
            $index = $this->positiveIndex($track, $errors, $context);
            $trackNumber = max(1, (int) ($track['track_number'] ?? $index));
            $title = $this->stringField($track, 'title', false, $errors, $context . '.title');
            $title = $this->sanitizer->sanitizeText($title);
            if ($title === '') {
                $errors[] = $context . '.title is required.';
            }

            $externalTrackId = $this->stringField($track, 'external_track_id', true, $errors, $context . '.external_track_id');
            if ($externalTrackId === '') {
                $externalTrackId = $this->deriveLegacyTrackIdentity($track, $releaseId, $schemaVersion, $errors, $context);
            }
            $externalTrackId = $this->sanitizer->sanitizeExternalId($externalTrackId);
            if ($externalTrackId === '') {
                $errors[] = $context . '.external_track_id is invalid or missing stable fallback identity.';
            }

            if (isset($ids[$externalTrackId])) {
                $errors[] = 'Track external IDs must be unique.';
            }
            $ids[$externalTrackId] = true;

            if (isset($indexes[$index])) {
                $errors[] = 'Track indexes must be unique.';
            }
            $indexes[$index] = true;

            $provider = $this->sanitizer->sanitizeProvider($this->stringField($track, 'source_provider', true, $errors, $context . '.source_provider'));
            if ($provider === '') {
                $provider = $albumProvider;
            }

            $sourceType = $this->trackSourceType($track, $provider);
            if ($sourceType === '') {
                $errors[] = $context . '.source_type is unsupported.';
                $sourceType = 'attachment';
            }

            $meta = [
                MetadataKeys::TRACK_EXTERNAL_TRACK_ID => $externalTrackId,
                MetadataKeys::TRACK_EXTERNAL_TRACK_INDEX => $index,
                MetadataKeys::TRACK_NUMBER => $trackNumber,
                MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => $sourceType,
            ];
            $this->setIfNotEmpty($meta, MetadataKeys::TRACK_SOURCE_PROVIDER, $provider);
            $this->setText($meta, MetadataKeys::TRACK_DURATION, $this->stringField($track, 'duration', true, $errors, $context . '.duration'));
            $this->setText($meta, MetadataKeys::TRACK_CREDITS, $this->stringField($track, 'credits', true, $errors, $context . '.credits'));
            $this->setRemoteUrl($meta, MetadataKeys::TRACK_AUDIO_ORIGINAL_URL, $this->firstString($track, ['original_url', 'original_flac_url'], true, $errors, $context . '.original URL'), $provider, $errors, $context . '.original URL');
            $this->setRemoteUrl($meta, MetadataKeys::TRACK_AUDIO_PLAYBACK_URL, $this->firstString($track, ['playback_url', 'derived_mp3_url'], true, $errors, $context . '.playback URL'), $provider, $errors, $context . '.playback URL');
            $this->setRemoteUrl($meta, MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL, $this->firstString($track, ['download_url'], true, $errors, $context . '.download URL'), $provider, $errors, $context . '.download URL');
            $this->setFormat($meta, MetadataKeys::TRACK_AUDIO_ORIGINAL_FORMAT, $this->stringField($track, 'original_format', true, $errors, $context . '.original_format'), $errors, $context . '.original_format');
            $this->setFormat($meta, MetadataKeys::TRACK_AUDIO_PLAYBACK_FORMAT, $this->stringField($track, 'playback_format', true, $errors, $context . '.playback_format'), $errors, $context . '.playback_format');
            $this->setNonNegativeSize($meta, MetadataKeys::TRACK_AUDIO_ORIGINAL_SIZE, $track['original_size'] ?? null, $errors, $context . '.original_size');
            $this->setNonNegativeSize($meta, MetadataKeys::TRACK_AUDIO_PLAYBACK_SIZE, $track['playback_size'] ?? null, $errors, $context . '.playback_size');
            $this->setChecksum($meta, MetadataKeys::TRACK_AUDIO_ORIGINAL_CHECKSUM, $this->stringField($track, 'original_checksum', true, $errors, $context . '.original_checksum'), $errors, $context . '.original_checksum');
            $this->setChecksum($meta, MetadataKeys::TRACK_AUDIO_PLAYBACK_CHECKSUM, $this->stringField($track, 'playback_checksum', true, $errors, $context . '.playback_checksum'), $errors, $context . '.playback_checksum');
            $this->setDerivativeStatus($meta, MetadataKeys::TRACK_REMOTE_DERIVATIVE_STATUS, $this->firstString($track, ['derivative_status', 'derivative_state'], true, $errors, $context . '.derivative_status'), $errors, $context . '.derivative_status');

            $tracks[] = new TrackManifest($externalTrackId, $index, $trackNumber, $title, $sourceType, $provider, $meta);
        }

        return $tracks;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function providerFrom(array $data): string
    {
        $provider = $this->stringField($data, 'source_provider', true, $ignored, 'source provider');
        if ($provider !== '') {
            return $provider;
        }

        $syncState = isset($data['sync_state']) && is_array($data['sync_state']) ? $data['sync_state'] : [];
        return $this->stringField($syncState, 'source_provider', true, $ignored, 'source provider');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasRemoteMedia(array $data): bool
    {
        if ($this->firstString($data, ['internet_archive_url', 'bandcamp_url', 'project_url'], true, $ignored, 'remote URL') !== '') {
            return true;
        }

        $cover = isset($data['cover']) && is_array($data['cover']) ? $data['cover'] : [];
        if ($this->stringField($cover, 'url', true, $ignored, 'cover URL') !== '') {
            return true;
        }

        foreach (($data['tracks'] ?? []) as $track) {
            if (is_array($track) && $this->firstString($track, ['playback_url', 'derived_mp3_url', 'download_url', 'original_url', 'original_flac_url'], true, $ignored, 'track URL') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $track
     * @param list<string> $errors
     */
    private function positiveIndex(array $track, array &$errors, string $context): int
    {
        $value = $track['index'] ?? $track['position'] ?? $track['track_number'] ?? null;
        if (! is_int($value) && (! is_string($value) || preg_match('/^\d+$/', $value) !== 1)) {
            $errors[] = $context . '.index must be a positive integer.';
            return 0;
        }

        $index = (int) $value;
        if ($index <= 0) {
            $errors[] = $context . '.index must be positive.';
        }

        return $index;
    }

    /**
     * @param array<string, mixed> $track
     */
    private function trackSourceType(array $track, string $provider): string
    {
        $sourceType = $this->stringField($track, 'source_type', true, $ignored, 'source type');
        if ($sourceType === '' && $provider === 'internet_archive' && $this->firstString($track, ['derived_mp3_url', 'original_flac_url', 'download_url'], true, $ignored, 'remote URL') !== '') {
            $sourceType = 'internet_archive';
        }

        if ($sourceType === '') {
            return 'attachment';
        }

        $sanitized = $this->sanitizer->sanitizeTrackAudioSourceType($sourceType);
        return $sanitized === $sourceType ? $sanitized : '';
    }

    /**
     * @param array<string, mixed> $album
     * @param list<string> $errors
     */
    private function postStatus(array $album, array &$errors): string
    {
        if (! array_key_exists('post_status', $album) || $album['post_status'] === null || $album['post_status'] === '') {
            return 'draft';
        }

        $status = $this->stringField($album, 'post_status', true, $errors, 'post_status');
        $status = sanitize_key($status);
        if (! in_array($status, self::ALLOWED_POST_STATUSES, true)) {
            $errors[] = 'post_status is unsupported.';
            return 'draft';
        }

        return $status;
    }

    /**
     * @param array<string, mixed> $track
     * @param list<string> $errors
     */
    private function deriveLegacyTrackIdentity(array $track, string $releaseId, string $schemaVersion, array &$errors, string $context): string
    {
        if ($schemaVersion !== 'campwp-mdk-import-example-v0') {
            $errors[] = $context . '.external_track_id is required.';
            return '';
        }

        $stable = $this->firstString($track, ['original_filename'], true, $errors, $context . '.original_filename');
        if ($stable === '') {
            foreach (['original_url', 'original_flac_url', 'download_url', 'playback_url', 'derived_mp3_url'] as $key) {
                $url = $this->stringField($track, $key, true, $errors, $context . '.' . $key);
                if ($url === '') {
                    continue;
                }

                $path = wp_parse_url($url, PHP_URL_PATH);
                if (is_string($path) && $path !== '') {
                    $basename = basename($path);
                    if ($basename !== '' && $basename !== '.' && $basename !== '/') {
                        $stable = rawurldecode($basename);
                        break;
                    }
                }
            }
        }

        $stable = trim($stable);
        if ($stable === '') {
            $errors[] = $context . '.external_track_id is required because no stable file identity is available.';
            return '';
        }

        return sprintf('%s:file:%s', $releaseId, substr(hash('sha256', strtolower($stable)), 0, 24));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $errors
     */
    private function stringField(array $data, string $key, bool $optional, ?array &$errors = null, string $label = ''): string
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return '';
        }

        if (! is_string($data[$key]) && ! is_int($data[$key])) {
            if (! $optional && is_array($errors)) {
                $errors[] = ($label !== '' ? $label : $key) . ' must be a string.';
            } elseif (is_array($errors)) {
                $errors[] = ($label !== '' ? $label : $key) . ' must be a string when provided.';
            }
            return '';
        }

        $value = trim((string) $data[$key]);
        if (strlen($value) > self::MAX_STRING_LENGTH) {
            if (is_array($errors)) {
                $errors[] = ($label !== '' ? $label : $key) . ' exceeds the maximum length.';
            }
            return '';
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     * @param list<string> $errors
     */
    private function firstString(array $data, array $keys, bool $optional, ?array &$errors, string $label): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                return $this->stringField($data, $key, $optional, $errors, $label);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $meta */
    private function setIfNotEmpty(array &$meta, string $key, mixed $value): void
    {
        if ($value !== '' && $value !== null) {
            $meta[$key] = $value;
        }
    }

    /** @param array<string, mixed> $meta */
    private function setText(array &$meta, string $key, string $value): void
    {
        $this->setIfNotEmpty($meta, $key, $this->sanitizer->sanitizeText($value));
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setSanitizedExternalId(array &$meta, string $key, string $value, array &$errors, string $label): void
    {
        if ($value === '') {
            return;
        }

        $sanitized = $this->sanitizer->sanitizeExternalId($value);
        if ($sanitized === '') {
            $errors[] = $label . ' is invalid.';
            return;
        }

        $meta[$key] = $sanitized;
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setInternetArchiveIdentifier(array &$meta, string $value, array &$errors): void
    {
        if ($value === '') {
            return;
        }

        $sanitized = $this->sanitizer->sanitizeInternetArchiveIdentifier($value);
        if ($sanitized === '') {
            $errors[] = 'Internet Archive identifier is invalid.';
            return;
        }

        $meta[MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER] = $sanitized;
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setRemoteUrl(array &$meta, string $key, string $value, string $provider, array &$errors, string $label): void
    {
        if ($value === '') {
            return;
        }

        $sanitized = $this->sanitizer->sanitizeRemoteUrl($value, $provider !== '' ? $provider : 'direct');
        if ($sanitized === '') {
            $errors[] = $label . ' is unsafe or unsupported.';
            return;
        }

        $meta[$key] = $sanitized;
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setLicenseUrl(array &$meta, string $key, string $value, array &$errors): void
    {
        if ($value === '') {
            return;
        }

        $sanitized = $this->sanitizer->sanitizeLicenseUrl($value);
        if ($sanitized === '') {
            $errors[] = 'license URL is unsafe or unsupported.';
            return;
        }

        $meta[$key] = $sanitized;
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setChecksum(array &$meta, string $key, string $value, array &$errors, string $label): void
    {
        if ($value === '') {
            return;
        }

        $sanitized = $this->sanitizer->sanitizeChecksum($value);
        if ($sanitized === '') {
            $errors[] = $label . ' is invalid.';
            return;
        }

        $meta[$key] = $sanitized;
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setTimestamp(array &$meta, string $key, string $value, array &$errors, string $label): void
    {
        if ($value === '') {
            return;
        }

        $sanitized = $this->sanitizer->sanitizeIso8601Timestamp($value);
        if ($sanitized === '') {
            $errors[] = $label . ' is invalid.';
            return;
        }

        $meta[$key] = $sanitized;
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setSyncStatus(array &$meta, string $key, string $value, array &$errors, string $label): void
    {
        if ($value === '') {
            return;
        }

        $sanitized = $this->sanitizer->sanitizeSyncStatus($value);
        if ($sanitized !== $value) {
            $errors[] = $label . ' is invalid.';
            return;
        }

        $meta[$key] = $sanitized;
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setDerivativeStatus(array &$meta, string $key, string $value, array &$errors, string $label): void
    {
        if ($value === '') {
            return;
        }

        $sanitized = $this->sanitizer->sanitizeDerivativeStatus($value);
        if ($sanitized !== $value) {
            $errors[] = $label . ' is invalid.';
            return;
        }

        $meta[$key] = $sanitized;
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setFormat(array &$meta, string $key, string $value, array &$errors, string $label): void
    {
        if ($value === '') {
            return;
        }

        $sanitized = $this->sanitizer->sanitizeFormatName($value);
        if ($sanitized === '') {
            $errors[] = $label . ' is invalid.';
            return;
        }

        $meta[$key] = $sanitized;
    }

    /** @param array<string, mixed> $meta @param list<string> $errors */
    private function setNonNegativeSize(array &$meta, string $key, mixed $value, array &$errors, string $label): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_int($value) && (! is_string($value) || preg_match('/^\d+$/', $value) !== 1)) {
            $errors[] = $label . ' must be a non-negative integer.';
            return;
        }

        $meta[$key] = (int) $value;
    }
}
