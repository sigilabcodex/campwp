<?php

declare(strict_types=1);

namespace CampWP\Domain\Import;

final class TrackManifest
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public readonly string $externalTrackId,
        public readonly int $index,
        public readonly int $trackNumber,
        public readonly string $title,
        public readonly string $sourceType,
        public readonly string $provider,
        public readonly array $meta
    ) {
    }
}
