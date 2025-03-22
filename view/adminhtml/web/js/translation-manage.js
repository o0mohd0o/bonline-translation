define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'mage/translate'
], function ($, modal, $t) {
    'use strict';

    return function (config) {
        var paging = {
            currentPage: 1,
            totalPages: 1,
            totalItems: 0,
            limit: 20
        };

        var filters = {
            search: '',
            locale: '',
            storeId: 0
        };

        // Initialize translation edit modal
        var translationModal = modal({
            type: 'popup',
            responsive: true,
            innerScroll: true,
            title: $t('Edit Translation'),
            buttons: [{
                text: $t('Cancel'),
                class: 'action-secondary',
                click: function () {
                    this.closeModal();
                }
            }, {
                text: $t('Save'),
                class: 'action-primary',
                click: function () {
                    saveTranslation();
                }
            }]
        }, $('#translation-edit-modal'));
        
        // Initialize file import functionality
        $('#import-file').on('change', function() {
            var fileName = $(this).val();
            if (fileName) {
                // Extract just the filename (not the full path)
                var fileNameOnly = fileName.split('\\').pop();
                if (!fileNameOnly) {
                    fileNameOnly = fileName.split('/').pop();
                }
                
                // Show the selected filename and submit button
                $('#selected-file-name').text(fileNameOnly).show();
                $('#import-submit-btn').show();
            } else {
                // Hide the filename and submit button if no file selected
                $('#selected-file-name').hide();
                $('#import-submit-btn').hide();
            }
        });
        
        // Initialize export modal
        var exportModal = modal({
            type: 'popup',
            responsive: true,
            innerScroll: true,
            title: $t('Export Translations'),
            buttons: [{
                text: $t('Cancel'),
                class: 'action-secondary',
                click: function () {
                    this.closeModal();
                }
            }, {
                text: $t('Export'),
                class: 'action-primary',
                click: function () {
                    $('#translation-export-form').submit();
                }
            }]
        }, $('#translation-export-modal'));

        /**
         * Initialize components
         */
        $(document).ready(function () {
            // Load translations
            loadTranslations();

            // Add translation button
            $('#add-translation-btn').on('click', function () {
                // Reset form
                $('#translation-form')[0].reset();
                $('#translation-id').val(0);
                
                // Open modal
                translationModal.openModal();
            });
            
            // Import button is now just a visual element
            // The actual file selection happens through the hidden file input
            $('#import-btn').on('click', function(e) {
                e.preventDefault();
                $('#import-file').trigger('click');
            });
            
            // Export button
            $('#export-btn').on('click', function () {
                exportModal.openModal();
            });
            
            // Select all translations
            $('#select-all-translations').on('change', function () {
                var isChecked = $(this).prop('checked');
                $('.translation-select-checkbox').prop('checked', isChecked);
                updateMassActionButton();
            });
            
            // Handle mass action button
            $('#translation-mass-action-apply').on('click', function () {
                var selectedAction = $('#translation-mass-action').val();
                var selectedIds = getSelectedTranslationIds();
                
                if (!selectedAction || selectedIds.length === 0) {
                    return;
                }
                
                if (selectedAction === 'delete') {
                    confirmMassDelete(selectedIds);
                }
            });
            
            // Update mass action button when action changes
            $('#translation-mass-action').on('change', function () {
                updateMassActionButton();
            });

            // Search button
            $('#search-button').on('click', function () {
                filters.search = $('#translation-search').val();
                filters.locale = $('#translation-locale').val();
                filters.storeId = $('#translation-store').val();
                paging.currentPage = 1;
                loadTranslations();
            });

            // Pagination handlers
            $('#pager-prev').on('click', function () {
                if (paging.currentPage > 1) {
                    paging.currentPage--;
                    loadTranslations();
                }
            });

            $('#pager-next').on('click', function () {
                if (paging.currentPage < paging.totalPages) {
                    paging.currentPage++;
                    loadTranslations();
                }
            });

            $('#pager-current').on('change', function () {
                var page = parseInt($(this).val(), 10);
                if (page >= 1 && page <= paging.totalPages) {
                    paging.currentPage = page;
                    loadTranslations();
                } else {
                    $(this).val(paging.currentPage);
                }
            });
        });

        /**
         * Load translations with filters and pagination
         */
        function loadTranslations() {
            var $gridBody = $('#translation-grid-body');
            
            $gridBody.html('<tr class="data-row"><td class="data-grid-loading-msg" colspan="7">' +
                '<div class="data-grid-loading-msg-text">' + $t('Loading translations...') + '</div></td></tr>');

            $.ajax({
                url: config.loadUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    search: filters.search,
                    locale: filters.locale,
                    store_id: filters.storeId,
                    page: paging.currentPage,
                    limit: paging.limit
                },
                success: function (response) {
                    if (response.success) {
                        renderTranslations(response.translations);
                        updatePagination(response.total_count);
                    } else {
                        $gridBody.html('<tr class="data-row"><td colspan="6">' + 
                            response.message + '</td></tr>');
                    }
                },
                error: function () {
                    $gridBody.html('<tr class="data-row"><td colspan="6">' + 
                        $t('Error loading translations.') + '</td></tr>');
                }
            });
        }

        /**
         * Render translations grid
         */
        function renderTranslations(translations) {
            var $gridBody = $('#translation-grid-body');
            $gridBody.empty();

            if (translations.length === 0) {
                $gridBody.html('<tr class="data-row"><td colspan="7">' + 
                    $t('No translations found.') + '</td></tr>');
                return;
            }

            $.each(translations, function (index, translation) {
                var $row = $('<tr class="data-row"></tr>');
                
                // Add checkbox cell
                var checkboxCell = '<td class="data-grid-multicheck-cell">' +
                    '<div class="data-grid-checkbox-cell-inner">' +
                    '<input type="checkbox" class="admin__control-checkbox translation-select-checkbox" ' +
                    'data-id="' + translation.id + '" id="select-translation-' + translation.id + '">' +
                    '<label for="select-translation-' + translation.id + '"></label>' +
                    '</div></td>';
                $row.append(checkboxCell);
                
                $row.append('<td class="data-grid-data-cell">' + translation.id + '</td>');
                $row.append('<td class="data-grid-data-cell">' + 
                    '<div class="data-grid-cell-content">' + escapeHtml(translation.string) + '</div></td>');
                $row.append('<td class="data-grid-data-cell">' + 
                    '<div class="data-grid-cell-content">' + escapeHtml(translation.translation) + '</div></td>');
                $row.append('<td class="data-grid-data-cell">' + translation.locale + '</td>');
                $row.append('<td class="data-grid-data-cell">' + translation.store_name + '</td>');
                
                var actions = '<td class="data-grid-actions-cell">' +
                    '<a href="#" class="action-edit" data-id="' + translation.id + '">' + $t('Edit') + '</a> ' +
                    '<a href="#" class="action-delete" data-id="' + translation.id + '">' + $t('Delete') + '</a>' +
                    '</td>';
                $row.append(actions);
                
                $gridBody.append($row);
            });

            // Bind edit action
            $('.action-edit').on('click', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                editTranslation(id);
            });

            // Bind delete action
            $('.action-delete').on('click', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                deleteTranslation(id);
            });
        }

        /**
         * Update pagination controls
         */
        function updatePagination(totalCount) {
            paging.totalItems = totalCount;
            paging.totalPages = Math.ceil(totalCount / paging.limit);

            $('#translation-total-count').text(totalCount);
            $('#pager-pages').text(paging.totalPages);
            $('#pager-current').val(paging.currentPage);
            
            // Enable/disable pagination buttons
            $('#pager-prev').prop('disabled', paging.currentPage <= 1);
            $('#pager-next').prop('disabled', paging.currentPage >= paging.totalPages);
        }

        /**
         * Edit translation
         */
        function editTranslation(id) {
            // Find translation in the grid
            var $row = $('.action-edit[data-id="' + id + '"]').closest('tr');
            var cells = $row.find('td');
            
            // Set form values
            $('#translation-id').val(id);
            $('#translation-string').val($(cells[1]).find('.data-grid-cell-content').text());
            $('#translation-translation').val($(cells[2]).find('.data-grid-cell-content').text());
            $('#translation-form-locale').val($(cells[3]).text());
            
            // Open modal
            translationModal.openModal();
        }

        /**
         * Save translation
         */
        function saveTranslation() {
            var formData = {
                id: $('#translation-id').val(),
                string: $('#translation-string').val(),
                translation: $('#translation-translation').val(),
                locale: $('#translation-form-locale').val(),
                store_id: $('#translation-form-store').val()
            };

            if (!formData.string || !formData.locale) {
                alert($t('Original string and locale are required.'));
                return;
            }

            $.ajax({
                url: config.saveUrl,
                type: 'POST',
                dataType: 'json',
                data: formData,
                showLoader: true,
                success: function (response) {
                    if (response.success) {
                        translationModal.closeModal();
                        alert(response.message);
                        loadTranslations();
                    } else {
                        alert(response.message);
                    }
                },
                error: function () {
                    alert($t('Error saving translation.'));
                }
            });
        }

        /**
         * Delete translation
         */
        function deleteTranslation(id) {
            if (confirm($t('Are you sure you want to delete this translation?'))) {
                $.ajax({
                    url: config.deleteUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        id: id
                    },
                    showLoader: true,
                    success: function (response) {
                        if (response.success) {
                            alert(response.message);
                            loadTranslations();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function () {
                        alert($t('Error deleting translation.'));
                    }
                });
            }
        }

        /**
         * Escape HTML
         */
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        /**
         * Get selected translation IDs
         */
        function getSelectedTranslationIds() {
            var ids = [];
            $('.translation-select-checkbox:checked').each(function () {
                ids.push($(this).data('id'));
            });
            return ids;
        }
        
        /**
         * Update mass action button state
         */
        function updateMassActionButton() {
            var selectedAction = $('#translation-mass-action').val();
            var selectedIds = getSelectedTranslationIds();
            $('#translation-mass-action-apply').prop('disabled', !selectedAction || selectedIds.length === 0);
        }
        
        /**
         * Confirm mass delete action
         */
        function confirmMassDelete(ids) {
            if (confirm($t('Are you sure you want to delete ' + ids.length + ' translation(s)?'))) {
                executeMassDelete(ids);
            }
        }
        
        /**
         * Execute mass delete
         */
        function executeMassDelete(ids) {
            $.ajax({
                url: config.massDeleteUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    ids: ids
                },
                showLoader: true,
                success: function (response) {
                    if (response.success) {
                        alert(response.message);
                        loadTranslations(); // Reload the grid
                    } else {
                        alert(response.message);
                    }
                },
                error: function () {
                    alert($t('An error occurred while deleting translations.'));
                }
            });
        }
        
        // Add event handler for checkbox changes
        $(document).on('change', '.translation-select-checkbox', function () {
            // If a checkbox is unchecked, uncheck the "select all" checkbox
            if (!$(this).prop('checked')) {
                $('#select-all-translations').prop('checked', false);
            }
            // If all checkboxes are checked, check the "select all" checkbox
            else if ($('.translation-select-checkbox:not(:checked)').length === 0) {
                $('#select-all-translations').prop('checked', true);
            }
            
            updateMassActionButton();
        });
    };
});
