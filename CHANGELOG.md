# Changelog

All notable changes to `laravel-shaka` will be documented in this file.

## 0.9.0 - 2026-01-26

### What's Changed

- fix: improve performance and error handling in various classes by @francoism90 in https://github.com/foxws/laravel-shaka/pull/24

**Full Changelog**: https://github.com/foxws/laravel-shaka/compare/0.8.0...0.9.0

## 0.8.0 - 2026-01-23

**Full Changelog**: https://github.com/foxws/laravel-shaka/compare/0.7.0...0.8.0

## 0.7.0 - 2026-01-18

**Full Changelog**: https://github.com/foxws/laravel-shaka/compare/0.6.0...0.7.0

## 0.6.0 - 2026-01-18

**Full Changelog**: https://github.com/foxws/laravel-shaka/compare/0.5.0...0.6.0

## 0.5.0 - 2026-01-18

### What's Changed

- feat: implement cache storage for temporary files by @francoism90 in https://github.com/foxws/laravel-shaka/pull/21
- feat: add support for key rotation in AES encryption by @francoism90 in https://github.com/foxws/laravel-shaka/pull/22

**Full Changelog**: https://github.com/foxws/laravel-shaka/compare/0.4.0...0.5.0

## 0.4.0 - 2026-01-08

### What's Changed

- build(deps): bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/foxws/laravel-shaka/pull/20

**Full Changelog**: https://github.com/foxws/laravel-shaka/compare/0.3.0...0.4.0

## [0.4.0] - 2026-01-08

### Added

- Queue job classes for background media processing
- Real-time progress tracking with webhooks
- Preset system for common configurations (YouTube HLS, Netflix DASH, etc.)
- Media analysis/inspection capabilities (duration, bitrate, resolution detection)
- Enhanced cloud storage optimization (streaming uploads to S3, multipart uploads)
- Bulk processing batch operations
- Performance metrics and monitoring API
- Additional subtitle/caption language support

## [0.3.0] - 2025-12-27

### Fixed

- Add missing text stream methods to fluent API
    - `addTextStream()` method now properly documented and tested
    - Ensures parity with video and audio stream methods

### Contributors

- [@francoism90](https://github.com/francoism90)

## [0.2.0] - 2026-01-08

### Added

- **Artisan Commands**
    - `php artisan shaka:verify` - Verify Shaka Packager installation and configuration
    - `php artisan shaka:info` - Display package information, system details, and configuration
    - Commands built with Laravel Prompts for modern, beautiful console output

- **Security Policy** (`SECURITY.md`) - Vulnerability disclosure guidelines and best practices

- **Enhanced Exception Types**
    - `InvalidStreamConfigurationException` - For stream configuration errors
    - `MediaNotFoundException` - For missing media files
    - `PackagingException` - For packaging operation errors

- **Stream Validation** - `StreamValidator` class for input validation and error prevention

- **Event System** for packaging lifecycle tracking:
    - `PackagingStarted` event - Fired when packaging begins
    - `PackagingCompleted` event - Fired on successful completion with execution time
    - `PackagingFailed` event - Fired on packaging failure

- **Progress Monitor Contract** (`ProgressMonitor`) - Interface for custom progress tracking implementations

- **Media Helper Utilities** (`MediaHelper` class):
    - Suggested bitrate calculations based on resolution
    - Standard ABR (Adaptive Bitrate) ladder generation
    - File size formatting
    - Encryption key generation
    - File extension validation
    - Processing time estimation
    - Filename sanitization
    - Bandwidth parsing utilities

- **Comprehensive Documentation**
    - Queue Integration Guide (`docs/QUEUE_INTEGRATION.md`) - Background processing with Laravel queues
    - Troubleshooting Guide (`docs/TROUBLESHOOTING.md`) - Common issues and solutions
    - Updated main documentation index

- **Feature Tests Structure** - Foundation for integration testing framework

- **Implementation Fixes**
    - Fixed `force_generic_input` config check in `Media::getSafeInputPath()`
    - Properly respect config option for generic input aliases

### Changed

- **Modernized Command Output** - Artisan commands now use Laravel Prompts instead of `$this->` methods for better UX
- **Config Facade Usage** - Replaced `config()` helper with `Config` facade throughout source code:
    - `src/Http/DynamicHLSPlaylist.php`
    - `src/Http/DynamicDASHManifest.php`
    - `src/Support/ShakaPackager.php`
    - `src/MediaOpener.php`
    - `src/Filesystem/Media.php`
    - Provides better type safety and IDE support

### Fixed

- Fixed `Config::string()` to `Config::get()` in `ShakaPackager::create()` to properly handle array configuration
- Corrected force_generic_input configuration implementation to actually respect the config setting

### Documentation

- Enhanced docs/README.md with links to new guides
- Added comprehensive troubleshooting section
- Added queue integration examples and best practices

## [0.1.1] - 2025-12-20

### Fixed

- Prevent Shaka Packager "Unknown field in stream descriptor" errors when filenames include smart quotes, commas, parentheses, or start with a dash.
- Use array-based process execution to avoid shell quoting issues with special characters.

### Added

- Descriptor sanitization in `Foxws\\Streamer\\Support\\CommandBuilder`:
    - Normalize smart quotes to ASCII
    - Replace commas with hyphens (commas are field separators in Shaka descriptors)
    - Trim surrounding quotes
    - Prefix leading dashes with `./` to avoid option confusion

- Unit tests covering descriptor sanitization for leading dashes, smart quotes, commas, and output filenames.

- `force_generic_input` config option to automatically create safe generic aliases for input files.
    - When enabled, creates a temporary copy/symlink with a generic name (e.g., `input.mp4`).
    - Prevents issues with any special characters in filenames (parentheses, brackets, smart quotes, etc.).
    - Uses symlinks for local disks (fast) and copies for remote disks (compatible).
    - Set `PACKAGER_FORCE_GENERIC_INPUT=true` in `.env` to enable.

### Internal

- Switch `Foxws\\Streamer\\Support\\Packager` to pass arguments using `buildArray()`.
- Update `Foxws\\Streamer\\Support\\ShakaPackager::command()` to accept `string|array` and run via `Process::run([$binary, ...$args])`.

## [0.1.0] - 2025-12-20

### Fixed

- Prevent Shaka Packager "Unknown field in stream descriptor" errors when filenames include smart quotes, commas, parentheses, or start with a dash.
- Use array-based process execution to avoid shell quoting issues with special characters.

### Added

- Descriptor sanitization in `Foxws\\Streamer\\Support\\CommandBuilder`:
    - Normalize smart quotes to ASCII
    - Replace commas with hyphens (commas are field separators in Shaka descriptors)
    - Trim surrounding quotes
    - Prefix leading dashes with `./` to avoid option confusion

- Unit tests covering descriptor sanitization for leading dashes, smart quotes, commas, and output filenames.

- `force_generic_input` config option to automatically create safe generic aliases for input files.
    - When enabled, creates a temporary copy/symlink with a generic name (e.g., `input.mp4`).
    - Prevents issues with any special characters in filenames (parentheses, brackets, smart quotes, etc.).
    - Uses symlinks for local disks (fast) and copies for remote disks (compatible).
    - Set `PACKAGER_FORCE_GENERIC_INPUT=true` in `.env` to enable.

### Internal

- Switch `Foxws\\Shaka\\Support\\Packager` to pass arguments using `buildArray()`.
- Update `Foxws\\Shaka\\Support\\ShakaPackager::command()` to accept `string|array` and run via `Process::run([$binary, ...$args])`.

## [0.1.0] - 2025-12-20

### Added

- Initial alpha release
- Core Shaka Packager integration
- Fluent API for creating adaptive streaming content
- Support for DASH and HLS packaging
- Multi-disk support (local, S3, custom filesystems)
- MediaOpener for handling media files
- Stream configuration with video and audio streams
- Packager driver with ShakaPackager implementation
- Comprehensive test suite
- Architecture tests for code quality
- Configuration file with environment variable support
- **Dynamic URL Resolvers** - Separate classes for HLS and DASH manifest URL customization

### Features

- `MediaOpener` class with disk switching capabilities

- `Packager` class for managing packaging operations

- `Stream` builder for creating video/audio stream configurations

- `MediaCollection` for handling multiple media files

- `Disk` abstraction for filesystem operations

- Facade support for easy access

- Service provider with Laravel integration

- **DynamicHLSPlaylist** class for processing and customizing HLS playlists
    - `setKeyUrlResolver()` - Generate URLs for encryption keys
    - `setMediaUrlResolver()` - Generate URLs for media segments
    - `setPlaylistUrlResolver()` - Generate URLs for sub-playlists
    - Process and rewrite playlist files with custom URLs
    - Return as HTTP response with correct content type
    - Automatic URL caching for performance
    - **No temporary directory creation** - Only reads existing files

- **DynamicDASHManifest** class for processing and customizing DASH manifests
    - `setMediaUrlResolver()` - Generate URLs for media segments
    - `setInitUrlResolver()` - Generate URLs for initialization segments
    - Process and rewrite MPD manifest files
    - Return as HTTP response with correct content type
    - Automatic URL caching for performance
    - **No temporary directory creation** - Only reads existing files

- **Media class improvements**
    - Optional `$createTemporary` parameter to control temporary directory creation
    - Prevents unnecessary temp directories when only reading files
    - Backward compatible - defaults to `true` for packaging operations

- Support for CDN integration, signed URLs, and multi-tenant applications

### Encryption

- Browser-compatible HLS encryption using AES-128-CBC
    - Use `protection_scheme: 'cbc1'` for browser-compatible encryption
    - Use `.ts` segments (not `.mp4`) for encrypted content
    - Set `clear_lead: 0` to encrypt all segments from the start
    - Default (no protection_scheme) produces SAMPLE-AES which only works on native iOS/tvOS

- Comprehensive encryption documentation in README

### Testing

- Unit tests for all core components
- Tests for disk operations and switching
- Tests for stream configuration
- Tests for packager operations
- Tests for URL resolver functionality
- Architecture tests ensuring code standards

### Examples

- [UrlResolverExamples.php](examples/UrlResolverExamples.php) - Comprehensive URL resolver examples
- [FluentBuilderExamples.php](examples/FluentBuilderExamples.php) - Fluent API examples
- [FromDiskExamples.php](examples/FromDiskExamples.php) - Multi-disk usage examples
- [PackagerExamples.php](examples/PackagerExamples.php) - Direct packager usage examples
