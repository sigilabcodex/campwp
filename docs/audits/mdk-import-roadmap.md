# MDK Internet Archive Import Roadmap

This roadmap intentionally stages small pull requests. Status reflects the runtime work completed after the original audit: the external media schema and remote-media rendering slice are complete, and the current stage adds a safe single-release manifest importer without an IA HTTP client, WP-CLI, or bulk import tooling.

## PR 1: External Media Source Schema - Complete

Files likely affected:

- `src/Domain/Metadata/MetadataKeys.php`
- `src/Domain/Metadata/MetadataSanitizer.php`
- `src/Domain/Metadata/MetadataSchemaRegistrar.php`
- `src/Domain/Audio/TrackAudioResolver.php`
- `src/Admin/Metadata/CoreMetadataMetaBox.php`
- `docs/`

Tests required:

- New meta registration defaults and sanitizers.
- Existing attachment-based releases still resolve playback/download.
- `external_url` compatibility.
- URL validation and IA host allowlisting.

Migrations/backward compatibility:

- Keep existing keys.
- Treat `_campwp_track_audio_external_url` as legacy generic playback URL.
- Extend source type values without breaking existing `attachment` and `external_url`.

Acceptance criteria:

- Albums can store external release/provider fields.
- Tracks can store separate remote playback, original, and download URLs.
- Existing attachment releases render unchanged.

Risks:

- Expanding source type enum can break hidden assumptions in admin UI and resolver logic.

Implementation note for this PR: track provenance fields use track-prefixed keys such as `_campwp_track_source_payload_hash`, `_campwp_track_last_synced_at`, `_campwp_track_sync_status`, and `_campwp_track_sync_message` to avoid accidental collisions with album-level provenance fields.

## PR 2: Internet Archive Provider/Client

Files likely affected:

- `src/Integrations/InternetArchive/InternetArchiveClient.php`
- `src/Integrations/InternetArchive/InternetArchiveFileSelector.php`
- `src/Integrations/IntegrationService.php`
- `src/Domain/Media/` or new provider namespace

Tests required:

- Metadata API parsing from fixtures.
- FLAC original selection.
- MP3 derivative selection.
- Cover selection.
- Missing derivative states.
- Timeout/error handling.

Migrations/backward compatibility:

- None for existing content.

Acceptance criteria:

- Given an IA metadata fixture, returns item URL, file URLs, checksums, sizes, and derivative status.
- Rejects non-IA hosts and local/private IP targets.

Risks:

- IA metadata file naming may vary across all 148 releases.
- HTTP behavior can differ for HEAD/range requests.

## PR 3: Single Release Manifest Importer And Field Mapping - Current Stage

Files affected in the single-release importer slice:

- `src/Application/Import/ManifestReader.php`
- `src/Application/Import/ManifestNormalizer.php`
- `src/Application/Import/ReleaseImporter.php`
- `src/Application/Import/ImportResult.php`
- `src/Application/Import/TrackImportResult.php`
- `src/Domain/Import/ReleaseManifest.php`
- `src/Domain/Import/TrackManifest.php`
- `tests/Fixtures/single-release-manifest.json`
- `docs/single-release-manifest-importer.md`

Tests required:

- Schema compatibility.
- Idempotent album create/update/skip.
- Idempotent track create/update/skip.
- Track reordering.
- Changed titles and local-edit conflicts.
- Local path rejection.

Migrations/backward compatibility:

- No destructive migration.
- New meta only; existing posts unaffected.

Acceptance criteria:

- Importer can dry-run MDK148 example and produce a deterministic plan.
- Apply mode can create one album and ordered tracks in a test WordPress environment.
- Repeated imports are idempotent by catalog identity, provider, external release identity, and track identity.
- Missing tracks are preserved rather than deleted.

Risks:

- Source repository schema may differ from the supplied examples; direct cross-repository inspection remains required before broad MDK use.
- WP-CLI and bulk import safety controls remain separate later stages.
- Legacy MDK audit manifests without explicit track IDs require stable file identities; index/title fallback is intentionally rejected.

## PR 4: WP-CLI Single Release Dry-Run/Import - Current Stage

Files likely affected:

- `src/Cli/CampWpCliService.php`
- `src/Cli/ImportMdkCommand.php`
- `src/Core/Application.php`
- `README.md`

Tests required:

- `--dry-run` performs no writes.
- `--apply` writes expected posts/meta.
- `--all`, `--limit`, `--batch-size`, and `--resume-from`.
- Exit codes for validation errors and partial failures.

Migrations/backward compatibility:

- None.

Acceptance criteria:

- Command registers only when `WP_CLI` is available.
- Exactly one of `--dry-run` or `--apply` is required.
- Output includes source identity, result status, album/track actions, counts, warnings, and errors.

Risks:

- CLI may be run against production accidentally; command requires explicit `--apply`, keeps dry-run separate, and documents exit behavior. Bulk import remains out of scope.

## PR 5: Frontend Remote Playback/Download - Complete

Files likely affected:

- `src/Domain/Audio/TrackAudioResolver.php`
- `src/Domain/Commerce/DownloadResolver.php`
- `src/Frontend/Data/AlbumViewDataProvider.php`
- `src/Frontend/Data/TrackViewDataProvider.php`
- `src/Frontend/Rendering/AlbumPageRenderer.php`
- `src/Frontend/Rendering/TrackPageRenderer.php`
- `assets/js/campwp-album-player.js`
- `assets/css/campwp-frontend.css`

Tests required:

- Remote MP3 playback source output.
- Original FLAC download CTA output.
- Missing MP3 fallback.
- Missing FLAC fallback.
- Frontend escaping.
- Entitlement behavior for remote downloads.

Migrations/backward compatibility:

- Existing attachment download routes remain.
- Existing external URL tracks keep working.

Acceptance criteria:

- Album page can show IA MP3 player source and FLAC download link for each track.
- IA item, Bandcamp, project, and license links render safely.

Risks:

- Browser playback can fail if IA direct URLs lack required CORS/range behavior for some clients.

## PR 6: Synchronization And Verification

Files likely affected:

- `src/Domain/Sync/InternetArchiveSyncService.php`
- `src/Domain/Sync/ExternalMediaVerifier.php`
- `src/Cli/SyncInternetArchiveCommand.php`
- `src/Cli/VerifyExternalMediaCommand.php`

Tests required:

- Source payload hash comparison.
- Remote changed file detection.
- Missing/failed remote request handling.
- Retry/timeout behavior.
- Sync status updates.

Migrations/backward compatibility:

- Existing imported posts gain sync status on next sync.

Acceptance criteria:

- `sync-internet-archive` can dry-run remote changes.
- `verify-external-media` reports reachable/unreachable MP3, FLAC, and cover URLs.

Risks:

- Excessive IA requests; add rate limiting and fixture-based tests.

## PR 7: Bulk MDK Import Tooling

Files likely affected:

- `src/Cli/ImportMdkCommand.php`
- `src/Domain/Import/BatchImportRunner.php`
- `src/Domain/Import/ImportRunLogger.php`
- `docs/`

Tests required:

- Batch interruption.
- Resume.
- Partial failure.
- Import run logs.
- Planned rollback/trash list generation.

Migrations/backward compatibility:

- Add import run records as options, custom post type, or log files depending on chosen design.

Acceptance criteria:

- Full 148-release dry-run produces deterministic counts.
- Apply mode can run in batches and resume without duplicating posts.
- Audio is not sideloaded.

Risks:

- Operational mistakes during bulk import; require dry-run report review and non-production pilot first.

## Recommended Command Shape

```bash
wp campwp import-mdk /path/to/MDK148.json --dry-run
wp campwp import-mdk /path/to/publication-catalog.json --all --dry-run
wp campwp import-mdk /path/to/publication-catalog.json --all --apply --batch-size=10
wp campwp sync-internet-archive MDK148 --dry-run
wp campwp verify-external-media MDK148
```

## Rollout Checklist

- Confirm direct access to canonical MDK schema files.
- Confirm IA metadata fixture coverage across representative releases.
- Run dry-run for MDK148.
- Apply MDK148 to local/staging WordPress only.
- Verify player, FLAC downloads, IA item link, Bandcamp link, project link, and license display.
- Dry-run all 148 releases.
- Review conflicts and missing derivative states.
- Apply in small batches with logs and resume checkpoints.

