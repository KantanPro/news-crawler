=== News Crawler ===
Contributors: KantanPro
Tags: news, crawler, youtube, automation, content
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.9.3
License: MIT
License URI: https://opensource.org/licenses/MIT

指定されたニュースソースから自動的に記事を取得し、WordPressサイトに投稿として追加するプラグイン。YouTube動画のクロール機能も含む。

== Description ==

News Crawlerは、指定されたニュースソースから自動的に記事を取得し、WordPressサイトに投稿として追加するプラグインです。

**主な機能:**

* ニュースソースからの自動記事取得
* YouTube動画の自動クロール
* アイキャッチ画像の自動生成
* AI要約の自動生成
* ジャンル別設定管理

* YouTube APIのクォータ制限対応

**新機能（v1.9.3）:**
* YouTube APIのクォータ制限対応

== Installation ==

1. プラグインファイルを `/wp-content/plugins/news-crawler/` ディレクトリにアップロードします
2. WordPressの管理画面でプラグインを有効化します
3. 設定画面で必要なAPIキーを設定します

== Frequently Asked Questions ==

= どのようなニュースソースに対応していますか？ =

RSSフィード、YouTubeチャンネル、その他のAPI対応サービスに対応しています。

= YouTube APIのクォータ制限はどのように対応していますか？ =

v1.9.0から、APIクォータの使用状況を監視し、制限に達した際の適切な処理を実装しています。

= X（旧Twitter）への自動投稿はどのように設定しますか？ =

管理画面のSNS設定から、X APIキーを設定することで自動投稿が可能になります。

== Screenshots ==

1. 管理画面のメインページ
2. ジャンル設定画面
3. YouTubeクローラー設定
4. アイキャッチ生成設定

== Changelog ==

= 1.9.3 =
* YouTube APIのクォータ制限対応
* その他のバグ修正とパフォーマンス向上

= 1.8.0 =
* アイキャッチ画像生成機能を追加
* AI要約生成機能を追加
* ジャンル別設定管理を改善

= 1.7.0 =
* YouTubeクローラー機能を追加
* 管理画面のUIを改善

= 1.6.0 =
* 基本的なニュースクロール機能を実装
* 管理画面の基本構造を追加

== Upgrade Notice ==

= 1.9.3 =
YouTube APIクォータ制限対応が追加された重要なアップデートです。既存の設定は保持されます。
