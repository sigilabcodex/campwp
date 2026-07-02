<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Domain\Metadata\MetadataKeys;
use PHPUnit\Framework\TestCase;

final class MetadataKeysTest extends TestCase
{
    public function testNewAlbumKeysExist(): void
    {
        foreach ([
            'ALBUM_SOURCE_PROVIDER',
            'ALBUM_CATALOG_IDENTITY',
            'ALBUM_EXTERNAL_RELEASE_ID',
            'ALBUM_EXTERNAL_ITEM_URL',
            'ALBUM_INTERNET_ARCHIVE_IDENTIFIER',
            'ALBUM_INTERNET_ARCHIVE_METADATA_URL',
            'ALBUM_BANDCAMP_URL',
            'ALBUM_PROJECT_URL',
            'ALBUM_LICENSE_NAME',
            'ALBUM_LICENSE_CODE',
            'ALBUM_LICENSE_URL',
            'ALBUM_REMOTE_COVER_URL',
            'ALBUM_SOURCE_PAYLOAD_HASH',
            'ALBUM_LAST_SYNCED_AT',
            'ALBUM_SYNC_STATUS',
            'ALBUM_SYNC_MESSAGE',
        ] as $constant) {
            self::assertTrue(defined(MetadataKeys::class . '::' . $constant), $constant);
        }
    }

    public function testNewTrackKeysExist(): void
    {
        foreach ([
            'TRACK_EXTERNAL_TRACK_ID',
            'TRACK_EXTERNAL_TRACK_INDEX',
            'TRACK_SOURCE_PROVIDER',
            'TRACK_AUDIO_ORIGINAL_URL',
            'TRACK_AUDIO_PLAYBACK_URL',
            'TRACK_AUDIO_DOWNLOAD_URL',
            'TRACK_AUDIO_ORIGINAL_FORMAT',
            'TRACK_AUDIO_PLAYBACK_FORMAT',
            'TRACK_AUDIO_ORIGINAL_SIZE',
            'TRACK_AUDIO_PLAYBACK_SIZE',
            'TRACK_AUDIO_ORIGINAL_CHECKSUM',
            'TRACK_AUDIO_PLAYBACK_CHECKSUM',
            'TRACK_REMOTE_DERIVATIVE_STATUS',
            'TRACK_SOURCE_PAYLOAD_HASH',
            'TRACK_LAST_SYNCED_AT',
            'TRACK_SYNC_STATUS',
            'TRACK_SYNC_MESSAGE',
        ] as $constant) {
            self::assertTrue(defined(MetadataKeys::class . '::' . $constant), $constant);
        }
    }
}
