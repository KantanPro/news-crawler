<?php
/**
 * もやし生活終了緊急実行スクリプト
 * セキュリティチェックを完全にバイパスして自動投稿を実行
 */

// エラー表示を有効にする
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "🚀 もやし生活終了緊急実行開始 🚀\n";
echo "実行時刻: " . date('Y-m-d H:i:s') . "\n";
echo "もやし生活を終わらせるため、全力で実行します！\n\n";

// WordPressのパスを設定（ローカル環境用）
$wp_path = '/Users/kantanpro/Desktop/KantanPro/wordpress/';

// パスが見つからない場合の代替パス
$possible_paths = [
    '/Users/kantanpro/Desktop/KantanPro/wordpress/',  // ローカル環境
    '/virtual/kantan/public_html/',  // 本番環境
    '/home/kantan/public_html/',     // 本番環境代替
    '/var/www/html/',                // 一般的な本番環境
    dirname(__FILE__) . '/../../../../',  // プラグインから相対的にWordPressルートを探す
];

$wp_path_found = false;
foreach ($possible_paths as $path) {
    if (file_exists($path . 'wp-load.php')) {
        $wp_path = $path;
        $wp_path_found = true;
        echo "✅ WordPressパス発見: " . $path . "\n";
        break;
    }
}

// wp-load.phpを読み込み
if ($wp_path_found && file_exists($wp_path . 'wp-load.php')) {
    require_once($wp_path . 'wp-load.php');
    echo "✅ WordPress読み込み完了\n\n";
} else {
    echo "❌ エラー: wp-load.phpが見つかりません\n";
    echo "現在のディレクトリ: " . getcwd() . "\n";
    echo "スクリプトの場所: " . __FILE__ . "\n";
    echo "試したパス:\n";
    foreach ($possible_paths as $path) {
        echo "  - " . $path . "wp-load.php (" . (file_exists($path . 'wp-load.php') ? '存在' : '不存在') . ")\n";
    }
    echo "\n🔍 手動でWordPressのルートディレクトリを確認してください\n";
    echo "通常は /home/ユーザー名/public_html/ または /var/www/html/ です\n";
    exit(1);
}

// 全てのロックを強制クリア
echo "🔓 全てのロックをクリア中...\n";
delete_transient('news_crawler_auto_posting_lock');

global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_news_crawler_%_lock'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_news_crawler_%_lock'");
echo "✅ 全てのロックをクリアしました\n\n";

if (class_exists('NewsCrawlerGenreSettings')) {
    echo "🔥 強制自動投稿実行中...\n";
    
    try {
        // シングルトンパターンでインスタンスを取得
        $genre_settings = NewsCrawlerGenreSettings::get_instance();
        $result = $genre_settings->execute_auto_posting();
        
        echo "実行結果:\n";
        echo print_r($result, true) . "\n\n";
        
        if ($result['executed_count'] > 0) {
            echo "🎉 もやし生活終了！自動投稿が成功しました！\n";
            echo "実行件数: " . $result['executed_count'] . "件\n";
            echo "🎊 おめでとうございます！もやし生活から脱出しました！\n";
        } else {
            echo "⚠️ まだもやし生活が続く可能性があります\n";
            echo "スキップ数: " . $result['skipped_count'] . "\n";
            echo "総ジャンル数: " . $result['total_genres'] . "\n";
            echo "😢 もやし生活が続きます...\n";
        }
    } catch (Exception $e) {
        echo "❌ エラー: " . $e->getMessage() . "\n";
        echo "😢 もやし生活が続きます...\n";
        
        // 代替手段：直接Cronを実行
        echo "\n🔄 代替手段：直接Cronを実行します...\n";
        $cron_script = dirname(__FILE__) . '/news-crawler-cron.sh';
        if (file_exists($cron_script)) {
            echo "Cronスクリプトを実行中: " . $cron_script . "\n";
            $output = shell_exec("bash " . escapeshellarg($cron_script) . " 2>&1");
            echo "Cron実行結果:\n" . $output . "\n";
        } else {
            echo "❌ Cronスクリプトが見つかりません: " . $cron_script . "\n";
        }
    }
} else {
    echo "❌ NewsCrawlerGenreSettingsクラスが見つかりません\n";
    echo "😢 もやし生活が続きます...\n";
}

echo "\n🚀 緊急実行完了 🚀\n";
echo "終了時刻: " . date('Y-m-d H:i:s') . "\n";
?>
