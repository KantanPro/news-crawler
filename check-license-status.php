<?php
/**
 * ライセンス認証状態の確認スクリプト
 */

// WordPressの読み込み
require_once('/var/www/html/wp-config.php');

echo "=== News Crawler ライセンス認証状態確認 ===\n";

// 現在のライセンスキーを取得
$current_license = get_option('news_crawler_license_key');
echo "現在のライセンスキー: " . (empty($current_license) ? '未設定' : $current_license) . "\n";

// ライセンス認証スキップ設定を確認
$skip_check = get_option('news_crawler_license_skip_check', false);
echo "ライセンス認証スキップ設定: " . ($skip_check ? '有効' : '無効') . "\n";

// HTTP 403エラーカウンターを確認
$error_count = get_option('news_crawler_403_error_count', 0);
echo "HTTP 403エラーカウンター: " . $error_count . "\n";

// 開発環境フラグを確認
$is_dev = defined('NEWS_CRAWLER_DEVELOPMENT_MODE') && NEWS_CRAWLER_DEVELOPMENT_MODE === true;
echo "開発環境フラグ: " . ($is_dev ? '有効' : '無効') . "\n";

// ライセンス認証をテスト
if (!empty($current_license)) {
    echo "\n=== ライセンス認証テスト ===\n";
    
    // 本番環境のライセンスキーでもテスト
    $production_license = 'NCRL-145561-JUMG8|GG-366B';
    echo "本番環境のライセンスキーでテスト: " . $production_license . "\n";
    
    // ライセンスマネージャーのインスタンスを取得
    $license_manager = News_Crawler_License_Manager::get_instance();
    
    // 現在のライセンスキーで認証を実行
    echo "\n--- 現在のライセンスキーでの認証 ---\n";
    $result = $license_manager->verify_license($current_license);
    
    echo "認証結果:\n";
    echo "成功: " . ($result['success'] ? 'はい' : 'いいえ') . "\n";
    echo "メッセージ: " . $result['message'] . "\n";
    
    if (isset($result['data'])) {
        echo "ライセンスデータ:\n";
        foreach ($result['data'] as $key => $value) {
            echo "  " . $key . ": " . $value . "\n";
        }
    }
    
    if (isset($result['debug_info'])) {
        echo "デバッグ情報:\n";
        foreach ($result['debug_info'] as $key => $value) {
            if (is_array($value)) {
                echo "  " . $key . ": " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "  " . $key . ": " . $value . "\n";
            }
        }
    }
    
    // 本番環境のライセンスキーでもテスト
    echo "\n--- 本番環境のライセンスキーでの認証 ---\n";
    $prod_result = $license_manager->verify_license($production_license);
    
    echo "認証結果:\n";
    echo "成功: " . ($prod_result['success'] ? 'はい' : 'いいえ') . "\n";
    echo "メッセージ: " . $prod_result['message'] . "\n";
    
    if (isset($prod_result['data'])) {
        echo "ライセンスデータ:\n";
        foreach ($prod_result['data'] as $key => $value) {
            echo "  " . $key . ": " . $value . "\n";
        }
    }
    
    if (isset($prod_result['debug_info'])) {
        echo "デバッグ情報:\n";
        foreach ($prod_result['debug_info'] as $key => $value) {
            if (is_array($value)) {
                echo "  " . $key . ": " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
            } else {
                echo "  " . $key . ": " . $value . "\n";
            }
        }
    }
} else {
    echo "\nライセンスキーが設定されていません。\n";
}

echo "\n=== 確認完了 ===\n";
?>
