<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

final class TrackAudioAsset
{
    private int $sourceAttachmentId;
    private int $mp3AttachmentId;
    private int $oggAttachmentId;
    private int $streamingAttachmentId;
    private string $sourceClassification;

    public function __construct(int $sourceAttachmentId, int $mp3AttachmentId, int $oggAttachmentId, int $streamingAttachmentId, string $sourceClassification)
    {
        $this->sourceAttachmentId = max(0, $sourceAttachmentId);
        $this->mp3AttachmentId = max(0, $mp3AttachmentId);
        $this->oggAttachmentId = max(0, $oggAttachmentId);
        $this->streamingAttachmentId = max(0, $streamingAttachmentId);
        $this->sourceClassification = $sourceClassification;
    }

    public function getSourceAttachmentId(): int
    {
        return $this->sourceAttachmentId;
    }

    public function getMp3AttachmentId(): int
    {
        return $this->mp3AttachmentId;
    }

    public function getOggAttachmentId(): int
    {
        return $this->oggAttachmentId;
    }

    public function getStreamingAttachmentId(): int
    {
        return $this->streamingAttachmentId;
    }

    public function getSourceClassification(): string
    {
        return $this->sourceClassification;
    }
}
