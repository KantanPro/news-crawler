/**
 * License Manager JavaScript for News Crawler plugin
 *
 * Handles license verification and management functionality.
 *
 * @package NewsCrawler
 * @since 2.1.5
 */

(function($) {
    'use strict';

    // ライセンス管理オブジェクト
    var NewsCrawlerLicenseManager = {
        
        /**
         * 初期化
         */
        init: function() {
            this.bindEvents();
            this.initializeLicenseStatus();
        },

        /**
         * イベントのバインド
         */
        bindEvents: function() {
            // 開発用ライセンスの切り替え
            $(document).on('click', '#toggle-dev-license', function(e) {
                e.preventDefault();
                NewsCrawlerLicenseManager.toggleDevLicense();
            });

            // テスト用ライセンスキーの自動入力
            $(document).on('click', '#use-dev-license', function(e) {
                e.preventDefault();
                NewsCrawlerLicenseManager.useDevLicense();
            });

            // ライセンス認証フォームの送信
            $(document).on('submit', '#news-crawler-license-form', function(e) {
                e.preventDefault();
                NewsCrawlerLicenseManager.verifyLicense();
            });

            // ライセンス状態再確認
            $(document).on('click', 'input[name="recheck_license"]', function(e) {
                e.preventDefault();
                NewsCrawlerLicenseManager.recheckLicense();
            });
        },

        /**
         * ライセンスステータスの初期化
         */
        initializeLicenseStatus: function() {
            // ページ読み込み時にライセンスステータスを更新
            if (typeof news_crawler_license_ajax !== 'undefined') {
                this.updateLicenseStatus();
            }
        },

        /**
         * テスト用ライセンスキーの自動入力
         */
        useDevLicense: function() {
            if (typeof news_crawler_license_ajax !== 'undefined' && news_crawler_license_ajax.dev_license_key) {
                $('#news_crawler_license_key').val(news_crawler_license_ajax.dev_license_key);
                $('#news_crawler_license_key').attr('type', 'text'); // 一時的に表示
                
                // 3秒後にパスワードフィールドに戻す
                setTimeout(function() {
                    $('#news_crawler_license_key').attr('type', 'password');
                }, 3000);
                
                // 成功メッセージを表示
                this.showSuccess('テスト用ライセンスキーが入力されました。認証ボタンをクリックしてください。');
            }
        },

        /**
         * 開発用ライセンスの切り替え
         */
        toggleDevLicense: function() {
            var $button = $('#toggle-dev-license');
            var $spinner = $button.siblings('.spinner');
            
            // スピナーを表示
            $spinner.show();
            $button.prop('disabled', true);

            $.ajax({
                url: news_crawler_license_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'news_crawler_toggle_dev_license',
                    nonce: news_crawler_license_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // ボタンテキストを更新
                        var newText = response.data.new_status ? '開発用ライセンスを無効化' : '開発用ライセンスを有効化';
                        $button.text(newText);
                        
                        // ページをリロードしてステータスを更新
                        location.reload();
                    } else {
                        alert('エラーが発生しました: ' + (response.data ? response.data.message : '不明なエラー'));
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました。');
                },
                complete: function() {
                    // スピナーを非表示
                    $spinner.hide();
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * ライセンスの認証
         */
        verifyLicense: function() {
            var $form = $('#news-crawler-license-form');
            var $submitButton = $form.find('input[type="submit"]');
            var $licenseKey = $('#news_crawler_license_key');
            
            var licenseKey = $licenseKey.val().trim();
            
            if (!licenseKey) {
                alert('ライセンスキーを入力してください。');
                $licenseKey.focus();
                return;
            }

            // ボタンを無効化
            $submitButton.prop('disabled', true).val('認証中...');

            $.ajax({
                url: news_crawler_license_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'news_crawler_verify_license',
                    license_key: licenseKey,
                    nonce: news_crawler_license_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('ライセンスが正常に認証されました。');
                        // ページをリロードしてステータスを更新
                        location.reload();
                    } else {
                        alert('ライセンスの認証に失敗しました: ' + (response.data ? response.data.message : '不明なエラー'));
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました。');
                },
                complete: function() {
                    // ボタンを有効化
                    $submitButton.prop('disabled', false).val('ライセンスを認証');
                }
            });
        },

        /**
         * ライセンス状態の再確認
         */
        recheckLicense: function() {
            var $button = $('input[name="recheck_license"]');
            
            // ボタンを無効化
            $button.prop('disabled', true).val('確認中...');

            $.ajax({
                url: news_crawler_license_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'news_crawler_get_license_info',
                    nonce: news_crawler_license_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('ライセンス状態の再確認が完了しました。');
                        // ページをリロードしてステータスを更新
                        location.reload();
                    } else {
                        alert('ライセンス状態の確認に失敗しました: ' + (response.data ? response.data.message : '不明なエラー'));
                    }
                },
                error: function() {
                    alert('通信エラーが発生しました。');
                },
                complete: function() {
                    // ボタンを有効化
                    $button.prop('disabled', false).val('ライセンス状態を再確認');
                }
            });
        },

        /**
         * ライセンスステータスの更新
         */
        updateLicenseStatus: function() {
            // 定期的にライセンスステータスを更新（オプション）
            setInterval(function() {
                if (typeof news_crawler_license_ajax !== 'undefined') {
                    $.ajax({
                        url: news_crawler_license_ajax.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'news_crawler_get_license_info',
                            nonce: news_crawler_license_ajax.nonce
                        },
                        success: function(response) {
                            // ステータスが変更された場合のみ更新
                            if (response.success && response.data) {
                                // 必要に応じてUIを更新
                            }
                        }
                    });
                }
            }, 300000); // 5分ごとに更新
        },

        /**
         * エラーメッセージの表示
         */
        showError: function(message) {
            // エラーメッセージを表示する処理
            if (typeof message === 'string' && message.length > 0) {
                alert(message);
            }
        },

        /**
         * 成功メッセージの表示
         */
        showSuccess: function(message) {
            // 成功メッセージを表示する処理
            if (typeof message === 'string' && message.length > 0) {
                alert(message);
            }
        }
    };

    // DOM読み込み完了後に初期化
    $(document).ready(function() {
        NewsCrawlerLicenseManager.init();
    });

})(jQuery);
