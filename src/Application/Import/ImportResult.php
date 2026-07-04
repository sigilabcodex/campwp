<?php

declare(strict_types=1);

namespace CampWP\Application\Import;

final class ImportResult
{
    /**
     * @param list<TrackImportResult> $trackResults
     * @param list<string> $warnings
     * @param list<string> $errors
     */
    public function __construct(
        public readonly int $albumPostId,
        public readonly string $albumAction,
        public readonly array $trackResults,
        public readonly int $createdCount,
        public readonly int $updatedCount,
        public readonly int $unchangedCount,
        public readonly array $warnings,
        public readonly array $errors,
        public readonly string $sourceReleaseIdentity,
        public readonly bool $dryRun,
        public readonly string $status = '',
        public readonly ?CoverImportResult $coverResult = null
    ) {
    }

    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public static function failed(array $errors, array $warnings = [], bool $dryRun = false, string $identity = ''): self
    {
        return new self(0, 'failed', [], 0, 0, 0, $warnings, $errors, $identity, $dryRun, $dryRun ? 'dry_run' : 'failed');
    }

    public function isSuccess(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'album_post_id' => $this->albumPostId,
            'album_action' => $this->albumAction,
            'tracks' => array_map(static fn (TrackImportResult $result): array => $result->toArray(), $this->trackResults),
            'created_count' => $this->createdCount,
            'updated_count' => $this->updatedCount,
            'unchanged_count' => $this->unchangedCount,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'source_release_identity' => $this->sourceReleaseIdentity,
            'dry_run' => $this->dryRun,
            'status' => $this->status,
            'cover' => ($this->coverResult ?? new CoverImportResult('skipped', 0, '', '', 'No cover provided.'))->toArray(),
        ];
    }
}
