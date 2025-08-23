<?php
/**
 * Plugin Name: News Crawler
 * Plugin URI: https://github.com/KantanPro/news-crawler
 * Description: 指定されたニュースソースから自動的に記事を取得し、WordPressサイトに投稿として追加するプラグイン
 * Version: 1.0.0
 * Author: KantanPro
 * Author URI: https://github.com/KantanPro
 * License: MIT
 * Text Domain: news-crawler
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの基本クラス
class NewsCrawler {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // 初期化処理
        load_plugin_textdomain('news-crawler', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        add_options_page(
            'News Crawler',
            'News Crawler',
            'manage_options',
            'news-crawler',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        // 管理画面の表示
        echo '<div class="wrap">';
        echo '<h1>News Crawler</h1>';
        echo '<p>設定画面は開発中です。</p>';
        echo '</div>';
    }
    
    public function activate() {
        // プラグイン有効化時の処理
    }
    
    public function deactivate() {
        // プラグイン無効化時の処理
    }
}

// プラグインの初期化
new NewsCrawler();
