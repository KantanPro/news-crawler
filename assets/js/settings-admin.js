/**
 * 設定管理画面用JavaScript
 * 
 * @package NewsCrawler
 * @since 2.0.5
 */

jQuery(document).ready(function($) {
    
    
    /**
     * 成功メッセージを表示
     */
    function showSuccess(message) {
        const notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        
        // 3秒後に自動で非表示
        setTimeout(function() {
            notice.fadeOut();
        }, 3000);
    }
    
    /**
     * エラーメッセージを表示
     */
    function showError(message) {
        const notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        
        // 5秒後に自動で非表示
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
    
    
    // 設定変更時の自動保存（オプション）
    $('input[type="text"], input[type="password"], select, textarea').on('change', function() {
        const field = $(this);
        const originalValue = field.data('original-value');
        
        if (originalValue === undefined) {
            field.data('original-value', field.val());
        } else if (originalValue !== field.val()) {
            field.addClass('modified');
        }
    });
    
    // 設定保存時の確認
    $('#submit').on('click', function() {
        const modifiedFields = $('.modified');
        if (modifiedFields.length > 0) {
            return confirm('設定が変更されています。保存しますか？');
        }
    });
    
    // 設定リセットの確認
    $('.reset-settings').on('click', function() {
        return confirm('本当に設定をリセットしますか？この操作は元に戻せません。');
    });
    
    // タブ切り替え機能（設定ページにタブがある場合）
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        const target = $(this).attr('href');
        const tabContent = $(target);
        
        // タブをアクティブにする
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // タブコンテンツを表示
        $('.tab-content').hide();
        tabContent.show();
        
        // URLハッシュを更新
        if (history.pushState) {
            history.pushState(null, null, target);
        }
    });
    
    // ページ読み込み時にURLハッシュに基づいてタブを表示
    const hash = window.location.hash;
    if (hash) {
        const targetTab = $('.nav-tab[href="' + hash + '"]');
        if (targetTab.length) {
            targetTab.click();
        }
    }
    
    // キャッシュクリア機能
    $('#clear-cache').on('click', function() {
        if (confirm('キャッシュをクリアしますか？')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'news_crawler_clear_cache',
                    nonce: $('#news_crawler_nonce').val()
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess('キャッシュをクリアしました。');
                        location.reload();
                    } else {
                        showError('キャッシュのクリアに失敗しました。');
                    }
                },
                error: function() {
                    showError('キャッシュのクリアに失敗しました。');
                }
            });
        }
    });
});
