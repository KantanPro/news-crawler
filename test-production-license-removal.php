<?php
/**
 * 本番環境でのライセンスキー削除テスト
 */

// WordPressの読み込み
require_once('/var/www/html/wp-config.php');

echo "=== 本番環境ライセンス削除テスト ===\n";

// 現在の環境を確認
$is_dev = defined('NEWS_CRAWLER_DEVELOPMENT_MODE') && NEWS_CRAWLER_DEVELOPMENT_MODE === true;
echo "開発環境フラグ: " . ($is_dev ? '有効' : '無効（本番環境）') . "\n";

// 現在のライセンス状態を確認
$current_license = get_option('news_crawler_license_key');
$license_status = get_option('news_crawler_license_status');

echo "現在のライセンスキー: " . (empty($current_license) ? '未設定' : substr($current_license, 0, 8) . '...') . "\n";
echo "現在のライセンスステータス: " . $license_status . "\n";

// ライセンス管理クラスでテスト
if (class_exists('NewsCrawler_License_Manager')) {
    $license_manager = NewsCrawler_License_Manager::get_instance();
    
    echo "\n=== ライセンスチェック結果 ===\n";
    echo "is_development_environment(): " . ($license_manager->is_development_environment() ? 'true' : 'false') . "\n";
    echo "is_license_valid(): " . ($license_manager->is_license_valid() ? 'true' : 'false') . "\n";
    echo "is_auto_posting_enabled(): " . ($license_manager->is_auto_posting_enabled() ? 'true' : 'false') . "\n";
    echo "is_basic_features_enabled(): " . ($license_manager->is_basic_features_enabled() ? 'true' : 'false') . "\n";
    echo "is_ai_summary_enabled(): " . ($license_manager->is_ai_summary_enabled() ? 'true' : 'false') . "\n";
    echo "is_advanced_features_enabled(): " . ($license_manager->is_advanced_features_enabled() ? 'true' : 'false') . "\n";
    echo "is_news_crawling_enabled(): " . ($license_manager->is_news_crawling_enabled() ? 'true' : 'false') . "\n";
    
    // ライセンスキーを削除してテスト
    echo "\n=== ライセンスキー削除テスト ===\n";
    update_option('news_crawler_license_key', '');
    update_option('news_crawler_license_status', 'not_set');
    
    echo "ライセンスキーを削除しました。\n";
    
    // 削除後の状態を確認
    echo "\n=== 削除後のライセンスチェック結果 ===\n";
    echo "is_license_valid(): " . ($license_manager->is_license_valid() ? 'true' : 'false') . "\n";
    echo "is_auto_posting_enabled(): " . ($license_manager->is_auto_posting_enabled() ? 'true' : 'false') . "\n";
    echo "is_basic_features_enabled(): " . ($license_manager->is_basic_features_enabled() ? 'true' : 'false') . "\n";
    echo "is_ai_summary_enabled(): " . ($license_manager->is_ai_summary_enabled() ? 'true' : 'false') . "\n";
    echo "is_advanced_features_enabled(): " . ($license_manager->is_advanced_features_enabled() ? 'true' : 'false') . "\n";
    echo "is_news_crawling_enabled(): " . ($license_manager->is_news_crawling_enabled() ? 'true' : 'false') . "\n";
    
    echo "\n=== 期待される結果 ===\n";
    echo "is_license_valid(): false（ライセンスキーが空のため）\n";
    echo "is_auto_posting_enabled(): false（自動投稿機能はライセンス必要）\n";
    echo "is_basic_features_enabled(): true（基本機能はライセンス不要）\n";
    echo "is_ai_summary_enabled(): true（AI要約はライセンス不要）\n";
    echo "is_advanced_features_enabled(): true（高度な機能はライセンス不要）\n";
    echo "is_news_crawling_enabled(): true（ニュースクローリングはライセンス不要）\n";
}

echo "\nテスト完了。\n";
?>
