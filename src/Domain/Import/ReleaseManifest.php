<?php

declare(strict_types=1);

namespace CampWP\Domain\Import;

final class ReleaseManifest
{
    /**
     * @param list<TrackManifest> $tracks
     * @param array<string, mixed> $albumMeta
     */
    public function __construct(
        public readonly string $schemaVersion,
        public readonly string $catalogIdentity,
        public readonly string $provider,
        public readonly string $externalReleaseId,
        public readonly string $title,
        public readonly string $content,
        public readonly string $postStatus,
        public readonly array $albumMeta,
        public readonly array $tracks
    ) {
    }

    public function getIdentityKey(): string
    {
        return $this->catalogIdentity . ':' . $this->externalReleaseId;
    }
}
