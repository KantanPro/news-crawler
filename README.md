# News Crawler

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/news-crawler?style=flat-square)](https://wordpress.org/plugins/news-crawler/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/news-crawler?style=flat-square)](https://wordpress.org/plugins/news-crawler/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/rating/news-crawler?style=flat-square)](https://wordpress.org/plugins/news-crawler/)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

Automatically fetch articles from specified news sources and add them as posts to your WordPress site. Includes YouTube video crawling functionality with AI-powered content generation.

## 🚀 Features

- **📰 News Source Crawling**: Automatically fetch articles from RSS feeds and news websites
- **🎥 YouTube Integration**: Crawl YouTube channels and create video embed posts
- **🤖 AI-Powered Content**: Generate summaries and featured images using OpenAI
- **🔒 Secure**: Enterprise-grade security with encrypted API key storage
- **🌐 Multilingual**: Full internationalization support (English/Japanese)
- **⚡ Performance Optimized**: Lightweight and fast with minimal resource usage
- **📊 Analytics**: Built-in statistics and monitoring

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

- **v2.0.0**: Major security and performance improvements
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