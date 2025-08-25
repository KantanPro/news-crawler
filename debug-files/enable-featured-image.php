<?php
/**
 * アイキャッチ生成を強制的に有効にするスクリプト
 */

// WordPressを読み込み（プラグインディレクトリからWordPressルートへ）
$wp_root = dirname(dirname(dirname(__DIR__)));
require_once($wp_root . '/wp-config.php');
require_once($wp_root . '/wp-load.php');

echo "<h1>アイキャッチ生成設定の強制有効化</h1>";

// 現在のジャンル設定を取得
$genre_settings = get_option('news_crawler_genre_settings', array());

if (empty($genre_settings)) {
    echo "❌ ジャンル設定が見つかりません。まずジャンル設定を作成してください。<br>";
    exit;
}

echo "<h2>現在のジャンル設定:</h2>";
foreach ($genre_settings as $id => $setting) {
    echo "<strong>{$setting['genre_name']}</strong><br>";
    echo "- アイキャッチ自動生成: " . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? '有効' : '無効') . "<br>";
    if (isset($setting['featured_image_method'])) {
        echo "- 生成方法: {$setting['featured_image_method']}<br>";
    }
    echo "<br>";
}

// 政治・経済ジャンルを探して有効化
$updated = false;
foreach ($genre_settings as $id => &$setting) {
    if ($setting['genre_name'] === '政治・経済') {
        echo "<h2>政治・経済ジャンルの設定を更新中...</h2>";
        
        $setting['auto_featured_image'] = 1;
        $setting['featured_image_method'] = 'template';
        
        echo "✅ アイキャッチ自動生成: 有効に設定<br>";
        echo "✅ 生成方法: テンプレート生成に設定<br>";
        
        $updated = true;
        break;
    }
}

if ($updated) {
    // 設定を保存
    $result = update_option('news_crawler_genre_settings', $genre_settings);
    
    if ($result) {
        echo "✅ <strong>設定の保存に成功しました！</strong><br>";
        
        // 確認のため再取得
        $updated_settings = get_option('news_crawler_genre_settings', array());
        foreach ($updated_settings as $id => $setting) {
            if ($setting['genre_name'] === '政治・経済') {
                echo "<h3>更新後の設定確認:</h3>";
                echo "- アイキャッチ自動生成: " . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? '✅ 有効' : '❌ 無効') . "<br>";
                echo "- 生成方法: " . (isset($setting['featured_image_method']) ? $setting['featured_image_method'] : '未設定') . "<br>";
                break;
            }
        }
        
        // テスト用の一時保存も実行
        echo "<h3>テスト用一時保存の実行:</h3>";
        $test_setting = array(
            'auto_featured_image' => 1,
            'featured_image_method' => 'template',
            'genre_name' => '政治・経済'
        );
        
        $transient_result = set_transient('news_crawler_current_genre_setting', $test_setting, 300);
        echo "一時保存結果: " . ($transient_result ? '✅ 成功' : '❌ 失敗') . "<br>";
        
        // 確認
        $saved_setting = get_transient('news_crawler_current_genre_setting');
        echo "一時保存確認: " . ($saved_setting ? '✅ 存在' : '❌ 見つからない') . "<br>";
        
        if ($saved_setting) {
            echo "<pre>";
            print_r($saved_setting);
            echo "</pre>";
        }
        
    } else {
        echo "❌ <strong>設定の保存に失敗しました</strong><br>";
    }
} else {
    echo "❌ 政治・経済ジャンルが見つかりませんでした<br>";
}

echo "<h2>次のステップ</h2>";
echo "<p>1. このスクリプトで設定が正常に更新された場合</p>";
echo "<p>2. WordPress管理画面のジャンル設定で「投稿を作成」ボタンをクリック</p>";
echo "<p>3. 新しい投稿にアイキャッチが生成されるかを確認</p>";

echo "<h2>手動テスト</h2>";
echo "<p>設定が正しく保存された場合、以下で手動テストを実行できます:</p>";
echo "<p><a href='docker-debug.php' target='_blank'>Docker デバッグスクリプトを実行</a></p>";
?>