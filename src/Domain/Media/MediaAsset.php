<?php

declare(strict_types=1);

namespace CampWP\Domain\Media;

final class MediaAsset
{
    private int $referenceId;

    private string $url;

    private string $mimeType;

    private string $filePath;

    private string $title;

    public function __construct(int $referenceId, string $url, string $mimeType, string $filePath, string $title)
    {
        $this->referenceId = $referenceId;
        $this->url = $url;
        $this->mimeType = $mimeType;
        $this->filePath = $filePath;
        $this->title = $title;
    }

    public function getReferenceId(): int
    {
        return $this->referenceId;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
