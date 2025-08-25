<?php
/**
 * WordPress統合部分の確認（WordPress不要）
 */

echo "=== WordPress統合部分の確認 ===\n\n";

// 1. メインファイルの確認
echo "1. メインファイルの確認\n";
$main_file = __DIR__ . '/news-crawler.php';
if (file_exists($main_file)) {
    echo "✅ メインファイル: 存在\n";
    
    $content = file_get_contents($main_file);
    
    // maybe_generate_featured_imageメソッドの確認
    if (strpos($content, 'maybe_generate_featured_image') !== false) {
        echo "✅ maybe_generate_featured_imageメソッド: 存在\n";
        
        // デバッグログの確認
        if (strpos($content, "error_log('NewsCrawler: maybe_generate_featured_image called") !== false) {
            echo "✅ デバッグログ: 追加済み\n";
        } else {
            echo "❌ デバッグログ: 未追加\n";
        }
    } else {
        echo "❌ maybe_generate_featured_imageメソッド: 見つかりません\n";
    }
    
    // クラスのインクルード確認
    if (strpos($content, 'NewsCrawlerFeaturedImageGenerator') !== false) {
        echo "✅ アイキャッチ生成クラスの参照: 存在\n";
    } else {
        echo "❌ アイキャッチ生成クラスの参照: 見つかりません\n";
    }
} else {
    echo "❌ メインファイル: 見つかりません\n";
}

echo "\n2. ジャンル設定クラスの確認\n";
$genre_file = __DIR__ . '/includes/class-genre-settings.php';
if (file_exists($genre_file)) {
    echo "✅ ジャンル設定ファイル: 存在\n";
    
    $content = file_get_contents($genre_file);
    
    // 一時保存の確認
    if (strpos($content, "set_transient('news_crawler_current_genre_setting'") !== false) {
        echo "✅ 一時保存処理: 存在\n";
    } else {
        echo "❌ 一時保存処理: 見つかりません\n";
    }
    
    // デバッグログの確認（ニュース）
    if (strpos($content, "Genre Settings - News: Saving current setting") !== false) {
        echo "✅ ニュース用デバッグログ: 追加済み\n";
    } else {
        echo "❌ ニュース用デバッグログ: 未追加\n";
    }
    
    // デバッグログの確認（YouTube）
    if (strpos($content, "Genre Settings - YouTube: Saving current setting") !== false) {
        echo "✅ YouTube用デバッグログ: 追加済み\n";
    } else {
        echo "❌ YouTube用デバッグログ: 未追加\n";
    }
} else {
    echo "❌ ジャンル設定ファイル: 見つかりません\n";
}

echo "\n3. アイキャッチ生成クラスの詳細確認\n";
$generator_file = __DIR__ . '/includes/class-featured-image-generator.php';
if (file_exists($generator_file)) {
    echo "✅ アイキャッチ生成ファイル: 存在\n";
    
    $content = file_get_contents($generator_file);
    
    // 主要メソッドの確認
    $methods = array(
        'generate_and_set_featured_image' => 'メイン生成メソッド',
        'generate_template_image' => 'テンプレート生成',
        'save_image_as_attachment' => '画像保存',
        'maybe_generate_featured_image' => 'WordPress統合'
    );
    
    foreach ($methods as $method => $description) {
        if (strpos($content, "function {$method}") !== false || strpos($content, "{$method}(") !== false) {
            echo "✅ {$description}: 存在\n";
        } else {
            echo "❌ {$description}: 見つかりません\n";
        }
    }
    
    // デバッグログの確認
    $debug_patterns = array(
        "Featured Image Generator: Starting generation" => "開始ログ",
        "Featured Image Generator - Template: Starting" => "テンプレート開始ログ",
        "Featured Image Generator - Save: Starting" => "保存開始ログ"
    );
    
    foreach ($debug_patterns as $pattern => $description) {
        if (strpos($content, $pattern) !== false) {
            echo "✅ {$description}: 追加済み\n";
        } else {
            echo "❌ {$description}: 未追加\n";
        }
    }
} else {
    echo "❌ アイキャッチ生成ファイル: 見つかりません\n";
}

echo "\n4. 呼び出しフローの確認\n";
echo "期待される呼び出しフロー:\n";
echo "1. ジャンル設定 → 投稿作成ボタンクリック\n";
echo "2. ジャンル設定クラス → 一時保存 + ニュースクロール実行\n";
echo "3. ニュースクローラー → 投稿作成後にmaybe_generate_featured_image呼び出し\n";
echo "4. maybe_generate_featured_image → 一時保存された設定を確認\n";
echo "5. アイキャッチ生成クラス → 画像生成・保存・設定\n";

echo "\n5. 潜在的な問題の確認\n";

// ファイル権限の確認
$upload_test_dir = __DIR__;
if (is_writable($upload_test_dir)) {
    echo "✅ ディレクトリ書き込み権限: OK\n";
} else {
    echo "❌ ディレクトリ書き込み権限: NG\n";
}

// PHPメモリ制限
$memory_limit = ini_get('memory_limit');
echo "✅ PHPメモリ制限: {$memory_limit}\n";

// 実行時間制限
$max_execution_time = ini_get('max_execution_time');
echo "✅ 最大実行時間: {$max_execution_time}秒\n";

echo "\n=== 確認完了 ===\n";
echo "WordPressが動作している環境で以下を確認してください:\n";
echo "1. ジャンル設定でアイキャッチ生成が有効になっているか\n";
echo "2. 投稿作成時にデバッグログが出力されているか\n";
echo "3. 一時保存された設定が正しく取得できているか\n";
?>