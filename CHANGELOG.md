# Changelog

All notable changes to this project will be documented in this file.

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

