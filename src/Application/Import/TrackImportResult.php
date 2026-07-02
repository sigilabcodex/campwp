<?php

declare(strict_types=1);

namespace CampWP\Application\Import;

final class TrackImportResult
{
    public function __construct(
        public readonly int $postId,
        public readonly string $externalTrackId,
        public readonly int $index,
        public readonly string $action
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'post_id' => $this->postId,
            'external_track_id' => $this->externalTrackId,
            'index' => $this->index,
            'action' => $this->action,
        ];
    }
}
