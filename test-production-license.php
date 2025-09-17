<?php
/**
 * 本番環境でのライセンス認証テストスクリプト
 * 
 * 使用方法:
 * 1. このファイルを本番環境のWordPressルートディレクトリにアップロード
 * 2. ブラウザでアクセス: https://www.kantanpro.com/test-production-license.php
 * 3. ライセンスキーを入力してテスト実行
 * 4. テスト完了後、このファイルを削除してください（セキュリティのため）
 */

// WordPress環境の読み込み
require_once('wp-load.php');

// 管理者権限チェック
if (!current_user_can('manage_options')) {
    die('このスクリプトは管理者のみアクセス可能です。');
}

// ライセンス管理クラスの読み込み
if (!class_exists('NewsCrawler_License_Manager')) {
    die('News Crawler プラグインが有効化されていません。');
}

$license_manager = NewsCrawler_License_Manager::get_instance();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Crawler - 本番環境ライセンス認証テスト</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a87; }
        .result { margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9; }
        .success { border-color: #46b450; background: #f0f8f0; }
        .error { border-color: #dc3232; background: #fef7f7; }
        .debug-info { margin-top: 10px; padding: 10px; background: #fff; border: 1px solid #ccc; border-radius: 4px; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <div class="container">
        <h1>News Crawler - 本番環境ライセンス認証テスト</h1>
        
        <form method="POST">
            <div class="form-group">
                <label for="license_key">ライセンスキー:</label>
                <input type="text" id="license_key" name="license_key" value="<?php echo esc_attr($_POST['license_key'] ?? ''); ?>" placeholder="例: NCRL-123456-ABC12345-XYZ4" required>
            </div>
            
            <div class="form-group">
                <label for="site_url">サイトURL (オプション):</label>
                <input type="text" id="site_url" name="site_url" value="<?php echo esc_attr($_POST['site_url'] ?? 'https://www.kantanpro.com'); ?>" placeholder="例: https://www.kantanpro.com">
            </div>
            
            <button type="submit" name="test_license">ライセンスをテスト</button>
        </form>

        <?php if (isset($_POST['test_license'])): ?>
            <?php
            $license_key = sanitize_text_field($_POST['license_key']);
            $site_url = sanitize_url($_POST['site_url']);
            
            echo '<div class="result">';
            echo '<h3>テスト結果</h3>';
            
            if (empty($license_key)) {
                echo '<div class="error">エラー: ライセンスキーが入力されていません。</div>';
            } else {
                // 環境情報を表示
                echo '<h4>環境情報</h4>';
                echo '<p>WordPressバージョン: ' . get_bloginfo('version') . '</p>';
                echo '<p>PHPバージョン: ' . PHP_VERSION . '</p>';
                echo '<p>サイトURL: ' . home_url() . '</p>';
                echo '<p>開発環境判定: ' . ($license_manager->is_development_environment() ? 'はい' : 'いいえ') . '</p>';
                
                // ライセンス検証をテスト
                echo '<h4>ライセンス検証テスト</h4>';
                $result = $license_manager->verify_license($license_key);
                
                if ($result['success']) {
                    echo '<div class="success">';
                    echo '<p><strong>成功:</strong> ライセンスが有効です</p>';
                    echo '<p><strong>メッセージ:</strong> ' . esc_html($result['message']) . '</p>';
                    if (isset($result['data'])) {
                        echo '<p><strong>ライセンス情報:</strong></p>';
                        echo '<pre>' . esc_html(print_r($result['data'], true)) . '</pre>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="error">';
                    echo '<p><strong>失敗:</strong> ライセンス認証に失敗しました</p>';
                    echo '<p><strong>メッセージ:</strong> ' . esc_html($result['message']) . '</p>';
                    if (isset($result['error_code'])) {
                        echo '<p><strong>エラーコード:</strong> ' . esc_html($result['error_code']) . '</p>';
                    }
                    if (isset($result['debug_info'])) {
                        echo '<div class="debug-info">';
                        echo '<h5>デバッグ情報:</h5>';
                        echo '<pre>' . esc_html(print_r($result['debug_info'], true)) . '</pre>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
            
            echo '</div>';
            ?>
        <?php endif; ?>
        
        <div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border: 1px solid #0073aa; border-radius: 4px;">
            <h3>使用方法</h3>
            <ol>
                <li>上記フォームに有効なライセンスキーを入力してください</li>
                <li>「ライセンスをテスト」ボタンをクリックしてください</li>
                <li>結果を確認して、問題の原因を特定してください</li>
                <li>テスト完了後、このファイルを削除してください（セキュリティのため）</li>
            </ol>
            
            <h3>注意事項</h3>
            <ul>
                <li>本番環境では確実に本番APIエンドポイント（https://www.kantanpro.com）が使用されます</li>
                <li>開発環境のオーバーライド設定は本番環境では無視されます</li>
                <li>有効なライセンスキーが必要です</li>
            </ul>
        </div>
    </div>
</body>
</html>
