# Changelog

All notable changes to this project will be documented in this file.

## [1.5.0] - 2025-01-XX

### Added
- **WP Domain Mapping Integration**: New integration with WP Domain Mapping plugin
  - Domain Mapping Overview widget showing network-wide domain statistics
  - Health status monitoring with visual indicators
  - Recently added domains list
  - Recent activity tracking
  - Quick access to domain management
  - Automatic plugin detection and conditional widget registration
  - Installation suggestion in settings page when plugin is not active
- New integration architecture in `includes/integrations/` directory
- Comprehensive documentation for integration implementation

### Changed
- Updated plugin version to 1.5.0
- Enhanced widget system to support conditional plugin integrations
- Improved settings page with integration suggestions section

### Technical
- Added `WP_MSD_Domain_Mapping_Integration` class for plugin integration
- Implemented multi-layer caching for domain data (5-15 minutes)
- Added AJAX endpoints for domain mapping data and health refresh
- Extended CSS with domain mapping widget styles
- Enhanced JavaScript with domain mapping widget handlers
- Updated Chinese translations for all new strings

## [1.3.0] - 2025-10-05

### Added
- Network Health dashboard widget: shows pending plugin/theme updates, cron status, object cache status, and HTTPS status (with AJAX endpoint and renderer).
- Performance setting: configurable "Storage scan site limit" to control the number of sites scanned when computing storage usage on large networks.

### Changed
- Storage usage computation now respects the configured scan limit and improves cache keying to avoid stale data when settings change.

### Fixed
- Avoid fatal error by removing reliance on non-core `wp_cache_flush_group` and adding safe fallback.
- Correct multisite upload directory detection by switching blog context before calling `wp_upload_dir()`.
- Remove insecure `sslverify=false` in favicon requests; add safer HEAD/GET fallback with redirection handling.
- Hardened settings saving with `manage_network` capability checks and whitelist sanitization for system widget inputs.

