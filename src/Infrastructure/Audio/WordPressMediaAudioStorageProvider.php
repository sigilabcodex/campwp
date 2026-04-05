<?php

declare(strict_types=1);

namespace CampWP\Infrastructure\Audio;

use CampWP\Domain\Audio\AudioStorageProviderInterface;
use CampWP\Domain\Audio\TrackAudioFile;

final class WordPressMediaAudioStorageProvider implements AudioStorageProviderInterface
{
    public function isValidReference(int $referenceId): bool
    {
        if ($referenceId <= 0) {
            return false;
        }

        $attachment = get_post($referenceId);

        if (! $attachment instanceof \WP_Post || $attachment->post_type !== 'attachment') {
            return false;
        }

        $mimeType = (string) get_post_mime_type($referenceId);

        if ($mimeType === '' || ! wp_attachment_is('audio', $referenceId)) {
            return false;
        }

        return str_starts_with($mimeType, 'audio/');
    }

    public function resolve(int $referenceId): ?TrackAudioFile
    {
        if (! $this->isValidReference($referenceId)) {
            return null;
        }

        $url = wp_get_attachment_url($referenceId);
        $mimeType = (string) get_post_mime_type($referenceId);

        if (! is_string($url) || $url === '') {
            return null;
        }

        return new TrackAudioFile($referenceId, $url, $mimeType);
    }
}
