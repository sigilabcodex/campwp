<?php

declare(strict_types=1);

namespace CampWP\Infrastructure\Media;

use CampWP\Domain\Media\MediaAsset;
use CampWP\Domain\Media\MediaStorageProviderInterface;

final class WordPressMediaLibraryProvider implements MediaStorageProviderInterface
{
    public function isValidReference(int $referenceId): bool
    {
        return $this->getAttachment($referenceId) instanceof \WP_Post;
    }

    public function isAudioReference(int $referenceId): bool
    {
        if (! $this->isValidReference($referenceId)) {
            return false;
        }

        $mimeType = (string) get_post_mime_type($referenceId);

        if ($mimeType === '') {
            return false;
        }

        return wp_attachment_is('audio', $referenceId) && str_starts_with($mimeType, 'audio/');
    }

    public function resolve(int $referenceId): ?MediaAsset
    {
        $attachment = $this->getAttachment($referenceId);

        if (! $attachment instanceof \WP_Post) {
            return null;
        }

        $url = wp_get_attachment_url($referenceId);

        if (! is_string($url) || $url === '') {
            return null;
        }

        $mimeType = (string) get_post_mime_type($referenceId);
        $filePath = get_attached_file($referenceId);
        $title = get_the_title($referenceId);

        return new MediaAsset(
            $referenceId,
            $url,
            $mimeType,
            is_string($filePath) ? $filePath : '',
            is_string($title) ? $title : ''
        );
    }

    private function getAttachment(int $referenceId): ?\WP_Post
    {
        if ($referenceId <= 0) {
            return null;
        }

        $attachment = get_post($referenceId);

        if (! $attachment instanceof \WP_Post || $attachment->post_type !== 'attachment') {
            return null;
        }

        return $attachment;
    }
}
