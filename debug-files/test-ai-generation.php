<?php
/**
 * AI画像生成のテストスクリプト
 */

try {
    // WordPressを読み込み
    $wp_root = dirname(dirname(dirname(__DIR__)));
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>AI画像生成テスト</h1>";
    
    // OpenAI APIキーの確認
    echo "<h2>OpenAI APIキーの確認</h2>";
    $basic_settings = get_option('news_crawler_basic_settings', array());
    $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
    
    if (!empty($api_key)) {
        echo "✅ OpenAI APIキー: 設定済み<br>";
        echo "キーの最初の部分: " . substr($api_key, 0, 10) . "...<br>";
    } else {
        echo "❌ OpenAI APIキー: 未設定<br>";
        exit;
    }
    
    // 投稿ID 348の情報
    $post = get_post(348);
    if (!$post) {
        echo "❌ 投稿ID 348が見つかりません<br>";
        exit;
    }
    
    echo "<h2>テスト対象投稿</h2>";
    echo "ID: 348<br>";
    echo "タイトル: " . esc_html($post->post_title) . "<br>";
    
    // AI生成をテスト
    if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
        echo "<h2>AI画像生成テスト</h2>";
        
        // テスト用設定を保存
        $test_setting = array(
            'auto_featured_image' => 1,
            'featured_image_method' => 'ai'
        );
        set_transient('news_crawler_current_genre_setting', $test_setting, 300);
        
        $generator = new NewsCrawlerFeaturedImageGenerator();
        
        echo "<strong>AI画像生成を実行中...</strong><br>";
        echo "（OpenAI APIの応答を待っています。30-60秒かかる場合があります）<br><br>";
        
        // タイムアウトを延長
        set_time_limit(120);
        
        $result = $generator->generate_and_set_featured_image(
            348, 
            $post->post_title, 
            array('politics', 'economy', 'news'), 
            'ai'
        );
        
        if ($result) {
            echo "✅ <strong>AI画像生成成功！</strong><br>";
            echo "添付ファイルID: " . $result . "<br>";
            $thumbnail_url = wp_get_attachment_url($result);
            echo "画像URL: " . $thumbnail_url . "<br>";
            echo "<h3>生成されたAI画像:</h3>";
            echo "<img src='{$thumbnail_url}' style='max-width: 600px; border: 2px solid #333; margin: 10px 0;'><br>";
            
            // 投稿のアイキャッチ設定を確認
            $new_thumbnail_id = get_post_thumbnail_id(348);
            echo "投稿のアイキャッチID: " . ($new_thumbnail_id ? $new_thumbnail_id : 'なし') . "<br>";
            
        } else {
            echo "❌ <strong>AI画像生成失敗</strong><br>";
            echo "<h3>考えられる原因:</h3>";
            echo "<ul>";
            echo "<li>OpenAI APIキーが無効</li>";
            echo "<li>APIクォータ（使用量制限）に達している</li>";
            echo "<li>ネットワーク接続の問題</li>";
            echo "<li>OpenAI APIサーバーの問題</li>";
            echo "<li>プロンプトがOpenAIのポリシーに違反している</li>";
            echo "</ul>";
        }
        
        // テンプレート生成もテスト（比較用）
        echo "<h2>比較: テンプレート生成テスト</h2>";
        
        // 既存のアイキャッチを削除
        if ($result) {
            delete_post_thumbnail(348);
            wp_delete_attachment($result, true);
        }
        
        $template_result = $generator->generate_and_set_featured_image(
            348, 
            $post->post_title, 
            array('politics', 'economy', 'news'), 
            'template'
        );
        
        if ($template_result) {
            echo "✅ <strong>テンプレート生成成功！</strong><br>";
            echo "添付ファイルID: " . $template_result . "<br>";
            $template_thumbnail_url = wp_get_attachment_url($template_result);
            echo "<h3>テンプレート画像:</h3>";
            echo "<img src='{$template_thumbnail_url}' style='max-width: 600px; border: 2px solid #333; margin: 10px 0;'><br>";
        } else {
            echo "❌ テンプレート生成も失敗<br>";
        }
        
    } else {
        echo "❌ NewsCrawlerFeaturedImageGeneratorクラスが見つかりません<br>";
    }
    
    echo "<h2>推奨事項</h2>";
    if ($result) {
        echo "<p>✅ AI生成が正常に動作しています。</p>";
        echo "<p>投稿作成時にアイキャッチが生成されない場合は、投稿作成フローの問題です。</p>";
    } else {
        echo "<p>❌ AI生成に問題があります。</p>";
        echo "<p><strong>対策:</strong></p>";
        echo "<ol>";
        echo "<li>一時的にテンプレート生成に変更する</li>";
        echo "<li>OpenAI APIキーを再確認する</li>";
        echo "<li>OpenAIアカウントの使用量制限を確認する</li>";
        echo "</ol>";
    }
    
} catch (Exception $e) {
    echo "<h1>エラー</h1>";
    echo "メッセージ: " . $e->getMessage() . "<br>";
    echo "ファイル: " . $e->getFile() . "<br>";
    echo "行: " . $e->getLine() . "<br>";
}
?>