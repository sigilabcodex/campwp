<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\ContentModel\ReleaseBuilderService;
use CampWP\Domain\Metadata\MetadataKeys;
use PHPUnit\Framework\TestCase;

final class ReleaseBuilderSourceSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['campwp_test_meta'] = [];
        $GLOBALS['campwp_deleted_meta'] = [];
        $GLOBALS['campwp_test_post_types'] = [99 => 'campwp_track'];
        $GLOBALS['campwp_test_attachment_mimes'] = [10 => 'audio/flac'];
        $GLOBALS['campwp_test_attachment_files'] = [10 => 'source.flac'];
    }

    public function testSavingAttachmentSourceClearsRemoteAudioFieldsAndPreservesProvenance(): void
    {
        $service = $this->service();
        $GLOBALS['campwp_test_meta'][99] = $this->remoteTrackMeta() + [
            MetadataKeys::TRACK_AUDIO_EXTERNAL_URL => 'https://legacy.example/audio.mp3',
        ];

        $service->saveInlineTrackFields(99, [
            'audio_source_type' => 'attachment',
            'audio_attachment_id' => '10',
        ]);

        self::assertSame('attachment', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_AUDIO_SOURCE_TYPE]);
        self::assertSame(10, $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID]);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_AUDIO_EXTERNAL_URL, $GLOBALS['campwp_test_meta'][99]);
        self::assertProviderBackedAudioMetaCleared(99);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_SOURCE_PROVIDER, $GLOBALS['campwp_test_meta'][99]);
        self::assertSame('mdk148:01', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_EXTERNAL_TRACK_ID]);
        self::assertSame('sha256:' . str_repeat('a', 64), $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_SOURCE_PAYLOAD_HASH]);
        self::assertSame('synced', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_SYNC_STATUS]);
    }

    public function testSavingExternalUrlClearsAttachmentAndRemoteAudioFieldsButPreservesProvenance(): void
    {
        $service = $this->service();
        $GLOBALS['campwp_test_meta'][99] = $this->remoteTrackMeta() + [
            MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID => 10,
            MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID => 10,
            MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID => 20,
        ];

        $service->saveInlineTrackFields(99, [
            'audio_source_type' => 'external_url',
            'audio_external_url' => 'https://legacy.example/audio.mp3',
        ]);

        self::assertSame('external_url', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_AUDIO_SOURCE_TYPE]);
        self::assertSame('https://legacy.example/audio.mp3', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_AUDIO_EXTERNAL_URL]);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, $GLOBALS['campwp_test_meta'][99]);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID, $GLOBALS['campwp_test_meta'][99]);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID, $GLOBALS['campwp_test_meta'][99]);
        self::assertProviderBackedAudioMetaCleared(99);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_SOURCE_PROVIDER, $GLOBALS['campwp_test_meta'][99]);
        self::assertSame('mdk148:01', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_EXTERNAL_TRACK_ID]);
        self::assertSame('sha256:' . str_repeat('a', 64), $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_SOURCE_PAYLOAD_HASH]);
    }

    public function testSavingInternetArchiveClearsAttachmentFieldsPreservesRemoteFieldsAndSetsEmptyProvider(): void
    {
        $service = $this->service();
        $GLOBALS['campwp_test_meta'][99] = $this->remoteTrackMeta() + [
            MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID => 10,
            MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID => 10,
            MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID => 20,
            MetadataKeys::TRACK_SOURCE_PROVIDER => '',
        ];

        $service->saveInlineTrackFields(99, [
            'audio_source_type' => 'internet_archive',
        ]);

        self::assertSame('internet_archive', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_AUDIO_SOURCE_TYPE]);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, $GLOBALS['campwp_test_meta'][99]);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID, $GLOBALS['campwp_test_meta'][99]);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID, $GLOBALS['campwp_test_meta'][99]);
        self::assertSame('internet_archive', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_SOURCE_PROVIDER]);
        self::assertSame('https://archive.org/download/item/source.flac', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_AUDIO_ORIGINAL_URL]);
        self::assertSame('https://archive.org/download/item/playback.mp3', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_AUDIO_PLAYBACK_URL]);
        self::assertSame('available', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_REMOTE_DERIVATIVE_STATUS]);
    }

    public function testSavingInternetArchiveDoesNotOverwriteExplicitValidProvider(): void
    {
        $service = $this->service();
        $GLOBALS['campwp_test_meta'][99] = array_merge($this->remoteTrackMeta(), [
            MetadataKeys::TRACK_SOURCE_PROVIDER => 's3',
            MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID => 10,
        ]);

        $service->saveInlineTrackFields(99, [
            'audio_source_type' => 'internet_archive',
        ]);

        self::assertSame('s3', $GLOBALS['campwp_test_meta'][99][MetadataKeys::TRACK_SOURCE_PROVIDER]);
        self::assertArrayNotHasKey(MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID, $GLOBALS['campwp_test_meta'][99]);
    }

    /**
     * @return array<string, string|int>
     */
    private function remoteTrackMeta(): array
    {
        return [
            MetadataKeys::TRACK_EXTERNAL_TRACK_ID => 'mdk148:01',
            MetadataKeys::TRACK_SOURCE_PROVIDER => 'internet_archive',
            MetadataKeys::TRACK_AUDIO_ORIGINAL_URL => 'https://archive.org/download/item/source.flac',
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL => 'https://archive.org/download/item/playback.mp3',
            MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL => 'https://archive.org/download/item/source.flac',
            MetadataKeys::TRACK_AUDIO_ORIGINAL_FORMAT => 'flac',
            MetadataKeys::TRACK_AUDIO_PLAYBACK_FORMAT => 'mp3',
            MetadataKeys::TRACK_AUDIO_ORIGINAL_SIZE => 123,
            MetadataKeys::TRACK_AUDIO_PLAYBACK_SIZE => 45,
            MetadataKeys::TRACK_AUDIO_ORIGINAL_CHECKSUM => 'md5:' . str_repeat('b', 32),
            MetadataKeys::TRACK_AUDIO_PLAYBACK_CHECKSUM => 'md5:' . str_repeat('c', 32),
            MetadataKeys::TRACK_REMOTE_DERIVATIVE_STATUS => 'available',
            MetadataKeys::TRACK_SOURCE_PAYLOAD_HASH => 'sha256:' . str_repeat('a', 64),
            MetadataKeys::TRACK_LAST_SYNCED_AT => '2026-07-01T18:00:00Z',
            MetadataKeys::TRACK_SYNC_STATUS => 'synced',
            MetadataKeys::TRACK_SYNC_MESSAGE => 'verified',
        ];
    }

    private static function assertProviderBackedAudioMetaCleared(int $trackId): void
    {
        foreach ([
            MetadataKeys::TRACK_AUDIO_ORIGINAL_URL,
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL,
            MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL,
            MetadataKeys::TRACK_AUDIO_ORIGINAL_FORMAT,
            MetadataKeys::TRACK_AUDIO_PLAYBACK_FORMAT,
            MetadataKeys::TRACK_AUDIO_ORIGINAL_SIZE,
            MetadataKeys::TRACK_AUDIO_PLAYBACK_SIZE,
            MetadataKeys::TRACK_AUDIO_ORIGINAL_CHECKSUM,
            MetadataKeys::TRACK_AUDIO_PLAYBACK_CHECKSUM,
            MetadataKeys::TRACK_REMOTE_DERIVATIVE_STATUS,
        ] as $metaKey) {
            self::assertArrayNotHasKey($metaKey, $GLOBALS['campwp_test_meta'][$trackId]);
        }
    }

    private function service(): ReleaseBuilderService
    {
        return new ReleaseBuilderService(null, new TrackAudioResolver(new FakeMediaProvider()));
    }
}
