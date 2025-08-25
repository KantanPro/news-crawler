<?php
/**
 * 日本語フォント専用テスト
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

// アイキャッチ生成クラスの読み込み
require_once('../includes/class-featured-image-generator.php');

echo "<h2>日本語フォント専用テスト</h2>";

// テスト用の設定を保存
$test_settings = array(
    'template_width' => 1200,
    'template_height' => 630,
    'bg_color1' => '#4F46E5',
    'bg_color2' => '#7C3AED', 
    'text_color' => '#FFFFFF',
    'font_size' => 48,
    'text_scale' => 3
);

update_option('news_crawler_basic_settings', $test_settings);
echo "<p>✓ テスト設定を保存しました</p>";

// アイキャッチ生成クラスのインスタンス作成
$generator = new NewsCrawlerFeaturedImageGenerator();

// リフレクションでプライベートメソッドにアクセス
$reflection = new ReflectionClass($generator);

// フォント環境の確認
echo "<h3>フォント環境確認</h3>";

$font_method = $reflection->getMethod('get_japanese_font_path');
$font_method->setAccessible(true);
$font_path = $font_method->invoke($generator);

if ($font_path) {
    echo "<p>✓ 日本語フォントが見つかりました</p>";
    echo "<p><strong>フォントパス:</strong> " . htmlspecialchars($font_path) . "</p>";
    
    // フォントファイルの詳細情報
    if (file_exists($font_path)) {
        $file_size = filesize($font_path);
        $file_size_mb = round($file_size / 1024 / 1024, 2);
        echo "<p><strong>ファイルサイズ:</strong> {$file_size_mb} MB</p>";
        
        // フォントテスト
        if (function_exists('imagettftext')) {
            echo "<p>✓ imagettftext関数が利用可能です</p>";
            
            $test_font_method = $reflection->getMethod('test_japanese_font');
            $test_font_method->setAccessible(true);
            $font_works = $test_font_method->invoke($generator, $font_path);
            
            if ($font_works) {
                echo "<p>✓ 日本語フォントテスト成功</p>";
            } else {
                echo "<p>❌ 日本語フォントテスト失敗</p>";
            }
        } else {
            echo "<p>❌ imagettftext関数が利用できません</p>";
        }
    } else {
        echo "<p>❌ フォントファイルが存在しません</p>";
    }
} else {
    echo "<p>❌ 日本語フォントが見つかりません</p>";
    echo "<h4>日本語フォントのインストール方法</h4>";
    echo "<ol>";
    echo "<li>Noto Sans JPフォントをダウンロード: <a href='https://fonts.google.com/noto/specimen/Noto+Sans+JP' target='_blank'>Google Fonts</a></li>";
    echo "<li>ダウンロードしたTTFファイルを <code>assets/fonts/</code> フォルダに配置</li>";
    echo "<li>ファイル名を <code>NotoSansJP-Regular.ttf</code> にリネーム</li>";
    echo "</ol>";
}

// 日本語フォントが利用可能な場合のみテスト実行
if ($font_path && function_exists('imagettftext')) {
    echo "<h3>日本語アイキャッチ生成テスト</h3>";
    
    $test_cases = array(
        array(
            'title' => '政治ニュース：自民党の新政策について',
            'keywords' => array('自民党', '新政策'),
            'description' => '政治ジャンル'
        ),
        array(
            'title' => '経済情報：市場予測レポート',
            'keywords' => array('市場', '予測'),
            'description' => '経済ジャンル'
        ),
        array(
            'title' => 'テクノロジー：AI技術の進歩',
            'keywords' => array('AI', '技術'),
            'description' => 'テックジャンル'
        )
    );
    
    $title_method = $reflection->getMethod('create_japanese_title');
    $title_method->setAccessible(true);
    
    foreach ($test_cases as $index => $test) {
        echo "<h4>テスト " . ($index + 1) . ": " . htmlspecialchars($test['description']) . "</h4>";
        
        // 生成される日本語タイトルを表示
        $japanese_title = $title_method->invoke($generator, $test['title'], $test['keywords']);
        echo "<p><strong>生成される日本語タイトル:</strong> " . htmlspecialchars($japanese_title) . "</p>";
        
        // 仮の投稿IDを使用
        $test_post_id = 5555 + $index;
        
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
                    echo "<img src='{$image_url}' style='max-width: 500px; border: 1px solid #ccc; margin: 10px 0;' />";
                }
            } else {
                echo "<p>❌ アイキャッチ画像生成失敗</p>";
            }
            
        } catch (Exception $e) {
            echo "<p>❌ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
        echo "<hr>";
    }
} else {
    echo "<h3>日本語フォントが利用できません</h3>";
    echo "<p>日本語フォントをインストールしてから再度テストしてください。</p>";
}

echo "<h3>システム情報</h3>";
echo "<ul>";
echo "<li><strong>PHP GD拡張:</strong> " . (extension_loaded('gd') ? '✓ 利用可能' : '❌ 利用不可') . "</li>";
echo "<li><strong>imagettftext関数:</strong> " . (function_exists('imagettftext') ? '✓ 利用可能' : '❌ 利用不可') . "</li>";
echo "<li><strong>OS:</strong> " . PHP_OS . "</li>";
echo "<li><strong>PHP バージョン:</strong> " . PHP_VERSION . "</li>";
echo "</ul>";

echo "<p><strong>テスト完了</strong></p>";
?>