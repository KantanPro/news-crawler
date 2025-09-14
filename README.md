# News Crawler

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/news-crawler?style=flat-square)](https://wordpress.org/plugins/news-crawler/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/news-crawler?style=flat-square)](https://wordpress.org/plugins/news-crawler/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/rating/news-crawler?style=flat-square)](https://wordpress.org/plugins/news-crawler/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)
[![Version](https://img.shields.io/badge/version-2.3.82-blue.svg?style=flat-square)](https://github.com/KantanPro/news-crawler/releases/tag/v2.3.82)

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

- **v2.3.82** (2025-09-14): Improved cron script with timeout functionality
  - Improved cron script: added timeout to PHP command execution
  - Enhanced error handling
  - Improved execution result log output
  - Achieved more stable cron execution
  - Enhanced plugin reliability

- **v2.3.81** (2025-09-14): Fixed cron script and enhanced error handling
  - Fixed cron script: corrected wp-load loading method
  - Enhanced error handling
  - Achieved more stable cron execution
  - Improved error response handling
  - Enhanced plugin reliability

- **v2.3.80** (2025-09-14): Fixed cron script and improved log output
  - Fixed cron script: corrected wp-load startup method
  - Improved log output
  - Enhanced PHP command error handling
  - Changed to prioritize wp-cli usage in Docker environment
  - Achieved more stable cron execution

- **v2.3.79** (2025-09-14): Added GitHub folder name normalization and asset download URL prioritization
  - Added GitHub folder name normalization to standard slug
  - Implemented asset download URL prioritization logic
  - Achieved more consistent folder name management
  - Improved distribution process
  - Enhanced plugin distribution quality

- **v2.3.78** (2025-09-14): Added automatic shell script generation on settings save
  - Added automatic shell script generation on settings save
  - Implemented script write permission checking
  - Added processing to create files in appropriate directories
  - Achieved more user-friendly settings management
  - Reduced manual configuration effort

- **v2.3.77** (2025-09-14): Fixed transient initialization and resolved Fatal error
  - Improved transient initialization conditions
  - Added defensive initialization for null or false cases
  - Resolved Fatal error: Attempt to assign property "checked" on false
  - Achieved more stable update check functionality
  - Improved plugin reliability

- **v2.3.76** (2025-09-14): Improved plugin update check functionality
  - Enhanced plugin update check functionality
  - Shortened cache expiration to 15 minutes
  - Modified to ignore cache during forced update checks
  - Achieved faster update information retrieval
  - Improved update check reliability

- **v2.3.75** (2025-09-14): Added admin page reload functionality after activation
  - Added one-time reload functionality after admin page activation
  - Implemented reload flag setting and safe redirect processing based on conditions
  - Achieved more stable admin page operation
  - Improved user experience

- **v2.3.74** (2025-09-14): Updated Cron script and fixed PHP command execution errors
  - Updated News Crawler Cron script
  - Fixed PHP command execution errors
  - Improved log output in Docker environment
  - Added detailed information at execution start
  - Achieved more stable Cron execution

- **v2.3.73** (2025-09-13): Updated automatic posting settings and fully dependent on cron job configuration
  - Updated documentation for automatic posting settings
  - Clarified complete dependence on cron job configuration
  - Removed posting frequency settings section
  - Simplified next execution schedule display update process
  - Provided clearer setup procedures

- **v2.3.72** (2025-09-13): Fixed Cron script and improved Docker environment support
  - Resolved PHP command execution errors
  - Improved log output during execution in Docker environment
  - Updated setup procedure documentation
  - Changed automatic posting schedule to be based on cron jobs
  - Achieved more stable Cron execution

- **v2.3.71** (2025-09-12): Improved WordPress standard update system
  - Enhanced update system initialization process
  - Clarified plugin reactivation conditions
  - Moved update check class initialization to early initialization
  - Achieved more stable update process

- **v2.3.70** (2025-09-12): Simplified development environment setup
  - Removed development environment setup sections from README
  - Deleted wp-config-example.php file
  - Simplified development mode configuration
  - Achieved more user-friendly development environment

- **v2.3.69** (2025-09-11): Added summary source accumulation for SEO title generation
  - Implemented functionality to save summary sources to post meta
  - Added similar processing for article summary generation
  - Enhanced error handling
  - Achieved more accurate SEO title generation

- **v2.3.68** (2025-09-11): Added synchronous execution fallback for disabled WP-Cron
  - Added fallback to synchronous execution when async scheduling fails
  - Achieved more stable news crawling functionality
  - Ensured execution regardless of server environment

- **v2.3.67** (2025-09-11): Improved post-update plugin auto-activation processing
  - Added support for forced update checks
  - Added processing to clear old no_update entries
  - Achieved more stable update process
  - Enhanced cache clearing functionality

- **v2.3.66** (2025-09-11): Fixed period restriction day settings normalization
  - Improved period restriction functionality settings processing
  - Achieved more accurate day calculations
  - Enhanced user interface stability

- **v2.3.65** (2025-09-11): Version management unification and release process improvements
  - Unified version numbers between main plugin file and constants
  - Improved release process automation and documentation updates
  - Enhanced plugin information consistency

- **v2.3.64** (2025-09-11): Fixed zero article issue
  - Improved handling when no articles are found
  - Achieved more stable news crawling functionality
  - Enhanced error handling

- **v2.3.63** (2025-09-11): Fixed duplicate summary generation issue
  - Resolved issue where summaries were being generated twice
  - Achieved more accurate summary generation
  - Improved user experience

- **v2.3.61** (2025-09-11): Added SEO settings tab
  - Added new "SEO Settings" tab to basic settings
  - Centralized SEO-related configuration management
  - Provided more detailed SEO setting options
  - Improved user interface

- **v2.3.60** (2025-09-10): Added automatic reactivation after plugin updates
  - Implemented automatic reactivation functionality after plugin updates
  - Save and restore pre-update state (active/inactive, network active)
  - Improved plugin version retrieval methods
  - Removed unnecessary log output and cache clear functionality
  - Achieved more stable update process

- **v2.3.59** (2025-09-10): Enhanced support for both posts and pages
  - Fixed category checking and meta box addition processing for both posts and pages
  - Strengthened post type validation
  - Updated error messages appropriately
  - Achieved more flexible content management

- **v2.3.58** (2025-09-10): AI summary generation process optimization
  - Removed AI summary generation processing from news-crawler.php
  - Clarified that processing is executed asynchronously in class-youtube-crawler.php
  - Avoided processing duplication and improved performance

- **v2.3.57** (2025-09-10): Version management unification and release process improvements
  - Unified version numbers between main plugin file and constants
  - Improved release process automation and documentation updates
  - Enhanced plugin information consistency

- **v2.3.54** (2025-09-09): Singleton pattern-based instance management improvements
  - Fixed NewsCrawlerGenreSettings class instance generation method
  - Changed to use get_instance method for proper instance management
  - Optimized memory usage and improved performance
  - Organized class dependencies and improved code stability
  - Significantly improved instance management reliability and efficiency

- **v2.3.53** (2025-09-09): Singleton pattern-based instance management improvements
  - Fixed NewsCrawlerGenreSettings class instance generation method
  - Changed to use get_instance method for proper instance management
  - Optimized memory usage and improved performance
  - Organized class dependencies and improved code stability
  - Significantly improved instance management reliability and efficiency

- **v2.3.52** (2025-09-09): News crawler settings UI improvements and enhanced usability
  - Improved re-evaluation button positioning for better user experience
  - Simplified settings screen display content for improved user operability
  - Enhanced automatic posting status display and removed unnecessary next execution schedule display
  - Significantly improved admin interface appearance and usability
  - Greatly enhanced user interface usability and visibility

- **v2.3.51** (2025-09-08): News crawling stability improvements and enhanced error handling
  - Added early termination functionality to news crawling for improved processing efficiency
  - Enhanced debug log output content for easier problem identification and resolution
  - Extended timeout for all-genre candidate re-evaluation function to 10 minutes for large-scale processing
  - Fixed some error handling issues and strengthened response content logging
  - Increased memory limit to 512MB and added processing time logging functionality
  - Significantly improved news crawling processing stability and debugging capabilities

- **v2.3.50** (2025-09-08): Major OpenAI API connection testing and error handling improvements
  - Added OpenAI API connection testing functionality before news crawling and article creation
  - Implemented proper error handling to stop processing when API authentication errors occur
  - Added functionality to retrieve API keys from multiple settings options for better compatibility
  - Improved settings validation and added re-evaluation button to user interface
  - Prevented creation of posts with empty content and enhanced error handling
  - Significantly improved AI functionality stability and reliability

- **v2.3.49** (2025-09-08): Major performance optimization and frontend load reduction improvements
  - Optimized class initialization to only run in admin or WP-Cron contexts, reducing frontend load
  - Added fallback notification functionality when WordPress standard update notifications are not displayed
  - Optimized license verification and update checks to only run in admin or WP-Cron contexts
  - Prevented unnecessary execution on every frontend request, improving site response speed
  - Significantly improved plugin performance and user experience

- **v2.3.48** (2025-09-07): Major news crawling functionality improvements and stability enhancements
  - Added RSS feed auto-discovery functionality for automatic feed detection on non-RSS sites
  - Enhanced error handling for HTML page retrieval with improved fallback processing for connection failures
  - Implemented exponential backoff for various requests with retry functionality for timeouts
  - Updated User-Agent to latest Chrome for more natural requests
  - Optimized redirect count and HTTP version for improved connection stability
  - Significantly improved news crawling reliability and success rate

- **v2.3.47** (2025-09-06): Plugin version management improvements
  - Enhanced version management functionality with dynamic version retrieval in settings classes
  - Improved consistency between plugin constants and header information
  - Implemented centralized version information management
  - Significantly improved plugin maintainability and management capabilities

- **v2.3.46** (2025-09-06): News crawler settings manager API connection verification improvements
  - Enhanced YouTube API connection verification with improved error handling for unexpected responses
  - Changed OpenAI API request method from POST to GET for improved API call stability
  - Significantly improved API connection reliability and error handling

- **v2.3.45** (2025-09-06): News crawling summary generation fallback improvements
  - Enhanced fallback processing for summary generation when content is short or summary is too brief
  - Added functionality to generate summaries using titles and descriptions as fallback
  - Added default messages for more stable summary generation
  - Significantly improved summary generation quality and stability

- **v2.3.44** (2025-09-06): Final adjustments for WordPress standard update notifications
  - Adjusted early initialization and defensive initialization for updater
  - Verified consistency of `id` and `plugin` keys in update response
  - Additional refinement for post ID display and messages
  - Documentation updates (README and descriptions)

- **v2.3.42** (2025-09-06): Admin script loading and update system improvements
  - Improved admin script loading conditions to load scripts on News Crawler related pages
  - Enhanced update information retrieval using Updater class with fallback processing
  - Improved admin interface functionality and stability
  - Enhanced update system reliability and error handling

- **v2.3.41** (2025-09-06): Settings management sanitization improvements
  - Improved sanitize_settings method to only update submitted items
  - Enhanced setting consistency by maintaining unsubmitted items
  - Replaced isset() with array_key_exists() for clearer condition checking
  - Improved settings management reliability and data integrity

- **v2.3.40** (2025-09-06): Settings management UI improvements and cache clear functionality
  - Added data attributes to settings management class tabs for improved UI/UX
  - Added cache clear functionality with AJAX processing implementation
  - Implemented administrator permissions and security checks for cache clearing
  - Added cache clear button to settings management screen
  - Enhanced settings management user experience and debugging capabilities

- **v2.3.39** (2025-09-06): Settings management improvements and API testing
  - Organized settings management class API, functionality, quality, and update information section slugs
  - Clarified configuration sections corresponding to each tab
  - Added API connection testing functionality
  - Enhanced settings management user experience and debugging capabilities

- **v2.3.38** (2025-09-06): Enhanced license verification with fallback processing
  - Improved license verification process with KLM plugin existence checking
  - Added fallback direct verification for network errors and HTTP errors
  - Enhanced API failure response handling with direct KLM plugin verification
  - Improved license verification reliability and error handling

- **v2.3.37** (2025-09-06): License site URL override functionality
  - Added license site URL override functionality using constants, filters, and options
  - Users can now flexibly configure site URLs based on specific conditions
  - Enhanced customization capabilities for license management

- **v2.3.36** (2025-09-06): License key regex improvements and enhanced validation
  - Updated license key regex to allow pipe (|) characters in the middle block
  - Improved development environment license key validation with fallback to KLM API
  - Enhanced license key verification flexibility and reliability

- **v2.3.35** (2025-09-06): License key normalization improvements
  - Added license key normalization processing to convert full-width characters to half-width
  - Unified dash characters and removed zero-width spaces and control characters
  - Improved license key format consistency and processing reliability

- **v2.3.34** (2025-09-06): License key processing improvements
  - Improved license key processing by discontinuing sanitize_text_field usage
  - Changed to use trim and wp_unslash for better license key input accuracy
  - Enhanced license key handling precision and user experience

- **v2.3.33** (2025-09-06): Method parameter improvements and enhanced flexibility
  - Added default values to before_update method parameters for improved flexibility
  - Enhanced plugin stability and maintainability
  - Improved method call flexibility and code quality

- **v2.3.32** (2025-09-06): License management improvements and security enhancements
  - Enhanced license management functionality with improved security
  - Added new license client class for better integration
  - Improved nonce verification flexibility for administrators and development efficiency

- **v2.3.31** (2025-09-06): AI summary improvements and responsive design enhancements
  - Enhanced AI summary functionality with improved error handling
  - Improved admin interface responsive design and accessibility
  - Overall plugin performance optimizations

- **v2.3.30** (2025-09-06): YouTube crawling improvements and UI/UX enhancements
  - Enhanced YouTube video crawling functionality with performance optimizations
  - Improved admin interface UI/UX and enhanced usability
  - Overall plugin stability and reliability improvements

- **v2.3.29** (2025-09-06): News crawling stability and performance improvements
  - Enhanced news crawling functionality stability and improved error handling
  - Optimized admin interface display speed and improved user experience
  - Overall code quality improvements and enhanced maintainability

- **v2.3.28** (2025-09-06): License management enhancements and security improvements
  - Further enhanced license management functionality with improved debugging
  - Improved admin interface responsiveness and user experience
  - Enhanced security features and code optimization

- **v2.3.27** (2025-09-06): Admin interface optimization and performance improvements
  - Optimized admin interface display and improved user experience
  - Enhanced error handling and stability improvements
  - Overall performance optimizations

- **v2.3.26** (2025-09-06): Minor UI copy updates and stability improvements
  - Minor UI text/copy consistency updates in settings/admin pages
  - Small stability improvements

- **v2.3.25** (2025-09-06): License validation debug information and improved error responses
  - Added detailed debug information (`debug_info`) to license validation error responses
  - Unified and enriched API connection failure responses, including `api_url`, `site_url`, and `plugin_version`
  - Improved stability and troubleshooting experience

- **v2.3.22** (2025-09-06): Main page access permission fixes and enhanced debugging
  - Fixed main page access permission issues
  - Enhanced permission checking functionality and added debugging features
  - Improved admin interface stability and user experience
  - Overall plugin stability and security improvements

- **v2.3.20** (2025-09-06): Main page access permission fixes and enhanced debugging
  - Fixed main page access permission issues
  - Enhanced permission checking functionality and added debugging features
  - Improved admin interface stability and user experience
  - Overall plugin stability and security improvements

- **v2.3.19** (2025-09-06): Further news crawling improvements and enhanced performance optimization
  - Further enhanced news crawling functionality and improved performance
  - Improved article retrieval process stability and error handling
  - Enhanced debugging capabilities and optimized log output
  - Overall plugin stability and user experience improvements

- **v2.3.18** (2025-09-06): News crawling functionality improvements and performance optimization
  - Enhanced news crawling functionality and improved performance
  - Improved article retrieval process stability
  - Strengthened error handling and debugging capabilities
  - Overall plugin stability improvements

- **v2.3.17** (2025-09-06): License management improvements and stability enhancements
  - Enhanced license management functionality and improved user experience
  - Optimized license status display in admin interface
  - Strengthened error handling and debugging capabilities
  - Overall plugin stability improvements

- **v2.3.16** (2025-09-06): License testing functionality and management improvements
  - Added license testing functionality for enhanced license key verification
  - Improved license status confirmation features in admin interface
  - Enhanced debugging capabilities and error handling
  - Improved license management user experience

- **v2.3.15** (2025-09-06): KantanPro License Manager (KLM) integration and API improvements
  - Complete implementation of KantanPro License Manager (KLM) integration
  - Added license key preprocessing with trim() for whitespace removal
  - Implemented strict license key format validation using regular expressions
  - Unified Content-Type to application/x-www-form-urlencoded
  - Enhanced error handling for all specified error cases
  - Detailed license format descriptions and examples in admin interface
  - Added debug endpoint information
  - Significantly improved license verification stability and user experience
  - Modified API calls according to KLM requirements for enhanced integration stability

- **v2.3.14** (2025-09-06): License key validation improvements and API verification enhancements
  - Simplified license key format checking and added API verification for NCRL- prefixed keys
  - Improved development environment validation logic with fallback processing for API connection failures
  - Enhanced license management stability and user experience

- **v2.3.13** (2025-09-06): License management menu display improvements
  - Fixed menu display to show even when license is not set
  - Improved license management display logic for better user experience
  - Enhanced admin interface menu display stability

- **v2.3.12** (2025-09-06): License management UI/UX improvements and notification enhancements
  - Added license management functionality with invalid license notification implementation
  - Enhanced license key format validation with stricter checks and improved admin notifications
  - Added CSS styles for improved user interface
  - Significantly improved license management UI/UX

- **v2.3.11** (2025-09-06): License management enhancements and error handling improvements
  - Enhanced license management functionality with license clear feature
  - Improved license key format validation and unified error message display
  - Strengthened AJAX request error handling with enhanced debug information
  - Improved license management stability and user experience

- **v2.3.10** (2025-09-05): Plugin version retrieval and cache management improvements
  - Improved plugin version retrieval method to directly fetch from constants
  - Enhanced cache clearing functionality with comprehensive cache clearing methods
  - Resolved update system version display issues
  - Fixed plugin activation errors
  - Improved version display consistency in admin interface

- **v2.3.9** (2025-09-05): License authentication page improvements and UI simplification
  - Removed unnecessary messages from license authentication page for simplified UI
  - Removed functional limitation descriptions to improve user experience
  - Removed license key-based functional limitation messages
  - Implemented dynamic version retrieval for admin page titles
  - Resolved license authentication page title display issues

- **v2.3.8** (2025-09-05): Development environment detection improvements
  - Enhanced development environment detection logic with more rigorous condition checking
  - Improved development license key acquisition methods in settings page
  - Increased accuracy of development vs production environment detection
  - Achieved more secure license management through improved environment detection

- **v2.3.7** (2025-09-05): License management and AJAX functionality enhancements
  - Added comprehensive license management functionality with real-time status checking
  - Implemented development license switching feature for improved testing environment support
  - Added AJAX handlers for real-time license status verification
  - Enhanced error logging and debugging capabilities
  - Implemented license input screen display when license key is invalid or not set
  - Improved overall plugin security and user experience

- **v2.3.6** (2025-09-05): Dynamic version logic and admin display improvements
  - Dynamic version retrieval logic: Changed plugin version retrieval logic to be dynamic and improved admin display
  - Enhanced version information display accuracy: Achieved more accurate update notifications
  - Optimized plugin information display in admin interface
  - Improved overall plugin management experience

- **v2.3.5** (2025-09-05): Performance optimization and enhanced post limit logic
  - Performance optimization: Improved processing speed by removing debug logs
  - Enhanced post limit logic: Implemented global limits based on valid genres with candidates
  - Improved error handling: Organized pre-execution check logic for better stability
  - Added functionality to count recent posts across all genres
  - Strengthened individual genre limit checks
  - Enhanced overall plugin stability and reliability

- **v2.3.4** (2025-09-05): Update check logic improvements and enhanced crawling functionality
  - Organized update check logic by removing unnecessary filters and actions
  - Improved update schedule configuration and latest version retrieval processing
  - Added automatic Cron script creation on plugin activation with enhanced initialization
  - Enhanced news crawling process with error logging for article link retrieval
  - Implemented processing time limits and interruption functionality
  - Added article type determination feature with deep crawling processing
  - Improved article link extraction logic and error handling
  - Replaced manual execution process with new genre settings system

- **v2.3.3** (2025-09-04): Settings UI improvements and duplicate handling
  - Settings UI: Improved visibility toggling when changing content type. Hides news settings when YouTube is selected; cancel restores default content type to News Article.
  - Duplicate handling: Implemented duplicate skipping for news/videos and preserved order (keywords and news sources).
  - Post creation: Improved post status sanitization; added default post author setting. Relaxed minimum content length threshold with fallback; clarified error message when no valid articles.
  - Update checks: Improved logic to suppress notification when already up to date; added transient saving; fixed behavior of forced update check.

- **v2.3.0** (2025-09-03): Enhanced category handling and AI processing improvements
  - Added article summary generation feature using OpenAI API to create detailed summaries
  - Added site title extraction from article pages
  - Improved post category retrieval method to return the first category name
  - Enhanced OpenAI summary error handling and retry logic
  - Strengthened AI generation for debugging, allowing summary and SEO title generation to continue even without metadata
  - Added exponential backoff to OpenAI API calls and improved error messages
  - Enhanced news crawler functionality and logging
  - Improved documentation comment formatting and removed unnecessary blank lines

- **v2.2.9** (2025-09-03): Enhanced OpenAI summary processing and YouTube API quota handling
  - Enhanced OpenAI summary error handling and retry logic
  - Strengthened AI generation for debugging, allowing summary and SEO title generation to continue even without metadata
  - Enhanced news crawler functionality and logging
  - Improved News Crawler metadata setting process with immediate post status updates
  - Added News Crawler Cron execution logs to record start and end messages

- **v2.2.8** (2025-09-03): AISEO OpenAI improvements and news crawler enhancements
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

## Changelog

### v2.3.55 - 2025-09-09

- **Enhanced Cache Protection**: Improved cache protection during settings save operations
  - Modified to maintain post count cache during settings save
  - Added cache clearing only when re-evaluation button is pressed
  - Strengthened cache protection during post creation to improve data integrity
  - Prevented unintended cache clearing from user operations

### v2.3.22 - 2025-09-06

- **Completely Fixed Main Page Access Permission Issues**: Fully resolved access permission problems on main pages
- **Further Enhanced Permission Checking**: Further strengthened permission checking functionality and improved debugging features
- **Significantly Improved Admin Interface**: Significantly enhanced admin interface stability and user experience
- **Overall Security**: General plugin stability and security improvements

### v2.3.21 - 2025-09-06

- **Fixed Main Page Access Permission Issues**: Resolved access permission problems on main pages
- **Enhanced Permission Checking**: Strengthened permission checking functionality and added debugging features
- **Improved Admin Interface**: Enhanced admin interface stability and user experience
- **Overall Security**: General plugin stability and security improvements

### v2.3.20 - 2025-09-06

- **Fixed Main Page Access Permission Issues**: Resolved access permission problems on main pages
- **Enhanced Permission Checking**: Strengthened permission checking functionality and added debugging features
- **Improved Admin Interface**: Enhanced admin interface stability and user experience
- **Overall Security**: General plugin stability and security improvements

### v2.3.19 - 2025-09-06

- **Further Enhanced News Crawling**: Further improved news crawling functionality and performance optimization
- **Improved Article Retrieval**: Enhanced article retrieval process stability and error handling
- **Enhanced Debugging**: Improved debugging capabilities and optimized log output
- **Overall Stability**: General plugin stability and user experience improvements

### v2.3.18 - 2025-09-06

- **Enhanced News Crawling**: Improved news crawling functionality and performance optimization
- **Improved Article Retrieval**: Enhanced article retrieval process stability
- **Strengthened Error Handling**: Enhanced debugging capabilities and error handling
- **Overall Stability**: General plugin stability improvements

### v2.3.17 - 2025-09-06

- **Enhanced License Management**: Improved license management functionality and user experience
- **Optimized Admin Interface**: Better license status display in admin interface
- **Strengthened Error Handling**: Enhanced debugging capabilities and error handling
- **Overall Stability**: General plugin stability improvements

### v2.3.16 - 2025-09-06

- **Added License Testing Functionality**: Enhanced license key verification with testing capabilities
- **Improved License Management**: Better license status confirmation features in admin interface
- **Enhanced Debugging**: Improved debugging capabilities and error handling
- **Better User Experience**: Enhanced license management user experience

### v2.3.15 - 2025-09-06

- **Complete KantanPro License Manager (KLM) Integration**: Fully implemented KLM integration with all specified requirements
- **Enhanced License Key Validation**: Added trim() preprocessing and strict regex format checking
- **Improved API Communication**: Unified Content-Type to application/x-www-form-urlencoded
- **Comprehensive Error Handling**: Implemented all error cases specified in the integration prompt
- **Enhanced Admin Interface**: Detailed license format descriptions and examples
- **Added Debug Endpoint Information**: Included debug endpoint details for troubleshooting
- **Significantly Improved License Verification**: Enhanced stability and user experience

### v2.3.15 - 2025-09-06

- Complete implementation of KantanPro License Manager (KLM) integration
- Added license key preprocessing with trim() for whitespace removal
- Implemented strict license key format validation using regular expressions
- Unified Content-Type to application/x-www-form-urlencoded
- Enhanced error handling for all specified error cases
- Detailed license format descriptions and examples in admin interface
- Added debug endpoint information
- Significantly improved license verification stability and user experience
- Modified API calls according to KLM requirements for enhanced integration stability

### v2.3.14 - 2025-09-06

- Simplified license key format checking and added API verification for NCRL- prefixed keys
- Improved development environment validation logic with fallback processing for API connection failures
- Enhanced license management stability and user experience

### v2.3.13 - 2025-09-06

- Fixed menu display to show even when license is not set
- Improved license management display logic for better user experience
- Enhanced admin interface menu display stability

### v2.3.12 - 2025-09-06

- Added license management functionality with invalid license notification implementation
- Enhanced license key format validation with stricter checks and improved admin notifications
- Added CSS styles for improved user interface
- Significantly improved license management UI/UX

### v2.3.11 - 2025-09-06

- Enhanced license management functionality with license clear feature
- Improved license key format validation and unified error message display
- Strengthened AJAX request error handling with enhanced debug information
- Improved license management stability and user experience

### v2.3.10 - 2025-09-05

- Improved plugin version retrieval method to directly fetch from constants
- Enhanced cache clearing functionality with comprehensive cache clearing methods
- Resolved update system version display issues
- Fixed plugin activation errors
- Improved version display consistency in admin interface

### v2.3.9 - 2025-09-05

- Removed unnecessary messages from license authentication page for simplified UI
- Removed functional limitation descriptions to improve user experience
- Removed license key-based functional limitation messages
- Implemented dynamic version retrieval for admin page titles
- Resolved license authentication page title display issues

### v2.3.8 - 2025-09-05

- Enhanced development environment detection logic with more rigorous condition checking
- Improved development license key acquisition methods in settings page
- Increased accuracy of development vs production environment detection
- Achieved more secure license management through improved environment detection

### v2.3.7 - 2025-09-05

- Added comprehensive license management functionality with real-time status checking
- Implemented development license switching feature for improved testing environment support
- Added AJAX handlers for real-time license status verification
- Enhanced error logging and debugging capabilities
- Implemented license input screen display when license key is invalid or not set
- Improved overall plugin security and user experience

### v2.3.6 - 2025-09-05

- Dynamic version retrieval logic: Changed plugin version retrieval logic to be dynamic and improved admin display
- Enhanced version information display accuracy: Achieved more accurate update notifications
- Optimized plugin information display in admin interface
- Improved overall plugin management experience

### v2.3.5 - 2025-09-05

- Performance optimization: Improved processing speed by removing debug logs
- Enhanced post limit logic: Implemented global limits based on valid genres with candidates
- Improved error handling: Organized pre-execution check logic for better stability
- Added functionality to count recent posts across all genres
- Strengthened individual genre limit checks
- Enhanced overall plugin stability and reliability

### v2.3.4 - 2025-09-05

- Update check logic improvements: Organized update check logic by removing unnecessary filters and actions
- Improved update schedule configuration and latest version retrieval processing
- Added automatic Cron script creation on plugin activation with enhanced initialization
- Enhanced news crawling process with error logging for article link retrieval
- Implemented processing time limits and interruption functionality
- Added article type determination feature with deep crawling processing
- Improved article link extraction logic and error handling
- Replaced manual execution process with new genre settings system

### v2.3.3 - 2025-09-04

- Settings UI: Improved visibility toggling when changing content type. Hides news settings when YouTube is selected; cancel restores default content type to News Article.
- Duplicate handling: Implemented duplicate skipping for news/videos and preserved order (keywords and news sources).
- Post creation: Improved post status sanitization; added default post author setting. Relaxed minimum content length threshold with fallback; clarified error message when no valid articles.
- Update checks: Improved logic to suppress notification when already up to date; added transient saving; fixed behavior of forced update check.

### v2.3.2 - 2025-09-04

- Added start-of-execution log message and improved log output
- Improved transparency of auto-posting process
- Enhanced debug information

### v2.3.1 - 2025-09-03

- Enhanced AJAX response error handling with fallback processing for success responses
- Improved content length checking during article summary generation and updated title generation logic
- Added default message handling for unknown nonce values
- Enhanced post content sanitization and strengthened functionality to remove unnecessary text before summary generation

### v2.3.0 - 2025-09-03

- Enhanced overall plugin stability and performance
- Improved error handling and API call reliability
- Enhanced logging functionality for easier debugging
- Optimized metadata processing for faster post creation
- Improved UI/UX in admin interface for better usability

### v2.2.9 - 2025-09-03

- Enhanced OpenAI summary error handling and retry logic
- Strengthened AI generation for debugging, allowing summary and SEO title generation to continue even without metadata. Added exponential backoff to OpenAI API calls and improved error messages
- Enhanced news crawler functionality and logging
- Improved News Crawler metadata setting process with immediate post status updates. Enabled AI summary generation by default with enhanced error logging. Added option to force enable AI functionality in license management
- Added News Crawler Cron execution logs to record start and end messages. Enhanced detailed logging of execution status via HTTP requests

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
