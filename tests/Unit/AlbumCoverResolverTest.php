<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Domain\Media\AlbumCoverResolver;
use CampWP\Domain\Metadata\MetadataKeys;
use PHPUnit\Framework\TestCase;

final class AlbumCoverResolverTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['campwp_test_meta'] = [];
        $GLOBALS['campwp_test_thumbnail_ids'] = [];
    }

    public function testFeaturedImageWinsOverRemoteCover(): void
    {
        $GLOBALS['campwp_test_thumbnail_ids'][100] = 55;
        $GLOBALS['campwp_test_meta'][100] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => 'internet_archive',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'https://archive.org/download/item/cover.jpg',
        ];

        $cover = (new AlbumCoverResolver())->resolve(100);

        self::assertNotNull($cover);
        self::assertTrue($cover->isAttachment());
        self::assertSame(55, $cover->getAttachmentId());
    }

    public function testValidRemoteCoverIsUsedWhenNoFeaturedImageExists(): void
    {
        $GLOBALS['campwp_test_meta'][100] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => 'direct',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'https://cdn.example.test/cover.jpg',
        ];

        $cover = (new AlbumCoverResolver())->resolve(100);

        self::assertNotNull($cover);
        self::assertTrue($cover->isRemote());
        self::assertSame('https://cdn.example.test/cover.jpg', $cover->getUrl());
    }

    public function testInvalidRemoteCoverIsRejected(): void
    {
        $GLOBALS['campwp_test_meta'][100] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => 'direct',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'not-a-url',
        ];

        self::assertNull((new AlbumCoverResolver())->resolve(100));
    }

    public function testUnsafeHostIsRejected(): void
    {
        $GLOBALS['campwp_test_meta'][100] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => 'direct',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'https://127.0.0.1/cover.jpg',
        ];

        self::assertNull((new AlbumCoverResolver())->resolve(100));
    }

    public function testProviderSpecificHostValidationIsApplied(): void
    {
        $GLOBALS['campwp_test_meta'][100] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => 'internet_archive',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'https://cdn.example.test/cover.jpg',
        ];

        self::assertNull((new AlbumCoverResolver())->resolve(100));
    }

    public function testInternetArchiveFallbackProviderWorksWhenProviderIsUnsetAndAlbumIsIaBacked(): void
    {
        $GLOBALS['campwp_test_meta'][100] = [
            MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER => 'example-item',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'https://archive.org/download/example-item/cover.jpg',
        ];

        $cover = (new AlbumCoverResolver())->resolve(100);

        self::assertNotNull($cover);
        self::assertTrue($cover->isRemote());
        self::assertSame('https://archive.org/download/example-item/cover.jpg', $cover->getUrl());
    }

    public function testEmptyCoverReturnsNoResolvedCover(): void
    {
        self::assertNull((new AlbumCoverResolver())->resolve(100));
    }
}
