<?php
/**
 * 投稿ID 334の詳細確認スクリプト
 */

// WordPressを読み込み
$wp_root = dirname(dirname(dirname(__DIR__)));
require_once($wp_root . '/wp-config.php');
require_once($wp_root . '/wp-load.php');

$post_id = 344; // 最新の投稿IDに変更

echo "<h2>投稿ID {$post_id} の詳細情報</h2>";

// 投稿の基本情報
$post = get_post($post_id);
if ($post) {
    echo "<h3>投稿情報</h3>";
    echo "タイトル: " . $post->post_title . "<br>";
    echo "ステータス: " . $post->post_status . "<br>";
    echo "作成日: " . $post->post_date . "<br>";
    
    // アイキャッチの確認
    $thumbnail_id = get_post_thumbnail_id($post_id);
    echo "<h3>アイキャッチ情報</h3>";
    if ($thumbnail_id) {
        echo "アイキャッチID: " . $thumbnail_id . "<br>";
        $thumbnail_url = wp_get_attachment_url($thumbnail_id);
        echo "アイキャッチURL: " . $thumbnail_url . "<br>";
        echo "<img src='{$thumbnail_url}' style='max-width: 300px;'><br>";
    } else {
        echo "アイキャッチなし<br>";
    }
    
    // メタデータの確認
    echo "<h3>投稿メタデータ</h3>";
    $meta_data = get_post_meta($post_id);
    echo "<pre>";
    print_r($meta_data);
    echo "</pre>";
    
} else {
    echo "投稿ID {$post_id} が見つかりません。";
}

// 現在のジャンル設定を確認
echo "<h3>現在のジャンル設定（一時保存）</h3>";
$current_genre = get_transient('news_crawler_current_genre_setting');
if ($current_genre) {
    echo "<pre>";
    print_r($current_genre);
    echo "</pre>";
} else {
    echo "一時保存されたジャンル設定がありません。<br>";
}

// 基本設定の確認
echo "<h3>基本設定</h3>";
$basic_settings = get_option('news_crawler_basic_settings', array());
echo "OpenAI APIキー: " . (isset($basic_settings['openai_api_key']) && !empty($basic_settings['openai_api_key']) ? '設定済み' : '未設定') . "<br>";

// ジャンル設定の確認
echo "<h3>ジャンル設定</h3>";
$genre_settings = get_option('news_crawler_genre_settings', array());
foreach ($genre_settings as $id => $setting) {
    echo "ジャンルID: {$id}<br>";
    echo "ジャンル名: " . $setting['genre_name'] . "<br>";
    echo "アイキャッチ自動生成: " . (isset($setting['auto_featured_image']) && $setting['auto_featured_image'] ? '有効' : '無効') . "<br>";
    if (isset($setting['featured_image_method'])) {
        echo "生成方法: " . $setting['featured_image_method'] . "<br>";
    }
    echo "<br>";
}

// 手動でアイキャッチ生成をテスト
echo "<h3>手動アイキャッチ生成テスト</h3>";
if (class_exists('NewsCrawlerFeaturedImageGenerator')) {
    $generator = new NewsCrawlerFeaturedImageGenerator();
    
    // テスト用の設定を一時保存
    $test_setting = array(
        'auto_featured_image' => 1,
        'featured_image_method' => 'template'
    );
    set_transient('news_crawler_current_genre_setting', $test_setting, 300);
    
    echo "テンプレート生成をテスト中...<br>";
    $result = $generator->generate_and_set_featured_image($post_id, $post->post_title, array('政治', '経済', 'ニュース'), 'template');
    
    if ($result) {
        echo "テンプレート生成成功！添付ファイルID: " . $result . "<br>";
        $thumbnail_url = wp_get_attachment_url($result);
        echo "<img src='{$thumbnail_url}' style='max-width: 300px;'><br>";
    } else {
        echo "テンプレート生成失敗<br>";
    }
} else {
    echo "NewsCrawlerFeaturedImageGeneratorクラスが見つかりません。<br>";
}
?>