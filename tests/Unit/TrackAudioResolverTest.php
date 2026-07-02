<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Domain\Audio\TrackAudioResolver;
use CampWP\Domain\Media\MediaAsset;
use CampWP\Domain\Media\MediaStorageProviderInterface;
use CampWP\Domain\Metadata\MetadataKeys;
use PHPUnit\Framework\TestCase;

final class TrackAudioResolverTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['campwp_test_meta'] = [];
    }

    public function testAttachmentPlaybackAndDownloadRemainUnchanged(): void
    {
        $resolver = new TrackAudioResolver(new FakeMediaProvider());
        $GLOBALS['campwp_test_meta'][1] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'attachment',
            MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID => 10,
            MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID => 10,
            MetadataKeys::TRACK_AUDIO_MP3_ATTACHMENT_ID => 20,
            MetadataKeys::TRACK_AUDIO_STREAMING_ATTACHMENT_ID => 30,
        ];

        self::assertSame('https://media.example/30.mp3', $resolver->getTrackPlaybackFile(1)?->getUrl());
        self::assertSame('https://media.example/10.flac', $resolver->getTrackDownloadFile(1)?->getUrl());
    }

    public function testExternalUrlPlaybackAndDownloadRemainUnchanged(): void
    {
        $resolver = new TrackAudioResolver(new FakeMediaProvider());
        $GLOBALS['campwp_test_meta'][2] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'external_url',
            MetadataKeys::TRACK_AUDIO_EXTERNAL_URL => 'http://legacy.example/audio.mp3',
        ];

        self::assertSame('http://legacy.example/audio.mp3', $resolver->getTrackPlaybackFile(2)?->getUrl());
        self::assertSame('http://legacy.example/audio.mp3', $resolver->getTrackDownloadFile(2)?->getUrl());
    }

    public function testInternetArchivePlaybackAndDownloadUseSeparateUrlsWithImplicitProvider(): void
    {
        $resolver = new TrackAudioResolver(new FakeMediaProvider());
        $GLOBALS['campwp_test_meta'][3] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL => 'https://archive.org/download/item/track.mp3',
            MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL => 'https://archive.org/download/item/track.flac',
        ];

        self::assertSame('https://archive.org/download/item/track.mp3', $resolver->getTrackPlaybackFile(3)?->getUrl());
        self::assertSame('https://archive.org/download/item/track.flac', $resolver->getTrackDownloadFile(3)?->getUrl());
    }

    public function testInternetArchiveDownloadFallsBackToOriginalFlac(): void
    {
        $resolver = new TrackAudioResolver(new FakeMediaProvider());
        $GLOBALS['campwp_test_meta'][4] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_AUDIO_ORIGINAL_URL => 'https://archive.org/download/item/source.flac',
        ];

        self::assertSame('https://archive.org/download/item/source.flac', $resolver->getTrackDownloadFile(4)?->getUrl());
    }

    public function testInternetArchivePlaybackRejectsUnsafeRemoteUrlAndFallsBackSafely(): void
    {
        $resolver = new TrackAudioResolver(new FakeMediaProvider());
        $GLOBALS['campwp_test_meta'][5] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL => 'https://127.0.0.1/audio.mp3',
        ];

        self::assertNull($resolver->getTrackPlaybackFile(5));
    }

    public function testInternetArchiveLegacyExternalUrlFallbackWorks(): void
    {
        $resolver = new TrackAudioResolver(new FakeMediaProvider());
        $GLOBALS['campwp_test_meta'][6] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_AUDIO_EXTERNAL_URL => 'https://legacy.example/audio.mp3',
        ];

        self::assertSame('https://legacy.example/audio.mp3', $resolver->getTrackPlaybackFile(6)?->getUrl());
        self::assertSame('https://legacy.example/audio.mp3', $resolver->getTrackDownloadFile(6)?->getUrl());
    }

    /**
     * @dataProvider explicitProviderPlaybackUrls
     */
    public function testInternetArchiveSourceTypeConsultsExplicitProvider(string $provider, string $url): void
    {
        $resolver = new TrackAudioResolver(new FakeMediaProvider());
        $GLOBALS['campwp_test_meta'][7] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_SOURCE_PROVIDER => $provider,
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL => $url,
        ];

        self::assertSame($url, $resolver->getTrackPlaybackFile(7)?->getUrl());
    }

    /**
     * @return list<array{string,string}>
     */
    public static function explicitProviderPlaybackUrls(): array
    {
        return [
            ['backblaze_b2', 'https://files.example.com/audio.mp3'],
            ['s3', 'https://bucket.s3.amazonaws.com/audio.mp3'],
            ['direct', 'https://cdn.example.com/audio.mp3'],
            ['bandcamp', 'https://artist.bandcamp.com/track/audio.mp3'],
        ];
    }

    public function testImplicitInternetArchiveProviderRejectsNonInternetArchiveHosts(): void
    {
        $resolver = new TrackAudioResolver(new FakeMediaProvider());
        $GLOBALS['campwp_test_meta'][8] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL => 'https://cdn.example.com/audio.mp3',
        ];

        self::assertNull($resolver->getTrackPlaybackFile(8));
    }

    public function testExplicitProviderIsNotSilentlyReinterpretedAsInternetArchive(): void
    {
        $resolver = new TrackAudioResolver(new FakeMediaProvider());
        $GLOBALS['campwp_test_meta'][9] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_SOURCE_PROVIDER => 'internet_archive',
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL => 'https://cdn.example.com/audio.mp3',
        ];
        $GLOBALS['campwp_test_meta'][10] = [
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_SOURCE_PROVIDER => 'direct',
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL => 'https://cdn.example.com/audio.mp3',
        ];

        self::assertNull($resolver->getTrackPlaybackFile(9));
        self::assertSame('https://cdn.example.com/audio.mp3', $resolver->getTrackPlaybackFile(10)?->getUrl());
    }
}

final class FakeMediaProvider implements MediaStorageProviderInterface
{
    public function isValidReference(int $referenceId): bool
    {
        return $referenceId > 0;
    }

    public function isAudioReference(int $referenceId): bool
    {
        return $referenceId > 0;
    }

    public function resolve(int $referenceId): ?MediaAsset
    {
        $urls = [
            10 => ['https://media.example/10.flac', 'audio/flac'],
            20 => ['https://media.example/20.mp3', 'audio/mpeg'],
            30 => ['https://media.example/30.mp3', 'audio/mpeg'],
        ];

        if (! isset($urls[$referenceId])) {
            return null;
        }

        return new MediaAsset($referenceId, $urls[$referenceId][0], $urls[$referenceId][1], '', '');
    }
}
