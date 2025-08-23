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
            
            <h2>手動実行</h2>
            <p>設定したニュースソースからキーワードにマッチした記事を取得して、1つの投稿にまとめて作成します。</p>
            <button type="button" id="manual-run" class="button button-primary">手動実行</button>
            <button type="button" id="test-fetch" class="button button-secondary">テスト取得</button>
            
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
                    resultDiv.html('記事の取得と投稿を開始します...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'news_crawler_manual_run',
                            nonce: '<?php echo wp_create_nonce('news_crawler_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                resultDiv.html('<div class="notice notice-success"><p>' + response.data.replace(/\n/g, '<br>') + '</p></div>');
                            } else {
                                resultDiv.html('<div class="notice notice-error"><p>' + response.data.replace(/\n/g, '<br>') + '</p></div>');
                            }
                        },
                        error: function() {
                            resultDiv.html('<div class="notice notice-error"><p>エラーが発生しました。</p></div>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('手動実行');
                        }
                    });
                });
                
                $('#test-fetch').click(function() {
                    var button = $(this);
                    var resultDiv = $('#manual-run-result');
                    button.prop('disabled', true).text('テスト中...');
                    resultDiv.html('ニュースソースへの接続をテストしています...');

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'news_crawler_test_fetch',
                            nonce: '<?php echo wp_create_nonce('news_crawler_nonce'); ?>'
                        },
                        success: function(response) {
                             if (response.success) {
                                resultDiv.html('<div class="notice notice-info"><p>' + response.data + '</p></div>');
                            } else {
                                resultDiv.html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                            }
                        },
                        error: function() {
                            resultDiv.html('<div class="notice notice-error"><p>エラーが発生しました。</p></div>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('テスト取得');
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
                            }
                        }
                    } else {
                        $article = $this->parse_content($content, $source);
                        if ($article) {
                            $debug_info[] = $source . ': HTMLページから記事を解析';
                            if ($this->is_keyword_match($article, $keywords)) {
                                $matched_articles[] = $article;
                                $debug_info[] = '  - キーワードマッチ: ' . $article['title'];
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
            if ($this->is_duplicate_article($article)) {
                $duplicates_skipped++;
                continue;
            }
            
            $quality_score = $this->calculate_quality_score($article);
            if ($quality_score < 0.5) {
                $low_quality_skipped++;
                continue;
            }
            
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
        foreach ($keywords as $keyword) {
            if (stripos($text_to_search, strtolower($keyword)) !== false) {
                return true;
            }
        }
        return false;
    }
    
    private function create_summary_post($articles, $category, $status) {
        $cat_id = $this->get_or_create_category($category);
        $post_title = 'ニュースまとめ - ' . date_i18n('Y年n月j日');
        
        $post_content = '';
        
        $articles_by_source = array();
        foreach ($articles as $article) {
            $source_host = parse_url($article['source'], PHP_URL_HOST) ?: $article['source'];
            $articles_by_source[$source_host][] = $article;
        }
        
        foreach ($articles_by_source as $source_host => $source_articles) {
            $post_content .= '<!-- wp:heading {"level":2} -->';
            $post_content .= '<h2>' . esc_html($source_host) . '</h2>';
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
            "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s AND post_type = 'post' AND post_status IN ('publish', 'draft', 'pending') AND post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
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
        $title_length = mb_strlen($article['title']);
        if ($title_length >= 10 && $title_length <= 100) $score += 0.3;
        
        $content_length = mb_strlen(($article['excerpt'] ?? '') . ' ' . ($article['news_content'] ?? ''));
        if ($content_length >= 100) $score += 0.4;
        
        if (!empty($article['image_url'])) $score += 0.1;
        if (!empty($article['article_date'])) $score += 0.1;
        if (!empty($article['source'])) $score += 0.1;
        
        return min($score, 1.0);
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
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        $title = $xpath->query('//h1')->item(0)->nodeValue ?? $xpath->query('//title')->item(0)->nodeValue ?? '';
        
        $paragraphs = [];
        foreach ($xpath->query('//p') as $p) {
            $text = trim($p->nodeValue);
            if (mb_strlen($text) > 30) {
                $paragraphs[] = $text;
            }
        }
        $excerpt = implode(' ', array_slice($paragraphs, 0, 2));
        
        $time_node = $xpath->query('//time[@datetime]')->item(0);
        $article_date = $time_node ? $time_node->getAttribute('datetime') : '';

        return array(
            'title' => trim($title),
            'excerpt' => $excerpt,
            'news_content' => implode("\n\n", $paragraphs),
            'article_date' => $article_date ? date('Y-m-d H:i:s', strtotime($article_date)) : '',
            'source' => $source,
        );
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
