<?php

declare(strict_types=1);

namespace CampWP\Domain\ContentModel;

use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Domain\Metadata\MetadataSanitizer;

final class TrackMetadataInheritanceService
{
    private const DEFAULTS_OPTION_KEY = 'campwp_defaults';

    private MetadataSanitizer $sanitizer;

    public function __construct(?MetadataSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function applyDefaultsToFields(array $fields, int $albumId): array
    {
        $defaults = $this->getReleaseDefaults($albumId);

        if ((string) ($fields['artist_display_name'] ?? '') === '' && $defaults['artist_display_name'] !== '') {
            $fields['artist_display_name'] = $defaults['artist_display_name'];
        }

        if ((string) ($fields['credits'] ?? '') === '' && $defaults['credits'] !== '') {
            $fields['credits'] = $defaults['credits'];
        }

        return $fields;
    }

    /**
     * @return array{artist_display_name:string,credits:string,label_name:string,release_date:string}
     */
    public function getReleaseDefaults(int $albumId): array
    {
        $siteDefaults = $this->getSiteDefaults();

        $artist = $albumId > 0 ? $this->sanitizeTextMeta($albumId, MetadataKeys::ALBUM_ARTIST_DISPLAY) : '';
        $credits = $albumId > 0 ? $this->sanitizeTextareaMeta($albumId, MetadataKeys::ALBUM_CREDITS_OVERRIDE) : '';
        $label = $albumId > 0 ? $this->sanitizeTextMeta($albumId, MetadataKeys::ALBUM_LABEL_NAME) : '';
        $releaseDate = $albumId > 0 ? $this->sanitizeReleaseDateMeta($albumId, MetadataKeys::ALBUM_RELEASE_DATE) : '';

        return [
            'artist_display_name' => $artist !== '' ? $artist : $siteDefaults['artist_display_name'],
            'credits' => $credits !== '' ? $credits : $siteDefaults['credits_template'],
            'label_name' => $label !== '' ? $label : $siteDefaults['label_name'],
            'release_date' => $releaseDate,
        ];
    }

    private function sanitizeTextMeta(int $postId, string $metaKey): string
    {
        return $this->sanitizer->sanitizeText((string) get_post_meta($postId, $metaKey, true));
    }

    private function sanitizeTextareaMeta(int $postId, string $metaKey): string
    {
        return $this->sanitizer->sanitizeTextarea((string) get_post_meta($postId, $metaKey, true));
    }

    private function sanitizeReleaseDateMeta(int $postId, string $metaKey): string
    {
        return $this->sanitizer->sanitizeReleaseDate((string) get_post_meta($postId, $metaKey, true));
    }

    /**
     * @return array{artist_display_name:string,label_name:string,credits_template:string}
     */
    private function getSiteDefaults(): array
    {
        $stored = get_option(self::DEFAULTS_OPTION_KEY, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        return [
            'artist_display_name' => $this->sanitizer->sanitizeText((string) ($stored['artist_display_name'] ?? '')),
            'label_name' => $this->sanitizer->sanitizeText((string) ($stored['label_name'] ?? '')),
            'credits_template' => $this->sanitizer->sanitizeTextarea((string) ($stored['credits_template'] ?? '')),
        ];
    }
}
