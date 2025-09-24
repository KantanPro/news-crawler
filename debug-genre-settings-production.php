<?php
/**
 * ジャンル設定デバッグスクリプト（本番環境用）
 * なぜ自動投稿がスキップされているのかを詳細に調査
 */

// エラー表示を有効にする
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== ジャンル設定デバッグ開始 ===\n";
echo "開始時刻: " . date('Y-m-d H:i:s') . "\n\n";

// WordPressのパスを設定
$wp_path = '/virtual/kantan/public_html/';

// wp-load.phpを読み込み
if (file_exists($wp_path . 'wp-load.php')) {
    require_once($wp_path . 'wp-load.php');
    echo "WordPress読み込み完了\n";
} else {
    echo "エラー: wp-load.phpが見つかりません\n";
    exit(1);
}

// NewsCrawlerGenreSettingsクラスをチェック
if (class_exists('NewsCrawlerGenreSettings')) {
    $genre_settings = new NewsCrawlerGenreSettings();
    echo "NewsCrawlerGenreSettingsクラス: 利用可能\n\n";
    
    // 全ジャンル設定を取得
    $all_genre_settings = $genre_settings->get_all_genre_settings();
    echo "全ジャンル設定数: " . count($all_genre_settings) . "\n\n";
    
    foreach ($all_genre_settings as $genre_id => $setting) {
        echo "=== ジャンル: " . $setting['genre_name'] . " (ID: $genre_id) ===\n";
        
        // 基本設定チェック
        echo "自動投稿有効: " . (isset($setting['auto_posting']) && $setting['auto_posting'] ? 'YES' : 'NO') . "\n";
        echo "コンテンツタイプ: " . (isset($setting['content_type']) ? $setting['content_type'] : '未設定') . "\n";
        echo "キーワード数: " . (isset($setting['keywords']) ? count($setting['keywords']) : 0) . "\n";
        
        if (isset($setting['keywords']) && !empty($setting['keywords'])) {
            echo "キーワード: " . implode(', ', array_slice($setting['keywords'], 0, 3)) . (count($setting['keywords']) > 3 ? '...' : '') . "\n";
        }
        
        // ニュースソースチェック
        if (isset($setting['content_type']) && $setting['content_type'] === 'news') {
            echo "ニュースソース数: " . (isset($setting['news_sources']) ? count($setting['news_sources']) : 0) . "\n";
            if (isset($setting['news_sources']) && !empty($setting['news_sources'])) {
                echo "ニュースソース: " . implode(', ', array_slice($setting['news_sources'], 0, 3)) . (count($setting['news_sources']) > 3 ? '...' : '') . "\n";
            }
        }
        
        // YouTubeチャンネルチェック
        if (isset($setting['content_type']) && $setting['content_type'] === 'youtube') {
            echo "YouTubeチャンネル数: " . (isset($setting['youtube_channels']) ? count($setting['youtube_channels']) : 0) . "\n";
            if (isset($setting['youtube_channels']) && !empty($setting['youtube_channels'])) {
                echo "YouTubeチャンネル: " . implode(', ', array_slice($setting['youtube_channels'], 0, 3)) . (count($setting['youtube_channels']) > 3 ? '...' : '') . "\n";
            }
        }
        
        // 投稿制限チェック
        if (isset($setting['daily_post_limit']) && $setting['daily_post_limit'] > 0) {
            $today_posts = $genre_settings->count_today_posts($genre_id);
            echo "今日の投稿数: $today_posts / " . $setting['daily_post_limit'] . "\n";
        }
        
        // 候補数チェック
        $cache_key = 'news_crawler_available_count_' . $genre_id;
        $available_candidates = get_transient($cache_key);
        echo "候補数キャッシュ: " . ($available_candidates === false ? 'false' : $available_candidates) . "\n";
        
        // 次回実行時刻チェック
        $next_execution = $genre_settings->get_next_execution_time($setting, $genre_id);
        $current_time = time();
        echo "次回実行時刻: " . date('Y-m-d H:i:s', $next_execution) . "\n";
        echo "現在時刻: " . date('Y-m-d H:i:s', $current_time) . "\n";
        echo "実行可能: " . ($next_execution <= $current_time ? 'YES' : 'NO') . "\n";
        
        // スキップ理由を特定
        $skip_reasons = [];
        
        if (!isset($setting['auto_posting']) || !$setting['auto_posting']) {
            $skip_reasons[] = '自動投稿が無効';
        }
        
        if (empty($setting['keywords'])) {
            $skip_reasons[] = 'キーワードが未設定';
        }
        
        if (isset($setting['content_type']) && $setting['content_type'] === 'news' && empty($setting['news_sources'])) {
            $skip_reasons[] = 'ニュースソースが未設定';
        }
        
        if (isset($setting['content_type']) && $setting['content_type'] === 'youtube' && empty($setting['youtube_channels'])) {
            $skip_reasons[] = 'YouTubeチャンネルが未設定';
        }
        
        if (isset($setting['daily_post_limit']) && $setting['daily_post_limit'] > 0) {
            $today_posts = $genre_settings->count_today_posts($genre_id);
            if ($today_posts >= $setting['daily_post_limit']) {
                $skip_reasons[] = '日次投稿制限に達している';
            }
        }
        
        if ($available_candidates === false || $available_candidates <= 0) {
            $skip_reasons[] = '候補がありません';
        }
        
        if ($next_execution > $current_time) {
            $skip_reasons[] = '実行時刻が来ていない';
        }
        
        if (empty($skip_reasons)) {
            echo "✅ 実行可能\n";
        } else {
            echo "❌ スキップ理由: " . implode(', ', $skip_reasons) . "\n";
        }
        
        echo "\n";
    }
    
    // 自動投稿を強制実行してテスト
    echo "=== 強制自動投稿テスト ===\n";
    $result = $genre_settings->execute_auto_posting();
    echo "実行結果: " . print_r($result, true) . "\n";
    
} else {
    echo "エラー: NewsCrawlerGenreSettingsクラスが見つかりません\n";
}

echo "\n=== デバッグ完了 ===\n";
echo "終了時刻: " . date('Y-m-d H:i:s') . "\n";
?>
