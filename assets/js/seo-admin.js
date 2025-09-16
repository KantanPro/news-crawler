jQuery(document).ready(function($) {
    'use strict';
    
    // SEO設定の動的表示制御
    function toggleSeoFields() {
        // キーワード最適化機能の制御
        var keywordOptimization = $('input[name="news_crawler_seo_settings[keyword_optimization_enabled]"]');
        var targetKeywords = $('textarea[name="news_crawler_seo_settings[target_keywords]"]').closest('tr');
        
        function toggleKeywordFields() {
            if (keywordOptimization.is(':checked')) {
                targetKeywords.show();
            } else {
                targetKeywords.hide();
            }
        }
        
        keywordOptimization.on('change', toggleKeywordFields);
        toggleKeywordFields();
        
        // メタディスクリプション自動生成の制御
        var autoMetaDesc = $('input[name="news_crawler_seo_settings[auto_meta_description]"]');
        var metaDescLength = $('input[name="news_crawler_seo_settings[meta_description_length]"]').closest('tr');
        
        function toggleMetaDescLength() {
            if (autoMetaDesc.is(':checked')) {
                metaDescLength.show();
            } else {
                metaDescLength.hide();
            }
        }
        
        autoMetaDesc.on('change', toggleMetaDescLength);
        toggleMetaDescLength();
        
        // メタキーワード自動生成の制御
        var autoMetaKeywords = $('input[name="news_crawler_seo_settings[auto_meta_keywords]"]');
        var metaKeywordsCount = $('input[name="news_crawler_seo_settings[meta_keywords_count]"]').closest('tr');
        
        function toggleMetaKeywordsCount() {
            if (autoMetaKeywords.is(':checked')) {
                metaKeywordsCount.show();
            } else {
                metaKeywordsCount.hide();
            }
        }
        
        autoMetaKeywords.on('change', toggleMetaKeywordsCount);
        toggleMetaKeywordsCount();
        
        // OGPタグ自動生成の制御
        var autoOgp = $('input[name="news_crawler_seo_settings[auto_ogp_tags]"]');
        
        // OGPタグ自動生成が有効な場合の処理（必要に応じて追加）
        autoOgp.on('change', function() {
            if ($(this).is(':checked')) {
                console.log('OGPタグ自動生成が有効になりました');
            } else {
                console.log('OGPタグ自動生成が無効になりました');
            }
        });
        
        // 構造化データ自動生成の制御
        var autoStructuredData = $('input[name="news_crawler_seo_settings[auto_structured_data]"]');
        var structuredDataType = $('select[name="news_crawler_seo_settings[structured_data_type]"]').closest('tr');
        
        function toggleStructuredDataType() {
            if (autoStructuredData.is(':checked')) {
                structuredDataType.show();
            } else {
                structuredDataType.hide();
            }
        }
        
        autoStructuredData.on('change', toggleStructuredDataType);
        toggleStructuredDataType();
        
        // タイトル最適化の制御
        var autoTitleOpt = $('input[name="news_crawler_seo_settings[auto_title_optimization]"]');
        var titleMaxLength = $('input[name="news_crawler_seo_settings[title_max_length]"]').closest('tr');
        var titleIncludeSiteName = $('input[name="news_crawler_seo_settings[title_include_site_name]"]').closest('tr');
        
        function toggleTitleFields() {
            if (autoTitleOpt.is(':checked')) {
                titleMaxLength.show();
                titleIncludeSiteName.show();
            } else {
                titleMaxLength.hide();
                titleIncludeSiteName.hide();
            }
        }
        
        autoTitleOpt.on('change', toggleTitleFields);
        toggleTitleFields();
    }
    
    // 初期化
    toggleSeoFields();
    
    // 数値入力の検証
    $('input[type="number"]').on('blur', function() {
        var $this = $(this);
        var min = parseInt($this.attr('min'));
        var max = parseInt($this.attr('max'));
        var value = parseInt($this.val());
        
        if (isNaN(value) || value < min) {
            $this.val(min);
        } else if (value > max) {
            $this.val(max);
        }
    });
    
    // プレビュー機能（将来の拡張用）
    function initSeoPreview() {
        // メタディスクリプションのプレビュー
        var metaDescInput = $('input[name="news_crawler_seo_settings[meta_description_length]"]');
        var metaDescLength = parseInt(metaDescInput.val()) || 160;
        
        metaDescInput.on('input', function() {
            var length = parseInt($(this).val()) || 160;
            // プレビュー表示の更新（将来実装）
            console.log('Meta description length updated to:', length);
        });
        
        // タイトル長のプレビュー
        var titleLengthInput = $('input[name="news_crawler_seo_settings[title_max_length]"]');
        var titleLength = parseInt(titleLengthInput.val()) || 60;
        
        titleLengthInput.on('input', function() {
            var length = parseInt($(this).val()) || 60;
            // プレビュー表示の更新（将来実装）
            console.log('Title max length updated to:', length);
        });
    }
    
    // プレビュー機能を初期化
    initSeoPreview();
    
    // 設定の保存時の検証
    $('form').on('submit', function() {
        var isValid = true;
        var errors = [];
        
        // 数値フィールドの検証
        $('input[type="number"]').each(function() {
            var $this = $(this);
            var value = parseInt($this.val());
            var min = parseInt($this.attr('min'));
            var max = parseInt($this.attr('max'));
            
            if (isNaN(value) || value < min || value > max) {
                isValid = false;
                errors.push($this.attr('name') + ' の値が無効です。');
            }
        });
        
        if (!isValid) {
            alert('設定にエラーがあります:\n' + errors.join('\n'));
            return false;
        }
        
        return true;
    });
    
    // ヘルプツールチップ（将来の拡張用）
    function initHelpTooltips() {
        $('.seo-help').each(function() {
            var $this = $(this);
            var helpText = $this.data('help');
            
            if (helpText) {
                $this.attr('title', helpText);
            }
        });
    }
    
    // ヘルプツールチップを初期化
    initHelpTooltips();
});
