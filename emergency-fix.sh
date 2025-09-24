#!/bin/bash
# News Crawler Emergency Fix Script
# 緊急時の重複実行停止スクリプト

set -euo pipefail

echo "=== News Crawler 緊急修正スクリプト ==="
echo "日時: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

echo "1. 全てのCronジョブを停止..."
crontab -r 2>/dev/null || echo "Cronジョブが存在しません"
echo "✅ 全てのCronジョブを停止しました"

echo ""
echo "2. 全てのロックファイルを削除..."
rm -f /tmp/news-crawler-cron.lock
rm -f /tmp/news-crawler-*.lock
echo "✅ ロックファイルを削除しました"

echo ""
echo "3. 実行中のNews Crawlerプロセスを確認..."
RUNNING_PROCESSES=$(ps aux | grep -i "news-crawler\|news_crawler" | grep -v grep | wc -l)
if [ "$RUNNING_PROCESSES" -gt 0 ]; then
    echo "⚠️  実行中のNews Crawlerプロセスが $RUNNING_PROCESSES 個見つかりました"
    echo "実行中のプロセス:"
    ps aux | grep -i "news-crawler\|news_crawler" | grep -v grep
    echo ""
    echo "これらのプロセスを強制終了しますか？ (y/N)"
    read -r response
    if [[ "$response" =~ ^[Yy]$ ]]; then
        pkill -f "news-crawler\|news_crawler" || echo "プロセスが見つかりませんでした"
        echo "✅ プロセスを強制終了しました"
    else
        echo "⚠️  プロセスの強制終了をスキップしました"
    fi
else
    echo "✅ 実行中のNews Crawlerプロセスはありません"
fi

echo ""
echo "4. ログファイルのサイズ確認..."
LOG_FILE="/virtual/kantan/public_html/wp-content/plugins/news-crawler/news-crawler-cron.log"
if [ -f "$LOG_FILE" ]; then
    LOG_SIZE=$(du -h "$LOG_FILE" | cut -f1)
    echo "ログファイルサイズ: $LOG_SIZE"
    if [ -f "$LOG_FILE" ] && [ $(stat -c%s "$LOG_FILE") -gt 10485760 ]; then  # 10MB以上
        echo "⚠️  ログファイルが大きすぎます。バックアップしてクリアしますか？ (y/N)"
        read -r response
        if [[ "$response" =~ ^[Yy]$ ]]; then
            cp "$LOG_FILE" "${LOG_FILE}.backup-$(date +%Y%m%d-%H%M%S)"
            > "$LOG_FILE"
            echo "✅ ログファイルをバックアップしてクリアしました"
        fi
    fi
else
    echo "ログファイルが見つかりません"
fi

echo ""
echo "5. WordPress Transient ロックをクリア..."
# WordPressの設定が必要な場合のための準備
echo "WordPressのTransientロックをクリアするには、以下のコマンドを実行してください:"
echo "mysql -u [username] -p[password] [database_name] -e \"DELETE FROM wp_options WHERE option_name LIKE '%news_crawler%lock%';\""
echo ""

echo "6. システムリソース確認..."
echo "メモリ使用量:"
free -h
echo ""
echo "CPU使用率:"
top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1
echo ""

echo "=== 緊急修正完了 ==="
echo "日時: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
echo "次のステップ:"
echo "1. 修正版スクリプトをデプロイ: ./deploy-cron-fix.sh"
echo "2. システムの安定化を待つ（5-10分）"
echo "3. 修正版Cronジョブを再設定"
echo "4. ログを監視して正常動作を確認"
