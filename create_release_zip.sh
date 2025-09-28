#!/bin/bash

# News Crawler プラグイン リリース用ZIPファイル作成スクリプト
# 使用方法: ./create_release_zip.sh

# プラグイン名とバージョンを取得
PLUGIN_NAME="news-crawler"
VERSION=$(grep "Version:" news-crawler.php | sed 's/.*Version: //' | tr -d ' ')
TODAY=$(date +%Y%m%d)
RELEASE_NAME="${PLUGIN_NAME}_${VERSION}_${TODAY}"

echo "=== News Crawler プラグイン リリースZIP作成 ==="
echo "プラグイン名: ${PLUGIN_NAME}"
echo "バージョン: ${VERSION}"
echo "本日の日付: ${TODAY}"
echo "リリース名: ${RELEASE_NAME}"
echo ""

# 指定されたディレクトリに既存のZIPファイルがあるかチェック
TARGET_DIR="/Users/kantanpro/Desktop/Game_TEST_UP"
if [ -f "${TARGET_DIR}/${RELEASE_NAME}.zip" ]; then
    echo "既存のZIPファイルを削除中: ${TARGET_DIR}/${RELEASE_NAME}.zip"
    rm "${TARGET_DIR}/${RELEASE_NAME}.zip"
fi

# 一時ディレクトリを作成
TEMP_DIR="/tmp/${PLUGIN_NAME}"
if [ -d "${TEMP_DIR}" ]; then
    echo "一時ディレクトリを削除中: ${TEMP_DIR}"
    rm -rf "${TEMP_DIR}"
fi

echo "一時ディレクトリを作成中: ${TEMP_DIR}"
mkdir -p "${TEMP_DIR}"

# 配布サイト用に必要最低限のファイルとディレクトリをコピー
echo "ファイルをコピー中..."
cp -r includes "${TEMP_DIR}/"
cp -r assets "${TEMP_DIR}/"
cp -r languages "${TEMP_DIR}/"
cp news-crawler.php "${TEMP_DIR}/"
cp readme.txt "${TEMP_DIR}/"
cp news-crawler-cron.sh "${TEMP_DIR}/"

# 不要なファイルを除外（配布サイト用に最適化）
echo "不要なファイルを除外中..."
find "${TEMP_DIR}" -name ".git*" -type d -exec rm -rf {} + 2>/dev/null || true
find "${TEMP_DIR}" -name ".DS_Store" -type f -delete 2>/dev/null || true
# news-crawler-cron.shは自動投稿に必要不可欠なため除外しない
# find "${TEMP_DIR}" -name "*.sh" -type f -delete 2>/dev/null || true
find "${TEMP_DIR}" -name "*.zip" -type f -delete 2>/dev/null || true
find "${TEMP_DIR}" -name "*.backup" -type f -delete 2>/dev/null || true
find "${TEMP_DIR}" -name "*.tmp" -type f -delete 2>/dev/null || true

# ZIPファイルを作成（解凍後のフォルダ名はプラグイン名のみ）
echo "ZIPファイルを作成中: ${RELEASE_NAME}.zip"
cd /tmp
zip -r "${RELEASE_NAME}.zip" "${PLUGIN_NAME}/" > /dev/null

# 指定されたディレクトリにZIPファイルを移動
mkdir -p "${TARGET_DIR}"
mv "${RELEASE_NAME}.zip" "${TARGET_DIR}/"

# 一時ディレクトリを削除
echo "一時ディレクトリを削除中..."
rm -rf "${TEMP_DIR}"

# 完了メッセージ
echo ""
echo "=== 完了 ==="
echo "リリースZIPファイルが作成されました: ${TARGET_DIR}/${RELEASE_NAME}.zip"
echo "ファイルサイズ: $(du -h "${TARGET_DIR}/${RELEASE_NAME}.zip" | cut -f1)"
echo "解凍後のフォルダ名: ${PLUGIN_NAME}"
echo ""
echo "このZIPファイルをWordPressプラグインとしてアップロードできます。"
echo "配布サイト用に最適化されています。"
