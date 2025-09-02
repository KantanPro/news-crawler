#!/bin/bash
# News Crawler Cron Script
# 自動生成されたシェルスクリプトです
# 生成日時: 2025-09-02 06:31:43

# WordPressのパスを設定
WP_PATH="/var/www/html/"

# プラグインパスを設定
PLUGIN_PATH="/var/www/html/wp-content/plugins/news-crawler/"

# ログファイルのパス
LOG_FILE="/var/www/html/wp-content/plugins/news-crawler/news-crawler-cron.log"

# ログに実行開始を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行開始" >> "$LOG_FILE"

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
    # wp-cliが利用できない場合は、HTTPリクエストでNews Crawlerを実行
    SITE_URL="http://localhost:8081"
    CRON_URL="$SITE_URL/wp-admin/admin-ajax.php"
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] HTTPリクエスト経由でNews Crawlerを実行中..." >> "$LOG_FILE"
    
    # News Crawlerの自動投稿機能をHTTPリクエストで実行
    curl -s -X POST "$CRON_URL" \
        -d "action=news_crawler_cron_execute" \
        -d "nonce=c8e1e7f9b8" \
        >> "$LOG_FILE" 2>&1
    
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] HTTPリクエスト経由でNews Crawlerを実行しました" >> "$LOG_FILE"
fi

# ログに実行終了を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行終了" >> "$LOG_FILE"
echo "---" >> "$LOG_FILE"
