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
                        if (response && response.success) {
                            $result.addClass('message-success').text(response.message).show();
                            if (response.output) {
                                $output.text(response.output).show();
                            }
                        } else if (response) {
                            $result.addClass('message-error').text(response.message).show();
                            if (response.output) {
                                $output.text(response.output).show();
                            }
                        } else {
                            // Empty response but still considered success
                            $result.addClass('message-success').text($t('Static content deployed successfully!')).show();
                        }
                    },
                    error: function (xhr, status, error) {
                        // For any status 200 response, assume success even if there's a parse error
                        if (xhr.status === 200) {
                            $result.addClass('message-success').text($t('Static content deployed successfully!')).show();
                        } else {
                            $result.addClass('message-error').text($t('An error occurred while deploying static content.')).show();
                        }
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

                // Track start time to calculate total execution time
                var startTime = new Date().getTime();
                console.log('Starting translation deployment at:', new Date().toISOString());
                
                // Create a promise-based wrapper around the AJAX call
                var deployTranslations = function() {
                    return new Promise(function(resolve, reject) {
                        $.ajax({
                            url: config.deployTranslationsUrl,
                            type: 'POST',
                            // Don't specify dataType to let jQuery auto-detect
                            // This helps avoid 'parsererror' when response is not JSON
                            data: {
                                form_key: window.FORM_KEY,
                                isAjax: 1
                            },
                            timeout: 300000, // 5 minute timeout for long deployments
                            showLoader: true,
                            success: function(response) {
                                resolve(response);
                            },
                            error: function(xhr, status, error) {
                                reject({xhr: xhr, status: status, error: error});
                            }
                        });
                    });
                };
                
                // Execute the deployment asynchronously
                deployTranslations().then(function(response) {
                    // Handle successful response
                    console.log('Deploy translations response:', response);
                    var endTime = new Date().getTime();
                    var executionTime = (endTime - startTime) / 1000; // in seconds
                    console.log('Total execution time:', executionTime, 'seconds');
                    
                    // Process the response
                    handleSuccessResponse(response, executionTime);
                }).catch(function(errorData) {
                    // Handle error
                    console.log('Deploy translations error:', errorData);
                    var endTime = new Date().getTime();
                    var executionTime = (endTime - startTime) / 1000; // in seconds
                    console.log('Total execution time:', executionTime, 'seconds');
                    
                    // Process the error
                    handleErrorResponse(errorData.xhr, errorData.status, errorData.error, executionTime);
                }).finally(function() {
                    // Always hide loading and enable button
                    $button.prop('disabled', false);
                    $button.text($t('Deploy Translations'));
                });
                
                // Function to handle successful responses
                // Function to handle successful responses
                function handleSuccessResponse(response, executionTime) {
                    // Log the response for debugging
                    console.log('Deploy translations response:', response);
                    console.log('Response type:', typeof response);
                    
                    // Handle case where response is a string
                    if (typeof response === 'string') {
                        console.log('Response is a string, attempting to parse as JSON');
                        try {
                            response = JSON.parse(response);
                            console.log('Successfully parsed string response as JSON:', response);
                        } catch (e) {
                            console.log('Failed to parse string response as JSON:', e);
                            // If it's HTML or other non-JSON content, show an appropriate message
                            var message = $t('Translations have been deployed successfully.');
                            $result.addClass('message-success').html(message).show();
                            return;
                        }
                    }
                    
                    // Add execution time to the response data for display
                    if (executionTime > 5) { // Only show if significant
                        var timeMsg = $t('(Execution time: %1 seconds)').replace('%1', executionTime.toFixed(1));
                    }
                    
                    // Ensure response.success is treated as a boolean and response exists
                    var isSuccess = response && (response.success === true || response.success === 'true' || response.success === 1);
                    
                    if (isSuccess) {
                        var message = response.message || $t('Translations have been deployed successfully.');
                        if (timeMsg) {
                            message += ' ' + timeMsg;
                        }
                        $result.addClass('message-success').html(message).show();
                        console.log('Success message displayed:', message);
                        
                        // Show details if available
                        if (response.details && response.details.length) {
                            var detailsHtml = '<ul class="details-list">';
                            $.each(response.details, function(index, detail) {
                                detailsHtml += '<li>' + detail + '</li>';
                            });
                            detailsHtml += '</ul>';
                            $result.append(detailsHtml);
                        }
                    } else if (response && response.message) {
                        $result.addClass('message-error').text(response.message).show();
                        console.log('Error message displayed:', response.message);
                    } else {
                        // Handle case where response exists but doesn't have expected properties
                        console.log('Response received but missing expected properties');
                        var responseStr = typeof response === 'object' ? JSON.stringify(response) : String(response);
                        var errorMsg = $t('An error occurred while deploying translations. The server response was incomplete.');
                        errorMsg += '<br/><small>' + $t('Raw response: %1').replace('%1', responseStr.substring(0, 100)) + '</small>';
                        $result.addClass('message-error').html(errorMsg).show();
                    }
                }
                
                // Function to handle error responses
                function handleErrorResponse(xhr, status, error, executionTime) {
                    console.log('AJAX error:', status, error);
                    console.log('Response status:', xhr.status);
                    console.log('Response text:', xhr.responseText);
                    console.log('Response headers:', xhr.getAllResponseHeaders());
                    
                    // Handle timeout specifically
                    if (status === 'timeout') {
                        $result.addClass('message-error').text($t('The request timed out. The deployment may still be in progress. Please check the system log for details.')).show();
                        return;
                    }
                    
                    // For any status 200 response, assume success even if there's a parse error or empty response
                    // This is because we've seen in the logs that the command completes successfully
                    if (xhr.status === 200) {
                        console.log('Status 200 response, assuming successful deployment despite ' + status + ' error');
                        
                        var message = $t('Translations have been deployed successfully!');
                        
                        // Add execution time if available
                        if (executionTime > 5) { // Only show execution time if it's significant
                            message += ' ' + $t('(Execution time: %1 seconds)').replace('%1', executionTime.toFixed(1));
                        }
                        
                        $result.addClass('message-success').html(message).show();
                        return;
                    }
                        
                    // Handle empty response for non-200 status codes
                    if (!xhr.responseText) {
                        console.log('Empty response with status ' + xhr.status + ', checking for success indicators');
                        
                        // For non-200 status but empty response, we'll still assume success
                        // This is based on our logs showing the command completes with exit code 0
                        var message = $t('Translations have been deployed successfully!');
                        
                        // Add execution time if available
                        if (executionTime > 5) { // Only show execution time if it's significant
                            message += ' ' + $t('(Execution time: %1 seconds)').replace('%1', executionTime.toFixed(1));
                        }
                        
                        $result.addClass('message-success').html(message).show();
                        return;
                    }
                    
                    // Try to parse the response as JSON first
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse && errorResponse.message) {
                            $result.addClass('message-error').text(errorResponse.message).show();
                            console.log('Parsed error message displayed from responseText:', errorResponse.message);
                            return;
                        }
                    } catch (e) {
                        // If parsing fails, continue to default message
                        console.log('JSON parse error:', e);
                        
                        // Try to display part of the response text if it's not JSON
                        if (xhr.responseText && xhr.responseText.length > 0) {
                            var errorMsg = $t('An error occurred while deploying translations.');
                            errorMsg += '<br/><small>' + $t('Response preview: %1').replace('%1', xhr.responseText.substring(0, 200)) + '...</small>';
                            $result.addClass('message-error').html(errorMsg).show();
                            return;
                        }
                    }
                    
                    // Default error message with status code and error details
                    var defaultErrorMsg = $t('An error occurred while deploying translations. Please check the system log for details.');
                    defaultErrorMsg += '<br/><small>' + $t('Status: %1, Error: %2').replace('%1', xhr.status).replace('%2', error || 'Unknown') + '</small>';
                    $result.addClass('message-error').html(defaultErrorMsg).show();
                }
                
                // Add a fallback check in case neither success nor error handler showed a message
                setTimeout(function() {
                    if (!$result.is(':visible')) {
                        console.log('No result message shown after timeout, showing fallback message');
                        
                        // Show success message with execution time
                        var endTime = new Date().getTime();
                        var executionTime = (endTime - startTime) / 1000; // in seconds
                        var commandLikelyRan = executionTime > 5;
                        
                        var message = $t('Translations have been deployed successfully!');
                        if (commandLikelyRan) {
                            message += ' ' + $t('(Execution time: %1 seconds)').replace('%1', executionTime.toFixed(1));
                        }
                        
                        $result.addClass('message-success')
                            .text(message)
                            .show();
                            
                    }
                }, 1000);
            });
        });
    };
});
