#!/bin/bash
# News Crawler Cron Fix Deployment Script
# 本番環境への緊急修正デプロイメントスクリプト

set -euo pipefail

echo "=== News Crawler Cron Fix Deployment ==="
echo "日時: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# 本番環境のパス設定
PRODUCTION_PATH="/virtual/kantan/public_html/wp-content/plugins/news-crawler"
BACKUP_PATH="/virtual/kantan/public_html/wp-content/plugins/news-crawler/backup-$(date +%Y%m%d-%H%M%S)"

echo "1. 本番環境のパス確認..."
if [ ! -d "$PRODUCTION_PATH" ]; then
    echo "エラー: 本番環境のパスが見つかりません: $PRODUCTION_PATH"
    exit 1
fi
echo "✅ 本番環境パス確認完了: $PRODUCTION_PATH"

echo ""
echo "2. 現在のCronジョブを一時停止..."
crontab -r 2>/dev/null || echo "Cronジョブが存在しません（正常）"
echo "✅ Cronジョブを一時停止しました"

echo ""
echo "3. ロックファイルを強制削除..."
rm -f /tmp/news-crawler-cron.lock
echo "✅ ロックファイルを削除しました"

echo ""
echo "4. 現在のスクリプトをバックアップ..."
mkdir -p "$BACKUP_PATH"
cp "$PRODUCTION_PATH/news-crawler-cron.sh" "$BACKUP_PATH/" 2>/dev/null || echo "既存のスクリプトが見つかりません（新規作成）"
echo "✅ バックアップ完了: $BACKUP_PATH"

echo ""
echo "5. 修正版スクリプトをデプロイ..."
# このスクリプトと同じディレクトリの修正版をコピー
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "$SCRIPT_DIR/news-crawler-cron.sh" ]; then
    cp "$SCRIPT_DIR/news-crawler-cron.sh" "$PRODUCTION_PATH/"
    chmod +x "$PRODUCTION_PATH/news-crawler-cron.sh"
    echo "✅ 修正版スクリプトをデプロイしました"
else
    echo "エラー: 修正版スクリプトが見つかりません: $SCRIPT_DIR/news-crawler-cron.sh"
    exit 1
fi

echo ""
echo "6. 修正版Cronジョブを設定..."
echo "10 10 * * * $PRODUCTION_PATH/news-crawler-cron.sh" | crontab -
echo "✅ Cronジョブを設定しました"

echo ""
echo "7. 設定確認..."
echo "現在のCronジョブ:"
crontab -l
echo ""

echo "8. スクリプトの動作テスト..."
echo "修正版スクリプトのテスト実行..."
if [ -f "$PRODUCTION_PATH/news-crawler-cron.sh" ]; then
    echo "スクリプトの構文チェック..."
    bash -n "$PRODUCTION_PATH/news-crawler-cron.sh"
    echo "✅ スクリプトの構文チェック完了"
else
    echo "エラー: デプロイされたスクリプトが見つかりません"
    exit 1
fi

echo ""
echo "=== デプロイメント完了 ==="
echo "日時: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
echo "次のCron実行時刻: 明日 10:10"
echo "ログファイル: $PRODUCTION_PATH/news-crawler-cron.log"
echo "バックアップ: $BACKUP_PATH"
echo ""
echo "⚠️  重要: 次のCron実行まで待機して、ログを確認してください"
