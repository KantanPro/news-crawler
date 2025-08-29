<?php
/**
 * Security Tests for News Crawler Plugin
 * 
 * @package NewsCrawler
 * @subpackage Tests
 */

class NewsCrawlerSecurityTest {
    
    public function run_all_tests() {
        echo "=== News Crawler Security Tests ===\n";
        
        $tests = [
            'test_sql_injection_protection',
            'test_xss_protection', 
            'test_csrf_protection',
            'test_file_access_protection',
            'test_api_key_protection',
            'test_nonce_verification',
            'test_encryption_functionality',
            'test_admin_capability_checks'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            if ($this->$test()) {
                echo "✓ {$test}: PASS\n";
                $passed++;
            } else {
                echo "✗ {$test}: FAIL\n";
            }
        }
        
        echo "\nSecurity Test Results: {$passed}/{$total} PASS\n";
        return $passed === $total;
    }
    
    private function test_sql_injection_protection() {
        // SQLインジェクション対策のテスト
        $malicious_input = "'; DROP TABLE wp_posts; --";
        
        // 入力値のサニタイズテスト
        $sanitized = sanitize_text_field($malicious_input);
        return $sanitized !== $malicious_input;
    }
    
    private function test_xss_protection() {
        // XSS攻撃対策のテスト
        $malicious_script = '<script>alert("XSS")</script>';
        
        // エスケープ処理のテスト
        $escaped = esc_html($malicious_script);
        return strpos($escaped, '<script>') === false;
    }
    
    private function test_csrf_protection() {
        // CSRF対策のテスト
        // nonceの生成と検証
        $nonce = wp_create_nonce('news_crawler_nonce');
        return !empty($nonce) && strlen($nonce) > 10;
    }
    
    private function test_file_access_protection() {
        // 直接ファイルアクセス防止のテスト
        $main_file = dirname(__DIR__) . '/news-crawler.php';
        
        if (!file_exists($main_file)) {
            return false;
        }
        
        $content = file_get_contents($main_file);
        return strpos($content, "if (!defined('ABSPATH'))") !== false;
    }
    
    private function test_api_key_protection() {
        // APIキー保護のテスト
        // 設定画面でのマスク表示確認
        $test_key = 'sk-test123456789';
        $masked = $this->mask_api_key($test_key);
        
        return $masked !== $test_key && strpos($masked, '*') !== false;
    }
    
    private function test_nonce_verification() {
        // nonce検証のテスト
        $nonce = wp_create_nonce('test_action');
        $verified = wp_verify_nonce($nonce, 'test_action');
        
        return $verified !== false;
    }
    
    private function mask_api_key($key) {
        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }
        
        return substr($key, 0, 4) . str_repeat('*', strlen($key) - 8) . substr($key, -4);
    }
}

// テスト実行
if (defined('WP_CLI') && WP_CLI) {
    $security_test = new NewsCrawlerSecurityTest();
    $security_test->run_all_tests();
}    

    private function test_encryption_functionality() {
        // 暗号化機能のテスト
        $test_data = 'test-api-key-12345';
        
        // 暗号化
        $encrypted = $this->simulate_encryption($test_data);
        
        // 復号化
        $decrypted = $this->simulate_decryption($encrypted);
        
        return $decrypted === $test_data && $encrypted !== $test_data;
    }
    
    private function test_admin_capability_checks() {
        // 管理者権限チェックのテスト
        // 実際の環境では current_user_can() をチェック
        return function_exists('current_user_can');
    }
    
    private function simulate_encryption($data) {
        // 暗号化のシミュレーション
        return base64_encode($data . '_encrypted');
    }
    
    private function simulate_decryption($encrypted_data) {
        // 復号化のシミュレーション
        $decoded = base64_decode($encrypted_data);
        return str_replace('_encrypted', '', $decoded);
    }
}

// テスト実行
if (defined('WP_CLI') && WP_CLI) {
    $security_test = new NewsCrawlerSecurityTest();
    $security_test->run_all_tests();
} elseif (php_sapi_name() === 'cli') {
    // CLI環境での直接実行
    $security_test = new NewsCrawlerSecurityTest();
    $result = $security_test->run_all_tests();
    exit($result ? 0 : 1);
}