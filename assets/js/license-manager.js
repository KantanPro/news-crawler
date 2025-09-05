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
            
            // デバッグ情報を出力
            console.log('NewsCrawler License: toggleDevLicense called');
            console.log('NewsCrawler License: news_crawler_license_ajax =', typeof news_crawler_license_ajax !== 'undefined' ? news_crawler_license_ajax : 'undefined');
            if (typeof news_crawler_license_ajax !== 'undefined') {
                console.log('NewsCrawler License: AJAX URL =', news_crawler_license_ajax.ajaxurl);
            }
            
            if (typeof news_crawler_license_ajax === 'undefined') {
                alert('エラー: AJAX設定が読み込まれていません。ページを再読み込みしてください。');
                return;
            }
            
            // 直接的なAJAX処理を実行（WordPressのAJAX処理をバイパス）
            this.performDirectToggle($button, $spinner);
        },

        /**
         * 直接的なライセンス切り替え処理
         */
        performDirectToggle: function($button, $spinner) {
            // スピナーを表示
            $spinner.show();
            $button.prop('disabled', true);

            var requestData = {
                action: 'news_crawler_direct_toggle'
            };

            console.log('NewsCrawler License: Sending direct AJAX request with data:', requestData);

            $.ajax({
                url: window.location.href, // 現在のページにリクエスト
                type: 'POST',
                data: requestData,
                dataType: 'json',
                success: function(response) {
                    console.log('AJAX Response:', response);
                    if (response && response.success) {
                        // ボタンテキストを更新
                        var newText = response.data.new_status ? '開発用ライセンスを無効化' : '開発用ライセンスを有効化';
                        $button.text(newText);
                        
                        // 成功メッセージを表示
                        alert(response.data.message || '開発用ライセンスの状態が変更されました。');
                        
                        // ページをリロードしてステータスを更新
                        location.reload();
                    } else {
                        console.error('AJAX Error Response:', response);
                        var errorMessage = '不明なエラー';
                        if (response && response.data && response.data.message) {
                            errorMessage = response.data.message;
                        } else if (response && response.message) {
                            errorMessage = response.message;
                        }
                        alert('エラーが発生しました: ' + errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Request Error:', xhr, status, error);
                    console.error('Response Text:', xhr.responseText);
                    
                    var errorMessage = '通信エラーが発生しました: ' + error;
                    
                    // レスポンステキストからエラーメッセージを抽出
                    if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response && response.data && response.data.message) {
                                errorMessage = response.data.message;
                            } else if (response && response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            // JSON解析に失敗した場合は、プレーンテキストとして処理
                            console.log('Response is not JSON, treating as plain text');
                            if (xhr.responseText && xhr.responseText.trim() !== '') {
                                errorMessage = xhr.responseText.trim();
                            }
                        }
                    }
                    
                    alert(errorMessage);
                },
                complete: function() {
                    // スピナーを非表示
                    $spinner.hide();
                    $button.prop('disabled', false);
                }
            });
        },

        /**
         * AJAX接続テスト
         */
        testAjaxConnection: function(callback) {
            console.log('NewsCrawler License: Testing AJAX connection...');
            
            // まず最も基本的なテストを実行
            this.testSimpleAjax(function() {
                // 基本的なテストが成功した場合、より詳細なテストを実行
                NewsCrawlerLicenseManager.testDetailedAjax(callback);
            });
        },

        /**
         * 基本的なAJAXテスト
         */
        testSimpleAjax: function(callback) {
            console.log('NewsCrawler License: Testing simple AJAX...');
            
            $.ajax({
                url: news_crawler_license_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'news_crawler_simple_test'
                },
                dataType: 'json',
                success: function(response) {
                    console.log('NewsCrawler License: Simple test success:', response);
                    if (callback) callback();
                },
                error: function(xhr, status, error) {
                    console.error('NewsCrawler License: Simple test failed:', xhr, status, error);
                    console.error('Response text:', xhr.responseText);
                    alert('基本的なAJAXテストに失敗しました: ' + error);
                }
            });
        },

        /**
         * 詳細なAJAXテスト
         */
        testDetailedAjax: function(callback) {
            console.log('NewsCrawler License: Testing detailed AJAX...');
            
            $.ajax({
                url: news_crawler_license_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'news_crawler_test_ajax'
                },
                dataType: 'json',
                success: function(response) {
                    console.log('NewsCrawler License: Detailed test success:', response);
                    if (callback) callback();
                },
                error: function(xhr, status, error) {
                    console.error('NewsCrawler License: Detailed test failed:', xhr, status, error);
                    console.error('Response text:', xhr.responseText);
                    alert('詳細なAJAXテストに失敗しました: ' + error);
                }
            });
        },

        /**
         * 実際のライセンス切り替え処理
         */
        performToggleDevLicense: function($button, $spinner) {
            // スピナーを表示
            $spinner.show();
            $button.prop('disabled', true);

            var requestData = {
                action: 'news_crawler_toggle_dev_license',
                nonce: news_crawler_license_ajax.nonce
            };

            console.log('NewsCrawler License: Sending AJAX request with data:', requestData);

            $.ajax({
                url: news_crawler_license_ajax.ajaxurl,
                type: 'POST',
                data: requestData,
                dataType: 'json',
                success: function(response) {
                    console.log('AJAX Response:', response);
                    if (response && response.success) {
                        // ボタンテキストを更新
                        var newText = response.data.new_status ? '開発用ライセンスを無効化' : '開発用ライセンスを有効化';
                        $button.text(newText);
                        
                        // 成功メッセージを表示
                        alert('開発用ライセンスの状態が変更されました。');
                        
                        // ページをリロードしてステータスを更新
                        location.reload();
                    } else {
                        console.error('AJAX Error Response:', response);
                        var errorMessage = '不明なエラー';
                        if (response && response.data && response.data.message) {
                            errorMessage = response.data.message;
                        } else if (response && response.message) {
                            errorMessage = response.message;
                        }
                        alert('エラーが発生しました: ' + errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Request Error:', xhr, status, error);
                    console.error('Response Text:', xhr.responseText);
                    
                    var errorMessage = '通信エラーが発生しました: ' + error;
                    
                    // レスポンステキストからエラーメッセージを抽出
                    if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response && response.data && response.data.message) {
                                errorMessage = response.data.message;
                            } else if (response && response.message) {
                                errorMessage = response.message;
                            }
                        } catch (e) {
                            // JSON解析に失敗した場合は、プレーンテキストとして処理
                            console.log('Response is not JSON, treating as plain text');
                            if (xhr.responseText && xhr.responseText.trim() !== '') {
                                errorMessage = xhr.responseText.trim();
                            }
                        }
                    }
                    
                    alert(errorMessage);
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
