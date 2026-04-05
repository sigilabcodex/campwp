<?php

declare(strict_types=1);

namespace CampWP\Domain\Media;

interface MediaStorageProviderInterface
{
    public function isValidReference(int $referenceId): bool;

    public function isAudioReference(int $referenceId): bool;

    public function resolve(int $referenceId): ?MediaAsset;
}
