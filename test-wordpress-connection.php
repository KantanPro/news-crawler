<?php
/**
 * WordPress接続テストスクリプト
 * データベース接続問題を調査する
 */

// エラー表示を有効にする
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== WordPress接続テスト ===\n";
echo "開始時刻: " . date('Y-m-d H:i:s') . "\n\n";

// スクリプトのディレクトリを取得
$script_dir = __DIR__;

// WordPressのパスを検出
$wp_paths = [
    dirname(dirname(dirname($script_dir))) . '/',
    dirname(dirname($script_dir)) . '/',
    dirname($script_dir) . '/',
    '/var/www/html/',
    '/home/*/public_html/',
    '/home/*/www/',
    '/usr/local/var/www/',
    '/srv/www/'
];

$wp_path = null;
foreach ($wp_paths as $path) {
    if (file_exists($path . 'wp-config.php')) {
        $wp_path = $path;
        break;
    }
}

if (!$wp_path) {
    echo "エラー: WordPressパスが見つかりません\n";
    exit(1);
}

echo "WordPressパス: $wp_path\n";

// wp-config.phpの内容を確認
$wp_config_path = $wp_path . 'wp-config.php';
echo "wp-config.php: $wp_config_path\n";

if (file_exists($wp_config_path)) {
    echo "wp-config.php: 存在\n";
    
    // データベース設定を確認
    $wp_config_content = file_get_contents($wp_config_path);
    
    // データベース設定を抽出
    preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $wp_config_content, $db_name_matches);
    preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $wp_config_content, $db_user_matches);
    preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]*)['\"]\s*\)/", $wp_config_content, $db_host_matches);
    
    if (isset($db_name_matches[1])) {
        echo "データベース名: " . $db_name_matches[1] . "\n";
    }
    if (isset($db_user_matches[1])) {
        echo "データベースユーザー: " . $db_user_matches[1] . "\n";
    }
    if (isset($db_host_matches[1])) {
        echo "データベースホスト: " . $db_host_matches[1] . "\n";
    }
} else {
    echo "エラー: wp-config.phpが見つかりません\n";
    exit(1);
}

// WordPressの定数を設定
if (!defined('ABSPATH')) {
    define('ABSPATH', $wp_path);
}
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}
if (!defined('DOING_CRON')) {
    define('DOING_CRON', true);
}

echo "\n=== WordPress読み込みテスト ===\n";

try {
    // wp-load.phpを読み込み
    require_once($wp_path . 'wp-load.php');
    echo "WordPress読み込み: 成功\n";
    
    // データベース接続をテスト
    global $wpdb;
    if (isset($wpdb) && $wpdb->last_error === '') {
        echo "データベース接続: 成功\n";
        
        // 簡単なクエリをテスト
        $result = $wpdb->get_var("SELECT 1");
        if ($result === '1') {
            echo "データベースクエリ: 成功\n";
        } else {
            echo "データベースクエリ: 失敗\n";
        }
    } else {
        echo "データベース接続: 失敗\n";
        if (isset($wpdb)) {
            echo "エラー: " . $wpdb->last_error . "\n";
        }
    }
    
    // WordPress関数をテスト
    if (function_exists('get_option')) {
        echo "WordPress関数: 利用可能\n";
        $site_url = get_option('siteurl');
        echo "サイトURL: $site_url\n";
    } else {
        echo "WordPress関数: 利用不可\n";
    }
    
    // プラグインクラスをテスト
    if (class_exists('NewsCrawlerGenreSettings')) {
        echo "NewsCrawlerGenreSettingsクラス: 存在\n";
        
        $genre_settings = new NewsCrawlerGenreSettings();
        echo "インスタンス作成: 成功\n";
        
        // ジャンル設定を取得
        $all_genre_settings = $genre_settings->get_all_genre_settings();
        echo "ジャンル設定数: " . count($all_genre_settings) . "\n";
        
        // 自動投稿をテスト
        echo "\n=== 自動投稿テスト ===\n";
        $result = $genre_settings->execute_auto_posting();
        echo "自動投稿結果: " . print_r($result, true) . "\n";
        
    } else {
        echo "NewsCrawlerGenreSettingsクラス: 存在しない\n";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
    echo "スタックトレース:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== テスト完了 ===\n";
echo "終了時刻: " . date('Y-m-d H:i:s') . "\n";
?>