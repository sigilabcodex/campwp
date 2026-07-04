<?php

declare(strict_types=1);

namespace CampWP\Application\Import\Media;

final class CoverSideloadResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $attachmentId = 0,
        public readonly string $payloadHash = '',
        public readonly string $message = ''
    ) {
    }
}
