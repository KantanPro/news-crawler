#!/bin/bash

# News Crawler プラグイン リリース用ZIPファイル作成スクリプト
# 使用方法: ./create_release_zip.sh

# プラグイン名とバージョンを取得
PLUGIN_NAME="news-crawler"
VERSION=$(grep "Version:" news-crawler.php | sed 's/.*Version: //' | tr -d ' ')
RELEASE_NAME="${PLUGIN_NAME}-${VERSION}"

echo "=== News Crawler プラグイン リリースZIP作成 ==="
echo "プラグイン名: ${PLUGIN_NAME}"
echo "バージョン: ${VERSION}"
echo "リリース名: ${RELEASE_NAME}"
echo ""

# 既存のZIPファイルを削除
if [ -f "${RELEASE_NAME}.zip" ]; then
    echo "既存のZIPファイルを削除中: ${RELEASE_NAME}.zip"
    rm "${RELEASE_NAME}.zip"
fi

# 一時ディレクトリを作成
TEMP_DIR="/tmp/${RELEASE_NAME}"
if [ -d "${TEMP_DIR}" ]; then
    echo "一時ディレクトリを削除中: ${TEMP_DIR}"
    rm -rf "${TEMP_DIR}"
fi

echo "一時ディレクトリを作成中: ${TEMP_DIR}"
mkdir -p "${TEMP_DIR}"

# 必要なファイルとディレクトリをコピー
echo "ファイルをコピー中..."
cp -r includes "${TEMP_DIR}/"
cp -r assets "${TEMP_DIR}/"
cp -r languages "${TEMP_DIR}/"
cp news-crawler.php "${TEMP_DIR}/"
cp readme.txt "${TEMP_DIR}/"
cp README.md "${TEMP_DIR}/"

# .gitディレクトリと.DS_Storeファイルを除外
echo "不要なファイルを除外中..."
find "${TEMP_DIR}" -name ".git*" -type d -exec rm -rf {} + 2>/dev/null || true
find "${TEMP_DIR}" -name ".DS_Store" -type f -delete 2>/dev/null || true
find "${TEMP_DIR}" -name "*.sh" -type f -delete 2>/dev/null || true

# ZIPファイルを作成
echo "ZIPファイルを作成中: ${RELEASE_NAME}.zip"
cd /tmp
zip -r "${RELEASE_NAME}.zip" "${RELEASE_NAME}/" > /dev/null

# 現在のディレクトリにZIPファイルを移動
mv "${RELEASE_NAME}.zip" "/Users/kantanpro/Desktop/KantanPro/wordpress/wp-content/plugins/news-crawler/"

# 一時ディレクトリを削除
echo "一時ディレクトリを削除中..."
rm -rf "${TEMP_DIR}"

# 完了メッセージ
echo ""
echo "=== 完了 ==="
echo "リリースZIPファイルが作成されました: ${RELEASE_NAME}.zip"
echo "ファイルサイズ: $(du -h "${RELEASE_NAME}.zip" | cut -f1)"
echo ""
echo "このZIPファイルをWordPressプラグインとしてアップロードできます。"
