<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

use CampWP\Domain\Metadata\MetadataKeys;

final class TrackAudioResolver
{
    private AudioStorageProviderInterface $storageProvider;

    public function __construct(AudioStorageProviderInterface $storageProvider)
    {
        $this->storageProvider = $storageProvider;
    }

    public function getTrackAudioReferenceId(int $trackId): int
    {
        return max(0, (int) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, true));
    }

    public function getTrackAudioFile(int $trackId): ?TrackAudioFile
    {
        $referenceId = $this->getTrackAudioReferenceId($trackId);

        if ($referenceId <= 0) {
            return null;
        }

        return $this->storageProvider->resolve($referenceId);
    }

    public function isValidTrackAudioReference(int $referenceId): bool
    {
        return $referenceId > 0 && $this->storageProvider->isValidReference($referenceId);
    }
}
