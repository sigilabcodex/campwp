# CAMPWP

Catalog of Albums & Music Publishing for WordPress.

## Purpose

This repository currently contains a minimal, production-safe scaffold for the CAMPWP plugin. It is intentionally light so future feature work can be added without reworking the foundation.

## Structure

- `campwp.php` - Plugin bootstrap, constants, Composer autoload include, activation/deactivation hooks, and app startup.
- `src/Core/` - Lifecycle and application bootstrap classes.
- `src/Admin/` - Admin-area service registration placeholders.
- `src/Domain/` - Domain-layer service registration placeholders.
- `src/Infrastructure/` - Infrastructure service registration placeholders.
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
5. On `plugins_loaded`, `CampWP\\Core\\Application` runs and initializes module placeholders.

## Current v1 editorial model

- `campwp_track` posts can exist without any parent release (standalone/loose tracks).
- `campwp_album` posts represent releases and now include a release type meta field (`single`, `ep`, `album`, `compilation`, `other`).
- Album-to-track assignment is currently one-album-per-track and is stored on the track itself using:
  - `_campwp_album_id`
  - `_campwp_track_order`
- Track metadata includes an artist display override field for compilation and guest-credit use-cases.
- Album metadata now includes a minimal `_campwp_album_bonus_items` placeholder (`[]`) for future album bonus-download workflows.
