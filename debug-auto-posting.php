<?php
/**
 * 自動投稿デバッグスクリプト
 * WordPress管理画面からアクセス可能
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
    <title>News Crawler 自動投稿デバッグ</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .error { color: red; }
        .success { color: green; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .button { background: #0073aa; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>News Crawler 自動投稿デバッグ</h1>
    <p>実行時刻: <?php echo date('Y-m-d H:i:s'); ?></p>
    
    <div class="debug-section">
        <h2>1. ジャンル設定の確認</h2>
        <?php
        $genre_settings = new NewsCrawlerGenreSettings();
        $settings = $genre_settings->get_genre_settings();
        
        echo "<p>登録されているジャンル数: " . count($settings) . "</p>";
        
        foreach ($settings as $genre_id => $setting) {
            echo "<h3>ジャンル: " . esc_html($setting['genre_name']) . " (ID: " . esc_html($genre_id) . ")</h3>";
            echo "<ul>";
            
            // 基本情報
            echo "<li>コンテンツタイプ: " . esc_html($setting['content_type']) . "</li>";
            echo "<li>自動投稿: " . (isset($setting['auto_posting']) && $setting['auto_posting'] ? '<span class="success">有効</span>' : '<span class="error">無効</span>') . "</li>";
            
            // キーワードの確認
            if (empty($setting['keywords'])) {
                echo "<li class='error'>❌ キーワードが設定されていません</li>";
            } else {
                echo "<li class='success'>✅ キーワード: " . esc_html(implode(', ', $setting['keywords'])) . "</li>";
            }
            
            // ニュースソースの確認
            if ($setting['content_type'] === 'news') {
                if (empty($setting['news_sources'])) {
                    echo "<li class='error'>❌ ニュースソースが設定されていません</li>";
                } else {
                    echo "<li class='success'>✅ ニュースソース: " . esc_html(implode(', ', $setting['news_sources'])) . "</li>";
                }
            }
            
            // YouTubeチャンネルの確認
            if ($setting['content_type'] === 'youtube') {
                if (empty($setting['youtube_channels'])) {
                    echo "<li class='error'>❌ YouTubeチャンネルが設定されていません</li>";
                } else {
                    echo "<li class='success'>✅ YouTubeチャンネル: " . esc_html(implode(', ', $setting['youtube_channels'])) . "</li>";
                }
            }
            
            // 投稿数制限の確認
            $max_posts = isset($setting['max_posts_per_execution']) ? intval($setting['max_posts_per_execution']) : 3;
            echo "<li>最大投稿数/実行: " . $max_posts . "</li>";
            
            // 最近の投稿数を確認
            $recent_posts = $genre_settings->count_recent_posts_by_genre($genre_id);
            echo "<li>最近24時間の投稿数: " . $recent_posts . "</li>";
            
            if ($recent_posts >= $max_posts) {
                echo "<li class='error'>❌ 24時間制限に達しています</li>";
            } else {
                echo "<li class='success'>✅ 24時間制限内です</li>";
            }
            
            // 候補数の確認
            $cache_key = 'news_crawler_available_count_' . $genre_id;
            $available_candidates = get_transient($cache_key);
            if ($available_candidates === false) {
                echo "<li class='warning'>⚠️ 候補数キャッシュがありません</li>";
            } else {
                echo "<li>候補数: " . $available_candidates . "</li>";
                if ($available_candidates <= 0) {
                    echo "<li class='error'>❌ 候補がありません</li>";
                } else {
                    echo "<li class='success'>✅ 候補があります</li>";
                }
            }
            
            // 次回実行時刻の確認
            $next_execution = $genre_settings->get_next_execution_time($setting, $genre_id);
            $current_time = current_time('timestamp');
            echo "<li>次回実行時刻: " . date('Y-m-d H:i:s', $next_execution) . "</li>";
            echo "<li>現在時刻: " . date('Y-m-d H:i:s', $current_time) . "</li>";
            
            if ($next_execution > $current_time) {
                echo "<li class='warning'>⚠️ まだ実行時刻ではありません</li>";
            } else {
                echo "<li class='success'>✅ 実行可能です</li>";
            }
            
            echo "</ul>";
        }
        ?>
    </div>
    
    <div class="debug-section">
        <h2>2. 強制実行テスト</h2>
        <?php
        if (isset($_POST['force_execute'])) {
            echo "<h3>強制実行結果:</h3>";
            $result = $genre_settings->force_auto_posting();
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        } else {
            echo '<form method="post">';
            echo '<input type="submit" name="force_execute" value="強制実行テスト" class="button">';
            echo '</form>';
        }
        ?>
    </div>
    
    <div class="debug-section">
        <h2>3. Cron設定の確認</h2>
        <?php
        $cron_settings = get_option('news_crawler_cron_settings', array());
        if (empty($cron_settings)) {
            echo "<p class='error'>Cron設定が見つかりません</p>";
        } else {
            echo "<h3>Cron設定:</h3>";
            echo "<pre>";
            print_r($cron_settings);
            echo "</pre>";
        }
        
        // スケジュールされたイベントの確認
        $scheduled_events = wp_get_scheduled_event('news_crawler_auto_posting_cron');
        if ($scheduled_events) {
            echo "<h3>スケジュールされたイベント:</h3>";
            echo "<p>次回実行: " . date('Y-m-d H:i:s', $scheduled_events->timestamp) . "</p>";
        } else {
            echo "<p class='warning'>スケジュールされたイベントが見つかりません</p>";
        }
        ?>
    </div>
    
    <div class="debug-section">
        <h2>4. ログの確認</h2>
        <p><a href="<?php echo admin_url('admin.php?page=news-crawler-cron-settings'); ?>" class="button">自動投稿設定ページへ</a></p>
        <p><a href="<?php echo admin_url('admin.php?page=news-crawler-genre-settings'); ?>" class="button">ジャンル設定ページへ</a></p>
    </div>
</body>
</html>
