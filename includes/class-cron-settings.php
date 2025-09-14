<?php
/**
 * Cron Settings Class
 * 
 * Cron設定を管理画面で設定するためのクラス
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerCronSettings {
    private $option_name = 'news_crawler_cron_settings';
    
    public function __construct() {
        // メニュー登録はNews Crawlerメインメニューから行われるため、
        // ここではadmin_initのみ実行
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_validate_cron_settings', array($this, 'validate_cron_settings'));
        add_action('wp_ajax_generate_cron_script', array($this, 'generate_cron_script'));
        add_action('wp_ajax_news_crawler_cron_execute', array($this, 'handle_cron_execution'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // プラグイン初期化時にCronスクリプトの存在チェックと自動作成
        add_action('init', array($this, 'check_and_create_cron_script'), 20);
    }
    
    /**
     * 管理メニューに追加
     * News Crawlerメニューのサブメニューとして登録されるため、
     * このメソッドは呼び出されません
     */
    public function add_admin_menu() {
        // このメソッドは使用されません
        // Cron設定はNews Crawlerメニューのサブメニューとして登録されます
    }
    
    /**
     * 管理画面の初期化
     */
    public function admin_init() {
        register_setting('news-crawler-cron-settings', $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'cron_basic_settings',
            'Cronジョブ設定',
            array($this, 'section_callback'),
            'news-crawler-cron-settings'
        );
        
        // シェルスクリプト名
        add_settings_field(
            'shell_script_name',
            'シェルスクリプト名',
            array($this, 'shell_script_name_callback'),
            'news-crawler-cron-settings',
            'cron_basic_settings'
        );
        
        // 分
        add_settings_field(
            'minute',
            '分 (0-59)',
            array($this, 'minute_callback'),
            'news-crawler-cron-settings',
            'cron_basic_settings'
        );
        
        // 時
        add_settings_field(
            'hour',
            '時 (0-23)',
            array($this, 'hour_callback'),
            'news-crawler-cron-settings',
            'cron_basic_settings'
        );
        
        // 日
        add_settings_field(
            'day',
            '日 (1-31)',
            array($this, 'day_callback'),
            'news-crawler-cron-settings',
            'cron_basic_settings'
        );
        
        // 月
        add_settings_field(
            'month',
            '月 (1-12)',
            array($this, 'month_callback'),
            'news-crawler-cron-settings',
            'cron_basic_settings'
        );
        
        // 曜日
        add_settings_field(
            'weekday',
            '曜日 (0-7, 0と7は日曜日)',
            array($this, 'weekday_callback'),
            'news-crawler-cron-settings',
            'cron_basic_settings'
        );
    }
    
    /**
     * セクションコールバック
     */
    public function section_callback() {
        echo '<p>サーバーのcronジョブ設定を行います。自動投稿を実現するために必要な設定項目を入力してください。</p>';
    }
    
    /**
     * シェルスクリプト名のコールバック
     */
    public function shell_script_name_callback() {
        $options = get_option($this->option_name);
        $value = isset($options['shell_script_name']) ? $options['shell_script_name'] : 'news-crawler-cron.sh';
        $script_path = NEWS_CRAWLER_PLUGIN_DIR . $value;
        
        // プラグインディレクトリの書き込み権限をチェック
        $plugin_writable = is_writable(NEWS_CRAWLER_PLUGIN_DIR);
        $upload_dir = wp_upload_dir();
        $upload_writable = is_writable($upload_dir['basedir']);
        
        // 複数のパスでファイル存在をチェック
        $script_exists = $this->check_script_exists($value);
        $actual_path = $this->get_actual_script_path($value);
        
        echo '<input type="text" id="shell_script_name" name="' . $this->option_name . '[shell_script_name]" value="' . esc_attr($value) . '" class="regular-text" required />';
        echo '<p class="description"><strong>必須項目：</strong>cronジョブで実行するシェルスクリプトのファイル名を入力してください。</p>';
        
        if ($script_exists && $actual_path) {
            echo '<p style="color: green;"><strong>✓ スクリプトが存在します：</strong> ' . esc_html($actual_path) . '</p>';
            echo '<p><strong>実際のパス：</strong> <code>' . esc_html($actual_path) . '</code></p>';
        } else {
            echo '<p style="color: orange;"><strong>⚠ スクリプトが存在しません：</strong> ' . esc_html($script_path) . '</p>';
            
            if ($plugin_writable) {
                echo '<button type="button" id="generate_script_btn" class="button button-secondary">シェルスクリプトを自動生成</button>';
            } elseif ($upload_writable) {
                echo '<button type="button" id="generate_script_btn" class="button button-secondary">シェルスクリプトを自動生成（アップロードディレクトリに保存）</button>';
            } else {
                echo '<p style="color: red;"><strong>❌ 書き込み権限がありません：</strong> プラグインディレクトリとアップロードディレクトリの両方に書き込み権限がありません。</p>';
            }
        }
        
        echo '<p><strong>絶対パス：</strong> <code>' . esc_html($script_path) . '</code></p>';
        
        if (!$plugin_writable && $upload_writable) {
            $alternative_path = $upload_dir['basedir'] . '/' . $value;
            echo '<p><strong>代替パス（アップロードディレクトリ）：</strong> <code>' . esc_html($alternative_path) . '</code></p>';
        }
        
        // 権限情報を表示
        echo '<div style="background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">';
        echo '<strong>権限情報：</strong><br>';
        echo 'プラグインディレクトリ書き込み可能: ' . ($plugin_writable ? '✓' : '❌') . '<br>';
        echo 'アップロードディレクトリ書き込み可能: ' . ($upload_writable ? '✓' : '❌');
        echo '</div>';
        
        // デバッグ情報を表示
        echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107;">';
        echo '<strong>デバッグ情報：</strong><br>';
        echo 'プラグインディレクトリ: ' . esc_html(NEWS_CRAWLER_PLUGIN_DIR) . '<br>';
        echo 'プラグインディレクトリ存在: ' . (is_dir(NEWS_CRAWLER_PLUGIN_DIR) ? '✓' : '❌') . '<br>';
        echo 'プラグインディレクトリ読み取り可能: ' . (is_readable(NEWS_CRAWLER_PLUGIN_DIR) ? '✓' : '❌') . '<br>';
        echo 'プラグインディレクトリ書き込み可能: ' . ($plugin_writable ? '✓' : '❌') . '<br>';
        echo 'ファイル存在チェック結果: ' . ($script_exists ? '✓' : '❌') . '<br>';
        
        // 詳細なファイル存在チェック
        $plugin_file_path = NEWS_CRAWLER_PLUGIN_DIR . $value;
        
        echo '<br><strong>詳細ファイルチェック：</strong><br>';
        echo 'プラグインファイルパス: ' . esc_html($plugin_file_path) . '<br>';
        echo 'プラグインファイル存在: ' . (file_exists($plugin_file_path) ? '✓' : '❌') . '<br>';
        echo 'プラグインファイル読み取り可能: ' . (is_readable($plugin_file_path) ? '✓' : '❌') . '<br>';
        
        // ディレクトリ内容を表示
        echo '<br><strong>プラグインディレクトリ内容：</strong><br>';
        if (is_readable(NEWS_CRAWLER_PLUGIN_DIR)) {
            $files = scandir(NEWS_CRAWLER_PLUGIN_DIR);
            if ($files !== false) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        $file_path = NEWS_CRAWLER_PLUGIN_DIR . $file;
                        $is_file = is_file($file_path);
                        $is_dir = is_dir($file_path);
                        $readable = is_readable($file_path);
                        $writable = is_writable($file_path);
                        echo '&nbsp;&nbsp;' . esc_html($file) . ' (';
                        echo $is_file ? 'ファイル' : ($is_dir ? 'ディレクトリ' : '不明');
                        echo ', 読み取り: ' . ($readable ? '✓' : '❌');
                        echo ', 書き込み: ' . ($writable ? '✓' : '❌');
                        echo ')<br>';
                    }
                }
            } else {
                echo 'ディレクトリの読み取りに失敗<br>';
            }
        } else {
            echo 'ディレクトリが読み取り不可能<br>';
        }
        
        if ($actual_path) {
            echo '<br>実際のファイルパス: ' . esc_html($actual_path) . '<br>';
        }
        echo '</div>';
    }
    
    /**
     * 分のコールバック
     */
    public function minute_callback() {
        $options = get_option($this->option_name);
        $value = isset($options['minute']) ? $options['minute'] : '0';
        echo '<input type="text" id="minute" name="' . $this->option_name . '[minute]" value="' . esc_attr($value) . '" class="small-text" placeholder="0" />';
        echo '<p class="description">実行する分を指定してください (0-59)。* を指定すると毎分実行されます。例：0, 30, *</p>';
    }
    
    /**
     * 時のコールバック
     */
    public function hour_callback() {
        $options = get_option($this->option_name);
        $value = isset($options['hour']) ? $options['hour'] : '9';
        echo '<input type="text" id="hour" name="' . $this->option_name . '[hour]" value="' . esc_attr($value) . '" class="small-text" placeholder="9" />';
        echo '<p class="description">実行する時を指定してください (0-23)。* を指定すると毎時実行されます。例：9, 12, *</p>';
    }
    
    /**
     * 日のコールバック
     */
    public function day_callback() {
        $options = get_option($this->option_name);
        $value = isset($options['day']) ? $options['day'] : '*';
        echo '<input type="text" id="day" name="' . $this->option_name . '[day]" value="' . esc_attr($value) . '" class="small-text" placeholder="*" />';
        echo '<p class="description">実行する日を指定してください (1-31)。* を指定すると毎日実行されます。例：1, 15, *</p>';
    }
    
    /**
     * 月のコールバック
     */
    public function month_callback() {
        $options = get_option($this->option_name);
        $value = isset($options['month']) ? $options['month'] : '*';
        echo '<input type="text" id="month" name="' . $this->option_name . '[month]" value="' . esc_attr($value) . '" class="small-text" placeholder="*" />';
        echo '<p class="description">実行する月を指定してください (1-12)。* を指定すると毎月実行されます。例：1, 6, *</p>';
    }
    
    /**
     * 曜日のコールバック
     */
    public function weekday_callback() {
        $options = get_option($this->option_name);
        $value = isset($options['weekday']) ? $options['weekday'] : '*';
        echo '<input type="text" id="weekday" name="' . $this->option_name . '[weekday]" value="' . esc_attr($value) . '" class="small-text" placeholder="*" />';
        echo '<p class="description">実行する曜日を指定してください (0-7, 0と7は日曜日)。* を指定すると毎日実行されます。例：0, 1-5, *</p>';
    }
    
    /**
     * 設定のサニタイズ
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // シェルスクリプト名のサニタイズ
        if (isset($input['shell_script_name'])) {
            $sanitized['shell_script_name'] = sanitize_text_field($input['shell_script_name']);
        }
        
        // 分のサニタイズ（cron形式に対応：*, 数値, 範囲, リスト）
        if (isset($input['minute'])) {
            $minute = trim($input['minute']);
            if ($this->is_valid_cron_field($minute, 0, 59)) {
                $sanitized['minute'] = $minute;
            } else {
                $sanitized['minute'] = '0';
            }
        }
        
        // 時のサニタイズ
        if (isset($input['hour'])) {
            $hour = trim($input['hour']);
            if ($this->is_valid_cron_field($hour, 0, 23)) {
                $sanitized['hour'] = $hour;
            } else {
                $sanitized['hour'] = '9';
            }
        }
        
        // 日のサニタイズ
        if (isset($input['day'])) {
            $day = trim($input['day']);
            if ($this->is_valid_cron_field($day, 1, 31)) {
                $sanitized['day'] = $day;
            } else {
                $sanitized['day'] = '*';
            }
        }
        
        // 月のサニタイズ
        if (isset($input['month'])) {
            $month = trim($input['month']);
            if ($this->is_valid_cron_field($month, 1, 12)) {
                $sanitized['month'] = $month;
            } else {
                $sanitized['month'] = '*';
            }
        }
        
        // 曜日のサニタイズ
        if (isset($input['weekday'])) {
            $weekday = trim($input['weekday']);
            if ($this->is_valid_cron_field($weekday, 0, 7)) {
                $sanitized['weekday'] = $weekday;
            } else {
                $sanitized['weekday'] = '*';
            }
        }
        
        // 設定保存時にシェルスクリプトを自動生成
        $this->auto_generate_script_on_save($sanitized);
        
        return $sanitized;
    }
    
    /**
     * Cron形式のフィールドが有効かチェック
     */
    private function is_valid_cron_field($value, $min, $max) {
        if (empty($value)) {
            return false;
        }
        
        // * は常に有効
        if ($value === '*') {
            return true;
        }
        
        // 数値の場合は範囲チェック
        if (is_numeric($value)) {
            return $value >= $min && $value <= $max;
        }
        
        // カンマ区切りのリスト（例：1,3,5）
        if (strpos($value, ',') !== false) {
            $parts = explode(',', $value);
            foreach ($parts as $part) {
                $part = trim($part);
                if (!is_numeric($part) || $part < $min || $part > $max) {
                    return false;
                }
            }
            return true;
        }
        
        // 範囲指定（例：1-5）
        if (strpos($value, '-') !== false) {
            $parts = explode('-', $value);
            if (count($parts) === 2) {
                $start = trim($parts[0]);
                $end = trim($parts[1]);
                return is_numeric($start) && is_numeric($end) && 
                       $start >= $min && $end <= $max && $start <= $end;
            }
        }
        
        // ステップ指定（例：*/5, 0-23/2）
        if (strpos($value, '/') !== false) {
            $parts = explode('/', $value);
            if (count($parts) === 2) {
                $base = trim($parts[0]);
                $step = trim($parts[1]);
                if (!is_numeric($step) || $step <= 0) {
                    return false;
                }
                if ($base === '*') {
                    return true;
                }
                // 範囲のステップ指定もチェック
                return $this->is_valid_cron_field($base, $min, $max);
            }
        }
        
        return false;
    }
    
    /**
     * スクリプトファイルの存在をチェック
     */
    private function check_script_exists($script_name) {
        // プラグインディレクトリでチェック
        $plugin_path = NEWS_CRAWLER_PLUGIN_DIR . $script_name;
        if (file_exists($plugin_path)) {
            return true;
        }
        
        // アップロードディレクトリでチェック
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'] . '/' . $script_name;
        if (file_exists($upload_path)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 実際のスクリプトファイルのパスを取得
     */
    private function get_actual_script_path($script_name) {
        // プラグインディレクトリでチェック
        $plugin_path = NEWS_CRAWLER_PLUGIN_DIR . $script_name;
        if (file_exists($plugin_path)) {
            return $plugin_path;
        }
        
        // アップロードディレクトリでチェック
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'] . '/' . $script_name;
        if (file_exists($upload_path)) {
            return $upload_path;
        }
        
        return null;
    }
    
    /**
     * 管理画面の表示
     */
    public function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'news-crawler' ) );
        }
        
        $options = get_option($this->option_name);
        $cron_command = $this->generate_cron_command($options);
        
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1>News Crawler <?php echo esc_html($this->get_plugin_version()); ?> - Cron設定</h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Cron設定を保存しました。</p>
                </div>
            <?php endif; ?>
            
            <div class="ktp-admin-content">
                <div class="ktp-admin-card">
                    <h2>サーバーCronジョブ設定</h2>
                    <p>自動投稿を実現するために、サーバーのcronジョブ設定を行います。<strong>シェルスクリプト名を入力するだけで、cronジョブ設定が完了します。</strong></p>
                    
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('news-crawler-cron-settings');
                        do_settings_sections('news-crawler-cron-settings');
                        submit_button('設定を保存');
                        ?>
                    </form>
                </div>
                
                <?php if (!empty($cron_command)): ?>
                <div class="ktp-admin-card">
                    <h2>生成されたCronジョブ設定</h2>
                    <p>以下の設定をサーバーのcronジョブに追加してください：</p>
                    <div style="background: #f1f1f1; padding: 15px; border-radius: 4px; font-family: monospace; word-break: break-all; border: 2px solid #0073aa;">
                        <?php echo esc_html($cron_command); ?>
                    </div>
                    <p><strong>重要：</strong>指定したシェルスクリプト（<code><?php echo esc_html($options['shell_script_name'] ?? 'news-crawler-cron.sh'); ?></code>）がサーバー上に存在し、実行権限が設定されている必要があります。</p>
                </div>
                
                <div class="ktp-admin-card">
                    <h2>設定方法（SSH不要）</h2>
                    <p>SSHでログインできない場合でも、以下の方法でcronジョブを設定できます：</p>
                    <ol>
                        <li><strong>ホスティング会社の管理パネルを使用：</strong>
                            <ul>
                                <li>cPanel、Plesk、DirectAdminなどの管理パネルにログイン</li>
                                <li>「Cronジョブ」または「スケジュールタスク」の項目を探す</li>
                                <li>上記の設定を入力して保存</li>
                            </ul>
                        </li>
                        <li><strong>WordPressプラグインを使用：</strong>
                            <ul>
                                <li>「WP Crontrol」などのcron管理プラグインをインストール</li>
                                <li>プラグインの設定画面で上記の設定を追加</li>
                            </ul>
                        </li>
                        <li><strong>サーバー管理会社に依頼：</strong>
                            <ul>
                                <li>上記の設定内容をサーバー管理会社に送信</li>
                                <li>cronジョブの設定を依頼</li>
                            </ul>
                        </li>
                    </ol>
                </div>
                
                <div class="ktp-admin-card">
                    <h2>設定手順（SSH使用可能な場合）</h2>
                    <ol>
                        <li>上記のCronジョブ設定コマンドをコピーします</li>
                        <li>サーバーにSSHでログインします</li>
                        <li><code>crontab -e</code>コマンドでcrontabを編集します</li>
                        <li>コピーしたコマンドを追加します</li>
                        <li>保存して終了します</li>
                        <li><code>crontab -l</code>コマンドで設定が正しく追加されたか確認します</li>
                    </ol>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Cronコマンドを生成
     */
    private function generate_cron_command($options) {
        if (empty($options)) {
            return '';
        }
        
        $minute = isset($options['minute']) ? $options['minute'] : '0';
        $hour = isset($options['hour']) ? $options['hour'] : '9';
        $day = isset($options['day']) ? $options['day'] : '*';
        $month = isset($options['month']) ? $options['month'] : '*';
        $weekday = isset($options['weekday']) ? $options['weekday'] : '*';
        $script_name = isset($options['shell_script_name']) ? $options['shell_script_name'] : 'news-crawler-cron.sh';
        
        // 実際のスクリプトパスを取得
        $script_path = $this->get_actual_script_path($script_name);
        
        // スクリプトが存在しない場合は、プラグインディレクトリのパスを使用
        if (!$script_path) {
            $script_path = NEWS_CRAWLER_PLUGIN_DIR . $script_name;
        }
        
        return sprintf('%s %s %s %s %s %s', $minute, $hour, $day, $month, $weekday, $script_path);
    }
    
    /**
     * Cron設定の検証
     */
    public function validate_cron_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        $minute = sanitize_text_field($_POST['minute'] ?? '');
        $hour = sanitize_text_field($_POST['hour'] ?? '');
        $day = sanitize_text_field($_POST['day'] ?? '');
        $month = sanitize_text_field($_POST['month'] ?? '');
        $weekday = sanitize_text_field($_POST['weekday'] ?? '');
        
        $errors = array();
        
        // 分の検証
        if ($minute !== '*' && (!is_numeric($minute) || $minute < 0 || $minute > 59)) {
            $errors[] = '分は0-59の範囲で指定してください';
        }
        
        // 時の検証
        if ($hour !== '*' && (!is_numeric($hour) || $hour < 0 || $hour > 23)) {
            $errors[] = '時は0-23の範囲で指定してください';
        }
        
        // 日の検証
        if ($day !== '*' && (!is_numeric($day) || $day < 1 || $day > 31)) {
            $errors[] = '日は1-31の範囲で指定してください';
        }
        
        // 月の検証
        if ($month !== '*' && (!is_numeric($month) || $month < 1 || $month > 12)) {
            $errors[] = '月は1-12の範囲で指定してください';
        }
        
        // 曜日の検証
        if ($weekday !== '*' && (!is_numeric($weekday) || $weekday < 0 || $weekday > 7)) {
            $errors[] = '曜日は0-7の範囲で指定してください（0と7は日曜日）';
        }
        
        if (empty($errors)) {
            wp_send_json_success('設定は有効です');
        } else {
            wp_send_json_error($errors);
        }
    }
    
    /**
     * シェルスクリプトの自動生成
     */
    public function generate_cron_script() {
        if (!current_user_can('manage_options')) {
            wp_die('権限がありません');
        }
        
        // ノンス検証を追加
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'generate_cron_script')) {
            wp_send_json_error('セキュリティ検証に失敗しました');
        }
        
        $script_name = sanitize_text_field($_POST['script_name'] ?? 'news-crawler-cron.sh');
        
        // デバッグ情報を収集
        $debug_info = array(
            'script_name' => $script_name,
            'plugin_dir' => NEWS_CRAWLER_PLUGIN_DIR,
            'plugin_dir_writable' => is_writable(NEWS_CRAWLER_PLUGIN_DIR),
            'plugin_dir_exists' => is_dir(NEWS_CRAWLER_PLUGIN_DIR)
        );
        
        // プラグインディレクトリの書き込み権限をチェック
        $script_path = NEWS_CRAWLER_PLUGIN_DIR . $script_name;
        $use_alternative_path = false;
        
        if (!is_writable(NEWS_CRAWLER_PLUGIN_DIR)) {
            // 代替手段として、WordPressのアップロードディレクトリを試す
            $upload_dir = wp_upload_dir();
            $script_path = $upload_dir['basedir'] . '/' . $script_name;
            
            $debug_info['alternative_path'] = $script_path;
            $debug_info['upload_dir_writable'] = is_writable($upload_dir['basedir']);
            
            if (is_writable($upload_dir['basedir'])) {
                $use_alternative_path = true;
                $debug_info['using_alternative_path'] = true;
            } else {
                wp_send_json_error(array(
                    'message' => 'プラグインディレクトリとアップロードディレクトリの両方に書き込み権限がありません',
                    'debug' => $debug_info
                ));
            }
        }
        
        $debug_info['final_script_path'] = $script_path;
        $debug_info['final_path_writable'] = is_writable(dirname($script_path));
        
        // シェルスクリプトの内容を生成
        $script_content = $this->generate_script_content();
        
        // ファイルを作成
        $debug_info['script_content_length'] = strlen($script_content);
        $debug_info['script_content_preview'] = substr($script_content, 0, 200) . '...';
        
        // ファイル作成前に既存ファイルを削除（存在する場合）
        if (file_exists($script_path)) {
            $debug_info['existing_file_removed'] = unlink($script_path);
        }
        
        $result = file_put_contents($script_path, $script_content, LOCK_EX);
        
        if ($result !== false) {
            // 実行権限を設定
            $chmod_result = chmod($script_path, 0755);
            
            // ファイルが実際に作成されたかチェック
            $file_exists = file_exists($script_path);
            $file_readable = is_readable($script_path);
            $file_writable = is_writable($script_path);
            $file_size = $file_exists ? filesize($script_path) : 0;
            
            // ファイルの内容を確認
            $file_content = $file_exists ? file_get_contents($script_path) : '';
            $content_matches = ($file_content === $script_content);
            
            $debug_info['file_created'] = true;
            $debug_info['bytes_written'] = $result;
            $debug_info['chmod_result'] = $chmod_result;
            $debug_info['file_exists_after'] = $file_exists;
            $debug_info['file_readable'] = $file_readable;
            $debug_info['file_writable'] = $file_writable;
            $debug_info['actual_file_size'] = $file_size;
            $debug_info['content_matches'] = $content_matches;
            $debug_info['file_content_preview'] = substr($file_content, 0, 200) . '...';
            
            wp_send_json_success(array(
                'message' => 'シェルスクリプトが正常に生成されました',
                'path' => $script_path,
                'debug' => $debug_info
            ));
        } else {
            $debug_info['file_created'] = false;
            $debug_info['error'] = error_get_last();
            $debug_info['php_error_reporting'] = error_reporting();
            $debug_info['php_display_errors'] = ini_get('display_errors');
            $debug_info['php_log_errors'] = ini_get('log_errors');
            
            wp_send_json_error(array(
                'message' => 'シェルスクリプトの生成に失敗しました',
                'debug' => $debug_info
            ));
        }
    }
    
    /**
     * シェルスクリプトの内容を生成
     */
    private function generate_script_content() {
        $wp_path = ABSPATH;
        $plugin_path = NEWS_CRAWLER_PLUGIN_DIR;
        
        return "#!/bin/bash
# News Crawler Cron Script
# 修正版 - " . date('Y-m-d H:i:s') . " (デバッグ機能強化版)

set -euo pipefail

# スクリプトのディレクトリを取得
SCRIPT_DIR=\"\$(cd \"\$(dirname \"\${BASH_SOURCE[0]}\")\" && pwd)\"

# WordPressのパスを動的に取得（プラグインディレクトリから逆算）
WP_PATH=\"\$(dirname \"\$(dirname \"\$(dirname \"\$SCRIPT_DIR\")\")\")/\"

# WordPressのパスが正しいかチェック（wp-config.phpの存在確認）
if [ ! -f \"\$WP_PATH/wp-config.php\" ]; then
    # 代替パスを試行（新しいパスを優先）
    for alt_path in \"/virtual/kantan/public_html/\" \"/var/www/html/\" \"\$(dirname \"\$SCRIPT_DIR\")/../../\"; do
        if [ -f \"\$alt_path/wp-config.php\" ]; then
            WP_PATH=\"\$alt_path\"
            break
        fi
    done
fi

# プラグインパスを設定
PLUGIN_PATH=\"\$SCRIPT_DIR/\"

# ログファイルのパス
LOG_FILE=\"\$SCRIPT_DIR/news-crawler-cron.log\"

# ログに実行開始を記録
echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行開始\" >> \"\$LOG_FILE\"
echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] スクリプトディレクトリ: \$SCRIPT_DIR\" >> \"\$LOG_FILE\"
echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] WordPressパス: \$WP_PATH\" >> \"\$LOG_FILE\"
echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] プラグインパス: \$PLUGIN_PATH\" >> \"\$LOG_FILE\"

# Docker環境チェック（Mac開発環境用）
if command -v docker &> /dev/null && docker ps --format \"{{.Names}}\" | grep -q \"KantanPro_wordpress\"; then
    # Docker環境の場合
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] Docker環境でdocker exec経由でNews Crawlerを実行中...\" >> \"\$LOG_FILE\"
    
    CONTAINER_NAME=\"KantanPro_wordpress\"
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] 使用するコンテナ: \$CONTAINER_NAME\" >> \"\$LOG_FILE\"
    
    # 一時的なPHPファイルを作成してコンテナ内で実行
    TEMP_PHP_FILE=\"/tmp/news-crawler-cron-\$(date +%s).php\"
    cat > \"\$TEMP_PHP_FILE\" << 'DOCKER_EOF'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('default_socket_timeout', 10);
ini_set('mysqli.default_socket_timeout', 10);
ini_set('mysql.connect_timeout', 10);
set_time_limit(110);

echo \"[PHP] Docker環境での実行を開始\\n\";
echo \"[PHP] WordPressディレクトリ: \" . getcwd() . \"\\n\";

require_once('/var/www/html/wp-load.php');
echo \"[PHP] WordPress読み込み完了\\n\";

echo \"[PHP] NewsCrawlerGenreSettingsクラスをチェック中\\n\";
if (class_exists('NewsCrawlerGenreSettings')) {
    echo \"[PHP] クラスが見つかりました。インスタンスを取得中\\n\";
    \$genre_settings = NewsCrawlerGenreSettings::get_instance();
    echo \"[PHP] 自動投稿を実行中\\n\";
    \$genre_settings->execute_auto_posting();
    echo \"[PHP] News Crawler自動投稿を実行しました\\n\";
} else {
    echo \"[PHP] News CrawlerGenreSettingsクラスが見つかりません\\n\";
}
?>
DOCKER_EOF

    # ホストの一時ファイルをコンテナにコピーして実行
    docker cp \"\$TEMP_PHP_FILE\" \"\$CONTAINER_NAME:/tmp/news-crawler-exec.php\"
    
    if command -v timeout &> /dev/null; then
        timeout 120s docker exec \"\$CONTAINER_NAME\" php /tmp/news-crawler-exec.php >> \"\$LOG_FILE\" 2>&1
        PHP_STATUS=\$?
    else
        docker exec \"\$CONTAINER_NAME\" php /tmp/news-crawler-exec.php >> \"\$LOG_FILE\" 2>&1
        PHP_STATUS=\$?
    fi
    
    # 一時ファイルのクリーンアップ
    rm -f \"\$TEMP_PHP_FILE\"
    docker exec \"\$CONTAINER_NAME\" rm -f /tmp/news-crawler-exec.php 2>/dev/null
    
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] Docker exec exit status: \$PHP_STATUS\" >> \"\$LOG_FILE\"
    
    if [ \"\$PHP_STATUS\" -eq 0 ]; then
        echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] Docker環境でNews Crawlerを実行しました\" >> \"\$LOG_FILE\"
    else
        echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] Docker環境での実行でエラー (exit=\$PHP_STATUS)\" >> \"\$LOG_FILE\"
    fi
# wp-cliが存在する場合は優先して使用（サーバー環境）
elif command -v wp &> /dev/null; then
    cd \"\$WP_PATH\"
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行中...\" >> \"\$LOG_FILE\"
    wp --path=\"\$WP_PATH\" eval \"
        if (class_exists('NewsCrawlerGenreSettings')) {
            \\\$genre_settings = NewsCrawlerGenreSettings::get_instance();
            \\\$genre_settings->execute_auto_posting();
            echo 'News Crawler自動投稿を実行しました';
        } else {
            echo 'News CrawlerGenreSettingsクラスが見つかりません';
        }
    \" >> \"\$LOG_FILE\" 2>&1 || echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] wp-cli実行でエラー\" >> \"\$LOG_FILE\"
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] wp-cli経由でNews Crawlerを実行しました\" >> \"\$LOG_FILE\"
else
    # wp-cliが無い場合はPHP直接実行
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でNews Crawlerを実行中...\" >> \"\$LOG_FILE\"

    # PHPのフルパスを複数の候補から検索
    PHP_CMD=\"\"
    for php_path in \"/usr/bin/php\" \"/usr/local/bin/php\" \"/opt/homebrew/bin/php\" \"\$(command -v php || true)\"; do
        if [ -n \"\$php_path\" ] && [ -x \"\$php_path\" ]; then
            PHP_CMD=\"\$php_path\"
            break
        fi
    done

    if [ -z \"\$PHP_CMD\" ]; then
        echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] PHPコマンドが見つかりません。スクリプトを終了します。\" >> \"\$LOG_FILE\"
        exit 1
    fi

    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] 使用するPHPコマンド: \$PHP_CMD\" >> \"\$LOG_FILE\"

    # 一時的なPHPファイルを作成して実行（wp-load.phpを使用）
    TEMP_PHP_FILE=\"/tmp/news-crawler-cron-\$(date +%s).php\"
    cat > \"\$TEMP_PHP_FILE\" << EOF
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('default_socket_timeout', 10);
ini_set('mysqli.default_socket_timeout', 10);
ini_set('mysql.connect_timeout', 10);
set_time_limit(110);

echo \"[PHP] 実行開始 - ディレクトリ: \" . getcwd() . \"\\n\";

// WordPressパスの動的検出（新しいパスを優先）
\$wp_paths = array(
    '/virtual/kantan/public_html/wp-load.php',
    '/var/www/html/wp-load.php',
    dirname(__FILE__) . '/../../../wp-load.php'
);

\$wp_load_path = null;
foreach (\$wp_paths as \$path) {
    if (file_exists(\$path)) {
        \$wp_load_path = \$path;
        echo \"[PHP] wp-load.phpを発見: \" . \$path . \"\\n\";
        break;
    }
}

if (!\$wp_load_path) {
    echo \"[PHP] エラー: wp-load.phpが見つかりません\\n\";
    echo \"[PHP] 検索したパス:\\n\";
    foreach (\$wp_paths as \$path) {
        echo \"[PHP] - \" . \$path . \" (存在しない)\\n\";
    }
    exit(1);
}

echo \"[PHP] wp-load.php読み込み開始: \" . \$wp_load_path . \"\\n\";
require_once(\$wp_load_path);
echo \"[PHP] WordPress読み込み完了\\n\";

echo \"[PHP] WordPress関数確認中\\n\";
if (function_exists('get_option')) {
    echo \"[PHP] get_option関数: 利用可能\\n\";
    \$site_url = get_option('siteurl');
    echo \"[PHP] サイトURL: \" . \$site_url . \"\\n\";
} else {
    echo \"[PHP] エラー: get_option関数が利用できません\\n\";
}

echo \"[PHP] NewsCrawlerGenreSettingsクラスをチェック中\\n\";
if (class_exists('NewsCrawlerGenreSettings')) {
    echo \"[PHP] クラスが見つかりました。インスタンスを取得中\\n\";
    try {
        \$genre_settings = NewsCrawlerGenreSettings::get_instance();
        echo \"[PHP] インスタンス取得成功\\n\";
        echo \"[PHP] 自動投稿を実行中\\n\";
        \$result = \$genre_settings->execute_auto_posting();
        echo \"[PHP] 自動投稿実行結果: \" . var_export(\$result, true) . \"\\n\";
        echo \"[PHP] News Crawler自動投稿を実行しました\\n\";
    } catch (Exception \$e) {
        echo \"[PHP] エラー: \" . \$e->getMessage() . \"\\n\";
        echo \"[PHP] スタックトレース: \" . \$e->getTraceAsString() . \"\\n\";
    }
} else {
    echo \"[PHP] News CrawlerGenreSettingsクラスが見つかりません\\n\";
    echo \"[PHP] 利用可能なクラス一覧:\\n\";
    \$declared_classes = get_declared_classes();
    \$crawler_classes = array_filter(\$declared_classes, function(\$class) {
        return strpos(\$class, 'NewsCrawler') !== false || strpos(\$class, 'Genre') !== false;
    });
    if (!empty(\$crawler_classes)) {
        foreach (\$crawler_classes as \$class) {
            echo \"[PHP] - \" . \$class . \"\\n\";
        }
    } else {
        echo \"[PHP] News Crawler関連のクラスが見つかりません\\n\";
    }
}
echo \"[PHP] スクリプト実行完了\\n\";
?>
EOF

    cd \"\$WP_PATH\"
    if command -v timeout &> /dev/null; then
        timeout 120s \"\$PHP_CMD\" \"\$TEMP_PHP_FILE\" >> \"\$LOG_FILE\" 2>&1
        PHP_STATUS=\$?
    else
        \"\$PHP_CMD\" \"\$TEMP_PHP_FILE\" >> \"\$LOG_FILE\" 2>&1
        PHP_STATUS=\$?
    fi
    echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] PHP exit status: \$PHP_STATUS\" >> \"\$LOG_FILE\"
    rm -f \"\$TEMP_PHP_FILE\"
    if [ \"\$PHP_STATUS\" -eq 0 ]; then
        echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でNews Crawlerを実行しました\" >> \"\$LOG_FILE\"
    else
        echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] PHP直接実行でエラー (exit=\$PHP_STATUS)\" >> \"\$LOG_FILE\"
    fi
fi

# ログに実行終了を記録
echo \"[\$(date '+%Y-%m-%d %H:%M:%S')] News Crawler Cron 実行終了\" >> \"\$LOG_FILE\"
echo \"---\" >> \"\$LOG_FILE\"
";
    }
    
    /**
     * 管理画面用スクリプトの読み込み
     */
    public function enqueue_admin_scripts($hook) {
        // Cron設定ページでのみスクリプトを読み込み
        if (strpos($hook, 'news-crawler-cron-settings') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        
        // インラインスクリプトとして追加
        $script = "
        jQuery(document).ready(function($) {
            $('#generate_script_btn').on('click', function() {
                var button = $(this);
                var scriptName = $('#shell_script_name').val();
                
                if (!scriptName) {
                    alert('シェルスクリプト名を入力してください。');
                    return;
                }
                
                button.prop('disabled', true).text('生成中...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_cron_script',
                        script_name: scriptName,
                        nonce: '" . wp_create_nonce('generate_cron_script') . "'
                    },
                    success: function(response) {
                        if (response.success) {
                            // 成功時はアラートを表示せず、ページを再読み込みして「✓ スクリプトが存在します」を表示
                            setTimeout(function() {
                                location.reload();
                            }, 500);
                        } else {
                            var errorMsg = 'エラー: ' + response.data.message;
                            if (response.data.debug) {
                                errorMsg += '\\n\\nデバッグ情報:\\n';
                                for (var key in response.data.debug) {
                                    errorMsg += key + ': ' + response.data.debug[key] + '\\n';
                                }
                            }
                            alert(errorMsg);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('シェルスクリプトの生成に失敗しました。\\n\\nエラー: ' + error + '\\nステータス: ' + status);
                    },
                    complete: function() {
                        button.prop('disabled', false).text('シェルスクリプトを自動生成');
                    }
                });
            });
        });
        ";
        
        wp_add_inline_script('jquery', $script);
    }
    
    /**
     * サーバーcronからのHTTPリクエストを処理
     */
    public function handle_cron_execution() {
        // ノンス検証
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'news_crawler_cron_nonce')) {
            wp_die('セキュリティ検証に失敗しました');
        }
        
        // News Crawlerの自動投稿機能を実行
        if (class_exists('NewsCrawlerGenreSettings')) {
            $genre_settings = NewsCrawlerGenreSettings::get_instance();
            $genre_settings->execute_auto_posting();
            echo 'News Crawler自動投稿を実行しました';
        } else {
            echo 'News CrawlerGenreSettingsクラスが見つかりません';
        }
        
        wp_die(); // AJAX処理を終了
    }
    
    /**
     * Cronスクリプトの存在チェックと自動作成
     */
    public function check_and_create_cron_script() {
        // 管理画面でのみ実行（フロントエンドでは実行しない）
        if (!is_admin()) {
            return;
        }
        
        // プラグインの設定が存在しない場合はスキップ
        $options = get_option($this->option_name);
        if (!$options) {
            return;
        }
        
        $script_name = isset($options['shell_script_name']) ? $options['shell_script_name'] : 'news-crawler-cron.sh';
        
        // スクリプトが既に存在する場合はスキップ
        if ($this->check_script_exists($script_name)) {
            return;
        }
        
        // スクリプトを自動作成
        $this->auto_create_cron_script($script_name);
    }
    
    /**
     * Cronスクリプトを自動作成
     */
    private function auto_create_cron_script($script_name) {
        $script_path = NEWS_CRAWLER_PLUGIN_DIR . $script_name;
        
        // プラグインディレクトリが書き込み可能かチェック
        if (!is_writable(NEWS_CRAWLER_PLUGIN_DIR)) {
            // プラグインディレクトリが書き込み不可の場合は、アップロードディレクトリに作成
            $upload_dir = wp_upload_dir();
            if (is_writable($upload_dir['basedir'])) {
                $script_path = $upload_dir['basedir'] . '/' . $script_name;
            } else {
                // どちらも書き込み不可の場合はスキップ
                return false;
            }
        }
        
        // スクリプトの内容を生成
        $script_content = $this->generate_script_content();
        
        // ファイルを作成
        $result = file_put_contents($script_path, $script_content, LOCK_EX);
        
        if ($result !== false) {
            // 実行権限を設定
            chmod($script_path, 0755);
            
            // ログに記録（オプション）
            error_log("News Crawler: Cronスクリプトを自動作成しました: " . $script_path);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * 設定保存時にシェルスクリプトを自動生成
     */
    private function auto_generate_script_on_save($sanitized) {
        // シェルスクリプト名を取得
        $script_name = isset($sanitized['shell_script_name']) ? $sanitized['shell_script_name'] : 'news-crawler-cron.sh';
        
        // スクリプトの内容を生成
        $script_content = $this->generate_script_content();
        
        // プラグインディレクトリの書き込み権限をチェック
        $script_path = NEWS_CRAWLER_PLUGIN_DIR . $script_name;
        
        if (!is_writable(NEWS_CRAWLER_PLUGIN_DIR)) {
            // 代替手段として、WordPressのアップロードディレクトリを試す
            $upload_dir = wp_upload_dir();
            if (is_writable($upload_dir['basedir'])) {
                $script_path = $upload_dir['basedir'] . '/' . $script_name;
            } else {
                // どちらも書き込み不可の場合はスキップ
                error_log('News Crawler: シェルスクリプトの自動生成をスキップしました（書き込み権限なし）');
                return false;
            }
        }
        
        // ファイルを作成
        $result = file_put_contents($script_path, $script_content, LOCK_EX);
        
        if ($result !== false) {
            // 実行権限を設定
            chmod($script_path, 0755);
            
            // ログに記録
            error_log("News Crawler: 設定保存時にシェルスクリプトを自動生成しました: " . $script_path);
            
            return true;
        } else {
            error_log('News Crawler: シェルスクリプトの自動生成に失敗しました: ' . $script_path);
            return false;
        }
    }
    
    /**
     * 強制的にCronスクリプトを作成（プラグイン有効化時など）
     */
    public function force_create_cron_script() {
        $script_name = 'news-crawler-cron.sh';
        
        // 既存のスクリプトを削除してから新しく作成
        $plugin_path = NEWS_CRAWLER_PLUGIN_DIR . $script_name;
        if (file_exists($plugin_path)) {
            unlink($plugin_path);
        }
        
        // アップロードディレクトリの既存スクリプトも削除
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['basedir'] . '/' . $script_name;
        if (file_exists($upload_path)) {
            unlink($upload_path);
        }
        
        // 新しいスクリプトを作成
        return $this->auto_create_cron_script($script_name);
    }
    
    /**
     * プラグインのバージョンを動的に取得
     */
    private function get_plugin_version() {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $plugin_file = NEWS_CRAWLER_PLUGIN_DIR . 'news-crawler.php';
        $plugin_data = get_plugin_data($plugin_file, false, false);
        
        return isset($plugin_data['Version']) ? $plugin_data['Version'] : NEWS_CRAWLER_VERSION;
    }
}
