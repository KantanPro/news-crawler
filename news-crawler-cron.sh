#!/bin/bash
# News Crawler Cron Script
# 修正版 - 2025-09-11

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# WordPressのパスを動的に取得（プラグインディレクトリから逆算）
WP_PATH="$(dirname "$(dirname "$(dirname "$SCRIPT_DIR")")")/"

# WordPressのパスが正しいかチェック（wp-config.phpの存在確認）
if [ ! -f "$WP_PATH/wp-config.php" ]; then
    # 代替パスを試行
    for alt_path in "/var/www/html/" "/Users/kantanpro/Desktop/KantanPro/wordpress/" "$(dirname "$SCRIPT_DIR")/../../"; do
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

# Docker環境かどうかを判定
if [ -f "/.dockerenv" ] || [ -n "$DOCKER_CONTAINER" ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Docker環境で実行中..." >> "$LOG_FILE"
    
    # Docker環境ではwp-cliを使用
    if command -v wp &> /dev/null; then
        cd "$WP_PATH"
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行中..." >> "$LOG_FILE"
        
        # WordPressのパスを明示的に指定して実行
        wp --path="$WP_PATH" eval "
            if (class_exists('NewsCrawlerGenreSettings')) {
                \$genre_settings = NewsCrawlerGenreSettings::get_instance();
                \$genre_settings->execute_auto_posting();
                echo 'News Crawler自動投稿を実行しました';
            } else {
                echo 'News CrawlerGenreSettingsクラスが見つかりません';
            }
        " >> "$LOG_FILE" 2>&1
        
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行しました" >> "$LOG_FILE"
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Docker環境でwp-cliが見つかりません" >> "$LOG_FILE"
    fi
else
    # ローカル環境ではwp-cliまたはPHP直接実行
    if command -v wp &> /dev/null; then
        # wp-cliを使用してNews Crawlerを実行
        cd "$WP_PATH"
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行中..." >> "$LOG_FILE"
        
        # WordPressのパスを明示的に指定して実行
        wp --path="$WP_PATH" eval "
            if (class_exists('NewsCrawlerGenreSettings')) {
                \$genre_settings = NewsCrawlerGenreSettings::get_instance();
                \$genre_settings->execute_auto_posting();
                echo 'News Crawler自動投稿を実行しました';
            } else {
                echo 'News CrawlerGenreSettingsクラスが見つかりません';
            }
        " >> "$LOG_FILE" 2>&1
        
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行しました" >> "$LOG_FILE"
    else
        # wp-cliが利用できない場合は、PHPを直接実行
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でNews Crawlerを実行中..." >> "$LOG_FILE"
        
        cd "$WP_PATH"
        
        # PHPのフルパスを複数の候補から検索
        PHP_CMD=""
        for php_path in "/usr/bin/php" "/usr/local/bin/php" "/opt/homebrew/bin/php" "$(which php)"; do
            if [ -x "$php_path" ]; then
                PHP_CMD="$php_path"
                break
            fi
        done
        
        # PHPコマンドが見つからない場合のエラーハンドリング
        if [ -z "$PHP_CMD" ]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHPコマンドが見つかりません。スクリプトを終了します。" >> "$LOG_FILE"
            exit 1
        fi
        
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] 使用するPHPコマンド: $PHP_CMD" >> "$LOG_FILE"
        
        # PHPコマンドを使用して実行
        $PHP_CMD -r "
            require_once('wp-config.php');
            require_once('wp-includes/pluggable.php');
            if (class_exists('NewsCrawlerGenreSettings')) {
                \$genre_settings = NewsCrawlerGenreSettings::get_instance();
                \$genre_settings->execute_auto_posting();
                echo 'News Crawler自動投稿を実行しました';
            } else {
                echo 'News CrawlerGenreSettingsクラスが見つかりません';
            }
        " >> "$LOG_FILE" 2>&1
        
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でNews Crawlerを実行しました" >> "$LOG_FILE"
    fi
fi

# ログに実行終了を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行終了" >> "$LOG_FILE"
echo "---" >> "$LOG_FILE"
