#!/bin/bash
# News Crawler Cron Script
# 修正版 - 2025-09-26 08:39:55 (同時実行防止強化版)

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

# 同時実行防止のためのロックファイル（強化版）
LOCK_FILE="$SCRIPT_DIR/news-crawler-cron.lock"
LOCK_TIMEOUT=300  # 5分間のロック（短縮）
MAX_RETRIES=3     # 最大再試行回数
RETRY_DELAY=2     # 再試行間隔（秒）

# ロックファイルの存在チェックと作成（アトミック操作）
lock_acquired=false
retry_count=0

while [ $retry_count -lt $MAX_RETRIES ] && [ "$lock_acquired" = false ]; do
    retry_count=$((retry_count + 1))
    
    # ロックファイルの作成を試行
    if (set -C; echo "$$" > "$LOCK_FILE") 2>/dev/null; then
        # ロックファイルが作成された場合、PIDが正しいか確認
        if [ -f "$LOCK_FILE" ]; then
            lock_pid=$(cat "$LOCK_FILE" 2>/dev/null || echo "")
            if [ "$lock_pid" = "$$" ]; then
                lock_acquired=true
                echo "[$(date '+%Y-%m-%d %H:%M:%S')] ロックファイルを取得しました (PID: $$, 試行: $retry_count)" >> "$LOG_FILE"
            else
                echo "[$(date '+%Y-%m-%d %H:%M:%S')] ロックファイルのPIDが一致しません (expected: $$, actual: $lock_pid, 試行: $retry_count)" >> "$LOG_FILE"
                rm -f "$LOCK_FILE"
                sleep $RETRY_DELAY
            fi
        else
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] ロックファイルの作成に失敗 (試行: $retry_count)" >> "$LOG_FILE"
            sleep $RETRY_DELAY
        fi
    else
        # ロックファイルが既に存在する場合
        if [ -f "$LOCK_FILE" ]; then
            # ロックファイルの作成時刻をチェック
            LOCK_TIME=$(stat -c %Y "$LOCK_FILE" 2>/dev/null || stat -f %m "$LOCK_FILE" 2>/dev/null || echo 0)
            CURRENT_TIME=$(date +%s)
            LOCK_AGE=$((CURRENT_TIME - LOCK_TIME))
            
            # ロックファイルのPIDを確認
            lock_pid=$(cat "$LOCK_FILE" 2>/dev/null || echo "")
            
            # プロセスが実際に実行中かチェック
            if [ -n "$lock_pid" ] && kill -0 "$lock_pid" 2>/dev/null; then
                # プロセスが実行中の場合
                if [ $LOCK_AGE -gt $LOCK_TIMEOUT ]; then
                    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 古いロックファイルを削除 (age: $LOCK_AGE秒, PID: $lock_pid, 試行: $retry_count)" >> "$LOG_FILE"
                    rm -f "$LOCK_FILE"
                    sleep $RETRY_DELAY
                else
                    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 既に実行中のためスキップ (age: $LOCK_AGE秒, PID: $lock_pid, 試行: $retry_count)" >> "$LOG_FILE"
                    exit 0
                fi
            else
                # プロセスが存在しない場合、ロックファイルを削除
                echo "[$(date '+%Y-%m-%d %H:%M:%S')] 存在しないプロセスのロックファイルを削除 (PID: $lock_pid, 試行: $retry_count)" >> "$LOG_FILE"
                rm -f "$LOCK_FILE"
                sleep $RETRY_DELAY
            fi
        else
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] ロックファイルの作成に失敗 (試行: $retry_count)" >> "$LOG_FILE"
            sleep $RETRY_DELAY
        fi
    fi
done

# ロックが取得できなかった場合
if [ "$lock_acquired" = false ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ロックファイルの取得に失敗しました（$MAX_RETRIES回試行後）" >> "$LOG_FILE"
    exit 1
fi

# ログに実行開始を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行開始 (PID: $$)" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] スクリプトディレクトリ: $SCRIPT_DIR" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] WordPressパス: $WP_PATH" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] プラグインパス: $PLUGIN_PATH" >> "$LOG_FILE"

# 実行時間制限を設定（5分でタイムアウト）
TIMEOUT_SECONDS=300
START_TIME=$(date +%s)

# START_TIMEが正しく設定されているか確認
if [ -z "${START_TIME:-}" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: START_TIMEが設定されませんでした" >> "$LOG_FILE"
    exit 1
fi

# エラーハンドリング用の関数
cleanup_and_exit() {
    local exit_code=$1
    local error_message=$2
    
    # 実行時間を計算（START_TIMEが未定義の場合は0を設定）
    local end_time=$(date +%s)
    local start_time=${START_TIME:-$end_time}
    local execution_time=$((end_time - start_time))
    
    # エラーログを記録
    if [ -n "$error_message" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: $error_message (実行時間: $execution_time秒)" >> "$LOG_FILE"
    fi
    
    # ロックファイルを削除
    rm -f "$LOCK_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ロックファイルを削除しました" >> "$LOG_FILE"
    
    # 実行終了を記録
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行終了 (PID: $$, 実行時間: $execution_time秒, 終了コード: $exit_code)" >> "$LOG_FILE"
    echo "---" >> "$LOG_FILE"
    
    exit $exit_code
}

# タイムアウトチェック用の関数
check_timeout() {
    local current_time=$(date +%s)
    local elapsed_time=$((current_time - START_TIME))
    
    if [ $elapsed_time -gt $TIMEOUT_SECONDS ]; then
        cleanup_and_exit 1 "実行時間が制限を超えました ($elapsed_time秒 > $TIMEOUT_SECONDS秒)"
    fi
}

# シグナルハンドラーを設定
trap 'echo "[$(date "+%Y-%m-%d %H:%M:%S")] エラーが発生しました (行: $LINENO)" >> "$LOG_FILE"; rm -f "$TEMP_PHP_FILE"; cleanup_and_exit 1 "スクリプト実行中にエラーが発生しました"' ERR
trap 'cleanup_and_exit 130 "スクリプトが中断されました"' INT TERM

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
    echo "[PHP] 自動投稿を強制実行中\n";
    // 強制実行を使用（手動実行と同じ処理）
    $reflection = new ReflectionClass($genre_settings);
    $method = $reflection->getMethod('execute_auto_posting_forced');
    $method->setAccessible(true);
    $result = $method->invoke($genre_settings);
    echo "[PHP] News Crawler自動投稿を強制実行しました\n";
    echo "[PHP] 実行結果: " . json_encode($result) . "\n";
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
        cleanup_and_exit 1 "Docker環境での実行でエラー (exit=$PHP_STATUS)"
    fi
# wp-cliが存在する場合は優先して使用（サーバー環境）
elif command -v wp &> /dev/null; then
    cd "$WP_PATH"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行中..." >> "$LOG_FILE"
    wp --path="$WP_PATH" eval "
        if (class_exists('NewsCrawlerGenreSettings')) {
            \$genre_settings = NewsCrawlerGenreSettings::get_instance();
            // 強制実行を使用（手動実行と同じ処理）
            \$reflection = new ReflectionClass(\$genre_settings);
            \$method = \$reflection->getMethod('execute_auto_posting_forced');
            \$method->setAccessible(true);
            \$result = \$method->invoke(\$genre_settings);
            echo 'News Crawler自動投稿を強制実行しました: ' . json_encode(\$result);
        } else {
            echo 'News CrawlerGenreSettingsクラスが見つかりません';
        }
    " >> "$LOG_FILE" 2>&1 || cleanup_and_exit 1 "wp-cli実行でエラー"
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
        cleanup_and_exit 1 "PHPコマンドが見つかりません"
    fi

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 使用するPHPコマンド: $PHP_CMD" >> "$LOG_FILE"

    # 一時的なPHPファイルを作成して実行（wp-load.phpを使用）
    TEMP_PHP_FILE="/tmp/news-crawler-cron-$(date +%s).php"
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 一時PHPファイル作成開始: $TEMP_PHP_FILE" >> "$LOG_FILE"
    
    # エラーハンドリングを強化（set -eを一時的に無効化）
    set +e
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPファイル内容を生成中..." >> "$LOG_FILE"
    
    # 一時ファイルの作成を段階的に実行
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ1: 一時ファイルの作成を開始" >> "$LOG_FILE"
    
    # まず空のファイルを作成
    touch "$TEMP_PHP_FILE" 2>>"$LOG_FILE"
    if [ $? -ne 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: 一時ファイルの作成に失敗" >> "$LOG_FILE"
        cleanup_and_exit 1 "一時ファイルの作成に失敗しました"
    fi
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ2: 一時ファイルの作成完了" >> "$LOG_FILE"
    
    # ファイルに内容を書き込み
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ3: PHPファイル内容を書き込み中..." >> "$LOG_FILE"
    cat > "$TEMP_PHP_FILE" << 'EOF'
<?php
// 安全な実行設定
error_reporting(E_ALL);
ini_set('display_errors', 0);  // 出力を抑制
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php-errors.log');
ini_set('memory_limit', '512M');  // メモリ制限を増加
set_time_limit(600);  // 実行時間制限を10分に延長（手動実行と同じ）

// 出力バッファリングを無効化
while (ob_get_level()) {
    ob_end_clean();
}

// ログファイルに直接書き込み
$log_file = '/tmp/php-execution.log';
$log = function($message) use ($log_file) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
};

$log('[PHP] 実行開始');
$log('[PHP] 現在のディレクトリ: ' . getcwd());
$log('[PHP] PHPバージョン: ' . phpversion());
$log('[PHP] メモリ制限: ' . ini_get('memory_limit'));

// WordPressパスの検索
$log('[PHP] WordPressパス検索開始');
$wp_paths = array(
    '/virtual/kantan/public_html/wp-load.php',
    '/var/www/html/wp-load.php'
);

$wp_load_path = null;
foreach ($wp_paths as $path) {
    $log('[PHP] パス確認: ' . $path);
    if (file_exists($path)) {
        $wp_load_path = $path;
        $log('[PHP] wp-load.phpを発見: ' . $path);
        break;
    }
}

if (!$wp_load_path) {
    $log('[PHP] エラー: wp-load.phpが見つかりません');
    exit(1);
}

// WordPress読み込み
$log('[PHP] WordPress読み込み開始');
try {
require_once($wp_load_path);
    $log('[PHP] WordPress読み込み成功');
} catch (Exception $e) {
    $log('[PHP] WordPress読み込みエラー: ' . $e->getMessage());
    exit(1);
} catch (Error $e) {
    $log('[PHP] WordPress読み込みFatal Error: ' . $e->getMessage());
    exit(1);
}

// WordPress関数確認
if (!function_exists('get_option')) {
    $log('[PHP] エラー: get_option関数が利用できません');
    exit(1);
}
$log('[PHP] WordPress関数確認完了');

// NewsCrawlerGenreSettingsクラス確認
if (class_exists('NewsCrawlerGenreSettings')) {
    $log('[PHP] NewsCrawlerGenreSettingsクラス発見');
    try {
        $genre_settings = NewsCrawlerGenreSettings::get_instance();
        $log('[PHP] インスタンス取得成功');
        $log('[PHP] 自動投稿強制実行開始');
        $start_time = microtime(true);
        // 強制実行を使用（手動実行と同じ処理）
        $reflection = new ReflectionClass($genre_settings);
        $method = $reflection->getMethod('execute_auto_posting_forced');
        $method->setAccessible(true);
        $result = $method->invoke($genre_settings);
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        $log('[PHP] 自動投稿完了: ' . json_encode($result) . ' (実行時間: ' . $execution_time . '秒)');
        
        // 実行結果の詳細分析
        $log('[PHP] 実行結果詳細:');
        $log('[PHP] - 実行されたジャンル数: ' . ($result['executed_count'] ?? '不明'));
        $log('[PHP] - スキップされたジャンル数: ' . ($result['skipped_count'] ?? '不明'));
        $log('[PHP] - 総ジャンル数: ' . ($result['total_genres'] ?? '不明'));
        $log('[PHP] - 作成された投稿数: ' . ($result['posts_created'] ?? '不明'));
        $log('[PHP] - メッセージ: ' . ($result['message'] ?? '不明'));
        
        // 投稿が作成されなかった場合の警告
        if (isset($result['posts_created']) && $result['posts_created'] == 0) {
            $log('[PHP] 警告: 投稿が作成されませんでした');
            if (isset($result['executed_count']) && $result['executed_count'] > 0) {
                $log('[PHP] 注意: ジャンルは実行されたが、投稿は作成されませんでした');
            }
        }
    } catch (Exception $e) {
        $log('[PHP] 実行エラー: ' . $e->getMessage());
    } catch (Error $e) {
        $log('[PHP] 実行Fatal Error: ' . $e->getMessage());
    }
} else {
    $log('[PHP] NewsCrawlerGenreSettingsクラスが見つかりません');
}

$log('[PHP] スクリプト実行完了');
?>
EOF

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ4: PHPファイル生成完了" >> "$LOG_FILE"
    
    # ファイルの存在とサイズを確認
    if [ ! -f "$TEMP_PHP_FILE" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: PHPファイルが存在しません: $TEMP_PHP_FILE" >> "$LOG_FILE"
        cleanup_and_exit 1 "PHPファイルが存在しません: $TEMP_PHP_FILE"
    fi
    
    FILE_SIZE=$(stat -c%s "$TEMP_PHP_FILE" 2>/dev/null || stat -f%z "$TEMP_PHP_FILE" 2>/dev/null || echo 0)
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ5: PHPファイルサイズ確認: $FILE_SIZE bytes" >> "$LOG_FILE"
    
    if [ "$FILE_SIZE" -eq 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: PHPファイルが空です" >> "$LOG_FILE"
        cleanup_and_exit 1 "PHPファイルが空です"
    fi

    # WordPressディレクトリに移動してPHPファイルを実行
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ6: WordPressディレクトリに移動開始: $WP_PATH" >> "$LOG_FILE"
    cd "$WP_PATH"
    if [ $? -ne 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: WordPressディレクトリへの移動に失敗" >> "$LOG_FILE"
        cleanup_and_exit 1 "WordPressディレクトリへの移動に失敗しました"
    fi
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ7: WordPressディレクトリに移動完了: $(pwd)" >> "$LOG_FILE"
    
    # PHPファイルの存在確認（移動後）
    if [ ! -f "$TEMP_PHP_FILE" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: 移動後にPHPファイルが見つかりません: $TEMP_PHP_FILE" >> "$LOG_FILE"
        cleanup_and_exit 1 "移動後にPHPファイルが見つかりません"
    fi
    
    # PHPファイルを実行（タイムアウト付き、詳細ログ付き）
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ8: PHPファイル実行開始: $TEMP_PHP_FILE" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 現在のディレクトリ: $(pwd)" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPファイルの存在確認: $(ls -la "$TEMP_PHP_FILE" 2>/dev/null || echo 'ファイルが見つかりません')" >> "$LOG_FILE"
    
    # PHP実行前の最終確認
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ9: PHP実行前の最終確認" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPコマンド: $PHP_CMD" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 実行ファイル: $TEMP_PHP_FILE" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 作業ディレクトリ: $(pwd)" >> "$LOG_FILE"
    
    # PHPコマンドの存在確認
    if [ ! -x "$PHP_CMD" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: PHPコマンドが実行できません: $PHP_CMD" >> "$LOG_FILE"
        cleanup_and_exit 1 "PHPコマンドが実行できません"
    fi
    
    # 一時ファイルの読み取り権限確認
    if [ ! -r "$TEMP_PHP_FILE" ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: PHPファイルが読み取れません: $TEMP_PHP_FILE" >> "$LOG_FILE"
        cleanup_and_exit 1 "PHPファイルが読み取れません"
    fi
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ10: PHP実行開始" >> "$LOG_FILE"
    
    # PHP実行の詳細ログ
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP実行コマンド: $PHP_CMD $TEMP_PHP_FILE" >> "$LOG_FILE"
    
    # 診断用の実行
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 診断実行開始" >> "$LOG_FILE"
    
    # まず、WordPress読み込みなしでPHPの基本動作をテスト
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ1: PHP基本動作テスト" >> "$LOG_FILE"
    echo '<?php echo "[TEST] PHP基本動作OK\n"; ?>' > /tmp/php-test.php
    timeout 5s "$PHP_CMD" /tmp/php-test.php >> "$LOG_FILE" 2>&1
    TEST_STATUS=$?
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP基本テスト完了 (exit status: $TEST_STATUS)" >> "$LOG_FILE"
    rm -f /tmp/php-test.php
    
    if [ "$TEST_STATUS" -ne 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] エラー: PHP基本動作に問題があります" >> "$LOG_FILE"
        cleanup_and_exit 1 "PHP基本動作に問題があります"
    fi
    
    # WordPress読み込みテスト用の簡単なPHPファイルを作成
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ2: WordPress読み込みテスト用ファイル作成" >> "$LOG_FILE"
    cat > /tmp/wp-test.php << 'WPTEST'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/wp-test-errors.log');
set_time_limit(15);

$log_file = '/tmp/wp-test.log';
$log = function($message) use ($log_file) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . ' ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
};

$log('[WP-TEST] 開始');
$log('[WP-TEST] 現在のディレクトリ: ' . getcwd());

// WordPressパス確認
$wp_path = '/virtual/kantan/public_html/wp-load.php';
$log('[WP-TEST] WordPressパス確認: ' . $wp_path);
$log('[WP-TEST] ファイル存在: ' . (file_exists($wp_path) ? 'YES' : 'NO'));

if (file_exists($wp_path)) {
    $log('[WP-TEST] WordPress読み込み開始');
    try {
        require_once($wp_path);
        $log('[WP-TEST] WordPress読み込み成功');
        
        if (function_exists('get_option')) {
            $log('[WP-TEST] get_option関数: 利用可能');
        } else {
            $log('[WP-TEST] get_option関数: 利用不可');
        }
    } catch (Exception $e) {
        $log('[WP-TEST] WordPress読み込みエラー: ' . $e->getMessage());
    } catch (Error $e) {
        $log('[WP-TEST] WordPress読み込みFatal Error: ' . $e->getMessage());
    }
} else {
    $log('[WP-TEST] WordPressファイルが見つかりません');
}

$log('[WP-TEST] 終了');
?>
WPTEST

    # WordPress読み込みテスト実行
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ3: WordPress読み込みテスト実行" >> "$LOG_FILE"
    timeout 15s "$PHP_CMD" /tmp/wp-test.php >> "$LOG_FILE" 2>&1
    WP_TEST_STATUS=$?
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] WordPress読み込みテスト完了 (exit status: $WP_TEST_STATUS)" >> "$LOG_FILE"
    
    # テスト結果をログに追加
    if [ -f /tmp/wp-test.log ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] WordPressテストログ:" >> "$LOG_FILE"
        cat /tmp/wp-test.log >> "$LOG_FILE"
        rm -f /tmp/wp-test.log
    fi
    
    if [ -f /tmp/wp-test-errors.log ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] WordPressエラーログ:" >> "$LOG_FILE"
        cat /tmp/wp-test-errors.log >> "$LOG_FILE"
        rm -f /tmp/wp-test-errors.log
    fi
    
    # 元のPHPファイルを実行
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ステップ4: 元のPHPファイル実行" >> "$LOG_FILE"
        timeout 600s "$PHP_CMD" "$TEMP_PHP_FILE" >> "$LOG_FILE" 2>&1
    PHP_STATUS=$?
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 元のPHPファイル実行完了 (exit status: $PHP_STATUS)" >> "$LOG_FILE"
    
    # 実行結果をログに追加
    if [ -f /tmp/php-execution.log ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP実行ログ:" >> "$LOG_FILE"
        cat /tmp/php-execution.log >> "$LOG_FILE"
        rm -f /tmp/php-execution.log
    fi
    
    if [ -f /tmp/php-errors.log ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPエラーログ:" >> "$LOG_FILE"
        cat /tmp/php-errors.log >> "$LOG_FILE"
        rm -f /tmp/php-errors.log
    fi
    
    # 一時ファイルをクリーンアップ
    rm -f /tmp/wp-test.php
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP exit status: $PHP_STATUS" >> "$LOG_FILE"
    
    # 一時ファイルを削除
    rm -f "$TEMP_PHP_FILE"
    
    if [ "$PHP_STATUS" -eq 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でNews Crawlerを実行しました" >> "$LOG_FILE"
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] 正常に完了しました" >> "$LOG_FILE"
    else
        cleanup_and_exit 1 "PHP直接実行でエラー (exit=$PHP_STATUS)"
    fi
fi

# 正常終了
cleanup_and_exit 0 "正常に完了しました"
