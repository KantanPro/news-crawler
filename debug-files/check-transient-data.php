<?php
// WordPress環境を読み込み
require_once '/var/www/html/wp-config.php';

echo "=== 一時保存データの確認 ===\n";

// 現在の一時保存データを確認
$genre_setting = get_transient('news_crawler_current_genre_setting');

echo "現在の一時保存データ:\n";
if ($genre_setting) {
    echo "✓ データあり\n";
    echo print_r($genre_setting, true) . "\n";
} else {
    echo "✗ データなし\n";
}

// 全ての一時保存データを確認
global $wpdb;
$transients = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_news_crawler%'");

echo "\n全ての関連一時保存データ:\n";
if ($transients) {
    foreach ($transients as $transient) {
        echo "キー: " . $transient->option_name . "\n";
        echo "値: " . $transient->option_value . "\n\n";
    }
} else {
    echo "関連する一時保存データが見つかりません\n";
}

// ジャンル設定を確認
$genre_settings = get_option('news_crawler_genre_settings', array());
echo "\nジャンル設定:\n";
if (!empty($genre_settings)) {
    foreach ($genre_settings as $index => $setting) {
        echo "ジャンル $index:\n";
        echo "  名前: " . ($setting['name'] ?? '未設定') . "\n";
        echo "  自動アイキャッチ: " . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? 'ON' : 'OFF') . "\n";
        echo "  アイキャッチ方法: " . ($setting['featured_image_method'] ?? '未設定') . "\n";
        echo "  URL: " . ($setting['url'] ?? '未設定') . "\n\n";
    }
} else {
    echo "ジャンル設定が見つかりません\n";
}

// テスト用の一時保存データを作成
echo "=== テスト用一時保存データの作成 ===\n";
$test_setting = array(
    'name' => 'テストジャンル',
    'url' => 'https://example.com',
    'auto_featured_image' => true,
    'featured_image_method' => 'ai',
    'keywords' => array('ニュース', 'テスト')
);

set_transient('news_crawler_current_genre_setting', $test_setting, 3600);
echo "テスト用一時保存データを作成しました\n";

// 確認
$check_setting = get_transient('news_crawler_current_genre_setting');
if ($check_setting) {
    echo "✓ 一時保存データの作成成功\n";
    echo print_r($check_setting, true);
} else {
    echo "✗ 一時保存データの作成失敗\n";
}
?>