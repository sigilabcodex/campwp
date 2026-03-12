<?php

declare(strict_types=1);

namespace CampWP\Infrastructure\Storage;

use RuntimeException;
use WP_Error;

final class LocalUploadsAudioStorageAdapter implements AudioStorageAdapterInterface
{
    public function exists(int $attachmentId): bool
    {
        if ($attachmentId <= 0) {
            return false;
        }

        return get_post_type($attachmentId) === 'attachment' && $this->isAudioAttachment($attachmentId);
    }

    public function getPath(int $attachmentId): ?string
    {
        $filePath = get_attached_file($attachmentId);

        if (! is_string($filePath) || $filePath === '') {
            return null;
        }

        return $filePath;
    }

    public function getUrl(int $attachmentId): ?string
    {
        $url = wp_get_attachment_url($attachmentId);

        if (! is_string($url) || $url === '') {
            return null;
        }

        return $url;
    }

    public function store(array $file, array $args = []): int
    {
        if (! isset($file['tmp_name']) || ! is_uploaded_file((string) $file['tmp_name'])) {
            throw new RuntimeException('No uploaded file received for local storage.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $postId = isset($args['post_id']) ? absint($args['post_id']) : 0;

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            throw new RuntimeException((string) $upload['error']);
        }

        $filePath = isset($upload['file']) && is_string($upload['file']) ? $upload['file'] : '';
        $mimeType = isset($upload['type']) && is_string($upload['type']) ? $upload['type'] : wp_check_filetype($filePath)['type'];

        if (! is_string($mimeType) || ! str_starts_with($mimeType, 'audio/')) {
            if ($filePath !== '') {
                wp_delete_file($filePath);
            }

            throw new RuntimeException('Uploaded file is not a supported audio format.');
        }

        $attachmentId = wp_insert_attachment(
            [
                'post_mime_type' => $mimeType,
                'post_title' => sanitize_text_field(pathinfo($filePath, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
            ],
            $filePath,
            $postId
        );

        if ($attachmentId instanceof WP_Error || ! is_int($attachmentId)) {
            if ($filePath !== '') {
                wp_delete_file($filePath);
            }

            throw new RuntimeException('Unable to save uploaded file to media library.');
        }

        $metadata = wp_generate_attachment_metadata($attachmentId, $filePath);
        wp_update_attachment_metadata($attachmentId, $metadata);

        return $attachmentId;
    }

    public function delete(int $attachmentId, bool $forceDelete = false): bool
    {
        if (! $this->exists($attachmentId)) {
            return false;
        }

        return wp_delete_attachment($attachmentId, $forceDelete) !== false;
    }

    public function getStreamReference(int $attachmentId): ?string
    {
        return $this->getUrl($attachmentId);
    }

    private function isAudioAttachment(int $attachmentId): bool
    {
        if (! wp_attachment_is('audio', $attachmentId)) {
            return false;
        }

        $filePath = $this->getPath($attachmentId);

        if ($filePath === null || ! file_exists($filePath)) {
            return false;
        }

        $fileType = wp_check_filetype($filePath);
        $mimeType = isset($fileType['type']) && is_string($fileType['type']) ? $fileType['type'] : '';

        return $mimeType !== '' && str_starts_with($mimeType, 'audio/');
    }
}
