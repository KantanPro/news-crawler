<?php
/**
 * スタンドアロン機能テスト
 * データベース接続なしでプラグインの基本構造をテストします
 */

echo "=== News Crawler スタンドアロンテスト ===\n";
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
 * 1. ファイル構造の確認
 */
echo "1. ファイル構造の確認\n";
echo str_repeat('-', 50) . "\n";

$base_dir = dirname(__DIR__);
$required_files = array(
    'news-crawler-improved.php' => 'メインプラグインファイル（改善版）',
    'includes/class-settings-manager.php' => '統合設定管理クラス',
    'includes/class-genre-settings.php' => 'ジャンル設定管理クラス',
    'includes/class-youtube-crawler.php' => 'YouTubeクローラークラス',
    'includes/class-openai-summarizer.php' => 'AI要約生成クラス',
    'README-improved.md' => '改善版README'
);

foreach ($required_files as $file => $description) {
    $exists = file_exists($base_dir . '/' . $file);
    $size = $exists ? filesize($base_dir . '/' . $file) : 0;
    display_test_result($description, $exists, $exists ? number_format($size) . ' bytes' : 'ファイルなし');
}

echo "\n";

/**
 * 2. コード品質の確認
 */
echo "2. コード品質の確認\n";
echo str_repeat('-', 50) . "\n";

// メインファイルの内容確認
$main_file = $base_dir . '/news-crawler-improved.php';
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    
    // セキュリティチェック
    $has_abspath_check = strpos($content, "if (!defined('ABSPATH'))") !== false;
    display_test_result('直接アクセス防止', $has_abspath_check, $has_abspath_check ? '実装済み' : '未実装');
    
    // シングルトンパターンの確認
    $has_singleton = strpos($content, 'get_instance()') !== false && strpos($content, 'private function __construct()') !== false;
    display_test_result('シングルトンパターン', $has_singleton, $has_singleton ? '実装済み' : '未実装');
    
    // 定数の定義確認
    $has_constants = strpos($content, 'define(') !== false;
    display_test_result('プラグイン定数の定義', $has_constants, $has_constants ? '定義済み' : '未定義');
    
    // クラス読み込みの確認
    $has_includes = strpos($content, 'require_once') !== false;
    display_test_result('クラスファイルの読み込み', $has_includes, $has_includes ? '実装済み' : '未実装');
    
    // フック登録の確認
    $has_hooks = strpos($content, 'add_action') !== false;
    display_test_result('WordPressフックの登録', $has_hooks, $has_hooks ? '実装済み' : '未実装');
}

echo "\n";

/**
 * 3. 設定管理クラスの確認
 */
echo "3. 設定管理クラスの確認\n";
echo str_repeat('-', 50) . "\n";

$settings_file = $base_dir . '/includes/class-settings-manager.php';
if (file_exists($settings_file)) {
    $content = file_get_contents($settings_file);
    
    // クラス定義の確認
    $has_class = strpos($content, 'class NewsCrawlerSettingsManager') !== false;
    display_test_result('設定管理クラスの定義', $has_class, $has_class ? '定義済み' : '未定義');
    
    // 管理画面の確認
    $has_admin_menu = strpos($content, 'add_admin_menu') !== false;
    display_test_result('管理メニューの実装', $has_admin_menu, $has_admin_menu ? '実装済み' : '未実装');
    
    // タブ機能の確認
    $has_tabs = strpos($content, 'nav-tab') !== false;
    display_test_result('タブ形式の設定画面', $has_tabs, $has_tabs ? '実装済み' : '未実装');
    
    // API テスト機能の確認
    $has_api_test = strpos($content, 'test_api_connection') !== false;
    display_test_result('API接続テスト機能', $has_api_test, $has_api_test ? '実装済み' : '未実装');
    
    // 設定リセット機能の確認
    $has_reset = strpos($content, 'reset_plugin_settings') !== false;
    display_test_result('設定リセット機能', $has_reset, $has_reset ? '実装済み' : '未実装');
}

echo "\n";

/**
 * 4. 重複コードの削除確認
 */
echo "4. 重複コードの削除確認\n";
echo str_repeat('-', 50) . "\n";

// 元のファイルでの重複クラス確認
$original_file = $base_dir . '/news-crawler.php';
if (file_exists($original_file)) {
    $content = file_get_contents($original_file);
    
    // 重複したYouTubeCrawlerクラスの確認
    $youtube_class_count = substr_count($content, 'class YouTubeCrawler');
    $newscrawler_youtube_count = substr_count($content, 'class NewsCrawlerYouTubeCrawler');
    
    display_test_result('YouTubeCrawlerクラスの重複', $youtube_class_count <= 1, 
        "YouTubeCrawler: {$youtube_class_count}個, NewsCrawlerYouTubeCrawler: {$newscrawler_youtube_count}個");
    
    // 設定オプションの重複確認
    $option_patterns = array(
        'youtube_crawler_settings',
        'news_crawler_settings',
        'news_crawler_basic_settings'
    );
    
    $option_usage = array();
    foreach ($option_patterns as $pattern) {
        $count = substr_count($content, $pattern);
        $option_usage[$pattern] = $count;
    }
    
    display_test_result('設定オプションの使用状況', true, 
        implode(', ', array_map(function($k, $v) { return "$k: {$v}回"; }, array_keys($option_usage), $option_usage)));
}

// 改善版での統合確認
$improved_file = $base_dir . '/news-crawler-improved.php';
if (file_exists($improved_file)) {
    $content = file_get_contents($improved_file);
    
    // 統合設定の使用確認
    $unified_settings = substr_count($content, 'news_crawler_settings');
    display_test_result('統合設定の使用', $unified_settings > 0, "{$unified_settings}箇所で使用");
    
    // 重複クラスの削除確認
    $no_duplicate_classes = strpos($content, 'class YouTubeCrawler {') === false;
    display_test_result('重複クラスの削除', $no_duplicate_classes, $no_duplicate_classes ? '削除済み' : '残存あり');
}

echo "\n";

/**
 * 5. パフォーマンス改善の確認
 */
echo "5. パフォーマンス改善の確認\n";
echo str_repeat('-', 50) . "\n";

// ファイルサイズの比較
if (file_exists($original_file) && file_exists($improved_file)) {
    $original_size = filesize($original_file);
    $improved_size = filesize($improved_file);
    $size_reduction = (($original_size - $improved_size) / $original_size) * 100;
    
    display_test_result('ファイルサイズの削減', $improved_size < $original_size, 
        sprintf("%.1f%% 削減 (%s → %s)", $size_reduction, number_format($original_size), number_format($improved_size)));
}

// コード行数の比較
if (file_exists($original_file) && file_exists($improved_file)) {
    $original_lines = count(file($original_file));
    $improved_lines = count(file($improved_file));
    $line_reduction = (($original_lines - $improved_lines) / $original_lines) * 100;
    
    display_test_result('コード行数の削減', $improved_lines < $original_lines,
        sprintf("%.1f%% 削減 (%d行 → %d行)", $line_reduction, $original_lines, $improved_lines));
}

echo "\n";

/**
 * 6. 新機能の確認
 */
echo "6. 新機能の確認\n";
echo str_repeat('-', 50) . "\n";

$new_features = array(
    'シングルトンパターン' => 'get_instance()',
    '統合設定管理' => 'NewsCrawlerSettingsManager',
    'API接続テスト' => 'test_api_connection',
    '設定リセット機能' => 'reset_plugin_settings',
    'タブ形式UI' => 'nav-tab-wrapper',
    'システム情報表示' => 'display_system_info',
    'エラーハンドリング強化' => 'wp_send_json_error',
    'セキュリティ強化' => 'wp_verify_nonce'
);

if (file_exists($improved_file)) {
    $content = file_get_contents($improved_file);
    
    foreach ($new_features as $feature => $pattern) {
        $has_feature = strpos($content, $pattern) !== false;
        display_test_result($feature, $has_feature, $has_feature ? '実装済み' : '未実装');
    }
}

echo "\n";

/**
 * 7. ドキュメントの改善確認
 */
echo "7. ドキュメントの改善確認\n";
echo str_repeat('-', 50) . "\n";

$readme_improved = $base_dir . '/README-improved.md';
if (file_exists($readme_improved)) {
    $content = file_get_contents($readme_improved);
    
    // 改善点の記載確認
    $improvement_sections = array(
        '改善点' => '## 🚀 v2.0.0の主な改善点',
        'インストール手順' => '## 🛠️ インストール',
        'トラブルシューティング' => '## 🚨 トラブルシューティング',
        'パフォーマンス指標' => '## 📊 パフォーマンス指標',
        'アップグレードガイド' => '## 🔄 アップグレードガイド'
    );
    
    foreach ($improvement_sections as $section => $pattern) {
        $has_section = strpos($content, $pattern) !== false;
        display_test_result($section . 'の記載', $has_section, $has_section ? '記載済み' : '未記載');
    }
    
    $file_size = filesize($readme_improved);
    display_test_result('ドキュメントの充実度', $file_size > 10000, number_format($file_size) . ' bytes');
}

echo "\n";

/**
 * 8. PHP構文チェック
 */
echo "8. PHP構文チェック\n";
echo str_repeat('-', 50) . "\n";

$php_files = array(
    'news-crawler-improved.php',
    'includes/class-settings-manager.php'
);

foreach ($php_files as $file) {
    $full_path = $base_dir . '/' . $file;
    if (file_exists($full_path)) {
        $output = array();
        $return_code = 0;
        exec("php -l " . escapeshellarg($full_path) . " 2>&1", $output, $return_code);
        
        $syntax_ok = ($return_code === 0);
        $result_text = $syntax_ok ? '構文OK' : '構文エラー: ' . implode(' ', $output);
        display_test_result(basename($file) . ' 構文チェック', $syntax_ok, $result_text);
    }
}

echo "\n";

/**
 * テスト結果サマリー
 */
echo "=== テスト結果サマリー ===\n";
echo "テスト完了日時: " . date('Y-m-d H:i:s') . "\n";
echo "PHP バージョン: " . PHP_VERSION . "\n";

// 改善点の確認
$improvements = array(
    '重複クラスの削除' => file_exists($improved_file) && strpos(file_get_contents($improved_file), 'class YouTubeCrawler {') === false,
    '設定の統合' => file_exists($base_dir . '/includes/class-settings-manager.php'),
    'シングルトンパターンの実装' => file_exists($improved_file) && strpos(file_get_contents($improved_file), 'get_instance()') !== false,
    'セキュリティの強化' => file_exists($improved_file) && strpos(file_get_contents($improved_file), 'wp_verify_nonce') !== false,
    'ドキュメントの改善' => file_exists($readme_improved)
);

echo "\n改善状況:\n";
foreach ($improvements as $improvement => $status) {
    echo "- $improvement: " . ($status ? '✓ 完了' : '✗ 未完了') . "\n";
}

$completed_count = count(array_filter($improvements));
$total_count = count($improvements);
$completion_rate = ($completed_count / $total_count) * 100;

echo "\n改善完了率: {$completed_count}/{$total_count} (" . number_format($completion_rate, 1) . "%)\n";

echo "\n=== テスト完了 ===\n";
?>