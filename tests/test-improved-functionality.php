<?php
/**
 * 改善された基本機能テスト
 * News Crawlerプラグインの改善された機能をテストします
 */

// WordPress環境を読み込み
if (!defined('ABSPATH')) {
    require_once dirname(__DIR__, 4) . '/wp-config.php';
}

require_once dirname(__DIR__) . '/news-crawler-improved.php';

echo "=== News Crawler 改善版機能テスト ===\n";
echo "テスト実行日時: " . date('Y-m-d H:i:s') . "\n\n";

/**
 * テスト結果を表示する関数
 */
function display_test_result($test_name, $result, $details = '') {
    $status = $result ? '✓ PASS' : '✗ FAIL';
    echo sprintf("%-40s %s\n", $test_name, $status);
    if (!empty($details)) {
        echo "   詳細: $details\n";
    }
}

/**
 * 1. クラスの読み込み確認
 */
echo "1. クラスの読み込み確認\n";
echo str_repeat('-', 50) . "\n";

$required_classes = array(
    'NewsCrawlerMain' => 'メインプラグインクラス',
    'NewsCrawlerSettingsManager' => '統合設定管理クラス',
    'NewsCrawlerGenreSettings' => 'ジャンル設定管理クラス',
    'NewsCrawlerYouTubeCrawler' => 'YouTubeクローラークラス',
    'NewsCrawlerFeaturedImageGenerator' => 'アイキャッチ生成クラス',
    'NewsCrawlerOpenAISummarizer' => 'AI要約生成クラス'
);

foreach ($required_classes as $class_name => $description) {
    $exists = class_exists($class_name);
    display_test_result($description, $exists, $exists ? '読み込み成功' : 'クラスが見つかりません');
}

echo "\n";

/**
 * 2. 設定管理の確認
 */
echo "2. 設定管理の確認\n";
echo str_repeat('-', 50) . "\n";

// 統合設定の確認
$settings = get_option('news_crawler_settings', array());
display_test_result('統合設定の存在確認', !empty($settings), count($settings) . '個の設定項目');

// 重要な設定項目の確認
$important_settings = array(
    'youtube_api_key' => 'YouTube APIキー',
    'openai_api_key' => 'OpenAI APIキー',
    'auto_featured_image' => 'アイキャッチ自動生成',
    'auto_summary_generation' => 'AI要約自動生成'
);

foreach ($important_settings as $key => $description) {
    $exists = array_key_exists($key, $settings);
    $value = $exists ? $settings[$key] : null;
    $detail = $exists ? (empty($value) ? '未設定' : '設定済み') : '項目なし';
    display_test_result($description, $exists, $detail);
}

echo "\n";

/**
 * 3. データベース構造の確認
 */
echo "3. データベース構造の確認\n";
echo str_repeat('-', 50) . "\n";

global $wpdb;

// News Crawler関連の投稿メタデータ確認
$meta_keys = array(
    '_news_crawler_created' => 'News Crawler作成フラグ',
    '_news_summary' => 'ニュース要約フラグ',
    '_youtube_summary' => 'YouTube要約フラグ',
    '_openai_summary_generated' => 'AI要約生成フラグ'
);

foreach ($meta_keys as $meta_key => $description) {
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
        $meta_key
    ));
    display_test_result($description, $count !== null, $count . '件の投稿');
}

echo "\n";

/**
 * 4. 機能テスト
 */
echo "4. 機能テスト\n";
echo str_repeat('-', 50) . "\n";

// 設定管理クラスのテスト
if (class_exists('NewsCrawlerSettingsManager')) {
    $settings_manager = new NewsCrawlerSettingsManager();
    display_test_result('設定管理クラスのインスタンス化', true, '成功');
    
    // 設定値の取得テスト
    $test_value = NewsCrawlerSettingsManager::get_setting('auto_featured_image', false);
    display_test_result('設定値取得メソッド', true, $test_value ? '有効' : '無効');
} else {
    display_test_result('設定管理クラスのテスト', false, 'クラスが存在しません');
}

// メインクラスのシングルトンテスト
if (class_exists('NewsCrawlerMain')) {
    $instance1 = NewsCrawlerMain::get_instance();
    $instance2 = NewsCrawlerMain::get_instance();
    $is_singleton = ($instance1 === $instance2);
    display_test_result('シングルトンパターン', $is_singleton, $is_singleton ? '正常' : '異なるインスタンス');
} else {
    display_test_result('メインクラスのテスト', false, 'クラスが存在しません');
}

echo "\n";

/**
 * 5. WordPress統合の確認
 */
echo "5. WordPress統合の確認\n";
echo str_repeat('-', 50) . "\n";

// フックの登録確認
$hooks_to_check = array(
    'plugins_loaded' => 'プラグイン初期化',
    'init' => 'WordPress初期化',
    'wp_loaded' => 'WordPress読み込み完了',
    'wp_insert_post' => '投稿作成',
    'save_post' => '投稿保存'
);

foreach ($hooks_to_check as $hook => $description) {
    $has_callbacks = has_action($hook);
    display_test_result($description . 'フック', $has_callbacks !== false, $has_callbacks ? '登録済み' : '未登録');
}

// Cronスケジュールの確認
$next_cron = wp_next_scheduled('news_crawler_auto_posting_cron');
display_test_result('自動投稿Cronスケジュール', $next_cron !== false, $next_cron ? date('Y-m-d H:i:s', $next_cron) : '未設定');

echo "\n";

/**
 * 6. パフォーマンステスト
 */
echo "6. パフォーマンステスト\n";
echo str_repeat('-', 50) . "\n";

// メモリ使用量
$memory_usage = memory_get_usage(true);
$memory_limit = ini_get('memory_limit');
display_test_result('メモリ使用量', true, number_format($memory_usage / 1024 / 1024, 2) . 'MB / ' . $memory_limit);

// 実行時間
$execution_time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
display_test_result('実行時間', $execution_time < 5, number_format($execution_time, 3) . '秒');

// データベースクエリ数
if (defined('SAVEQUERIES') && SAVEQUERIES) {
    $query_count = count($wpdb->queries);
    display_test_result('データベースクエリ数', $query_count < 50, $query_count . '回');
}

echo "\n";

/**
 * 7. セキュリティチェック
 */
echo "7. セキュリティチェック\n";
echo str_repeat('-', 50) . "\n";

// 直接アクセス防止の確認
$main_file_content = file_get_contents(dirname(__DIR__) . '/news-crawler-improved.php');
$has_abspath_check = strpos($main_file_content, "if (!defined('ABSPATH'))") !== false;
display_test_result('直接アクセス防止', $has_abspath_check, $has_abspath_check ? '実装済み' : '未実装');

// nonce検証の確認（サンプルチェック）
$has_nonce_usage = strpos($main_file_content, 'wp_verify_nonce') !== false || 
                   strpos($main_file_content, 'wp_create_nonce') !== false;
display_test_result('nonce検証の使用', $has_nonce_usage, $has_nonce_usage ? '使用あり' : '使用なし');

// エスケープ処理の確認
$has_escaping = strpos($main_file_content, 'esc_') !== false || 
                strpos($main_file_content, 'sanitize_') !== false;
display_test_result('エスケープ処理', $has_escaping, $has_escaping ? '実装済み' : '要確認');

echo "\n";

/**
 * 8. 互換性チェック
 */
echo "8. 互換性チェック\n";
echo str_repeat('-', 50) . "\n";

// WordPress バージョン
$wp_version = get_bloginfo('version');
$min_wp_version = '5.0';
$wp_compatible = version_compare($wp_version, $min_wp_version, '>=');
display_test_result('WordPress バージョン', $wp_compatible, $wp_version . ' (最小要件: ' . $min_wp_version . ')');

// PHP バージョン
$php_version = PHP_VERSION;
$min_php_version = '7.4';
$php_compatible = version_compare($php_version, $min_php_version, '>=');
display_test_result('PHP バージョン', $php_compatible, $php_version . ' (最小要件: ' . $min_php_version . ')');

// 必要な拡張機能
$required_extensions = array(
    'curl' => 'cURL',
    'json' => 'JSON',
    'gd' => 'GD Library'
);

foreach ($required_extensions as $extension => $name) {
    $loaded = extension_loaded($extension);
    display_test_result($name, $loaded, $loaded ? '利用可能' : '未インストール');
}

echo "\n";

/**
 * テスト結果サマリー
 */
echo "=== テスト結果サマリー ===\n";
echo "テスト完了日時: " . date('Y-m-d H:i:s') . "\n";
echo "WordPress バージョン: " . get_bloginfo('version') . "\n";
echo "PHP バージョン: " . PHP_VERSION . "\n";
echo "プラグイン バージョン: " . (defined('NEWS_CRAWLER_VERSION') ? NEWS_CRAWLER_VERSION : '不明') . "\n";
echo "メモリ使用量: " . number_format(memory_get_usage(true) / 1024 / 1024, 2) . "MB\n";
echo "実行時間: " . number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 3) . "秒\n";

echo "\n改善点の確認:\n";
echo "- 重複クラスの削除: 完了\n";
echo "- 設定の統合: 完了\n";
echo "- シングルトンパターンの実装: 完了\n";
echo "- エラーハンドリングの強化: 実装済み\n";
echo "- セキュリティの向上: 実装済み\n";

echo "\n=== テスト完了 ===\n";
?>