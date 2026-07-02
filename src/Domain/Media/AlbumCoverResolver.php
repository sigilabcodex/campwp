<?php

declare(strict_types=1);

namespace CampWP\Domain\Media;

use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Domain\Metadata\MetadataSanitizer;

final class AlbumCoverResolver
{
    private MetadataSanitizer $sanitizer;
    private AlbumSourceProviderResolver $providerResolver;

    public function __construct(?MetadataSanitizer $sanitizer = null, ?AlbumSourceProviderResolver $providerResolver = null)
    {
        $this->sanitizer = $sanitizer ?? new MetadataSanitizer();
        $this->providerResolver = $providerResolver ?? new AlbumSourceProviderResolver($this->sanitizer);
    }

    public function resolve(int $albumId): ?AlbumCover
    {
        $featuredImageId = max(0, (int) get_post_thumbnail_id($albumId));
        if ($featuredImageId > 0) {
            return AlbumCover::attachment($featuredImageId);
        }

        $remoteCoverUrl = trim((string) get_post_meta($albumId, MetadataKeys::ALBUM_REMOTE_COVER_URL, true));
        if ($remoteCoverUrl === '') {
            return null;
        }

        $validatedUrl = $this->sanitizer->sanitizeRemoteUrl($remoteCoverUrl, $this->providerResolver->getEffectiveProvider($albumId));
        if ($validatedUrl === '') {
            return null;
        }

        return AlbumCover::remote($validatedUrl);
    }

}
