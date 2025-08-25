<?php
/**
 * Docker環境でのネットワーク接続テスト
 * このファイルをWordPressのルートディレクトリに配置して実行してください
 */

echo "<h1>Docker環境 ネットワーク接続テスト</h1>";

// 1. 基本的な接続テスト
echo "<h2>1. 基本的な接続テスト</h2>";

// Google APIへの接続テスト
echo "<h3>Google API接続テスト</h3>";
$google_response = wp_remote_get('https://www.googleapis.com/discovery/v1/apis', array(
    'timeout' => 60,
    'sslverify' => false,
    'httpversion' => '1.1'
));

if (is_wp_error($google_response)) {
    echo "<p style='color: red;'>❌ Google API接続失敗: " . $google_response->get_error_message() . "</p>";
    echo "<p>エラーコード: " . $google_response->get_error_code() . "</p>";
} else {
    echo "<p style='color: green;'>✅ Google API接続成功: HTTP " . wp_remote_retrieve_response_code($google_response) . "</p>";
}

// 2. Docker環境情報
echo "<h2>2. Docker環境情報</h2>";

// ホスト名
echo "<p><strong>ホスト名:</strong> " . gethostname() . "</p>";

// IPアドレス
echo "<p><strong>IPアドレス:</strong> " . $_SERVER['SERVER_ADDR'] ?? '不明' . "</p>";

// ポート
echo "<p><strong>ポート:</strong> " . $_SERVER['SERVER_PORT'] ?? '不明' . "</p>";

// 3. ネットワーク設定
echo "<h2>3. ネットワーク設定</h2>";

// DNS設定
echo "<h3>DNS設定</h3>";
$dns_servers = file_get_contents('/etc/resolv.conf');
if ($dns_servers) {
    echo "<pre>" . htmlspecialchars($dns_servers) . "</pre>";
} else {
    echo "<p>DNS設定ファイルが読み取れません</p>";
}

// 4. 環境変数
echo "<h2>4. 環境変数</h2>";
echo "<h3>Docker関連の環境変数</h3>";
$docker_envs = array_filter($_ENV, function($key) {
    return strpos($key, 'DOCKER') !== false || strpos($key, 'COMPOSE') !== false;
}, ARRAY_FILTER_USE_KEY);

if (!empty($docker_envs)) {
    echo "<ul>";
    foreach ($docker_envs as $key => $value) {
        echo "<li><strong>$key:</strong> $value</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Docker関連の環境変数が見つかりません</p>";
}

// 5. ファイルシステム情報
echo "<h2>5. ファイルシステム情報</h2>";
echo "<p><strong>現在のディレクトリ:</strong> " . getcwd() . "</p>";
echo "<p><strong>WordPressルート:</strong> " . ABSPATH . "</p>";

// 6. 推奨事項
echo "<h2>6. 推奨事項</h2>";
echo "<h3>Docker環境での設定</h3>";
echo "<ol>";
echo "<li><strong>docker-compose.ymlの確認:</strong> ネットワーク設定とポートマッピングを確認</li>";
echo "<li><strong>extra_hostsの追加:</strong> host.docker.internal:host-gateway を設定</li>";
echo "<li><strong>ネットワークドライバ:</strong> bridge または host を使用</li>";
echo "<li><strong>ファイアウォール設定:</strong> コンテナからの外部接続を許可</li>";
echo "</ol>";

echo "<h3>即座に試せる対処法</h3>";
echo "<ol>";
echo "<li><strong>ニュース記事モード:</strong> 外部接続なしでキーワードマッチングをテスト</li>";
echo "<li><strong>ローカルファイル:</strong> コンテナ内のファイルを使用</li>";
echo "<li><strong>Dockerネットワーク:</strong> ホストネットワークモードでの実行</li>";
echo "</ol>";
?>