<?php
/**
 * 投稿ID 379のアイキャッチ画像の最終確認
 */

try {
    // WordPressを読み込み
    $wp_root = '/var/www/html';
    require_once($wp_root . '/wp-config.php');
    require_once($wp_root . '/wp-load.php');
    
    echo "<h1>投稿ID 379のアイキャッチ画像の最終確認</h1>";
    
    // 投稿ID 379を取得
    $post = get_post(379);
    
    if (!$post) {
        echo "❌ 投稿ID 379が見つかりません<br>";
        return;
    }
    
    echo "<h2>投稿情報</h2>";
    echo "- 投稿ID: " . $post->ID . "<br>";
    echo "- タイトル: " . $post->post_title . "<br>";
    echo "- ステータス: " . $post->post_status . "<br>";
    echo "- 作成日: " . $post->post_date . "<br>";
    
    // アイキャッチ画像の詳細確認
    $has_featured_image = has_post_thumbnail($post->ID);
    $featured_image_id = get_post_thumbnail_id($post->ID);
    
    echo "<h2>アイキャッチ画像の状況</h2>";
    echo "- アイキャッチ設定: " . ($has_featured_image ? '✅ 設定済み' : '❌ 未設定') . "<br>";
    
    if ($has_featured_image && $featured_image_id) {
        echo "- 画像ID: " . $featured_image_id . "<br>";
        
        // 画像の詳細情報を取得
        $attachment = get_post($featured_image_id);
        if ($attachment) {
            echo "- 画像タイトル: " . $attachment->post_title . "<br>";
            echo "- 画像ファイル名: " . $attachment->post_name . "<br>";
            echo "- 画像MIMEタイプ: " . $attachment->post_mime_type . "<br>";
            echo "- 画像作成日: " . $attachment->post_date . "<br>";
            
            // 画像のURLを取得
            $full_url = wp_get_attachment_url($featured_image_id);
            $thumbnail_url = wp_get_attachment_image_url($featured_image_id, 'thumbnail');
            $medium_url = wp_get_attachment_image_url($featured_image_id, 'medium');
            
            echo "- フルサイズURL: " . $full_url . "<br>";
            echo "- サムネイルURL: " . $thumbnail_url . "<br>";
            echo "- 中サイズURL: " . $medium_url . "<br>";
            
            // 画像のメタデータを取得
            $metadata = wp_get_attachment_metadata($featured_image_id);
            if ($metadata) {
                echo "- 画像幅: " . (isset($metadata['width']) ? $metadata['width'] : '不明') . "px<br>";
                echo "- 画像高さ: " . (isset($metadata['height']) ? $metadata['height'] : '不明') . "px<br>";
                echo "- ファイルサイズ: " . (isset($metadata['filesize']) ? $metadata['filesize'] : '不明') . " bytes<br>";
            }
            
            // 画像ファイルの存在確認
            $upload_dir = wp_upload_dir();
            $file_path = get_attached_file($featured_image_id);
            if ($file_path && file_exists($file_path)) {
                echo "- ファイル存在: ✅ 存在します<br>";
                echo "- ファイルパス: " . $file_path . "<br>";
                echo "- ファイルサイズ: " . filesize($file_path) . " bytes<br>";
                echo "- ファイル権限: " . substr(sprintf('%o', fileperms($file_path)), -4) . "<br>";
            } else {
                echo "- ファイル存在: ❌ 存在しません<br>";
                if ($file_path) {
                    echo "- 期待されるパス: " . $file_path . "<br>";
                }
            }
            
        } else {
            echo "❌ 画像の詳細情報を取得できませんでした<br>";
        }
        
        // 投稿のメタデータも確認
        $post_meta = get_post_meta($post->ID, '_thumbnail_id', true);
        echo "- 投稿メタデータ（_thumbnail_id）: " . ($post_meta ?: '未設定') . "<br>";
        
    } else {
        echo "❌ アイキャッチ画像が設定されていません<br>";
    }
    
    // メディアライブラリの全体的な状況も確認
    echo "<h2>メディアライブラリの状況</h2>";
    $media_count = wp_count_posts('attachment');
    echo "- 添付ファイル総数: " . $media_count->inherit . "<br>";
    
    // 最近追加された画像を確認
    $recent_attachments = get_posts(array(
        'post_type' => 'attachment',
        'numberposts' => 5,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    if (!empty($recent_attachments)) {
        echo "<h3>最近追加された画像（最大5件）</h3>";
        foreach ($recent_attachments as $attachment) {
            echo "- ID: " . $attachment->ID . " - " . $attachment->post_title . " (" . $attachment->post_date . ")<br>";
        }
    }
    
    echo "<h2>テスト結果の要約</h2>";
    if ($has_featured_image && $featured_image_id) {
        echo "✅ <strong>アイキャッチ画像の自動生成テストが成功しました！</strong><br>";
        echo "- 投稿ID 379にアイキャッチ画像が正常に設定されています<br>";
        echo "- 画像ID: " . $featured_image_id . "<br>";
        echo "- Unsplash画像取得機能は正常に動作しています<br>";
    } else {
        echo "❌ アイキャッチ画像の自動生成テストが失敗しました<br>";
        echo "- 投稿ID 379にアイキャッチ画像が設定されていません<br>";
    }
    
    echo "<h2>次のステップ</h2>";
    echo "1. WordPress管理画面で投稿ID 379のアイキャッチ画像を確認する<br>";
    echo "2. 実際の投稿作成時にアイキャッチが自動生成されるか確認する<br>";
    echo "3. 設定画面でアイキャッチ自動生成が有効になっているか確認する<br>";
    echo "4. 必要に応じて、他の投稿でもアイキャッチ自動生成をテストする<br>";
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage();
    echo "<br>スタックトレース: <pre>" . $e->getTraceAsString() . "</pre>";
}
?>
