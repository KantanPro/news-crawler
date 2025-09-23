<?php
/**
 * 自動投稿修正のテストスクリプト
 * 修正後の動作を確認する
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    // WordPress環境を読み込み
    require_once('../../../wp-load.php');
}

// プラグインのクラスを読み込み
require_once('includes/class-genre-settings.php');

// 管理者権限チェック
if (!current_user_can('manage_options')) {
    wp_die('このページにアクセスする権限がありません。');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>News Crawler 自動投稿修正テスト</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .button { background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; margin: 5px; }
        .button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>News Crawler 自動投稿修正テスト</h1>
    <p>実行時刻: <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <div class="test-section">
        <h2>1. 修正内容の確認</h2>
        <ul>
            <li class="success">✅ WordPress Cronの自動設定を無効化</li>
            <li class="success">✅ 重複実行防止ロック時間を5分に短縮</li>
            <li class="success">✅ サーバーCronのみを使用するように変更</li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>2. 現在のCron設定確認</h2>
        <?php
        // WordPress Cronの確認
        $wp_cron_events = wp_get_scheduled_event('news_crawler_auto_posting_cron');
        if ($wp_cron_events) {
            echo "<p class='warning'>⚠️ WordPress Cronが設定されています: " . date('Y-m-d H:i:s', $wp_cron_events->timestamp) . "</p>";
        } else {
            echo "<p class='success'>✅ WordPress Cronは設定されていません（正常）</p>";
        }
        
        // サーバーCronの確認
        $cron_settings = get_option('news_crawler_cron_settings', array());
        if (!empty($cron_settings)) {
            echo "<p class='info'>ℹ️ サーバーCron設定: " . json_encode($cron_settings) . "</p>";
        } else {
            echo "<p class='warning'>⚠️ サーバーCron設定が見つかりません</p>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>3. ジャンル設定の確認</h2>
        <?php
        $genre_settings = new NewsCrawlerGenreSettings();
        $settings = $genre_settings->get_genre_settings();
        
        echo "<p>登録されているジャンル数: " . count($settings) . "</p>";
        
        $enabled_genres = 0;
        foreach ($settings as $genre_id => $setting) {
            if (isset($setting['auto_posting']) && $setting['auto_posting']) {
                $enabled_genres++;
                echo "<p class='success'>✅ " . esc_html($setting['genre_name']) . " - 自動投稿有効</p>";
            } else {
                echo "<p class='warning'>⚠️ " . esc_html($setting['genre_name']) . " - 自動投稿無効</p>";
            }
        }
        
        echo "<p class='info'>ℹ️ 有効なジャンル数: " . $enabled_genres . "</p>";
        ?>
    </div>
    
    <div class="test-section">
        <h2>4. ロック状態の確認</h2>
        <?php
        $lock_key = 'news_crawler_auto_posting_lock';
        $existing_lock = get_transient($lock_key);
        
        if ($existing_lock !== false) {
            echo "<p class='warning'>⚠️ 実行中ロックが設定されています: " . esc_html($existing_lock) . "</p>";
            echo "<form method='post' style='display:inline;'>";
            echo "<input type='submit' name='clear_lock' value='ロックをクリア' class='button'>";
            echo "</form>";
        } else {
            echo "<p class='success'>✅ ロックは設定されていません（正常）</p>";
        }
        
        if (isset($_POST['clear_lock'])) {
            delete_transient($lock_key);
            echo "<p class='success'>✅ ロックをクリアしました</p>";
            echo "<script>setTimeout(function(){ location.reload(); }, 1000);</script>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>5. 強制実行テスト</h2>
        <?php
        if (isset($_POST['test_execution'])) {
            echo "<h3>強制実行結果:</h3>";
            
            // ロックをクリアしてから実行
            delete_transient('news_crawler_auto_posting_lock');
            
            $result = $genre_settings->force_auto_posting();
            echo "<pre>";
            print_r($result);
            echo "</pre>";
            
            if ($result['executed_count'] > 0) {
                echo "<p class='success'>✅ 投稿が正常に実行されました</p>";
            } else {
                echo "<p class='warning'>⚠️ 投稿が実行されませんでした</p>";
                
                // 詳細なデバッグ情報を表示
                echo "<h4>デバッグ情報:</h4>";
                $settings = $genre_settings->get_genre_settings();
                foreach ($settings as $genre_id => $setting) {
                    if (isset($setting['auto_posting']) && $setting['auto_posting']) {
                        echo "<h5>ジャンル: " . esc_html($setting['genre_name']) . "</h5>";
                        
                        // pre_execution_checkの結果を確認
                        $check_result = $genre_settings->pre_execution_check($setting, $genre_id, true);
                        echo "<p>実行チェック結果: " . ($check_result['can_execute'] ? '✅ 実行可能' : '❌ 実行不可 - ' . $check_result['reason']) . "</p>";
                        
                        // 候補数を確認
                        $cache_key = 'news_crawler_available_count_' . $genre_id;
                        $available_candidates = get_transient($cache_key);
                        echo "<p>候補数: " . ($available_candidates === false ? 'キャッシュなし' : $available_candidates) . "</p>";
                    }
                }
            }
        } else {
            echo '<form method="post">';
            echo '<input type="submit" name="test_execution" value="強制実行テスト" class="button">';
            echo '<input type="submit" name="update_candidates" value="候補数を更新" class="button">';
            echo '</form>';
        }
        
        if (isset($_POST['update_candidates'])) {
            echo "<h3>候補数更新結果:</h3>";
            
            $settings = $genre_settings->get_genre_settings();
            foreach ($settings as $genre_id => $setting) {
                if (isset($setting['auto_posting']) && $setting['auto_posting']) {
                    echo "<h4>ジャンル: " . esc_html($setting['genre_name']) . "</h4>";
                    
                    // 候補数を再計算
                    if ($setting['content_type'] === 'news') {
                        // ニュースの候補数を計算
                        $candidates = $genre_settings->count_available_news_candidates($genre_id);
                    } else if ($setting['content_type'] === 'youtube') {
                        // YouTubeの候補数を計算
                        $candidates = $genre_settings->count_available_youtube_candidates($genre_id);
                    } else {
                        $candidates = 0;
                    }
                    
                    // キャッシュを更新
                    $cache_key = 'news_crawler_available_count_' . $genre_id;
                    set_transient($cache_key, $candidates, 3600); // 1時間キャッシュ
                    
                    echo "<p>候補数: " . $candidates . " (キャッシュ更新済み)</p>";
                }
            }
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>6. ログの確認</h2>
        <p>最新のログを確認してください：</p>
        <ul>
            <li><a href="<?php echo admin_url('admin.php?page=news-crawler-cron-settings'); ?>" class="button">Cron設定ページ</a></li>
            <li><a href="<?php echo admin_url('admin.php?page=news-crawler-genre-settings'); ?>" class="button">ジャンル設定ページ</a></li>
            <li><a href="<?php echo admin_url('admin.php?page=news-crawler-settings'); ?>" class="button">メイン設定ページ</a></li>
        </ul>
    </div>
    
    <div class="test-section">
        <h2>7. 次のステップ</h2>
        <ol>
            <li>サーバーCronが正しく設定されているか確認</li>
            <li>ジャンル設定で自動投稿が有効になっているか確認</li>
            <li>キーワード、ニュースソース、YouTubeチャンネルが設定されているか確認</li>
            <li>強制実行テストで投稿が作成されるか確認</li>
            <li>本番環境でサーバーCronの実行を監視</li>
        </ol>
    </div>
</body>
</html>
