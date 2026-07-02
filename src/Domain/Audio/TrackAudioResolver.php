<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

use CampWP\Domain\Media\MediaAsset;
use CampWP\Domain\Media\MediaStorageProviderInterface;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Domain\Metadata\MetadataSanitizer;

final class TrackAudioResolver
{
    private const SOURCE_TYPE_ATTACHMENT = 'attachment';
    private const SOURCE_TYPE_EXTERNAL_URL = 'external_url';
    private const SOURCE_TYPE_INTERNET_ARCHIVE = 'internet_archive';

    private MediaStorageProviderInterface $storageProvider;
    private MetadataSanitizer $sanitizer;

    public function __construct(MediaStorageProviderInterface $storageProvider, ?MetadataSanitizer $sanitizer = null)
    {
        $this->storageProvider = $storageProvider;
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
    }

    public function getTrackPlaybackReferenceId(int $trackId): int
    {
        $streamingId = max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_STREAMING_ATTACHMENT_ID, true));
        if ($streamingId > 0) {
            return $streamingId;
        }

        $mp3Id = max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID, true));
        if ($mp3Id > 0) {
            return $mp3Id;
        }

        $oggId = max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_OGG_ATTACHMENT_ID, true));
        if ($oggId > 0) {
            return $oggId;
        }

        return $this->getTrackAudioReferenceId($trackId);
    }

    public function getTrackDownloadReferenceId(int $trackId): int
    {
        $sourceId = max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID, true));
        if ($sourceId > 0) {
            return $sourceId;
        }

        $mp3Id = max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID, true));
        if ($mp3Id > 0) {
            return $mp3Id;
        }

        $oggId = max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_OGG_ATTACHMENT_ID, true));
        if ($oggId > 0) {
            return $oggId;
        }

        $streamingId = max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_STREAMING_ATTACHMENT_ID, true));
        if ($streamingId > 0) {
            return $streamingId;
        }

        return max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, true));
    }

    public function getTrackAudioReferenceId(int $trackId): int
    {
        return $this->getTrackDownloadReferenceId($trackId);
    }

    public function getTrackAudioFile(int $trackId): ?TrackAudioFile
    {
        return $this->resolveTrackAudioFileFromReferenceId($this->getTrackAudioReferenceId($trackId));
    }

    public function getTrackPlaybackFile(int $trackId): ?TrackAudioFile
    {
        $sourceType = $this->getTrackSourceType($trackId);

        if ($sourceType === self::SOURCE_TYPE_EXTERNAL_URL) {
            return $this->resolveTrackAudioFileFromExternalUrl($trackId);
        }

        if ($sourceType === self::SOURCE_TYPE_INTERNET_ARCHIVE) {
            return $this->resolveInternetArchivePlaybackFile($trackId);
        }

        return $this->resolveTrackAudioFileFromReferenceId($this->getTrackPlaybackReferenceId($trackId));
    }

    public function getTrackDownloadFile(int $trackId): ?TrackAudioFile
    {
        $sourceType = $this->getTrackSourceType($trackId);

        if ($sourceType === self::SOURCE_TYPE_EXTERNAL_URL) {
            return $this->resolveTrackAudioFileFromExternalUrl($trackId);
        }

        if ($sourceType === self::SOURCE_TYPE_INTERNET_ARCHIVE) {
            return $this->resolveInternetArchiveDownloadFile($trackId);
        }

        return $this->resolveTrackAudioFileFromReferenceId($this->getTrackDownloadReferenceId($trackId));
    }

    private function resolveTrackAudioFileFromReferenceId(int $referenceId): ?TrackAudioFile
    {
        if ($referenceId <= 0) {
            return null;
        }

        $asset = $this->storageProvider->resolve($referenceId);

        if (! $asset instanceof MediaAsset || ! $this->storageProvider->isAudioReference($referenceId)) {
            return null;
        }

        return new TrackAudioFile($asset->getReferenceId(), $asset->getUrl(), $asset->getMimeType(), $asset->getFilePath());
    }

    public function isValidTrackAudioReference(int $referenceId): bool
    {
        return $referenceId > 0 && $this->storageProvider->isAudioReference($referenceId);
    }

    public function getTrackSourceType(int $trackId): string
    {
        $sourceType = sanitize_key((string) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_TYPE, true));

        if (in_array($sourceType, [self::SOURCE_TYPE_ATTACHMENT, self::SOURCE_TYPE_EXTERNAL_URL, self::SOURCE_TYPE_INTERNET_ARCHIVE], true)) {
            return $sourceType;
        }

        return self::SOURCE_TYPE_ATTACHMENT;
    }

    public function getTrackExternalAudioUrl(int $trackId): string
    {
        $url = trim((string) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_EXTERNAL_URL, true));
        if ($url === '') {
            return '';
        }

        $sanitized = esc_url_raw($url, ['http', 'https']);

        return is_string($sanitized) ? $sanitized : '';
    }

    private function resolveTrackAudioFileFromExternalUrl(int $trackId): ?TrackAudioFile
    {
        $url = $this->getTrackExternalAudioUrl($trackId);
        if ($url === '') {
            return null;
        }

        return new TrackAudioFile(0, $url, $this->detectMimeTypeForUrl($url), '');
    }

    private function resolveInternetArchivePlaybackFile(int $trackId): ?TrackAudioFile
    {
        $playbackUrl = $this->getStrictRemoteAudioUrl($trackId, MetadataKeys::TRACK_AUDIO_PLAYBACK_URL);
        if ($playbackUrl !== '') {
            return new TrackAudioFile(0, $playbackUrl, $this->detectMimeTypeForUrl($playbackUrl), '');
        }

        $legacyExternal = $this->resolveTrackAudioFileFromExternalUrl($trackId);
        if ($legacyExternal instanceof TrackAudioFile) {
            return $legacyExternal;
        }

        $originalUrl = $this->getStrictRemoteAudioUrl($trackId, MetadataKeys::TRACK_AUDIO_ORIGINAL_URL);
        if ($originalUrl !== '' && $this->isBrowserPlayableRemoteUrl($originalUrl)) {
            return new TrackAudioFile(0, $originalUrl, $this->detectMimeTypeForUrl($originalUrl), '');
        }

        return $this->resolveTrackAudioFileFromReferenceId($this->getTrackPlaybackReferenceId($trackId));
    }

    private function resolveInternetArchiveDownloadFile(int $trackId): ?TrackAudioFile
    {
        foreach ([MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL, MetadataKeys::TRACK_AUDIO_ORIGINAL_URL] as $metaKey) {
            $url = $this->getStrictRemoteAudioUrl($trackId, $metaKey);
            if ($url !== '') {
                return new TrackAudioFile(0, $url, $this->detectMimeTypeForUrl($url), '');
            }
        }

        $legacyExternal = $this->resolveTrackAudioFileFromExternalUrl($trackId);
        if ($legacyExternal instanceof TrackAudioFile) {
            return $legacyExternal;
        }

        return $this->resolveTrackAudioFileFromReferenceId($this->getTrackDownloadReferenceId($trackId));
    }

    private function getStrictRemoteAudioUrl(int $trackId, string $metaKey): string
    {
        $url = trim((string) get_post_meta($trackId, $metaKey, true));
        if ($url === '') {
            return '';
        }

        return $this->sanitizer->sanitizeRemoteUrl($url, $this->getEffectiveRemoteProvider($trackId));
    }

    private function getEffectiveRemoteProvider(int $trackId): string
    {
        $provider = $this->sanitizer->sanitizeProvider((string) get_post_meta($trackId, MetadataKeys::TRACK_SOURCE_PROVIDER, true));
        if ($provider !== '') {
            return $provider;
        }

        if ($this->getTrackSourceType($trackId) === self::SOURCE_TYPE_INTERNET_ARCHIVE) {
            return 'internet_archive';
        }

        return 'direct';
    }

    private function isBrowserPlayableRemoteUrl(string $url): bool
    {
        $mimeType = $this->detectMimeTypeForUrl($url);

        return in_array($mimeType, ['audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav'], true);
    }

    private function detectMimeTypeForUrl(string $url): string
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return 'audio/mpeg';
        }

        $type = wp_check_filetype($path);
        $mimeType = is_array($type) ? (string) ($type['type'] ?? '') : '';

        if (str_starts_with($mimeType, 'audio/')) {
            return $mimeType;
        }

        return 'audio/mpeg';
    }
}
