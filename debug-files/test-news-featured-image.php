<?php
// WordPress環境を読み込み
require_once '/var/www/html/wp-config.php';
require_once '/var/www/html/wp-content/plugins/news-crawler/news-crawler.php';

echo "=== ニュース投稿のアイキャッチ生成テスト ===\n";

// 基本設定を確認
$basic_settings = get_option('news_crawler_basic_settings', array());
echo "アイキャッチ自動生成: " . (isset($basic_settings['auto_featured_image']) && $basic_settings['auto_featured_image'] ? 'ON' : 'OFF') . "\n";
echo "アイキャッチ生成方法: " . ($basic_settings['featured_image_method'] ?? '未設定') . "\n";

// ジャンル設定を確認
$genre_settings = get_option('news_crawler_genre_settings', array());
echo "\nジャンル設定:\n";
foreach ($genre_settings as $genre_id => $setting) {
    if (isset($setting['content_type']) && $setting['content_type'] === 'news') {
        echo "ジャンル: " . ($setting['genre_name'] ?? $genre_id) . "\n";
        echo "  自動アイキャッチ: " . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? 'ON' : 'OFF') . "\n";
        echo "  アイキャッチ方法: " . ($setting['featured_image_method'] ?? '未設定') . "\n";
        echo "  ニュースソース数: " . (isset($setting['news_sources']) ? count($setting['news_sources']) : 0) . "\n";
        break;
    }
}

// 一時保存データをクリア（基本設定からの動作をテストするため）
delete_transient('news_crawler_current_genre_setting');
echo "\n一時保存データをクリアしました\n";

// NewsCrawlerのインスタンスを作成
$news_crawler = new NewsCrawler();

// テスト用のニュース記事データを作成
$test_articles = array(
    array(
        'title' => 'AI技術の最新動向について - テスト記事',
        'content' => 'AI技術の発展により、様々な分野で革新が起こっています。この記事では最新の動向について詳しく解説します。',
        'url' => 'https://example.com/test-article-1',
        'published_date' => date('Y-m-d H:i:s'),
        'source' => 'テストニュース',
        'image_url' => '',
        'quality_score' => 0.8
    )
);

// リフレクションを使ってprivateメソッドにアクセス
$reflection = new ReflectionClass($news_crawler);
$create_post_method = $reflection->getMethod('create_summary_post_with_categories');
$create_post_method->setAccessible(true);

echo "\n=== ニュース投稿作成 ===\n";
$post_id = $create_post_method->invoke(
    $news_crawler, 
    $test_articles, 
    array('テスト'), 
    'draft'
);

if ($post_id && !is_wp_error($post_id)) {
    echo "✓ ニュース投稿作成成功: ID $post_id\n";
    
    // 投稿情報を確認
    $post = get_post($post_id);
    echo "タイトル: {$post->post_title}\n";
    
    // アイキャッチを確認
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if ($thumbnail_id) {
        echo "✓ アイキャッチ設定成功: ID $thumbnail_id\n";
        $attachment_url = wp_get_attachment_url($thumbnail_id);
        echo "画像URL: $attachment_url\n";
    } else {
        echo "✗ アイキャッチが設定されていません\n";
        
        // エラーログを確認
        echo "\n最新のエラーログ（アイキャッチ関連）:\n";
        $log_file = '/var/www/html/wp-content/debug.log';
        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            $log_lines = explode("\n", $log_content);
            $relevant_lines = array();
            
            foreach (array_reverse($log_lines) as $line) {
                if (strpos($line, 'maybe_generate_featured_image') !== false || 
                    strpos($line, 'Featured Image Generator') !== false ||
                    strpos($line, 'NewsCrawler') !== false) {
                    $relevant_lines[] = $line;
                    if (count($relevant_lines) >= 10) break;
                }
            }
            
            foreach (array_reverse($relevant_lines) as $line) {
                echo $line . "\n";
            }
        }
    }
} else {
    $error_message = is_wp_error($post_id) ? $post_id->get_error_message() : '不明なエラー';
    echo "✗ ニュース投稿作成失敗: $error_message\n";
}

echo "\n=== テスト完了 ===\n";
?>