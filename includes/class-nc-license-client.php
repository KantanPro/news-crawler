<?php
// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ===== 設定 =====
if ( ! defined( 'NC_LICENSE_API_BASE' ) ) {
    // ライセンスサーバー（KantanPro License Manager）側のドメインに置き換え
    define( 'NC_LICENSE_API_BASE', 'https://www.kantanpro.com' );
}
if ( ! defined( 'NC_DEV_MODE' ) ) {
    // ローカルで true にすると厳格チェックを緩めます（本番は未定義 or false）
    define( 'NC_DEV_MODE', function_exists( 'wp_get_environment_type' ) ? ( wp_get_environment_type() !== 'production' ) : false );
}

// ===== ヘルパ =====
function nc_http_request( $method, $url, $body = null, $headers = array(), $options = array() ) {
    $args = array(
        'method'    => strtoupper( $method ),
        'timeout'   => isset( $options['timeout'] ) ? (int) $options['timeout'] : 15,
        'headers'   => is_array( $headers ) ? $headers : array(),
        'body'      => $body,
        'sslverify' => NC_DEV_MODE ? false : true,
    );

    // フォームエンコードを標準に
    if ( ! isset( $args['headers']['Content-Type'] ) ) {
        $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
    }

    $attempts   = 0;
    $max        = isset( $options['retries'] ) ? max( 0, (int) $options['retries'] ) : 2; // 初回+2回=最大3回
    $backoff_ms = 300;

    while ( $attempts <= $max ) {
        $attempts++;
        $res = wp_remote_request( $url, $args );
        if ( ! is_wp_error( $res ) ) {
            $code = (int) wp_remote_retrieve_response_code( $res );
            if ( $code >= 200 && $code < 300 ) {
                return $res;
            }
            if ( $code >= 500 && $attempts <= $max ) {
                usleep( $backoff_ms * 1000 );
                $backoff_ms *= 2;
                continue;
            }
            return $res;
        } else {
            if ( $attempts <= $max ) {
                usleep( $backoff_ms * 1000 );
                $backoff_ms *= 2;
                continue;
            }
            return $res;
        }
    }
    return new WP_Error( 'nc_http_error', 'Request failed.' );
}

// ===== ライセンスクライアント =====
class NC_License_Client {
    const OPTION_KEY    = 'nc_license_key';
    const OPTION_STATUS = 'nc_license_status';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
    }

    public static function register_settings() {
        register_setting( 'nc_license_group', self::OPTION_KEY, array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
    }

    public static function register_menu() {
        add_options_page(
            'News Crawler License',
            'News Crawler License',
            'manage_options',
            'nc-license',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // 手動検証
        if ( isset( $_POST['nc_verify_now'] ) && check_admin_referer( 'nc_license_verify' ) ) {
            $key = sanitize_text_field( $_POST[ self::OPTION_KEY ] ?? '' );
            if ( ! empty( $key ) ) {
                $result = self::verify_license( $key );
                update_option( self::OPTION_STATUS, $result );

                // NewsCrawler_License_Manager と同期
                update_option( 'news_crawler_license_key', $key );
                if ( ! empty( $result['success'] ) || ! empty( $result['valid'] ) ) {
                    update_option( 'news_crawler_license_status', 'active' );
                    if ( isset( $result['data'] ) ) {
                        update_option( 'news_crawler_license_info', $result['data'] );
                    }
                    update_option( 'news_crawler_license_verified_at', current_time( 'timestamp' ) );
                    // 開発環境では開発用ライセンスを自動ON（通知抑止）
                    update_option( 'news_crawler_dev_license_enabled', '1' );
                } else {
                    update_option( 'news_crawler_license_status', 'invalid' );
                }
            } else {
                update_option( 'news_crawler_license_key', '' );
                update_option( 'news_crawler_license_status', 'not_set' );
            }
            update_option( self::OPTION_KEY, $key );
            echo '<div class="updated"><p>ライセンスを検証しました。</p></div>';
        }

        $saved_key = get_option( self::OPTION_KEY, '' );
        $status    = get_option( self::OPTION_STATUS, array() );

        echo '<div class="wrap"><h1>News Crawler ライセンス</h1>';
        echo '<form method="post" action="">';
        settings_fields( 'nc_license_group' );

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="nc_license_key">ライセンスキー</label></th><td>';
        echo '<input name="' . esc_attr( self::OPTION_KEY ) . '" id="nc_license_key" type="text" class="regular-text" value="' . esc_attr( $saved_key ) . '" placeholder="例: NCR-123456-ABCDEFGH-1234" />';
        echo '</td></tr>';
        echo '</tbody></table>';

        wp_nonce_field( 'nc_license_verify' );
        submit_button( '今すぐ検証', 'primary', 'nc_verify_now' );

        // 表示
        if ( ! empty( $status ) ) {
            $ok = ! empty( $status['valid'] );
            echo '<h2>現在の状態: ' . ( $ok ? '有効' : '無効' ) . '</h2>';
            if ( ! empty( $status['message'] ) ) {
                echo '<p>' . esc_html( $status['message'] ) . '</p>';
            }
            if ( ! empty( $status['data'] ) && is_array( $status['data'] ) ) {
                echo '<pre style="background:#f7f7f7;border:1px solid #ddd;padding:12px;overflow:auto;">';
                echo esc_html( print_r( $status['data'], true ) );
                echo '</pre>';
            }
        }

        echo '</form></div>';
    }

    public static function verify_license( $license_key ) {
        $endpoint = rtrim( NC_LICENSE_API_BASE, '/' ) . '/wp-json/ktp-license/v1/verify';

        $plugin_version = defined( 'NEWS_CRAWLER_VERSION' ) ? NEWS_CRAWLER_VERSION : '';
        $body           = array(
            'license_key'    => $license_key,
            'site_url'       => home_url(),
            'plugin_version' => $plugin_version,
        );

        $res = nc_http_request( 'POST', $endpoint, $body, array(), array( 'retries' => 2 ) );
        if ( is_wp_error( $res ) ) {
            return array(
                'success'    => false,
                'valid'      => false,
                'message'    => '接続エラー: ' . $res->get_error_message(),
                'error_code' => $res->get_error_code(),
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $res );
        $json = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( $code >= 200 && $code < 300 && is_array( $json ) ) {
            return array(
                'success' => ! empty( $json['success'] ),
                'valid'   => ! empty( $json['valid'] ),
                'message' => $json['message'] ?? '',
                'data'    => $json['data'] ?? null,
            );
        }

        return array(
            'success' => false,
            'valid'   => false,
            'message' => '検証失敗 (HTTP ' . $code . ')',
            'raw'     => substr( (string) wp_remote_retrieve_body( $res ), 0, 500 ),
        );
    }
}

NC_License_Client::init();


