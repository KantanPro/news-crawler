<?php
/**
 * 大きな文字サイズでのアイキャッチ画像生成テスト
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

// アイキャッチ生成クラスの読み込み
require_once('../includes/class-featured-image-generator.php');

echo "<h2>大きな文字サイズでのアイキャッチ画像生成テスト</h2>";

// テスト用の設定を保存
$test_settings = array(
    'template_width' => 1200,
    'template_height' => 630,
    'bg_color1' => '#4F46E5',
    'bg_color2' => '#7C3AED', 
    'text_color' => '#FFFFFF',
    'font_size' => 72,
    'text_scale' => 4  // 4倍に拡大
);

update_option('news_crawler_featured_image_settings', $test_settings);
echo "<p>✓ テスト設定を保存しました（文字拡大倍率: 4倍）</p>";

// アイキャッチ生成クラスのインスタンス作成
$generator = new NewsCrawlerFeaturedImageGenerator();

// テスト用の投稿データ
$test_posts = array(
    array(
        'title' => '政治ニュース：自民党の新政策について',
        'keywords' => array('政治', '自民党', 'ニュース')
    ),
    array(
        'title' => '経済情報：2025年の市場予測',
        'keywords' => array('経済', '市場', '予測')
    ),
    array(
        'title' => 'テクノロジー：AI技術の最新動向',
        'keywords' => array('AI', 'テクノロジー', '技術')
    )
);

foreach ($test_posts as $index => $post_data) {
    echo "<h3>テスト " . ($index + 1) . ": " . htmlspecialchars($post_data['title']) . "</h3>";
    
    // 仮の投稿IDを使用
    $test_post_id = 9999 + $index;
    
    try {
        $result = $generator->generate_and_set_featured_image(
            $test_post_id,
            $post_data['title'],
            $post_data['keywords'],
            'template'
        );
        
        if ($result) {
            echo "<p>✓ アイキャッチ画像生成成功 (添付ファイルID: {$result})</p>";
            
            // 生成された画像のURLを取得
            $image_url = wp_get_attachment_url($result);
            if ($image_url) {
                echo "<p>画像URL: <a href='{$image_url}' target='_blank'>{$image_url}</a></p>";
                echo "<img src='{$image_url}' style='max-width: 400px; border: 1px solid #ccc;' />";
            }
        } else {
            echo "<p>❌ アイキャッチ画像生成失敗</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}

// 設定の確認
echo "<h3>現在の設定</h3>";
$current_settings = get_option('news_crawler_featured_image_settings', array());
echo "<pre>";
print_r($current_settings);
echo "</pre>";

// 異なる拡大倍率でのテスト
echo "<h3>拡大倍率比較テスト</h3>";
$scale_tests = array(2, 3, 4, 5);

foreach ($scale_tests as $scale) {
    echo "<h4>拡大倍率: {$scale}倍</h4>";
    
    // 設定を更新
    $test_settings['text_scale'] = $scale;
    update_option('news_crawler_featured_image_settings', $test_settings);
    
    $test_post_id = 8888 + $scale;
    $result = $generator->generate_and_set_featured_image(
        $test_post_id,
        "拡大倍率テスト {$scale}倍",
        array('テスト', 'フォント'),
        'template'
    );
    
    if ($result) {
        $image_url = wp_get_attachment_url($result);
        if ($image_url) {
            echo "<img src='{$image_url}' style='max-width: 300px; border: 1px solid #ccc; margin: 5px;' />";
        }
    }
}

echo "<p><strong>テスト完了</strong></p>";
echo "<p>各画像の文字サイズを比較して、最適な拡大倍率を選択してください。</p>";
?>