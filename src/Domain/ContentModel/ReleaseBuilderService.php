<?php

declare(strict_types=1);

namespace CampWP\Domain\ContentModel;

use CampWP\Domain\Audio\AudioFormatClassifier;
use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Domain\Metadata\MetadataSanitizer;
use CampWP\Infrastructure\Media\WordPressMediaLibraryProvider;

final class ReleaseBuilderService
{
    private MetadataSanitizer $sanitizer;
    private TrackAudioResolver $trackAudioResolver;
    private AudioFormatClassifier $audioFormatClassifier;
    private TrackMetadataAutofillService $metadataAutofill;

    public function __construct(?MetadataSanitizer $sanitizer = null, ?TrackAudioResolver $trackAudioResolver = null, ?TrackMetadataAutofillService $metadataAutofill = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
        $this->trackAudioResolver = $trackAudioResolver ?? new TrackAudioResolver(new WordPressMediaLibraryProvider());
        $this->audioFormatClassifier = new AudioFormatClassifier();
        $this->metadataAutofill = $metadataAutofill ?? new TrackMetadataAutofillService($this->sanitizer);
    }

    /**
     * @param list<int> $attachmentIds
     * @return list<int>
     */
    public function ensureTracksForAudioAttachments(int $albumId, array $attachmentIds): array
    {
        $trackIds = [];

        foreach ($attachmentIds as $attachmentId) {
            if (! $this->trackAudioResolver->isValidTrackAudioReference($attachmentId)) {
                continue;
            }

            $existingTrackId = $this->findAlbumTrackByAttachment($albumId, $attachmentId);

            if ($existingTrackId === 0) {
                $existingTrackId = $this->findStandaloneTrackByAttachment($attachmentId);
            }

            if ($existingTrackId === 0) {
                $existingTrackId = $this->createTrackFromAttachment($albumId, $attachmentId);
            }

            if ($existingTrackId > 0) {
                $this->applyAlbumMetadataSuggestionsIfMissing($albumId, $attachmentId);
                $trackIds[] = $existingTrackId;
            }
        }

        return array_values(array_unique(array_filter(array_map('absint', $trackIds))));
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function saveInlineTrackFields(int $trackId, array $fields): void
    {
        if (get_post_type($trackId) !== $this->getTrackPostType() || ! current_user_can('edit_post', $trackId)) {
            return;
        }

        $title = $this->sanitizer->sanitizeText((string) ($fields['title'] ?? ''));
        if ($title !== '') {
            wp_update_post([
                'ID' => $trackId,
                'post_title' => $title,
            ]);
        }

        $this->updateMeta($trackId, MetadataKeys::TRACK_SUBTITLE, $this->sanitizer->sanitizeText((string) ($fields['subtitle'] ?? '')));
        $this->updateMeta($trackId, MetadataKeys::TRACK_NUMBER, $this->sanitizer->sanitizePositiveInteger((string) ($fields['track_number'] ?? '0')));
        $this->updateMeta($trackId, MetadataKeys::TRACK_DURATION, $this->sanitizer->sanitizeDuration((string) ($fields['duration'] ?? '')));
        $this->updateMeta($trackId, MetadataKeys::TRACK_ARTIST_DISPLAY, $this->sanitizer->sanitizeText((string) ($fields['artist_display_name'] ?? '')));
        $this->updateMeta($trackId, MetadataKeys::TRACK_CREDITS, $this->sanitizer->sanitizeTextarea((string) ($fields['credits'] ?? '')));

        $audioSourceType = array_key_exists('audio_source_type', $fields)
            ? $this->sanitizer->sanitizeTrackAudioSourceType((string) $fields['audio_source_type'])
            : $this->sanitizer->sanitizeTrackAudioSourceType((string) get_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_TYPE, true));
        $this->updateMeta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_TYPE, $audioSourceType);

        if ($audioSourceType === 'external_url') {
            $externalAudioUrl = $this->sanitizer->sanitizeTrackAudioExternalUrl((string) ($fields['audio_external_url'] ?? ''));
            $this->updateMeta($trackId, MetadataKeys::TRACK_AUDIO_EXTERNAL_URL, $externalAudioUrl);
            $this->clearAttachmentDrivenAudioMeta($trackId);

            return;
        }

        delete_post_meta($trackId, MetadataKeys::TRACK_AUDIO_EXTERNAL_URL);

        $audioAttachmentId = $this->sanitizer->sanitizeAttachmentId((string) ($fields['audio_attachment_id'] ?? '0'));
        if ($audioAttachmentId > 0 && $this->trackAudioResolver->isValidTrackAudioReference($audioAttachmentId)) {
            $this->syncTrackAudioMeta($trackId, $audioAttachmentId);
            return;
        }

        $this->clearAttachmentDrivenAudioMeta($trackId);
    }

    private function findAlbumTrackByAttachment(int $albumId, int $attachmentId): int
    {
        $trackIds = get_posts([
            'post_type' => $this->getTrackPostType(),
            'posts_per_page' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => MetadataKeys::TRACK_ALBUM_ID,
                    'value' => $albumId,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID,
                    'value' => $attachmentId,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
            ],
            'suppress_filters' => false,
        ]);

        if (! is_array($trackIds) || $trackIds === []) {
            return 0;
        }

        return absint($trackIds[0]);
    }

    private function findStandaloneTrackByAttachment(int $attachmentId): int
    {
        $trackIds = get_posts([
            'post_type' => $this->getTrackPostType(),
            'posts_per_page' => 1,
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID,
                    'value' => $attachmentId,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => MetadataKeys::TRACK_ALBUM_ID,
                    'compare' => 'NOT EXISTS',
                ],
            ],
            'suppress_filters' => false,
        ]);

        if (! is_array($trackIds) || $trackIds === []) {
            return 0;
        }

        return absint($trackIds[0]);
    }

    private function createTrackFromAttachment(int $albumId, int $attachmentId): int
    {
        $suggested = $this->metadataAutofill->getSuggestedTrackFieldsFromAudio($albumId, $attachmentId);
        $title = (string) ($suggested['title'] ?? '');

        if ($title === '') {
            $attachment = get_post($attachmentId);
            $title = $attachment instanceof \WP_Post ? sanitize_text_field((string) $attachment->post_title) : '';
        }

        if ($title === '') {
            $title = sprintf(__('Track %d', 'campwp'), $attachmentId);
        }

        $trackId = wp_insert_post([
            'post_type' => $this->getTrackPostType(),
            'post_status' => 'draft',
            'post_title' => $title,
        ], true);

        if (is_wp_error($trackId)) {
            return 0;
        }

        $this->syncTrackAudioMeta((int) $trackId, $attachmentId);
        $this->applyMetadataSuggestions((int) $trackId, $albumId, $attachmentId);

        return (int) $trackId;
    }

    private function applyMetadataSuggestions(int $trackId, int $albumId, int $attachmentId): void
    {
        $fields = $this->metadataAutofill->getSuggestedTrackFieldsFromAudio($albumId, $attachmentId);
        $fields = $this->metadataAutofill->applyTrackDefaults($fields, $albumId, $trackId);

        if ((string) ($fields['title'] ?? '') !== '' && (string) get_the_title($trackId) === '') {
            wp_update_post([
                'ID' => $trackId,
                'post_title' => (string) $fields['title'],
            ]);
        }

        $this->setTrackMetaIfMissing($trackId, MetadataKeys::TRACK_SUBTITLE, (string) ($fields['subtitle'] ?? ''));
        $this->setTrackMetaIfMissing($trackId, MetadataKeys::TRACK_ARTIST_DISPLAY, (string) ($fields['artist_display_name'] ?? ''));
        $this->setTrackMetaIfMissing($trackId, MetadataKeys::TRACK_NUMBER, (int) ($fields['track_number'] ?? 0));
        $this->setTrackMetaIfMissing($trackId, MetadataKeys::TRACK_DURATION, (string) ($fields['duration'] ?? ''));
        $this->setTrackMetaIfMissing($trackId, MetadataKeys::TRACK_CREDITS, (string) ($fields['credits'] ?? ''));
    }

    private function applyAlbumMetadataSuggestionsIfMissing(int $albumId, int $attachmentId): void
{
    if ($albumId <= 0) {
        return;
    }

    $fields = $this->metadataAutofill->getSuggestedTrackFieldsFromAudio($albumId, $attachmentId);

    if ($this->getMetaString($albumId, MetadataKeys::ALBUM_ARTIST_DISPLAY) === '' && (string) ($fields['artist_display_name'] ?? '') !== '') {
        update_post_meta($albumId, MetadataKeys::ALBUM_ARTIST_DISPLAY, (string) $fields['artist_display_name']);
    }

    if ($this->getMetaString($albumId, MetadataKeys::ALBUM_RELEASE_DATE) === '' && (string) ($fields['release_year'] ?? '') !== '') {
        $releaseYear = (string) $fields['release_year'];
        if (preg_match('/^\d{4}$/', $releaseYear) === 1) {
            update_post_meta($albumId, MetadataKeys::ALBUM_RELEASE_DATE, $releaseYear . '-01-01');
        }
    }

    if ($this->getMetaString($albumId, MetadataKeys::ALBUM_CREDITS_OVERRIDE) === '' && (string) ($fields['credits'] ?? '') !== '') {
        update_post_meta($albumId, MetadataKeys::ALBUM_CREDITS_OVERRIDE, (string) $fields['credits']);
    }
}

    private function syncTrackAudioMeta(int $trackId, int $attachmentId): void
    {
        update_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_TYPE, 'attachment');
        delete_post_meta($trackId, MetadataKeys::TRACK_AUDIO_EXTERNAL_URL);
        update_post_meta($trackId, MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, $attachmentId);
        update_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID, $attachmentId);

        $classification = $this->audioFormatClassifier->classifyAttachment($attachmentId);
        update_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_CLASSIFICATION, $classification['classification']);
    }

    private function clearAttachmentDrivenAudioMeta(int $trackId): void
    {
        delete_post_meta($trackId, MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID);
        delete_post_meta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID);
        delete_post_meta($trackId, MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID);
        delete_post_meta($trackId, MetadataKeys::TRACK_AUDIO_OGG_ATTACHMENT_ID);
        delete_post_meta($trackId, MetadataKeys::TRACK_AUDIO_STREAMING_ATTACHMENT_ID);
        $this->updateMeta($trackId, MetadataKeys::TRACK_AUDIO_SOURCE_CLASSIFICATION, 'unknown');
    }

    /**
     * @param int|string $value
     */
    private function updateMeta(int $trackId, string $metaKey, $value): void
    {
        if ($value === '' || $value === 0) {
            delete_post_meta($trackId, $metaKey);
            return;
        }

        update_post_meta($trackId, $metaKey, $value);
    }

    /**
     * @param int|string $value
     */
    private function setTrackMetaIfMissing(int $trackId, string $metaKey, $value): void
    {
        if ($value === '' || $value === 0) {
            return;
        }

        $existing = get_post_meta($trackId, $metaKey, true);
        if ($existing !== '' && $existing !== null && $existing !== []) {
            return;
        }

        update_post_meta($trackId, $metaKey, $value);
    }

    private function getMetaString(int $postId, string $metaKey): string
    {
        $value = get_post_meta($postId, $metaKey, true);

        return is_string($value) ? $value : '';
    }

    private function getTrackPostType(): string
    {
        $postType = apply_filters('campwp_track_post_type', 'campwp_track');

        if (! is_string($postType) || $postType === '') {
            return 'campwp_track';
        }

        return sanitize_key($postType);
    }
}
