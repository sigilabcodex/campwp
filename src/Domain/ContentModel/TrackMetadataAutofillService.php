<?php

declare(strict_types=1);

namespace CampWP\Domain\ContentModel;

use CampWP\Domain\Metadata\MetadataSanitizer;

final class TrackMetadataAutofillService
{
    private MetadataSanitizer $sanitizer;
    private AudioMetadataExtractorService $audioMetadataExtractor;
    private TrackMetadataInheritanceService $inheritance;

    public function __construct(?MetadataSanitizer $sanitizer = null, ?AudioMetadataExtractorService $audioMetadataExtractor = null, ?TrackMetadataInheritanceService $inheritance = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
        $this->audioMetadataExtractor = $audioMetadataExtractor ?? new AudioMetadataExtractorService($this->sanitizer);
        $this->inheritance = $inheritance ?? new TrackMetadataInheritanceService($this->sanitizer);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSuggestedTrackFieldsFromAudio(int $albumId, int $attachmentId): array
    {
        $suggested = $this->audioMetadataExtractor->extractFromAttachment($attachmentId);

        $filtered = apply_filters('campwp_release_builder_extract_audio_metadata', $suggested, $attachmentId, $albumId);

        if (! is_array($filtered)) {
            return $this->sanitizeTrackFields($suggested);
        }

        return $this->sanitizeTrackFields($filtered);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function applyTrackDefaults(array $fields, int $albumId, int $trackId): array
    {
        $resolved = $this->inheritance->applyDefaultsToFields($fields, $albumId);

        $filtered = apply_filters('campwp_release_builder_apply_track_defaults', $resolved, $albumId, $trackId);

        if (! is_array($filtered)) {
            return $this->sanitizeTrackFields($resolved);
        }

        return $this->sanitizeTrackFields($filtered);
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
            'album' => $this->sanitizer->sanitizeText((string) ($fields['album'] ?? '')),
            'release_year' => $this->sanitizer->sanitizeText((string) ($fields['release_year'] ?? '')),
        ];
    }
}
