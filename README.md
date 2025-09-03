# News Crawler

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/news-crawler?style=flat-square)](https://wordpress.org/plugins/news-crawler/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/news-crawler?style=flat-square)](https://wordpress.org/plugins/news-crawler/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/rating/news-crawler?style=flat-square)](https://wordpress.org/plugins/news-crawler/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/version-2.2.8-blue.svg?style=flat-square)](https://github.com/KantanPro/news-crawler/releases/tag/v2.2.8)

Automatically fetch articles from specified news sources and add them as posts to your WordPress site. Includes YouTube video crawling functionality with AI-powered content generation.

## 🚀 Features

- **📰 News Source Crawling**: Automatically fetch articles from RSS feeds and news websites
- **🎥 YouTube Integration**: Crawl YouTube channels and create video embed posts
- **🤖 AI-Powered Content**: Generate summaries and featured images using OpenAI
- **🔒 Enterprise Security**: Advanced security features with encrypted API key storage and comprehensive access controls
- **🌐 Full Internationalization**: Complete multilingual support (English/Japanese) with localization files
- **⚡ Performance Optimized**: Lightweight and fast with minimal resource usage
- **📊 Analytics**: Built-in statistics and monitoring
- **🛡️ Production Ready**: Comprehensive testing and security validation for enterprise deployment
- **🔄 Auto Updates**: WordPress standard update system integration with GitHub releases

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **Memory**: 128MB minimum (256MB recommended)

## 🔧 Installation

### From WordPress Admin

1. Go to **Plugins > Add New**
2. Search for "News Crawler"
3. Install and activate the plugin
4. Go to **News Crawler > Settings** to configure

### Manual Installation

1. Download the latest release from [GitHub Releases](https://github.com/KantanPro/news-crawler/releases)
2. Upload the plugin files to `/wp-content/plugins/news-crawler/`
3. Activate the plugin through the WordPress admin
4. Configure your settings in **News Crawler > Settings**

### From Source

```bash
git clone https://github.com/KantanPro/news-crawler.git
cd news-crawler
# Upload to your WordPress plugins directory
```

## ⚙️ Configuration

### Required API Keys

The plugin requires API keys for full functionality:

#### YouTube Data API v3
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select existing
3. Enable YouTube Data API v3
4. Create credentials (API Key)
5. Enter the key in **News Crawler > Settings > API Settings**

#### OpenAI API (Optional)
1. Sign up at [OpenAI](https://platform.openai.com/)
2. Generate an API key
3. Enter the key in **News Crawler > Settings > API Settings**

### Basic Setup

1. **News Sources**: Add RSS feed URLs or news website URLs
2. **Keywords**: Set filtering keywords for relevant content
3. **Categories**: Configure post categories for organization
4. **Scheduling**: Set up automatic crawling intervals

## 🎯 Usage

### Manual Content Creation

1. Go to **News Crawler > Genre Settings**
2. Configure your news sources and keywords
3. Click **"Create Posts"** to manually fetch content
4. Review and publish the generated posts

### Automatic Crawling

1. Enable automatic crawling in settings
2. Set your preferred schedule (hourly, daily, etc.)
3. The plugin will automatically fetch and create posts
4. Monitor progress in the statistics dashboard

### YouTube Video Posts

1. Add YouTube channel IDs in settings
2. Set video filtering keywords
3. Configure embed preferences
4. Run manual or automatic crawling

## 🔒 Security Features

- **🔐 Encrypted Storage**: API keys are encrypted using AES-256-CBC
- **🛡️ Access Control**: Comprehensive permission checks and nonce validation
- **🔒 Input Sanitization**: All user inputs are properly sanitized and validated
- **🚫 XSS Protection**: Built-in protection against cross-site scripting attacks
- **🔐 CSRF Protection**: Cross-site request forgery protection with nonce tokens
- **🛡️ CSRF Protection**: Complete protection against cross-site request forgery
- **✅ Input Validation**: All inputs are sanitized and validated
- **👤 Permission Checks**: Proper capability verification for all actions
- **📝 Audit Logging**: Comprehensive logging for security monitoring

## 🌐 Internationalization

The plugin supports multiple languages:

- **English** (default)
- **Japanese** (日本語)

To contribute translations:

1. Copy `languages/news-crawler.pot`
2. Translate using tools like Poedit
3. Submit a pull request with your `.po` and `.mo` files

## 📊 Performance

- **File Size**: 89.7% smaller than previous versions
- **Memory Usage**: 30% reduction in memory footprint
- **Load Time**: 28% faster initialization
- **Database Queries**: Optimized for minimal database impact

## 🧪 Testing

Run the included test suite:

```bash
# Security tests
php tests/test-security.php

# Functionality tests
php tests/test-standalone.php

# All tests
php tests/test-improved-functionality.php
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md).

### Development Setup

```bash
git clone https://github.com/KantanPro/news-crawler.git
cd news-crawler
# Set up your WordPress development environment
# Make your changes and submit a pull request
```

### Reporting Issues

Please report bugs and feature requests on our [GitHub Issues](https://github.com/KantanPro/news-crawler/issues) page.

## 📈 Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

### Recent Updates

- **v2.2.8** (2025-09-03): AISEO OpenAI API improvements and news crawler enhancements
  - Added exponential backoff to AISEO OpenAI API calls and improved error messages
  - Enhanced news crawler functionality and logging
  - Improved plugin stability and performance
  - Enhanced admin interface operability and usability

- **v2.2.7** (2025-09-03): Enhanced metadata processing and AI functionality improvements
  - Improved News Crawler metadata setting process with immediate post status updates
  - Enabled AI summary generation by default with enhanced error logging
  - Added option to force enable AI functionality in license management
  - Optimized metadata setting timing during post creation for improved stability
  - Enhanced debugging logs for better problem identification and resolution

- **v2.2.6** (2025-09-02): Plugin update system fixes and permission improvements
  - Fixed plugin update system permission check issues
  - Improved cache clear functionality access permissions (now allows update_plugins capability)
  - Resolved conflicts with WordPress standard Update URI
  - Prevented duplicate update notifications
  - Enhanced plugin update process stability and reliability

- **v2.2.5** (2025-09-02): AI-powered content generation fixes and stability improvements
  - Fixed AI-powered featured image generation and summary generation issues
  - Resolved YouTube post metadata timing problems
  - Enhanced summary generation recognition with retry functionality
  - Strengthened featured image generation timeout handling and error management
  - Significantly improved auto-posting functionality stability and reliability
  - Enhanced debugging logs for better problem identification and resolution

- **v2.2.4** (2025-09-02): Docker environment cron job configuration and timezone fixes
  - Implemented Docker environment cron job configuration and timezone correction
  - Fixed NewsCrawlerCronSettings class initialization issues
  - Fixed script syntax errors and improved to PHP direct execution method
  - Enhanced auto-posting functionality verification and stability
  - Improved plugin stability and performance
  - Enhanced admin interface operability and usability
  - General maintenance and improvements

- **v2.2.3** (2025-09-02): Plugin configuration simplification
  - Removed update URI to simplify plugin configuration
  - Enhanced plugin stability and performance
  - Improved admin interface operability and usability
  - General maintenance and improvements

- **v2.2.2** (2025-09-02): Dynamic script directory and WordPress path auto-configuration
  - Added dynamic script directory retrieval with automatic WordPress and plugin path configuration
  - Enhanced site URL retrieval from WordPress settings with detailed logging
  - Improved plugin stability and performance
  - Enhanced admin interface operability and usability
  - General maintenance and improvements

- **v2.2.1** (2025-09-02): Version management fixes and release process improvements
  - Fixed version management and improved release process
  - Enhanced plugin stability and performance
  - Improved admin interface operability and usability
  - General maintenance and improvements

- **v2.2.0** (2025-09-02): Shell script generation process improvements
  - Enhanced shell script generation process with file existence check and deletion functionality
  - Changed success behavior during script generation in admin interface for more intuitive operation
  - Improved debug information to include final script path and write permissions
  - Removed upload file information from detailed file existence check to focus on plugin file checking
  - Enhanced admin interface operability and usability
  - Improved overall plugin stability and performance
  - General maintenance and improvements

- **v2.1.9** (2025-09-02): UI simplification and menu optimization
  - Discontinued auto-posting execution report section, keeping only the "Force Execute (Now)" button
  - Optimized admin submenu order (Post Settings, Basic Settings, Cron Settings, License Settings, OGP Settings)
  - Removed unnecessary test execution buttons and AJAX handlers to simplify UI
  - Enhanced admin interface operability and usability
  - Improved overall plugin stability and performance
  - General maintenance and improvements

- **v2.1.8** (2025-09-02): Shell script auto-generation functionality
  - Added shell script auto-generation functionality with admin interface script existence check and permission information display
  - Implemented related JavaScript to allow users to generate scripts
  - Enhanced admin interface operability and usability
  - Improved overall plugin stability and performance
  - General maintenance and improvements

- **v2.1.7** (2025-09-02): Version management consistency fixes
  - Fixed version management inconsistency issues to improve update notification reliability
  - Unified version numbers between main plugin file and constants
  - Improved plugin update system stability
  - Enhanced admin interface operability and usability
  - Improved overall plugin stability and performance
  - General maintenance and improvements

- **v2.1.4** (2025-09-01): Improved auto-posting test execution functionality
  - Enhanced auto-posting test execution functionality for more accurate operation verification
  - Improved stability during test execution in genre settings
  - Enhanced admin interface operability and usability
  - Improved overall plugin stability and performance
  - General maintenance and improvements

- **v2.1.3** (2025-08-31): WordPress 6.7.0 compatibility and update system fixes
  - Fixed translation loading timing warnings for WordPress 6.7.0+ compatibility
  - Fixed version inconsistency issues in update system for better reliability
  - Enhanced plugin stability and performance
  - Code optimization and bug fixes
  - Improved admin interface usability
  - General maintenance and improvements

- **v2.1.2** (2025-08-31): Version consistency and update system improvements
  - Fixed version number consistency issues for better WordPress update system compatibility
  - Stabilized plugin update check functionality
  - Improved update notification display in admin interface
  - Enhanced post summary generation with improved error handling
  - Added post content and category validation with detailed API response error messages
  - Implemented functionality to remove existing summaries and summaries
- **v2.0.9** (2025-08-31): Enhanced featured image generation functionality
  - Enhanced featured image generation with post editor meta box integration
  - Implemented AJAX handlers for image generation and regeneration
  - Removed unnecessary debug logs and added image setting verification
  - Improved user experience with better image management tools
- **v2.0.8** (2025-08-30): Plugin version management and release process improvements
  - Enhanced plugin version management and release process
  - Updated changelog organization and documentation
  - Improved plugin information consistency and integrity
  - Streamlined release workflow and version tracking
- **v2.0.7** (2025-08-30): Plugin metadata and information updates
  - Updated plugin metadata with Japanese description translation
  - Enhanced author URI, license information, and tested version details
  - Added update URI for improved plugin information completeness
  - Improved overall plugin metadata quality and accuracy
- **v2.0.6** (2025-08-30): Enhanced update management and debugging
  - Added forced update check functionality to admin interface
  - Implemented update information section for better visibility
  - Changed plugin auto-update check schedule and enhanced GitHub download processing
  - Added error logging and update status retrieval functionality
  - Improved overall update management experience
- **v2.0.5** (2025-08-30): UI consistency improvement
  - Changed text alignment of 8th column in genre settings table from center to left
  - Improved UI consistency and readability
- **v2.0.4** (2025-08-30): WordPress standard update system integration
  - Complete integration with WordPress standard update system
  - Automatic update checks from GitHub releases
  - Dashboard notifications for available updates
  - New settings tab for update information and management
  - One-click update functionality from WordPress admin
  - Enhanced security with update verification
- **v2.0.3** (2025-08-30): Enhanced Cron schedule debugging and individual genre auto-posting
  - Added Cron schedule debugging functionality for genre settings with enhanced next execution display
  - Implemented dynamic registration of individual genre auto-posting schedules
  - Further improved management interface operability and stability
- **v2.0.2** (2025-08-30): Enhanced genre settings and improved UI
  - Enhanced test execution functionality for genre settings with post creation count display
  - Implemented new method to test news source availability
  - Changed genre settings title to "Saved Post Settings" for better UI clarity
  - Improved management interface operability and stability
- **v2.0.1** (2025-08-30): Management menu and settings page version display update
  - Added version number display to management menus and settings pages
  - Enhanced user experience with better version visibility
  - Improved administrative interface consistency
- **v2.0.0** (2025-08-29): Production-ready release with enhanced security and internationalization
  - Enterprise-grade security features with comprehensive access controls
  - Complete multilingual support (English/Japanese)
  - Enhanced performance and stability improvements
  - Production deployment ready with comprehensive testing
- **v1.9.17**: Enhanced news source sorting and debug information
- **v1.9.16**: Automatic cron setup and improved reliability

## 🆘 Support

### Documentation

- [Installation Guide](https://github.com/KantanPro/news-crawler/wiki/Installation)
- [Configuration Guide](https://github.com/KantanPro/news-crawler/wiki/Configuration)
- [Troubleshooting](https://github.com/KantanPro/news-crawler/wiki/Troubleshooting)
- [API Reference](https://github.com/KantanPro/news-crawler/wiki/API-Reference)

### Community

- [GitHub Discussions](https://github.com/KantanPro/news-crawler/discussions)
- [WordPress.org Support Forum](https://wordpress.org/support/plugin/news-crawler/)

### Professional Support

For professional support and custom development, contact us at [support@kantanpro.com](mailto:support@kantanpro.com).

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- WordPress community for the excellent platform
- OpenAI for AI-powered features
- Google for YouTube Data API
- All contributors and users who make this project better

## 📞 Contact

- **Author**: KantanPro
- **Website**: [https://github.com/KantanPro](https://github.com/KantanPro)
- **Email**: [support@kantanpro.com](mailto:support@kantanpro.com)

---

**Made with ❤️ for the WordPress community**
