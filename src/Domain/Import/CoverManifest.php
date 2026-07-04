<?php

declare(strict_types=1);

namespace CampWP\Domain\Import;

final class CoverManifest
{
    public function __construct(
        public readonly string $source,
        public readonly string $externalId,
        public readonly string $url,
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly string $strategy,
        public readonly string $payloadHash = ''
    ) {
    }
}
