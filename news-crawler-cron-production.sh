#!/bin/bash
# News Crawler Cron Script - 本番環境用修正版
# 重複実行防止機能を強化したバージョン

set -euo pipefail

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# 本番環境のWordPressパス
WP_PATH="/virtual/kantan/public_html/"

# プラグインパスを設定
PLUGIN_PATH="$SCRIPT_DIR/"

# ログファイルのパス
LOG_FILE="$SCRIPT_DIR/news-crawler-cron.log"

# 重複実行防止のためのロックファイル（強化版）
LOCK_FILE="/tmp/news-crawler-cron.lock"
LOCK_TIMEOUT=600  # 10分間のロック（延長）
LOCK_RETRY_COUNT=0
MAX_RETRY=3
LOCK_RETRY_DELAY=10

# ログ関数
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# 既存のロックファイルをチェックしてクリーンアップ
if [ -f "$LOCK_FILE" ]; then
    LOCK_AGE=$(($(date +%s) - $(stat -c %Y "$LOCK_FILE" 2>/dev/null || echo 0)))
    LOCK_PID=$(cat "$LOCK_FILE" 2>/dev/null || echo "")
    
    if [ $LOCK_AGE -lt $LOCK_TIMEOUT ]; then
        # プロセスIDをチェックして、実際に実行中かどうか確認
        if [ -n "$LOCK_PID" ] && kill -0 "$LOCK_PID" 2>/dev/null; then
            log_message "既に実行中のためスキップします (PID: $LOCK_PID, 経過時間: ${LOCK_AGE}秒)"
            exit 0
        else
            # 古いロックファイルを削除
            rm -f "$LOCK_FILE"
            log_message "古いロックファイルを削除しました (PID: $LOCK_PID)"
        fi
    else
        # 古いロックファイルを削除
        rm -f "$LOCK_FILE"
        log_message "古いロックファイルを削除しました (経過時間: ${LOCK_AGE}秒)"
    fi
fi

# ロックファイルの存在チェックと取得
while [ $LOCK_RETRY_COUNT -lt $MAX_RETRY ]; do
    # ロックファイルを作成（アトミック操作）
    if (set -C; echo $$ > "$LOCK_FILE") 2>/dev/null; then
        # ロック取得成功
        log_message "ロック取得成功 (PID: $$)"
        break
    else
        # ロック取得失敗、少し待って再試行
        LOCK_RETRY_COUNT=$((LOCK_RETRY_COUNT + 1))
        if [ $LOCK_RETRY_COUNT -lt $MAX_RETRY ]; then
            log_message "ロック取得失敗、再試行中... ($LOCK_RETRY_COUNT/$MAX_RETRY)"
            sleep $LOCK_RETRY_DELAY
        else
            log_message "ロック取得に失敗しました。最大試行回数に達しました。"
            exit 1
        fi
    fi
done

# ロックファイルのクリーンアップ用のtrapを設定
trap 'rm -f "$LOCK_FILE"; log_message "ロックファイルを削除しました"' EXIT

# ログに実行開始を記録
log_message "News Crawler Cron 実行開始"
log_message "スクリプトディレクトリ: $SCRIPT_DIR"
log_message "WordPressパス: $WP_PATH"
log_message "プラグインパス: $PLUGIN_PATH"

# PHPコマンドを検索
PHP_CMD=""
for cmd in "/usr/local/bin/php" "/usr/bin/php" "php"; do
    if command -v "$cmd" >/dev/null 2>&1; then
        PHP_CMD="$cmd"
        break
    fi
done

if [ -z "$PHP_CMD" ]; then
    log_message "エラー: PHPコマンドが見つかりません"
    exit 1
fi

log_message "使用するPHPコマンド: $PHP_CMD"

# PHP直接実行でNews Crawlerを実行
log_message "PHP直接実行でNews Crawlerを実行中..."

# 一時ディレクトリを作成
TEMP_DIR="/tmp/news-crawler-temp"
mkdir -p "$TEMP_DIR"

# PHPスクリプトを実行
cd "$TEMP_DIR"
$PHP_CMD -r "
echo '[PHP] 実行開始 - ディレクトリ: ' . getcwd() . PHP_EOL;

// WordPressのパスを設定
\$wp_path = '$WP_PATH';
echo '[PHP] wp-load.phpを発見: ' . \$wp_path . 'wp-load.php' . PHP_EOL;

// wp-load.phpを読み込み
if (file_exists(\$wp_path . 'wp-load.php')) {
    echo '[PHP] wp-load.php読み込み開始: ' . \$wp_path . 'wp-load.php' . PHP_EOL;
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
PHP_EXIT_CODE=${PIPESTATUS[0]}
log_message "PHP exit status: $PHP_EXIT_CODE"

if [ $PHP_EXIT_CODE -eq 0 ]; then
    log_message "PHP直接実行でNews Crawlerを実行しました"
else
    log_message "エラー: PHP実行に失敗しました (終了コード: $PHP_EXIT_CODE)"
fi

log_message "News Crawler Cron 実行終了"
echo "---" >> "$LOG_FILE"
