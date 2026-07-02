<?php

declare(strict_types=1);

namespace CampWP\Tests\Unit;

use CampWP\Application\Import\ManifestReader;
use CampWP\Application\Import\ReleaseImporter;
use CampWP\Domain\Metadata\MetadataKeys;
use PHPUnit\Framework\TestCase;

final class SingleReleaseManifestImporterTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['campwp_test_posts'] = [];
        $GLOBALS['campwp_test_meta'] = [];
        $GLOBALS['campwp_test_post_types'] = [];
        $GLOBALS['campwp_inserted_posts'] = [];
        $GLOBALS['campwp_updated_posts'] = [];
        $GLOBALS['campwp_deleted_meta'] = [];
        $GLOBALS['campwp_test_next_post_id'] = 1000;
        $GLOBALS['campwp_test_thumbnail_ids'] = [];
        $GLOBALS['campwp_test_meta_write_failures'] = [];
        $GLOBALS['campwp_test_update_post_meta_return_false'] = false;
        $GLOBALS['campwp_test_wp_insert_post_fail_titles'] = [];
        $GLOBALS['campwp_test_wp_update_post_fail_titles'] = [];
    }

    public function testValidMdkStyleManifestIsAcceptedAndCreatesAlbumAndTracks(): void
    {
        $result = $this->importer()->importLocalFile($this->repoPath('docs/audits/mdk-import-example.json'));

        self::assertTrue($result->isSuccess(), implode(', ', $result->errors));
        self::assertSame('internet_archive:MDK148', $result->sourceReleaseIdentity);
        self::assertSame('created', $result->albumAction);
        self::assertSame(9, $result->createdCount);
        self::assertSame(0, $result->updatedCount);
        self::assertSame(0, $result->unchangedCount);
        self::assertCount(8, $result->trackResults);

        $album = get_post($result->albumPostId);
        self::assertInstanceOf(\WP_Post::class, $album);
        self::assertSame('campwp_album', $album->post_type);
        self::assertSame('draft', $album->post_status);
        self::assertSame('The trade wind desert soundscape', $album->post_title);
        self::assertSame('MDK148', get_post_meta($result->albumPostId, MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID, true));
        self::assertSame('internet_archive', get_post_meta($result->albumPostId, MetadataKeys::ALBUM_SOURCE_PROVIDER, true));
        self::assertSame('internet_archive', get_post_meta($result->albumPostId, MetadataKeys::ALBUM_CATALOG_IDENTITY, true));
        self::assertSame('mdk148-the-trade-wind-desert-soundscape', get_post_meta($result->albumPostId, MetadataKeys::ALBUM_INTERNET_ARCHIVE_IDENTIFIER, true));
        self::assertSame('Produced By K', get_post_meta($result->albumPostId, MetadataKeys::ALBUM_CREDITS_OVERRIDE, true));

        $firstTrackId = $result->trackResults[0]->postId;
        self::assertSame($result->albumPostId, (int) get_post_meta($firstTrackId, MetadataKeys::TRACK_ALBUM_ID, true));
        self::assertSame(1, (int) get_post_meta($firstTrackId, MetadataKeys::TRACK_ORDER, true));
        self::assertSame($this->legacyTrackId('MDK148', '01 - Fennec Fox.flac'), get_post_meta($firstTrackId, MetadataKeys::TRACK_EXTERNAL_TRACK_ID, true));
        self::assertSame('internet_archive', get_post_meta($firstTrackId, MetadataKeys::TRACK_AUDIO_SOURCE_TYPE, true));
        self::assertSame('https://archive.org/download/mdk148-the-trade-wind-desert-soundscape/01%20-%20Fennec%20Fox.mp3', get_post_meta($firstTrackId, MetadataKeys::TRACK_AUDIO_PLAYBACK_URL, true));
        self::assertSame('https://archive.org/download/mdk148-the-trade-wind-desert-soundscape/01%20-%20Fennec%20Fox.flac', get_post_meta($firstTrackId, MetadataKeys::TRACK_AUDIO_ORIGINAL_URL, true));
        self::assertSame('https://archive.org/download/mdk148-the-trade-wind-desert-soundscape/01%20-%20Fennec%20Fox.flac', get_post_meta($firstTrackId, MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL, true));
        self::assertSame('', get_post_meta($result->albumPostId, '_thumbnail_id', true));
        self::assertSame(0, $this->countPostsOfType('attachment'));
    }

    public function testMalformedJsonIsRejected(): void
    {
        $result = $this->importer()->importJsonString('{broken');

        self::assertFalse($result->isSuccess());
        self::assertStringContainsString('malformed', $result->errors[0]);
    }

    public function testUnsupportedSchemaVersionIsRejectedWithoutWrites(): void
    {
        $manifest = $this->normalizedManifest();
        $manifest['schema_version'] = 'v0';

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertSame([], $GLOBALS['campwp_test_posts']);
        self::assertSame([], $GLOBALS['campwp_test_meta']);
    }

    public function testMissingReleaseIdentityIsRejected(): void
    {
        $manifest = $this->normalizedManifest();
        unset($manifest['album']['external_release_id']);

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('release identity is missing or invalid.', $result->errors);
    }

    public function testDuplicateTrackIdsAreRejected(): void
    {
        $manifest = $this->normalizedManifest();
        $manifest['tracks'][1]['external_track_id'] = $manifest['tracks'][0]['external_track_id'];

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('Track external IDs must be unique.', $result->errors);
    }

    public function testDuplicateTrackIndexesAreRejected(): void
    {
        $manifest = $this->normalizedManifest();
        $manifest['tracks'][1]['index'] = 1;

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('Track indexes must be unique.', $result->errors);
    }

    public function testInvalidProviderIsRejected(): void
    {
        $manifest = $this->normalizedManifest();
        $manifest['album']['source_provider'] = 'evil';

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('provider is required when remote media is present.', $result->errors);
    }

    public function testMissingProviderIsRejectedWhenOnlyTrackRemoteMediaIsPresent(): void
    {
        $manifest = $this->normalizedManifest();
        unset($manifest['album']['source_provider'], $manifest['album']['cover'], $manifest['album']['bandcamp_url'], $manifest['album']['project_url']);
        foreach ($manifest['tracks'] as &$track) {
            unset($track['source_provider']);
        }
        unset($track);

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('provider is required when remote media is present.', $result->errors);
        self::assertSame([], $GLOBALS['campwp_test_posts']);
        self::assertSame([], $GLOBALS['campwp_test_meta']);
    }

    public function testUnsafeRemoteUrlIsRejectedWithoutWrites(): void
    {
        $manifest = $this->normalizedManifest();
        $manifest['tracks'][0]['playback_url'] = 'https://127.0.0.1/audio.mp3';

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('tracks[0].playback URL is unsafe or unsupported.', $result->errors);
        self::assertSame([], $GLOBALS['campwp_test_posts']);
        self::assertSame([], $GLOBALS['campwp_test_meta']);
    }

    public function testInvalidChecksumIsRejected(): void
    {
        $manifest = $this->normalizedManifest();
        $manifest['tracks'][0]['original_checksum'] = 'sha256:not-valid';

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('tracks[0].original_checksum is invalid.', $result->errors);
    }

    public function testInvalidInternetArchiveIdentifierIsRejected(): void
    {
        $manifest = $this->mdkManifest();
        $manifest['archive_identifier'] = 'https://archive.org/details/item';

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('Internet Archive identifier is invalid.', $result->errors);
    }

    public function testSameProviderAndReleaseIdDifferentCatalogsCreateDistinctAlbums(): void
    {
        $manifestA = $this->normalizedManifest();
        $manifestB = $this->normalizedManifest();
        $manifestB['album']['catalog_key'] = 'second_catalog';
        $manifestB['album']['title'] = 'Second Catalog Album';

        $first = $this->importer()->importArray($manifestA);
        $second = $this->importer()->importArray($manifestB);

        self::assertTrue($first->isSuccess());
        self::assertTrue($second->isSuccess());
        self::assertNotSame($first->albumPostId, $second->albumPostId);
        self::assertSame('test_catalog', get_post_meta($first->albumPostId, MetadataKeys::ALBUM_CATALOG_IDENTITY, true));
        self::assertSame('second_catalog', get_post_meta($second->albumPostId, MetadataKeys::ALBUM_CATALOG_IDENTITY, true));
    }

    public function testRepeatedImportWithinSameCatalogUpdatesSameAlbumWhenTitleChanges(): void
    {
        $manifest = $this->normalizedManifest();
        $first = $this->importer()->importArray($manifest);
        $manifest['album']['title'] = 'Same Catalog New Title';
        $second = $this->importer()->importArray($manifest);

        self::assertSame($first->albumPostId, $second->albumPostId);
        self::assertSame('updated', $second->albumAction);
        self::assertSame('Same Catalog New Title', get_post($first->albumPostId)->post_title);
    }

    public function testManuallyAuthoredAlbumWithSameTitleIsUntouched(): void
    {
        $GLOBALS['campwp_test_posts'][25] = new \WP_Post(['ID' => 25, 'post_type' => 'campwp_album', 'post_title' => 'Test Remote Album', 'post_status' => 'draft']);
        $GLOBALS['campwp_test_post_types'][25] = 'campwp_album';

        $result = $this->importer()->importArray($this->normalizedManifest());

        self::assertTrue($result->isSuccess());
        self::assertNotSame(25, $result->albumPostId);
        self::assertSame('Test Remote Album', get_post(25)->post_title);
        self::assertSame('', get_post_meta(25, MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID, true));
    }

    public function testExistingImportedAlbumWithoutCatalogIdentityIsNotMatchedAmbiguously(): void
    {
        $GLOBALS['campwp_test_posts'][26] = new \WP_Post(['ID' => 26, 'post_type' => 'campwp_album', 'post_title' => 'Legacy Import', 'post_status' => 'draft']);
        $GLOBALS['campwp_test_post_types'][26] = 'campwp_album';
        $GLOBALS['campwp_test_meta'][26] = [
            MetadataKeys::ALBUM_SOURCE_PROVIDER => 'direct',
            MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID => 'TEST001',
        ];

        $result = $this->importer()->importArray($this->normalizedManifest());

        self::assertTrue($result->isSuccess());
        self::assertNotSame(26, $result->albumPostId);
        self::assertContains('Existing imported album without catalog identity was not matched automatically.', $result->warnings);
    }

    public function testIdenticalSecondImportCreatesNothingAndReportsUnchanged(): void
    {
        $manifest = $this->normalizedManifest();
        $first = $this->importer()->importArray($manifest);
        $second = $this->importer()->importArray($manifest);

        self::assertTrue($first->isSuccess());
        self::assertTrue($second->isSuccess());
        self::assertSame($first->albumPostId, $second->albumPostId);
        self::assertSame('unchanged', $second->albumAction);
        self::assertSame(0, $second->createdCount);
        self::assertSame(0, $second->updatedCount);
        self::assertSame(3, $second->unchangedCount);
        self::assertSame(3, count($GLOBALS['campwp_test_posts']));
    }

    public function testChangedAlbumAndTrackTitlesUpdateSamePostsWithoutDuplication(): void
    {
        $manifest = $this->normalizedManifest();
        $first = $this->importer()->importArray($manifest);

        $manifest['album']['title'] = 'Updated Remote Album';
        $manifest['tracks'][0]['title'] = 'Updated One';
        $second = $this->importer()->importArray($manifest);

        self::assertSame($first->albumPostId, $second->albumPostId);
        self::assertSame('updated', $second->albumAction);
        self::assertSame($first->trackResults[0]->postId, $second->trackResults[0]->postId);
        self::assertSame('Updated Remote Album', get_post($first->albumPostId)->post_title);
        self::assertSame('Updated One', get_post($first->trackResults[0]->postId)->post_title);
        self::assertSame(3, count($GLOBALS['campwp_test_posts']));
    }

    public function testReorderedTracksUpdateExistingTrackOrder(): void
    {
        $manifest = $this->normalizedManifest();
        $first = $this->importer()->importArray($manifest);

        $manifest['tracks'][0]['index'] = 2;
        $manifest['tracks'][1]['index'] = 1;
        $second = $this->importer()->importArray($manifest);

        self::assertSame($first->trackResults[0]->postId, $second->trackResults[0]->postId);
        self::assertSame($first->trackResults[1]->postId, $second->trackResults[1]->postId);
        self::assertSame(2, (int) get_post_meta($first->trackResults[0]->postId, MetadataKeys::TRACK_ORDER, true));
        self::assertSame(1, (int) get_post_meta($first->trackResults[1]->postId, MetadataKeys::TRACK_ORDER, true));
        self::assertSame(3, count($GLOBALS['campwp_test_posts']));
    }

    public function testUnrelatedContentRemainsUntouched(): void
    {
        $GLOBALS['campwp_test_posts'][50] = new \WP_Post(['ID' => 50, 'post_type' => 'campwp_album', 'post_title' => 'Unrelated', 'post_status' => 'draft']);
        $GLOBALS['campwp_test_post_types'][50] = 'campwp_album';
        $GLOBALS['campwp_test_meta'][50] = [MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID => 'OTHER', MetadataKeys::ALBUM_SOURCE_PROVIDER => 'direct'];

        $this->importer()->importArray($this->normalizedManifest());

        self::assertSame('Unrelated', get_post(50)->post_title);
        self::assertSame('OTHER', get_post_meta(50, MetadataKeys::ALBUM_EXTERNAL_RELEASE_ID, true));
    }

    public function testDryRunReportsPlannedChangesAndWritesNothing(): void
    {
        $result = $this->importer()->importArray($this->normalizedManifest(), true);

        self::assertTrue($result->isSuccess());
        self::assertTrue($result->dryRun);
        self::assertSame('created', $result->albumAction);
        self::assertSame(3, $result->createdCount);
        self::assertSame([], $GLOBALS['campwp_test_posts']);
        self::assertSame([], $GLOBALS['campwp_test_meta']);
    }

    public function testDryRunDoesNotAlterExistingImportedRecords(): void
    {
        $manifest = $this->normalizedManifest();
        $first = $this->importer()->importArray($manifest);
        $manifest['album']['title'] = 'Dry Run Title';

        $dryRun = $this->importer()->importArray($manifest, true);

        self::assertTrue($dryRun->isSuccess());
        self::assertSame('updated', $dryRun->albumAction);
        self::assertSame('Test Remote Album', get_post($first->albumPostId)->post_title);
    }

    public function testAbsentOptionalFieldsDoNotEraseExistingMetadataOrFeaturedImage(): void
    {
        $manifest = $this->normalizedManifest();
        $first = $this->importer()->importArray($manifest);
        update_post_meta($first->albumPostId, MetadataKeys::ALBUM_BANDCAMP_URL, 'https://artist.bandcamp.com/album/test-remote-album');
        $GLOBALS['campwp_test_thumbnail_ids'][$first->albumPostId] = 77;

        unset($manifest['album']['bandcamp_url'], $manifest['album']['cover']);
        $second = $this->importer()->importArray($manifest);

        self::assertTrue($second->isSuccess());
        self::assertSame('https://artist.bandcamp.com/album/test-remote-album', get_post_meta($first->albumPostId, MetadataKeys::ALBUM_BANDCAMP_URL, true));
        self::assertSame(77, $GLOBALS['campwp_test_thumbnail_ids'][$first->albumPostId]);
    }

    public function testMissingManifestTrackDoesNotDeleteExistingTrack(): void
    {
        $manifest = $this->normalizedManifest();
        $first = $this->importer()->importArray($manifest);
        array_pop($manifest['tracks']);

        $second = $this->importer()->importArray($manifest);

        self::assertTrue($second->isSuccess());
        self::assertSame(3, count($GLOBALS['campwp_test_posts']));
        self::assertInstanceOf(\WP_Post::class, get_post($first->trackResults[1]->postId));
    }

    public function testLegacyMdkFallbackTrackIdentitySurvivesReorderInsertTitleAndNumberChanges(): void
    {
        $manifest = $this->mdkManifest();
        $first = $this->importer()->importArray($manifest);
        $fennecId = $first->trackResults[0]->postId;
        $meerkatsId = $first->trackResults[1]->postId;

        $newTrack = $manifest['tracks'][0];
        $newTrack['title'] = 'Inserted Stable File';
        $newTrack['original_filename'] = '00 - Inserted Stable File.flac';
        $newTrack['derived_filename'] = '00 - Inserted Stable File.mp3';
        $newTrack['original_flac_url'] = 'https://archive.org/download/mdk148-the-trade-wind-desert-soundscape/00%20-%20Inserted%20Stable%20File.flac';
        $newTrack['derived_mp3_url'] = 'https://archive.org/download/mdk148-the-trade-wind-desert-soundscape/00%20-%20Inserted%20Stable%20File.mp3';
        $newTrack['download_url'] = $newTrack['original_flac_url'];
        array_splice($manifest['tracks'], 0, 0, [$newTrack]);
        $manifest['tracks'][1]['position'] = 9;
        $manifest['tracks'][1]['track_number'] = 9;
        $manifest['tracks'][1]['title'] = 'Fennec Fox Updated';
        $manifest['tracks'][2]['position'] = 10;

        $second = $this->importer()->importArray($manifest);

        self::assertTrue($second->isSuccess(), implode(', ', $second->errors));
        self::assertSame($fennecId, $this->findTrackByExternalId($this->legacyTrackId('MDK148', '01 - Fennec Fox.flac')));
        self::assertSame($meerkatsId, $this->findTrackByExternalId($this->legacyTrackId('MDK148', '02 - Meerkats.flac')));
        self::assertSame('Fennec Fox Updated', get_post($fennecId)->post_title);
        self::assertSame(9, (int) get_post_meta($fennecId, MetadataKeys::TRACK_ORDER, true));
    }

    public function testDuplicateDerivedLegacyTrackIdentitiesAreRejected(): void
    {
        $manifest = $this->mdkManifest();
        $manifest['tracks'][1]['original_filename'] = $manifest['tracks'][0]['original_filename'];

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('Track external IDs must be unique.', $result->errors);
    }

    public function testMissingStableLegacyTrackIdentityIsRejected(): void
    {
        $manifest = $this->mdkManifest();
        unset($manifest['tracks'][0]['external_track_id'], $manifest['tracks'][0]['original_filename'], $manifest['tracks'][0]['original_flac_url'], $manifest['tracks'][0]['download_url'], $manifest['tracks'][0]['derived_mp3_url']);

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('tracks[0].external_track_id is required because no stable file identity is available.', $result->errors);
    }

    public function testNormalizedManifestWithoutExplicitTrackIdIsRejected(): void
    {
        $manifest = $this->normalizedManifest();
        unset($manifest['tracks'][0]['external_track_id']);

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertContains('tracks[0].external_track_id is required.', $result->errors);
    }

    public function testLateMalformedTrackRejectsEntireManifestWithoutWrites(): void
    {
        $manifest = $this->normalizedManifest();
        $manifest['tracks'][] = ['external_track_id' => 'TEST001-03', 'index' => 3, 'title' => ['bad']];

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertSame([], $GLOBALS['campwp_test_posts']);
        self::assertSame([], $GLOBALS['campwp_test_meta']);
    }

    public function testTrackInsertFailureReportsPartialState(): void
    {
        $manifest = $this->normalizedManifest();
        $manifest['tracks'][] = $manifest['tracks'][1];
        $manifest['tracks'][2]['external_track_id'] = 'TEST001-03';
        $manifest['tracks'][2]['index'] = 3;
        $manifest['tracks'][2]['title'] = 'Fail Me';
        $GLOBALS['campwp_test_wp_insert_post_fail_titles'] = ['Fail Me'];

        $result = $this->importer()->importArray($manifest);

        self::assertFalse($result->isSuccess());
        self::assertSame('partial', $result->status);
        self::assertGreaterThan(0, $result->albumPostId);
        self::assertCount(3, $result->trackResults);
        self::assertSame('failed', $result->trackResults[2]->action);
        self::assertSame(3, $result->createdCount);
        self::assertNotSame([], $GLOBALS['campwp_test_posts']);
    }

    public function testAlbumIdentityMetaFailureStopsBeforeTracksAndReportsPartial(): void
    {
        $GLOBALS['campwp_test_meta_write_failures'] = [MetadataKeys::ALBUM_CATALOG_IDENTITY];

        $result = $this->importer()->importArray($this->normalizedManifest());

        self::assertFalse($result->isSuccess());
        self::assertSame('partial', $result->status);
        self::assertGreaterThan(0, $result->albumPostId);
        self::assertSame(1, $result->createdCount);
        self::assertSame([], $result->trackResults);
        self::assertStringContainsString(MetadataKeys::ALBUM_CATALOG_IDENTITY, $result->errors[0]);
    }

    public function testAlbumOptionalMetaFailureReportsPartial(): void
    {
        $GLOBALS['campwp_test_meta_write_failures'] = [MetadataKeys::ALBUM_REMOTE_COVER_URL];

        $result = $this->importer()->importArray($this->normalizedManifest());

        self::assertFalse($result->isSuccess());
        self::assertSame('partial', $result->status);
        self::assertStringContainsString(MetadataKeys::ALBUM_REMOTE_COVER_URL, $result->errors[0]);
    }

    public function testTrackIdentityMetaFailureReportsPartial(): void
    {
        $GLOBALS['campwp_test_meta_write_failures'] = [MetadataKeys::TRACK_EXTERNAL_TRACK_ID];

        $result = $this->importer()->importArray($this->normalizedManifest());

        self::assertFalse($result->isSuccess());
        self::assertSame('partial', $result->status);
        self::assertSame('failed', $result->trackResults[0]->action);
        self::assertStringContainsString(MetadataKeys::TRACK_EXTERNAL_TRACK_ID, $result->errors[0]);
    }

    public function testTrackPlaybackDownloadMetaFailureReportsPartial(): void
    {
        $GLOBALS['campwp_test_meta_write_failures'] = [MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL];

        $result = $this->importer()->importArray($this->normalizedManifest());

        self::assertFalse($result->isSuccess());
        self::assertSame('partial', $result->status);
        self::assertStringContainsString(MetadataKeys::TRACK_AUDIO_DOWNLOAD_URL, $result->errors[0]);
    }

    public function testTrackPlaybackMetaFailureReportsPartial(): void
    {
        $GLOBALS['campwp_test_meta_write_failures'] = [MetadataKeys::TRACK_AUDIO_PLAYBACK_URL];

        $result = $this->importer()->importArray($this->normalizedManifest());

        self::assertFalse($result->isSuccess());
        self::assertSame('partial', $result->status);
        self::assertStringContainsString(MetadataKeys::TRACK_AUDIO_PLAYBACK_URL, $result->errors[0]);
    }

    public function testUnchangedMetaReturningFalseIsNotFailure(): void
    {
        $manifest = $this->normalizedManifest();
        $this->importer()->importArray($manifest);
        $GLOBALS['campwp_test_update_post_meta_return_false'] = true;

        $result = $this->importer()->importArray($manifest);

        self::assertTrue($result->isSuccess(), implode(', ', $result->errors));
        self::assertSame('unchanged', $result->status);
    }

    public function testPostStatusPolicy(): void
    {
        foreach (['draft', 'pending', 'private'] as $status) {
            $this->setUp();
            $manifest = $this->normalizedManifest();
            $manifest['album']['post_status'] = $status;
            $result = $this->importer()->importArray($manifest);
            self::assertTrue($result->isSuccess(), $status);
            self::assertSame($status, get_post($result->albumPostId)->post_status);
        }
    }

    public function testAbsentPostStatusDefaultsToDraftAndUnsupportedStatusRejectsWithoutWrites(): void
    {
        $manifest = $this->normalizedManifest();
        unset($manifest['album']['post_status']);
        $draft = $this->importer()->importArray($manifest);
        self::assertSame('draft', get_post($draft->albumPostId)->post_status);

        $this->setUp();
        $manifest = $this->normalizedManifest();
        $manifest['album']['post_status'] = 'publish';
        $publish = $this->importer()->importArray($manifest);
        self::assertFalse($publish->isSuccess());
        self::assertContains('post_status is unsupported.', $publish->errors);
        self::assertSame([], $GLOBALS['campwp_test_posts']);

        $manifest['album']['post_status'] = 'weird';
        $weird = $this->importer()->importArray($manifest);
        self::assertFalse($weird->isSuccess());
        self::assertContains('post_status is unsupported.', $weird->errors);
    }

    public function testLocalFileReaderRejectsUrlWrappersAndDirectories(): void
    {
        $reader = new ManifestReader();

        $this->expectException(\InvalidArgumentException::class);
        $reader->readLocalFile('https://example.com/manifest.json');
    }

    /** @return array<string, mixed> */
    private function normalizedManifest(): array
    {
        return $this->readJson($this->repoPath('tests/Fixtures/single-release-manifest.json'));
    }

    /** @return array<string, mixed> */
    private function mdkManifest(): array
    {
        return $this->readJson($this->repoPath('docs/audits/mdk-import-example.json'));
    }

    /** @return array<string, mixed> */
    private function readJson(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        return $decoded;
    }

    private function repoPath(string $relative): string
    {
        return dirname(__DIR__, 2) . '/' . $relative;
    }

    private function importer(): ReleaseImporter
    {
        return new ReleaseImporter();
    }

    private function countPostsOfType(string $postType): int
    {
        return count(array_filter(
            $GLOBALS['campwp_test_posts'],
            static fn ($post): bool => $post instanceof \WP_Post && $post->post_type === $postType
        ));
    }

    private function legacyTrackId(string $releaseId, string $stableIdentity): string
    {
        return sprintf('%s:file:%s', $releaseId, substr(hash('sha256', strtolower($stableIdentity)), 0, 24));
    }

    private function findTrackByExternalId(string $externalTrackId): int
    {
        foreach ($GLOBALS['campwp_test_meta'] as $postId => $meta) {
            if (($meta[MetadataKeys::TRACK_EXTERNAL_TRACK_ID] ?? '') === $externalTrackId) {
                return (int) $postId;
            }
        }

        return 0;
    }
}
