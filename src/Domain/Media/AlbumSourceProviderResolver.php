<?php

declare(strict_types=1);

namespace CampWP\Domain\Media;

use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Domain\Metadata\MetadataSanitizer;

final class AlbumSourceProviderResolver
{
    private MetadataSanitizer $sanitizer;

    public function __construct(?MetadataSanitizer $sanitizer = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
    }

    public function getEffectiveProvider(int $albumId): string
    {
        $provider = $this->sanitizer->sanitizeProvider((string) get_post_meta($albumId, MetadataKeys::ALBUM_SOURCE_PROVIDER, true));
        if ($provider !== '') {
            return $provider;
        }

        if ($this->isInternetArchiveBacked($albumId)) {
            return 'internet_archive';
        }

        return 'direct';
    }

    public function isInternetArchiveBacked(int $albumId): bool
    {
        return trim((string) get_post_meta($albumId, MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER, true)) !== ''
            || trim((string) get_post_meta($albumId, MetadataKeys::ALBUM_INTERNET_ARCHIVE_METADATA_URL, true)) !== '';
    }
}
