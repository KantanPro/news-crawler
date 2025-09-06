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

            // ライセンステスト
            $(document).on('click', '#test-license-btn', function(e) {
                e.preventDefault();
                NewsCrawlerLicenseManager.testLicense();
            });

            // ライセンスクリア
            $(document).on('click', 'input[name="clear_license"]', function(e) {
                e.preventDefault();
                NewsCrawlerLicenseManager.clearLicense();
            });

            // ライセンスキーフィールドのクリアボタン
            $(document).on('click', '#clear-license-field', function(e) {
                e.preventDefault();
                $('#news_crawler_license_key').val('').focus();
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
            
            // 正規のWordPress AJAX経路で実行（nonce付き・admin-ajax.php）
            this.performToggleDevLicense($button, $spinner);
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
                this.showError('ライセンスキーを入力してください。');
                $licenseKey.focus();
                return;
            }

            // ライセンスキーの基本形式チェック（NCRL-で始まるかどうかのみ）
            if (!licenseKey.startsWith('NCRL-')) {
                this.showError('ライセンスキーはNCRL-で始まる必要があります。');
                $licenseKey.focus();
                return;
            }

            // ボタンを無効化
            $submitButton.prop('disabled', true).val('認証中...');

            // ローディング表示
            this.showLoading('ライセンスを認証中...');

            $.ajax({
                url: news_crawler_license_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'news_crawler_verify_license',
                    license_key: licenseKey,
                    nonce: news_crawler_license_ajax.nonce,
                    _ajax_nonce: news_crawler_license_ajax.nonce
                },
                dataType: 'json',
                timeout: 60000, // 60秒のタイムアウト
                success: function(response) {
                    console.log('License verification response:', response);
                    
                    // 文字列で返った場合のフォールバック解析
                    try {
                        if (typeof response === 'string') {
                            response = JSON.parse(response);
                        }
                    } catch (e) {
                        console.error('Response JSON parse fallback failed:', e);
                    }

                    if (response && response.success) {
                        NewsCrawlerLicenseManager.showSuccess('ライセンスが正常に認証されました。');
                        // ページをリロードしてステータスを更新
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        var errorMessage = 'ライセンスの認証に失敗しました。';
                        
                        var data = response ? (response.data || response) : null;
                        if (data && data.message) {
                            errorMessage = data.message;
                        } else if (response && response.message) {
                            errorMessage = response.data.message;
                        }
                        
                        // レスポンス全体をコンソールに出力
                        console.error('License verification failed - Full response:', response);
                        
                        // デバッグ情報がある場合は表示
                        if (data && data.debug_info) {
                            console.log('Debug info:', data.debug_info);
                            errorMessage += '\n\n【デバッグ情報】\n' + JSON.stringify(data.debug_info, null, 2);
                        }
                        
                        // エラーコードがある場合は追加
                        if (data && data.error_code) {
                            errorMessage += '\nエラーコード: ' + data.error_code;
                        }
                        
                        // レスポンスデータ全体を追加（デバッグ用）
                        if (data) {
                            errorMessage += '\n\n【レスポンスデータ】\n' + JSON.stringify(data, null, 2);
                        }
                        
                        // 常に詳細なモーダルで表示
                        NewsCrawlerLicenseManager.showDetailedError(errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('License verification error:', xhr, status, error);
                    console.error('Response text:', xhr.responseText);
                    
                    var errorMessage = '通信エラーが発生しました。\n\n';
                    errorMessage += '【エラー詳細】\n';
                    errorMessage += 'ステータス: ' + status + '\n';
                    errorMessage += 'エラー: ' + error + '\n';
                    errorMessage += 'HTTPステータス: ' + xhr.status + '\n';
                    errorMessage += 'ステータステキスト: ' + xhr.statusText + '\n';
                    
                    // 詳細なエラー情報を取得
                    if (xhr.responseText) {
                        errorMessage += '\n【レスポンス内容】\n';
                        try {
                            var response = JSON.parse(xhr.responseText);
                            errorMessage += JSON.stringify(response, null, 2);
                            
                            if (response && response.data && response.data.message) {
                                errorMessage = response.data.message + '\n\n' + errorMessage;
                            } else if (response && response.message) {
                                errorMessage = response.message + '\n\n' + errorMessage;
                            }
                        } catch (e) {
                            // JSON解析に失敗した場合
                            errorMessage += xhr.responseText;
                            
                            if (xhr.status === 0) {
                                errorMessage = 'ネットワーク接続エラーが発生しました。インターネット接続を確認してください。\n\n' + errorMessage;
                            } else if (xhr.status === 404) {
                                errorMessage = 'ライセンスサーバーが見つかりません。しばらく時間をおいてから再試行してください。\n\n' + errorMessage;
                            } else if (xhr.status === 500) {
                                errorMessage = 'ライセンスサーバーでエラーが発生しました。しばらく時間をおいてから再試行してください。\n\n' + errorMessage;
                            } else if (xhr.status === 503) {
                                errorMessage = 'ライセンスサーバーが一時的に利用できません。しばらく時間をおいてから再試行してください。\n\n' + errorMessage;
                            } else {
                                errorMessage = 'サーバーエラーが発生しました。(' + xhr.status + ')\n\n' + errorMessage;
                            }
                        }
                    }
                    
                    // 常に詳細なモーダルで表示
                    NewsCrawlerLicenseManager.showDetailedError(errorMessage);
                },
                complete: function() {
                    // ボタンを有効化
                    $submitButton.prop('disabled', false).val('ライセンスを認証');
                    // ローディングを非表示
                    NewsCrawlerLicenseManager.hideLoading();
                }
            });
        },

        /**
         * ライセンステスト（詳細デバッグ）
         */
        testLicense: function() {
            var $form = $('#news-crawler-license-form');
            var $licenseKey = $('#news_crawler_license_key');
            var licenseKey = $licenseKey.val().trim();
            
            if (!licenseKey) {
                this.showError('ライセンスキーを入力してください。');
                $licenseKey.focus();
                return;
            }

            // ローディング表示
            this.showLoading('ライセンスをテスト中...');

            $.ajax({
                url: news_crawler_license_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'news_crawler_test_license',
                    license_key: licenseKey,
                    nonce: news_crawler_license_ajax.nonce
                },
                timeout: 60000,
                success: function(response) {
                    console.log('License test response:', response);
                    
                    if (response.success) {
                        var testData = response.data;
                        var message = 'テスト結果: ' + testData.message;
                        
                        if (testData.error_code) {
                            message += '\nエラーコード: ' + testData.error_code;
                        }
                        
                        if (testData.response_code) {
                            message += '\nHTTPステータス: ' + testData.response_code;
                        }
                        
                        if (testData.parsed_data) {
                            message += '\nサーバーレスポンス: ' + JSON.stringify(testData.parsed_data, null, 2);
                        }
                        
                        NewsCrawlerLicenseManager.showTestResult(message, testData.success);
                    } else {
                        NewsCrawlerLicenseManager.showError('テスト実行に失敗しました: ' + (response.data ? response.data.message : 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('License test error:', xhr, status, error);
                    NewsCrawlerLicenseManager.showError('テスト実行中にエラーが発生しました: ' + error);
                },
                complete: function() {
                    NewsCrawlerLicenseManager.hideLoading();
                }
            });
        },

        /**
         * テスト結果の表示
         */
        showTestResult: function(message, isSuccess) {
            var $resultDiv = $('#test-result');
            if ($resultDiv.length === 0) {
                $resultDiv = $('<div id="test-result" style="margin: 10px 0; padding: 10px; border-radius: 4px;"></div>');
                $('#news-crawler-license-form').after($resultDiv);
            }
            
            $resultDiv.removeClass('notice-success notice-error')
                     .addClass(isSuccess ? 'notice-success' : 'notice-error')
                     .html('<pre style="white-space: pre-wrap; font-family: monospace; font-size: 12px;">' + message + '</pre>');
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
         * ライセンスのクリア
         */
        clearLicense: function() {
            if (!confirm('ライセンス情報をクリアしますか？この操作は元に戻せません。')) {
                return;
            }

            var $button = $('input[name="clear_license"]');
            
            // ボタンを無効化
            $button.prop('disabled', true).val('クリア中...');

            $.ajax({
                url: news_crawler_license_ajax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'news_crawler_clear_license',
                    nonce: news_crawler_license_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        this.showSuccess('ライセンス情報がクリアされました。');
                        // ページをリロードしてステータスを更新
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        this.showError('ライセンスのクリアに失敗しました: ' + (response.data ? response.data.message : '不明なエラー'));
                    }
                }.bind(this),
                error: function() {
                    this.showError('通信エラーが発生しました。');
                }.bind(this),
                complete: function() {
                    // ボタンを有効化
                    $button.prop('disabled', false).val('ライセンスをクリア');
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
                // 改行が含まれている場合は詳細なモーダルで表示
                if (message.includes('\n') || message.length > 100) {
                    this.showDetailedError(message);
                } else {
                    alert(message);
                }
            }
        },

        /**
         * 詳細なエラーメッセージの表示
         */
        showDetailedError: function(message) {
            // 既存のモーダルを削除
            $('.news-crawler-error-modal').remove();
            
            var modalHtml = '<div class="news-crawler-error-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">' +
                '<div style="background: white; border-radius: 5px; padding: 20px; max-width: 80%; max-height: 80%; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                '<h3 style="margin-top: 0; color: #d63638; display: flex; align-items: center;">' +
                '<span class="dashicons dashicons-warning" style="margin-right: 10px;"></span>' +
                'ライセンス認証エラー' +
                '</h3>' +
                '<div style="margin: 15px 0; padding: 15px; background: #f6f7f7; border-radius: 3px; font-family: monospace; font-size: 13px; white-space: pre-wrap; max-height: 400px; overflow-y: auto;">' +
                message +
                '</div>' +
                '<div style="text-align: right; margin-top: 15px;">' +
                '<button type="button" class="button button-primary" onclick="jQuery(\'.news-crawler-error-modal\').remove();">OK</button>' +
                '</div>' +
                '</div>' +
                '</div>';
            
            $('body').append(modalHtml);
        },

        /**
         * 成功メッセージの表示
         */
        showSuccess: function(message) {
            // 成功メッセージを表示する処理
            if (typeof message === 'string' && message.length > 0) {
                this.showNotification(message, 'success');
            }
        },

        /**
         * ライセンスキーの基本形式を検証（サーバーサイドで詳細検証）
         */
        validateLicenseKeyFormat: function(licenseKey) {
            // 基本的な形式チェック（NCRL-で始まるかどうかのみ）
            // 詳細な形式検証はサーバーサイドで実行
            return licenseKey && licenseKey.startsWith('NCRL-');
        },

        /**
         * ローディング表示
         */
        showLoading: function(message) {
            // 既存のローディングを削除
            this.hideLoading();
            
            var loadingHtml = '<div id="news-crawler-loading" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 5px; z-index: 9999; text-align: center;">' +
                '<div class="spinner is-active" style="float: none; margin: 0 auto 10px;"></div>' +
                '<div>' + (message || '処理中...') + '</div>' +
                '</div>';
            
            $('body').append(loadingHtml);
        },

        /**
         * ローディング非表示
         */
        hideLoading: function() {
            $('#news-crawler-loading').remove();
        },

        /**
         * 通知メッセージの表示
         */
        showNotification: function(message, type) {
            // 既存の通知を削除
            $('.news-crawler-notification').remove();
            
            var typeClass = type === 'success' ? 'notice-success' : 'notice-error';
            var icon = type === 'success' ? 'dashicons-yes-alt' : 'dashicons-warning';
            
            var notificationHtml = '<div class="news-crawler-notification notice ' + typeClass + ' is-dismissible" style="position: fixed; top: 32px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">' +
                '<p style="margin: 10px 0;">' +
                '<span class="dashicons ' + icon + '" style="margin-right: 5px;"></span>' +
                message +
                '</p>' +
                '<button type="button" class="notice-dismiss" style="position: absolute; top: 0; right: 1px; border: none; padding: 9px; background: 0 0; color: #787c82; cursor: pointer;">' +
                '<span class="screen-reader-text">この通知を非表示にする</span>' +
                '</button>' +
                '</div>';
            
            $('body').append(notificationHtml);
            
            // 自動で非表示にする（5秒後）
            setTimeout(function() {
                $('.news-crawler-notification').fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // 手動で非表示にする
            $('.news-crawler-notification .notice-dismiss').on('click', function() {
                $(this).parent().fadeOut(300, function() {
                    $(this).remove();
                });
            });
        }
    };

    // DOM読み込み完了後に初期化
    $(document).ready(function() {
        NewsCrawlerLicenseManager.init();
    });

})(jQuery);
