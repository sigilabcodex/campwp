<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Admin\Metadata\CoreMetadataMetaBox;
use CampWP\Domain\Metadata\MetadataKeys;
use PHPUnit\Framework\TestCase;

final class AdminRemoteMediaSummaryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['campwp_test_meta'] = [];
    }

    public function testAlbumSummaryShowsArchiveCoverForIaBackedAlbumWithEmptyProvider(): void
    {
        $GLOBALS['campwp_test_meta'][101] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => '',
            MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER => 'example-item',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'https://archive.org/download/example-item/cover.jpg',
        ];

        $html = $this->renderAlbumSummary(101);

        self::assertStringContainsString('Remote cover URL', $html);
        self::assertStringContainsString('href="https://archive.org/download/example-item/cover.jpg"', $html);
    }

    public function testAlbumSummaryHidesNonIaCoverForIaBackedAlbumWithEmptyProvider(): void
    {
        $GLOBALS['campwp_test_meta'][101] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => '',
            MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER => 'example-item',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'https://cdn.example.test/cover.jpg',
        ];

        self::assertSame('', $this->renderAlbumSummary(101));
    }

    public function testAlbumSummaryRespectsExplicitProvider(): void
    {
        $GLOBALS['campwp_test_meta'][101] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => 'bandcamp',
            MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER => 'example-item',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'https://artist.bandcamp.com/img/cover.jpg',
        ];

        $html = $this->renderAlbumSummary(101);

        self::assertStringContainsString('bandcamp', $html);
        self::assertStringContainsString('href="https://artist.bandcamp.com/img/cover.jpg"', $html);

        $GLOBALS['campwp_test_meta'][101][MetadataKeys::ALBUM_REMOTE_COVER_URL] = 'https://archive.org/download/example-item/cover.jpg';
        $html = $this->renderAlbumSummary(101);

        self::assertStringContainsString('bandcamp', $html);
        self::assertStringNotContainsString('href="https://archive.org/download/example-item/cover.jpg"', $html);
    }

    public function testAlbumSummaryEmitsNothingWhenRemoteCoverIsEmpty(): void
    {
        $GLOBALS['campwp_test_meta'][101] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => '',
            MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER => 'example-item',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => '',
        ];

        self::assertSame('', $this->renderAlbumSummary(101));
    }

    private function renderAlbumSummary(int $albumId): string
    {
        $metabox = new CoreMetadataMetaBox();
        $method = new \ReflectionMethod($metabox, 'renderRemoteAlbumMediaSummary');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($metabox, $albumId);

        return (string) ob_get_clean();
    }
}
