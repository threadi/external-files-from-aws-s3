# Changelog

## [Unreleased]

### Changed

- Optimized the option for use auto-updates for this plugin

## [1.2.1] - 24.10.2026

### Changed

- Updated translations

## [1.2.0] - 24.20.2026

### Added

- Added logo for Backblaze and Digital Ocean Spaces
- Added PHP Unit Tests

### Changed

- Renamed all "backplaze" to "backblaze"
- Mark as compatible with WordPress 7.2

### Fixed

- Fixed removal of the authorization header for HTTP requests to other sources if Backblaze S3, Cloudflare R2 or DigitalOcean Spaces is enabled
- Fixed import of subdirectories in AWS S3
- Fixed detection of deeper subdirectories in directory listing
- Fixed missing region setting for Backblaze S3 in user-specific mode
- Fixed EU storage setting for Cloudflare R2 not being used in global mode
- Fixed detection of DigitalOcean Spaces URLs for content type and mime type checks
- Fixed wrong documentation link for DigitalOcean Spaces credentials
- Fixed wrong label for DigitalOcean Spaces region NYC3
- Fixed file version for enqueued styles if WP_DEBUG is disabled

## [1.1.1] - 22.10.2026

### Changed

- Detect the real mime-type if some octet-stream is coming from the platform
- Updated dependencies

## [1.1.0] - 30.08.2026

### Changed

- Use your custom domain for Cloudflare R2 to use external hosted files there
- Allow switching hostings for Cloudflare R2 if custom domain is used for the files

### Fixed

- Fixed missing crypt for global Cloudflare R2 bucket setting
- Fixed wrong internal names for Digital Ocean Spaces and Cloudflare R2

## [1.0.5] - 16.08.2026

### Changed

- Now requires PHP 8.2 or newer
- Compatibility with WordPress 7.1
- Updates translations

## [1.0.4] - 06.05.2026

### Changed

- Updated dependencies

### Fixed

- Fixed missed running during automatic synchronization

## [1.0.3] - 26.04.2026

### Changed

- Set compatibility with WordPress 7.0

### Fixed

- Fixed error with missing library in release
- Fixed wrong version to check for updates

## [1.0.2] - 29.03.2026

### Added

- Added translations for all 5 german language packages in WordPress

### Changed

- Updated translations

### Fixed

- Fixed wrong used text domain

## [1.0.1] - 29.03.2026

### Changed

- Updated the settings library

## [1.0.0] - 14.01.2026

- Initial release
