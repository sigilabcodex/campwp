<?php

declare(strict_types=1);

namespace CampWP\Application\Import;

final class CoverImportResult
{
    public function __construct(
        public readonly string $action,
        public readonly int $attachmentId = 0,
        public readonly string $externalId = '',
        public readonly string $url = '',
        public readonly string $message = ''
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'attachment_id' => $this->attachmentId,
            'external_id' => $this->externalId,
            'url' => $this->url,
            'message' => $this->message,
        ];
    }
}
