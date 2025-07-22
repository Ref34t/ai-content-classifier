/**
 * Admin JavaScript for AI Content Classifier
 */

jQuery(document).ready(function($) {
    'use strict';
    
    let currentResult = null;
    
    // Initialize
    init();
    
    function init() {
        bindEvents();
        loadTemplates();
    }
    
    function bindEvents() {
        // Template selection
        $('#template').on('change', function() {
            const selectedOption = $(this).find('option:selected');
            const prompt = selectedOption.data('prompt');
            
            if (prompt) {
                $('#prompt').val(prompt);
            }
        });
        
        // Generate content form
        $('#aicg-generate-form').on('submit', function(e) {
            e.preventDefault();
            generateContent();
        });
        
        // Action buttons
        $('#create-post-btn').on('click', createPost);
        $('#copy-content-btn').on('click', copyContent);
        $('#regenerate-btn').on('click', regenerateContent);
        
        // Auto-resize textarea
        $('#prompt').on('input', function() {
            autoResizeTextarea(this);
        });
    }
    
    function loadTemplates() {
        // Templates are loaded server-side, nothing to do here
    }
    
    function generateContent() {
        const form = $('#aicg-generate-form');
        const submitBtn = $('#generate-btn');
        const spinner = form.find('.spinner');
        
        // Validation
        const prompt = $('#prompt').val().trim();
        if (!prompt) {
            showNotice('Please enter a prompt', 'error');
            return;
        }
        
        // Show loading state
        submitBtn.prop('disabled', true);
        spinner.addClass('is-active');
        form.addClass('aicg-loading');
        
        // Prepare data
        const data = {
            action: 'aicg_generate_content',
            nonce: aicg.nonce,
            prompt: prompt,
            content_type: $('#content_type').val(),
            seo_enabled: $('#seo_enabled').is(':checked'),
            auto_save: $('#auto_save').is(':checked')
        };
        
        // Make AJAX request
        $.post(aicg.ajax_url, data)
            .done(function(response) {
                if (response.success) {
                    displayResult(response.data);
                    showNotice(aicg.strings.success, 'success');
                    
                    // Auto-save as draft if enabled
                    if (data.auto_save) {
                        autoSaveAsDraft(response.data);
                    }
                } else {
                    showNotice(response.data || aicg.strings.error, 'error');
                }
            })
            .fail(function() {
                showNotice(aicg.strings.error, 'error');
            })
            .always(function() {
                // Hide loading state
                submitBtn.prop('disabled', false);
                spinner.removeClass('is-active');
                form.removeClass('aicg-loading');
            });
    }
    
    function displayResult(data) {
        currentResult = data;
        
        // Display main content
        $('#result-content').html(formatContent(data));
        $('#aicg-results').show();
        
        // Display SEO data if available
        if (data.meta_description || data.keywords || data.excerpt) {
            $('#meta-description').text(data.meta_description || '');
            $('#keywords').text(Array.isArray(data.keywords) ? data.keywords.join(', ') : '');
            $('#excerpt').text(data.excerpt || '');
            $('#seo-results').show();
        } else {
            $('#seo-results').hide();
        }
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#aicg-results').offset().top - 50
        }, 500);
    }
    
    function formatContent(data) {
        let html = '';
        
        if (data.title) {
            html += '<h1>' + escapeHtml(data.title) + '</h1>';
        }
        
        if (data.content) {
            html += data.content;
        }
        
        return html;
    }
    
    function createPost() {
        if (!currentResult) {
            showNotice('No content to create post from', 'error');
            return;
        }
        
        const postData = {
            action: 'aicg_create_post',
            nonce: aicg.nonce,
            title: currentResult.title,
            content: currentResult.content,
            excerpt: currentResult.excerpt,
            meta_description: currentResult.meta_description,
            keywords: currentResult.keywords,
            content_type: $('#content_type').val(),
            status: 'draft'
        };
        
        $.post(aicg.ajax_url, postData)
            .done(function(response) {
                if (response.success) {
                    showNotice('Post created successfully! <a href="' + response.data.edit_url + '">Edit post</a>', 'success');
                } else {
                    showNotice(response.data || 'Failed to create post', 'error');
                }
            })
            .fail(function() {
                showNotice('Failed to create post', 'error');
            });
    }
    
    function copyContent() {
        if (!currentResult) {
            showNotice('No content to copy', 'error');
            return;
        }
        
        const content = currentResult.content;
        
        // Create temporary textarea
        const textarea = document.createElement('textarea');
        textarea.value = content;
        document.body.appendChild(textarea);
        
        // Select and copy
        textarea.select();
        textarea.setSelectionRange(0, 99999);
        
        try {
            document.execCommand('copy');
            showNotice('Content copied to clipboard', 'success');
        } catch (err) {
            showNotice('Failed to copy content', 'error');
        }
        
        // Clean up
        document.body.removeChild(textarea);
    }
    
    function regenerateContent() {
        generateContent();
    }
    
    function autoSaveAsDraft(data) {
        const postData = {
            action: 'aicg_create_post',
            nonce: aicg.nonce,
            title: data.title,
            content: data.content,
            excerpt: data.excerpt,
            meta_description: data.meta_description,
            keywords: data.keywords,
            content_type: $('#content_type').val(),
            status: 'draft'
        };
        
        $.post(aicg.ajax_url, postData)
            .done(function(response) {
                if (response.success) {
                    showNotice('Content auto-saved as draft! <a href="' + response.data.edit_url + '">Edit post</a>', 'success');
                } else {
                    showNotice('Auto-save failed: ' + (response.data || 'Unknown error'), 'error');
                }
            })
            .fail(function() {
                showNotice('Auto-save failed', 'error');
            });
    }
    
    function autoResizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = (textarea.scrollHeight) + 'px';
    }
    
    function showNotice(message, type) {
        const noticeClass = 'aicg-notice aicg-notice-' + type;
        const notice = $('<div class="' + noticeClass + '">' + message + '</div>');
        
        // Remove existing notices
        $('.aicg-notice').remove();
        
        // Add new notice
        $('.wrap h1').after(notice);
        
        // Auto-hide success notices
        if (type === 'success') {
            setTimeout(function() {
                notice.fadeOut(function() {
                    notice.remove();
                });
            }, 5000);
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Settings page specific JavaScript
    if ($('#aicg-settings').length) {
        initSettings();
    }
    
    // Temperature slider handler
    $('#aicg_temperature').on('input', function() {
        $('#temp_value').text(this.value);
    });
    
    function initSettings() {
        // API key validation
        $('#aicg_api_key').on('blur', function() {
            const apiKey = $(this).val().trim();
            if (apiKey && apiKey.length > 20) {
                validateApiKey(apiKey);
            }
        });
        
        // Model selection cost estimation
        $('#aicg_model').on('change', function() {
            updateCostEstimate();
        });
        
        $('#aicg_max_tokens').on('change', function() {
            updateCostEstimate();
        });
    }
    
    function validateApiKey(apiKey) {
        // Simple client-side validation
        if (!apiKey.startsWith('sk-')) {
            showNotice('API key should start with "sk-"', 'error');
            return;
        }
        
        if (apiKey.length < 40) {
            showNotice('API key appears to be too short', 'error');
            return;
        }
        
        // TODO: Add server-side validation
        showNotice('API key format looks correct', 'success');
    }
    
    function updateCostEstimate() {
        const model = $('#aicg_model').val();
        const maxTokens = parseInt($('#aicg_max_tokens').val());
        
        // Rough cost estimation
        const costPerToken = {
            'gpt-3.5-turbo': 0.000002,
            'gpt-3.5-turbo-16k': 0.000003,
            'gpt-4': 0.00006,
            'gpt-4-turbo-preview': 0.00004
        };
        
        const cost = (costPerToken[model] || 0) * maxTokens;
        const costFormatted = '$' + cost.toFixed(4);
        
        $('#cost-estimate').text(costFormatted);
    }
});