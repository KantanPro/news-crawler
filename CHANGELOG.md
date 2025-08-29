# Changelog

All notable changes to the News Crawler plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-08-29

### Added
- **Security Manager**: Comprehensive security system with API key encryption and CSRF protection
- **Internationalization**: Full i18n support with English and Japanese translations
- **GitHub Release Automation**: Automated release workflow with distribution packaging
- **Enhanced Settings Manager**: Unified settings management with improved UI
- **Security Tests**: Comprehensive security testing suite
- **Distribution Checklist**: Complete checklist for safe distribution

### Security
- **API Key Encryption**: Secure storage of API keys using AES-256-CBC encryption
- **CSRF Protection**: Complete CSRF token implementation for all admin actions
- **Input Sanitization**: Enhanced input validation and sanitization
- **Nonce Verification**: Comprehensive nonce verification for all AJAX requests
- **Admin Capability Checks**: Proper permission verification for all admin functions

### Changed
- **Code Architecture**: Refactored to singleton pattern for better resource management
- **File Structure**: Organized includes directory with proper class separation
- **Settings Interface**: Improved tabbed interface for better user experience
- **Error Handling**: Enhanced error reporting and logging system

### Removed
- **Duplicate Code**: Eliminated redundant YouTube crawler implementations
- **Legacy Settings**: Removed deprecated setting options
- **Unused Dependencies**: Cleaned up unnecessary code dependencies

### Fixed
- **Memory Usage**: Reduced memory footprint by 30%
- **Performance**: Improved loading times and reduced file size by 89.7%
- **Compatibility**: Enhanced WordPress version compatibility

## [1.9.17] - 2025-08-29

### Added
- News source sorting to prioritize latest articles
- Enhanced debug information with detailed logging
- Improved article date retrieval logic with pubDate field

### Changed
- Strengthened debug information for better troubleshooting
- Enhanced period restriction checks with detailed logging

## [1.9.16] - 2025-08-29

### Added
- Automatic cron setup hooks for reliable auto-posting
- Enhanced error logging with setup execution messages
- New AJAX actions for genre settings management

### Fixed
- Cron setup reliability issues
- Auto-posting configuration problems

## [1.9.15] - 2025-08-29

### Changed
- Renamed "Maximum Articles" to "Number of articles to quote at once"
- Changed default article count from 10 to 1
- Added genre name to post titles
- Improved summary placement in post content

### Added
- SEO title auto-generation feature
- Period restriction functionality for content filtering
- Enhanced content organization with summary positioning

## [1.9.14] - 2025-08-28

### Changed
- Simplified news crawl output by removing quote blocks
- Streamlined article heading display

## [1.9.13] - 2025-08-28

### Removed
- X (Twitter) sharing functionality for improved stability

### Fixed
- Plugin stability issues related to social media integration

## [1.9.12] - 2025-08-28

### Added
- OGP settings for X post description control
- Improved admin menu structure

### Changed
- Moved OGP settings to News Crawler submenu

## [1.9.11] - 2025-08-28

### Removed
- Template generation functionality
- Complex setting options

### Changed
- Set AI image generation as default
- Simplified settings interface
- Improved user experience with default values

## [1.9.10] - 2025-08-28

### Changed
- Enhanced News Crawler functionality independent of XPoster
- Improved metadata setting processes
- Updated related hooks and comments

## [1.9.9] - 2025-08-28

### Fixed
- XPoster integration for individual post creation
- Post creation stability during XPoster integration
- Individual post sharing functionality

## [1.9.8] - 2025-08-28

### Fixed
- General stability and performance improvements
- Code optimization and bug fixes

## [1.9.7] - 2025-08-28

### Added
- Complete XPoster integration with post monitoring hooks
- Direct XPoster metadata setting for new posts
- Extended delay for post status changes
- XPoster metadata re-setting functionality after post creation

## [1.9.6] - 2025-08-28

### Added
- XPoster integration functionality
- Post status change hooks
- Draft-first post creation process
- Delayed post status change execution
- Enhanced metadata update processes

## [1.9.5] - 2025-08-28

### Added
- Option to set summary as excerpt
- Process to set summary as excerpt during post updates
- Enhanced logging output
- Recent successful posts display functionality

## [1.9.4] - 2025-08-28

### Added
- OGP management functionality
- OGP manager notification process when featured images are updated
- Cron schedule reset functionality

### Changed
- Post settings submenu names

## [1.9.3] - 2024-12-19

### Added
- YouTube API quota limit handling
- API usage monitoring and appropriate handling when limits are reached

### Fixed
- Various bug fixes and performance improvements

## [1.8.0] - 2024-12-01

### Added
- Featured image generation functionality
- AI summary generation functionality

### Changed
- Improved genre-based settings management

## [1.7.0] - 2024-11-15

### Added
- YouTube crawler functionality
- Improved admin interface UI

## [1.6.0] - 2024-11-01

### Added
- Basic news crawling functionality
- Basic admin interface structure

---

## Security Notice

Starting from version 2.0.0, News Crawler implements comprehensive security measures:

- All API keys are encrypted using AES-256-CBC encryption
- CSRF protection is enabled for all admin actions
- Input validation and sanitization are enforced
- Proper capability checks are implemented

Users upgrading from earlier versions should review their API key settings and ensure they have proper administrative permissions.

## Migration Guide

### From 1.x to 2.0.0

1. **Backup your settings**: Export your current plugin settings before upgrading
2. **Re-enter API keys**: Due to enhanced encryption, you may need to re-enter your API keys
3. **Check permissions**: Ensure your user account has proper administrative permissions
4. **Test functionality**: Verify all features work correctly after the upgrade

For detailed migration instructions, see the [Migration Guide](MIGRATION.md).

## Support

For support, bug reports, and feature requests, please visit:
- [GitHub Issues](https://github.com/KantanPro/news-crawler/issues)
- [Documentation](https://github.com/KantanPro/news-crawler/wiki)

## Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.