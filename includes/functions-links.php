<?php
/**
 * 外部リンク（利用規約等）ヘルパー
 *
 * @package News_Crawler
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('news_crawler_terms_url')) {
    /**
     * News Crawler 利用規約ページ URL
     *
     * @return string
     */
    function news_crawler_terms_url() {
        if (defined('NEWS_CRAWLER_TERMS_URL') && NEWS_CRAWLER_TERMS_URL !== '') {
            return NEWS_CRAWLER_TERMS_URL;
        }

        return 'https://www.kantanpro.com/news-crawler-kiyaku';
    }
}

if (!function_exists('news_crawler_is_admin_screen')) {
    /**
     * 現在の管理画面が News Crawler 関連か
     *
     * @return bool
     */
    function news_crawler_is_admin_screen() {
        if (!function_exists('get_current_screen')) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen || empty($screen->id)) {
            return false;
        }

        return (strpos($screen->id, 'news-crawler') !== false);
    }
}

if (!function_exists('news_crawler_admin_body_class')) {
    /**
     * News Crawler 管理画面用 body クラス
     *
     * @param string $classes 既存クラス.
     * @return string
     */
    function news_crawler_admin_body_class($classes) {
        if (news_crawler_is_admin_screen()) {
            $classes .= ' news-crawler-admin-screen';
        }

        return $classes;
    }
}

if (!function_exists('news_crawler_enqueue_admin_footer_styles')) {
    /**
     * 利用規約表示（コンテンツ末尾・右寄せ）用スタイル
     *
     * @param string $hook_suffix 現在の管理画面フック.
     */
    function news_crawler_enqueue_admin_footer_styles($hook_suffix) {
        if (strpos($hook_suffix, 'news-crawler') === false) {
            return;
        }

        $css = '
            body.news-crawler-admin-screen .news-crawler-admin-terms-footer {
                display: block;
                box-sizing: border-box;
                width: 100%;
                margin: 20px 0 4px;
                padding: 0;
                color: #50575e;
                font-size: 12px;
                line-height: 1.5;
                text-align: right;
            }
        ';

        wp_add_inline_style('wp-admin', $css);
    }
}

if (!function_exists('news_crawler_render_terms_footer')) {
    /**
     * 設定画面コンテンツ末尾用の利用規約表示（admin_footer で出力し .wrap 末尾へ移動）
     */
    function news_crawler_render_terms_footer() {
        if (!news_crawler_is_admin_screen()) {
            return;
        }

        $terms_url = news_crawler_terms_url();
        ?>
        <p class="news-crawler-admin-terms-footer" hidden>
            <?php
            printf(
                /* translators: 1: opening anchor, 2: closing anchor */
                esc_html__('本プラグインのご利用には %1$s利用規約%2$s に同意したものとみなします。個別の技術サポートは提供しておりません。', 'news-crawler'),
                '<a href="' . esc_url($terms_url) . '" target="_blank" rel="noopener noreferrer">',
                '</a>'
            );
            ?>
        </p>
        <?php
    }
}

if (!function_exists('news_crawler_place_terms_footer_script')) {
    /**
     * 利用規約を最後のコンテンツブロック直下（.wrap 末尾）へ配置
     */
    function news_crawler_place_terms_footer_script() {
        if (!news_crawler_is_admin_screen()) {
            return;
        }
        ?>
        <script>
        (function () {
            var terms = document.querySelector('.news-crawler-admin-terms-footer');
            if (!terms) {
                return;
            }

            var wrap = document.querySelector('#wpbody-content .wrap');
            if (!wrap) {
                return;
            }

            function placeBelow(el) {
                if (!el) {
                    return false;
                }
                el.insertAdjacentElement('afterend', terms);
                return true;
            }

            var placed =
                placeBelow(wrap.querySelector('.ktp-admin-content')) ||
                placeBelow(wrap.querySelector('#genre-settings-container')) ||
                placeBelow(wrap.querySelector('.tab-content.active'));

            if (!placed) {
                var blocks = wrap.querySelectorAll('.ktp-settings-card, .card, .ktp-admin-content');
                if (blocks.length) {
                    placeBelow(blocks[blocks.length - 1]);
                } else {
                    wrap.appendChild(terms);
                }
            }

            terms.hidden = false;
        })();
        </script>
        <?php
    }
}

if (!function_exists('news_crawler_plugin_row_meta')) {
    /**
     * プラグイン一覧のメタリンクに利用規約を追加
     *
     * @param string[] $links  既存リンク.
     * @param string   $file   プラグインファイル.
     * @return string[]
     */
    function news_crawler_plugin_row_meta($links, $file) {
        $plugin_basename = plugin_basename(NEWS_CRAWLER_PLUGIN_DIR . 'news-crawler.php');
        if ($file !== $plugin_basename) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url(news_crawler_terms_url()),
            esc_html__('利用規約', 'news-crawler')
        );

        return $links;
    }
}
