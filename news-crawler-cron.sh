#!/bin/bash
# News Crawler Cron Script
# 修正版 - 2025-09-14 09:24:32 (wp-load起動・ログ改善)

set -euo pipefail

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# WordPressのパスを動的に取得（プラグインディレクトリから逆算）
WP_PATH="$(dirname "$(dirname "$(dirname "$SCRIPT_DIR")")")/"

# WordPressのパスが正しいかチェック（wp-config.phpの存在確認）
if [ ! -f "$WP_PATH/wp-config.php" ]; then
    # 代替パスを試行
    for alt_path in "/var/www/html/" "/virtual/kantan/public_html/" "/var/www/html/" "$(dirname "$SCRIPT_DIR")/../../"; do
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

# wp-cliが存在する場合は優先して使用
if command -v wp &> /dev/null; then
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
    cat > "$TEMP_PHP_FILE" << EOF
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('default_socket_timeout', 10);
ini_set('mysqli.default_socket_timeout', 10);
ini_set('mysql.connect_timeout', 10);
set_time_limit(110);

echo "[PHP] before require: " . getcwd() . "\n";

require_once('/var/www/html/wp-load.php');
echo "[PHP] after require: WordPress loaded successfully\n";

echo "[PHP] checking class NewsCrawlerGenreSettings\n";
if (class_exists('NewsCrawlerGenreSettings')) {
    echo "[PHP] class found, getting instance\n";
    $genre_settings = NewsCrawlerGenreSettings::get_instance();
    echo "[PHP] executing auto posting\n";
    $genre_settings->execute_auto_posting();
    echo "[PHP] News Crawler自動投稿を実行しました\n";
} else {
    echo "[PHP] News CrawlerGenreSettingsクラスが見つかりません\n";
}
?>
EOF

    cd "$WP_PATH"
    if command -v timeout &> /dev/null; then
        timeout 120s "$PHP_CMD" "$TEMP_PHP_FILE" >> "$LOG_FILE" 2>&1
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
