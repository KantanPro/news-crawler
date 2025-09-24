#!/bin/bash
# News Crawler Cron Script
# 修正版 - 2025-09-23 18:00:00 (重複実行防止機能追加)

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

# 重複実行防止のためのロックファイル（超強化版）
LOCK_FILE="/tmp/news-crawler-cron.lock"
LOCK_TIMEOUT=300  # 5分間のロック
LOCK_RETRY_COUNT=0
MAX_RETRY=10
LOCK_RETRY_DELAY=3

# 既存のロックファイルをチェックしてクリーンアップ
if [ -f "$LOCK_FILE" ]; then
    LOCK_AGE=$(($(date +%s) - $(stat -c %Y "$LOCK_FILE" 2>/dev/null || echo 0)))
    LOCK_PID=$(cat "$LOCK_FILE" 2>/dev/null || echo "")
    
    if [ $LOCK_AGE -lt $LOCK_TIMEOUT ]; then
        # プロセスIDをチェックして、実際に実行中かどうか確認
        if [ -n "$LOCK_PID" ] && kill -0 "$LOCK_PID" 2>/dev/null; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] 既に実行中のためスキップします (PID: $LOCK_PID, 経過時間: ${LOCK_AGE}秒)" >> "$LOG_FILE"
            exit 0
        else
            # 古いロックファイルを削除
            rm -f "$LOCK_FILE"
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] 古いロックファイルを削除しました (PID: $LOCK_PID)" >> "$LOG_FILE"
        fi
    else
        # 古いロックファイルを削除
        rm -f "$LOCK_FILE"
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] 古いロックファイルを削除しました (経過時間: ${LOCK_AGE}秒)" >> "$LOG_FILE"
    fi
fi

# ロックファイルの存在チェックと取得
while [ $LOCK_RETRY_COUNT -lt $MAX_RETRY ]; do
    # ロックファイルを作成（アトミック操作）
    if (set -C; echo $$ > "$LOCK_FILE") 2>/dev/null; then
        # ロック取得成功
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ロック取得成功 (PID: $$)" >> "$LOG_FILE"
        break
    else
        # ロック取得失敗、少し待って再試行
        LOCK_RETRY_COUNT=$((LOCK_RETRY_COUNT + 1))
        if [ $LOCK_RETRY_COUNT -lt $MAX_RETRY ]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] ロック取得失敗、再試行中... ($LOCK_RETRY_COUNT/$MAX_RETRY)" >> "$LOG_FILE"
            sleep $LOCK_RETRY_DELAY
        else
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] ロック取得に失敗しました。最大試行回数に達しました。" >> "$LOG_FILE"
            exit 1
        fi
    fi
done

# ロックファイルのクリーンアップ用のtrapを設定
trap 'rm -f "$LOCK_FILE"; echo "[$(date "+%Y-%m-%d %H:%M:%S")] ロックファイルを削除しました" >> "$LOG_FILE"' EXIT

# ログに実行開始を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行開始" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] スクリプトディレクトリ: $SCRIPT_DIR" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] WordPressパス: $WP_PATH" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] プラグインパス: $PLUGIN_PATH" >> "$LOG_FILE"

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
set_time_limit(580);

echo "[PHP] Docker環境での実行を開始\n";
echo "[PHP] WordPressディレクトリ: " . getcwd() . "\n";

require_once('/var/www/html/wp-load.php');
echo "[PHP] WordPress読み込み完了\n";

echo "[PHP] NewsCrawlerGenreSettingsクラスをチェック中\n";
if (class_exists('NewsCrawlerGenreSettings')) {
    echo "[PHP] クラスが見つかりました。インスタンスを取得中\n";
    $genre_settings = NewsCrawlerGenreSettings::get_instance();
    echo "[PHP] 自動投稿を実行中\n";
    $genre_settings->execute_auto_posting();
    echo "[PHP] News Crawler自動投稿を実行しました\n";
} else {
    echo "[PHP] News CrawlerGenreSettingsクラスが見つかりません\n";
}
?>
DOCKER_EOF

    # ホストの一時ファイルをコンテナにコピーして実行
    docker cp "$TEMP_PHP_FILE" "$CONTAINER_NAME:/tmp/news-crawler-exec.php"
    
    if command -v timeout &> /dev/null; then
        timeout 600s docker exec "$CONTAINER_NAME" php /tmp/news-crawler-exec.php >> "$LOG_FILE" 2>&1
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
elif command -v wp &> /dev/null; then
    cd "$WP_PATH"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行中..." >> "$LOG_FILE"
    wp --path="$WP_PATH" eval "
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

    # PHPのフルパスを複数の候補から検索
    PHP_CMD=""
    for php_path in "/usr/bin/php" "/usr/local/bin/php" "/opt/homebrew/bin/php" "$(command -v php || true)"; do
        if [ -n "$php_path" ] && [ -x "$php_path" ]; then
            PHP_CMD="$php_path"
            break
        fi
    done

    if [ -z "$PHP_CMD" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPコマンドが見つかりません。スクリプトを終了します。" >> "$LOG_FILE"
        exit 1
    fi

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 使用するPHPコマンド: $PHP_CMD" >> "$LOG_FILE"

    # 一時的なPHPファイルを作成して実行（wp-load.phpを使用）
    TEMP_PHP_FILE="/tmp/news-crawler-cron-$(date +%s).php"
    cat > "$TEMP_PHP_FILE" << 'EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('default_socket_timeout', 10);
ini_set('mysqli.default_socket_timeout', 10);
ini_set('mysql.connect_timeout', 10);
set_time_limit(580);

echo "[PHP] 実行開始 - ディレクトリ: " . getcwd() . "\n";

// WordPressパスの動的検出（新しいパスを優先）
$wp_paths = array(
    '/virtual/kantan/public_html/wp-load.php',
    '/var/www/html/wp-load.php',
    dirname(__FILE__) . '/../../../wp-load.php'
);

$wp_load_path = null;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        $wp_load_path = $path;
        echo "[PHP] wp-load.phpを発見: " . $path . "\n";
        break;
    }
}

if (!$wp_load_path) {
    echo "[PHP] エラー: wp-load.phpが見つかりません\n";
    echo "[PHP] 検索したパス:\n";
    foreach ($wp_paths as $path) {
        echo "[PHP] - " . $path . " (存在しない)\n";
    }
    exit(1);
}

echo "[PHP] wp-load.php読み込み開始: " . $wp_load_path . "\n";
require_once($wp_load_path);
echo "[PHP] WordPress読み込み完了\n";

echo "[PHP] WordPress関数確認中\n";
if (function_exists('get_option')) {
    echo "[PHP] get_option関数: 利用可能\n";
    $site_url = get_option('siteurl');
    echo "[PHP] サイトURL: " . $site_url . "\n";
} else {
    echo "[PHP] エラー: get_option関数が利用できません\n";
}

echo "[PHP] NewsCrawlerGenreSettingsクラスをチェック中\n";
if (class_exists('NewsCrawlerGenreSettings')) {
    echo "[PHP] クラスが見つかりました。インスタンスを取得中\n";
    try {
        $genre_settings = NewsCrawlerGenreSettings::get_instance();
        echo "[PHP] インスタンス取得成功\n";
        echo "[PHP] 自動投稿を実行中\n";
        $result = $genre_settings->execute_auto_posting();
        echo "[PHP] 自動投稿実行結果: " . var_export($result, true) . "\n";
        echo "[PHP] News Crawler自動投稿を実行しました\n";
    } catch (Exception $e) {
        echo "[PHP] エラー: " . $e->getMessage() . "\n";
        echo "[PHP] スタックトレース: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "[PHP] News CrawlerGenreSettingsクラスが見つかりません\n";
    echo "[PHP] 利用可能なクラス一覧:\n";
    $declared_classes = get_declared_classes();
    $crawler_classes = array_filter($declared_classes, function($class) {
        return strpos($class, 'NewsCrawler') !== false || strpos($class, 'Genre') !== false;
    });
    if (!empty($crawler_classes)) {
        foreach ($crawler_classes as $class) {
            echo "[PHP] - " . $class . "\n";
        }
    } else {
        echo "[PHP] News Crawler関連のクラスが見つかりません\n";
    }
}
echo "[PHP] スクリプト実行完了\n";
?>
EOF

    cd "$WP_PATH"
    if command -v timeout &> /dev/null; then
        timeout 600s "$PHP_CMD" "$TEMP_PHP_FILE" >> "$LOG_FILE" 2>&1
        PHP_STATUS=$?
    else
        "$PHP_CMD" "$TEMP_PHP_FILE" >> "$LOG_FILE" 2>&1
        PHP_STATUS=$?
    fi
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP exit status: $PHP_STATUS" >> "$LOG_FILE"
    rm -f "$TEMP_PHP_FILE"
    if [ "$PHP_STATUS" -eq 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でNews Crawlerを実行しました" >> "$LOG_FILE"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でエラー (exit=$PHP_STATUS)" >> "$LOG_FILE"
    fi
fi

# ログに実行終了を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行終了" >> "$LOG_FILE"
echo "---" >> "$LOG_FILE"
