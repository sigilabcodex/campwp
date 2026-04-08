<?php

declare(strict_types=1);

namespace CampWP\Domain\Audio;

final class AudioFormatClassifier
{
    /**
     * @return array{classification:string,format:string,mime:string,extension:string}
     */
    public function classifyAttachment(int $attachmentId): array
    {
        if ($attachmentId <= 0) {
            return $this->unknown();
        }

        $mime = strtolower((string) get_post_mime_type($attachmentId));
        $filePath = get_attached_file($attachmentId);
        $extension = is_string($filePath) ? strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION)) : '';

        if ($mime === '' && is_string($filePath) && $filePath !== '') {
            $type = wp_check_filetype_and_ext($filePath, basename($filePath), $this->supportedMimes());
            $mime = is_array($type) ? strtolower((string) ($type['type'] ?? '')) : '';
            $extension = is_array($type) && is_string($type['ext'] ?? '') && (string) ($type['ext'] ?? '') !== ''
                ? strtolower((string) $type['ext'])
                : $extension;
        }

        return $this->classify($mime, $extension);
    }

    /**
     * @return array{classification:string,format:string,mime:string,extension:string}
     */
    public function classify(string $mimeType, string $extension = ''): array
    {
        $mime = strtolower($mimeType);
        $ext = strtolower($extension);

        if ($this->isLossless($mime, $ext)) {
            return [
                'classification' => AudioFormatClassification::LOSSLESS,
                'format' => $this->resolveFormat($mime, $ext),
                'mime' => $mime,
                'extension' => $ext,
            ];
        }

        if ($this->isLossy($mime, $ext)) {
            return [
                'classification' => AudioFormatClassification::LOSSY,
                'format' => $this->resolveFormat($mime, $ext),
                'mime' => $mime,
                'extension' => $ext,
            ];
        }

        return [
            'classification' => AudioFormatClassification::UNKNOWN,
            'format' => $ext !== '' ? $ext : 'unknown',
            'mime' => $mime,
            'extension' => $ext,
        ];
    }

    private function isLossless(string $mime, string $ext): bool
    {
        return in_array($mime, ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/flac', 'audio/x-flac', 'audio/aiff', 'audio/x-aiff'], true)
            || in_array($ext, ['wav', 'flac', 'aif', 'aiff'], true);
    }

    private function isLossy(string $mime, string $ext): bool
    {
        return in_array($mime, ['audio/mpeg', 'audio/mp3', 'audio/ogg'], true)
            || in_array($ext, ['mp3', 'ogg'], true);
    }

    private function resolveFormat(string $mime, string $ext): string
    {
        if (str_contains($mime, 'flac') || $ext === 'flac') {
            return 'flac';
        }

        if (str_contains($mime, 'aiff') || in_array($ext, ['aif', 'aiff'], true)) {
            return 'aiff';
        }

        if (str_contains($mime, 'wav') || $ext === 'wav') {
            return 'wav';
        }

        if (str_contains($mime, 'mpeg') || $ext === 'mp3') {
            return 'mp3';
        }

        if (str_contains($mime, 'ogg') || $ext === 'ogg') {
            return 'ogg';
        }

        return $ext !== '' ? $ext : 'unknown';
    }

    /**
     * @return array<string,string>
     */
    public function supportedMimes(): array
    {
        return [
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'aif' => 'audio/aiff',
            'aiff' => 'audio/aiff',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
        ];
    }

    /**
     * @return array{classification:string,format:string,mime:string,extension:string}
     */
    private function unknown(): array
    {
        return [
            'classification' => AudioFormatClassification::UNKNOWN,
            'format' => 'unknown',
            'mime' => '',
            'extension' => '',
        ];
    }
}
