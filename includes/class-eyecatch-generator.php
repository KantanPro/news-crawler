<?php
/**
 * アイキャッチ画像生成クラス
 * 
 * @package NewsCrawler
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class News_Crawler_Eyecatch_Generator {
    
    /**
     * アイキャッチ画像を生成
     * 
     * @param string $genre ジャンル
     * @param string $keyword キーワード
     * @param string $date 日付
     * @return string|WP_Error 生成された画像のURLまたはエラー
     */
    public function generate_eyecatch($genre, $keyword, $date) {
        
        error_log('News Crawler: アイキャッチ生成開始 - ジャンル: ' . $genre . ', キーワード: ' . $keyword . ', 日付: ' . $date);
        
        // GDライブラリが利用可能かチェック
        if (!extension_loaded('gd')) {
            error_log('News Crawler: GDライブラリが利用できません');
            return new WP_Error('gd_not_available', 'GDライブラリが利用できません');
        }
        
        // FreeTypeサポートの確認
        if (!function_exists('imagettftext')) {
            error_log('News Crawler: FreeTypeサポートが利用できません');
            return new WP_Error('freetype_not_available', 'FreeTypeサポートが利用できません。TrueTypeフォントを使用できません。');
        }
        
        try {
            // 画像サイズ設定
            $width = 1200;
            $height = 630;
            
            error_log('News Crawler: 画像作成開始 - サイズ: ' . $width . 'x' . $height);
            
            // 画像を作成
            $image = imagecreatetruecolor($width, $height);
            
            if (!$image) {
                throw new Exception('画像の作成に失敗しました');
            }
            
            // 背景色（グラデーション）
            $this->create_gradient_background($image, $width, $height);
            
            // フォントファイルのパス（プラグイン内フォントを優先）
            $font_path = $this->get_japanese_font_path();
            
            if (!$font_path) {
                imagedestroy($image);
                error_log('News Crawler: 日本語フォントが見つかりません');
                return new WP_Error('font_not_found', 'Japanese Font Required - 日本語フォントが見つかりません。システムに日本語フォントがインストールされているか確認してください。');
            }
            
            error_log('News Crawler: 使用フォント: ' . $font_path . ' (サイズ: ' . filesize($font_path) . ' bytes)');
            
            // フォントの動作確認
            $test_bbox = imagettfbbox(20, 0, $font_path, 'テスト');
            if ($test_bbox === false) {
                imagedestroy($image);
                error_log('News Crawler: フォントファイルの読み込みに失敗: ' . $font_path);
                return new WP_Error('font_read_error', 'Japanese Font Required - フォントファイルの読み込みに失敗しました。フォントファイルが破損している可能性があります。');
            }
            
            error_log('News Crawler: フォントテスト成功 - 境界ボックス: ' . implode(', ', $test_bbox));
            
            // テキストを描画
            try {
                $this->draw_text($image, $genre, $font_path, 48, 0xFFFFFF, $width, 200);
                $this->draw_text($image, $keyword, $font_path, 36, 0xFFFFFF, $width, 280);
                $this->draw_text($image, 'ニュースまとめ', $font_path, 42, 0xFFFFFF, $width, 360);
                $this->draw_text($image, $date, $font_path, 32, 0xFFFFFF, $width, 420);
                error_log('News Crawler: 全テキストの描画が完了しました');
            } catch (Exception $e) {
                imagedestroy($image);
                error_log('News Crawler: テキスト描画エラー: ' . $e->getMessage());
                return new WP_Error('text_draw_error', 'Japanese Font Required - テキストの描画に失敗しました: ' . $e->getMessage());
            }
            
            // 装飾要素を追加
            $this->add_decorative_elements($image, $width, $height);
            
            // 一時ファイルに保存
            $upload_dir = wp_upload_dir();
            $filename = 'eyecatch_' . sanitize_title($genre . '_' . $keyword) . '_' . date('YmdHis') . '.png';
            $filepath = $upload_dir['path'] . '/' . $filename;
            
            error_log('News Crawler: 画像保存開始 - ' . $filepath);
            
            // PNG形式で保存
            if (imagepng($image, $filepath)) {
                imagedestroy($image);
                error_log('News Crawler: 画像保存成功');
                
                // メディアライブラリに登録
                $attachment_id = $this->add_to_media_library($filepath, $filename);
                
                if ($attachment_id) {
                    $url = wp_get_attachment_url($attachment_id);
                    error_log('News Crawler: アイキャッチ生成完了 - URL: ' . $url);
                    return $url;
                } else {
                    error_log('News Crawler: メディアライブラリへの登録に失敗');
                    return new WP_Error('media_library_error', 'メディアライブラリへの登録に失敗しました');
                }
            } else {
                imagedestroy($image);
                error_log('News Crawler: 画像の保存に失敗');
                return new WP_Error('save_error', '画像の保存に失敗しました');
            }
            
        } catch (Exception $e) {
            if (isset($image)) {
                imagedestroy($image);
            }
            error_log('News Crawler: アイキャッチ生成エラー - ' . $e->getMessage());
            error_log('News Crawler: エラーの詳細 - ' . $e->getTraceAsString());
            return new WP_Error('generation_error', 'アイキャッチ画像の生成中にエラーが発生しました: ' . $e->getMessage());
        }
    }
    
    /**
     * グラデーション背景を作成
     */
    private function create_gradient_background($image, $width, $height) {
        // グラデーションの色
        $color1 = imagecolorallocate($image, 41, 128, 185);  // 青
        $color2 = imagecolorallocate($image, 52, 152, 219);  // 明るい青
        
        // グラデーションを描画
        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            
            // 色の値を個別に計算し、0-255の範囲に制限
            $r1 = 41; $g1 = 128; $b1 = 185;
            $r2 = 52; $g2 = 152; $b2 = 219;
            
            $r = (int)max(0, min(255, $r1 + ($r2 - $r1) * $ratio));
            $g = (int)max(0, min(255, $g1 + ($g2 - $g1) * $ratio));
            $b = (int)max(0, min(255, $b1 + ($b2 - $b1) * $ratio));
            
            $color = imagecolorallocate($image, $r, $g, $b);
            imageline($image, 0, $y, $width, $y, $color);
        }
    }
    
    /**
     * テキストを描画（センタリング）
     */
    private function draw_text($image, $text, $font_path, $font_size, $color, $width, $y) {
        // フォントファイルの存在確認
        if (!file_exists($font_path) || !is_readable($font_path)) {
            error_log('News Crawler: フォントファイルが存在しないか読み取り不可 - ' . $font_path);
            throw new Exception('フォントファイルが存在しないか読み取り不可: ' . $font_path);
        }
        
        // テキストの境界ボックスを取得
        $bbox = imagettfbbox($font_size, 0, $font_path, $text);
        
        // フォント読み込みエラーの確認
        if ($bbox === false) {
            error_log('News Crawler: フォント読み込みエラー - ' . $font_path . ' (テキスト: ' . $text . ')');
            throw new Exception('フォントファイルの読み込みに失敗しました: ' . $font_path);
        }
        
        // 境界ボックスの値が正しく取得できているか確認
        if (!is_array($bbox) || count($bbox) < 8) {
            error_log('News Crawler: 境界ボックスが正しく取得できません - ' . $text);
            throw new Exception('テキストの境界ボックスが正しく取得できません: ' . $text);
        }
        
        $text_width = $bbox[4] - $bbox[0];
        $text_height = $bbox[1] - $bbox[7];
        
        // センタリング位置を計算
        $x = ($width - $text_width) / 2;
        
        // Y座標を調整（テキストのベースラインに合わせる）
        $adjusted_y = $y + $text_height;
        
        error_log('News Crawler: テキスト描画 - ' . $text . ' (x: ' . $x . ', y: ' . $adjusted_y . ', width: ' . $text_width . ')');
        
        // テキストを描画
        $result = imagettftext($image, $font_size, 0, $x, $adjusted_y, $color, $font_path, $text);
        
        // テキスト描画エラーの確認
        if ($result === false) {
            error_log('News Crawler: テキスト描画エラー - ' . $text . ' (フォント: ' . $font_path . ')');
            throw new Exception('テキストの描画に失敗しました: ' . $text);
        }
        
        error_log('News Crawler: テキスト描画成功 - ' . $text);
    }
    
    /**
     * 装飾要素を追加
     */
    private function add_decorative_elements($image, $width, $height) {
        // 角に装飾的な円を追加
        $white = imagecolorallocate($image, 255, 255, 255);
        $alpha = imagecolorallocatealpha($image, 255, 255, 255, 80);
        
        // 左上の円
        imagefilledellipse($image, 100, 100, 60, 60, $alpha);
        imageellipse($image, 100, 100, 60, 60, $white);
        
        // 右下の円
        imagefilledellipse($image, $width - 100, $height - 100, 80, 80, $alpha);
        imageellipse($image, $width - 100, $height - 100, 80, 80, $white);
        
        // 中央上部に装飾線
        $line_color = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, $width/2 - 50, 150, $width/2 + 50, 155, $line_color);
    }
    
    /**
     * 日本語フォントのパスを取得
     */
    private function get_japanese_font_path() {
        error_log('Eyecatch Generator: Starting font path search...');
        
        // 優先順位1: システムの日本語フォント（macOS、最も信頼性が高い）
        $system_fonts = array(
            '/System/Library/Fonts/STHeiti Medium.ttc',  // 最も安定している
            '/System/Library/Fonts/STHeiti Light.ttc',   // 軽量版
            '/System/Library/Fonts/PingFang.ttc',        // モダンなフォント
            '/System/Library/Fonts/Hiragino Sans GB.ttc', // ヒラギノ
            '/System/Library/Fonts/ヒラギノ角ゴシック W3.ttc',
            '/System/Library/Fonts/ヒラギノ角ゴシック W6.ttc',
            '/System/Library/Fonts/ヒラギノ角ゴシック W8.ttc',
            '/System/Library/Fonts/AquaKana.ttc',
            '/System/Library/Fonts/Osaka.ttf',
            '/Library/Fonts/Arial Unicode MS.ttf',
            '/Library/Fonts/ヒラギノ角ゴ Pro W3.otf',
            '/Library/Fonts/ヒラギノ角ゴ Pro W6.otf'
        );
        
        error_log('Eyecatch Generator: Checking ' . count($system_fonts) . ' system fonts...');
        foreach ($system_fonts as $font) {
            error_log('Eyecatch Generator: Checking system font: ' . $font);
            if (file_exists($font)) {
                error_log('Eyecatch Generator: Font file exists: ' . $font);
                if (is_readable($font)) {
                    error_log('Eyecatch Generator: Font file is readable: ' . $font);
                    $file_size = filesize($font);
                    error_log('Eyecatch Generator: Font file size: ' . $file_size . ' bytes');
                    
                    // フォントの動作確認
                    if (function_exists('imagettftext')) {
                        $test_bbox = imagettfbbox(20, 0, $font, 'テスト');
                        if ($test_bbox !== false) {
                            error_log('Eyecatch Generator: Font test successful: ' . $font);
                            return $font;
                        } else {
                            error_log('Eyecatch Generator: Font test failed (bbox): ' . $font);
                        }
                    } else {
                        error_log('Eyecatch Generator: FreeType functions not available');
                        return $font; // FreeTypeが利用できない場合は、ファイルの存在のみで判断
                    }
                } else {
                    error_log('Eyecatch Generator: Font file not readable: ' . $font);
                }
            } else {
                error_log('Eyecatch Generator: Font file does not exist: ' . $font);
            }
        }
        
        error_log('Eyecatch Generator: No working system fonts found, checking plugin fonts...');
        
        // 優先順位2: プラグイン内のフォントファイル
        $plugin_fonts = array();
        
        // WordPress関数を使用したパス解決
        if (function_exists('plugin_dir_path')) {
            $plugin_dir = plugin_dir_path(dirname(__FILE__));
            error_log('Eyecatch Generator: WordPress plugin_dir_path: ' . $plugin_dir);
            $plugin_fonts[] = $plugin_dir . 'assets/fonts/NotoSansJP-Regular.ttf';
            $plugin_fonts[] = $plugin_dir . 'assets/fonts/NotoSansJP-Regular.otf';
        }
        
        // 絶対パスでの解決（フォールバック）
        $fallback_path = dirname(dirname(__FILE__)) . '/assets/fonts/NotoSansJP-Regular.ttf';
        error_log('Eyecatch Generator: Fallback path: ' . $fallback_path);
        $plugin_fonts[] = $fallback_path;
        $plugin_fonts[] = dirname(dirname(__FILE__)) . '/assets/fonts/NotoSansJP-Regular.otf';
        
        // 重複を除去
        $plugin_fonts = array_unique($plugin_fonts);
        
        error_log('Eyecatch Generator: Checking ' . count($plugin_fonts) . ' plugin fonts...');
        foreach ($plugin_fonts as $plugin_font) {
            error_log('Eyecatch Generator: Checking plugin font: ' . $plugin_font);
            if (file_exists($plugin_font)) {
                error_log('Eyecatch Generator: Plugin font file exists: ' . $plugin_font);
                if (is_readable($plugin_font)) {
                    error_log('Eyecatch Generator: Plugin font file is readable: ' . $plugin_font);
                    $file_size = filesize($plugin_font);
                    error_log('Eyecatch Generator: Plugin font file size: ' . $file_size . ' bytes');
                    
                    // フォントの動作確認
                    if (function_exists('imagettftext')) {
                        $test_bbox = imagettfbbox(20, 0, $plugin_font, 'テスト');
                        if ($test_bbox !== false) {
                            error_log('Eyecatch Generator: Plugin font test successful: ' . $plugin_font);
                            return $plugin_font;
                        } else {
                            error_log('Eyecatch Generator: Plugin font test failed (bbox): ' . $plugin_font);
                        }
                    } else {
                        error_log('Eyecatch Generator: FreeType functions not available');
                        return $plugin_font; // FreeTypeが利用できない場合は、ファイルの存在のみで判断
                    }
                } else {
                    error_log('Eyecatch Generator: Plugin font file not readable: ' . $plugin_font);
                }
            } else {
                error_log('Eyecatch Generator: Plugin font file does not exist: ' . $plugin_font);
            }
        }
        
        // 優先順位3: fc-listで検索（Linux/Unix系）
        if (function_exists('exec')) {
            error_log('Eyecatch Generator: Trying fc-list command...');
            $output = array();
            $return_var = 0;
            exec('fc-list :lang=ja file 2>/dev/null', $output, $return_var);
            
            if ($return_var === 0 && !empty($output)) {
                error_log('Eyecatch Generator: fc-list found ' . count($output) . ' fonts');
                foreach ($output as $line) {
                    $font_path = trim($line);
                    error_log('Eyecatch Generator: fc-list font: ' . $font_path);
                    if (file_exists($font_path) && is_readable($font_path)) {
                        error_log('Eyecatch Generator: Found fc-list font: ' . $font_path);
                        return $font_path;
                    }
                }
            } else {
                error_log('Eyecatch Generator: fc-list command failed or returned no results');
            }
        } else {
            error_log('Eyecatch Generator: exec function not available');
        }
        
        // 優先順位4: Windows用のフォントパス
        $windows_fonts = array(
            'C:/Windows/Fonts/msgothic.ttc',
            'C:/Windows/Fonts/yu gothic.ttc',
            'C:/Windows/Fonts/meiryo.ttc'
        );
        
        error_log('Eyecatch Generator: Checking Windows fonts...');
        foreach ($windows_fonts as $font) {
            if (file_exists($font) && is_readable($font)) {
                error_log('Eyecatch Generator: Found Windows font: ' . $font);
                return $font;
            }
        }
        
        error_log('Eyecatch Generator: No Japanese font found after checking all sources!');
        return false;
    }
    
    /**
     * メディアライブラリに画像を登録
     */
    private function add_to_media_library($filepath, $filename) {
        $file_type = wp_check_filetype($filename, null);
        
        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $filepath);
        
        if (!is_wp_error($attachment_id)) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment_data = wp_generate_attachment_metadata($attachment_id, $filepath);
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            return $attachment_id;
        }
        
        return false;
    }
    
    /**
     * アイキャッチ画像生成のプレビュー用HTML
     */
    public function get_preview_html($genre, $keyword, $date) {
        $nonce = wp_create_nonce('generate_eyecatch');
        
        $html = '<div class="eyecatch-preview">';
        $html .= '<h3>アイキャッチ画像プレビュー</h3>';
        $html .= '<div class="preview-info">';
        $html .= '<p><strong>ジャンル:</strong> ' . esc_html($genre) . '</p>';
        $html .= '<p><strong>キーワード:</strong> ' . esc_html($keyword) . '</p>';
        $html .= '<p><strong>日付:</strong> ' . esc_html($date) . '</p>';
        $html .= '</div>';
        $html .= '<button type="button" class="button button-primary" onclick="generateEyecatch(\'' . esc_js($genre) . '\', \'' . esc_js($keyword) . '\', \'' . esc_js($date) . '\', \'' . $nonce . '\')">アイキャッチ画像を生成</button>';
        $html .= '<div id="eyecatch-result"></div>';
        $html .= '</div>';
        
        return $html;
    }
}
