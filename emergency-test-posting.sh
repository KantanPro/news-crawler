#!/bin/bash
# 緊急自動投稿テストスクリプト
# 修正されたロジックで実際に投稿が成功するかテスト

echo "=== 緊急自動投稿テスト開始 ==="
echo "実行時刻: $(date '+%Y-%m-%d %H:%M:%S')"

# 1. 既存のロックをクリア
echo "既存のロックをクリア中..."
rm -f /tmp/news-crawler-cron.lock 2>/dev/null || true

# 2. WordPressのtransientロックをクリア
echo "WordPress transientロックをクリア中..."
cd /virtual/kantan/public_html/wp-content/plugins/news-crawler
/usr/local/bin/php -r "
require_once('/virtual/kantan/public_html/wp-load.php');
delete_transient('news_crawler_auto_posting_lock');
echo 'Transient locks cleared' . PHP_EOL;
" 2>/dev/null || true

# 3. ジャンル設定をデバッグ
echo "ジャンル設定をデバッグ中..."
/usr/local/bin/php debug-genre-settings-production.php

# 4. 修正されたCronスクリプトを実行
echo "修正されたCronスクリプトを実行中..."
./news-crawler-cron.sh

echo "=== 緊急テスト完了 ==="
echo "完了時刻: $(date '+%Y-%m-%d %H:%M:%S')"
