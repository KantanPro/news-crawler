<?php
/**
 * 自動投稿デバッグテストスクリプト
 * 本番環境での自動投稿問題を調査する
 */

// WordPressのパスを設定
$wp_path = '/Users/kantanpro/Desktop/KantanPro/wordpress/';

// wp-load.phpを読み込み
if (file_exists($wp_path . 'wp-load.php')) {
    require_once($wp_path . 'wp-load.php');
    echo "WordPress読み込み完了\n";
} else {
    echo "エラー: wp-load.phpが見つかりません\n";
    exit(1);
}

// WordPress関数が利用可能かチェック
if (function_exists('get_option')) {
    echo "WordPress関数: 利用可能\n";
    echo "サイトURL: " . get_option('siteurl') . "\n";
} else {
    echo "エラー: WordPress関数が利用できません\n";
    exit(1);
}

// NewsCrawlerGenreSettingsクラスをチェック
if (class_exists('NewsCrawlerGenreSettings')) {
    echo "NewsCrawlerGenreSettingsクラス: 見つかりました\n";
    
    $genre_settings = new NewsCrawlerGenreSettings();
    echo "インスタンス取得成功\n";
    
    // ジャンル設定を取得
    $all_genre_settings = $genre_settings->get_all_genre_settings();
    echo "総ジャンル数: " . count($all_genre_settings) . "\n";
    
    // 各ジャンルの設定を詳細チェック
    foreach ($all_genre_settings as $genre_id => $setting) {
        echo "\n=== ジャンル: " . $setting['genre_name'] . " (ID: " . $genre_id . ") ===\n";
        echo "自動投稿有効: " . (isset($setting['auto_posting']) && $setting['auto_posting'] ? 'Yes' : 'No') . "\n";
        echo "キーワード数: " . (isset($setting['keywords']) ? count($setting['keywords']) : 0) . "\n";
        echo "コンテンツタイプ: " . (isset($setting['content_type']) ? $setting['content_type'] : 'N/A') . "\n";
        
        if (isset($setting['content_type'])) {
            if ($setting['content_type'] === 'news') {
                echo "ニュースソース数: " . (isset($setting['news_sources']) ? count($setting['news_sources']) : 0) . "\n";
            } elseif ($setting['content_type'] === 'youtube') {
                echo "YouTubeチャンネル数: " . (isset($setting['youtube_channels']) ? count($setting['youtube_channels']) : 0) . "\n";
            }
        }
        
        // 候補数キャッシュをチェック
        $cache_key = 'news_crawler_available_count_' . $genre_id;
        $available_candidates = get_transient($cache_key);
        echo "候補数キャッシュ: " . ($available_candidates === false ? 'false' : $available_candidates) . "\n";
        
        // 次回実行時刻をチェック
        if (method_exists($genre_settings, 'get_next_execution_time')) {
            $next_execution = $genre_settings->get_next_execution_time($setting, $genre_id);
            echo "次回実行時刻: " . date('Y-m-d H:i:s', $next_execution) . "\n";
            echo "現在時刻: " . date('Y-m-d H:i:s', time()) . "\n";
            echo "実行可能: " . ($next_execution <= time() ? 'Yes' : 'No') . "\n";
        }
    }
    
    // 自動投稿を実行
    echo "\n=== 自動投稿実行テスト ===\n";
    $result = $genre_settings->execute_auto_posting();
    echo "実行結果: " . print_r($result, true) . "\n";
    
} else {
    echo "エラー: NewsCrawlerGenreSettingsクラスが見つかりません\n";
    exit(1);
}

echo "\nデバッグテスト完了\n";
?>
