#!/bin/bash
# 緊急Cron停止スクリプト
# 全てのNews Crawler関連プロセスとロックを強制停止

echo "=== 緊急Cron停止スクリプト実行開始 ==="
echo "実行時刻: $(date '+%Y-%m-%d %H:%M:%S')"

# 1. 全てのNews Crawler関連プロセスを強制終了
echo "News Crawler関連プロセスを停止中..."
pkill -f "news-crawler-cron" 2>/dev/null || true
pkill -f "News Crawler" 2>/dev/null || true
pkill -f "news-crawler" 2>/dev/null || true

# 2. 全てのロックファイルを削除
echo "ロックファイルを削除中..."
rm -f /tmp/news-crawler-cron.lock 2>/dev/null || true
rm -f /tmp/news-crawler-*.lock 2>/dev/null || true
rm -f /export/tmp/news-crawler-*.lock 2>/dev/null || true

# 3. WordPressのtransientロックをクリア
echo "WordPress transientロックをクリア中..."
cd /virtual/kantan/public_html/wp-content/plugins/news-crawler
/usr/local/bin/php -r "
require_once('/virtual/kantan/public_html/wp-load.php');
delete_transient('news_crawler_auto_posting_lock');
delete_transient('news_crawler_genre_lock_*');
echo 'Transient locks cleared' . PHP_EOL;
" 2>/dev/null || true

# 4. 実行中のCronジョブを確認
echo "実行中のCronジョブを確認中..."
ps aux | grep -i "news-crawler" | grep -v grep || echo "News Crawler関連プロセスは見つかりませんでした"

echo "=== 緊急停止完了 ==="
echo "完了時刻: $(date '+%Y-%m-%d %H:%M:%S')"
