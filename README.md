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

## Notes

- No business features, CPTs, UI, integrations, or storage schema are implemented yet.
- Scaffold is namespaced and Composer-ready for long-term growth.
