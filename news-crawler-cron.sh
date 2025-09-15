#!/bin/bash
# News Crawler Cron Script
# 修正版 - 2025-09-15 09:00:00 (本番環境デバッグ強化版 + 次回実行時刻修正)

set -euo pipefail

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# WordPressのパスを動的に取得（プラグインディレクトリから逆算）
WP_PATH="$(dirname "$(dirname "$(dirname "$SCRIPT_DIR")")")/"

# WordPressのパスが正しいかチェック（wp-config.phpの存在確認）
if [ ! -f "$WP_PATH/wp-config.php" ]; then
    # 代替パスを試行（新しいパスを優先）
    for alt_path in "/virtual/kantan/public_html/" "/var/www/html/" "$(dirname "$SCRIPT_DIR")/../../"; do
        if [ -f "$alt_path/wp-config.php" ]; then
            WP_PATH="$alt_path"
            break
        fi
    done
fi

# プラグインパスを設定
PLUGIN_PATH="$SCRIPT_DIR/"

# ログファイルのパス
LOG_FILE="$SCRIPT_DIR/news-crawler-cron.log"

# ログに実行開始を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行開始" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] スクリプトディレクトリ: $SCRIPT_DIR" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] WordPressパス: $WP_PATH" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] プラグインパス: $PLUGIN_PATH" >> "$LOG_FILE"

# 事前に使用するPHPコマンドを検出（全パス共通で使用）
PHP_CMD=""
PHP_SAPI=""
detect_php_cli() {
    for php_path in "/usr/bin/php" "/usr/local/bin/php" "/opt/homebrew/bin/php" "$(command -v php || true)"; do
        if [ -n "$php_path" ] && [ -x "$php_path" ]; then
            sapi="$($php_path -r 'echo php_sapi_name();' 2>/dev/null || true)"
            if [ "$sapi" = "cli" ]; then
                PHP_CMD="$php_path"
                PHP_SAPI="$sapi"
                return 0
            fi
        fi
    done
    # 最後の手段: 実行可能なものを採用（SAPIは不明）
    for php_path in "/usr/bin/php" "/usr/local/bin/php" "/opt/homebrew/bin/php" "$(command -v php || true)"; do
        if [ -n "$php_path" ] && [ -x "$php_path" ]; then
            PHP_CMD="$php_path"
            PHP_SAPI="$($php_path -r 'echo php_sapi_name();' 2>/dev/null || true)"
            return 0
        fi
    done
    return 1
}

detect_php_cli || true

# Docker環境チェック（Mac開発環境用）
if command -v docker &> /dev/null && docker ps --format "{{.Names}}" | grep -q "KantanPro_wordpress"; then
    # Docker環境の場合
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Docker環境でdocker exec経由でNews Crawlerを実行中..." >> "$LOG_FILE"
    
    CONTAINER_NAME="KantanPro_wordpress"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 使用するコンテナ: $CONTAINER_NAME" >> "$LOG_FILE"
    
    # 一時的なPHPファイルを作成してコンテナ内で実行
    TEMP_PHP_FILE="/tmp/news-crawler-cron-$(date +%s).php"
    cat > "$TEMP_PHP_FILE" << 'DOCKER_EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('default_socket_timeout', 10);
ini_set('mysqli.default_socket_timeout', 10);
ini_set('mysql.connect_timeout', 10);
set_time_limit(110);

echo "[PHP] Docker環境での実行を開始\n";
echo "[PHP] WordPressディレクトリ: " . getcwd() . "\n";

require_once('/var/www/html/wp-load.php');
echo "[PHP] WordPress読み込み完了\n";

echo "[PHP] NewsCrawlerGenreSettingsクラスをチェック中\n";
if (class_exists('NewsCrawlerGenreSettings')) {
    echo "[PHP] クラスが見つかりました。インスタンスを取得中\n";
    $genre_settings = NewsCrawlerGenreSettings::get_instance();
    echo "[PHP] 自動投稿を実行中\n";
    
    // デバッグ用にジャンル設定の状況を確認
    $debug_info = array();
    if (method_exists($genre_settings, 'get_genre_settings')) {
        $genre_configs = $genre_settings->get_genre_settings();
        $debug_info['genre_count'] = count($genre_configs);
        $debug_info['auto_posting_enabled'] = 0;
        
        foreach ($genre_configs as $genre_id => $setting) {
            if (isset($setting['auto_posting']) && $setting['auto_posting']) {
                $debug_info['auto_posting_enabled']++;
                echo "[PHP] 自動投稿有効ジャンル: " . $setting['genre_name'] . " (ID: " . $genre_id . ")\n";
            }
        }
    }
    
    echo "[PHP] デバッグ情報: " . json_encode($debug_info) . "\n";
    
    $result = $genre_settings->execute_auto_posting();
    echo "[PHP] 自動投稿実行結果: " . var_export($result, true) . "\n";
    echo "[PHP] News Crawler自動投稿を実行しました\n";
} else {
    echo "[PHP] News CrawlerGenreSettingsクラスが見つかりません\n";
}
?>
DOCKER_EOF

    # ホストの一時ファイルをコンテナにコピーして実行
    docker cp "$TEMP_PHP_FILE" "$CONTAINER_NAME:/tmp/news-crawler-exec.php"
    
    if command -v timeout &> /dev/null; then
        timeout 120s docker exec "$CONTAINER_NAME" php /tmp/news-crawler-exec.php >> "$LOG_FILE" 2>&1
        PHP_STATUS=$?
    else
        docker exec "$CONTAINER_NAME" php /tmp/news-crawler-exec.php >> "$LOG_FILE" 2>&1
        PHP_STATUS=$?
    fi
    
    # 一時ファイルのクリーンアップ
    rm -f "$TEMP_PHP_FILE"
    docker exec "$CONTAINER_NAME" rm -f /tmp/news-crawler-exec.php 2>/dev/null
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Docker exec exit status: $PHP_STATUS" >> "$LOG_FILE"
    
    if [ "$PHP_STATUS" -eq 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Docker環境でNews Crawlerを実行しました" >> "$LOG_FILE"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Docker環境での実行でエラー (exit=$PHP_STATUS)" >> "$LOG_FILE"
    fi
# wp-cliが存在する場合は優先して使用（サーバー環境）
elif command -v wp &> /dev/null && [ "$PHP_SAPI" = "cli" ]; then
    cd "$WP_PATH"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行中..." >> "$LOG_FILE"
    # CLI版PHPを明示的に指定してwp-cliを実行（CGIを掴む環境対策）
    WP_CLI_PHP="$PHP_CMD" wp --path="$WP_PATH" eval "
        if (class_exists('NewsCrawlerGenreSettings')) {
            \$genre_settings = NewsCrawlerGenreSettings::get_instance();
            \$genre_settings->execute_auto_posting();
            echo 'News Crawler自動投稿を実行しました';
        } else {
            echo 'News CrawlerGenreSettingsクラスが見つかりません';
        }
    " >> "$LOG_FILE" 2>&1 || echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli実行でエラー" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行しました" >> "$LOG_FILE"
else
    # wp-cliが無い場合はPHP直接実行
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でNews Crawlerを実行中..." >> "$LOG_FILE"

    if [ -z "$PHP_CMD" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPコマンドが見つかりません。スクリプトを終了します。" >> "$LOG_FILE"
        exit 1
    fi

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 使用するPHPコマンド: $PHP_CMD (SAPI=${PHP_SAPI})" >> "$LOG_FILE"

    # 一時的なPHPファイルを作成して実行（wp-load.phpを使用）
    TEMP_PHP_FILE="/tmp/news-crawler-cron-$(date +%s).php"
    cat > "$TEMP_PHP_FILE" << EOF
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('default_socket_timeout', 10);
ini_set('mysqli.default_socket_timeout', 10);
ini_set('mysql.connect_timeout', 10);
set_time_limit(110);

echo "[PHP] 実行開始 - ディレクトリ: " . getcwd() . "\n";
echo "[PHP] PHP版本: " . phpversion() . "\n";
echo "[PHP] 現在時刻: " . date('Y-m-d H:i:s') . "\n";
echo "[PHP] メモリ制限: " . ini_get('memory_limit') . "\n";

// WordPressパスの動的検出（新しいパスを優先）
\$wp_paths = array(
    '/virtual/kantan/public_html/wp-load.php',
    '/var/www/html/wp-load.php',
    dirname(__FILE__) . '/../../../wp-load.php',
    \$_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'
);

\$wp_load_path = null;
foreach (\$wp_paths as \$path) {
    if (file_exists(\$path)) {
        \$wp_load_path = \$path;
        echo "[PHP] wp-load.phpを発見: " . \$path . "\n";
        break;
    }
}

if (!\$wp_load_path) {
    echo "[PHP] エラー: wp-load.phpが見つかりません\n";
    echo "[PHP] 検索したパス:\n";
    foreach (\$wp_paths as \$path) {
        echo "[PHP] - " . \$path . " (存在しない)\n";
    }
    exit(1);
}

echo "[PHP] wp-load.php読み込み開始: " . \$wp_load_path . "\n";
require_once(\$wp_load_path);
echo "[PHP] WordPress読み込み完了\n";

echo "[PHP] WordPress関数確認中\n";
if (function_exists('get_option')) {
    echo "[PHP] get_option関数: 利用可能\n";
    \$site_url = get_option('siteurl');
    echo "[PHP] サイトURL: " . \$site_url . "\n";
} else {
    echo "[PHP] エラー: get_option関数が利用できません\n";
}

echo "[PHP] NewsCrawlerGenreSettingsクラスをチェック中\n";
if (class_exists('NewsCrawlerGenreSettings')) {
    echo "[PHP] クラスが見つかりました。インスタンスを取得中\n";
    try {
        \$genre_settings = NewsCrawlerGenreSettings::get_instance();
        echo "[PHP] インスタンス取得成功\n";
        echo "[PHP] 自動投稿を実行中\n";
        
        // デバッグ用にジャンル設定の状況を確認
        \$debug_info = array();
        if (method_exists(\$genre_settings, 'get_genre_settings')) {
            \$genre_configs = \$genre_settings->get_genre_settings();
            \$debug_info['genre_count'] = count(\$genre_configs);
            \$debug_info['auto_posting_enabled'] = 0;
            
            foreach (\$genre_configs as \$genre_id => \$setting) {
                if (isset(\$setting['auto_posting']) && \$setting['auto_posting']) {
                    \$debug_info['auto_posting_enabled']++;
                    echo "[PHP] 自動投稿有効ジャンル: " . \$setting['genre_name'] . " (ID: " . \$genre_id . ")\n";
                }
            }
        }
        
        echo "[PHP] デバッグ情報: " . json_encode(\$debug_info) . "\n";
        
        \$result = \$genre_settings->execute_auto_posting();
        echo "[PHP] 自動投稿実行結果: " . var_export(\$result, true) . "\n";
        echo "[PHP] News Crawler自動投稿を実行しました\n";
    } catch (Exception \$e) {
        echo "[PHP] エラー: " . \$e->getMessage() . "\n";
        echo "[PHP] スタックトレース: " . \$e->getTraceAsString() . "\n";
    }
} else {
    echo "[PHP] News CrawlerGenreSettingsクラスが見つかりません\n";
    echo "[PHP] 利用可能なクラス一覧:\n";
    \$declared_classes = get_declared_classes();
    \$crawler_classes = array_filter(\$declared_classes, function(\$class) {
        return strpos(\$class, 'NewsCrawler') !== false || strpos(\$class, 'Genre') !== false;
    });
    if (!empty(\$crawler_classes)) {
        foreach (\$crawler_classes as \$class) {
            echo "[PHP] - " . \$class . "\n";
        }
    } else {
        echo "[PHP] News Crawler関連のクラスが見つかりません\n";
    }
}
echo "[PHP] スクリプト実行完了\n";
?>
EOF

    cd "$WP_PATH"
    
    # デバッグ: PHPスクリプト実行前の状態をログに記録
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPスクリプト実行前の状態:" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] - 現在のディレクトリ: $(pwd)" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] - PHPファイルのサイズ: $(wc -c < "$TEMP_PHP_FILE" 2>/dev/null || echo "不明")" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] - PHPファイルの存在確認: $([ -f "$TEMP_PHP_FILE" ] && echo "存在する" || echo "存在しない")" >> "$LOG_FILE"
    
    # PHPスクリプトの実行
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPスクリプト実行開始..." >> "$LOG_FILE"
    
    if command -v timeout &> /dev/null; then
        timeout 120s "$PHP_CMD" "$TEMP_PHP_FILE" >> "$LOG_FILE" 2>&1
        PHP_STATUS=$?
    else
        "$PHP_CMD" "$TEMP_PHP_FILE" >> "$LOG_FILE" 2>&1
        PHP_STATUS=$?
    fi
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPスクリプト実行完了" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP exit status: $PHP_STATUS" >> "$LOG_FILE"
    
    # 一時ファイルのクリーンアップ
    rm -f "$TEMP_PHP_FILE"
    
    if [ "$PHP_STATUS" -eq 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でNews Crawlerを実行しました" >> "$LOG_FILE"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でエラー (exit=$PHP_STATUS)" >> "$LOG_FILE"
        
        # エラー時の追加デバッグ情報
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラーデバッグ情報:" >> "$LOG_FILE"
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] - PHPバージョン: $("$PHP_CMD" -v 2>/dev/null | head -1 || echo "取得失敗")" >> "$LOG_FILE"
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] - wp-load.php存在確認: $([ -f "/virtual/kantan/public_html/wp-load.php" ] && echo "存在する" || echo "存在しない")" >> "$LOG_FILE"
    fi
fi

# ログに実行終了を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行終了" >> "$LOG_FILE"
echo "---" >> "$LOG_FILE"