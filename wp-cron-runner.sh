#!/bin/bash

# WordPressの内部cronシステムを実行するスクリプト
# このスクリプトは定期的にWordPressの内部cronシステムを実行します

# ログファイルのパス
LOG_FILE="/Users/kantanpro/Desktop/KantanPro/wordpress/wp-content/plugins/news-crawler/wp-cron.log"

# ログに実行開始時刻を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] WordPress内部cronシステム実行開始" >> "$LOG_FILE"

# Dockerコンテナ内でWordPressの内部cronシステムを実行
docker exec KantanPro_wordpress php -r "
require_once('/var/www/html/wp-load.php');
wp_cron();
echo 'WordPressの内部cronシステムを実行しました' . PHP_EOL;
" >> "$LOG_FILE" 2>&1

# ログに実行終了時刻を記録
echo "[$(date '+%Y-%m-%d %H:%M:%S')] WordPress内部cronシステム実行終了" >> "$LOG_FILE"
echo "---" >> "$LOG_FILE"
