# WP-CLI Single Release Import

CAMPWP provides a minimal WP-CLI invocation layer for the single-release manifest importer. The command only reads a local JSON manifest and delegates validation, normalization, dry-run planning, and apply behavior to `CampWP\Application\Import\ReleaseImporter`.

It does not fetch Internet Archive metadata, discover remote files, run bulk imports, verify remote catalogs, or publish automatically. Apply mode may sideload a cover only when the local manifest explicitly provides `cover.strategy: "sideload_featured_image"` and a resolved cover URL.

## Command

```bash
wp campwp import-release /absolute/path/to/manifest.json --dry-run
wp campwp import-release /absolute/path/to/manifest.json --apply
```

Exactly one execution mode is required:

- `--dry-run`
- `--apply`

Optional output format:

```bash
wp campwp import-release /absolute/path/to/manifest.json --dry-run --format=json
```

Supported formats are `table` and `json`. `table` is the default.

## Path Restrictions

The manifest argument must be a local readable `.json` file. CAMPWP rejects URL paths and stream wrappers such as:

- `https://example.com/manifest.json`
- `http://example.com/manifest.json`
- `file:///tmp/manifest.json`
- `php://filter/...`

Directories, unreadable files, missing files, and non-JSON files are rejected by the existing manifest reader. Readable local JSON symlinks are accepted because PHP `is_file()` follows symlinks.

## Dry Run

Dry-run mode validates the manifest, normalizes it, locates existing records, and prints planned operations, including cover action. It performs no post or metadata writes, HTTP requests, media downloads, attachments, or featured-image changes.

Example output:

```text
Source: test_catalog:TEST001
Status: dry_run
Album: created
Counts: 3 created, 0 updated, 0 unchanged
Cover: skipped
Tracks:
  TEST001-01  created
  TEST001-02  created
Warnings: 0
Errors: 0
```

A valid dry-run exits with status `0`. Invalid manifests print errors and exit nonzero.

## Apply

Apply mode requires explicit `--apply`. It runs the importer in apply mode and follows the importer policy:

- New albums and tracks default to `draft`.
- Allowed explicit statuses are `draft`, `pending`, and `private`.
- `publish` and arbitrary statuses are rejected during validation.
- Missing tracks are preserved.
- Unrelated posts are not touched.
- Attachments are created only for the explicit single-release cover sideload strategy. Cover sideload failures are non-fatal warnings and report `Cover: skipped`.

Apply exits with status `0` only for `success` or `unchanged`. `partial` and `failed` results exit nonzero.

## Result Fields

Table output prints:

- source release identity
- result status
- album action and post ID when available
- created, updated, and unchanged counts
- cover action and attachment ID when available
- track actions and post IDs when available
- warning count and messages
- error count and messages

JSON output is based directly on `ImportResult::toArray()` and includes a stable machine-readable object with the same importer result data, including a `cover` object with `action`, `attachment_id`, `external_id`, `url`, and `message`.

## Partial Results

If a later operation fails after earlier writes succeeded, the importer returns `status: partial`. The command prints the partial result and exits nonzero. Partial output preserves:

- album ID and action
- successful prior track results
- completed counts
- warnings
- errors

## Safety Guarantees

The command does not bypass importer validation. It never accepts remote manifest URLs, never discovers provider metadata from WordPress, never runs remote catalog verification, never deletes missing tracks, and never performs bulk imports. Dry-run never downloads files or writes attachments. Apply downloads media only for an explicit manifest cover sideload request.

## Current Limitations

- No Internet Archive HTTP client.
- No remote metadata discovery.
- No bulk importer.
- No cron or scheduled sync.
- No admin upload UI.
- No media download or sideloading beyond the explicit single-release cover strategy.
