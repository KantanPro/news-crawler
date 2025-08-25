<?php
/**
 * Featured Image Generator Class
 * 
 * 投稿の内容からアイキャッチを自動生成する機能
 */

// 直接アクセスを防ぐ
if (!defined('ABSPATH')) {
    exit;
}

class NewsCrawlerFeaturedImageGenerator {
    private $option_name = 'news_crawler_featured_image_settings';
    
    public function __construct() {
        add_action('admin_init', array($this, 'admin_init'));
    }
    
    public function admin_init() {
        register_setting($this->option_name, $this->option_name, array($this, 'sanitize_settings'));
    }
    
    /**
     * アイキャッチ画像を生成して投稿に設定
     * 
     * @param int $post_id 投稿ID
     * @param string $title 投稿タイトル
     * @param array $keywords キーワード配列
     * @param string $method 生成方法 ('ai', 'template', 'unsplash')
     * @return bool|int 成功時はattachment_id、失敗時はfalse
     */
    public function generate_and_set_featured_image($post_id, $title, $keywords = array(), $method = 'template') {
        error_log('Featured Image Generator: Starting generation for post ' . $post_id . ' with method: ' . $method);
        error_log('Featured Image Generator: Title: ' . $title);
        error_log('Featured Image Generator: Keywords: ' . implode(', ', $keywords));
        
        $settings = get_option($this->option_name, array());
        
        $result = false;
        switch ($method) {
            case 'ai':
                $result = $this->generate_ai_image($post_id, $title, $keywords, $settings);
                break;
            case 'unsplash':
                $result = $this->fetch_unsplash_image($post_id, $title, $keywords, $settings);
                break;
            case 'template':
            default:
                $result = $this->generate_template_image($post_id, $title, $keywords, $settings);
                break;
        }
        
        error_log('Featured Image Generator: Result: ' . ($result ? 'Success (ID: ' . $result . ')' : 'Failed'));
        return $result;
    }
    
    /**
     * テンプレートベースの画像生成
     */
    private function generate_template_image($post_id, $title, $keywords, $settings) {
        error_log('Featured Image Generator - Template: Starting template generation');
        
        // GD拡張の確認
        if (!extension_loaded('gd')) {
            error_log('Featured Image Generator - Template: GD extension not loaded');
            return false;
        }
        
        // 基本設定から値を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        
        // 画像サイズ設定（基本設定を優先）
        $width = isset($basic_settings['template_width']) ? intval($basic_settings['template_width']) : 
                 (isset($settings['template_width']) ? intval($settings['template_width']) : 1200);
        $height = isset($basic_settings['template_height']) ? intval($basic_settings['template_height']) : 
                  (isset($settings['template_height']) ? intval($settings['template_height']) : 630);
        
        error_log('Featured Image Generator - Template: Image size: ' . $width . 'x' . $height);
        
        // 画像を作成
        $image = imagecreatetruecolor($width, $height);
        
        // 背景色設定（グラデーション）- 基本設定を優先
        $bg_color1 = isset($basic_settings['bg_color1']) ? $basic_settings['bg_color1'] : 
                    (isset($settings['bg_color1']) ? $settings['bg_color1'] : '#4F46E5');
        $bg_color2 = isset($basic_settings['bg_color2']) ? $basic_settings['bg_color2'] : 
                    (isset($settings['bg_color2']) ? $settings['bg_color2'] : '#7C3AED');
        
        $this->create_gradient_background($image, $width, $height, $bg_color1, $bg_color2);
        
        // テキスト設定 - 基本設定を優先
        $text_color = isset($basic_settings['text_color']) ? $basic_settings['text_color'] : 
                     (isset($settings['text_color']) ? $settings['text_color'] : '#FFFFFF');
        $font_size = isset($basic_settings['font_size']) ? intval($basic_settings['font_size']) : 
                    (isset($settings['font_size']) ? intval($settings['font_size']) : 48);
        
        // 日本語タイトルを生成（キーワード + ニュースまとめ + 日付）
        $display_title = $this->create_japanese_title($title, $keywords);
        
        // 日本語テキストを画像に描画
        $this->draw_japanese_text_on_image($image, $display_title, $font_size, $text_color, $width, $height);
        
        // キーワードタグを追加
        if (!empty($keywords)) {
            $this->draw_keywords_on_image($image, $keywords, $width, $height, $text_color);
        }
        
        // 画像を保存
        error_log('Featured Image Generator - Template: Saving image as attachment');
        $result = $this->save_image_as_attachment($image, $post_id, $title);
        error_log('Featured Image Generator - Template: Save result: ' . ($result ? 'Success (ID: ' . $result . ')' : 'Failed'));
        return $result;
    }
    
    /**
     * AI画像生成（OpenAI DALL-E使用）
     */
    private function generate_ai_image($post_id, $title, $keywords, $settings) {
        // 基本設定からAPIキーを取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $api_key = isset($basic_settings['openai_api_key']) ? $basic_settings['openai_api_key'] : '';
        
        // デバッグログ
        error_log('Featured Image Generator - AI: API Key exists: ' . (!empty($api_key) ? 'Yes' : 'No'));
        
        if (empty($api_key)) {
            error_log('Featured Image Generator - AI: No API key found');
            return false;
        }
        
        // プロンプト生成
        $prompt = $this->create_ai_prompt($title, $keywords, $settings);
        
        // デバッグログ
        error_log('Featured Image Generator - AI: Prompt: ' . $prompt);
        
        // OpenAI API呼び出し
        $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1024x1024',
                'quality' => 'standard',
                'response_format' => 'url'
            )),
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            error_log('Featured Image Generator - AI: WP Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // デバッグログ
        error_log('Featured Image Generator - AI: Response: ' . $body);
        
        if (isset($data['data'][0]['url'])) {
            error_log('Featured Image Generator - AI: Image URL found, downloading...');
            return $this->download_and_attach_image($data['data'][0]['url'], $post_id, $title);
        }
        
        if (isset($data['error'])) {
            error_log('Featured Image Generator - AI: API Error: ' . $data['error']['message']);
        }
        
        return false;
    }
    
    /**
     * Unsplash画像取得
     */
    private function fetch_unsplash_image($post_id, $title, $keywords, $settings) {
        $access_key = isset($settings['unsplash_access_key']) ? $settings['unsplash_access_key'] : '';
        
        if (empty($access_key)) {
            return false;
        }
        
        // 検索キーワード生成
        $search_query = $this->create_unsplash_query($title, $keywords);
        
        // Unsplash API呼び出し
        $response = wp_remote_get('https://api.unsplash.com/search/photos?' . http_build_query(array(
            'query' => $search_query,
            'per_page' => 1,
            'orientation' => 'landscape',
            'content_filter' => 'high'
        )), array(
            'headers' => array(
                'Authorization' => 'Client-ID ' . $access_key,
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['results'][0]['urls']['regular'])) {
            return $this->download_and_attach_image($data['results'][0]['urls']['regular'], $post_id, $title);
        }
        
        return false;
    }    
    
/**
     * グラデーション背景を作成
     */
    private function create_gradient_background($image, $width, $height, $color1, $color2) {
        // 16進数カラーをRGBに変換
        $rgb1 = $this->hex_to_rgb($color1);
        $rgb2 = $this->hex_to_rgb($color2);
        
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $r = intval($rgb1['r'] * (1 - $ratio) + $rgb2['r'] * $ratio);
            $g = intval($rgb1['g'] * (1 - $ratio) + $rgb2['g'] * $ratio);
            $b = intval($rgb1['b'] * (1 - $ratio) + $rgb2['b'] * $ratio);
            
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }
    }
    
    /**
     * 16進数カラーをRGBに変換
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        );
    }
    
    /**
     * 日本語テキストを画像に描画
     */
    private function draw_japanese_text_on_image($image, $text, $font_size, $text_color, $width, $height) {
        $rgb = $this->hex_to_rgb($text_color);
        $color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        
        // 日本語フォントファイルのパス
        $font_path = $this->get_japanese_font_path();
        
        error_log('Featured Image Generator: Font path returned: ' . ($font_path ?: 'false'));
        error_log('Featured Image Generator: imagettftext function exists: ' . (function_exists('imagettftext') ? 'yes' : 'no'));
        
        if ($font_path) {
            error_log('Featured Image Generator: Testing font: ' . $font_path);
            $font_test_result = $this->test_japanese_font($font_path);
            error_log('Featured Image Generator: Font test result: ' . ($font_test_result ? 'success' : 'failed'));
            
            if ($font_test_result && function_exists('imagettftext')) {
                // 日本語TrueTypeフォントを使用して日本語を直接描画
                error_log('Featured Image Generator: Using TTF font for Japanese text');
                $this->draw_japanese_text_with_ttf($image, $text, $font_size, $color, $width, $height, $font_path);
            } else {
                // 日本語フォントが利用できない場合はエラーメッセージを描画
                error_log('Featured Image Generator: Font test failed or imagettftext not available');
                $error_message = 'Japanese Font Required';
                $this->draw_text_with_builtin($image, $error_message, $color, $width, $height);
                error_log('Featured Image Generator: Japanese font not available. Please install Noto Sans JP font.');
            }
        } else {
            error_log('Featured Image Generator: No font path found');
            $error_message = 'Japanese Font Required';
            $this->draw_text_with_builtin($image, $error_message, $color, $width, $height);
            error_log('Featured Image Generator: Japanese font not available. Please install Noto Sans JP font.');
        }
    }
    
    /**
     * 日本語フォントのテスト
     */
    private function test_japanese_font($font_path) {
        error_log('Featured Image Generator: Testing font: ' . $font_path);
        
        if (!file_exists($font_path)) {
            error_log('Featured Image Generator: Font file does not exist: ' . $font_path);
            return false;
        }
        
        if (!is_readable($font_path)) {
            error_log('Featured Image Generator: Font file is not readable: ' . $font_path);
            return false;
        }
        
        try {
            // 簡単な日本語文字でテスト
            error_log('Featured Image Generator: Testing with character: あ');
            $test_bbox = imagettfbbox(20, 0, $font_path, 'あ');
            
            if ($test_bbox === false) {
                error_log('Featured Image Generator: imagettfbbox returned false');
                return false;
            }
            
            if (!is_array($test_bbox)) {
                error_log('Featured Image Generator: imagettfbbox returned non-array: ' . gettype($test_bbox));
                return false;
            }
            
            error_log('Featured Image Generator: Font test successful, bbox: ' . implode(', ', $test_bbox));
            return true;
            
        } catch (Exception $e) {
            error_log('Featured Image Generator: Exception during font test: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 画像にテキストを描画（旧版・互換性のため残す）
     */
    private function draw_text_on_image($image, $text, $font_size, $text_color, $width, $height) {
        $rgb = $this->hex_to_rgb($text_color);
        $color = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        
        // フォントファイルのパス（システムフォントまたはWebフォント）
        $font_path = $this->get_font_path();
        
        if ($font_path && function_exists('imagettftext')) {
            // TrueTypeフォントを使用
            $this->draw_text_with_ttf($image, $text, $font_size, $color, $width, $height, $font_path);
        } else {
            // 内蔵フォントを使用
            $this->draw_text_with_builtin($image, $text, $color, $width, $height);
        }
    }
    
    /**
     * 日本語TTFフォントでテキスト描画
     */
    private function draw_japanese_text_with_ttf($image, $text, $font_size, $color, $width, $height, $font_path) {
        // 基本設定から拡大倍率を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $scale_factor = isset($basic_settings['text_scale']) ? intval($basic_settings['text_scale']) : 3;
        
        // フォントサイズを調整（日本語用に大きめに）
        $adjusted_font_size = max($font_size, 36) * ($scale_factor / 3);
        
        error_log("Featured Image Generator: Drawing Japanese text: {$text}");
        error_log("Featured Image Generator: Font size: {$adjusted_font_size}, Font path: {$font_path}");
        
        // 長いテキストを複数行に分割（日本語用）
        $max_chars_per_line = intval(12 / ($scale_factor / 3)); // 日本語は文字数を少なめに
        $lines = $this->split_japanese_text_into_lines($text, $max_chars_per_line);
        
        error_log("Featured Image Generator: Split into " . count($lines) . " lines: " . implode(' | ', $lines));
        
        // 各行の高さを計算
        $line_heights = array();
        $total_height = 0;
        $line_spacing = 30; // 行間
        
        foreach ($lines as $line) {
            try {
                $bbox = imagettfbbox($adjusted_font_size, 0, $font_path, $line);
                if ($bbox === false) {
                    error_log("Featured Image Generator: Failed to get bbox for line: {$line}");
                    $line_height = $adjusted_font_size * 1.2; // フォールバック
                } else {
                    $line_height = abs($bbox[1] - $bbox[7]);
                }
            } catch (Exception $e) {
                error_log("Featured Image Generator: Exception getting bbox: " . $e->getMessage());
                $line_height = $adjusted_font_size * 1.2; // フォールバック
            }
            
            $line_heights[] = $line_height;
            $total_height += $line_height + $line_spacing;
        }
        $total_height -= $line_spacing; // 最後の行間を削除
        
        // 開始Y位置を計算（中央揃え）
        $start_y = ($height - $total_height) / 2;
        
        // 影色
        $shadow_color = imagecolorallocatealpha($image, 0, 0, 0, 70);
        
        foreach ($lines as $index => $line) {
            try {
                // テキストの境界ボックスを取得
                $bbox = imagettfbbox($adjusted_font_size, 0, $font_path, $line);
                if ($bbox === false) {
                    error_log("Featured Image Generator: Failed to get bbox for rendering line: {$line}");
                    continue;
                }
                
                $text_width = $bbox[4] - $bbox[0];
                $line_height = $line_heights[$index];
                
                // 中央配置の計算
                $x = ($width - $text_width) / 2;
                $y = $start_y + ($index * ($line_height + $line_spacing)) + $line_height;
                
                error_log("Featured Image Generator: Drawing line {$index}: '{$line}' at ({$x}, {$y})");
                
                // 影効果（複数の影で強調）
                for ($sx = 1; $sx <= 3; $sx++) {
                    for ($sy = 1; $sy <= 3; $sy++) {
                        imagettftext($image, $adjusted_font_size, 0, $x + $sx, $y + $sy, $shadow_color, $font_path, $line);
                    }
                }
                
                // メインテキスト（太く見せるために複数回描画）
                for ($dx = 0; $dx <= 1; $dx++) {
                    for ($dy = 0; $dy <= 1; $dy++) {
                        imagettftext($image, $adjusted_font_size, 0, $x + $dx, $y + $dy, $color, $font_path, $line);
                    }
                }
                
            } catch (Exception $e) {
                error_log("Featured Image Generator: Exception drawing line: " . $e->getMessage());
            }
        }
        
        error_log("Featured Image Generator: Japanese text drawing completed");
    }
    
    /**
     * TTFフォントでテキスト描画（旧版・互換性のため残す）
     */
    private function draw_text_with_ttf($image, $text, $font_size, $color, $width, $height, $font_path) {
        // テキストの境界ボックスを取得
        $bbox = imagettfbbox($font_size, 0, $font_path, $text);
        $text_width = $bbox[4] - $bbox[0];
        $text_height = $bbox[1] - $bbox[7];
        
        // 中央配置の計算
        $x = ($width - $text_width) / 2;
        $y = ($height - $text_height) / 2 + $text_height;
        
        // 影効果
        $shadow_color = imagecolorallocatealpha($image, 0, 0, 0, 50);
        imagettftext($image, $font_size, 0, $x + 2, $y + 2, $shadow_color, $font_path, $text);
        
        // メインテキスト
        imagettftext($image, $font_size, 0, $x, $y, $color, $font_path, $text);
    }
    
    /**
     * 内蔵フォントでテキスト描画（文字化け防止版）
     */
    private function draw_text_with_builtin($image, $text, $color, $width, $height) {
        $font_size = 5; // 内蔵フォントサイズ（1-5）
        
        // 設定から拡大倍率を取得（基本設定を優先）
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $featured_settings = get_option($this->option_name, array());
        $scale_factor = isset($basic_settings['text_scale']) ? intval($basic_settings['text_scale']) : 
                       (isset($featured_settings['text_scale']) ? intval($featured_settings['text_scale']) : 4);
        
        // テキストが日本語を含む場合はローマ字に変換
        if (preg_match('/[^\x00-\x7F]/', $text)) {
            $display_text = $this->convert_japanese_to_clean_romaji($text);
        } else {
            $display_text = $text;
        }
        
        // 長いテキストを複数行に分割（拡大を考慮して文字数を調整）
        $max_chars_per_line = intval(16 / ($scale_factor / 3)); // 拡大倍率に応じて調整
        $lines = $this->split_text_into_lines($display_text, $max_chars_per_line);
        
        $base_char_width = imagefontwidth($font_size);
        $base_char_height = imagefontheight($font_size);
        $scaled_char_width = $base_char_width * $scale_factor;
        $scaled_char_height = $base_char_height * $scale_factor;
        
        $line_height = $scaled_char_height + 30; // 行間を追加
        $total_height = count($lines) * $line_height;
        
        // 開始Y位置を計算（中央揃え）
        $start_y = ($height - $total_height) / 2;
        
        foreach ($lines as $index => $line) {
            // ASCII文字のみであることを確認
            $safe_line = preg_replace('/[^\x00-\x7F]/', '?', $line);
            
            $text_width = $scaled_char_width * strlen($safe_line);
            $x = ($width - $text_width) / 2;
            $y = $start_y + ($index * $line_height);
            
            // 拡大描画のために文字を1文字ずつ処理
            $this->draw_scaled_text($image, $safe_line, $font_size, $color, $x, $y, $scale_factor);
        }
    }
    
    /**
     * 文字を拡大して描画
     */
    private function draw_scaled_text($image, $text, $font_size, $color, $start_x, $start_y, $scale_factor) {
        $base_char_width = imagefontwidth($font_size);
        $base_char_height = imagefontheight($font_size);
        
        // 影色
        $shadow_color = imagecolorallocate($image, 0, 0, 0);
        
        for ($i = 0; $i < strlen($text); $i++) {
            $char = $text[$i];
            $char_x = $start_x + ($i * $base_char_width * $scale_factor);
            
            // 一時的な小さい画像を作成して文字を描画
            $temp_img = imagecreatetruecolor($base_char_width, $base_char_height);
            $temp_bg = imagecolorallocate($temp_img, 255, 255, 255);
            $temp_text_color = imagecolorallocate($temp_img, 0, 0, 0);
            imagefill($temp_img, 0, 0, $temp_bg);
            imagestring($temp_img, $font_size, 0, 0, $char, $temp_text_color);
            
            // 拡大してメイン画像にコピー（影）
            imagecopyresized(
                $image, $temp_img,
                $char_x + ($scale_factor * 2), $start_y + ($scale_factor * 2), // 影の位置
                0, 0,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                $base_char_width, $base_char_height
            );
            
            // 影を黒に変換
            $this->replace_color($image, 
                $char_x + ($scale_factor * 2), $start_y + ($scale_factor * 2),
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                imagecolorallocate($image, 255, 255, 255), $shadow_color
            );
            
            // 拡大してメイン画像にコピー（メインテキスト）
            imagecopyresized(
                $image, $temp_img,
                $char_x, $start_y,
                0, 0,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                $base_char_width, $base_char_height
            );
            
            // メインテキストの色を変更
            $this->replace_color($image, 
                $char_x, $start_y,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                imagecolorallocate($image, 255, 255, 255), $color
            );
            
            imagedestroy($temp_img);
        }
    }
    
    /**
     * 指定領域の色を置換
     */
    private function replace_color($image, $x, $y, $width, $height, $old_color, $new_color) {
        for ($px = $x; $px < $x + $width; $px++) {
            for ($py = $y; $py < $y + $height; $py++) {
                if ($px >= 0 && $py >= 0 && $px < imagesx($image) && $py < imagesy($image)) {
                    $current_color = imagecolorat($image, $px, $py);
                    if ($current_color == $old_color) {
                        imagesetpixel($image, $px, $py, $new_color);
                    }
                }
            }
        }
    }
    
    /**
     * 日本語をローマ字に変換
     */
    private function convert_japanese_to_romaji($japanese_text) {
        // ひらがな・カタカナ・漢字をローマ字に変換するマッピング
        $conversion_map = array(
            // 基本的な単語
            'ニュース' => 'NEWS',
            'まとめ' => 'MATOME',
            '政治' => 'SEIJI',
            '経済' => 'KEIZAI',
            '社会' => 'SHAKAI',
            '国際' => 'KOKUSAI',
            '地域' => 'CHIIKI',
            'スポーツ' => 'SPORTS',
            '芸能' => 'GEINOU',
            'テック' => 'TECH',
            'ビジネス' => 'BUSINESS',
            '最新' => 'SAISHIN',
            
            // 政党名
            '自民党' => 'JIMINTO',
            '公明党' => 'KOMEITO',
            '参政党' => 'SANSEITO',
            '国民民主党' => 'KOKUMIN',
            
            // 月日
            '1月' => '1-gatsu', '2月' => '2-gatsu', '3月' => '3-gatsu',
            '4月' => '4-gatsu', '5月' => '5-gatsu', '6月' => '6-gatsu',
            '7月' => '7-gatsu', '8月' => '8-gatsu', '9月' => '9-gatsu',
            '10月' => '10-gatsu', '11月' => '11-gatsu', '12月' => '12-gatsu',
            
            // 日付
            '日' => '-nichi',
            
            // 記号
            '・' => ' ',
            '：' => ':',
            '、' => ', ',
            '。' => '.',
            
            // 数字（全角→半角）
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9'
        );
        
        $romaji_text = $japanese_text;
        
        // 変換マップを適用
        foreach ($conversion_map as $japanese => $romaji) {
            $romaji_text = str_replace($japanese, $romaji, $romaji_text);
        }
        
        // 残った日本語文字を削除し、英数字と記号のみ残す
        $romaji_text = preg_replace('/[^\x00-\x7F]/', ' ', $romaji_text);
        
        // 複数のスペースを1つにまとめる
        $romaji_text = preg_replace('/\s+/', ' ', $romaji_text);
        
        // 前後のスペースを削除
        $romaji_text = trim($romaji_text);
        
        // 空の場合はデフォルトテキスト
        if (empty($romaji_text)) {
            $romaji_text = 'NEWS SUMMARY';
        }
        
        return strtoupper($romaji_text);
    }
    
    /**
     * 日本語を確実に表示できるローマ字に変換（文字化け防止）
     */
    private function convert_japanese_to_clean_romaji($japanese_text) {
        // より包括的な変換マッピング
        $conversion_map = array(
            // 基本的な単語
            'ニュース' => 'NEWS',
            'まとめ' => 'MATOME',
            '政治' => 'SEIJI',
            '経済' => 'KEIZAI',
            '社会' => 'SHAKAI',
            '国際' => 'KOKUSAI',
            '地域' => 'CHIIKI',
            'スポーツ' => 'SPORTS',
            '芸能' => 'GEINOU',
            'エンタメ' => 'ENTAME',
            'テック' => 'TECH',
            'ビジネス' => 'BUSINESS',
            '最新' => 'SAISHIN',
            '健康' => 'KENKOU',
            '教育' => 'KYOUIKU',
            '環境' => 'KANKYOU',
            
            // 政党名・組織名
            '自民党' => 'JIMINTO',
            '公明党' => 'KOMEITO',
            '参政党' => 'SANSEITO',
            '国民民主党' => 'KOKUMIN',
            
            // よく使われる単語
            '新政策' => 'SHINSEISAKU',
            '市場' => 'SHIJOU',
            '予測' => 'YOSOKU',
            '技術' => 'GIJUTSU',
            'プロ野球' => 'PROYAKYU',
            '開幕' => 'KAIMAKU',
            '活性化' => 'KASSEIKA',
            '外交' => 'GAIKOU',
            '政策' => 'SEISAKU',
            '映画' => 'EIGA',
            
            // 月日
            '1月' => '1-GATSU', '2月' => '2-GATSU', '3月' => '3-GATSU',
            '4月' => '4-GATSU', '5月' => '5-GATSU', '6月' => '6-GATSU',
            '7月' => '7-GATSU', '8月' => '8-GATSU', '9月' => '9-GATSU',
            '10月' => '10-GATSU', '11月' => '11-GATSU', '12月' => '12-GATSU',
            
            // 日付
            '日' => '-NICHI',
            
            // 記号
            '・' => ' ',
            '：' => ':',
            '、' => ', ',
            '。' => '.',
            
            // 数字（全角→半角）
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9'
        );
        
        $romaji_text = $japanese_text;
        
        // 変換マップを適用
        foreach ($conversion_map as $japanese => $romaji) {
            $romaji_text = str_replace($japanese, $romaji, $romaji_text);
        }
        
        // 残った日本語文字を削除し、英数字と記号のみ残す
        $romaji_text = preg_replace('/[^\x00-\x7F]/', ' ', $romaji_text);
        
        // 複数のスペースを1つにまとめる
        $romaji_text = preg_replace('/\s+/', ' ', $romaji_text);
        
        // 前後のスペースを削除
        $romaji_text = trim($romaji_text);
        
        // 空の場合はデフォルトテキスト
        if (empty($romaji_text)) {
            $romaji_text = 'NEWS SUMMARY';
        }
        
        return $romaji_text;
    }
    
    /**
     * 日本語タイトルを英語に変換（旧版・互換性のため残す）
     */
    private function convert_to_english($japanese_text) {
        // 基本的な変換マップ
        $conversion_map = array(
            '政治' => 'Politics',
            '経済' => 'Economy', 
            'ニュース' => 'News',
            'まとめ' => 'Summary',
            '自民党' => 'LDP',
            '公明党' => 'Komeito',
            '参政党' => 'Sanseito',
            '国民民主党' => 'DPFP',
            'チームみらい' => 'Team Mirai',
            '年' => '',
            '月' => '/',
            '日' => '',
            '：' => ':',
            '、' => ', ',
            '。' => '.'
        );
        
        $english_text = $japanese_text;
        
        // 変換マップを適用
        foreach ($conversion_map as $japanese => $english) {
            $english_text = str_replace($japanese, $english, $english_text);
        }
        
        // 日付パターンを変換 (例: 2025年8月25日 -> 2025/8/25)
        $english_text = preg_replace('/(\d{4})年(\d{1,2})月(\d{1,2})日/', '$1/$2/$3', $english_text);
        
        // 残った日本語文字を削除し、英数字と記号のみ残す
        $english_text = preg_replace('/[^\x00-\x7F]/', ' ', $english_text);
        
        // 複数のスペースを1つにまとめる
        $english_text = preg_replace('/\s+/', ' ', $english_text);
        
        // 前後のスペースを削除
        $english_text = trim($english_text);
        
        // 空の場合はデフォルトテキスト
        if (empty($english_text)) {
            $english_text = 'News Summary';
        }
        
        return $english_text;
    }
    
    /**
     * テキストを複数行に分割（汎用版）
     */
    private function split_text_into_lines($text, $max_chars_per_line) {
        $words = explode(' ', $text);
        $lines = array();
        $current_line = '';
        
        foreach ($words as $word) {
            if (strlen($current_line . ' ' . $word) <= $max_chars_per_line) {
                $current_line .= ($current_line ? ' ' : '') . $word;
            } else {
                if (!empty($current_line)) {
                    $lines[] = $current_line;
                }
                $current_line = $word;
            }
        }
        
        if (!empty($current_line)) {
            $lines[] = $current_line;
        }
        
        return empty($lines) ? array($text) : $lines;
    }
    
    /**
     * 日本語テキストを複数行に分割
     */
    private function split_japanese_text_into_lines($text, $max_chars_per_line) {
        $lines = array();
        $current_line = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($chars as $char) {
            if (mb_strlen($current_line . $char) <= $max_chars_per_line) {
                $current_line .= $char;
            } else {
                if (!empty($current_line)) {
                    $lines[] = $current_line;
                }
                $current_line = $char;
            }
        }
        
        if (!empty($current_line)) {
            $lines[] = $current_line;
        }
        
        return empty($lines) ? array($text) : $lines;
    }
    
    /**
     * 日本語テキストのフォールバック描画（フォントがない場合）
     */
    private function draw_japanese_text_fallback($image, $text, $color, $width, $height) {
        // 基本設定から拡大倍率を取得
        $basic_settings = get_option('news_crawler_basic_settings', array());
        $scale_factor = isset($basic_settings['text_scale']) ? intval($basic_settings['text_scale']) : 4;
        
        // 日本語を読みやすい形式に変換
        $display_text = $this->format_japanese_for_display($text);
        
        // 長いテキストを複数行に分割
        $max_chars_per_line = intval(16 / ($scale_factor / 3));
        $lines = $this->split_japanese_text_into_lines($display_text, $max_chars_per_line);
        
        $font_size = 5; // 内蔵フォントサイズ（1-5）
        $base_char_width = imagefontwidth($font_size) * 2; // 日本語用に幅を調整
        $base_char_height = imagefontheight($font_size);
        $scaled_char_width = $base_char_width * $scale_factor;
        $scaled_char_height = $base_char_height * $scale_factor;
        
        $line_height = $scaled_char_height + 30; // 行間を追加
        $total_height = count($lines) * $line_height;
        
        // 開始Y位置を計算（中央揃え）
        $start_y = ($height - $total_height) / 2;
        
        foreach ($lines as $index => $line) {
            $text_width = $scaled_char_width * mb_strlen($line);
            $x = ($width - $text_width) / 2;
            $y = $start_y + ($index * $line_height);
            
            // 拡大描画のために文字を1文字ずつ処理
            $this->draw_scaled_japanese_text($image, $line, $font_size, $color, $x, $y, $scale_factor);
        }
    }
    
    /**
     * 日本語を表示用にフォーマット
     */
    private function format_japanese_for_display($text) {
        // 読みやすくするための調整
        $formatted = $text;
        
        // スペースを適切に配置
        $formatted = str_replace('ニュースまとめ', 'ニュース まとめ', $formatted);
        
        return $formatted;
    }
    
    /**
     * 拡大された日本語文字を描画
     */
    private function draw_scaled_japanese_text($image, $text, $font_size, $color, $start_x, $start_y, $scale_factor) {
        $base_char_width = imagefontwidth($font_size) * 2; // 日本語用に幅を調整
        $base_char_height = imagefontheight($font_size);
        
        // 影色
        $shadow_color = imagecolorallocate($image, 0, 0, 0);
        
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        for ($i = 0; $i < count($chars); $i++) {
            $char = $chars[$i];
            $char_x = $start_x + ($i * $base_char_width * $scale_factor);
            
            // 日本語文字を簡略化して表示
            $display_char = $this->simplify_japanese_char($char);
            
            // 一時的な小さい画像を作成して文字を描画
            $temp_img = imagecreatetruecolor($base_char_width, $base_char_height);
            $temp_bg = imagecolorallocate($temp_img, 255, 255, 255);
            $temp_text_color = imagecolorallocate($temp_img, 0, 0, 0);
            imagefill($temp_img, 0, 0, $temp_bg);
            imagestring($temp_img, $font_size, 0, 0, $display_char, $temp_text_color);
            
            // 拡大してメイン画像にコピー（影）
            imagecopyresized(
                $image, $temp_img,
                $char_x + ($scale_factor * 2), $start_y + ($scale_factor * 2), // 影の位置
                0, 0,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                $base_char_width, $base_char_height
            );
            
            // 影を黒に変換
            $this->replace_color($image, 
                $char_x + ($scale_factor * 2), $start_y + ($scale_factor * 2),
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                imagecolorallocate($image, 255, 255, 255), $shadow_color
            );
            
            // 拡大してメイン画像にコピー（メインテキスト）
            imagecopyresized(
                $image, $temp_img,
                $char_x, $start_y,
                0, 0,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                $base_char_width, $base_char_height
            );
            
            // メインテキストの色を変更
            $this->replace_color($image, 
                $char_x, $start_y,
                $base_char_width * $scale_factor, $base_char_height * $scale_factor,
                imagecolorallocate($image, 255, 255, 255), $color
            );
            
            imagedestroy($temp_img);
        }
    }
    
    /**
     * 日本語文字を簡略化（フォールバック用）
     */
    private function simplify_japanese_char($char) {
        // 日本語文字を英数字に簡略化するマッピング
        $char_map = array(
            // ひらがな
            'あ' => 'a', 'い' => 'i', 'う' => 'u', 'え' => 'e', 'お' => 'o',
            'か' => 'ka', 'き' => 'ki', 'く' => 'ku', 'け' => 'ke', 'こ' => 'ko',
            'さ' => 'sa', 'し' => 'si', 'す' => 'su', 'せ' => 'se', 'そ' => 'so',
            'た' => 'ta', 'ち' => 'ti', 'つ' => 'tu', 'て' => 'te', 'と' => 'to',
            'な' => 'na', 'に' => 'ni', 'ぬ' => 'nu', 'ね' => 'ne', 'の' => 'no',
            'は' => 'ha', 'ひ' => 'hi', 'ふ' => 'hu', 'へ' => 'he', 'ほ' => 'ho',
            'ま' => 'ma', 'み' => 'mi', 'む' => 'mu', 'め' => 'me', 'も' => 'mo',
            'や' => 'ya', 'ゆ' => 'yu', 'よ' => 'yo',
            'ら' => 'ra', 'り' => 'ri', 'る' => 'ru', 'れ' => 're', 'ろ' => 'ro',
            'わ' => 'wa', 'ん' => 'n',
            
            // カタカナ
            'ア' => 'A', 'イ' => 'I', 'ウ' => 'U', 'エ' => 'E', 'オ' => 'O',
            'カ' => 'KA', 'キ' => 'KI', 'ク' => 'KU', 'ケ' => 'KE', 'コ' => 'KO',
            'サ' => 'SA', 'シ' => 'SI', 'ス' => 'SU', 'セ' => 'SE', 'ソ' => 'SO',
            'タ' => 'TA', 'チ' => 'TI', 'ツ' => 'TU', 'テ' => 'TE', 'ト' => 'TO',
            'ナ' => 'NA', 'ニ' => 'NI', 'ヌ' => 'NU', 'ネ' => 'NE', 'ノ' => 'NO',
            'ハ' => 'HA', 'ヒ' => 'HI', 'フ' => 'HU', 'ヘ' => 'HE', 'ホ' => 'HO',
            'マ' => 'MA', 'ミ' => 'MI', 'ム' => 'MU', 'メ' => 'ME', 'モ' => 'MO',
            'ヤ' => 'YA', 'ユ' => 'YU', 'ヨ' => 'YO',
            'ラ' => 'RA', 'リ' => 'RI', 'ル' => 'RU', 'レ' => 'RE', 'ロ' => 'RO',
            'ワ' => 'WA', 'ン' => 'N',
            
            // 漢字（よく使われるもの）
            '政' => 'SEI', '治' => 'JI', '経' => 'KEI', '済' => 'ZAI',
            '社' => 'SHA', '会' => 'KAI', '国' => 'KOKU', '際' => 'SAI',
            '地' => 'CHI', '域' => 'IKI', '最' => 'SAI', '新' => 'SHIN',
            'ニ' => 'NI', 'ュ' => 'YU', 'ー' => '-', 'ス' => 'SU',
            'ま' => 'MA', 'と' => 'TO', 'め' => 'ME',
            '月' => 'GATSU', '日' => 'NICHI',
            
            // 数字
            '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5',
            '６' => '6', '７' => '7', '８' => '8', '９' => '9', '０' => '0',
            
            // 記号
            '・' => '*', '：' => ':', '、' => ',', '。' => '.'
        );
        
        return isset($char_map[$char]) ? $char_map[$char] : $char;
    }
    
    /**
     * 英語テキストを複数行に分割（旧版・互換性のため残す）
     */
    private function split_english_text_into_lines($text, $max_chars_per_line) {
        $words = explode(' ', $text);
        $lines = array();
        $current_line = '';
        
        foreach ($words as $word) {
            if (strlen($current_line . ' ' . $word) <= $max_chars_per_line) {
                $current_line .= ($current_line ? ' ' : '') . $word;
            } else {
                if (!empty($current_line)) {
                    $lines[] = $current_line;
                }
                $current_line = $word;
            }
        }
        
        if (!empty($current_line)) {
            $lines[] = $current_line;
        }
        
        return empty($lines) ? array($text) : $lines;
    }
    

    
    /**
     * キーワードタグを描画（日本語対応）
     */
    private function draw_keywords_on_image($image, $keywords, $width, $height, $text_color) {
        $rgb = $this->hex_to_rgb($text_color);
        $tag_color = imagecolorallocatealpha($image, $rgb['r'], $rgb['g'], $rgb['b'], 30);
        $text_color_obj = imagecolorallocate($image, $rgb['r'], $rgb['g'], $rgb['b']);
        
        $tag_y = $height - 80;
        $tag_x = 50;
        
        foreach (array_slice($keywords, 0, 3) as $keyword) {
            // キーワードをローマ字に変換
            $romaji_keyword = $this->convert_japanese_to_romaji($keyword);
            
            $tag_width = strlen($romaji_keyword) * 8 + 20; // ローマ字用に調整
            $tag_height = 30;
            
            // タグ背景（角丸風）
            imagefilledrectangle($image, $tag_x, $tag_y, $tag_x + $tag_width, $tag_y + $tag_height, $tag_color);
            
            // タグテキスト（太く表示）
            $font_size = 3;
            for ($dx = 0; $dx <= 1; $dx++) {
                for ($dy = 0; $dy <= 1; $dy++) {
                    imagestring($image, $font_size, $tag_x + 10 + $dx, $tag_y + 8 + $dy, $romaji_keyword, $text_color_obj);
                }
            }
            
            $tag_x += $tag_width + 15;
        }
    }
    
    /**
     * 日本語フォントファイルのパスを取得
     */
    private function get_japanese_font_path() {
        error_log('Featured Image Generator: Starting font path search...');
        
        // 優先順位1: macOSシステムフォント（信頼性が高い）
        $macos_fonts = array(
            '/System/Library/Fonts/PingFang.ttc',
            '/System/Library/Fonts/Hiragino Sans GB.ttc',
            '/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc',
            '/System/Library/Fonts/Supplemental/Hiragino Sans GB.ttc',
            '/Library/Fonts/ヒラギノ角ゴ ProN W3.otf'
        );
        
        error_log('Featured Image Generator: Checking macOS system fonts...');
        foreach ($macos_fonts as $font) {
            error_log('Featured Image Generator: Checking font: ' . $font);
            if (file_exists($font)) {
                error_log('Featured Image Generator: Found macOS system font: ' . $font);
                return $font;
            } else {
                error_log('Featured Image Generator: Font not found: ' . $font);
            }
        }
        
        // 優先順位2: プラグインディレクトリ内の日本語フォントファイル
        $plugin_fonts = array(
            plugin_dir_path(__FILE__) . '../assets/fonts/NotoSansJP-Regular.otf',
            plugin_dir_path(__FILE__) . '../assets/fonts/NotoSansJP-Regular.ttf',
            plugin_dir_path(__FILE__) . '../assets/fonts/NotoSansJP-Bold.ttf',
            plugin_dir_path(__FILE__) . '../assets/fonts/NotoSansCJK-Regular.ttf',
            plugin_dir_path(__FILE__) . '../assets/fonts/japanese.ttf'
        );
        
        error_log('Featured Image Generator: Checking plugin fonts...');
        foreach ($plugin_fonts as $font) {
            error_log('Featured Image Generator: Checking font: ' . $font);
            if (file_exists($font)) {
                error_log('Featured Image Generator: Found plugin font: ' . $font);
                return $font;
            } else {
                error_log('Featured Image Generator: Font not found: ' . $font);
            }
        }
        
        // Linuxシステムフォント
        $linux_fonts = array(
            '/usr/share/fonts/truetype/noto/NotoSansCJK-Regular.ttc',
            '/usr/share/fonts/opentype/noto/NotoSansCJK-Regular.otf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
        );
        
        foreach ($linux_fonts as $font) {
            if (file_exists($font)) {
                error_log('Featured Image Generator: Found Linux system font: ' . $font);
                return $font;
            }
        }
        
        error_log('Featured Image Generator: No Japanese font found');
        return false;
    }
    
    /**
     * フォントファイルのパスを取得（旧版・互換性のため残す）
     */
    private function get_font_path() {
        // プラグインディレクトリ内のフォントファイルを確認
        $plugin_fonts = array(
            plugin_dir_path(__FILE__) . '../assets/fonts/NotoSansJP-Regular.otf',
            plugin_dir_path(__FILE__) . '../assets/fonts/NotoSansJP-Regular.ttf'
        );
        
        foreach ($plugin_fonts as $plugin_font) {
            if (file_exists($plugin_font)) {
                return $plugin_font;
            }
        }
        
        // システムフォントを確認（macOS/Linux）
        $system_fonts = array(
            '/System/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/TTF/arial.ttf'
        );
        
        foreach ($system_fonts as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        
        return false;
    }
    
    /**
     * 日本語タイトルを生成（ジャンル + キーワード + ニュースまとめ + 日付）
     */
    private function create_japanese_title($original_title, $keywords) {
        // ジャンルを抽出（元のタイトルまたはキーワードから）
        $genre = $this->extract_genre_from_title($original_title, $keywords);
        
        // キーワードから主要なものを選択
        $main_keyword = '';
        if (!empty($keywords)) {
            // 最初のキーワードを使用、または複数のキーワードを組み合わせ
            if (count($keywords) == 1) {
                $main_keyword = $keywords[0];
            } else {
                // 複数のキーワードがある場合は最初の2つを使用
                $main_keyword = implode('・', array_slice($keywords, 0, 2));
            }
        } else {
            // キーワードがない場合は元のタイトルから推測
            $main_keyword = $this->extract_keyword_from_title($original_title);
        }
        
        // 日付を取得（今日の日付）
        $date = date_i18n('n月j日');
        
        // タイトル形式: {ジャンル}{キーワード}ニュースまとめ {日付}
        $japanese_title = $genre . $main_keyword . 'ニュースまとめ ' . $date;
        
        return $japanese_title;
    }
    
    /**
     * ジャンルを抽出
     */
    private function extract_genre_from_title($title, $keywords) {
        // ジャンルマッピング
        $genre_map = array(
            '政治' => '政治',
            '経済' => '経済',
            'AI' => 'テック',
            'テクノロジー' => 'テック',
            'ビジネス' => 'ビジネス',
            'スポーツ' => 'スポーツ',
            '芸能' => 'エンタメ',
            '社会' => '社会',
            '国際' => '国際',
            '地域' => '地域',
            '健康' => '健康',
            '教育' => '教育',
            '環境' => '環境'
        );
        
        // キーワードからジャンルを検索
        if (!empty($keywords)) {
            foreach ($keywords as $keyword) {
                foreach ($genre_map as $search => $genre) {
                    if (strpos($keyword, $search) !== false) {
                        return $genre;
                    }
                }
            }
        }
        
        // タイトルからジャンルを検索
        foreach ($genre_map as $search => $genre) {
            if (strpos($title, $search) !== false) {
                return $genre;
            }
        }
        
        // デフォルトジャンル
        return '最新';
    }
    
    /**
     * タイトルからキーワードを抽出
     */
    private function extract_keyword_from_title($title) {
        // よく使われるキーワードのマッピング
        $keyword_map = array(
            '政治' => '政治',
            '経済' => '経済',
            '自民党' => '政治',
            '公明党' => '政治',
            '参政党' => '政治',
            '国民民主党' => '政治',
            'AI' => 'AI',
            'テクノロジー' => 'テック',
            'ビジネス' => 'ビジネス',
            'スポーツ' => 'スポーツ',
            '芸能' => '芸能',
            '社会' => '社会',
            '国際' => '国際',
            '地域' => '地域'
        );
        
        foreach ($keyword_map as $search => $keyword) {
            if (strpos($title, $search) !== false) {
                return $keyword;
            }
        }
        
        // マッチしない場合はデフォルト
        return '最新';
    }
    
    /**
     * タイトルを画像表示用にフォーマット
     */
    private function format_title_for_image($title, $max_length = 40) {
        if (mb_strlen($title) <= $max_length) {
            return $title;
        }
        
        return mb_substr($title, 0, $max_length) . '...';
    }
    
    /**
     * 画像をWordPressの添付ファイルとして保存
     */
    private function save_image_as_attachment($image, $post_id, $title) {
        error_log('Featured Image Generator - Save: Starting save process');
        
        // 一時ファイル作成
        $upload_dir = wp_upload_dir();
        $filename = 'featured-image-' . $post_id . '-' . time() . '.png';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        error_log('Featured Image Generator - Save: Upload dir: ' . $upload_dir['path']);
        error_log('Featured Image Generator - Save: Filename: ' . $filename);
        
        // PNG形式で保存
        if (!imagepng($image, $filepath)) {
            error_log('Featured Image Generator - Save: Failed to save PNG file');
            imagedestroy($image);
            return false;
        }
        
        error_log('Featured Image Generator - Save: PNG file saved successfully');
        imagedestroy($image);
        
        // WordPressの添付ファイルとして登録
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => 'image/png',
            'post_title' => 'アイキャッチ: ' . $title,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $filepath, $post_id);
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        // 添付ファイルのメタデータを生成
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $filepath);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // 投稿のアイキャッチに設定
        $thumbnail_result = set_post_thumbnail($post_id, $attachment_id);
        error_log('Featured Image Generator - Save: Set post thumbnail result: ' . ($thumbnail_result ? 'Success' : 'Failed'));
        
        return $attachment_id;
    }
    
    /**
     * 外部画像をダウンロードして添付ファイルとして保存
     */
    private function download_and_attach_image($image_url, $post_id, $title) {
        // 画像をダウンロード
        $response = wp_remote_get($image_url, array('timeout' => 30));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        
        // ファイル拡張子を決定
        $extension = 'jpg';
        if (strpos($content_type, 'png') !== false) {
            $extension = 'png';
        } elseif (strpos($content_type, 'gif') !== false) {
            $extension = 'gif';
        }
        
        // 一時ファイル作成
        $upload_dir = wp_upload_dir();
        $filename = 'featured-image-' . $post_id . '-' . time() . '.' . $extension;
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        if (!file_put_contents($filepath, $image_data)) {
            return false;
        }
        
        // WordPressの添付ファイルとして登録
        $attachment = array(
            'guid' => $upload_dir['url'] . '/' . $filename,
            'post_mime_type' => $content_type,
            'post_title' => 'アイキャッチ: ' . $title,
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $filepath, $post_id);
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        // 添付ファイルのメタデータを生成
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $filepath);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        // 投稿のアイキャッチに設定
        set_post_thumbnail($post_id, $attachment_id);
        
        return $attachment_id;
    }
    
    /**
     * AI画像生成用のプロンプトを作成
     */
    private function create_ai_prompt($title, $keywords, $settings) {
        $style = isset($settings['ai_style']) ? $settings['ai_style'] : 'modern, clean, professional';
        $base_prompt = isset($settings['ai_base_prompt']) ? $settings['ai_base_prompt'] : 'Create a featured image for a blog post about';
        
        $keyword_text = !empty($keywords) ? implode(', ', array_slice($keywords, 0, 3)) : '';
        
        $prompt = $base_prompt . ' "' . $title . '"';
        if (!empty($keyword_text)) {
            $prompt .= ' related to ' . $keyword_text;
        }
        $prompt .= '. Style: ' . $style . '. No text overlay.';
        
        return $prompt;
    }
    
    /**
     * Unsplash検索用のクエリを作成
     */
    private function create_unsplash_query($title, $keywords) {
        if (!empty($keywords)) {
            return implode(' ', array_slice($keywords, 0, 2));
        }
        
        // タイトルから重要なキーワードを抽出
        $words = explode(' ', $title);
        $important_words = array_slice($words, 0, 2);
        
        return implode(' ', $important_words);
    }
    
    /**
     * 設定のサニタイズ
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // テンプレート設定
        $sanitized['template_width'] = isset($input['template_width']) ? intval($input['template_width']) : 1200;
        $sanitized['template_height'] = isset($input['template_height']) ? intval($input['template_height']) : 630;
        $sanitized['bg_color1'] = isset($input['bg_color1']) ? sanitize_hex_color($input['bg_color1']) : '#4F46E5';
        $sanitized['bg_color2'] = isset($input['bg_color2']) ? sanitize_hex_color($input['bg_color2']) : '#7C3AED';
        $sanitized['text_color'] = isset($input['text_color']) ? sanitize_hex_color($input['text_color']) : '#FFFFFF';
        $sanitized['font_size'] = isset($input['font_size']) ? intval($input['font_size']) : 48;
        $sanitized['text_scale'] = isset($input['text_scale']) ? intval($input['text_scale']) : 3;
        
        // AI設定
        $sanitized['openai_api_key'] = isset($input['openai_api_key']) ? sanitize_text_field($input['openai_api_key']) : '';
        $sanitized['ai_style'] = isset($input['ai_style']) ? sanitize_text_field($input['ai_style']) : 'modern, clean, professional';
        $sanitized['ai_base_prompt'] = isset($input['ai_base_prompt']) ? sanitize_textarea_field($input['ai_base_prompt']) : 'Create a featured image for a blog post about';
        
        // Unsplash設定
        $sanitized['unsplash_access_key'] = isset($input['unsplash_access_key']) ? sanitize_text_field($input['unsplash_access_key']) : '';
        
        return $sanitized;
    }
    
    /**
     * 設定画面のHTML出力
     */
    public function render_settings_form() {
        $settings = get_option($this->option_name, array());
        ?>
        <div class="featured-image-settings">
            <h3>アイキャッチ自動生成設定</h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">生成方法</th>
                    <td>
                        <select name="featured_image_method" id="featured_image_method">
                            <option value="template">テンプレート生成</option>
                            <option value="ai">AI画像生成 (OpenAI DALL-E)</option>
                            <option value="unsplash">Unsplash画像取得</option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <!-- テンプレート設定 -->
            <div id="template-settings" class="method-settings">
                <h4>テンプレート設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">画像サイズ</th>
                        <td>
                            <input type="number" name="<?php echo $this->option_name; ?>[template_width]" value="<?php echo esc_attr($settings['template_width'] ?? 1200); ?>" min="400" max="2000" /> × 
                            <input type="number" name="<?php echo $this->option_name; ?>[template_height]" value="<?php echo esc_attr($settings['template_height'] ?? 630); ?>" min="200" max="1200" /> px
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">背景色1</th>
                        <td><input type="color" name="<?php echo $this->option_name; ?>[bg_color1]" value="<?php echo esc_attr($settings['bg_color1'] ?? '#4F46E5'); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">背景色2</th>
                        <td><input type="color" name="<?php echo $this->option_name; ?>[bg_color2]" value="<?php echo esc_attr($settings['bg_color2'] ?? '#7C3AED'); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">テキスト色</th>
                        <td><input type="color" name="<?php echo $this->option_name; ?>[text_color]" value="<?php echo esc_attr($settings['text_color'] ?? '#FFFFFF'); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row">フォントサイズ</th>
                        <td>
                            <input type="number" name="<?php echo $this->option_name; ?>[font_size]" value="<?php echo esc_attr($settings['font_size'] ?? 48); ?>" min="24" max="120" /> px
                            <p class="description">TTFフォント使用時のサイズ。内蔵フォント使用時は自動調整されます。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">文字拡大倍率</th>
                        <td>
                            <select name="<?php echo $this->option_name; ?>[text_scale]">
                                <option value="2" <?php selected($settings['text_scale'] ?? 3, 2); ?>>2倍</option>
                                <option value="3" <?php selected($settings['text_scale'] ?? 3, 3); ?>>3倍（推奨）</option>
                                <option value="4" <?php selected($settings['text_scale'] ?? 3, 4); ?>>4倍</option>
                                <option value="5" <?php selected($settings['text_scale'] ?? 3, 5); ?>>5倍</option>
                            </select>
                            <p class="description">内蔵フォント使用時の文字拡大倍率</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- AI設定 -->
            <div id="ai-settings" class="method-settings" style="display: none;">
                <h4>AI画像生成設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">OpenAI APIキー</th>
                        <td><input type="text" name="<?php echo $this->option_name; ?>[openai_api_key]" value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" size="50" /></td>
                    </tr>
                    <tr>
                        <th scope="row">画像スタイル</th>
                        <td><input type="text" name="<?php echo $this->option_name; ?>[ai_style]" value="<?php echo esc_attr($settings['ai_style'] ?? 'modern, clean, professional'); ?>" size="50" /></td>
                    </tr>
                </table>
            </div>
            
            <!-- Unsplash設定 -->
            <div id="unsplash-settings" class="method-settings" style="display: none;">
                <h4>Unsplash設定</h4>
                <table class="form-table">
                    <tr>
                        <th scope="row">Unsplash Access Key</th>
                        <td><input type="text" name="<?php echo $this->option_name; ?>[unsplash_access_key]" value="<?php echo esc_attr($settings['unsplash_access_key'] ?? ''); ?>" size="50" /></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#featured_image_method').change(function() {
                $('.method-settings').hide();
                $('#' + $(this).val() + '-settings').show();
            });
        });
        </script>
        <?php
    }
}