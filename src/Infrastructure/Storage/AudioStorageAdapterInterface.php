<?php

declare(strict_types=1);

namespace CampWP\Infrastructure\Storage;

interface AudioStorageAdapterInterface
{
    public function exists(int $attachmentId): bool;

    public function getPath(int $attachmentId): ?string;

    public function getUrl(int $attachmentId): ?string;

    /**
     * @param array<string, mixed> $file
     * @param array<string, mixed> $args
     */
    public function store(array $file, array $args = []): int;

    public function delete(int $attachmentId, bool $forceDelete = false): bool;

    public function getStreamReference(int $attachmentId): ?string;
}
