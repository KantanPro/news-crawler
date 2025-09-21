<?php
/**
 * テスト用：ライセンスチェックを一時的に無効化
 * 自動投稿機能のテスト用
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

echo "=== News Crawler ライセンス無効化テスト ===\n";

// 現在のライセンス状態を確認
$current_license = get_option('news_crawler_license_key');
$license_status = get_option('news_crawler_license_status');

echo "現在のライセンスキー: " . (empty($current_license) ? '未設定' : substr($current_license, 0, 8) . '...') . "\n";
echo "現在のライセンスステータス: " . $license_status . "\n";

// ライセンスキーを削除
update_option('news_crawler_license_key', '');
update_option('news_crawler_license_status', 'not_set');
update_option('news_crawler_license_info', array(
    'message' => 'テスト用：ライセンスキーを削除しました。',
    'features' => array(
        'ai_summary' => false,
        'advanced_features' => false,
        'basic_features' => false
    )
));

echo "ライセンスキーを削除しました。\n";

// ライセンス管理クラスでテスト
if (class_exists('NewsCrawler_License_Manager')) {
    $license_manager = NewsCrawler_License_Manager::get_instance();
    
    echo "\n=== ライセンスチェックテスト ===\n";
    echo "is_license_valid(): " . ($license_manager->is_license_valid() ? 'true' : 'false') . "\n";
    echo "is_auto_posting_enabled(): " . ($license_manager->is_auto_posting_enabled() ? 'true' : 'false') . "\n";
    echo "is_basic_features_enabled(): " . ($license_manager->is_basic_features_enabled() ? 'true' : 'false') . "\n";
    echo "is_ai_summary_enabled(): " . ($license_manager->is_ai_summary_enabled() ? 'true' : 'false') . "\n";
    echo "is_advanced_features_enabled(): " . ($license_manager->is_advanced_features_enabled() ? 'true' : 'false') . "\n";
    echo "is_news_crawling_enabled(): " . ($license_manager->is_news_crawling_enabled() ? 'true' : 'false') . "\n";
    
    echo "\n=== 開発環境情報 ===\n";
    $dev_info = $license_manager->get_development_info();
    foreach ($dev_info as $key => $value) {
        echo "$key: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
    }
}

echo "\nテスト完了。自動投稿機能のみライセンスが必要な状態になりました。\n";
echo "X投稿設定画面でライセンス通知が表示されることを確認してください。\n";
?>
