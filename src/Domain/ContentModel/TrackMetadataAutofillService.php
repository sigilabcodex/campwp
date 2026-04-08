<?php

declare(strict_types=1);

namespace CampWP\Domain\ContentModel;

use CampWP\Domain\Metadata\MetadataSanitizer;

final class TrackMetadataAutofillService
{
    private MetadataSanitizer $sanitizer;

    public function __construct(?MetadataSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSuggestedTrackFieldsFromAudio(int $albumId, int $attachmentId): array
    {
        $suggested = apply_filters('campwp_release_builder_extract_audio_metadata', [], $attachmentId, $albumId);

        if (! is_array($suggested)) {
            return [];
        }

        return $this->sanitizeTrackFields($suggested);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function applyTrackDefaults(array $fields, int $albumId, int $trackId): array
    {
        $resolved = apply_filters('campwp_release_builder_apply_track_defaults', $fields, $albumId, $trackId);

        if (! is_array($resolved)) {
            return $fields;
        }

        return $this->sanitizeTrackFields($resolved);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function sanitizeTrackFields(array $fields): array
    {
        return [
            'title' => $this->sanitizer->sanitizeText((string) ($fields['title'] ?? '')),
            'subtitle' => $this->sanitizer->sanitizeText((string) ($fields['subtitle'] ?? '')),
            'artist_display_name' => $this->sanitizer->sanitizeText((string) ($fields['artist_display_name'] ?? '')),
            'track_number' => $this->sanitizer->sanitizePositiveInteger((string) ($fields['track_number'] ?? '0')),
            'duration' => $this->sanitizer->sanitizeDuration((string) ($fields['duration'] ?? '')),
            'credits' => $this->sanitizer->sanitizeTextarea((string) ($fields['credits'] ?? '')),
        ];
    }
}
