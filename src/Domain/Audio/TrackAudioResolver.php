<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

use CampWP\Domain\Media\MediaAsset;
use CampWP\Domain\Media\MediaStorageProviderInterface;
use CampWP\Domain\Metadata\MetadataKeys;

final class TrackAudioResolver
{
    private MediaStorageProviderInterface $storageProvider;

    public function __construct(MediaStorageProviderInterface $storageProvider)
    {
        $this->storageProvider = $storageProvider;
    }

    public function getTrackAudioReferenceId(int $trackId): int
    {
        $sourceId = max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID, true));

        if ($sourceId > 0) {
            return $sourceId;
        }

        return max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, true));
    }

    public function getTrackAudioFile(int $trackId): ?TrackAudioFile
    {
        $referenceId = $this->getTrackAudioReferenceId($trackId);

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
}
