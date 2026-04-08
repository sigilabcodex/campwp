<?php

declare(strict_types=1);

namespace CampWP\Domain\ContentModel;

use CampWP\Domain\Metadata\MetadataSanitizer;

final class AudioMetadataExtractorService
{
    private MetadataSanitizer $sanitizer;

    public function __construct(?MetadataSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFromAttachment(int $attachmentId): array
    {
        if ($attachmentId <= 0) {
            return [];
        }

        $filePath = get_attached_file($attachmentId);
        $raw = [];

        if (is_string($filePath) && $filePath !== '' && function_exists('wp_read_audio_metadata')) {
            $candidate = wp_read_audio_metadata($filePath);
            if (is_array($candidate)) {
                $raw = $candidate;
            }
        }

        $mapped = $this->mapAudioMetadata($raw);
        $fallback = $this->parseFilenameFallback($attachmentId, is_string($filePath) ? $filePath : '');

        return array_merge($fallback, array_filter($mapped, static fn ($value) => $value !== '' && $value !== 0));
    }

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function mapAudioMetadata(array $metadata): array
    {
        $composer = $this->firstString($metadata, ['composer', 'composer_name']);
        $comment = $this->firstString($metadata, ['comment', 'description']);

        $creditsParts = array_filter([$composer !== '' ? 'Composer: ' . $composer : '', $comment]);

        return [
            'title' => $this->sanitizer->sanitizeText($this->firstString($metadata, ['title'])),
            'artist_display_name' => $this->sanitizer->sanitizeText($this->firstString($metadata, ['artist'])),
            'album' => $this->sanitizer->sanitizeText($this->firstString($metadata, ['album'])),
            'track_number' => $this->sanitizer->sanitizePositiveInteger((string) $this->firstScalar($metadata, ['track_number', 'tracknumber'])),
            'release_year' => $this->extractYear($metadata),
            'subtitle' => $this->sanitizer->sanitizeText($comment),
            'credits' => $this->sanitizer->sanitizeTextarea(implode("\n", $creditsParts)),
            'duration' => $this->resolveDuration($metadata),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseFilenameFallback(int $attachmentId, string $filePath): array
    {
        $filename = $filePath !== '' ? pathinfo($filePath, PATHINFO_FILENAME) : get_the_title($attachmentId);
        $filename = is_string($filename) ? trim($filename) : '';

        if ($filename === '') {
            return [];
        }

        if (preg_match('/^(\d{1,2})\s*-\s*([^\-]+?)\s*-\s*(.+)$/', $filename, $matches) === 1) {
            return [
                'track_number' => $this->sanitizer->sanitizePositiveInteger((string) $matches[1]),
                'artist_display_name' => $this->sanitizer->sanitizeText((string) $matches[2]),
                'title' => $this->sanitizer->sanitizeText((string) $matches[3]),
            ];
        }

        if (preg_match('/^(\d{1,2})\s+(.+)$/', $filename, $matches) === 1) {
            return [
                'track_number' => $this->sanitizer->sanitizePositiveInteger((string) $matches[1]),
                'title' => $this->sanitizer->sanitizeText((string) $matches[2]),
            ];
        }

        return [
            'title' => $this->sanitizer->sanitizeText($filename),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function extractYear(array $metadata): string
    {
        $date = $this->firstString($metadata, ['year', 'date', 'created_date']);

        if (preg_match('/\b(\d{4})\b/', $date, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolveDuration(array $metadata): string
    {
        $formatted = $this->firstString($metadata, ['length_formatted']);
        if ($formatted !== '') {
            return $this->sanitizer->sanitizeDuration($formatted);
        }

        $seconds = (int) $this->firstScalar($metadata, ['length', 'playtime_seconds']);
        if ($seconds <= 0) {
            return '';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
        }

        return sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<string> $keys
     */
    private function firstString(array $metadata, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->firstScalar($metadata, [$key]);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $metadata
     * @param list<string> $keys
     * @return string|int|float
     */
    private function firstScalar(array $metadata, array $keys)
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];

            if (is_array($value) && $value !== []) {
                $first = reset($value);
                if (is_scalar($first)) {
                    return $first;
                }
            }

            if (is_scalar($value)) {
                return $value;
            }
        }

        return '';
    }
}
