<?php
// WordPress環境を読み込み
require_once '/var/www/html/wp-config.php';

echo "=== アイキャッチ自動生成の有効化 ===\n";

// 現在の基本設定を確認
$basic_settings = get_option('news_crawler_basic_settings', array());
echo "現在の基本設定:\n";
echo print_r($basic_settings, true) . "\n";

// アイキャッチ自動生成を有効化
$basic_settings['auto_featured_image'] = true;
$basic_settings['featured_image_method'] = 'ai';

$result = update_option('news_crawler_basic_settings', $basic_settings);
echo "設定更新結果: " . ($result ? '✅ 成功' : '❌ 失敗') . "\n";

// 確認
$updated_settings = get_option('news_crawler_basic_settings', array());
echo "\n更新後の基本設定:\n";
echo print_r($updated_settings, true) . "\n";

echo "アイキャッチ自動生成: " . (isset($updated_settings['auto_featured_image']) && $updated_settings['auto_featured_image'] ? 'ON' : 'OFF') . "\n";
echo "アイキャッチ生成方法: " . ($updated_settings['featured_image_method'] ?? '未設定') . "\n";

echo "\n=== 設定完了 ===\n";
?>