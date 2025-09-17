// File: elevate-client-portal/assets/js/ecp-admin-dashboard.js
/**
 * Handles all AJAX logic for the administrator dashboard, including user management,
 * file/folder operations, and the file uploader. This is the single, consolidated
 * script for the entire admin dashboard view.
 *
 * @package Elevate_Client_Portal
 * @version 44.0.0 (Final Audit & Refactor)
 */

jQuery(function ($) {
    const adminDashboard = $('.ecp-admin-dashboard');
    if (!adminDashboard.length) return;

    // --- GLOBALS & HELPERS ---
    const mainContentArea = $('#ecp-dashboard-main-content');
    const messageBox = $('#ecp-admin-messages');
    let fileQueue = [];
    let isUploading = false;
    
    if (!adminDashboard.find('.ecp-loader-overlay').length) {
        adminDashboard.append('<div class="ecp-loader-overlay" style="display:none;"><div class="ecp-spinner"></div><p>Processing...</p></div>');
    }
    const loaderOverlay = adminDashboard.find('.ecp-loader-overlay');

    window.showAdminMessage = function(message, type = 'success') {
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        messageBox.html(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`).fadeIn();
        setTimeout(() => messageBox.fadeOut(500, function() { $(this).html('').show(); }), 5000);
    };

    const showBlockingLoader = (message = 'Processing...') => loaderOverlay.find('p').text(message).end().css('display', 'flex');
    const hideBlockingLoader = () => loaderOverlay.fadeOut(200);

    function loadView(viewHtml) {
        mainContentArea.html(viewHtml);
    }
    
    function loadNamedView(viewName, data = {}) {
        mainContentArea.html('<div id="ecp-loader" style="display:block; position: relative; top: 50px;"><div class="ecp-spinner"></div></div>');
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_get_view',
            nonce: ecp_ajax.nonces.viewNonce,
            view: viewName,
            ...data
        }).done(response => {
            if (response.success) loadView(response.data);
            else {
                showAdminMessage(response.data.message || 'Error loading view.', 'error');
                loadNamedView('user_list');
            }
        }).fail(() => {
            showAdminMessage('An unexpected server error occurred.', 'error');
            loadNamedView('user_list');
        });
    }

    const refreshFileManager = (userId) => {
        const id = parseInt(userId, 10);
        if (isNaN(id)) return;
        loadNamedView(id === 0 ? 'all_users_files' : 'file_manager', { user_id: id });
    };

    // --- CORE & USER MANAGEMENT ---
    mainContentArea.on('click.adminDashboard', '.ecp-back-to-users', () => loadNamedView('user_list'));
    mainContentArea.on('click.adminDashboard', '#ecp-add-new-client-btn', () => loadNamedView('add_user'));
    mainContentArea.on('click.adminDashboard', '#ecp-all-users-files-btn', () => loadNamedView('all_users_files'));
    mainContentArea.on('click.adminDashboard', '#ecp-file-summary-btn', () => loadNamedView('file_summary'));
    mainContentArea.on('click.adminDashboard', '#ecp-role-permissions-btn', () => loadNamedView('role_permissions'));
    mainContentArea.on('click.adminDashboard', '.edit-user-btn', function() { loadNamedView('edit_user', { user_id: $(this).closest('tr').data('userid') }); });
    mainContentArea.on('click.adminDashboard', '.manage-files-btn', function() { loadNamedView('file_manager', { user_id: $(this).closest('tr').data('userid') }); });
    
    let adminSearchTimeout;
    mainContentArea.on('keyup.adminDashboard', '#ecp-admin-user-search', function() {
        clearTimeout(adminSearchTimeout);
        adminSearchTimeout = setTimeout(() => {
            const userListContainer = $('#ecp-admin-user-list-container');
            if(!userListContainer.length) return;
            userListContainer.css('opacity', 0.5);
            $.post(ecp_ajax.ajax_url, {
                action: 'ecp_admin_dashboard_actions',
                nonce: ecp_ajax.nonces.dashboardNonce,
                sub_action: 'search_users',
                search: $(this).val() || ''
            }).done(response => userListContainer.html(response.success ? response.data : '<tr><td colspan="5">Error loading users.</td></tr>'))
              .fail(() => userListContainer.html('<tr><td colspan="5">Server error.</td></tr>'))
              .always(() => userListContainer.css('opacity', 1));
        }, 400);
    });

    mainContentArea.on('submit.adminDashboard', '#ecp-user-details-form, #ecp-role-permissions-form', function(e) {
        e.preventDefault();
        const form = $(this), btn = form.find('button[type="submit"]'), btnHtml = btn.html();
        btn.prop('disabled', true).text('Saving...');
        $.post(ecp_ajax.ajax_url, form.serialize() + '&nonce=' + ecp_ajax.nonces.dashboardNonce)
            .done(response => {
                showAdminMessage(response.data.message || 'Success!', response.success ? 'success' : 'error');
                if (response.success && form.attr('id') === 'ecp-user-details-form') loadNamedView('user_list');
            })
            .fail(() => showAdminMessage('An unknown error occurred.', 'error'))
            .always(() => btn.prop('disabled', false).html(btnHtml));
    });

    mainContentArea.on('click.adminDashboard', '.ecp-user-action-btn', function() {
        const btn = $(this), action = btn.data('action'), userId = btn.closest('tr').data('userid');
        const ajaxData = { action: 'ecp_admin_dashboard_actions', nonce: ecp_ajax.nonces.dashboardNonce, sub_action: action, user_id: userId };
        let confirmMsg = '';

        if (action === 'toggle_status') {
            ajaxData.enable = btn.data('enable');
            confirmMsg = ecp_ajax.strings.confirm_action.replace('%s', ajaxData.enable ? 'enable' : 'disable');
        } else if (action === 'remove_user') {
            confirmMsg = ecp_ajax.strings.confirm_delete_user;
        }
        if (!confirm(confirmMsg)) return;

        showBlockingLoader();
        $.post(ecp_ajax.ajax_url, ajaxData)
            .done(response => {
                showAdminMessage(response.data.message, response.success ? 'success' : 'error');
                if (response.success) loadNamedView('user_list');
            })
            .fail(() => showAdminMessage('An unknown server error occurred.', 'error'))
            .always(() => hideBlockingLoader());
    });
    
    mainContentArea.on('change.adminDashboard', '#ecp_user_role', function() {
        const role = $(this).val(), form = $(this).closest('form');
        form.find('.ecp-business-admin-field').toggle(role === 'ecp_business_admin');
        form.find('.ecp-client-field').toggle(role === 'ecp_client' || role === 'scp_client');
    }).trigger('change');

    // --- FILE MANAGER ---
    function executeFileAction(userId, subAction, data) {
        showBlockingLoader('Processing...');
        return $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions',
            nonce: ecp_ajax.nonces.fileManagerNonce,
            sub_action: subAction,
            user_id: userId,
            ...data
        }).done(response => {
            showAdminMessage(response.data.message, response.success ? 'success' : 'error');
            if (response.success) refreshFileManager(userId);
        }).fail(() => showAdminMessage('An unknown server error occurred.', 'error'))
          .always(() => hideBlockingLoader());
    }

    mainContentArea.on('change.fileManager', '#ecp-admin-folder-filter', function() {
        const userId = $(this).data('userid'), folder = $(this).val();
        const body = mainContentArea.find(`#ecp-file-manager-view-${userId} .file-list-body`).css('opacity', 0.5);
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_file_manager_actions', nonce: ecp_ajax.nonces.fileManagerNonce, sub_action: 'filter_files', user_id: userId, folder: folder
        }).done(response => body.html(response.success ? response.data : `<tr><td colspan="6">${response.data.message || 'Error.'}</td></tr>`))
          .fail(() => body.html('<tr><td colspan="6">Server error.</td></tr>'))
          .always(() => body.css('opacity', 1));
    });

    mainContentArea.on('change.fileManager', '.ecp-file-manager .ecp-select-all-files', function() {
        $(this).closest('table').find('.ecp-file-checkbox').prop('checked', this.checked).trigger('change');
    });

    mainContentArea.on('change.fileManager', '.ecp-file-manager .ecp-file-checkbox', function() {
        const table = $(this).closest('table');
        table.find('.ecp-select-all-files').prop('checked', table.find('.ecp-file-checkbox:checked').length === table.find('.ecp-file-checkbox').length);
    });
    
    mainContentArea.on('change.fileManager', '.ecp-change-category', function() {
        $(this).siblings('.ecp-save-category-btn').show();
    });

    mainContentArea.on('click.fileManager', '.ecp-save-category-btn', function() {
        const btn = $(this), select = btn.siblings('select');
        const userId = btn.closest('.ecp-file-manager').attr('id').replace('ecp-file-manager-view-', '');
        executeFileAction(userId, 'update_category', { file_key: select.data('filekey'), new_folder: select.val() });
    });

    mainContentArea.on('click.fileManager', '.ecp-bulk-action-apply', function(e) {
        e.stopImmediatePropagation();
        const fileManager = $(this).closest('.ecp-file-manager'), userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const bulkAction = fileManager.find('.ecp-bulk-action-select').val();
        const fileKeys = fileManager.find('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get();

        if (!bulkAction || fileKeys.length === 0) return showAdminMessage('Please select an action and at least one file.', 'error');

        if (bulkAction === 'encrypt' || bulkAction === 'decrypt') {
            const password = prompt(`Please enter a password to ${bulkAction} the selected files:`);
            if (password) executeFileAction(userId, 'bulk_actions', { file_keys: fileKeys, bulk_action: bulkAction, details: password });
        } else if (bulkAction === 'delete') {
            if (confirm(ecp_ajax.strings.confirm_delete_file)) executeFileAction(userId, 'bulk_actions', { file_keys: fileKeys, bulk_action: bulkAction });
        } else if (bulkAction === 'move') {
            const modal = fileManager.find('#ecp-move-files-modal');
            modal.find('#ecp-modal-folder-select').html(fileManager.find('#ecp-upload-folder-select').html());
            modal.fadeIn(200);
        }
    });

    mainContentArea.on('click.fileManager', '.ecp-single-file-action-btn', function(e) {
        e.stopImmediatePropagation(); e.preventDefault();
        const btn = $(this), action = btn.data('action'), userId = btn.closest('.ecp-file-manager').attr('id').replace('ecp-file-manager-view-', '');
        const fileKey = btn.data('filekey');
        
        if (action === 'encrypt' || action === 'decrypt') {
            const password = prompt(ecp_ajax.strings[`${action}_prompt`]);
            if (password) executeFileAction(userId, 'bulk_actions', { file_keys: [fileKey], bulk_action: action, details: password });
        } else if (action === 'delete') {
            if (confirm(ecp_ajax.strings.confirm_delete_file.replace('the selected files', 'this file'))) {
                executeFileAction(userId, 'bulk_actions', { file_keys: [fileKey], bulk_action: 'delete' });
            }
        }
    });

    mainContentArea.on('click.fileManager', '#ecp-modal-confirm-move-btn', function() {
        const modal = $(this).closest('.ecp-modal-overlay'), fileManager = modal.closest('.ecp-file-manager');
        const userId = fileManager.attr('id').replace('ecp-file-manager-view-', '');
        const newFolder = modal.find('#ecp-modal-folder-select').val();
        const fileKeys = fileManager.find('.ecp-file-checkbox:checked').map((i, el) => $(el).val()).get();
        executeFileAction(userId, 'bulk_actions', { file_keys: fileKeys, bulk_action: 'move', details: newFolder });
        modal.fadeOut(200);
    });

    mainContentArea.on('click.fileManager', '.ecp-modal-cancel-btn, #ecp-modal-cancel-btn', () => $('.ecp-modal-overlay').fadeOut(200));

    // --- FOLDER MANAGEMENT ---
    mainContentArea.on('submit.folderManager', '#ecp-add-folder-form, #ecp-edit-folder-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const userId = form.find('input[name="user_id"]').val();
        executeFileAction(userId, form.find('input[name="sub_action"]').val(), form.serialize());
    });

    mainContentArea.on('click.folderManager', '.ecp-delete-folder-btn', function() {
        if (!confirm(ecp_ajax.strings.confirm_delete_folder)) return;
        const btn = $(this);
        const userId = btn.closest('.ecp-file-manager').attr('id').replace('ecp-file-manager-view-', '');
        executeFileAction(userId, 'delete_folder', { folder_name: btn.data('folder'), folder_location: btn.data('location') });
    });

    mainContentArea.on('click.folderManager', '.ecp-edit-folder-btn', function(e) {
        e.preventDefault();
        const btn = $(this), fileManager = btn.closest('.ecp-file-manager');
        const form = fileManager.find('#ecp-edit-folder-form');
        form.find('input[name="original_folder_name"]').val(btn.data('folder'));
        form.find('input[name="original_folder_location"]').val(btn.data('location'));
        form.find('input[name="new_folder_name"]').val(btn.data('folder'));
        form.find('input[name="new_folder_location"]').val(btn.data('location'));
        form.slideDown();
        fileManager.find('#ecp-add-folder-form').slideUp();
    });

    mainContentArea.on('click.folderManager', '#ecp-edit-folder-cancel', function() {
        const form = $(this).closest('form');
        form.slideUp();
        form.closest('.ecp-file-manager').find('#ecp-add-folder-form').slideDown();
    });

    // --- UPLOADER ---
    function processQueue(form) {
        if (isUploading || fileQueue.length === 0) {
            if (!isUploading) refreshFileManager(form.find('input[name="user_id"]').val());
            return;
        }
        isUploading = true;
        const file = fileQueue.shift();
        const formData = new FormData(form[0]);
        formData.append('ecp_file_upload', file);
        formData.append('original_filename', file.name);
        formData.set('nonce', ecp_ajax.nonces.fileManagerNonce);

        $.ajax({
            url: ecp_ajax.ajax_url, type: 'POST', data: formData, processData: false, contentType: false,
            xhr: () => {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', e => {
                    if (e.lengthComputable) {
                        const percent = e.loaded / e.total * 100;
                        $(`#file-${file.queueId} .ecp-progress-bar-inner`).css('width', `${percent}%`);
                    }
                }, false);
                return xhr;
            }
        }).done(response => showAdminMessage(response.data.message, response.success ? 'success' : 'error'))
          .fail(() => showAdminMessage(`Error uploading ${file.name}.`, 'error'))
          .always(() => { isUploading = false; processQueue(form); });
    }

    mainContentArea.on('change.uploader', '.ecp-file-upload-input', function(e) {
        const form = $(this).closest('form'), progress = form.find('#ecp-upload-progress-container');
        $.each(e.target.files, (i, file) => {
            file.queueId = new Date().getTime() + i;
            fileQueue.push(file);
            progress.append(`<div class="ecp-progress-item" id="file-${file.queueId}"><div class="ecp-progress-filename">${file.name}</div><div class="ecp-progress-bar-outer"><div class="ecp-progress-bar-inner"></div></div></div>`);
        });
        if (!isUploading) processQueue(form);
    });

    mainContentArea.on('dragover.uploader', '.ecp-dropzone-area', function(e) { e.preventDefault(); $(this).addClass('dragover'); });
    mainContentArea.on('dragleave.uploader', '.ecp-dropzone-area', function(e) { e.preventDefault(); $(this).removeClass('dragover'); });
    
    mainContentArea.on('drop.uploader', '.ecp-dropzone-area', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        const form = $(this).closest('form'), progress = form.find('#ecp-upload-progress-container');
        const files = e.originalEvent.dataTransfer.files;
        $.each(files, (i, file) => {
            file.queueId = new Date().getTime() + i;
            fileQueue.push(file);
            progress.append(`<div class="ecp-progress-item" id="file-${file.queueId}"><div class="ecp-progress-filename">${file.name}</div><div class="ecp-progress-bar-outer"><div class="ecp-progress-bar-inner"></div></div></div>`);
        });
        if (!isUploading) processQueue(form);
    });
    
    mainContentArea.on('click.uploader', '.ecp-dropzone-area', function() { $(this).closest('form').find('.ecp-file-upload-input').trigger('click'); });
    mainContentArea.on('change.uploader', '.ecp-encrypt-toggle', function() { $(this).closest('.ecp-encryption-section').find('.ecp-password-fields').slideToggle(this.checked); });
    
    // Initial Load
    if ($('#ecp-dashboard-main-content').children().length === 0 || $('#ecp-admin-user-list-container').length) {
        loadNamedView('user_list');
    }
});

