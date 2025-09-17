<?php
/**
 * 最終的なライセンス認証テスト
 */

// WordPressの読み込み
require_once('/var/www/html/wp-config.php');

echo "=== 最終ライセンス認証テスト ===\n";

// 現在の設定を確認
$current_license = get_option('news_crawler_license_key');
$is_dev = defined('NEWS_CRAWLER_DEVELOPMENT_MODE') && NEWS_CRAWLER_DEVELOPMENT_MODE === true;
$skip_check = get_option('news_crawler_license_skip_check', false);

echo "現在のライセンスキー: " . $current_license . "\n";
echo "開発環境フラグ: " . ($is_dev ? '有効' : '無効') . "\n";
echo "スキップ設定: " . ($skip_check ? '有効' : '無効') . "\n";

// スキップ設定を無効化
if ($skip_check) {
    update_option('news_crawler_license_skip_check', false);
    echo "✅ スキップ設定を無効化しました\n";
}

// ライセンス認証をテスト
if (!empty($current_license)) {
    echo "\n=== ライセンス認証テスト ===\n";
    
    // ライセンスマネージャーのインスタンスを取得
    $license_manager = News_Crawler_License_Manager::get_instance();
    
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
    
    // 認証結果の詳細分析
    echo "\n=== 認証結果の詳細分析 ===\n";
    if ($result['success']) {
        if (isset($result['data']['status']) && $result['data']['status'] === 'active') {
            echo "✅ 実際のライセンスキーで正常に認証されています\n";
            echo "ライセンスステータス: " . $result['data']['status'] . "\n";
            echo "ユーザーEmail: " . $result['data']['user_email'] . "\n";
            echo "プロダクトタイプ: " . $result['data']['product_type'] . "\n";
        } else {
            echo "⚠️  認証は成功しましたが、予期しないステータスです\n";
            echo "ステータス: " . ($result['data']['status'] ?? '不明') . "\n";
        }
    } else {
        echo "❌ ライセンス認証に失敗しました\n";
        echo "エラーコード: " . ($result['error_code'] ?? '不明') . "\n";
    }
} else {
    echo "\nライセンスキーが設定されていません。\n";
}

echo "\n=== テスト完了 ===\n";
?>
