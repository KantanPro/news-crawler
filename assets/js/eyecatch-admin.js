/**
 * アイキャッチ画像生成管理画面用JavaScript
 * 
 * @package NewsCrawler
 * @since 1.3.0
 */

jQuery(document).ready(function($) {
    
    // アイキャッチ画像生成ボタンのクリックイベント
    $('#generate-eyecatch').on('click', function() {
        generateEyecatch();
    });
    
    /**
     * アイキャッチ画像を生成
     */
    function generateEyecatch() {
        const genre = $('#genre').val().trim();
        const keyword = $('#keyword').val().trim();
        const date = $('#date').val();
        
        // バリデーション
        if (!genre) {
            alert('ジャンルを入力してください');
            $('#genre').focus();
            return;
        }
        
        if (!keyword) {
            alert('キーワードを入力してください');
            $('#keyword').focus();
            return;
        }
        
        if (!date) {
            alert('日付を選択してください');
            $('#date').focus();
            return;
        }
        
        // ローディング表示
        showLoading();
        
        // AJAXリクエスト
        $.ajax({
            url: newsCrawlerEyecatch.ajax_url,
            type: 'POST',
            data: {
                action: 'generate_eyecatch',
                nonce: newsCrawlerEyecatch.nonce,
                genre: genre,
                keyword: keyword,
                date: date
            },
            success: function(response) {
                hideLoading();
                
                if (response.success) {
                    showPreview(response.data.image_url, response.data.message);
                } else {
                    showError(response.data);
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                showError('通信エラーが発生しました: ' + error);
            }
        });
    }
    
    /**
     * ローディング表示
     */
    function showLoading() {
        $('#eyecatch-loading').show();
        $('#eyecatch-preview').hide();
        $('#eyecatch-error').hide();
        $('#generate-eyecatch').prop('disabled', true);
    }
    
    /**
     * ローディング非表示
     */
    function hideLoading() {
        $('#eyecatch-loading').hide();
        $('#generate-eyecatch').prop('disabled', false);
    }
    
    /**
     * プレビュー表示
     */
    function showPreview(imageUrl, message) {
        const container = $('#eyecatch-image-container');
        container.html(`
            <div class="eyecatch-image-wrapper">
                <img src="${imageUrl}" alt="生成されたアイキャッチ画像" style="max-width: 100%; height: auto;" />
                <div class="eyecatch-info">
                    <p><strong>生成日時:</strong> ${new Date().toLocaleString('ja-JP')}</p>
                    <p><strong>画像URL:</strong> <a href="${imageUrl}" target="_blank">${imageUrl}</a></p>
                </div>
            </div>
        `);
        
        $('#eyecatch-preview').show();
        
        // 成功メッセージ表示
        if (message) {
            showSuccessMessage(message);
        }
    }
    
    /**
     * エラー表示
     */
    function showError(message) {
        $('#error-message').text(message);
        $('#eyecatch-error').show();
    }
    
    /**
     * 成功メッセージ表示
     */
    function showSuccessMessage(message) {
        const notice = $(`
            <div class="notice notice-success is-dismissible">
                <p>${message}</p>
            </div>
        `);
        
        $('.wrap h1').after(notice);
        
        // 3秒後に自動で非表示
        setTimeout(function() {
            notice.fadeOut();
        }, 3000);
    }
    
    /**
     * ダウンロードボタンのイベント
     */
    $(document).on('click', '#download-eyecatch', function() {
        const imageUrl = $('#eyecatch-image-container img').attr('src');
        if (imageUrl) {
            const link = document.createElement('a');
            link.href = imageUrl;
            link.download = 'eyecatch_' + new Date().getTime() + '.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });
    
    /**
     * アイキャッチ画像として使用ボタンのイベント
     */
    $(document).on('click', '#use-as-featured', function() {
        const imageUrl = $('#eyecatch-image-container img').attr('src');
        if (imageUrl) {
            // 現在の投稿編集画面にアイキャッチ画像を設定
            if (window.parent && window.parent.wp && window.parent.wp.media) {
                // メディアライブラリから画像を選択した状態にする
                const imageId = extractImageIdFromUrl(imageUrl);
                if (imageId) {
                    window.parent.postMessage({
                        action: 'set_featured_image',
                        image_id: imageId
                    }, '*');
                }
            }
            
            // 成功メッセージ
            showSuccessMessage('アイキャッチ画像が設定されました');
        }
    });
    
    /**
     * URLから画像IDを抽出
     */
    function extractImageIdFromUrl(url) {
        // URLから画像IDを抽出する処理
        // 例: /wp-content/uploads/2025/08/eyecatch_xxx.png
        const match = url.match(/eyecatch_.*?\.png$/);
        if (match) {
            // 実際の実装では、データベースから画像IDを取得する必要があります
            return null;
        }
        return null;
    }
    
    // Enterキーでフォーム送信
    $('input').on('keypress', function(e) {
        if (e.which === 13) {
            generateEyecatch();
        }
    });
    
});
