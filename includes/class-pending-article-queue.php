<?php
/**
 * ニュース記事の未投稿待ち行列
 *
 * ブログソースから一度に複数ヒットしても、投稿上限（1件/回など）で
 * 作れなかった記事を保持し、次回以降の自動投稿で消化する。
 *
 * @package News_Crawler
 */

if (!defined('ABSPATH')) {
    exit;
}

class News_Crawler_Pending_Article_Queue {

    const OPTION_KEY = 'news_crawler_pending_article_queue';

    /** 待ち行列の最大件数 */
    const MAX_ITEMS = 100;

    /** 保存する本文の最大文字数（option 肥大化防止） */
    const MAX_CONTENT_CHARS = 30000;

    /**
     * 待ち行列を取得
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_items() {
        $items = get_option(self::OPTION_KEY, array());
        if (!is_array($items)) {
            return array();
        }

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * 待ち行列を保存
     *
     * @param array<int, array<string, mixed>> $items アイテム
     */
    private static function save_items(array $items) {
        // 古い順を維持しつつ上限で切り詰め（先頭＝古い）
        if (count($items) > self::MAX_ITEMS) {
            $items = array_slice($items, -self::MAX_ITEMS);
        }
        update_option(self::OPTION_KEY, array_values($items), false);
    }

    /**
     * 件数
     *
     * @param string|null $genre_id ジャンル ID（null で全体）
     * @return int
     */
    public static function count($genre_id = null) {
        $items = self::get_items();
        if ($genre_id === null || $genre_id === '') {
            return count($items);
        }

        $genre_id = (string) $genre_id;
        $count = 0;
        foreach ($items as $item) {
            if ((string) ($item['genre_id'] ?? '') === $genre_id) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * URL が既に待ち行列にあるか
     *
     * @param string $url 記事 URL
     * @return bool
     */
    public static function has_url($url) {
        $url = self::normalize_url($url);
        if ($url === '') {
            return false;
        }

        foreach (self::get_items() as $item) {
            $item_url = self::normalize_url($item['article']['url'] ?? ($item['url'] ?? ''));
            if ($item_url !== '' && $item_url === $url) {
                return true;
            }
        }

        return false;
    }

    /**
     * 記事を待ち行列へ追加（重複 URL はスキップ）
     *
     * @param array $article 記事データ
     * @param array $context genre_id / categories / status
     * @return bool 追加したら true
     */
    public static function enqueue(array $article, array $context = array()) {
        $url = self::normalize_url($article['url'] ?? '');
        if ($url === '') {
            return false;
        }

        if (self::has_url($url)) {
            return false;
        }

        $items = self::get_items();
        $items[] = self::build_item($article, $context);
        self::save_items($items);

        return true;
    }

    /**
     * 複数記事を待ち行列へ追加
     *
     * @param array $articles 記事配列
     * @param array $context  コンテキスト
     * @return int 追加件数
     */
    public static function enqueue_many(array $articles, array $context = array()) {
        $added = 0;
        $items = self::get_items();
        $known_urls = array();

        foreach ($items as $item) {
            $u = self::normalize_url($item['article']['url'] ?? ($item['url'] ?? ''));
            if ($u !== '') {
                $known_urls[$u] = true;
            }
        }

        foreach ($articles as $article) {
            if (!is_array($article)) {
                continue;
            }
            $url = self::normalize_url($article['url'] ?? '');
            if ($url === '' || isset($known_urls[$url])) {
                continue;
            }

            $items[] = self::build_item($article, $context);
            $known_urls[$url] = true;
            $added++;
        }

        if ($added > 0) {
            self::save_items($items);
        }

        return $added;
    }

    /**
     * 待ち行列から 1 件取り出す（古い順。ジャンル指定可）
     *
     * @param string|null $genre_id ジャンル ID
     * @return array<string, mixed>|null
     */
    public static function dequeue($genre_id = null) {
        $items = self::get_items();
        if (empty($items)) {
            return null;
        }

        $genre_id = ($genre_id === null || $genre_id === '') ? null : (string) $genre_id;
        $found_index = null;

        foreach ($items as $index => $item) {
            if ($genre_id !== null && (string) ($item['genre_id'] ?? '') !== $genre_id) {
                continue;
            }
            $found_index = $index;
            break;
        }

        if ($found_index === null) {
            return null;
        }

        $item = $items[$found_index];
        array_splice($items, $found_index, 1);
        self::save_items($items);

        return $item;
    }

    /**
     * 投稿用に最大 $limit 件を取り出す（重複はコールバックで除外して次を試す）
     *
     * @param string|null $genre_id              ジャンル ID
     * @param int         $limit                 取得件数
     * @param callable|null $is_duplicate_callback function(array $article): bool
     * @return array<int, array> 記事データの配列
     */
    public static function dequeue_articles_for_posting($genre_id, $limit, $is_duplicate_callback = null) {
        $limit = max(0, (int) $limit);
        $articles = array();
        $attempts = 0;
        $max_attempts = max($limit * 5, 10);

        while (count($articles) < $limit && $attempts < $max_attempts) {
            $attempts++;
            $item = self::dequeue($genre_id);
            if ($item === null) {
                break;
            }

            $article = isset($item['article']) && is_array($item['article'])
                ? $item['article']
                : array();

            if (empty($article['url'])) {
                continue;
            }

            if (is_callable($is_duplicate_callback) && $is_duplicate_callback($article)) {
                continue;
            }

            // コンテキストを記事に付与（投稿作成側で参照可能）
            if (!empty($item['categories']) && is_array($item['categories'])) {
                $article['_queue_categories'] = $item['categories'];
            }
            if (!empty($item['status'])) {
                $article['_queue_status'] = $item['status'];
            }

            $articles[] = $article;
        }

        return $articles;
    }

    /**
     * URL で待ち行列から除去
     *
     * @param string $url URL
     * @return int 除去件数
     */
    public static function remove_by_url($url) {
        $url = self::normalize_url($url);
        if ($url === '') {
            return 0;
        }

        $items = self::get_items();
        $kept = array();
        $removed = 0;

        foreach ($items as $item) {
            $item_url = self::normalize_url($item['article']['url'] ?? ($item['url'] ?? ''));
            if ($item_url !== '' && $item_url === $url) {
                $removed++;
                continue;
            }
            $kept[] = $item;
        }

        if ($removed > 0) {
            self::save_items($kept);
        }

        return $removed;
    }

    /**
     * 管理画面向けの要約一覧
     *
     * @param int $limit 件数
     * @return array<int, array{title:string,url:string,genre_id:string,queued_at:string}>
     */
    public static function get_summary_list($limit = 20) {
        $limit = max(1, min(50, (int) $limit));
        $summary = array();

        foreach (array_slice(self::get_items(), 0, $limit) as $item) {
            $article = isset($item['article']) && is_array($item['article']) ? $item['article'] : array();
            $summary[] = array(
                'title' => (string) ($article['title'] ?? '(無題)'),
                'url' => (string) ($article['url'] ?? ''),
                'genre_id' => (string) ($item['genre_id'] ?? ''),
                'queued_at' => (string) ($item['queued_at'] ?? ''),
            );
        }

        return $summary;
    }

    /**
     * 待ち行列アイテムを組み立て
     *
     * @param array $article  記事
     * @param array $context  コンテキスト
     * @return array<string, mixed>
     */
    private static function build_item(array $article, array $context) {
        $content = isset($article['content']) ? (string) $article['content'] : '';
        if (function_exists('mb_substr')) {
            $content = mb_substr($content, 0, self::MAX_CONTENT_CHARS);
        } else {
            $content = substr($content, 0, self::MAX_CONTENT_CHARS);
        }

        $safe_article = array(
            'title' => isset($article['title']) ? (string) $article['title'] : '',
            'content' => $content,
            'description' => isset($article['description']) ? (string) $article['description'] : '',
            'url' => self::normalize_url($article['url'] ?? ''),
            'published_at' => isset($article['published_at']) ? (string) $article['published_at'] : '',
            'author' => isset($article['author']) ? (string) $article['author'] : '',
            'source' => isset($article['source']) ? (string) $article['source'] : '',
            'excerpt' => isset($article['excerpt']) ? (string) $article['excerpt'] : '',
        );

        if (!empty($article['categories']) && is_array($article['categories'])) {
            $safe_article['categories'] = array_values(array_map('strval', $article['categories']));
        }

        return array(
            'id' => uniqid('ncpaq_', true),
            'queued_at' => current_time('mysql'),
            'genre_id' => isset($context['genre_id']) ? (string) $context['genre_id'] : '',
            'categories' => isset($context['categories']) && is_array($context['categories'])
                ? array_values($context['categories'])
                : array('blog'),
            'status' => isset($context['status']) ? sanitize_text_field((string) $context['status']) : 'draft',
            'article' => $safe_article,
            'url' => $safe_article['url'],
        );
    }

    /**
     * URL 正規化
     *
     * @param string $url URL
     * @return string
     */
    private static function normalize_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        return untrailingslashit($url);
    }
}
