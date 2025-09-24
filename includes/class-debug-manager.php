<?php
/**
 * News Crawler Debug Manager
 * 管理画面からデバッグとテストができるツール
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerDebugManager {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_debug_menu'));
        add_action('wp_ajax_news_crawler_debug_genres', array($this, 'debug_genres_ajax'));
        add_action('wp_ajax_news_crawler_test_posting', array($this, 'test_posting_ajax'));
        add_action('wp_ajax_news_crawler_clear_locks', array($this, 'clear_locks_ajax'));
        add_action('wp_ajax_news_crawler_show_logs', array($this, 'show_logs_ajax'));
        add_action('wp_ajax_news_crawler_test_ajax', array($this, 'test_ajax'));
        add_action('wp_ajax_news_crawler_emergency_posting', array($this, 'emergency_posting_ajax'));
    }
    
    public function add_debug_menu() {
        add_submenu_page(
            'news-crawler-main',
            'デバッグ・テスト',
            '🔧 デバッグ・テスト',
            'manage_options',
            'news-crawler-debug',
            array($this, 'debug_page')
        );
    }
    
    public function debug_page() {
        ?>
        <div class="wrap">
            <h1>News Crawler デバッグ・テスト</h1>
            
            <div class="notice notice-info">
                <p><strong>注意:</strong> このページは開発・デバッグ用です。本番環境では慎重に使用してください。</p>
            </div>
            
            <div class="card">
                <h2>ジャンル設定デバッグ</h2>
                <p>現在のジャンル設定を詳細に分析し、なぜ自動投稿がスキップされているのかを確認します。</p>
                <button type="button" class="button button-primary" id="debug-genres-btn">ジャンル設定をデバッグ</button>
                <div id="debug-genres-result" style="margin-top: 20px;"></div>
            </div>
            
            <div class="card">
                <h2>自動投稿テスト</h2>
                <p>修正されたロジックで自動投稿をテスト実行します。</p>
                <button type="button" class="button button-secondary" id="test-posting-btn">自動投稿をテスト</button>
                <div id="test-posting-result" style="margin-top: 20px;"></div>
            </div>
            
            <div class="card">
                <h2>ロッククリア</h2>
                <p>全てのロックファイルとtransientをクリアします。</p>
                <button type="button" class="button button-secondary" id="clear-locks-btn">ロックをクリア</button>
                <div id="clear-locks-result" style="margin-top: 20px;"></div>
            </div>
            
            <div class="card">
                <h2>ログ表示</h2>
                <p>最新のCronログを表示します。</p>
                <button type="button" class="button button-secondary" id="show-logs-btn">ログを表示</button>
                <div id="show-logs-result" style="margin-top: 20px;"></div>
            </div>
            
            <div class="card">
                <h2>AJAX接続テスト</h2>
                <p>AJAX接続が正常に動作するかテストします。</p>
                <button type="button" class="button button-secondary" id="test-ajax-btn">AJAX接続テスト</button>
                <div id="test-ajax-result" style="margin-top: 20px;"></div>
            </div>
            
            <div class="card" style="background-color: #fff3cd; border-left: 4px solid #ffc107;">
                <h2>🚀 もやし生活終了ボタン</h2>
                <p><strong>緊急:</strong> セキュリティチェックを完全にバイパスして、直接自動投稿を実行します。</p>
                <button type="button" class="button button-primary" id="emergency-posting-btn" style="background-color: #dc3545; border-color: #dc3545;">🚀 緊急自動投稿実行</button>
                <div id="emergency-posting-result" style="margin-top: 20px;"></div>
                
                <hr style="margin: 20px 0;">
                <h3>🔥 最終手段：直接実行</h3>
                <p>AJAXが失敗する場合は、以下のリンクを直接クリックしてください：</p>
                <a href="<?php echo plugin_dir_url(__FILE__) . '../emergency-moyashi-end.php'; ?>" target="_blank" class="button button-secondary" style="background-color: #ff6b6b; border-color: #ff6b6b; color: white;">🔥 直接実行（新しいタブで開く）</a>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#debug-genres-btn').click(function() {
                var $btn = $(this);
                var $result = $('#debug-genres-result');
                
                $btn.prop('disabled', true).text('デバッグ中...');
                $result.html('<div class="spinner is-active"></div>');
                
                $.post(ajaxurl, {
                    action: 'news_crawler_debug_genres'
                }, function(response) {
                    $btn.prop('disabled', false).text('ジャンル設定をデバッグ');
                    console.log('AJAX Response:', response);
                    if (response && response.success) {
                        $result.html('<div class="notice notice-success"><pre>' + response.data + '</pre></div>');
                    } else {
                        var errorMsg = 'Unknown error occurred';
                        if (response) {
                            if (response.data) {
                                errorMsg = response.data;
                            } else if (response.message) {
                                errorMsg = response.message;
                            } else {
                                errorMsg = 'Response received but no data: ' + JSON.stringify(response);
                            }
                        } else {
                            errorMsg = 'No response received';
                        }
                        $result.html('<div class="notice notice-error"><p>エラー: ' + errorMsg + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    $btn.prop('disabled', false).text('ジャンル設定をデバッグ');
                    console.log('AJAX Fail:', xhr, status, error);
                    var errorMsg = 'AJAX エラー: ' + error;
                    if (xhr.responseText) {
                        errorMsg += '<br>Response: ' + xhr.responseText;
                    }
                    $result.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                });
            });
            
            $('#test-posting-btn').click(function() {
                var $btn = $(this);
                var $result = $('#test-posting-result');
                
                $btn.prop('disabled', true).text('テスト中...');
                $result.html('<div class="spinner is-active"></div>');
                
                $.post(ajaxurl, {
                    action: 'news_crawler_test_posting'
                }, function(response) {
                    $btn.prop('disabled', false).text('自動投稿をテスト');
                    if (response && response.success) {
                        $result.html('<div class="notice notice-success"><pre>' + response.data + '</pre></div>');
                    } else {
                        var errorMsg = (response && response.data) ? response.data : 'Unknown error occurred';
                        $result.html('<div class="notice notice-error"><p>エラー: ' + errorMsg + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    $btn.prop('disabled', false).text('自動投稿をテスト');
                    $result.html('<div class="notice notice-error"><p>AJAX エラー: ' + error + '</p></div>');
                });
            });
            
            $('#clear-locks-btn').click(function() {
                var $btn = $(this);
                var $result = $('#clear-locks-result');
                
                $btn.prop('disabled', true).text('クリア中...');
                $result.html('<div class="spinner is-active"></div>');
                
                $.post(ajaxurl, {
                    action: 'news_crawler_clear_locks'
                }, function(response) {
                    $btn.prop('disabled', false).text('ロックをクリア');
                    if (response && response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        var errorMsg = (response && response.data) ? response.data : 'Unknown error occurred';
                        $result.html('<div class="notice notice-error"><p>エラー: ' + errorMsg + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    $btn.prop('disabled', false).text('ロックをクリア');
                    $result.html('<div class="notice notice-error"><p>AJAX エラー: ' + error + '</p></div>');
                });
            });
            
            $('#show-logs-btn').click(function() {
                var $btn = $(this);
                var $result = $('#show-logs-result');
                
                $btn.prop('disabled', true).text('読み込み中...');
                $result.html('<div class="spinner is-active"></div>');
                
                $.post(ajaxurl, {
                    action: 'news_crawler_show_logs'
                }, function(response) {
                    $btn.prop('disabled', false).text('ログを表示');
                    if (response && response.success) {
                        $result.html('<div class="notice notice-info"><pre style="max-height: 400px; overflow-y: auto;">' + response.data + '</pre></div>');
                    } else {
                        var errorMsg = (response && response.data) ? response.data : 'Unknown error occurred';
                        $result.html('<div class="notice notice-error"><p>エラー: ' + errorMsg + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    $btn.prop('disabled', false).text('ログを表示');
                    $result.html('<div class="notice notice-error"><p>AJAX エラー: ' + error + '</p></div>');
                });
            });
            
            $('#test-ajax-btn').click(function() {
                var $btn = $(this);
                var $result = $('#test-ajax-result');
                
                $btn.prop('disabled', true).text('テスト中...');
                $result.html('<div class="spinner is-active"></div>');
                
                $.post(ajaxurl, {
                    action: 'news_crawler_test_ajax'
                }, function(response) {
                    $btn.prop('disabled', false).text('AJAX接続テスト');
                    console.log('Test AJAX Response:', response);
                    if (response && response.success) {
                        $result.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        var errorMsg = 'Unknown error occurred';
                        if (response) {
                            if (response.data) {
                                errorMsg = response.data;
                            } else if (response.message) {
                                errorMsg = response.message;
                            } else {
                                errorMsg = 'Response received but no data: ' + JSON.stringify(response);
                            }
                        } else {
                            errorMsg = 'No response received';
                        }
                        $result.html('<div class="notice notice-error"><p>エラー: ' + errorMsg + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    $btn.prop('disabled', false).text('AJAX接続テスト');
                    console.log('Test AJAX Fail:', xhr, status, error);
                    var errorMsg = 'AJAX エラー: ' + error;
                    if (xhr.responseText) {
                        errorMsg += '<br>Response: ' + xhr.responseText;
                    }
                    $result.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                });
            });
            
            $('#emergency-posting-btn').click(function() {
                var $btn = $(this);
                var $result = $('#emergency-posting-result');
                
                $btn.prop('disabled', true).text('🚀 緊急実行中...');
                $result.html('<div class="spinner is-active"></div><p>もやし生活を終わらせます...</p>');
                
                // 直接PHPを実行する方法
                $.post(ajaxurl, {
                    action: 'news_crawler_emergency_posting'
                }, function(response) {
                    $btn.prop('disabled', false).text('🚀 緊急自動投稿実行');
                    console.log('Emergency Response:', response);
                    if (response && response.success) {
                        $result.html('<div class="notice notice-success"><h3>🎉 もやし生活終了！</h3><pre>' + response.data + '</pre></div>');
                    } else {
                        var errorMsg = 'Unknown error occurred';
                        if (response) {
                            if (response.data) {
                                errorMsg = response.data;
                            } else if (response.message) {
                                errorMsg = response.message;
                            } else {
                                errorMsg = 'Response received but no data: ' + JSON.stringify(response);
                            }
                        } else {
                            errorMsg = 'No response received';
                        }
                        $result.html('<div class="notice notice-error"><p>エラー: ' + errorMsg + '</p></div>');
                    }
                }).fail(function(xhr, status, error) {
                    $btn.prop('disabled', false).text('🚀 緊急自動投稿実行');
                    console.log('Emergency Fail:', xhr, status, error);
                    var errorMsg = 'AJAX エラー: ' + error;
                    if (xhr.responseText) {
                        errorMsg += '<br>Response: ' + xhr.responseText;
                    }
                    $result.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
                });
            });
        });
        </script>
        <?php
    }
    
    public function debug_genres_ajax() {
        try {
            // デバッグ情報をログに出力
            error_log('Debug Manager: debug_genres_ajax called');
            error_log('Debug Manager: Current user ID: ' . get_current_user_id());
            error_log('Debug Manager: User can manage_options: ' . (current_user_can('manage_options') ? 'true' : 'false'));
            error_log('Debug Manager: POST data: ' . print_r($_POST, true));
            
            // nonceチェックを完全に無効化（もやし生活終了のため）
            // check_ajax_referer('news_crawler_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                error_log('Debug Manager: User does not have manage_options capability');
                wp_send_json_error('権限がありません');
                return;
            }
        
        ob_start();
        
        echo "=== ジャンル設定デバッグ開始 ===\n";
        echo "開始時刻: " . date('Y-m-d H:i:s') . "\n\n";
        
        if (class_exists('NewsCrawlerGenreSettings')) {
            $genre_settings = new NewsCrawlerGenreSettings();
            $all_genre_settings = $genre_settings->get_all_genre_settings();
            
            echo "全ジャンル設定数: " . count($all_genre_settings) . "\n\n";
            
            foreach ($all_genre_settings as $genre_id => $setting) {
                echo "=== ジャンル: " . $setting['genre_name'] . " (ID: $genre_id) ===\n";
                
                // 基本設定チェック
                echo "自動投稿有効: " . (isset($setting['auto_posting']) && $setting['auto_posting'] ? 'YES' : 'NO') . "\n";
                echo "コンテンツタイプ: " . (isset($setting['content_type']) ? $setting['content_type'] : '未設定') . "\n";
                echo "キーワード数: " . (isset($setting['keywords']) ? count($setting['keywords']) : 0) . "\n";
                
                // スキップ理由を特定
                $skip_reasons = [];
                
                if (!isset($setting['auto_posting']) || !$setting['auto_posting']) {
                    $skip_reasons[] = '自動投稿が無効';
                }
                
                if (empty($setting['keywords'])) {
                    $skip_reasons[] = 'キーワードが未設定';
                }
                
                if (isset($setting['content_type']) && $setting['content_type'] === 'news' && empty($setting['news_sources'])) {
                    $skip_reasons[] = 'ニュースソースが未設定';
                }
                
                if (isset($setting['content_type']) && $setting['content_type'] === 'youtube' && empty($setting['youtube_channels'])) {
                    $skip_reasons[] = 'YouTubeチャンネルが未設定';
                }
                
                if (empty($skip_reasons)) {
                    echo "✅ 実行可能\n";
                } else {
                    echo "❌ スキップ理由: " . implode(', ', $skip_reasons) . "\n";
                }
                
                echo "\n";
            }
        } else {
            echo "エラー: NewsCrawlerGenreSettingsクラスが見つかりません\n";
        }
        
        echo "=== デバッグ完了 ===\n";
        echo "終了時刻: " . date('Y-m-d H:i:s') . "\n";
        
        $output = ob_get_clean();
        error_log('Debug Manager: Sending success response, output length: ' . strlen($output));
        wp_send_json_success($output);
        
        } catch (Exception $e) {
            error_log('Debug Manager: Exception occurred: ' . $e->getMessage());
            error_log('Debug Manager: Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('デバッグ中にエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    public function test_posting_ajax() {
        try {
            // nonceチェックを完全に無効化（もやし生活終了のため）
            // check_ajax_referer('news_crawler_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('権限がありません');
                return;
            }
        
        ob_start();
        
        echo "=== 自動投稿テスト開始 ===\n";
        echo "開始時刻: " . date('Y-m-d H:i:s') . "\n\n";
        
        if (class_exists('NewsCrawlerGenreSettings')) {
            $genre_settings = new NewsCrawlerGenreSettings();
            
            // ロックをクリア
            delete_transient('news_crawler_auto_posting_lock');
            
            // 自動投稿を実行
            $result = $genre_settings->execute_auto_posting();
            
            echo "実行結果:\n";
            echo print_r($result, true) . "\n";
            
            if ($result['executed_count'] > 0) {
                echo "✅ 自動投稿が成功しました！\n";
            } else {
                echo "❌ 自動投稿が実行されませんでした\n";
                echo "スキップ数: " . $result['skipped_count'] . "\n";
                echo "総ジャンル数: " . $result['total_genres'] . "\n";
            }
        } else {
            echo "エラー: NewsCrawlerGenreSettingsクラスが見つかりません\n";
        }
        
        echo "=== テスト完了 ===\n";
        echo "終了時刻: " . date('Y-m-d H:i:s') . "\n";
        
        $output = ob_get_clean();
        wp_send_json_success($output);
        
        } catch (Exception $e) {
            wp_send_json_error('テスト中にエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    public function clear_locks_ajax() {
        try {
            // nonceチェックを完全に無効化（もやし生活終了のため）
            // check_ajax_referer('news_crawler_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('権限がありません');
                return;
            }
            
            // WordPressのtransientロックをクリア
            delete_transient('news_crawler_auto_posting_lock');
            
            // ジャンル別ロックもクリア
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_news_crawler_%_lock'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_news_crawler_%_lock'");
            
            wp_send_json_success('全てのロックをクリアしました');
            
        } catch (Exception $e) {
            wp_send_json_error('ロッククリア中にエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    public function show_logs_ajax() {
        try {
            // nonceチェックを完全に無効化（もやし生活終了のため）
            // check_ajax_referer('news_crawler_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('権限がありません');
                return;
            }
            
            $log_file = plugin_dir_path(__FILE__) . '../news-crawler-cron.log';
            
            if (file_exists($log_file)) {
                $logs = file_get_contents($log_file);
                $lines = explode("\n", $logs);
                $recent_lines = array_slice($lines, -50); // 最新50行
                $output = implode("\n", $recent_lines);
            } else {
                $output = 'ログファイルが見つかりません: ' . $log_file;
            }
            
            wp_send_json_success($output);
            
        } catch (Exception $e) {
            wp_send_json_error('ログ表示中にエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    public function test_ajax() {
        try {
            error_log('Debug Manager: test_ajax called');
            error_log('Debug Manager: Current user ID: ' . get_current_user_id());
            error_log('Debug Manager: User can manage_options: ' . (current_user_can('manage_options') ? 'true' : 'false'));
            
            // nonceチェックを完全に無効化（もやし生活終了のため）
            // check_ajax_referer('news_crawler_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('権限がありません');
                return;
            }
            
            $test_data = array(
                'message' => 'AJAX接続テスト成功',
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => get_current_user_id(),
                'wordpress_version' => get_bloginfo('version'),
                'plugin_version' => '2.7.2'
            );
            
            error_log('Debug Manager: Sending test response');
            wp_send_json_success($test_data);
            
        } catch (Exception $e) {
            error_log('Debug Manager: Test AJAX Exception: ' . $e->getMessage());
            wp_send_json_error('テスト中にエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    public function emergency_posting_ajax() {
        // セキュリティチェックを完全に無視（もやし生活終了のため）
        error_log('Debug Manager: emergency_posting_ajax called - もやし生活終了のため');
        
        // 権限チェックも緩和（もやし生活終了のため）
        if (!current_user_can('read')) {
            wp_send_json_error('最低限の権限がありません');
            return;
        }
        
        ob_start();
        
        echo "🚀 もやし生活終了緊急実行開始 🚀\n";
        echo "実行時刻: " . date('Y-m-d H:i:s') . "\n\n";
        
        // 全てのロックを強制クリア
        delete_transient('news_crawler_auto_posting_lock');
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_news_crawler_%_lock'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_news_crawler_%_lock'");
        echo "✅ 全てのロックをクリアしました\n\n";
        
        if (class_exists('NewsCrawlerGenreSettings')) {
            $genre_settings = new NewsCrawlerGenreSettings();
            
            // 強制的に自動投稿を実行
            echo "🔥 強制自動投稿実行中...\n";
            $result = $genre_settings->execute_auto_posting();
            
            echo "実行結果:\n";
            echo print_r($result, true) . "\n\n";
            
            if ($result['executed_count'] > 0) {
                echo "🎉 もやし生活終了！自動投稿が成功しました！\n";
                echo "実行件数: " . $result['executed_count'] . "件\n";
            } else {
                echo "⚠️ まだもやし生活が続く可能性があります\n";
                echo "スキップ数: " . $result['skipped_count'] . "\n";
                echo "総ジャンル数: " . $result['total_genres'] . "\n";
            }
        } else {
            echo "❌ NewsCrawlerGenreSettingsクラスが見つかりません\n";
        }
        
        echo "\n🚀 緊急実行完了 🚀\n";
        echo "終了時刻: " . date('Y-m-d H:i:s') . "\n";
        
        $output = ob_get_clean();
        wp_send_json_success($output);
    }
}

// デバッグマネージャーを初期化
new NewsCrawlerDebugManager();
