<?php

declare(strict_types=1);

namespace CampWP\Domain\Media;

final class AlbumCover
{
    private string $sourceType;

    private int $attachmentId;

    private string $url;

    private function __construct(string $sourceType, int $attachmentId, string $url)
    {
        $this->sourceType = $sourceType;
        $this->attachmentId = $attachmentId;
        $this->url = $url;
    }

    public static function attachment(int $attachmentId): self
    {
        return new self('attachment', $attachmentId, '');
    }

    public static function remote(string $url): self
    {
        return new self('remote', 0, $url);
    }

    public function isAttachment(): bool
    {
        return $this->sourceType === 'attachment';
    }

    public function isRemote(): bool
    {
        return $this->sourceType === 'remote';
    }

    public function getAttachmentId(): int
    {
        return $this->attachmentId;
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
