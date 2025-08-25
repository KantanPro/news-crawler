<?php
/**
 * 文字化け対策テスト
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

// アイキャッチ生成クラスの読み込み
require_once('../includes/class-featured-image-generator.php');

echo "<h2>文字化け対策テスト</h2>";

// テスト用の設定を保存（文字化けしにくい設定）
$test_settings = array(
    'template_width' => 1200,
    'template_height' => 630,
    'bg_color1' => '#4F46E5',
    'bg_color2' => '#7C3AED', 
    'text_color' => '#FFFFFF',
    'font_size' => 48,
    'text_scale' => 4  // 4倍に拡大
);

update_option('news_crawler_basic_settings', $test_settings);
echo "<p>✓ テスト設定を保存しました（文字拡大倍率: 4倍）</p>";

// アイキャッチ生成クラスのインスタンス作成
$generator = new NewsCrawlerFeaturedImageGenerator();

// 文字化けテスト用のデータ
$test_cases = array(
    array(
        'title' => '政治ニュース：自民党の新政策について',
        'keywords' => array('自民党', '新政策'),
        'description' => '政治ジャンル + 自民党キーワード'
    ),
    array(
        'title' => '経済情報：市場予測レポート',
        'keywords' => array('市場', '予測'),
        'description' => '経済ジャンル + 市場キーワード'
    ),
    array(
        'title' => 'テクノロジー：AI技術の進歩',
        'keywords' => array('AI', '技術'),
        'description' => 'テックジャンル + AI技術キーワード'
    ),
    array(
        'title' => 'スポーツ：プロ野球開幕戦',
        'keywords' => array('プロ野球', '開幕'),
        'description' => 'スポーツジャンル + プロ野球キーワード'
    )
);

echo "<h3>ローマ字変換テスト</h3>";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>日本語</th><th>ローマ字変換結果</th><th>文字化けチェック</th></tr>";

// リフレクションでプライベートメソッドにアクセス
$reflection = new ReflectionClass($generator);
$romaji_method = $reflection->getMethod('convert_japanese_to_clean_romaji');
$romaji_method->setAccessible(true);

$title_method = $reflection->getMethod('create_japanese_title');
$title_method->setAccessible(true);

foreach ($test_cases as $test) {
    $japanese_title = $title_method->invoke($generator, $test['title'], $test['keywords']);
    $romaji_result = $romaji_method->invoke($generator, $japanese_title);
    
    // 文字化けチェック（ASCII文字のみかどうか）
    $is_safe = preg_match('/^[\x00-\x7F]*$/', $romaji_result);
    $safety_status = $is_safe ? '✓ 安全' : '❌ 危険';
    
    echo "<tr>";
    echo "<td>" . htmlspecialchars($japanese_title) . "</td>";
    echo "<td>" . htmlspecialchars($romaji_result) . "</td>";
    echo "<td>" . $safety_status . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>アイキャッチ画像生成テスト</h3>";

foreach ($test_cases as $index => $test) {
    echo "<h4>テスト " . ($index + 1) . ": " . htmlspecialchars($test['description']) . "</h4>";
    echo "<p>元タイトル: " . htmlspecialchars($test['title']) . "</p>";
    echo "<p>キーワード: " . implode('、', $test['keywords']) . "</p>";
    
    // 仮の投稿IDを使用
    $test_post_id = 6666 + $index;
    
    try {
        $result = $generator->generate_and_set_featured_image(
            $test_post_id,
            $test['title'],
            $test['keywords'],
            'template'
        );
        
        if ($result) {
            echo "<p>✓ アイキャッチ画像生成成功 (添付ファイルID: {$result})</p>";
            
            // 生成された画像のURLを取得
            $image_url = wp_get_attachment_url($result);
            if ($image_url) {
                echo "<p>画像URL: <a href='{$image_url}' target='_blank'>{$image_url}</a></p>";
                echo "<img src='{$image_url}' style='max-width: 400px; border: 1px solid #ccc; margin: 10px 0;' />";
                
                // 期待されるタイトルを表示
                $japanese_title = $title_method->invoke($generator, $test['title'], $test['keywords']);
                $romaji_title = $romaji_method->invoke($generator, $japanese_title);
                echo "<p><strong>日本語タイトル:</strong> {$japanese_title}</p>";
                echo "<p><strong>表示されるローマ字:</strong> {$romaji_title}</p>";
            }
        } else {
            echo "<p>❌ アイキャッチ画像生成失敗</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "<hr>";
}

// フォント確認
echo "<h3>フォント環境確認</h3>";
$font_method = $reflection->getMethod('get_japanese_font_path');
$font_method->setAccessible(true);
$font_path = $font_method->invoke($generator);

if ($font_path) {
    echo "<p>✓ 日本語フォントが見つかりました: " . htmlspecialchars($font_path) . "</p>";
    
    // フォントテスト
    if (function_exists('imagettftext')) {
        $test_font_method = $reflection->getMethod('test_japanese_font');
        $test_font_method->setAccessible(true);
        $font_works = $test_font_method->invoke($generator, $font_path);
        
        if ($font_works) {
            echo "<p>✓ 日本語フォントは正常に動作します</p>";
        } else {
            echo "<p>⚠ 日本語フォントに問題があります。ローマ字フォールバックを使用します。</p>";
        }
    } else {
        echo "<p>⚠ imagettftext関数が利用できません。ローマ字フォールバックを使用します。</p>";
    }
} else {
    echo "<p>⚠ 日本語フォントが見つかりません。ローマ字フォールバックを使用します。</p>";
}

echo "<h3>文字化け対策の説明</h3>";
echo "<ul>";
echo "<li><strong>日本語フォント利用可能:</strong> 日本語を直接描画</li>";
echo "<li><strong>日本語フォント利用不可:</strong> 日本語をローマ字に変換して描画</li>";
echo "<li><strong>変換例:</strong> 「政治自民党ニュースまとめ」→「SEIJI JIMINTO NEWS MATOME」</li>";
echo "<li><strong>安全性:</strong> ASCII文字のみ使用で文字化けを完全防止</li>";
echo "</ul>";

echo "<p><strong>テスト完了</strong></p>";
echo "<p>生成された画像で文字化けが発生していないか確認してください。</p>";
?>