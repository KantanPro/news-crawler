<?php
/**
 * 魅力的な自動投稿設定表示テスト
 */

// WordPressの読み込み
require_once('/var/www/html/wp-config.php');

echo "=== 魅力的な自動投稿設定表示テスト ===\n";

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
    
    // 自動投稿設定メニューの表示判定をテスト
    echo "\n=== 自動投稿設定メニュー表示判定 ===\n";
    if (!$license_manager->is_auto_posting_enabled()) {
        echo "自動投稿設定画面: 魅力的なライセンス制限メッセージを表示\n";
        echo "デザイン特徴:\n";
        echo "  - 黄色のメニュー文字（🚀 自動投稿設定）\n";
        echo "  - グラデーション背景のタイトル\n";
        echo "  - 機能特徴のリスト表示\n";
        echo "  - 魅力的なボタンデザイン\n";
        echo "  - 購入後の案内メッセージ\n";
    } else {
        echo "自動投稿設定画面: 通常の設定画面を表示（ライセンス有効）\n";
    }
}

echo "\nテスト完了。\n";
echo "期待される動作:\n";
echo "- サブメニュー: 黄色の「🚀 自動投稿設定」で目立つ表示\n";
echo "- ライセンス無効時: KantanProスタイルの魅力的な制限画面\n";
echo "- ユーザーがライセンス購入したくなるデザイン\n";
?>

