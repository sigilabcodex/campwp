<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Domain\Metadata\MetadataSanitizer;
use PHPUnit\Framework\TestCase;

final class MetadataSanitizerTest extends TestCase
{
    private MetadataSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new MetadataSanitizer();
    }

    public function testTrackAudioSourceTypesRemainBackwardCompatible(): void
    {
        self::assertSame('attachment', $this->sanitizer->sanitizeTrackAudioSourceType('attachment'));
        self::assertSame('external_url', $this->sanitizer->sanitizeTrackAudioSourceType('external_url'));
        self::assertSame('internet_archive', $this->sanitizer->sanitizeTrackAudioSourceType('internet_archive'));
        self::assertSame('attachment', $this->sanitizer->sanitizeTrackAudioSourceType('bad'));
    }

    public function testProvidersSanitizeConservativelyWithUnsetDistinctFromDirect(): void
    {
        self::assertSame('', $this->sanitizer->sanitizeProvider(''));
        self::assertSame('', $this->sanitizer->sanitizeProvider('unknown-provider'));
        self::assertSame('direct', $this->sanitizer->sanitizeProvider('direct'));
        self::assertSame('internet_archive', $this->sanitizer->sanitizeProvider('Internet Archive'));
        self::assertSame('bandcamp', $this->sanitizer->sanitizeProvider('bandcamp'));
        self::assertSame('backblaze_b2', $this->sanitizer->sanitizeProvider('backblaze_b2'));
        self::assertSame('s3', $this->sanitizer->sanitizeProvider('s3'));
        self::assertSame('other', $this->sanitizer->sanitizeProvider('other'));
    }

    public function testInternetArchiveIdentifierValidation(): void
    {
        self::assertSame('mdk148-the-trade-wind-desert-soundscape', $this->sanitizer->sanitizeInternetArchiveIdentifier(' mdk148-the-trade-wind-desert-soundscape '));
        self::assertSame('', $this->sanitizer->sanitizeInternetArchiveIdentifier('https://archive.org/details/item'));
        self::assertSame('', $this->sanitizer->sanitizeInternetArchiveIdentifier('folder/item'));
        self::assertSame('', $this->sanitizer->sanitizeInternetArchiveIdentifier(str_repeat('a', 101)));
    }

    public function testStatusFormatTimestampChecksumAndSizeValidation(): void
    {
        self::assertSame('synced', $this->sanitizer->sanitizeSyncStatus('synced'));
        self::assertSame('never_synced', $this->sanitizer->sanitizeSyncStatus('done'));
        self::assertSame('available', $this->sanitizer->sanitizeDerivativeStatus('available'));
        self::assertSame('unknown', $this->sanitizer->sanitizeDerivativeStatus('ready'));
        self::assertSame('flac', $this->sanitizer->sanitizeFormatName('FLAC'));
        self::assertSame('', $this->sanitizer->sanitizeFormatName('../flac'));
        self::assertSame(12345, $this->sanitizer->sanitizePositiveFileSize('12345'));
        self::assertSame(0, $this->sanitizer->sanitizePositiveFileSize('0'));
        self::assertSame('2026-07-01T18:00:00Z', $this->sanitizer->sanitizeIso8601Timestamp('2026-07-01T12:00:00-06:00'));
        self::assertSame('', $this->sanitizer->sanitizeIso8601Timestamp('2026-07-01'));
        self::assertSame('md5:' . str_repeat('a', 32), $this->sanitizer->sanitizeChecksum('MD5:' . str_repeat('A', 32)));
        self::assertSame('sha1:' . str_repeat('b', 40), $this->sanitizer->sanitizeChecksum('sha1:' . str_repeat('b', 40)));
        self::assertSame('sha256:' . str_repeat('c', 64), $this->sanitizer->sanitizeSourcePayloadHash('sha256:' . str_repeat('c', 64)));
        self::assertSame('', $this->sanitizer->sanitizeChecksum('sha256:nothex'));
    }

    public function testLocalPathDetectionThroughStrictRemoteUrlValidation(): void
    {
        self::assertSame('', $this->sanitizer->sanitizeRemoteUrl('/home/diegom/Music/file.flac', 'internet_archive'));
        self::assertSame('', $this->sanitizer->sanitizeRemoteUrl('C:\\Music\\file.flac', 'internet_archive'));
        self::assertSame('', $this->sanitizer->sanitizeRemoteUrl('C:/Music/file.flac', 'internet_archive'));
        self::assertSame('https://archive.org/download/item/file.flac', $this->sanitizer->sanitizeRemoteUrl('https://archive.org/download/item/file.flac', 'internet_archive'));
        self::assertSame('', $this->sanitizer->sanitizeRemoteUrl('mdk148-the-trade-wind-desert-soundscape', 'internet_archive'));
    }

    public function testStrictRemoteUrlPolicyAcceptsAllowedHttpsHosts(): void
    {
        self::assertSame('https://archive.org/download/item/file.mp3', $this->sanitizer->sanitizeRemoteUrl('https://archive.org/download/item/file.mp3', 'internet_archive'));
        self::assertSame('https://ia800304.us.archive.org/1/items/item/file.mp3', $this->sanitizer->sanitizeRemoteUrl('https://ia800304.us.archive.org/1/items/item/file.mp3', 'internet_archive'));
        self::assertSame('https://artist.bandcamp.com/album/example', $this->sanitizer->sanitizeRemoteUrl('https://artist.bandcamp.com/album/example', 'bandcamp'));
        self::assertSame('https://www.bandcamp.com/album/example', $this->sanitizer->sanitizeRemoteUrl('https://www.bandcamp.com/album/example', 'bandcamp'));
        self::assertSame('https://mdkband.com/releases/example', $this->sanitizer->sanitizeRemoteUrl('https://mdkband.com/releases/example'));
    }

    /**
     * @dataProvider rejectedRemoteUrls
     */
    public function testStrictRemoteUrlPolicyRejectsUnsafeUrls(string $url, string $provider = 'internet_archive'): void
    {
        self::assertSame('', $this->sanitizer->sanitizeRemoteUrl($url, $provider));
    }

    /**
     * @return list<array{0:string,1?:string}>
     */
    public static function rejectedRemoteUrls(): array
    {
        return [
            ['http://archive.org/download/item/file.mp3'],
            ['https://localhost/file.mp3'],
            ['https://127.0.0.1/file.mp3'],
            ['https://10.0.0.1/file.mp3'],
            ['https://172.16.0.1/file.mp3'],
            ['https://192.168.1.10/file.mp3'],
            ['https://[::1]/file.mp3'],
            ['https://169.254.1.1/file.mp3'],
            ['https://2130706433/file.mp3', 'direct'],
            ['https://0x7f000001/file.mp3', 'direct'],
            ['https://0177.0.0.1/file.mp3', 'direct'],
            ['https://12345/file.mp3', 'direct'],
            ['https://0x7f.0.0.1/file.mp3', 'direct'],
            ['/home/diegom/Music/file.mp3'],
            ['https://user:pass@archive.org/download/item/file.mp3'],
            ['javascript:alert(1)'],
            ['data:text/plain,hello'],
            ['file:///tmp/file.mp3'],
            ['ftp://archive.org/file.mp3'],
            ['https://archive.org.example.com/download/item/file.mp3'],
            ['https://bandcamp.com.example.org/album/example', 'bandcamp'],
            ['https://fakebandcamp.com/album/example', 'bandcamp'],
            ['https://bandcamp.example.com/album/example', 'bandcamp'],
        ];
    }
}
