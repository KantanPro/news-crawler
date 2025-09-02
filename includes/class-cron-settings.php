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
        echo '<input type="text" id="shell_script_name" name="' . $this->option_name . '[shell_script_name]" value="' . esc_attr($value) . '" class="regular-text" required />';
        echo '<p class="description"><strong>必須項目：</strong>cronジョブで実行するシェルスクリプトのファイル名を入力してください。このスクリプトがサーバー上に存在し、実行権限が設定されている必要があります。</p>';
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
            <h1>News Crawler <?php echo esc_html(NEWS_CRAWLER_VERSION); ?> - Cron設定</h1>
            
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
        
        // シェルスクリプトのパスを生成（WordPressのルートディレクトリを基準）
        $script_path = ABSPATH . $script_name;
        
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
}
