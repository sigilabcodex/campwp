<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

interface AudioStorageProviderInterface
{
    public function isValidReference(int $referenceId): bool;

    public function resolve(int $referenceId): ?TrackAudioFile;
}
