<?php
/**
 * News Crawler設定画面の場所確認
 */

// WordPressの読み込み
require_once('../../../wp-config.php');

echo "<h2>News Crawler設定画面の場所確認</h2>";

// 管理画面のメニュー構造を確認
echo "<h3>設定画面へのアクセス方法</h3>";
echo "<ol>";
echo "<li>WordPress管理画面にログイン</li>";
echo "<li>左側メニューの「News Crawler」をクリック</li>";
echo "<li>「基本設定」タブをクリック</li>";
echo "<li>「アイキャッチ自動生成設定」セクションを確認</li>";
echo "<li>「テンプレート設定」で文字サイズを調整</li>";
echo "</ol>";

// 現在の設定値を表示
echo "<h3>現在の基本設定</h3>";
$basic_settings = get_option('news_crawler_basic_settings', array());
echo "<pre>";
print_r($basic_settings);
echo "</pre>";

// 文字拡大倍率の説明
echo "<h3>文字拡大倍率の効果</h3>";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>倍率</th><th>効果</th><th>推奨用途</th></tr>";
echo "<tr><td>2倍</td><td>控えめな拡大</td><td>シンプルなデザイン</td></tr>";
echo "<tr><td>3倍</td><td>バランスの良い拡大</td><td>一般的な用途（推奨）</td></tr>";
echo "<tr><td>4倍</td><td>大きく見やすい</td><td>視認性重視</td></tr>";
echo "<tr><td>5倍</td><td>最大拡大</td><td>非常に大きな文字が必要な場合</td></tr>";
echo "</table>";

// 設定のテスト
echo "<h3>設定テスト</h3>";
if (isset($basic_settings['text_scale'])) {
    echo "<p>✓ 文字拡大倍率が設定されています: " . $basic_settings['text_scale'] . "倍</p>";
} else {
    echo "<p>⚠ 文字拡大倍率が未設定です。デフォルト値（3倍）が使用されます。</p>";
}

if (isset($basic_settings['font_size'])) {
    echo "<p>✓ フォントサイズが設定されています: " . $basic_settings['font_size'] . "px</p>";
} else {
    echo "<p>⚠ フォントサイズが未設定です。デフォルト値（48px）が使用されます。</p>";
}

// 設定更新のリンク
$admin_url = admin_url('admin.php?page=news-crawler-basic');
echo "<h3>設定を変更する</h3>";
echo "<p><a href='{$admin_url}' target='_blank'>News Crawler基本設定画面を開く</a></p>";

echo "<h3>設定変更後の確認方法</h3>";
echo "<ol>";
echo "<li>設定を変更して「変更を保存」をクリック</li>";
echo "<li><a href='test-large-font-featured-image.php'>アイキャッチ生成テスト</a>を実行</li>";
echo "<li>生成された画像の文字サイズを確認</li>";
echo "</ol>";
?>