define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (config) {
        $(document).ready(function () {
            // Clean cache button handler
            $('#clean-cache-btn').on('click', function () {
                var $button = $(this);
                var $result = $('#clean-cache-result');

                $button.prop('disabled', true);
                $button.text($t('Processing...'));
                $result.removeClass('message-success message-error').hide();

                $.ajax({
                    url: config.cleanCacheUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        form_key: window.FORM_KEY
                    },
                    showLoader: true,
                    success: function (response) {
                        if (response.success) {
                            $result.addClass('message-success').text(response.message).show();
                        } else {
                            $result.addClass('message-error').text(response.message).show();
                        }
                    },
                    error: function () {
                        $result.addClass('message-error').text($t('An error occurred while cleaning cache.')).show();
                    },
                    complete: function () {
                        $button.prop('disabled', false);
                        $button.text($t('Clean Cache and Static Files'));
                    }
                });
            });

            // Deploy static content button handler
            $('#deploy-static-btn').on('click', function () {
                var $button = $(this);
                var $result = $('#deploy-static-result');
                var $output = $('#deploy-static-output');
                var selectedLocales = [];

                // Get selected locales
                $('#locale-options input:checked').each(function () {
                    selectedLocales.push($(this).val());
                });

                if (selectedLocales.length === 0) {
                    $result.addClass('message-error').text($t('Please select at least one language.')).show();
                    return;
                }

                $button.prop('disabled', true);
                $button.text($t('Processing...'));
                $result.removeClass('message-success message-error').hide();
                $output.hide();

                $.ajax({
                    url: config.deployStaticUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        form_key: window.FORM_KEY,
                        languages: selectedLocales
                    },
                    showLoader: true,
                    success: function (response) {
                        if (response.success) {
                            $result.addClass('message-success').text(response.message).show();
                            if (response.output) {
                                $output.text(response.output).show();
                            }
                        } else {
                            $result.addClass('message-error').text(response.message).show();
                            if (response.output) {
                                $output.text(response.output).show();
                            }
                        }
                    },
                    error: function () {
                        $result.addClass('message-error').text($t('An error occurred while deploying static content.')).show();
                    },
                    complete: function () {
                        $button.prop('disabled', false);
                        $button.text($t('Deploy Static Content'));
                    }
                });
            });
            
            // Deploy translations button handler
            $('#deploy-translations-btn').on('click', function () {
                var $button = $(this);
                var $result = $('#deploy-translations-result');

                $button.prop('disabled', true);
                $button.text($t('Processing...'));
                $result.removeClass('message-success message-error').hide();

                $.ajax({
                    url: config.deployTranslationsUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        form_key: window.FORM_KEY,
                        isAjax: 1
                    },
                    showLoader: true,
                    success: function (response) {
                        if (response.success) {
                            $result.addClass('message-success').text(response.message).show();
                            
                            // Show details if available
                            if (response.details && response.details.length) {
                                var detailsHtml = '<ul class="details-list">';
                                $.each(response.details, function(index, detail) {
                                    detailsHtml += '<li>' + detail + '</li>';
                                });
                                detailsHtml += '</ul>';
                                $result.append(detailsHtml);
                            }
                        } else {
                            $result.addClass('message-error').text(response.message).show();
                        }
                    },
                    error: function () {
                        $result.addClass('message-error').text($t('An error occurred while deploying translations.')).show();
                    },
                    complete: function () {
                        $button.prop('disabled', false);
                        $button.text($t('Deploy Custom Translations'));
                    }
                });
            });
        });
    };
});
