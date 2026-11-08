# Changelog

## [Unreleased]

### Added

- Added optional Amazon CloudFront domain for AWS S3 to deliver files via CloudFront, also from private buckets
- Added optional invalidation of the Amazon CloudFront cache for overwritten or deleted files during export
- Allow switching hostings for AWS S3 if files are delivered via Amazon CloudFront
- Added possibility to define credentials and settings of each platform via constants, e.g. in wp-config.php (requires External Files in Media Library 5.7.0)
- Constants of a platform are only used if the constants for all its required fields are defined
- Settings show hints instead of input fields for values defined via constants
- Saved external sources for other buckets than the one defined via constants are not usable as long as the constants are defined, files imported before are still delivered
- Added check for existing files in the bucket before a file is exported to prevent overwriting them (requires External Files in Media Library 5.7.0)
- Added hook "efmlawss3_upload_params" to change the parameters for the upload of files

### Changed

- Optimized the option for use auto-updates for this plugin

### Fixed

- Fixed import and export of AWS S3 files if the access key is not allowed to read object ACLs
- Fixed usage of the custom domain for Cloudflare R2 in user-specific mode
- Fixed missing content type of exported files, which resulted in "binary/octet-stream" for every file
- Fixed high memory usage during the export of large files, they are now uploaded as stream (and in parts for files with more than 16 MB)
- Fixed missing error handling if the upload of a file fails

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
