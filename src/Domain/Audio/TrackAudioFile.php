<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

final class TrackAudioFile
{
    private int $referenceId;

    private string $url;

    private string $mimeType;

    public function __construct(int $referenceId, string $url, string $mimeType)
    {
        $this->referenceId = $referenceId;
        $this->url = $url;
        $this->mimeType = $mimeType;
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
}
