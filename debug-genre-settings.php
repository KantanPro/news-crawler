<?php
/**
 * ジャンル設定のデバッグスクリプト
 * 自動投稿がスキップされる理由を特定する
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// プラグインのクラスを読み込み
require_once('includes/class-genre-settings.php');

echo "=== News Crawler ジャンル設定デバッグ ===\n";
echo "実行時刻: " . date('Y-m-d H:i:s') . "\n\n";

// ジャンル設定クラスのインスタンスを作成
$genre_settings = new NewsCrawlerGenreSettings();

// ジャンル設定を取得
$settings = $genre_settings->get_genre_settings();

echo "登録されているジャンル数: " . count($settings) . "\n\n";

foreach ($settings as $genre_id => $setting) {
    echo "--- ジャンル: " . $setting['genre_name'] . " (ID: " . $genre_id . ") ---\n";
    
    // 基本情報
    echo "コンテンツタイプ: " . $setting['content_type'] . "\n";
    echo "自動投稿: " . (isset($setting['auto_posting']) && $setting['auto_posting'] ? '有効' : '無効') . "\n";
    
    // キーワードの確認
    if (empty($setting['keywords'])) {
        echo "❌ キーワードが設定されていません\n";
    } else {
        echo "✅ キーワード: " . implode(', ', $setting['keywords']) . "\n";
    }
    
    // ニュースソースの確認
    if ($setting['content_type'] === 'news') {
        if (empty($setting['news_sources'])) {
            echo "❌ ニュースソースが設定されていません\n";
        } else {
            echo "✅ ニュースソース: " . implode(', ', $setting['news_sources']) . "\n";
        }
    }
    
    // YouTubeチャンネルの確認
    if ($setting['content_type'] === 'youtube') {
        if (empty($setting['youtube_channels'])) {
            echo "❌ YouTubeチャンネルが設定されていません\n";
        } else {
            echo "✅ YouTubeチャンネル: " . implode(', ', $setting['youtube_channels']) . "\n";
        }
    }
    
    // 投稿数制限の確認
    $max_posts = isset($setting['max_posts_per_execution']) ? intval($setting['max_posts_per_execution']) : 3;
    echo "最大投稿数/実行: " . $max_posts . "\n";
    
    // 最近の投稿数を確認
    $recent_posts = $genre_settings->count_recent_posts_by_genre($genre_id);
    echo "最近24時間の投稿数: " . $recent_posts . "\n";
    
    if ($recent_posts >= $max_posts) {
        echo "❌ 24時間制限に達しています\n";
    } else {
        echo "✅ 24時間制限内です\n";
    }
    
    // 候補数の確認
    $cache_key = 'news_crawler_available_count_' . $genre_id;
    $available_candidates = get_transient($cache_key);
    if ($available_candidates === false) {
        echo "❌ 候補数キャッシュがありません\n";
    } else {
        echo "候補数: " . $available_candidates . "\n";
        if ($available_candidates <= 0) {
            echo "❌ 候補がありません\n";
        } else {
            echo "✅ 候補があります\n";
        }
    }
    
    // 次回実行時刻の確認
    $next_execution = $genre_settings->get_next_execution_time($setting, $genre_id);
    $current_time = current_time('timestamp');
    echo "次回実行時刻: " . date('Y-m-d H:i:s', $next_execution) . "\n";
    echo "現在時刻: " . date('Y-m-d H:i:s', $current_time) . "\n";
    
    if ($next_execution > $current_time) {
        echo "❌ まだ実行時刻ではありません\n";
    } else {
        echo "✅ 実行可能です\n";
    }
    
    echo "\n";
}

// 強制実行のテスト
echo "=== 強制実行テスト ===\n";
$result = $genre_settings->force_auto_posting();
echo "強制実行結果:\n";
print_r($result);

echo "\n=== デバッグ完了 ===\n";
?>
