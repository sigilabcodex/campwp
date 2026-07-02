<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Domain\Metadata\MetadataKeys;
use CampWP\Frontend\Data\AlbumViewDataProvider;
use CampWP\Frontend\Rendering\AlbumPageRenderer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class AlbumRemoteMediaRenderingTest extends TestCase
{
    private const PLAYBACK_URL = 'https://archive.org/download/example-item/01%20-%20One.mp3';
    private const DOWNLOAD_URL = 'https://archive.org/download/example-item/01%20-%20One.flac';

    protected function setUp(): void
    {
        $GLOBALS['campwp_test_meta'] = [];
        $GLOBALS['campwp_test_posts'] = [];
        $GLOBALS['campwp_test_thumbnail_ids'] = [];
        $GLOBALS['campwp_test_attachment_mimes'] = [];
        $GLOBALS['campwp_test_attachment_urls'] = [];
        $GLOBALS['campwp_test_attachment_files'] = [];
        $GLOBALS['campwp_test_options'] = ['permalink_structure' => '/%postname%/'];
        $GLOBALS['campwp_test_is_user_logged_in'] = false;
        $GLOBALS['campwp_test_current_user_id'] = 0;
        $GLOBALS['campwp_test_customer_bought_product'] = [];
    }

    public function testRemoteCoverPlaybackAndDownloadRenderForInternetArchiveTracks(): void
    {
        $album = $this->remoteAlbumFixture();

        $html = $this->renderAlbum($album);

        self::assertStringContainsString('src="https://archive.org/download/example-item/cover.jpg?x=1&amp;y=2"', $html);
        self::assertStringContainsString('alt="Remote &amp; Album cover art"', $html);
        self::assertStringContainsString('<source src="' . self::PLAYBACK_URL . '" type="audio/mpeg"', $html);
        self::assertStringContainsString('data-campwp-audio-src="' . self::PLAYBACK_URL . '"', $html);
        self::assertStringContainsString('href="' . self::DOWNLOAD_URL . '"', $html);
        self::assertStringContainsString('href="https://archive.org/download/example-item/02%20-%20Two.flac"', $html);
        self::assertStringContainsString('Producer — First Track', $html);
        self::assertStringNotContainsString('01 - One.mp3', $html);
    }

    public function testPlaybackAndDownloadUrlsAreNotSwapped(): void
    {
        $album = $this->remoteAlbumFixture();
        $html = $this->renderAlbum($album);

        self::assertStringContainsString('<source src="' . self::PLAYBACK_URL . '"', $html);
        self::assertStringContainsString('data-campwp-audio-src="' . self::PLAYBACK_URL . '"', $html);
        self::assertStringNotContainsString('href="' . self::PLAYBACK_URL . '"', $html);
        self::assertStringContainsString('href="' . self::DOWNLOAD_URL . '"', $html);
        self::assertStringNotContainsString('<source src="' . self::DOWNLOAD_URL . '"', $html);
        self::assertStringNotContainsString('data-campwp-audio-src="' . self::DOWNLOAD_URL . '"', $html);
    }

    public function testInvalidRemotePlaybackAndDownloadUrlsAreOmitted(): void
    {
        $album = $this->remoteAlbumFixture();
        $GLOBALS['campwp_test_meta'][201][MetadataKeys::TRACK_AUDIO_PLAYBACK_URL] = 'https://127.0.0.1/audio.mp3';
        $GLOBALS['campwp_test_meta'][201][MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL] = 'file:///tmp/source.flac';
        $GLOBALS['campwp_test_meta'][201][MetadataKeys::TRACK_AUDIO_ORIGINAL_URL] = '';
        unset($GLOBALS['campwp_test_meta'][202]);
        unset($GLOBALS['campwp_test_posts'][202]);

        $html = $this->renderAlbum($album);

        self::assertStringNotContainsString('127.0.0.1', $html);
        self::assertStringNotContainsString('file:///tmp/source.flac', $html);
        self::assertStringContainsString('No audio attached for this track yet.', $html);
        self::assertStringContainsString('data-campwp-cta-state="missing_file"', $html);
    }

    public function testAttachmentCoverAndAttachmentAudioBehaviorRemainUnchanged(): void
    {
        $album = new \WP_Post(['ID' => 101, 'post_title' => 'Attachment Album', 'post_type' => 'campwp_album', 'post_status' => 'publish']);
        $track = new \WP_Post(['ID' => 203, 'post_title' => 'Attachment Track', 'post_type' => 'campwp_track', 'post_status' => 'publish']);
        $attachment = new \WP_Post(['ID' => 303, 'post_title' => 'Source Attachment', 'post_type' => 'attachment', 'post_status' => 'inherit']);
        $GLOBALS['campwp_test_posts'] = [101 => $album, 203 => $track, 303 => $attachment];
        $GLOBALS['campwp_test_thumbnail_ids'][101] = 77;
        $GLOBALS['campwp_test_meta'][101] = [MetadataKeys::ALBUM_DOWNLOAD_ENABLED => '1'];
        $GLOBALS['campwp_test_meta'][203] = [
            MetadataKeys::TRACK_ALBUM_ID => 101,
            MetadataKeys::TRACK_ORDER => 1,
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'attachment',
            MetadataKeys::TRACK_AUDIO_ATTACHMENT_ID => 303,
            MetadataKeys::TRACK_AUDIO_SOURCE_ATTACHMENT_ID => 303,
            MetadataKeys::TRACK_DOWNLOAD_ENABLED => '1',
        ];
        $GLOBALS['campwp_test_attachment_mimes'][303] = 'audio/flac';
        $GLOBALS['campwp_test_attachment_urls'][303] = 'https://media.example.test/source.flac';

        $html = $this->renderAlbum($album);

        self::assertStringContainsString('attachment-77.jpg', $html);
        self::assertStringContainsString('<source src="https://media.example.test/source.flac" type="audio/flac"', $html);
        self::assertStringContainsString('href="https://example.test/campwp-download/track/203/"', $html);
    }

    public function testExternalUrlTrackStillUsesLegacyUrlForPlaybackAndDownload(): void
    {
        $album = new \WP_Post(['ID' => 101, 'post_title' => 'External Album', 'post_type' => 'campwp_album', 'post_status' => 'publish']);
        $track = new \WP_Post(['ID' => 204, 'post_title' => 'External Track', 'post_type' => 'campwp_track', 'post_status' => 'publish']);
        $GLOBALS['campwp_test_posts'] = [101 => $album, 204 => $track];
        $GLOBALS['campwp_test_meta'][204] = [
            MetadataKeys::TRACK_ALBUM_ID => 101,
            MetadataKeys::TRACK_ORDER => 1,
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'external_url',
            MetadataKeys::TRACK_AUDIO_EXTERNAL_URL => 'http://legacy.example.test/audio.mp3',
            MetadataKeys::TRACK_DOWNLOAD_ENABLED => '1',
        ];

        $html = $this->renderAlbum($album);

        self::assertStringContainsString('<source src="http://legacy.example.test/audio.mp3" type="audio/mpeg"', $html);
        self::assertStringContainsString('href="http://legacy.example.test/audio.mp3"', $html);
    }

    public function testAlbumWithoutCoverDoesNotEmitBrokenImage(): void
    {
        $album = new \WP_Post(['ID' => 101, 'post_title' => 'No Cover', 'post_type' => 'campwp_album', 'post_status' => 'publish']);
        $GLOBALS['campwp_test_posts'] = [101 => $album];

        $html = $this->renderAlbum($album);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('campwp-release-cover', $html);
    }

    public function testPublicRemoteDownloadIsVisibleToAnonymousUser(): void
    {
        $album = $this->remoteAlbumFixture();
        $this->setTrackDownloadPolicy('public');

        $this->assertRemoteDownloadExposed($album);
    }

    public function testPublicRemoteDownloadIsVisibleToLoggedInUser(): void
    {
        $album = $this->remoteAlbumFixture();
        $this->setTrackDownloadPolicy('public');
        $this->logInUser();

        $this->assertRemoteDownloadExposed($album);
    }

    public function testRestrictedRemoteDownloadIsHiddenFromAnonymousUser(): void
    {
        $album = $this->remoteAlbumFixture();
        $this->setTrackDownloadPolicy('restricted');

        $html = $this->renderAlbum($album);
        $data = $this->getAlbumViewData($album);

        $this->assertRemoteDownloadAbsent($html, $data);
        $cta = $this->trackCta($data, 201);
        self::assertSame('login_required', $cta['state']);
        self::assertSame('https://example.test/wp-login.php', $cta['action_url']);
    }

    public function testRestrictedRemoteDownloadIsVisibleToLoggedInUser(): void
    {
        $album = $this->remoteAlbumFixture();
        $this->setTrackDownloadPolicy('restricted');
        $this->logInUser();

        $this->assertRemoteDownloadExposed($album);
    }

    public function testPurchaseRemoteDownloadIsHiddenWhenWooUnavailable(): void
    {
        $album = $this->remoteAlbumFixture();
        $this->setTrackDownloadPolicy('purchase', 555);
        $this->logInUser();

        $html = $this->renderAlbum($album);
        $data = $this->getAlbumViewData($album);

        $this->assertRemoteDownloadAbsent($html, $data);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPurchaseRemoteDownloadIsHiddenWhenProductIsMissing(): void
    {
        $this->enableWoo();
        $album = $this->remoteAlbumFixture();
        $this->setTrackDownloadPolicy('purchase', 0);
        $this->logInUser();

        $html = $this->renderAlbum($album);
        $data = $this->getAlbumViewData($album);

        $this->assertRemoteDownloadAbsent($html, $data);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPurchaseRemoteDownloadIsHiddenFromAnonymousUser(): void
    {
        $this->enableWoo();
        $album = $this->remoteAlbumFixture();
        $this->setTrackDownloadPolicy('purchase', 555);

        $html = $this->renderAlbum($album);
        $data = $this->getAlbumViewData($album);

        $this->assertRemoteDownloadAbsent($html, $data);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPurchaseRemoteDownloadIsHiddenFromLoggedInNonPurchaser(): void
    {
        $this->enableWoo();
        $album = $this->remoteAlbumFixture();
        $this->setTrackDownloadPolicy('purchase', 555);
        $this->logInUser();

        $html = $this->renderAlbum($album);
        $data = $this->getAlbumViewData($album);

        $this->assertRemoteDownloadAbsent($html, $data);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPurchaseRemoteDownloadIsVisibleToLoggedInPurchaser(): void
    {
        $this->enableWoo();
        $album = $this->remoteAlbumFixture();
        $this->setTrackDownloadPolicy('purchase', 555);
        $this->logInUser();
        $GLOBALS['campwp_test_customer_bought_product'][555] = true;

        $this->assertRemoteDownloadExposed($album);
    }

    public function testDisabledRemoteDownloadIsHidden(): void
    {
        $album = $this->remoteAlbumFixture();
        $GLOBALS['campwp_test_meta'][201][MetadataKeys::TRACK_DOWNLOAD_ENABLED] = '0';

        $html = $this->renderAlbum($album);
        $data = $this->getAlbumViewData($album);

        self::assertStringContainsString('data-campwp-cta-state="disabled"', $html);
        $this->assertRemoteDownloadAbsent($html, $data);
    }

    private function renderAlbum(\WP_Post $album): string
    {
        return (new AlbumPageRenderer(new AlbumViewDataProvider()))->render($album, '');
    }

    /**
     * @return array<string, mixed>
     */
    private function getAlbumViewData(\WP_Post $album): array
    {
        return (new AlbumViewDataProvider())->getAlbumViewData($album);
    }

    private function assertRemoteDownloadExposed(\WP_Post $album): void
    {
        $html = $this->renderAlbum($album);
        $data = $this->getAlbumViewData($album);
        $cta = $this->trackCta($data, 201);

        self::assertStringContainsString('href="' . self::DOWNLOAD_URL . '"', $html);
        self::assertSame(self::DOWNLOAD_URL, $cta['action_url']);
        self::assertStringContainsString(self::DOWNLOAD_URL, serialize($data));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertRemoteDownloadAbsent(string $html, array $data): void
    {
        self::assertStringNotContainsString(self::DOWNLOAD_URL, $html);
        self::assertStringNotContainsString(self::DOWNLOAD_URL, serialize($data));
        self::assertNotSame(self::DOWNLOAD_URL, $this->trackCta($data, 201)['action_url']);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function trackCta(array $data, int $trackId): array
    {
        foreach ((array) ($data['tracks'] ?? []) as $track) {
            if ((int) ($track['id'] ?? 0) === $trackId) {
                return (array) ($track['cta'] ?? []);
            }
        }

        self::fail('Track CTA not found.');
    }

    private function setTrackDownloadPolicy(string $mode, int $productId = 0): void
    {
        $GLOBALS['campwp_test_meta'][201][MetadataKeys::TRACK_DOWNLOAD_ENABLED] = '1';
        $GLOBALS['campwp_test_meta'][201][MetadataKeys::TRACK_DOWNLOAD_MODE] = $mode;
        $GLOBALS['campwp_test_meta'][201][MetadataKeys::TRACK_PRODUCT_ID] = $productId;
    }

    private function logInUser(): void
    {
        $GLOBALS['campwp_test_is_user_logged_in'] = true;
        $GLOBALS['campwp_test_current_user_id'] = 7;
    }

    private function enableWoo(): void
    {
        if (! class_exists('WooCommerce')) {
            eval('class WooCommerce {}');
        }
    }

    private function remoteAlbumFixture(): \WP_Post
    {
        $album = new \WP_Post(['ID' => 101, 'post_title' => 'Remote & Album', 'post_type' => 'campwp_album', 'post_status' => 'publish']);
        $trackOne = new \WP_Post(['ID' => 201, 'post_title' => 'First Track', 'post_type' => 'campwp_track', 'post_status' => 'publish']);
        $trackTwo = new \WP_Post(['ID' => 202, 'post_title' => 'Second Track', 'post_type' => 'campwp_track', 'post_status' => 'publish']);
        $GLOBALS['campwp_test_posts'] = [101 => $album, 201 => $trackOne, 202 => $trackTwo];

        $GLOBALS['campwp_test_meta'][101] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => '',
            MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER => 'example-item',
            MetadataKeys::ALBUM_REMOTE_COVER_URL => 'https://archive.org/download/example-item/cover.jpg?x=1&y=2',
            MetadataKeys::ALBUM_ARTIST_DISPLAY => 'Producer',
        ];

        $GLOBALS['campwp_test_meta'][201] = [
            MetadataKeys::TRACK_ALBUM_ID => 101,
            MetadataKeys::TRACK_ORDER => 1,
            MetadataKeys::TRACK_NUMBER => 1,
            MetadataKeys::TRACK_DURATION => '03:10',
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_SOURCE_PROVIDER => 'internet_archive',
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL => self::PLAYBACK_URL,
            MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL => self::DOWNLOAD_URL,
            MetadataKeys::TRACK_AUDIO_ORIGINAL_URL => self::DOWNLOAD_URL,
            MetadataKeys::TRACK_DOWNLOAD_ENABLED => '1',
            MetadataKeys::TRACK_DOWNLOAD_MODE => 'public',
        ];

        $GLOBALS['campwp_test_meta'][202] = [
            MetadataKeys::TRACK_ALBUM_ID => 101,
            MetadataKeys::TRACK_ORDER => 2,
            MetadataKeys::TRACK_NUMBER => 2,
            MetadataKeys::TRACK_DURATION => '04:20',
            MetadataKeys::TRACK_AUDIO_SOURCE_TYPE => 'internet_archive',
            MetadataKeys::TRACK_SOURCE_PROVIDER => 'internet_archive',
            MetadataKeys::TRACK_AUDIO_PLAYBACK_URL => 'https://archive.org/download/example-item/02%20-%20Two.mp3',
            MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL => 'https://archive.org/download/example-item/02%20-%20Two.flac',
            MetadataKeys::TRACK_AUDIO_ORIGINAL_URL => 'https://archive.org/download/example-item/02%20-%20Two.flac',
            MetadataKeys::TRACK_DOWNLOAD_ENABLED => '1',
            MetadataKeys::TRACK_DOWNLOAD_MODE => 'public',
        ];

        return $album;
    }
}
