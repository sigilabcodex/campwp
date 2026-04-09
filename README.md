# CAMPWP

Catalog of Albums & Music Publishing for WordPress.

## Purpose

This repository currently contains a minimal, production-safe scaffold for the CAMPWP plugin. It is intentionally light so future feature work can be added without reworking the foundation.

## Structure

- `campwp.php` - Plugin bootstrap, constants, Composer autoload include, activation/deactivation hooks, and app startup.
- `src/Core/` - Lifecycle and application bootstrap classes.
- `src/Admin/` - Admin menu, settings, metadata metaboxes, and release builder UX.
- `src/Domain/` - Content model, metadata schema, audio services, and business logic.
- `src/Infrastructure/` - Infrastructure service registration and upload-policy wiring.
- `src/Integrations/` - Third-party integration registration placeholders.
- `assets/` - Static assets for future admin/frontend usage.
- `templates/` - Render templates for future UI output.
- `languages/` - Translation files.
- `uninstall.php` - Uninstall entry point.

## Bootstrap flow

1. WordPress loads `campwp.php`.
2. Constants are defined.
3. Composer autoload is loaded if available.
4. Activation and deactivation hooks are registered.
5. On `plugins_loaded`, `CampWP\Core\Application` runs and initializes module services.

## Current v1 editorial model

- `campwp_track` posts can exist without any parent release (standalone/loose tracks).
- `campwp_album` posts represent releases and include a release type (`single`, `ep`, `album`, `compilation`, `other`).
- Album-to-track assignment is currently one-album-per-track and is stored on the track itself using:
  - `_campwp_album_id`
  - `_campwp_track_order`
- Track metadata includes artist display overrides, credits, subtitle, duration, audio source references, and optional artwork overrides.
- Album metadata includes artist defaults, credits overrides, release notes, label, and bonus asset references.

## Release Builder architecture (current)

- The release builder uses a compact track list with one focused editor panel for the active track.
- `Add Audio Files` now creates/assigns tracks immediately in the release builder list and auto-focuses the first newly added track for editing.
- Existing standalone tracks are added via a searchable picker (instead of raw multi-select) and appear instantly in the release list.
- Track reordering remains available from the list via order inputs.
- Track metadata editing is single-track-at-a-time while preserving compatibility with existing payload/storage (`campwp_release_builder[tracks][<id>]`).

## Audio metadata extraction + defaults

- When audio is added through the release builder, CAMPWP attempts conservative metadata extraction from attachment files via WordPress audio metadata APIs. The UI now surfaces source-quality guidance directly in the builder (lossless WAV/FLAC preferred, lossy accepted with warnings).
- Supported extraction targets include title, artist, album, track number, year/date, comments/composer-to-credits hints, and duration when available.
- Conservative filename fallback parsing is applied when embedded metadata is absent (for patterns like `01 - Artist - Title` and `01 Title`).
- Release-level defaults and track-level overrides follow inheritance rules:
  - Track artist/credits values override when set.
  - Empty track artist/credits inherit from release metadata.
  - Empty release artist/credits can fall back to CAMPWP settings defaults.

## Download routing

- Frontend download URLs route through `/campwp-download/...` endpoints.
- Access checks remain centralized in entitlement + resolver services before redirecting to media assets.
