#!/bin/bash
# News Crawler Cron Script
# 自動生成されたシェルスクリプトです
# 生成日時: 2025-09-02 06:31:43

# スクリプトのディレクトリを取得
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# WordPressのパスを動的に取得（プラグインディレクトリから逆算）
WP_PATH="$(dirname "$(dirname "$(dirname "$SCRIPT_DIR")")")/"

# プラグインパスを設定
PLUGIN_PATH="$SCRIPT_DIR/"

# ログファイルのパス
LOG_FILE="$SCRIPT_DIR/news-crawler-cron.log"

# ログに実行開始を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行開始" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] スクリプトディレクトリ: $SCRIPT_DIR" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] WordPressパス: $WP_PATH" >> "$LOG_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] プラグインパス: $PLUGIN_PATH" >> "$LOG_FILE"

# WordPressのwp-cliが利用可能かチェック
if command -v wp &> /dev/null; then
    # wp-cliを使用してNews Crawlerを実行
    cd "$WP_PATH"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行中..." >> "$LOG_FILE"
    
    # News Crawlerの自動投稿機能を直接実行
    wp eval "
        if (class_exists('NewsCrawlerGenreSettings')) {
            \$genre_settings = new NewsCrawlerGenreSettings();
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
    php -r "
        require_once('wp-config.php');
        require_once('wp-includes/pluggable.php');
        if (class_exists('NewsCrawlerGenreSettings')) {
            \$genre_settings = new NewsCrawlerGenreSettings();
            \$genre_settings->execute_auto_posting();
            echo 'News Crawler自動投稿を実行しました';
        } else {
            echo 'News CrawlerGenreSettingsクラスが見つかりません';
        }
    " >> "$LOG_FILE" 2>&1
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でNews Crawlerを実行しました" >> "$LOG_FILE"
    
    # HTTPリクエストは使用しない（セキュリティチェックの問題のため）
    # else
    # wp-cliが利用できない場合は、HTTPリクエストでNews Crawlerを実行
    # WordPressの設定からサイトURLを動的に取得
    SITE_URL=$(cd "$WP_PATH" && php -r "
        if (file_exists('wp-config.php')) {
            require_once('wp-config.php');
            echo get_option('home', 'http://localhost');
        } else {
            echo 'http://localhost';
        }
    " 2>/dev/null)
    
        # サイトURLが取得できない場合は、wp-config.phpから直接取得を試行
    if [ -z "$SITE_URL" ] || [ "$SITE_URL" = "http://localhost" ]; then
        SITE_URL=$(cd "$WP_PATH" && php -r "
        if (file_exists('wp-config.php')) {
            \$config = file_get_contents('wp-config.php');
            if (preg_match('/define\s*\(\s*[\'\"\"]WP_HOME[\'\"\"]\s*,\s*[\'\"\"]([^\'\"\"]+)[\'\"\"]/', \$config, \$matches)) {
                echo \$matches[1];
            } elseif (preg_match('/define\s*\(\s*[\'\"\"]WP_SITEURL[\'\"\"]\s*,\s*[\'\"\"]([^\'\"\"]+)[\'\"\"]/', \$config, \$matches)) {
                echo \$matches[1];
            } else {
                echo 'http://localhost:8081';
            }
        } else {
            echo 'http://localhost:8081';
        }
    " 2>/dev/null)
    fi
    
    # Dockerコンテナ内からアクセスする場合は、ホストのIPアドレスを使用
    if [ "$SITE_URL" = "http://localhost:8081" ]; then
        SITE_URL="http://host.docker.internal:8081"
    fi
    
    CRON_URL="$SITE_URL/wp-admin/admin-ajax.php"
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 取得したサイトURL: $SITE_URL" >> "$LOG_FILE"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] HTTPリクエスト経由でNews Crawlerを実行中..." >> "$LOG_FILE"
    
    # News Crawlerの自動投稿機能をHTTPリクエストで実行
    # 正しいnonceを動的に生成
    CRON_NONCE=$(cd "$WP_PATH" && php -r "
        require_once('wp-config.php');
        require_once('wp-includes/pluggable.php');
        echo wp_create_nonce('news_crawler_cron_nonce');
    " 2>/dev/null)
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 生成されたnonce: $CRON_NONCE" >> "$LOG_FILE"
    
    curl -s -X POST "$CRON_URL" \
        -d "action=news_crawler_cron_execute" \
        -d "nonce=$CRON_NONCE" \
        >> "$LOG_FILE" 2>&1
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] HTTPリクエスト経由でNews Crawlerを実行しました" >> "$LOG_FILE"
fi

# ログに実行終了を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行終了" >> "$LOG_FILE"
echo "---" >> "$LOG_FILE"
