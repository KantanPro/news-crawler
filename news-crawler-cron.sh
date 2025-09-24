#!/bin/bash
# News Crawler WordPress Cron Runner
# プラグイン配布用の汎用的なCronスクリプト

set -euo pipefail

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# WordPressのパスを動的に検出
WP_PATH=""
PLUGIN_PATH="$SCRIPT_DIR/"

# ログファイルのパス
LOG_FILE="$SCRIPT_DIR/news-crawler-cron.log"

# 重複実行防止のためのロックファイル（超強化版）
LOCK_FILE="/tmp/news-crawler-cron.lock"
LOCK_TIMEOUT=1800  # 30分間のロック（長めに設定）
LOCK_RETRY_COUNT=0
MAX_RETRY=1  # 再試行回数を減らす
LOCK_RETRY_DELAY=30  # 待機時間を長くする

# ログ関数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# WordPressパスを検出する関数
detect_wordpress_path() {
    local current_dir="$SCRIPT_DIR"
    
    # プラグインディレクトリから逆算してWordPressルートを検索
    for i in {1..5}; do
        local wp_candidate=""
        case $i in
            1) wp_candidate="$(dirname "$(dirname "$(dirname "$current_dir")")")/" ;;
            2) wp_candidate="$(dirname "$(dirname "$current_dir")")/" ;;
            3) wp_candidate="$(dirname "$current_dir")/" ;;
            4) wp_candidate="$current_dir/" ;;
            5) wp_candidate="/var/www/html/" ;;
        esac
        
        if [ -f "$wp_candidate/wp-config.php" ]; then
            WP_PATH="$wp_candidate"
            log_message "WordPressパスを検出: $WP_PATH"
            return 0
        fi
    done
    
    # 環境変数から検索
    if [ -n "${WORDPRESS_PATH:-}" ] && [ -f "$WORDPRESS_PATH/wp-config.php" ]; then
        WP_PATH="$WORDPRESS_PATH"
        log_message "環境変数からWordPressパスを検出: $WP_PATH"
        return 0
    fi
    
    # 一般的なパスを試行
    local common_paths=(
        "/var/www/html/"
        "/var/www/"
        "/home/*/public_html/"
        "/home/*/www/"
        "/usr/local/var/www/"
        "/srv/www/"
    )
    
    for pattern in "${common_paths[@]}"; do
        for wp_candidate in $pattern; do
            if [ -f "$wp_candidate/wp-config.php" ]; then
                WP_PATH="$wp_candidate"
                log_message "一般的なパスからWordPressを検出: $WP_PATH"
                return 0
            fi
        done
    done
    
    log_message "エラー: WordPressパスが見つかりません"
    return 1
}

# 既存のロックファイルをチェックしてクリーンアップ（超強化版）
cleanup_lock_file() {
    if [ -f "$LOCK_FILE" ]; then
        local lock_age=$(($(date +%s) - $(stat -c %Y "$LOCK_FILE" 2>/dev/null || echo 0)))
        local lock_pid=$(cat "$LOCK_FILE" 2>/dev/null || echo "")
        
        log_message "ロックファイル発見 - 経過時間: ${lock_age}秒, PID: $lock_pid"
        
        if [ $lock_age -lt $LOCK_TIMEOUT ]; then
            # プロセスIDをチェックして、実際に実行中かどうか確認
            if [ -n "$lock_pid" ] && kill -0 "$lock_pid" 2>/dev/null; then
                log_message "既に実行中のためスキップします (PID: $lock_pid, 経過時間: ${lock_age}秒)"
                exit 0
            else
                # 古いロックファイルを削除
                rm -f "$LOCK_FILE"
                log_message "古いロックファイルを削除しました (PID: $lock_pid は存在しません)"
            fi
        else
            # 古いロックファイルを削除
            rm -f "$LOCK_FILE"
            log_message "古いロックファイルを削除しました (経過時間: ${lock_age}秒 > ${LOCK_TIMEOUT}秒)"
        fi
    else
        log_message "ロックファイルは存在しません"
    fi
}

# ロックファイルを取得
acquire_lock() {
    while [ $LOCK_RETRY_COUNT -lt $MAX_RETRY ]; do
        # ロックファイルを作成（アトミック操作）
        if (set -C; echo $$ > "$LOCK_FILE") 2>/dev/null; then
            # ロック取得成功
            log_message "ロック取得成功 (PID: $$)"
            return 0
        else
            # ロック取得失敗、少し待って再試行
            LOCK_RETRY_COUNT=$((LOCK_RETRY_COUNT + 1))
            if [ $LOCK_RETRY_COUNT -lt $MAX_RETRY ]; then
                log_message "ロック取得失敗、再試行中... ($LOCK_RETRY_COUNT/$MAX_RETRY)"
                sleep $LOCK_RETRY_DELAY
            else
                log_message "ロック取得に失敗しました。最大試行回数に達しました。"
                return 1
            fi
        fi
    done
}

# PHPコマンドを検索
find_php_command() {
    local php_cmd=""
    local php_commands=(
        "/usr/local/bin/php"
        "/usr/bin/php"
        "/opt/php/bin/php"
        "php"
    )
    
    for cmd in "${php_commands[@]}"; do
        if command -v "$cmd" >/dev/null 2>&1; then
            php_cmd="$cmd"
            break
        fi
    done
    
    if [ -z "$php_cmd" ]; then
        log_message "エラー: PHPコマンドが見つかりません"
        return 1
    fi
    
    log_message "使用するPHPコマンド: $php_cmd"
    echo "$php_cmd"
}

# メイン実行関数
main() {
    log_message "News Crawler Cron 実行開始"
    log_message "スクリプトディレクトリ: $SCRIPT_DIR"
    
    # WordPressパスを検出
    if ! detect_wordpress_path; then
        exit 1
    fi
    
    log_message "プラグインパス: $PLUGIN_PATH"
    
    # ロックファイルのクリーンアップ
    cleanup_lock_file
    
    # ロックファイルを取得
    if ! acquire_lock; then
        exit 1
    fi
    
    # ロックファイルのクリーンアップ用のtrapを設定
    trap 'rm -f "$LOCK_FILE"; log_message "ロックファイルを削除しました"' EXIT
    
    # PHPコマンドを検索
    local php_cmd
    if ! php_cmd=$(find_php_command); then
        exit 1
    fi
    
    # 一時ディレクトリを作成（本番環境用）
    local temp_dir="/tmp/news-crawler-temp-$$"
    mkdir -p "$temp_dir" 2>/dev/null || {
        # フォールバック：プラグインディレクトリ内に作成
        temp_dir="$SCRIPT_DIR/temp-$$"
        mkdir -p "$temp_dir"
    }
    
    # PHPスクリプトを実行
    log_message "PHP直接実行でNews Crawlerを実行中..."
    
    cd "$temp_dir"
    $php_cmd -r "
echo '[PHP] 実行開始 - ディレクトリ: ' . getcwd() . PHP_EOL;

// WordPressのパスを設定
\$wp_path = '$WP_PATH';
echo '[PHP] wp-load.phpを発見: ' . \$wp_path . 'wp-load.php' . PHP_EOL;

// wp-load.phpを読み込み
if (file_exists(\$wp_path . 'wp-load.php')) {
    echo '[PHP] wp-load.php読み込み開始: ' . \$wp_path . 'wp-load.php' . PHP_EOL;
    
    // データベース接続エラーを抑制
    ini_set('display_errors', 0);
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    
    // WordPressの定数を設定
    if (!defined('ABSPATH')) {
        define('ABSPATH', \$wp_path);
    }
    if (!defined('WP_USE_THEMES')) {
        define('WP_USE_THEMES', false);
    }
    if (!defined('DOING_CRON')) {
        define('DOING_CRON', true);
    }
    
    require_once(\$wp_path . 'wp-load.php');
    echo '[PHP] WordPress読み込み完了' . PHP_EOL;
} else {
    echo '[PHP] エラー: wp-load.phpが見つかりません' . PHP_EOL;
    exit(1);
}

// WordPress関数が利用可能かチェック
echo '[PHP] WordPress関数確認中' . PHP_EOL;
if (function_exists('get_option')) {
    echo '[PHP] get_option関数: 利用可能' . PHP_EOL;
    echo '[PHP] サイトURL: ' . get_option('siteurl') . PHP_EOL;
} else {
    echo '[PHP] エラー: WordPress関数が利用できません' . PHP_EOL;
    exit(1);
}

// NewsCrawlerGenreSettingsクラスをチェック
echo '[PHP] NewsCrawlerGenreSettingsクラスをチェック中' . PHP_EOL;
if (class_exists('NewsCrawlerGenreSettings')) {
    echo '[PHP] クラスが見つかりました。インスタンスを取得中' . PHP_EOL;
    \$genre_settings = new NewsCrawlerGenreSettings();
    echo '[PHP] インスタンス取得成功' . PHP_EOL;
    
    // 自動投稿を実行
    echo '[PHP] 自動投稿を実行中' . PHP_EOL;
    \$result = \$genre_settings->execute_auto_posting();
    echo '[PHP] 自動投稿実行結果: ' . print_r(\$result, true) . PHP_EOL;
    echo '[PHP] News Crawler自動投稿を実行しました' . PHP_EOL;
} else {
    echo '[PHP] エラー: NewsCrawlerGenreSettingsクラスが見つかりません' . PHP_EOL;
    exit(1);
}

echo '[PHP] スクリプト実行完了' . PHP_EOL;
" 2>&1 | while IFS= read -r line; do
        log_message "$line"
    done
    
    # PHP実行結果をチェック
    local php_exit_code=${PIPESTATUS[0]}
    log_message "PHP exit status: $php_exit_code"
    
    # 一時ディレクトリをクリーンアップ
    rm -rf "$temp_dir"
    
    if [ $php_exit_code -eq 0 ]; then
        log_message "PHP直接実行でNews Crawlerを実行しました"
    else
        log_message "エラー: PHP実行に失敗しました (終了コード: $php_exit_code)"
    fi
    
    log_message "News Crawler Cron 実行終了"
    echo "---" >> "$LOG_FILE"
}

# メイン実行
main "$@"