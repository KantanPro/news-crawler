#!/bin/bash

# News Crawler プラグインの配布用zipファイル作成スクリプト

# 設定
PLUGIN_NAME="news-crawler"
PLUGIN_VERSION="1.3.0"
TODAY=$(date +%Y%m%d)
OUTPUT_DIR="/Users/kantanpro/Desktop/Game_TEST_UP"
ZIP_FILENAME="${PLUGIN_NAME}_${PLUGIN_VERSION}_${TODAY}.zip"

# 出力ディレクトリが存在しない場合は作成
if [ ! -d "$OUTPUT_DIR" ]; then
    echo "出力ディレクトリを作成中: $OUTPUT_DIR"
    mkdir -p "$OUTPUT_DIR"
fi

# 現在のディレクトリを保存
CURRENT_DIR=$(pwd)

# 一時作業ディレクトリを作成
TEMP_DIR=$(mktemp -d)
echo "一時作業ディレクトリを作成: $TEMP_DIR"

# プラグインディレクトリを作成
PLUGIN_DIR="$TEMP_DIR/$PLUGIN_NAME"
mkdir -p "$PLUGIN_DIR"

# 配布に必要なファイルのみをコピー
echo "配布用ファイルをコピー中..."

# メインプラグインファイル
cp "$PLUGIN_NAME.php" "$PLUGIN_DIR/"

# READMEファイル
if [ -f "README.md" ]; then
    cp "README.md" "$PLUGIN_DIR/"
fi

if [ -f "readme.txt" ]; then
    cp "readme.txt" "$PLUGIN_DIR/"
fi

# 言語ファイル（存在する場合）
if [ -d "languages" ]; then
    cp -r "languages" "$PLUGIN_DIR/"
fi

# アセットファイル（存在する場合）
if [ -d "assets" ]; then
    cp -r "assets" "$PLUGIN_DIR/"
fi

# インクルードファイル（存在する場合）
if [ -d "includes" ]; then
    cp -r "includes" "$PLUGIN_DIR/"
fi

# テストファイルは除外（配布に不要）
# test-news.html はコピーしない

echo "配布用ファイルのコピーが完了しました"

# zipファイルを作成
echo "zipファイルを作成中: $ZIP_FILENAME"
cd "$TEMP_DIR"
zip -r "$ZIP_FILENAME" "$PLUGIN_NAME"

# 作成されたzipファイルを指定されたディレクトリに移動
mv "$ZIP_FILENAME" "$OUTPUT_DIR/"

# 一時ディレクトリを削除
cd "$CURRENT_DIR"
rm -rf "$TEMP_DIR"

# 結果を表示
echo ""
echo "=== 配布用zipファイルの作成が完了しました ==="
echo "ファイル名: $ZIP_FILENAME"
echo "保存先: $OUTPUT_DIR"
echo "ファイルサイズ: $(du -h "$OUTPUT_DIR/$ZIP_FILENAME" | cut -f1)"
echo ""

# 作成されたzipファイルの内容を確認
echo "zipファイルの内容:"
unzip -l "$OUTPUT_DIR/$ZIP_FILENAME" | head -20

echo ""
echo "配布用zipファイルの作成が完了しました！"
