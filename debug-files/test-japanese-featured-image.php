<?php
/**
 * 日本語アイキャッチ画像生成テスト
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

// アイキャッチ生成クラスの読み込み
require_once('../includes/class-featured-image-generator.php');

echo "<h2>日本語アイキャッチ画像生成テスト</h2>";

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

update_option('news_crawler_basic_settings', $test_settings);
echo "<p>✓ テスト設定を保存しました（文字拡大倍率: 4倍）</p>";

// アイキャッチ生成クラスのインスタンス作成
$generator = new NewsCrawlerFeaturedImageGenerator();

// テスト用の投稿データ（ジャンル + キーワード形式）
$test_posts = array(
    array(
        'title' => '政治ニュース：自民党の新政策について',
        'keywords' => array('自民党', '新政策')
    ),
    array(
        'title' => '経済情報：2025年の市場予測',
        'keywords' => array('市場', '予測')
    ),
    array(
        'title' => 'テクノロジー：AI技術の最新動向',
        'keywords' => array('AI', '技術')
    ),
    array(
        'title' => 'スポーツニュース：プロ野球開幕戦',
        'keywords' => array('プロ野球', '開幕')
    ),
    array(
        'title' => '社会ニュース：地域活性化の取り組み',
        'keywords' => array('地域', '活性化')
    ),
    array(
        'title' => '国際ニュース：外交政策の変更',
        'keywords' => array('外交', '政策')
    )
);

foreach ($test_posts as $index => $post_data) {
    echo "<h3>テスト " . ($index + 1) . ": " . htmlspecialchars($post_data['title']) . "</h3>";
    echo "<p>キーワード: " . implode('、', $post_data['keywords']) . "</p>";
    
    // 仮の投稿IDを使用
    $test_post_id = 7777 + $index;
    
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
                echo "<img src='{$image_url}' style='max-width: 400px; border: 1px solid #ccc; margin: 10px 0;' />";
                
                // 期待されるタイトル形式を表示（ジャンル + キーワード + ニュースまとめ + 日付）
                // ジャンル抽出のテスト
                $reflection = new ReflectionClass($generator);
                $genre_method = $reflection->getMethod('extract_genre_from_title');
                $genre_method->setAccessible(true);
                $genre = $genre_method->invoke($generator, $post_data['title'], $post_data['keywords']);
                
                $keyword_part = implode('・', array_slice($post_data['keywords'], 0, 2));
                $expected_title = $genre . $keyword_part . 'ニュースまとめ ' . date_i18n('n月j日');
                echo "<p><strong>期待されるタイトル:</strong> {$expected_title}</p>";
                echo "<p><strong>抽出されたジャンル:</strong> {$genre}</p>";
            }
        } else {
            echo "<p>❌ アイキャッチ画像生成失敗</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}

// タイトル生成のテスト
echo "<h3>タイトル生成テスト</h3>";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>タイトル / キーワード</th><th>生成されるタイトル</th></tr>";

$title_tests = array(
    array('title' => '政治ニュース', 'keywords' => array('自民党')),
    array('title' => '経済情報', 'keywords' => array('市場')),
    array('title' => 'テクノロジー', 'keywords' => array('AI', '技術')),
    array('title' => 'スポーツ', 'keywords' => array('プロ野球')),
    array('title' => '社会問題', 'keywords' => array('地域', '活性化')),
    array('title' => '芸能ニュース', 'keywords' => array('映画')),
    array('title' => '国際情勢', 'keywords' => array('外交'))
);

// テスト用のメソッドを直接呼び出すためのリフレクション
$reflection = new ReflectionClass($generator);
$method = $reflection->getMethod('create_japanese_title');
$method->setAccessible(true);

foreach ($title_tests as $test) {
    $generated_title = $method->invoke($generator, $test['title'], $test['keywords']);
    echo "<tr>";
    echo "<td>" . htmlspecialchars($test['title']) . " / " . implode('、', $test['keywords']) . "</td>";
    echo "<td>" . htmlspecialchars($generated_title) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>ローマ字変換テスト</h3>";
$romaji_method = $reflection->getMethod('convert_japanese_to_romaji');
$romaji_method->setAccessible(true);

$romaji_tests = array(
    '政治ニュースまとめ',
    '経済ニュースまとめ',
    'テックニュースまとめ',
    'スポーツニュースまとめ',
    '社会・地域ニュースまとめ'
);

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>日本語</th><th>ローマ字変換</th></tr>";

foreach ($romaji_tests as $japanese) {
    $romaji = $romaji_method->invoke($generator, $japanese);
    echo "<tr>";
    echo "<td>" . htmlspecialchars($japanese) . "</td>";
    echo "<td>" . htmlspecialchars($romaji) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<p><strong>テスト完了</strong></p>";
echo "<p>生成された画像で日本語タイトルが正しく表示されているか確認してください。</p>";
?>