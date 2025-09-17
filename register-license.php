<?php
/**
 * 本番環境のKantanPro License Managerにライセンスキーを登録するスクリプト
 */

// WordPressの読み込み
require_once('/var/www/html/wp-config.php');

echo "=== ライセンスキー登録スクリプト ===\n";

// 登録するライセンスキー
$license_key = 'NCRL-145561-JUMG8|GG-366B';
$user_email = 'info@kantanpro.com';
$product_type = 'lifetime';

echo "登録するライセンスキー: " . $license_key . "\n";
echo "ユーザーEmail: " . $user_email . "\n";
echo "プロダクトタイプ: " . $product_type . "\n";

// KantanPro License Managerプラグインが有効かチェック
if (!class_exists('KTP_License_Manager')) {
    echo "❌ KantanPro License Managerプラグインが有効ではありません\n";
    exit;
}

echo "✅ KantanPro License Managerプラグインが有効です\n";

// ライセンスマネージャーのインスタンスを取得
$license_manager = KTP_License_Manager::get_instance();

// データベースに直接ライセンスを登録
global $wpdb;

$table_name = $wpdb->prefix . 'ktp_licenses';

// 既存のライセンスをチェック
$existing = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE license_key = %s",
    $license_key
));

if ($existing) {
    echo "⚠️  ライセンスキーは既に登録されています\n";
    echo "ステータス: " . $existing->status . "\n";
    echo "作成日: " . $existing->created_at . "\n";
} else {
    // 新しいライセンスを登録
    $result = $wpdb->insert(
        $table_name,
        array(
            'license_key' => $license_key,
            'user_email' => $user_email,
            'product_type' => $product_type,
            'status' => 'active',
            'created_at' => current_time('mysql'),
            'expires_at' => null
        ),
        array(
            '%s', '%s', '%s', '%s', '%s', '%s'
        )
    );
    
    if ($result) {
        echo "✅ ライセンスキーが正常に登録されました\n";
        echo "登録ID: " . $wpdb->insert_id . "\n";
    } else {
        echo "❌ ライセンスキーの登録に失敗しました\n";
        echo "エラー: " . $wpdb->last_error . "\n";
    }
}

// 登録されたライセンスの確認
echo "\n=== 登録確認 ===\n";
$registered = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE license_key = %s",
    $license_key
));

if ($registered) {
    echo "ライセンスキー: " . $registered->license_key . "\n";
    echo "ユーザーEmail: " . $registered->user_email . "\n";
    echo "プロダクトタイプ: " . $registered->product_type . "\n";
    echo "ステータス: " . $registered->status . "\n";
    echo "作成日: " . $registered->created_at . "\n";
    echo "有効期限: " . ($registered->expires_at ?: '無期限') . "\n";
} else {
    echo "❌ ライセンスが見つかりません\n";
}

echo "\n=== 完了 ===\n";
?>
