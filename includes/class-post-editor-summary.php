<?php
/**
 * 投稿編集画面に要約生成の手動実行ボタンを追加するクラス
 * 
 * @package NewsCrawler
 * @since 1.5.2
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerPostEditorSummary {
    
    public function __construct() {
        // 投稿編集画面にメタボックスを追加
        add_action('add_meta_boxes', array($this, 'add_summary_meta_box'));
        
        // AJAXハンドラーを追加
        add_action('wp_ajax_manual_generate_summary', array($this, 'manual_generate_summary'));
        add_action('wp_ajax_regenerate_summary', array($this, 'regenerate_summary'));
    }
    
    /**
     * 要約生成用のメタボックスを追加
     */
    public function add_summary_meta_box() {
        // 投稿と固定ページの両方に追加
        add_meta_box(
            'news_crawler_summary',
            'News Crawler ' . news_crawler_get_version() . ' - AI要約生成',
            array($this, 'render_summary_meta_box'),
            array('post', 'page'),
            'side',
            'high'
        );
    }
    
    /**
     * メタボックスの内容を表示
     */
    public function render_summary_meta_box($post) {
        // 基本設定からOpenAI APIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
        $auto_summary_enabled = isset($basic_settings['auto_summary_generation']) ? $basic_settings['auto_summary_generation'] : false;
        
        // 既に要約が生成されているかチェック
        $summary_generated = get_post_meta($post->ID, '_openai_summary_generated', true);
        $summary_date = get_post_meta($post->ID, '_openai_summary_date', true);
        
        if (empty($api_key)) {
            echo '<p style="color: #d63638;">⚠️ OpenAI APIキーが設定されていません。</p>';
            echo '<p><a href="' . admin_url('admin.php?page=news-crawler-basic') . '">基本設定</a>でOpenAI APIキーを設定してください。</p>';
            return;
        }
        
        if (!$auto_summary_enabled) {
            echo '<p style="color: #d63638;">⚠️ 要約自動生成が無効になっています。</p>';
            echo '<p><a href="' . admin_url('admin.php?page=news-crawler-basic') . '">基本設定</a>で要約自動生成を有効にしてください。</p>';
            return;
        }
        
        echo '<div id="news-crawler-summary-controls">';
        
        if ($summary_generated) {
            echo '<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            echo '<p style="margin: 0 0 10px 0;"><strong>✅ 要約が生成されています</strong></p>';
            echo '<p style="margin: 0; font-size: 12px;">生成日時: ' . esc_html($summary_date) . '</p>';
            echo '</div>';
            
            echo '<p><button type="button" id="regenerate-summary" class="button button-secondary" style="width: 100%;">要約を再生成</button></p>';
        } else {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            echo '<p style="margin: 0;"><strong>📝 要約が未生成です</strong></p>';
            echo '</div>';
            
            echo '<p><button type="button" id="manual-generate-summary" class="button button-primary" style="width: 100%;">要約を生成</button></p>';
        }
        
        echo '<div id="summary-status" style="margin-top: 10px; display: none;"></div>';
        echo '</div>';
        
        // JavaScript
        ?>
        <script>
        jQuery(document).ready(function($) {
            // 手動要約生成
            $('#manual-generate-summary').click(function() {
                var button = $(this);
                var statusDiv = $('#summary-status');
                
                button.prop('disabled', true).text('生成中...');
                statusDiv.html('<div style="color: #0073aa;">🔄 要約とまとめを生成中です...</div>').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'manual_generate_summary',
                        nonce: '<?php echo wp_create_nonce('manual_summary_nonce'); ?>',
                        post_id: <?php echo $post->ID; ?>
                    },
                    success: function(response) {
                        if (response.success) {
                            statusDiv.html('<div style="color: #46b450;">✅ 要約とまとめの生成が完了しました！</div>');
                            
                            // ページをリロードして更新された内容を表示
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            statusDiv.html('<div style="color: #d63638;">❌ エラー: ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        statusDiv.html('<div style="color: #d63638;">❌ 通信エラーが発生しました</div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('要約を生成');
                    }
                });
            });
            
            // 要約再生成
            $('#regenerate-summary').click(function() {
                var button = $(this);
                var statusDiv = $('#summary-status');
                
                if (!confirm('既存の要約とまとめを削除して再生成しますか？')) {
                    return;
                }
                
                button.prop('disabled', true).text('再生成中...');
                statusDiv.html('<div style="color: #0073aa;">🔄 要約とまとめを再生成中です...</div>').show();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'regenerate_summary',
                        nonce: '<?php echo wp_create_nonce('regenerate_summary_nonce'); ?>',
                        post_id: <?php echo $post->ID; ?>
                    },
                    success: function(response) {
                        if (response.success) {
                            statusDiv.html('<div style="color: #46b450;">✅ 要約とまとめの再生成が完了しました！</div>');
                            
                            // ページをリロードして更新された内容を表示
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            statusDiv.html('<div style="color: #d63638;">❌ エラー: ' + response.data + '</div>');
                        }
                    },
                    error: function() {
                        statusDiv.html('<div style="color: #d63638;">❌ 通信エラーが発生しました</div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('要約を再生成');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * 手動で要約を生成するAJAXハンドラー
     */
    public function manual_generate_summary() {
        check_ajax_referer('manual_summary_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post || !in_array($post->post_type, array('post', 'page'))) {
            wp_send_json_error('投稿が見つかりません');
        }
        
        // 既に要約が生成されている場合はスキップ
        if (get_post_meta($post_id, '_openai_summary_generated', true)) {
            wp_send_json_error('既に要約が生成されています');
        }
        
        // OpenAI要約生成クラスを使用して要約を生成
        if (class_exists('NewsCrawlerOpenAISummarizer')) {
            $summarizer = new NewsCrawlerOpenAISummarizer();
            $result = $summarizer->generate_summary($post_id);
            
            if (is_array($result) && isset($result['error'])) {
                wp_send_json_error($result['error']);
            } elseif ($result === true) {
                wp_send_json_success('要約とまとめの生成が完了しました');
            } else {
                wp_send_json_error('要約の生成に失敗しました');
            }
        } else {
            wp_send_json_error('OpenAI要約生成クラスが見つかりません');
        }
    }
    
    /**
     * 要約を再生成するAJAXハンドラー
     */
    public function regenerate_summary() {
        check_ajax_referer('regenerate_summary_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post || !in_array($post->post_type, array('post', 'page'))) {
            wp_send_json_error('投稿が見つかりません');
        }
        
        // OpenAI要約生成クラスを使用して要約を再生成
        if (class_exists('NewsCrawlerOpenAISummarizer')) {
            $summarizer = new NewsCrawlerOpenAISummarizer();
            $result = $summarizer->regenerate_summary($post_id);
            
            if (is_array($result) && isset($result['error'])) {
                wp_send_json_error($result['error']);
            } elseif ($result === true) {
                wp_send_json_success('要約とまとめの再生成が完了しました');
            } else {
                wp_send_json_error('要約の再生成に失敗しました');
            }
        } else {
            wp_send_json_error('OpenAI要約生成クラスが見つかりません');
        }
    }
}

// クラスの初期化
new NewsCrawlerPostEditorSummary();
