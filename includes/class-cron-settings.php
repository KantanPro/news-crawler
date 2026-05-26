<?php
/**
 * News Crawler Cron設定管理クラス
 * 生成されたCronジョブ設定のみを表示
 */

if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerCronSettings {
    
    private $option_name = 'news_crawler_cron_settings';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * 管理メニューに追加
     */
    public function add_admin_menu() {
        add_submenu_page(
            'news-crawler-settings',
            '自動投稿設定',
            '自動投稿設定',
            'manage_options',
            'news-crawler-cron-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * 管理画面の初期化
     */
    public function admin_init() {
        // news_crawler_basic_settings の保存は NewsCrawlerSettingsManager に統一
    }
    
    /**
     * 管理画面用スクリプトの読み込み
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'news-crawler_page_news-crawler-cron-settings'
            && $hook !== 'news-crawler-main_page_news-crawler-cron-settings') {
            return;
        }
        
        wp_enqueue_style('ktp-admin-style', plugin_dir_url(__FILE__) . '../assets/css/auto-posting-admin.css', array(), NEWS_CRAWLER_VERSION);
        wp_enqueue_script(
            'nc-x-settings-admin',
            plugin_dir_url(__FILE__) . '../assets/js/x-settings-admin.js',
            array('jquery'),
            NEWS_CRAWLER_VERSION,
            true
        );
    }
    
    /**
     * 管理画面の表示
     */
    public function admin_page() {
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1 class="ktp-admin-title">
                    <span class="ktp-icon">⚙️</span>
                    News Crawler <?php echo esc_html($this->get_plugin_version()); ?> - 自動投稿設定
                </h1>
            
            <div class="ktp-admin-content">
                <!-- ブログ自動投稿設定カード -->
                <div class="ktp-settings-card">
                    <div class="ktp-card-header">
                        <h2 class="ktp-card-title">
                            <span class="ktp-icon">📋</span>
                            ブログ自動投稿設定
                        </h2>
                    </div>
                    <div class="ktp-card-content">
                        <?php $this->display_generated_cron_settings(); ?>
                    </div>
                </div>
                
                <!-- X（Twitter）設定カード -->
                <div class="ktp-settings-card">
                    <div class="ktp-card-header">
                        <h2 class="ktp-card-title">
                            <span class="ktp-icon">🐦</span>
                            X（旧Twitter）自動シェア設定
                        </h2>
                    </div>
                    <div class="ktp-card-content">
                        <?php $this->render_x_settings(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 生成されたCronジョブ設定を表示
     */
    private function display_generated_cron_settings() {
        $script_path = dirname(plugin_dir_path(__FILE__)) . '/news-crawler-cron.sh';
        $script_exists = file_exists($script_path);
        
        if (!$script_exists) {
            echo '<div class="ktp-error-box">';
            echo '<span class="ktp-error-icon">❌</span>';
            echo '<p><strong>エラー：</strong>cronスクリプトが見つかりません。</p>';
            echo '<p>スクリプトパス: ' . esc_html($script_path) . '</p>';
            echo '</div>';
            return;
        }
        
        
        // 生成されたcronコマンドを表示
        $cron_command = $this->generate_cron_command();
        
        echo '<div class="ktp-command-box">';
        echo '<h3 style="margin-top: 0;">📝 Cronスクリプトパス</h3>';
        echo '<p style="margin-bottom: 16px;">以下のパスをサーバーのcrontabに追加してください：</p>';
        echo '<div class="ktp-code-block" style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 16px; border-radius: 6px; margin-bottom: 16px; overflow-x: auto;">';
        echo '<code style="color: #333; font-family: Monaco, Menlo, Ubuntu Mono, monospace; font-size: 14px; line-height: 1.6; word-break: break-all;">' . esc_html($cron_command) . '</code>';
        echo '</div>';
        echo '<button type="button" class="button button-secondary" onclick="copyToClipboard(\'' . esc_js($cron_command) . '\')">パスをコピー</button>';
        echo '</div>';
        
        // 設定手順を表示
        echo '<div class="ktp-instructions-box" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">';
        echo '<h3 style="margin-top: 0;">📋 設定手順</h3>';
        echo '<ol style="line-height: 1.6;">';
        echo '<li>上記のパスをコピーします</li>';
        echo '<li>実行頻度とパスを組み合わせてcrontabに追加します（例：<code style="background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-family: Monaco, Menlo, Ubuntu Mono, monospace;">0 * * * * /path/to/script</code>）</li>';
        echo '</ol>';
        echo '</div>';
        
        
        // JavaScript
        ?>
        <script>
        function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(function() {
                alert('パスをクリップボードにコピーしました');
            }, function(err) {
                console.error('コピーに失敗しました: ', err);
            });
        }
        </script>
        <?php
    }
    
    /**
     * Cronコマンドを生成
     */
    private function generate_cron_command() {
        $script_path = dirname(plugin_dir_path(__FILE__)) . '/news-crawler-cron.sh';
        
        // スクリプトのパスのみを返す
        return $script_path;
    }
    
    
    /**
     * X 設定を描画（xLabo 風 UI）
     */
    private function render_x_settings() {
        $settings = get_option('news_crawler_basic_settings', array());
        if (!is_array($settings)) {
            $settings = array();
        }

        $oauth = News_Crawler_X_OAuth::instance();
        $auth_method = $oauth->get_auth_method($settings);
        $connected = $oauth->is_connected($settings);
        $connected_label = $connected ? $oauth->get_connected_display_label($settings) : '';
        $connected_username = $connected ? $oauth->get_connected_username($settings) : '';
        $connection_diagnostics = $oauth->get_connection_diagnostics($settings);
        $auth_url = $oauth->get_authorization_url();
        $redirect_uri = $oauth->get_redirect_uri();

        $template = isset($settings['twitter_message_template']) ? $settings['twitter_message_template'] : "%TITLE%\n%URL%";
        if ($template === '{title}' || $template === '%TITLE%') {
            $template = "%TITLE%\n%URL%";
        }
        ?>
        <p>News Crawler が作成した投稿を公開すると、X へ自動シェアします。</p>

        <form method="post" action="options.php">
            <?php settings_fields('news_crawler_basic_settings'); ?>

            <h3 class="title">一般設定</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">自動シェア</th>
                    <td>
                        <label>
                            <input type="hidden" name="news_crawler_basic_settings[twitter_enabled]" value="0" />
                            <input type="checkbox" name="news_crawler_basic_settings[twitter_enabled]" value="1" <?php checked(!empty($settings['twitter_enabled'])); ?> />
                            投稿公開時に X へ自動シェアする
                        </label>
                    </td>
                </tr>
            </table>

            <h3 class="title">投稿テンプレート</h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nc-x-template">ツイート本文</label></th>
                    <td>
                        <textarea id="nc-x-template" name="news_crawler_basic_settings[twitter_message_template]" rows="4" class="large-text code"><?php echo esc_textarea($template); ?></textarea>
                        <p class="description">利用可能: %TITLE%, %URL%, %EXCERPT%, %SITENAME%, %AUTHORNAME%</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="nc-x-hashtags">固定ハッシュタグ</label></th>
                    <td>
                        <input id="nc-x-hashtags" type="text" class="regular-text" name="news_crawler_basic_settings[twitter_hashtags]" value="<?php echo esc_attr($settings['twitter_hashtags'] ?? ''); ?>" placeholder="#ニュース #自動投稿" />
                    </td>
                </tr>
            </table>

            <h3 class="title">X API 認証</h3>
            <p class="description">
                <a href="https://developer.x.com/" target="_blank" rel="noopener noreferrer">X Developer Portal</a>
                でアプリを作成し、Callback URL に以下を登録してください。
            </p>
            <p><code><?php echo esc_html($redirect_uri); ?></code></p>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">認証方式</th>
                    <td>
                        <label style="margin-right:16px;">
                            <input type="radio" name="news_crawler_basic_settings[twitter_auth_method]" value="oauth2" class="nc-x-auth-method" <?php checked('oauth2', $auth_method); ?> />
                            OAuth 2.0（推奨）
                        </label>
                        <label>
                            <input type="radio" name="news_crawler_basic_settings[twitter_auth_method]" value="oauth1" class="nc-x-auth-method" <?php checked('oauth1', $auth_method); ?> />
                            OAuth 1.0a
                        </label>
                    </td>
                </tr>
            </table>

            <div class="nc-x-auth-panel nc-x-auth-oauth2" <?php echo ($auth_method === 'oauth1') ? 'style="display:none;"' : ''; ?>>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="nc-x-client-id">Client ID</label></th>
                        <td>
                            <input id="nc-x-client-id" type="text" class="regular-text" name="news_crawler_basic_settings[twitter_client_id]" value="<?php echo esc_attr($settings['twitter_client_id'] ?? ''); ?>" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nc-x-client-secret">Client Secret</label></th>
                        <td>
                            <input id="nc-x-client-secret" type="password" class="regular-text" name="news_crawler_basic_settings[twitter_client_secret]" value="" autocomplete="new-password" placeholder="変更する場合のみ入力" />
                        </td>
                    </tr>
                </table>
            </div>

            <div class="nc-x-auth-panel nc-x-auth-oauth1" <?php echo ($auth_method !== 'oauth1') ? 'style="display:none;"' : ''; ?>>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="nc-x-api-key">API Key</label></th>
                        <td><input id="nc-x-api-key" type="text" class="regular-text" name="news_crawler_basic_settings[twitter_api_key]" value="<?php echo esc_attr($settings['twitter_api_key'] ?? ''); ?>" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nc-x-api-secret">API Secret</label></th>
                        <td><input id="nc-x-api-secret" type="password" class="regular-text" name="news_crawler_basic_settings[twitter_api_secret]" value="" autocomplete="new-password" placeholder="変更する場合のみ入力" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nc-x-access-token">Access Token</label></th>
                        <td><input id="nc-x-access-token" type="text" class="regular-text" name="news_crawler_basic_settings[twitter_access_token]" value="<?php echo esc_attr($settings['twitter_access_token'] ?? ''); ?>" autocomplete="off" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nc-x-access-token-secret">Access Token Secret</label></th>
                        <td><input id="nc-x-access-token-secret" type="password" class="regular-text" name="news_crawler_basic_settings[twitter_access_token_secret]" value="" autocomplete="new-password" placeholder="変更する場合のみ入力" /></td>
                    </tr>
                </table>
                <p class="description">Developer Portal の Keys and Tokens から Read and Write 権限のトークンを生成してください。</p>
            </div>

            <?php submit_button('設定を保存'); ?>
        </form>

        <hr />

        <h3>接続操作</h3>
        <?php if ($connected && $connected_label !== '') : ?>
            <p class="nc-x-connection-status"><strong>接続中</strong> <?php echo esc_html($connected_label); ?></p>
        <?php elseif ($connected) : ?>
            <p class="nc-x-connection-status"><strong>接続済み</strong>（アカウント名を未取得）</p>
        <?php endif; ?>
        <div class="nc-x-actions">
            <?php if ($auth_method === 'oauth2' && $auth_url !== '') : ?>
                <a class="button button-primary" href="<?php echo esc_url($auth_url); ?>">X アカウントを接続</a>
            <?php endif; ?>

            <?php if ($connected) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-left:8px;">
                    <?php wp_nonce_field('nc_x_test_tweet'); ?>
                    <input type="hidden" name="action" value="nc_x_test_tweet" />
                    <?php submit_button('接続テスト投稿', 'secondary', 'submit', false); ?>
                </form>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-left:8px;">
                    <?php wp_nonce_field('nc_x_refresh_profile'); ?>
                    <input type="hidden" name="action" value="nc_x_refresh_profile" />
                    <?php submit_button('アカウント名を再取得', 'secondary', 'submit', false); ?>
                </form>

                <?php if ($auth_method === 'oauth2') : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-left:8px;">
                        <?php wp_nonce_field('nc_x_disconnect'); ?>
                        <input type="hidden" name="action" value="nc_x_disconnect" />
                        <?php submit_button('接続を解除', 'delete', 'submit', false); ?>
                    </form>
                <?php endif; ?>
            <?php elseif ($auth_method === 'oauth2') : ?>
                <p class="description">Client ID / Secret を保存後、「X アカウントを接続」をクリックしてください。</p>
            <?php endif; ?>
        </div>

        <?php if ($connected) : ?>
            <div class="nc-x-manual-username" style="margin-top:16px;padding:12px;border:1px solid #ddd;border-radius:4px;background:#fafafa;">
                <h4 style="margin-top:0;">アカウント名を手動で登録</h4>
                <p class="description" style="margin-bottom:8px;">
                    X API（<code>/2/users/me</code>）が呼び出せない場合（Free プラン・レート制限など）は、こちらに直接 <code>@username</code> を入力してください。
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('nc_x_save_manual_username'); ?>
                    <input type="hidden" name="action" value="nc_x_save_manual_username" />
                    <label>
                        @<input type="text" name="nc_x_manual_username" value="<?php echo esc_attr($connected_username); ?>" placeholder="your_username" pattern="[A-Za-z0-9_]{1,15}" maxlength="15" style="width:200px;" />
                    </label>
                    <?php submit_button('保存', 'secondary', 'submit', false); ?>
                </form>
            </div>
        <?php endif; ?>

        <div class="nc-x-connection-diagnostics" style="margin-top:16px;padding:12px;border:1px solid #ddd;border-radius:4px;background:#fff;">
            <h4 style="margin-top:0;">接続状態の診断</h4>
            <table class="widefat striped" style="max-width:760px;">
                <tbody>
                    <tr>
                        <th scope="row">認証方式</th>
                        <td><?php echo esc_html($connection_diagnostics['method']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Client ID</th>
                        <td><?php echo !empty($connection_diagnostics['client_id_saved']) ? '保存済み' : '未保存'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Client Secret</th>
                        <td><?php echo !empty($connection_diagnostics['client_secret_saved']) ? '保存済み' : '未保存'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">OAuth 2.0 Access Token</th>
                        <td>
                            <?php if (!empty($connection_diagnostics['oauth2_access_token_saved'])) : ?>
                                保存済み
                            <?php else : ?>
                                <strong style="color:#b32d2e;">未保存</strong>（X の許可画面から WordPress への戻り処理が完了していません）
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Refresh Token</th>
                        <td><?php echo !empty($connection_diagnostics['oauth2_refresh_token_saved']) ? '保存済み' : '未保存'; ?></td>
                    </tr>
                    <tr>
                        <th scope="row">接続判定</th>
                        <td><?php echo !empty($connection_diagnostics['connected']) ? '<strong style="color:#008a20;">接続済み</strong>' : '<strong style="color:#b32d2e;">未接続</strong>'; ?></td>
                    </tr>
                </tbody>
            </table>
            <?php if (empty($connection_diagnostics['connected'])) : ?>
                <p class="description" style="margin-top:8px;">
                    <code>接続テスト投稿</code> は OAuth 2.0 Access Token が保存された後に表示されます。
                    X の許可画面で「許可する」まで完了し、WordPress のコールバック URL に戻る必要があります。
                </p>
            <?php endif; ?>
        </div>

        <hr />

        <?php $this->render_share_log_table(); ?>
        <?php
    }

    /**
     * X シェアログを描画
     */
    private function render_share_log_table() {
        $entries = News_Crawler_X_Share_Log::get_entries();
        ?>
        <h3>シェアログ</h3>
        <?php if (empty($entries)) : ?>
            <p>ログはまだありません。</p>
        <?php else : ?>
            <table class="widefat striped nc-x-share-log-table">
                <thead>
                    <tr>
                        <th scope="col">日時</th>
                        <th scope="col">結果</th>
                        <th scope="col">内容</th>
                        <th scope="col">失敗原因</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry) : ?>
                        <?php
                        if (!is_array($entry)) {
                            continue;
                        }
                        $level = (string) ($entry['level'] ?? 'info');
                        $error = trim((string) ($entry['error'] ?? ''));
                        ?>
                        <tr>
                            <td><?php echo esc_html((string) ($entry['time'] ?? '')); ?></td>
                            <td>
                                <span class="nc-x-log-level nc-x-log-level-<?php echo esc_attr($level); ?>">
                                    <?php echo esc_html(News_Crawler_X_Share_Log::get_level_label($level)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html((string) ($entry['message'] ?? '')); ?></td>
                            <td><?php echo $error !== '' ? esc_html($error) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
                <?php wp_nonce_field('nc_x_clear_share_log'); ?>
                <input type="hidden" name="action" value="nc_x_clear_share_log" />
                <?php submit_button('ログをクリア', 'secondary', 'submit', false); ?>
            </form>
        <?php endif; ?>
        <?php
    }
    
    /**
     * 強制的にcronスクリプトを作成
     */
    public function force_create_cron_script() {
        $script_path = dirname(plugin_dir_path(__FILE__)) . '/news-crawler-cron.sh';
        
        // スクリプトが既に存在する場合は何もしない
        if (file_exists($script_path)) {
            error_log('News Crawler: cronスクリプトは既に存在します - パス: ' . $script_path);
            return;
        }
        
        error_log('News Crawler: cronスクリプトを作成中 - パス: ' . $script_path);
        
        // 既存のcronスクリプトをコピーして作成
        $source_script = dirname(plugin_dir_path(__FILE__)) . '/news-crawler-cron-backup.sh';
        
        if (file_exists($source_script)) {
            // バックアップスクリプトからコピー
            $result = copy($source_script, $script_path);
            if ($result) {
                chmod($script_path, 0755);
                error_log('News Crawler: バックアップからcronスクリプトを復元しました - パス: ' . $script_path);
                return;
            }
        }
        
        // バックアップが存在しない場合は基本的なスクリプトを作成
        $script_content = '#!/bin/bash
# News Crawler Auto Posting Script
# Generated automatically by News Crawler plugin

# WordPress root directory
WP_ROOT="' . ABSPATH . '"

# Change to WordPress directory
cd "$WP_ROOT"

# Run WordPress cron
php wp-cron.php

# Log the execution
echo "$(date): News Crawler cron executed" >> wp-content/plugins/news-crawler/news-crawler-cron.log
';
        
        // スクリプトファイルを作成
        $result = file_put_contents($script_path, $script_content);
        
        if ($result === false) {
            error_log('News Crawler: cronスクリプトの作成に失敗しました - パス: ' . $script_path);
            return;
        }
        
        // 実行権限を付与
        chmod($script_path, 0755);
        
        error_log('News Crawler: cronスクリプトを作成しました - パス: ' . $script_path);
    }
    
    /**
     * プラグインバージョンを取得
     */
    private function get_plugin_version() {
        if (defined('NEWS_CRAWLER_VERSION')) {
            return NEWS_CRAWLER_VERSION;
        }
        return '2.9.3';
    }
}

