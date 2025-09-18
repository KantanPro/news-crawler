<?php
/**
 * 本番環境でライセンス認証を一時的にスキップする設定
 * HTTP 403エラーが解決するまでの緊急回避策
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

// ライセンス認証スキップ機能を有効化
update_option('news_crawler_license_skip_check', true);

// 設定の確認
$skip_check = get_option('news_crawler_license_skip_check');
echo "ライセンス認証スキップ設定: " . ($skip_check ? '有効' : '無効') . "\n";

// 現在のライセンス状態を確認
$license_manager = NewsCrawler_License_Manager::get_instance();
$current_license = get_option('news_crawler_license_key');
echo "現在のライセンスキー: " . (empty($current_license) ? '未設定' : substr($current_license, 0, 8) . '...') . "\n";

// ライセンス認証をテスト
if (!empty($current_license)) {
    echo "ライセンス認証をテスト中...\n";
    $result = $license_manager->verify_license($current_license);
    echo "認証結果: " . ($result['success'] ? '成功' : '失敗') . "\n";
    if (!$result['success']) {
        echo "エラーメッセージ: " . $result['message'] . "\n";
    }
}

echo "設定完了\n";
?>
