<?php
/**
 * プラグインバージョン取得ヘルパー
 *
 * @package News_Crawler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('news_crawler_get_version')) {
    /**
     * プラグインバージョンを取得（news-crawler.php ヘッダー優先）
     *
     * @return string
     */
    function news_crawler_get_version() {
        static $version = null;

        if ($version !== null) {
            return $version;
        }

        if (defined('NEWS_CRAWLER_PLUGIN_DIR')) {
            $plugin_file = NEWS_CRAWLER_PLUGIN_DIR . 'news-crawler.php';

            if (file_exists($plugin_file)) {
                if (!function_exists('get_plugin_data')) {
                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                }

                $plugin_data = get_plugin_data($plugin_file, false, false);
                if (!empty($plugin_data['Version'])) {
                    $version = (string) $plugin_data['Version'];
                    return $version;
                }
            }
        }

        $version = defined('NEWS_CRAWLER_VERSION') ? NEWS_CRAWLER_VERSION : '0.0.0';

        return $version;
    }
}
