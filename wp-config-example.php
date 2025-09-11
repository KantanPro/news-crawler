<?php
/**
 * News Crawler Development Environment Configuration Example
 * 
 * This file shows how to configure wp-config.php for development environments.
 * DO NOT use this configuration in production!
 */

// WordPress Debug Mode (required for development)
define('WP_DEBUG', true);

// News Crawler Development Mode (ONLY for development)
// This flag enables development mode and bypasses license verification
// Requires WP_DEBUG to be true as well
define('NEWS_CRAWLER_DEVELOPMENT_MODE', true);

// Standard WordPress configuration continues below...
// (Add your normal wp-config.php content here)

/* That's all, stop editing! Happy publishing. */
