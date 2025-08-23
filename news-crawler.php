<?php
/**
 * Plugin Name: News Crawler
 * Plugin URI: https://github.com/KantanPro/news-crawler
 * Description: 指定されたニュースソースから自動的に記事を取得し、WordPressサイトに投稿として追加するプラグイン
 * Version: 1.1.0
 * Author: KantanPro
 * Author URI: https://github.com/KantanPro
 * License: MIT
 * Text Domain: news-crawler
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

// プラグインの基本クラス
class NewsCrawler {
    
    private $option_name = 'news_crawler_settings';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_news_crawler_manual_run', array($this, 'manual_run'));
        add_action('wp_ajax_news_crawler_test_fetch', array($this, 'test_fetch'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // 自動投稿機能は廃止、手動実行のみ
    }
    
    public function init() {
        // 初期化処理
        load_plugin_textdomain('news-crawler', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        add_options_page(
            'News Crawler',
            'News Crawler',
            'manage_options',
            'news-crawler',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'news_crawler_main',
            '基本設定',
            array($this, 'main_section_callback'),
            'news-crawler'
        );
        
        add_settings_field(
            'max_articles',
            '最大記事数',
            array($this, 'max_articles_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'max_articles')
        );
        
        add_settings_field(
            'keywords',
            'キーワード設定',
            array($this, 'keywords_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'keywords')
        );
        
        add_settings_field(
            'news_sources',
            'ニュースソース（URL）',
            array($this, 'news_sources_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'news_sources')
        );
        
        add_settings_field(
            'post_category',
            '投稿カテゴリー',
            array($this, 'post_category_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'post_category')
        );
        
        add_settings_field(
            'post_status',
            '投稿ステータス',
            array($this, 'post_status_callback'),
            'news-crawler',
            'news_crawler_main',
            array('label_for' => 'post_status')
        );
    }
    
    public function main_section_callback() {
        echo '<p>ニュースソースからキーワードにマッチした記事を取得し、1つの投稿にまとめて作成します。</p>';
    }
    
    public function max_articles_callback() {
        $options = get_option($this->option_name, array());
        $max_articles = isset($options['max_articles']) && !empty($options['max_articles']) ? $options['max_articles'] : 10;
        echo '<input type="number" id="max_articles" name="' . $this->option_name . '[max_articles]" value="' . esc_attr($max_articles) . '" min="1" max="50" />';
        echo '<p class="description">キーワードにマッチした記事の最大取得数（1-50件）</p>';
    }
    
    public function keywords_callback() {
        $options = get_option($this->option_name, array());
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $keywords_text = implode("\n", $keywords);
        echo '<textarea id="keywords" name="' . $this->option_name . '[keywords]" rows="5" cols="50" placeholder="1行に1キーワードを入力してください">' . esc_textarea($keywords_text) . '</textarea>';
        echo '<p class="description">1行に1キーワードを入力してください。キーワードにマッチした記事のみを取得します。例：AI, テクノロジー, ビジネス</p>';
    }
    
    public function news_sources_callback() {
        $options = get_option($this->option_name, array());
        $sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        $sources_text = implode("\n", $sources);
        echo '<textarea id="news_sources" name="' . $this->option_name . '[news_sources]" rows="10" cols="50" placeholder="https://example.com/news&#10;https://example2.com/rss">' . esc_textarea($sources_text) . '</textarea>';
        echo '<p class="description">1行に1URLを入力してください。RSSフィードまたはニュースサイトのURLを指定できます。</p>';
    }
    
    public function post_category_callback() {
        $options = get_option($this->option_name, array());
        $category = isset($options['post_category']) && !empty($options['post_category']) ? $options['post_category'] : 'blog';
        echo '<input type="text" id="post_category" name="' . $this->option_name . '[post_category]" value="' . esc_attr($category) . '" />';
        echo '<p class="description">投稿するカテゴリー名を入力してください。存在しない場合は自動的に作成されます。</p>';
    }
    
    public function post_status_callback() {
        $options = get_option($this->option_name, array());
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        $statuses = array(
            'draft' => '下書き',
            'publish' => '公開',
            'private' => '非公開',
            'pending' => '承認待ち'
        );
        echo '<select id="post_status" name="' . $this->option_name . '[post_status]">';
        foreach ($statuses as $value => $label) {
            echo '<option value="' . $value . '" ' . selected($value, $status, false) . '>' . $label . '</option>';
        }
        echo '</select>';
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        $existing_options = get_option($this->option_name, array());
        
        if (isset($input['max_articles']) && !empty(trim($input['max_articles']))) {
            $max_articles = intval($input['max_articles']);
            $sanitized['max_articles'] = max(1, min(50, $max_articles));
        } else {
            $sanitized['max_articles'] = isset($existing_options['max_articles']) ? $existing_options['max_articles'] : 10;
        }
        
        if (isset($input['keywords']) && !empty(trim($input['keywords']))) {
            $keywords = explode("\n", $input['keywords']);
            $keywords = array_map('trim', $keywords);
            $keywords = array_filter($keywords);
            $sanitized['keywords'] = $keywords;
        } else {
            $sanitized['keywords'] = isset($existing_options['keywords']) ? $existing_options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        }
        
        if (isset($input['news_sources']) && !empty(trim($input['news_sources']))) {
            $sources = explode("\n", $input['news_sources']);
            $sources = array_map('trim', $sources);
            $sources = array_filter($sources);
            $sources = array_map('esc_url_raw', $sources);
            $sanitized['news_sources'] = $sources;
        } else {
            $sanitized['news_sources'] = isset($existing_options['news_sources']) ? $existing_options['news_sources'] : array();
        }
        
        if (isset($input['post_category']) && !empty(trim($input['post_category']))) {
            $sanitized['post_category'] = sanitize_text_field($input['post_category']);
        } else {
            $sanitized['post_category'] = isset($existing_options['post_category']) ? $existing_options['post_category'] : 'blog';
        }
        
        if (isset($input['post_status']) && !empty(trim($input['post_status']))) {
            $sanitized['post_status'] = sanitize_text_field($input['post_status']);
        } else {
            $sanitized['post_status'] = isset($existing_options['post_status']) ? $existing_options['post_status'] : 'draft';
        }
        
        return $sanitized;
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>News Crawler</h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>設定を保存しました。</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields($this->option_name);
                do_settings_sections('news-crawler');
                submit_button();
                ?>
            </form>
            
            <hr>
            
            <h2>投稿を作成</h2>
            <p>設定したニュースソースからキーワードにマッチした記事を取得して、1つの投稿にまとめて作成します。</p>
            <button type="button" id="manual-run" class="button button-primary">投稿を作成</button>
            
            <div id="manual-run-result" style="margin-top: 10px; white-space: pre-wrap; background: #f7f7f7; padding: 15px; border: 1px solid #ccc; border-radius: 4px; max-height: 400px; overflow-y: auto;"></div>
            
            <hr>
            
            <h2>統計情報</h2>
            <?php $stats = $this->get_crawler_statistics(); ?>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>項目</th>
                        <th>数値</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>総投稿数</td>
                        <td><?php echo $stats['total_posts']; ?>件</td>
                    </tr>
                    <tr>
                        <td>今月の投稿数</td>
                        <td><?php echo $stats['posts_this_month']; ?>件</td>
                    </tr>
                    <tr>
                        <td>重複スキップ数</td>
                        <td><?php echo $stats['duplicates_skipped']; ?>件</td>
                    </tr>
                    <tr>
                        <td>低品質スキップ数</td>
                        <td><?php echo $stats['low_quality_skipped']; ?>件</td>
                    </tr>
                    <tr>
                        <td>最後の実行日時</td>
                        <td><?php echo $stats['last_run']; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <script>
            jQuery(document).ready(function($) {
                $('#manual-run').click(function() {
                    var button = $(this);
                    var resultDiv = $('#manual-run-result');
                    button.prop('disabled', true).text('実行中...');
                    resultDiv.html('ニュースソースの解析と投稿作成を開始します...');
                    
                    // まずニュースソースの解析を実行
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'news_crawler_test_fetch',
                            nonce: '<?php echo wp_create_nonce('news_crawler_nonce'); ?>'
                        },
                        success: function(testResponse) {
                            var testResult = '';
                            if (testResponse.success) {
                                testResult = '<div class="notice notice-info"><p><strong>ニュースソース解析結果:</strong><br>' + testResponse.data + '</p></div>';
                            } else {
                                testResult = '<div class="notice notice-error"><p><strong>ニュースソース解析エラー:</strong><br>' + testResponse.data + '</p></div>';
                            }
                            
                            // 次に投稿作成を実行
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'news_crawler_manual_run',
                                    nonce: '<?php echo wp_create_nonce('news_crawler_nonce'); ?>'
                                },
                                success: function(postResponse) {
                                    var postResult = '';
                                    if (postResponse.success) {
                                        postResult = '<div class="notice notice-success"><p><strong>投稿作成結果:</strong><br>' + postResponse.data.replace(/\n/g, '<br>') + '</p></div>';
                                    } else {
                                        postResult = '<div class="notice notice-error"><p><strong>投稿作成エラー:</strong><br>' + postResponse.data.replace(/\n/g, '<br>') + '</p></div>';
                                    }
                                    
                                    // 両方の結果を表示
                                    resultDiv.html(testResult + '<br>' + postResult);
                                },
                                error: function() {
                                    resultDiv.html(testResult + '<br><div class="notice notice-error"><p><strong>投稿作成エラー:</strong><br>エラーが発生しました。</p></div>');
                                },
                                complete: function() {
                                    button.prop('disabled', false).text('投稿を作成');
                                }
                            });
                        },
                        error: function() {
                            resultDiv.html('<div class="notice notice-error"><p><strong>ニュースソース解析エラー:</strong><br>エラーが発生しました。</p></div>');
                            button.prop('disabled', false).text('投稿を作成');
                        }
                    });
                });
            });
            </script>
        </div>
        <?php
    }
    
    public function manual_run() {
        check_ajax_referer('news_crawler_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $result = $this->crawl_news();
        wp_send_json_success($result);
    }
    
    public function test_fetch() {
        check_ajax_referer('news_crawler_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('権限がありません');
        }
        
        $options = get_option($this->option_name, array());
        $sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        
        if (empty($sources)) {
            wp_send_json_success('ニュースソースが設定されていません。');
        }
        
        $test_result = array();
        foreach ($sources as $source) {
            $content = $this->fetch_content($source);
            if ($content) {
                $test_result[] = $source . ': 取得成功 (' . (is_array($content) ? count($content) . '件の記事' : strlen($content) . ' 文字') . ')';
            } else {
                $test_result[] = $source . ': 取得失敗';
            }
        }
        
        wp_send_json_success(implode('<br>', $test_result));
    }
    
    public function activate() {
        $options = get_option($this->option_name);
        if (!$options) {
            $default_options = array(
                'max_articles' => 10,
                'keywords' => array('AI', 'テクノロジー', 'ビジネス', 'ニュース'),
                'news_sources' => array(),
                'post_category' => 'blog',
                'post_status' => 'draft'
            );
            add_option($this->option_name, $default_options);
        }
    }
    
    private function crawl_news() {
        $options = get_option($this->option_name, array());
        $sources = isset($options['news_sources']) && !empty($options['news_sources']) ? $options['news_sources'] : array();
        $keywords = isset($options['keywords']) && !empty($options['keywords']) ? $options['keywords'] : array('AI', 'テクノロジー', 'ビジネス', 'ニュース');
        $max_articles = isset($options['max_articles']) && !empty($options['max_articles']) ? $options['max_articles'] : 10;
        $category = isset($options['post_category']) && !empty($options['post_category']) ? $options['post_category'] : 'blog';
        $status = isset($options['post_status']) && !empty($options['post_status']) ? $options['post_status'] : 'draft';
        
        if (empty($sources)) {
            return 'ニュースソースが設定されていません。';
        }
        
        $matched_articles = array();
        $errors = array();
        $duplicates_skipped = 0;
        $low_quality_skipped = 0;
        $debug_info = array();
        
        foreach ($sources as $source) {
            try {
                $content = $this->fetch_content($source);
                if ($content) {
                    if (is_array($content)) {
                        $debug_info[] = $source . ': RSSフィードから' . count($content) . '件の記事を取得';
                        foreach ($content as $article) {
                            if ($this->is_keyword_match($article, $keywords)) {
                                $matched_articles[] = $article;
                                $debug_info[] = '  - キーワードマッチ: ' . $article['title'];
                            } else {
                                // キーワードマッチしない場合のデバッグ情報
                                global $news_crawler_search_text;
                                $debug_info[] = '  - キーワードマッチなし: ' . $article['title'];
                                $debug_info[] = '    検索対象テキスト: ' . mb_substr($news_crawler_search_text, 0, 100) . '...';
                            }
                        }
                    } else {
                        $articles = $this->parse_content($content, $source);
                        if ($articles && is_array($articles)) {
                            $debug_info[] = $source . ': HTMLページから' . count($articles) . '件の記事を解析';
                            foreach ($articles as $article) {
                                if ($this->is_keyword_match($article, $keywords)) {
                                    $matched_articles[] = $article;
                                    $debug_info[] = '  - キーワードマッチ: ' . $article['title'];
                                }
                            }
                        } elseif ($articles) {
                            // 単一記事の場合
                            $debug_info[] = $source . ': HTMLページから単一記事を解析';
                            if ($this->is_keyword_match($articles, $keywords)) {
                                $matched_articles[] = $articles;
                                $debug_info[] = '  - キーワードマッチ: ' . $articles['title'];
                            } else {
                                // キーワードマッチしない場合のデバッグ情報
                                global $news_crawler_search_text;
                                $debug_info[] = '  - キーワードマッチなし: ' . $articles['title'];
                                $debug_info[] = '    検索対象テキスト: ' . mb_substr($news_crawler_search_text, 0, 100) . '...';
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = $source . ': ' . $e->getMessage();
            }
        }
        
        $debug_info[] = "\nキーワードマッチした記事数: " . count($matched_articles);
        
        $valid_articles = array();
        foreach ($matched_articles as $article) {
            $debug_info[] = "  - 記事: " . $article['title'];
            
            if ($this->is_duplicate_article($article)) {
                $duplicates_skipped++;
                $debug_info[] = "    → 重複のためスキップ";
                continue;
            }
            
            $quality_score = $this->calculate_quality_score($article);
            $debug_info[] = "    → 品質スコア: " . number_format($quality_score, 2);
            
            // 品質スコアの詳細情報を追加
            global $news_crawler_debug_details;
            if (!empty($news_crawler_debug_details)) {
                foreach ($news_crawler_debug_details as $detail) {
                    $debug_info[] = "      " . $detail;
                }
            }
            
            if ($quality_score < 0.3) {
                $low_quality_skipped++;
                $debug_info[] = "    → 品質スコアが低いためスキップ";
                continue;
            }
            
            $debug_info[] = "    → 有効記事として追加";
            $valid_articles[] = $article;
        }
        
        $valid_articles = array_slice($valid_articles, 0, $max_articles);
        
        $posts_created = 0;
        if (!empty($valid_articles)) {
            $post_id = $this->create_summary_post($valid_articles, $category, $status);
            if ($post_id && !is_wp_error($post_id)) {
                $posts_created = 1;
            }
        }
        
        $result = $posts_created . '件の投稿を作成しました（' . count($valid_articles) . '件の記事を含む）。';
        $result .= "\n投稿ID: " . ($post_id ?? 'なし');
        if ($duplicates_skipped > 0) $result .= "\n重複スキップ: " . $duplicates_skipped . '件';
        if ($low_quality_skipped > 0) $result .= "\n低品質スキップ: " . $low_quality_skipped . '件';
        if (!empty($errors)) $result .= "\nエラー: " . implode(', ', $errors);
        
        $result .= "\n\n=== デバッグ情報 ===\n" . implode("\n", $debug_info);
        
        $this->update_crawler_statistics($posts_created, $duplicates_skipped, $low_quality_skipped);
        
        return $result;
    }
    
    private function is_keyword_match($article, $keywords) {
        $text_to_search = strtolower($article['title'] . ' ' . ($article['excerpt'] ?? '') . ' ' . ($article['news_content'] ?? '') . ' ' . ($article['description'] ?? ''));
        
        // デバッグ用：検索対象のテキストを記録
        global $news_crawler_search_text;
        $news_crawler_search_text = $text_to_search;
        
        foreach ($keywords as $keyword) {
            if (stripos($text_to_search, strtolower($keyword)) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function create_summary_post($articles, $category, $status) {
        $cat_id = $this->get_or_create_category($category);
        
        // キーワード情報を取得
        $options = get_option('news_crawler_settings', array());
        $keywords = isset($options['keywords']) ? $options['keywords'] : array('ニュース');
        
        // キーワードが設定されていない場合は、記事の内容から推測
        if (empty($keywords) || (count($keywords) === 1 && $keywords[0] === 'ニュース')) {
            $keyword_text = '最新';
        } else {
            // キーワードを組み合わせてタイトルを作成（最大3つまで）
            $keyword_text = implode('、', array_slice($keywords, 0, 3));
        }
        
        $post_title = $keyword_text . '：ニュースまとめ – ' . date_i18n('Y年n月j日');
        
        $post_content = '';
        
        $articles_by_source = array();
        foreach ($articles as $article) {
            $source_host = parse_url($article['source'], PHP_URL_HOST) ?: $article['source'];
            $articles_by_source[$source_host][] = $article;
        }
        
        foreach ($articles_by_source as $source_host => $source_articles) {
            $post_content .= '<!-- wp:quote -->';
            $post_content .= '<blockquote class="wp-block-quote">';
            
            $post_content .= '<!-- wp:heading {"level":2} -->';
            $post_content .= '<h2>' . esc_html($this->get_readable_source_name($source_host)) . '</h2>';
            $post_content .= '<!-- /wp:heading -->';
            
            foreach ($source_articles as $article) {
                if (!empty($article['link'])) {
                    $post_content .= '<!-- wp:heading {"level":3} -->';
                    $post_content .= '<h3><a href="' . esc_url($article['link']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($article['title']) . '</a></h3>';
                    $post_content .= '<!-- /wp:heading -->';
                } else {
                    $post_content .= '<!-- wp:heading {"level":3} -->';
                    $post_content .= '<h3>' . esc_html($article['title']) . '</h3>';
                    $post_content .= '<!-- /wp:heading -->';
                }
                
                if (!empty($article['excerpt'])) {
                    $post_content .= '<!-- wp:paragraph -->';
                    $post_content .= '<p>' . esc_html($article['excerpt']) . '</p>';
                    $post_content .= '<!-- /wp:paragraph -->';
                }
                
                $meta_info = [];
                if (!empty($article['article_date'])) {
                    $meta_info[] = '<strong>公開日:</strong> ' . esc_html($article['article_date']);
                }
                if (!empty($article['source'])) {
                    $meta_info[] = '<strong>出典:</strong> <a href="' . esc_url($article['source']) . '" target="_blank" rel="noopener noreferrer">' . esc_html(parse_url($article['source'], PHP_URL_HOST) ?: $article['source']) . '</a>';
                }

                if (!empty($meta_info)) {
                    $post_content .= '<!-- wp:paragraph {"fontSize":"small"} -->';
                    $post_content .= '<p class="has-small-font-size">' . implode(' | ', $meta_info) . '</p>';
                    $post_content .= '<!-- /wp:paragraph -->';
                }

                $post_content .= '<!-- wp:spacer {"height":"20px"} -->';
                $post_content .= '<div style="height:20px" aria-hidden="true" class="wp-block-spacer"></div>';
                $post_content .= '<!-- /wp:spacer -->';
            }
            
            $post_content .= '</blockquote>';
            $post_content .= '<!-- /wp:quote -->';
        }
        
        $post_data = array(
            'post_title'    => $post_title,
            'post_content'  => $post_content,
            'post_status'   => $status,
            'post_author'   => get_current_user_id() ?: 1,
            'post_type'     => 'post',
            'post_category' => array($cat_id)
        );
        
        // ksesフィルターを一時的に無効化して投稿を作成
        kses_remove_filters();
        $post_id = wp_insert_post($post_data, true);
        kses_init_filters();
        
        if (is_wp_error($post_id)) {
            return $post_id;
        }
        
        // メタデータの保存
        update_post_meta($post_id, '_news_summary', true);
        update_post_meta($post_id, '_news_articles_count', count($articles));
        update_post_meta($post_id, '_news_crawled_date', current_time('mysql'));
        
        foreach ($articles as $index => $article) {
            update_post_meta($post_id, '_news_article_' . $index . '_title', $article['title']);
            update_post_meta($post_id, '_news_article_' . $index . '_source', $article['source']);
            if (!empty($article['link'])) {
                update_post_meta($post_id, '_news_article_' . $index . '_link', $article['link']);
            }
        }
        
        return $post_id;
    }
    
    private function is_duplicate_article($article) {
        global $wpdb;
        $title = $article['title'];
        $similar_titles = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s AND post_type = 'post' AND post_status IN ('publish', 'draft', 'pending') AND post_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            '%' . $wpdb->esc_like($title) . '%'
        ));
        if ($similar_titles) return true;
        
        if (!empty($article['link'])) {
            $existing_url = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_news_source' AND meta_value = %s",
                $article['link']
            ));
            if ($existing_url) return true;
        }
        return false;
    }
    
    private function calculate_quality_score($article) {
        $score = 0;
        $debug_details = [];
        
        $title_length = mb_strlen($article['title']);
        if ($title_length >= 5 && $title_length <= 150) {
            $score += 0.3;
            $debug_details[] = "タイトル長: " . $title_length . "文字 (+0.3)";
        } else {
            $debug_details[] = "タイトル長: " . $title_length . "文字 (不足)";
        }
        
        // excerptとnews_contentの両方をチェック（RSSとHTMLの両方に対応）
        $content_text = '';
        if (!empty($article['excerpt'])) $content_text .= $article['excerpt'] . ' ';
        if (!empty($article['news_content'])) $content_text .= $article['news_content'] . ' ';
        if (!empty($article['description'])) $content_text .= $article['description'] . ' ';
        
        $content_length = mb_strlen(trim($content_text));
        if ($content_length >= 50) {
            $score += 0.4;
            $debug_details[] = "本文長: " . $content_length . "文字 (+0.4)";
        } else {
            $debug_details[] = "本文長: " . $content_length . "文字 (不足)";
        }
        
        if (!empty($article['image_url'])) {
            $score += 0.1;
            $debug_details[] = "画像あり (+0.1)";
        } else {
            $debug_details[] = "画像なし";
        }
        
        if (!empty($article['article_date'])) {
            $score += 0.1;
            $debug_details[] = "日付あり (+0.1)";
        } else {
            $debug_details[] = "日付なし";
        }
        
        if (!empty($article['source'])) {
            $score += 0.1;
            $debug_details[] = "ソースあり (+0.1)";
        } else {
            $debug_details[] = "ソースなし";
        }
        
        $final_score = min($score, 1.0);
        $debug_details[] = "最終スコア: " . number_format($final_score, 2);
        
        // デバッグ情報をグローバル変数に保存
        global $news_crawler_debug_details;
        $news_crawler_debug_details = $debug_details;
        
        return $final_score;
    }
    
    private function get_crawler_statistics() {
        global $wpdb;
        $stats = array();
        $stats['total_posts'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_news_summary'");
        $stats['posts_this_month'] = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_news_crawled_date' AND meta_value >= %s", date('Y-m-01')));
        $stats['duplicates_skipped'] = get_option('news_crawler_duplicates_skipped', 0);
        $stats['low_quality_skipped'] = get_option('news_crawler_low_quality_skipped', 0);
        $stats['last_run'] = get_option('news_crawler_last_run', '未実行');
        return $stats;
    }
    
    private function update_crawler_statistics($posts_created, $duplicates_skipped, $low_quality_skipped) {
        if ($duplicates_skipped > 0) {
            $current_duplicates = get_option('news_crawler_duplicates_skipped', 0);
            update_option('news_crawler_duplicates_skipped', $current_duplicates + $duplicates_skipped);
        }
        if ($low_quality_skipped > 0) {
            $current_low_quality = get_option('news_crawler_low_quality_skipped', 0);
            update_option('news_crawler_low_quality_skipped', $current_low_quality + $low_quality_skipped);
        }
        update_option('news_crawler_last_run', current_time('mysql'));
    }
    
    private function fetch_content($url) {
        if ($this->is_rss_feed($url)) {
            return $this->fetch_rss_content($url);
        } else {
            return $this->fetch_html_content($url);
        }
    }
    
    private function is_rss_feed($url) {
        $url_lower = strtolower($url);
        return str_contains($url_lower, 'rss') || str_contains($url_lower, 'feed') || str_contains($url_lower, 'xml');
    }
    
    private function fetch_rss_content($url) {
        $response = wp_remote_get($url, array('timeout' => 30, 'user-agent' => 'NewsCrawler/1.1'));
        if (is_wp_error($response)) return false;
        $body = wp_remote_retrieve_body($response);
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        if ($xml === false) return false;
        return $this->parse_rss_xml($xml, $url);
    }
    
    private function fetch_html_content($url) {
        $response = wp_remote_get($url, array('timeout' => 30, 'user-agent' => 'NewsCrawler/1.1'));
        if (is_wp_error($response)) return false;
        return wp_remote_retrieve_body($response);
    }
    
    private function parse_rss_xml($xml, $source_url) {
        $articles = array();
        $items = $xml->channel->item ?? $xml->entry ?? [];
        
        foreach ($items as $item) {
            $namespaces = $item->getNamespaces(true);
            $dc = $item->children($namespaces['dc'] ?? '');

            $article = array(
                'title' => (string)($item->title ?? ''),
                'link' => (string)($item->link['href'] ?? $item->link ?? ''),
                'description' => (string)($item->description ?? $item->summary ?? ''),
                'article_date' => date('Y-m-d H:i:s', strtotime((string)($item->pubDate ?? $item->published ?? $dc->date ?? 'now'))),
                'source' => $source_url
            );
            $article['excerpt'] = wp_strip_all_tags($article['description']);
            $articles[] = $article;
        }
        return $articles;
    }
    
    private function parse_content($content, $source) {
        try {
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);
            libxml_clear_errors();
            $xpath = new DOMXPath($doc);
            
            if (!$xpath) {
                error_log('News Crawler: XPath初期化に失敗しました');
                return array();
            }

        $articles = array();
        
        // 複数の記事を抽出するためのセレクター
        $article_selectors = array(
            '//article',
            '//div[contains(@class, "post")]',
            '//div[contains(@class, "news")]',
            '//div[contains(@class, "item")]',
            '//li[contains(@class, "news")]',
            '//div[contains(@class, "article")]',
            '//div[contains(@class, "entry")]'
        );
        
        $found_articles = false;
        foreach ($article_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes && $nodes->length > 0) {
                foreach ($nodes as $node) {
                    $title_query = $xpath->query('.//h1|.//h2|.//h3|.//h4|.//a', $node);
                    if (!$title_query || $title_query->length === 0) continue;
                    
                    $title_node = $title_query->item(0);
                    if (!$title_node) continue;
                    
                    $title = trim($title_node->nodeValue);
                    if (empty($title) || mb_strlen($title) < 5) continue;
                    
                    $link_query = $xpath->query('.//a', $node);
                    $link = '';
                    if ($link_query && $link_query->length > 0) {
                        $link_node = $link_query->item(0);
                        if ($link_node) {
                            $link = $link_node->getAttribute('href');
                            // 相対URLを絶対URLに変換
                            if (!empty($link) && !filter_var($link, FILTER_VALIDATE_URL)) {
                                $link = $this->build_absolute_url($source, $link);
                            }
                        }
                    }
                    
                    $paragraphs = array();
                    // より多くの要素から本文を抽出
                    $content_selectors = array(
                        './/p',
                        './/div[contains(@class, "content")]',
                        './/div[contains(@class, "text")]',
                        './/div[contains(@class, "body")]',
                        './/div[contains(@class, "article")]',
                        './/span[contains(@class, "content")]',
                        './/span[contains(@class, "text")]'
                    );
                    
                    foreach ($content_selectors as $content_selector) {
                        $content_query = $xpath->query($content_selector, $node);
                        if ($content_query && $content_query->length > 0) {
                            foreach ($content_query as $content_element) {
                                $text = trim($content_element->nodeValue);
                                if (mb_strlen($text) > 20) {
                                    $paragraphs[] = $text;
                                }
                            }
                        }
                    }
                    
                    // 段落が見つからない場合は、ノード全体からテキストを抽出
                    if (empty($paragraphs)) {
                        $node_text = trim(strip_tags($doc->saveHTML($node)));
                        if (mb_strlen($node_text) > 50) {
                            $paragraphs[] = $node_text;
                        }
                    }
                    
                    $excerpt = implode(' ', array_slice($paragraphs, 0, 2));
                    
                    $time_query = $xpath->query('.//time[@datetime]|.//span[@class*="date"]', $node);
                    $article_date = '';
                    if ($time_query && $time_query->length > 0) {
                        $time_node = $time_query->item(0);
                        if ($time_node) {
                            if ($time_node->hasAttribute('datetime')) {
                                $article_date = date('Y-m-d H:i:s', strtotime($time_node->getAttribute('datetime')));
                            } else {
                                $article_date = date('Y-m-d H:i:s', strtotime($time_node->nodeValue));
                            }
                        }
                    }
                    
                    $articles[] = array(
                        'title' => $title,
                        'link' => $link,
                        'excerpt' => $excerpt,
                        'news_content' => implode("\n\n", $paragraphs),
                        'article_date' => $article_date,
                        'source' => $source,
                    );
                    
                    // デバッグ用：抽出された記事の詳細を記録
                    error_log('News Crawler: 記事抽出 - タイトル: ' . $title . ', 本文長: ' . mb_strlen($excerpt) . '文字, リンク: ' . $link);
                }
                $found_articles = true;
                break;
            }
        }
        
        // 記事が見つからない場合は、単一ページとして解析
        if (!$found_articles) {
            $title = '';
            $h1_query = $xpath->query('//h1');
            if ($h1_query && $h1_query->length > 0) {
                $title = $h1_query->item(0)->nodeValue ?? '';
            }
            if (empty($title)) {
                $title_query = $xpath->query('//title');
                if ($title_query && $title_query->length > 0) {
                    $title = $title_query->item(0)->nodeValue ?? '';
                }
            }
            
            $paragraphs = array();
            $p_query = $xpath->query('//p');
            if ($p_query && $p_query->length > 0) {
                foreach ($p_query as $p) {
                    $text = trim($p->nodeValue);
                    if (mb_strlen($text) > 30) {
                        $paragraphs[] = $text;
                    }
                }
            }
            $excerpt = implode(' ', array_slice($paragraphs, 0, 2));
            
            $article_date = '';
            $time_query = $xpath->query('//time[@datetime]');
            if ($time_query && $time_query->length > 0) {
                $time_node = $time_query->item(0);
                if ($time_node) {
                    $article_date = $time_node->getAttribute('datetime');
                }
            }

            $articles[] = array(
                'title' => trim($title),
                'excerpt' => $excerpt,
                'news_content' => implode("\n\n", $paragraphs),
                'article_date' => $article_date ? date('Y-m-d H:i:s', strtotime($article_date)) : '',
                'source' => $source,
            );
        }
        
        return $articles;
        } catch (Exception $e) {
            error_log('News Crawler: HTML解析中にエラーが発生しました: ' . $e->getMessage());
            return array();
        }
    }
    
    private function get_readable_source_name($source_host) {
        // ドメイン名を読みやすい名前に変換
        $source_names = array(
            'www3.nhk.or.jp' => 'NHKニュース',
            'news.tv-asahi.co.jp' => 'テレビ朝日ニュース',
            'newsdig.tbs.co.jp' => 'TBSニュース',
            'www.fnn.jp' => 'フジテレビニュース',
            'news.ntv.co.jp' => '日本テレビニュース',
            'mainichi.jp' => '毎日新聞',
            'www.asahi.com' => '朝日新聞',
            'www.yomiuri.co.jp' => '読売新聞',
            'www.sankei.com' => '産経新聞',
            'www.nikkei.com' => '日本経済新聞',
            'www.tokyo-np.co.jp' => '東京新聞',
            'kyodonews.jp' => '共同通信',
            'www.jiji.com' => '時事通信',
            'www.itmedia.co.jp' => 'ITmedia',
            'www.techno-edge.net' => 'テクノエッジ',
            'sanseito.jp' => '参政党',
            'www.komei.or.jp' => '公明党',
            'reiwa-shinsengumi.com' => 'れいわ新選組'
        );
        
        // 登録されている名前があれば返す
        if (isset($source_names[$source_host])) {
            return $source_names[$source_host];
        }
        
        // 登録されていない場合は、ドメイン名をクリーンアップして返す
        $clean_name = str_replace(array('www.', 'news.', 'www3.'), '', $source_host);
        $clean_name = ucfirst($clean_name); // 最初の文字を大文字に
        
        return $clean_name;
    }
    
    private function build_absolute_url($base_url, $relative_url) {
        // 既に絶対URLの場合はそのまま返す
        if (filter_var($relative_url, FILTER_VALIDATE_URL)) {
            return $relative_url;
        }
        
        // 空の場合は空文字を返す
        if (empty($relative_url)) {
            return '';
        }
        
        // プロトコル相対URL（//example.com/path）の場合
        if (substr($relative_url, 0, 2) === '//') {
            $base_parts = parse_url($base_url);
            $scheme = $base_parts['scheme'] ?? 'https';
            return $scheme . ':' . $relative_url;
        }
        
        // 絶対パス（/path）の場合
        if (substr($relative_url, 0, 1) === '/') {
            $base_parts = parse_url($base_url);
            $scheme = $base_parts['scheme'] ?? 'https';
            $host = $base_parts['host'] ?? '';
            return $scheme . '://' . $host . $relative_url;
        }
        
        // 相対パス（path）の場合
        $base_parts = parse_url($base_url);
        $scheme = $base_parts['scheme'] ?? 'https';
        $host = $base_parts['host'] ?? '';
        $path = $base_parts['path'] ?? '/';
        
        // ベースパスからディレクトリ部分を取得
        $dir = dirname($path);
        if ($dir === '.') {
            $dir = '/';
        }
        
        return $scheme . '://' . $host . $dir . '/' . $relative_url;
    }
    
    private function get_or_create_category($category_name) {
        $category = get_term_by('name', $category_name, 'category');
        if (!$category) {
            $result = wp_insert_term($category_name, 'category');
            return is_wp_error($result) ? 1 : $result['term_id'];
        }
        return $category->term_id;
    }
}

new NewsCrawler();
