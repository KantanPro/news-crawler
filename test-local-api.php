<?php
/**
 * ローカルAPIエンドポイントでのライセンス認証テスト
 */

// WordPressの読み込み
require_once('/var/www/html/wp-config.php');

echo "=== ローカルAPIエンドポイントでのライセンス認証テスト ===\n";

// 現在の設定を確認
$current_license = get_option('news_crawler_license_key');
$is_dev = defined('NEWS_CRAWLER_DEVELOPMENT_MODE') && NEWS_CRAWLER_DEVELOPMENT_MODE === true;

echo "現在のライセンスキー: " . $current_license . "\n";
echo "開発環境フラグ: " . ($is_dev ? '有効' : '無効') . "\n";
echo "ホームURL: " . home_url() . "\n";

// ライセンスマネージャーのインスタンスを取得
$license_manager = NewsCrawler_License_Manager::get_instance();

// リフレクションを使用してプライベートプロパティにアクセス
$reflection = new ReflectionClass($license_manager);
$api_endpoints_property = $reflection->getProperty('api_endpoints');
$api_endpoints_property->setAccessible(true);
$api_endpoints = $api_endpoints_property->getValue($license_manager);

echo "\n=== APIエンドポイント確認 ===\n";
foreach ($api_endpoints as $key => $url) {
    echo $key . ": " . $url . "\n";
}

// ライセンス認証をテスト
if (!empty($current_license)) {
    echo "\n=== ライセンス認証テスト ===\n";
    
    // ライセンス認証を実行
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
} else {
    echo "\nライセンスキーが設定されていません。\n";
}

echo "\n=== テスト完了 ===\n";
?>
