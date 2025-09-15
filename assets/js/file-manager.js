// File: assets/js/file-manager.js
/**
 * Handles all AJAX logic for the File Manager component in the admin dashboard.
 * @package Elevate_Client_Portal
 * @version 9.1.0 (Stable & Complete)
 */

jQuery(function ($) {
    const mainContentArea = $('#ecp-dashboard-main-content');
    
    // --- Helper Functions ---
    function executeBulkAction(userId, bulkAction, fileKeys, details = '') {
        showBlockingLoader('Applying action...');
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions',
            nonce: ecp_ajax.nonce,
            sub_action: 'bulk_actions',
            user_id: userId,
            file_keys: fileKeys,
            bulk_action: bulkAction,
            details: details
        }).done(response => {
            showAdminMessage(response.data.message, response.success ? 'success' : 'error');
            if (response.success) refreshFileManager(userId);
        }).fail(() => showAdminMessage('An unknown server error occurred.', 'error'))
          .always(() => hideBlockingLoader());
    }

    function fetchFilteredFiles(userId, folder) {
        const fileListBody = mainContentArea.find(`#ecp-file-manager-view-${userId} .file-list-body`);
        fileListBody.css('opacity', 0.5);
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions',
            nonce: ecp_ajax.nonce,
            sub_action: 'filter_files',
            user_id: userId,
            folder: folder
        }).done(response => {
            if (response.success) fileListBody.html(response.data);
            else fileListBody.html(`<tr><td colspan="6">${response.data.message || 'Error.'}</td></tr>`);
        }).fail(() => fileListBody.html('<tr><td colspan="6">Server error.</td></tr>'))
          .always(() => fileListBody.css('opacity', 1));
    }
    
    // --- Event Handlers (Delegated from the main content area) ---
    mainContentArea.off('.fileManager');

    mainContentArea.on('change.fileManager', '#ecp-admin-folder-filter', function() {
        fetchFilteredFiles($(this).data('userid'), $(this).val());
    });

    mainContentArea.on('change.fileManager', '.ecp-file-manager .ecp-select-all-files', function() {
        $(this).closest('table').find('.ecp-file-checkbox').prop('checked', $(this).prop('checked')).trigger('change');
    });

     mainContentArea.on('change.fileManager', '.ecp-file-manager .ecp-file-checkbox', function() {
        const table = $(this).closest('table');
        const allChecked = table.find('.ecp-file-checkbox:checked').length === table.find('.ecp-file-checkbox').length;
        table.find('.ecp-select-all-files').prop('checked', allChecked);
    });

    mainContentArea.on('click.fileManager', '.ecp-bulk-action-apply', function() {
        const container = $(this).closest('.ecp-bulk-actions-container');
        const fileManager = container.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const action = container.find('.ecp-bulk-action-select').val();
        const fileKeys = fileManager.find('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get();

        if (!action || fileKeys.length === 0) {
            showAdminMessage('Please select an action and at least one file.', 'error');
            return;
        }

        if (action === 'encrypt' || action === 'decrypt') {
            const password = prompt(`Please enter a password to ${action} the selected files:`);
            if (password) executeBulkAction(userId, action, fileKeys, password);
        } else if (action === 'delete') {
            if (confirm('Are you sure you want to delete the selected files? This cannot be undone.')) {
                executeBulkAction(userId, action, fileKeys);
            }
        } else if (action === 'move') {
            const modal = fileManager.find('#ecp-move-files-modal');
            modal.find('#ecp-modal-folder-select').html(fileManager.find('#ecp-upload-folder-select').html());
            modal.fadeIn(200);
        }
    });

    mainContentArea.on('click.fileManager', '.ecp-encrypt-file-btn', function() {
        const btn = $(this);
        const userId = btn.closest('.ecp-file-manager').attr('id').replace('ecp-file-manager-view-', '');
        const fileKey = btn.data('filekey');
        const password = prompt('Please enter a password to encrypt this file:');
        if (password) executeBulkAction(userId, 'encrypt', [fileKey], password);
    });

    mainContentArea.on('click.fileManager', '.ecp-delete-link', function(e) {
        e.preventDefault();
        const link = $(this);
        const userId = link.closest('.ecp-file-manager').attr('id').replace('ecp-file-manager-view-', '');
        const fileKey = link.data('filekey');
        if (confirm('Are you sure you want to delete this file? This cannot be undone.')) {
            showBlockingLoader('Deleting...');
            $.post(ecp_ajax.ajax_url, {
                action: 'ecp_file_manager_actions',
                nonce: ecp_ajax.nonce,
                sub_action: 'delete_file',
                user_id: userId,
                file_key: fileKey
            }).done(response => {
                showAdminMessage(response.data.message, response.success ? 'success' : 'error');
                if (response.success) refreshFileManager(userId);
            }).fail(() => showAdminMessage('An error occurred during deletion.', 'error'))
              .always(() => hideBlockingLoader());
        }
    });

    // Modal for 'move' bulk action
    mainContentArea.on('click.fileManager', '#ecp-modal-confirm-move-btn', function() {
        const modal = $(this).closest('.ecp-modal-overlay');
        const fileManager = modal.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const newFolder = modal.find('#ecp-modal-folder-select').val();
        const fileKeys = fileManager.find('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get();
        executeBulkAction(userId, 'move', fileKeys, newFolder);
        modal.fadeOut(200);
    });

    mainContentArea.on('click.fileManager', '#ecp-modal-cancel-btn', function() {
        $(this).closest('.ecp-modal-overlay').fadeOut(200);
    });
});

