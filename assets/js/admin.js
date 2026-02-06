/**
 * AI Product Description Generator - Admin JavaScript
 */
(function($) {
    'use strict';

    var AIPDG = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            $(document).on('click', '#aipdg_generate_btn', this.handleGenerate);
        },

        /**
         * Handle generate button click
         */
        handleGenerate: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $status = $('#aipdg_status');
            var productId = $btn.data('product-id');
            var keywords = $('#aipdg_keywords').val();
            var generateShort = $('#aipdg_generate_short').is(':checked');
            var generateLong = $('#aipdg_generate_long').is(':checked');

            if (!productId) {
                AIPDG.showStatus($status, 'error', aipdg_params.i18n.error);
                return;
            }

            if (!generateShort && !generateLong) {
                AIPDG.showStatus($status, 'error', 'Please select at least one description type.');
                return;
            }

            // Disable button and show loading
            $btn.prop('disabled', true);
            $btn.addClass('aipdg-loading');
            $btn.find('.dashicons').removeClass('dashicons-welcome-write-blog').addClass('dashicons-update');
            $btn.find('span:not(.dashicons)').text(aipdg_params.i18n.generating);

            AIPDG.showStatus($status, 'loading', aipdg_params.i18n.generating);

            // Make AJAX request
            $.ajax({
                url: aipdg_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'aipdg_generate',
                    nonce: aipdg_params.nonce,
                    product_id: productId,
                    keywords: keywords,
                    generate_short: generateShort ? 1 : 0,
                    generate_long: generateLong ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        var description = response.data.description;

                        // Update description fields
                        if (generateLong) {
                            AIPDG.setLongDescription(description);
                        }

                        if (generateShort) {
                            AIPDG.setShortDescription(description);
                        }

                        var message = aipdg_params.i18n.success;
                        if (response.data.tokens_used) {
                            message += ' (Tokens: ' + response.data.tokens_used + ')';
                        }

                        // Update usage display if present
                        if (response.data.usage) {
                            AIPDG.updateUsageDisplay(response.data.usage);
                        }

                        AIPDG.showStatus($status, 'success', message);
                    } else {
                        AIPDG.showStatus($status, 'error', response.data.message || aipdg_params.i18n.error);
                    }
                },
                error: function(xhr, status, error) {
                    AIPDG.showStatus($status, 'error', aipdg_params.i18n.error);
                },
                complete: function() {
                    // Reset button
                    $btn.prop('disabled', false);
                    $btn.removeClass('aipdg-loading');
                    $btn.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-welcome-write-blog');
                    $btn.find('span:not(.dashicons)').text(aipdg_params.i18n.generate);
                }
            });
        },

        /**
         * Set long description
         */
        setLongDescription: function(content) {
            // Try Gutenberg editor first
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch('core/editor')) {
                try {
                    var blocks = wp.blocks.parse('<!-- wp:paragraph -->\n<p>' + content + '</p>\n<!-- /wp:paragraph -->');
                    wp.data.dispatch('core/block-editor').resetBlocks(blocks);
                    return;
                } catch (e) {
                    console.log('Gutenberg update failed, trying classic editor');
                }
            }

            // Classic editor - TinyMCE
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                tinymce.get('content').setContent(content);
            }

            // Textarea fallback
            $('#content').val(content);
        },

        /**
         * Set short description
         */
        setShortDescription: function(content) {
            // Limit to first 200 chars for short description
            var shortContent = content.length > 200 ? content.substring(0, 200) + '...' : content;

            // Try Gutenberg
            if (typeof wp !== 'undefined' && wp.data) {
                try {
                    wp.data.dispatch('core/editor').editPost({ excerpt: shortContent });
                    return;
                } catch (e) {
                    console.log('Gutenberg excerpt update failed');
                }
            }

            // Classic editor
            $('#excerpt').val(shortContent);
        },

        /**
         * Show status message
         */
        showStatus: function($el, type, message) {
            $el.removeClass('success error loading').addClass(type).html(message).show();

            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $el.fadeOut();
                }, 5000);
            }
        },

        /**
         * Update usage display
         */
        updateUsageDisplay: function(usage) {
            // Update usage text
            var $usageInfo = $('.aipdg-usage-info small');
            if ($usageInfo.length) {
                var usageText = aipdg_params.i18n.usage_text 
                    ? aipdg_params.i18n.usage_text.replace('%1$d', usage.current).replace('%2$d', usage.limit)
                    : '使用量：本月 ' + usage.current + ' / ' + usage.limit;
                $usageInfo.text(usageText);
            }

            // Calculate remaining
            var remaining = Math.max(0, usage.limit - usage.current);

            // Update warning text
            var $warning = $('.aipdg-upgrade-box .aipdg-warning');
            if (remaining <= 10) {
                var warningText = aipdg_params.i18n.generations_left 
                    ? aipdg_params.i18n.generations_left.replace('%d', remaining)
                    : '本月仅剩 ' + remaining + ' 次生成机会！';
                if ($warning.length) {
                    // Update existing warning
                    $warning.text(warningText);
                } else {
                    // Create new warning if not exists
                    $('.aipdg-upgrade-header').after(
                        '<p class="aipdg-warning">' + warningText + '</p>'
                    );
                }
            } else {
                // Remove warning if remaining > 10
                $warning.remove();
            }

            // Disable generate button if limit reached
            if (remaining <= 0) {
                $('#aipdg_generate_btn').prop('disabled', true).text(aipdg_params.i18n.limit_reached);
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AIPDG.init();
    });

})(jQuery);