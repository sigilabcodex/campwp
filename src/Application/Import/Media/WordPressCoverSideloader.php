<?php

declare(strict_types=1);

namespace CampWP\Application\Import\Media;

use CampWP\Domain\Import\CoverManifest;

final class WordPressCoverSideloader implements CoverSideloaderInterface
{
    /** @var list<string> */
    private const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    public function sideload(CoverManifest $cover, int $albumId, int $existingAttachmentId = 0): CoverSideloadResult
    {
        $this->loadWordPressMediaIncludes();

        if (! function_exists('wp_remote_get')) {
            return new CoverSideloadResult(false, 0, '', 'WordPress HTTP API is unavailable.');
        }

        $tmp = function_exists('wp_tempnam') ? wp_tempnam($cover->filename) : tempnam(sys_get_temp_dir(), 'campwp-cover-');
        if (! is_string($tmp) || $tmp === '') {
            return new CoverSideloadResult(false, 0, '', 'Could not create a temporary cover file.');
        }

        $response = wp_remote_get($cover->url, ['timeout' => 30, 'stream' => true, 'filename' => $tmp]);
        if (is_wp_error($response)) {
            @unlink($tmp);
            return new CoverSideloadResult(false, 0, '', $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            @unlink($tmp);
            return new CoverSideloadResult(false, 0, '', sprintf('Cover download failed with HTTP %d.', $code));
        }

        $headerMime = strtolower(trim((string) wp_remote_retrieve_header($response, 'content-type')));
        $headerMime = $headerMime !== '' ? strtolower(trim(explode(';', $headerMime)[0])) : '';
        if ($headerMime !== '' && $headerMime !== strtolower($cover->mimeType)) {
            @unlink($tmp);
            return new CoverSideloadResult(false, 0, '', sprintf('Cover MIME mismatch: expected %s, got %s.', $cover->mimeType, $headerMime));
        }

        if (! in_array(strtolower($cover->mimeType), self::SUPPORTED_MIME_TYPES, true)) {
            @unlink($tmp);
            return new CoverSideloadResult(false, 0, '', 'Cover MIME type is not a supported image type.');
        }

        if (! is_file($tmp) || ! is_readable($tmp)) {
            @unlink($tmp);
            return new CoverSideloadResult(false, 0, '', 'Cover download did not produce a readable file.');
        }

        $hash = hash_file('sha256', $tmp);
        if (! is_string($hash) || $hash === '') {
            @unlink($tmp);
            return new CoverSideloadResult(false, 0, '', 'Could not fingerprint downloaded cover.');
        }
        $payloadHash = 'sha256:' . $hash;
        $fileType = function_exists('wp_check_filetype_and_ext')
            ? wp_check_filetype_and_ext($tmp, $cover->filename, [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
            ])
            : ['type' => $cover->mimeType, 'ext' => pathinfo($cover->filename, PATHINFO_EXTENSION)];

        if (($fileType['type'] ?? '') !== $cover->mimeType) {
            @unlink($tmp);
            return new CoverSideloadResult(false, 0, '', 'Downloaded cover is not the expected image type.');
        }

        if ($existingAttachmentId > 0 && get_post($existingAttachmentId) instanceof \WP_Post) {
            if (! function_exists('wp_handle_sideload') || ! function_exists('update_attached_file')) {
                @unlink($tmp);
                return new CoverSideloadResult(false, 0, '', 'WordPress media file API is unavailable.');
            }

            $handled = wp_handle_sideload([
                'name' => $cover->filename,
                'type' => $cover->mimeType,
                'tmp_name' => $tmp,
                'error' => 0,
                'size' => filesize($tmp) ?: 0,
            ], [
                'test_form' => false,
                'mimes' => [
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                ],
            ]);

            if (! is_array($handled) || isset($handled['error']) || ! isset($handled['file'])) {
                @unlink($tmp);
                return new CoverSideloadResult(false, 0, '', (string) ($handled['error'] ?? 'Cover file sideload failed.'));
            }

            $updated = wp_update_post([
                'ID' => $existingAttachmentId,
                'post_mime_type' => $cover->mimeType,
                'post_title' => pathinfo($cover->filename, PATHINFO_FILENAME),
                'post_parent' => $albumId,
            ], true);
            if (is_wp_error($updated)) {
                @unlink((string) $handled['file']);
                return new CoverSideloadResult(false, 0, '', $updated->get_error_message());
            }

            update_attached_file($existingAttachmentId, (string) $handled['file']);
            $this->generateMetadata($existingAttachmentId, (string) $handled['file']);
            return new CoverSideloadResult(true, $existingAttachmentId, $payloadHash);
        }

        if (! function_exists('media_handle_sideload')) {
            @unlink($tmp);
            return new CoverSideloadResult(false, 0, '', 'WordPress media sideload API is unavailable.');
        }

        $file = [
            'name' => $cover->filename,
            'type' => $cover->mimeType,
            'tmp_name' => $tmp,
            'error' => 0,
            'size' => filesize($tmp) ?: 0,
        ];
        $attachmentId = media_handle_sideload($file, $albumId, null, [
            'post_title' => pathinfo($cover->filename, PATHINFO_FILENAME),
            'post_mime_type' => $cover->mimeType,
        ]);

        if (is_wp_error($attachmentId)) {
            @unlink($tmp);
            return new CoverSideloadResult(false, 0, '', $attachmentId->get_error_message());
        }

        return new CoverSideloadResult(true, (int) $attachmentId, $payloadHash);
    }

    private function loadWordPressMediaIncludes(): void
    {
        if (! defined('ABSPATH')) {
            return;
        }

        foreach (['file.php', 'media.php', 'image.php'] as $file) {
            $path = ABSPATH . 'wp-admin/includes/' . $file;
            if (is_readable($path)) {
                require_once $path;
            }
        }
    }

    private function generateMetadata(int $attachmentId, string $file): void
    {
        if (function_exists('wp_generate_attachment_metadata') && function_exists('wp_update_attachment_metadata')) {
            $metadata = wp_generate_attachment_metadata($attachmentId, $file);
            if (is_array($metadata)) {
                wp_update_attachment_metadata($attachmentId, $metadata);
            }
        }
    }
}
