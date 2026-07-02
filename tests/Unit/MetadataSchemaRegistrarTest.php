<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Domain\Metadata\MetadataSchemaRegistrar;
use PHPUnit\Framework\TestCase;

final class MetadataSchemaRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['campwp_registered_meta'] = [];
    }

    /**
     * @dataProvider expectedMetaDefinitions
     */
    public function testExpectedPostMetaDefinitionsAreRegistered(string $postType, string $key, string $type, int|string $default): void
    {
        (new MetadataSchemaRegistrar())->registerMeta();

        self::assertArrayHasKey($postType, $GLOBALS['campwp_registered_meta']);
        self::assertArrayHasKey($key, $GLOBALS['campwp_registered_meta'][$postType]);

        $definition = $GLOBALS['campwp_registered_meta'][$postType][$key];
        self::assertSame($type, $definition['type']);
        self::assertSame($default, $definition['default']);
        self::assertTrue($definition['single']);
        self::assertTrue($definition['show_in_rest']);
        self::assertIsCallable($definition['auth_callback']);
        self::assertIsCallable($definition['sanitize_callback']);
        self::assertTrue($definition['auth_callback']());
    }

    /**
     * @return list<array{string,string,string,int|string}>
     */
    public static function expectedMetaDefinitions(): array
    {
        return [
            ['campwp_track', MetadataKeys::TRACK_ALBUM_ID, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_ORDER, 'integer', 0],

            ['campwp_album', MetadataKeys::ALBUM_SUBTITLE, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_CATALOG_NUMBER, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_ARTIST_DISPLAY, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_LABEL_NAME, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_CREDITS_OVERRIDE, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_RELEASE_NOTES, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_RELEASE_TYPE, 'string', 'album'],
            ['campwp_album', MetadataKeys::ALBUM_BONUS_ITEMS, 'string', '[]'],
            ['campwp_album', MetadataKeys::ALBUM_RELEASE_DATE, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_DOWNLOAD_ENABLED, 'integer', 0],
            ['campwp_album', MetadataKeys::ALBUM_DOWNLOAD_MODE, 'string', 'public'],
            ['campwp_album', MetadataKeys::ALBUM_PRODUCT_ID, 'integer', 0],

            ['campwp_album', MetadataKeys::ALBUM_SOURCE_PROVIDER, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_EXTERNAL_ITEM_URL, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_INTERNET_ARCHIVE_METADATA_URL, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_BANDCAMP_URL, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_PROJECT_URL, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_LICENSE_NAME, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_LICENSE_CODE, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_LICENSE_URL, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_REMOTE_COVER_URL, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_SOURCE_PAYLOAD_HASH, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_LAST_SYNCED_AT, 'string', ''],
            ['campwp_album', MetadataKeys::ALBUM_SYNC_STATUS, 'string', 'never_synced'],
            ['campwp_album', MetadataKeys::ALBUM_SYNC_MESSAGE, 'string', ''],

            ['campwp_track', MetadataKeys::TRACK_NUMBER, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_SUBTITLE, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_DURATION, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_ARTIST_DISPLAY, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_CREDITS, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_LYRICS, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_ISRC, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_ARTWORK_ID, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_SOURCE_TYPE, 'string', 'attachment'],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_EXTERNAL_URL, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_OGG_ATTACHMENT_ID, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_STREAMING_ATTACHMENT_ID, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_SOURCE_CLASSIFICATION, 'string', 'unknown'],
            ['campwp_track', MetadataKeys::TRACK_DOWNLOAD_ENABLED, 'integer', 1],
            ['campwp_track', MetadataKeys::TRACK_DOWNLOAD_MODE, 'string', 'public'],
            ['campwp_track', MetadataKeys::TRACK_PRODUCT_ID, 'integer', 0],

            ['campwp_track', MetadataKeys::TRACK_EXTERNAL_TRACK_ID, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_EXTERNAL_TRACK_INDEX, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_SOURCE_PROVIDER, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_ORIGINAL_URL, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_PLAYBACK_URL, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_ORIGINAL_FORMAT, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_PLAYBACK_FORMAT, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_ORIGINAL_SIZE, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_PLAYBACK_SIZE, 'integer', 0],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_ORIGINAL_CHECKSUM, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_AUDIO_PLAYBACK_CHECKSUM, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_REMOTE_DERIVATIVE_STATUS, 'string', 'unknown'],
            ['campwp_track', MetadataKeys::TRACK_SOURCE_PAYLOAD_HASH, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_LAST_SYNCED_AT, 'string', ''],
            ['campwp_track', MetadataKeys::TRACK_SYNC_STATUS, 'string', 'never_synced'],
            ['campwp_track', MetadataKeys::TRACK_SYNC_MESSAGE, 'string', ''],
        ];
    }

    public function testRepresentativeSanitizerCallbacksPreserveExpectedBehavior(): void
    {
        (new MetadataSchemaRegistrar())->registerMeta();

        self::assertSame('attachment', $this->sanitize('campwp_track', MetadataKeys::TRACK_AUDIO_SOURCE_TYPE, 'invalid'));
        self::assertSame('internet_archive', $this->sanitize('campwp_track', MetadataKeys::TRACK_AUDIO_SOURCE_TYPE, 'internet_archive'));
        self::assertSame('', $this->sanitize('campwp_track', MetadataKeys::TRACK_SOURCE_PROVIDER, 'invalid'));
        self::assertSame('direct', $this->sanitize('campwp_track', MetadataKeys::TRACK_SOURCE_PROVIDER, 'direct'));
        self::assertSame('', $this->sanitize('campwp_album', MetadataKeys::ALBUM_SOURCE_PROVIDER, 'invalid'));
        self::assertSame('https://artist.bandcamp.com/album/example', $this->sanitize('campwp_album', MetadataKeys::ALBUM_BANDCAMP_URL, 'https://artist.bandcamp.com/album/example'));
        self::assertSame('', $this->sanitize('campwp_album', MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER, 'https://archive.org/details/item'));
        self::assertSame('unknown', $this->sanitize('campwp_track', MetadataKeys::TRACK_REMOTE_DERIVATIVE_STATUS, 'ready'));
        self::assertSame('never_synced', $this->sanitize('campwp_album', MetadataKeys::ALBUM_SYNC_STATUS, 'done'));
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitize(string $postType, string $key, $value)
    {
        $callback = $GLOBALS['campwp_registered_meta'][$postType][$key]['sanitize_callback'];
        self::assertIsCallable($callback);

        return $callback($value);
    }
}
