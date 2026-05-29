# WooCommerce 商品説明（News Crawler）

WooCommerce の商品編集画面に貼り付けてください。  
**バージョン番号は商品説明に書かず**、ZIP 差し替え時も文言のまま使えます（機能の大幅変更時のみ本文を見直してください）。

- **商品タイプ:** シンプル / バーチャル / ダウンロード可能
- **推奨価格（税込）:** ¥9,800（1サイト・買い切り）
- **利用規約:** https://www.kantanpro.com/news-crawler-kiyaku

---

## 商品の短い説明（抜粋）

```
ニュース・YouTube・AI要約・アイキャッチ・SEOタイトル・cron自動投稿・X自動シェアまで、WordPressの記事更新をほったらかし運用できるプラグイン（1サイト買い切り）。プラグイン内ライセンスキー不要。個別サポートなし。バージョンはダウンロード時点の最新版。
```

---

## 商品説明（HTML・長文）

```html
<h2>News Crawler — WordPress 自動投稿プラグイン</h2>

<p>ニュースサイト・RSS・YouTube から記事を集め、AI で要約し、アイキャッチと SEO タイトルを付けて WordPress に投稿。<strong>サーバー cron で定期実行</strong>し、公開と同時に <strong>X（旧 Twitter）へ自動シェア</strong>まで行えます。一度設定すれば、日々の更新作業を大幅に減らす<strong>ほったらかし運用</strong>向けのプラグインです。</p>

<h3>できること</h3>
<ul>
<li><strong>ニュース・RSS クロール</strong> — 指定ソースからキーワードに合う記事を取得し投稿</li>
<li><strong>YouTube 連携</strong> — チャンネル ID・チャンネル URL・@ハンドルで指定。動画の埋め込みと要約付き投稿</li>
<li><strong>AI 要約</strong> — OpenAI API で本文要約（API キーはご自身で取得・設定）</li>
<li><strong>アイキャッチ自動生成</strong> — DALL-E / Unsplash / テンプレートなど</li>
<li><strong>SEO タイトル自動生成</strong> — 記事・動画の内容に沿ったタイトル案</li>
<li><strong>自動投稿（cron）</strong> — サーバー側 cron の設定が必要</li>
<li><strong>X 自動シェア</strong> — OAuth 2.0 接続後、投稿公開時にシェア（X Developer アカウント・API 利用はご自身の責任）</li>
<li><strong>ジャンル別設定</strong> — ソース・キーワード・投稿先カテゴリーなどを複数パターン管理</li>
</ul>

<h3>このプラグインの特徴</h3>
<ul>
<li><strong>プラグイン内ライセンスキー不要</strong> — 購入後すぐ全機能を利用可能（自動投稿・X 連携を含む）</li>
<li><strong>GPL v2 or later</strong> で提供（ソースの再配布等は GPL に従います）</li>
<li><strong>YouTube チャンネル</strong>を ID・URL・@ハンドルのいずれかで設定可能</li>
<li>404・削除済みページなどを記事として投稿しないフィルタ</li>
<li>不具合修正・機能追加のアップデート版（ZIP）を随時提供（時期・内容は当社裁量）</li>
</ul>

<h3>こんな方に</h3>
<ul>
<li>ブログやメディアの更新を自動化したい</li>
<li>ニュースまとめ・業界動向サイトを運営している</li>
<li>YouTube チャンネルとブログを連動させたい</li>
<li>公開と X 告知までまとめて任せたい</li>
</ul>

<h3>ご購入にあたって（必ずお読みください）</h3>
<ul>
<li><strong>対応環境:</strong> WordPress 5.0 以上、PHP 7.4 以上（詳細はダウンロード ZIP 内の readme.txt を参照）</li>
<li><strong>1回の購入で利用できるサイト:</strong> 1 WordPress インストール（1サイト）。複数サイト利用は別プラン・利用規約の範囲内</li>
<li><strong>外部 API:</strong> OpenAI・YouTube Data API・X 等は<strong>利用者ご自身の API キー・利用料金</strong>が必要です</li>
<li><strong>自動投稿:</strong> サーバー cron（または同等の定期実行）の設定が必要です。当社がサーバー設定を代行することはありません</li>
<li><strong>サポート:</strong> <strong>個別サポート（インストール・設定・運用の問い合わせ）は提供しません</strong></li>
<li><strong>アップデート:</strong> 不具合修正・機能追加を目的とした ZIP の提供のみ（時期・回数は保証しません）。再ダウンロード可能な期間・回数は商品ページの表記に従います</li>
<li><strong>返金:</strong> ダウンロード商品のため、原則返金不可（法令上必要な場合を除く）</li>
<li><strong>利用規約:</strong> <a href="https://www.kantanpro.com/news-crawler-kiyaku" target="_blank" rel="noopener">News Crawler 利用規約</a> に同意のうえご利用ください</li>
<li><strong>GPL:</strong> 購入者に提供したソースは GPL の下で再配布される場合があります。当社提供の<strong>販売用 ZIP の再販・無断再配布は禁止</strong>です（利用規約参照）</li>
</ul>

<h3>納品方法</h3>
<p>ご注文完了後、<strong>ダウンロードリンク</strong>から ZIP を取得してください。解凍後のフォルダ名は <code>news-crawler</code> です。含まれるバージョンは<strong>ダウンロード時点の最新版</strong>です（管理画面のプラグイン一覧でもバージョンを確認できます）。WordPress 管理画面の「プラグイン → 新規追加 → プラグインのアップロード」からインストールし、有効化してください。</p>

<h3>初期設定の流れ（概要）</h3>
<ol>
<li>プラグインをインストール・有効化</li>
<li><strong>News Crawler → 基本設定</strong>で OpenAI・YouTube API キーを設定</li>
<li><strong>投稿設定</strong>でニュースソース・キーワード・YouTube チャンネル等を登録</li>
<li><strong>自動投稿設定</strong>で cron コマンドをサーバーに登録</li>
<li>（任意）<strong>X 自動シェア</strong>を基本設定タブから接続</li>
</ol>

<p><small>開発: <a href="https://www.kantanpro.com" target="_blank" rel="noopener">KantanPro</a></small></p>
```

---

## 商品データの推奨設定

| 項目 | 値 |
|------|-----|
| 名前 | News Crawler（WordPress プラグイン）買い切り 1サイト |
| スラッグ | `news-crawler` |
| 通常価格 | 9800 |
| ダウンロードファイル | リリースごとに ZIP を差し替え（ファイル名にバージョンを含めても可） |
| ダウンロード制限 | 5 回程度（任意） |
| 有効期限 | 365 日（任意） |

### リリース時の運用メモ

- 商品説明の HTML は**原則そのまま**でよい
- 新機能をアピールしたいときだけ「できること」「特徴」に追記
- バージョンは WooCommerce のダウンロードファイル差し替えと、購入者への案内（メール・マイアカウント）で伝える
