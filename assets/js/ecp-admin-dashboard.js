// File: elevate-client-portal/assets/js/ecp-admin-dashboard.js
/**
 * Handles core AJAX logic for the administrator dashboard, including user management and view switching.
 * This file also defines global helper functions used by other component scripts.
 *
 * @package Elevate_Client_Portal
 * @version 73.0.0 (Final Audit)
 * @comment Final Audit: Centralized all shared helper functions (showAdminMessage, refreshFileManager, etc.) into a single global ECP_Admin object. This resolves "is not defined" errors in component scripts by providing a single, reliable source for shared functionality.
 */

// Define a global object to hold shared functions, making them available to component scripts.
window.ECP_Admin = {
    showAdminMessage: function(message, type = 'success') {
        const messageBox = jQuery('#ecp-admin-messages');
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        messageBox.html(`<div class="notice ${noticeClass} is-dismissible"><p>${message}</p></div>`).fadeIn();
        setTimeout(() => messageBox.fadeOut(500, function() { jQuery(this).html('').show(); }), 5000);
    },

    showBlockingLoader: function(message = 'Processing...') {
        jQuery('.ecp-loader-overlay').find('p').text(message).end().css('display', 'flex');
    },

    hideBlockingLoader: function() {
        jQuery('.ecp-loader-overlay').fadeOut(200);
    },
    
    refreshFileManager: function(userId) {
        const id = parseInt(userId, 10);
        if (isNaN(id)) return;
        
        const fileManagerView = jQuery(`#ecp-file-manager-view-${id}`);
        if(fileManagerView.length) {
            fileManagerView.css('opacity', 0.5);
             jQuery.post(ecp_ajax.ajax_url, {
                action: 'ecp_get_view',
                nonce: ecp_ajax.nonces.viewNonce,
                view: id === 0 ? 'all_users_files' : 'file_manager',
                user_id: id
            }).done(response => {
                if (response.success) {
                    const newContent = jQuery(response.data).html();
                    fileManagerView.html(newContent).css('opacity', 1);
                } else {
                    this.showAdminMessage(response.data.message || 'Error refreshing file list.', 'error');
                    fileManagerView.css('opacity', 1);
                }
            }).fail(() => {
                this.showAdminMessage('An unexpected server error occurred while refreshing.', 'error');
                fileManagerView.css('opacity', 1);
            });
        }
    }
};

jQuery(function ($) {
    const adminDashboard = $('.ecp-admin-dashboard');
    if (!adminDashboard.length) return;

    const mainContentArea = $('#ecp-dashboard-main-content');
    
    if (!adminDashboard.find('.ecp-loader-overlay').length) {
        adminDashboard.append('<div class="ecp-loader-overlay" style="display:none;"><div class="ecp-spinner"></div><p>Processing...</p></div>');
    }

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
            if (response.success) {
                loadView(response.data);
                if (viewName === 'user_list') {
                    fetchUserListTable();
                }
            } else {
                ECP_Admin.showAdminMessage(response.data.message || 'Error loading view.', 'error');
                loadNamedView('user_list');
            }
        }).fail(() => {
            ECP_Admin.showAdminMessage('An unexpected server error occurred.', 'error');
            loadNamedView('user_list');
        });
    }

    function fetchUserListTable(searchTerm = '') {
        const userListContainer = $('#ecp-admin-user-list-container');
        if(!userListContainer.length) return;

        userListContainer.css('opacity', 0.5);
        $.post(ecp_ajax.ajax_url, {
            action: 'ecp_admin_dashboard_actions',
            nonce: ecp_ajax.nonces.dashboardNonce,
            sub_action: 'search_users',
            search: searchTerm
        }).done(response => userListContainer.html(response.success ? response.data : '<tr><td colspan="5">Error loading users.</td></tr>'))
          .fail(() => userListContainer.html('<tr><td colspan="5">Server error.</td></tr>'))
          .always(() => userListContainer.css('opacity', 1));
    }

    // --- CORE & USER MANAGEMENT EVENT LISTENERS ---
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
        const searchTerm = $(this).val();
        adminSearchTimeout = setTimeout(() => fetchUserListTable(searchTerm), 400);
    });

    mainContentArea.on('submit.adminDashboard', '#ecp-user-details-form, #ecp-role-permissions-form', function(e) {
        e.preventDefault();
        const form = $(this), btn = form.find('button[type="submit"]'), btnHtml = btn.html();
        btn.prop('disabled', true).text('Saving...');
        $.post(ecp_ajax.ajax_url, form.serialize() + '&nonce=' + ecp_ajax.nonces.dashboardNonce)
            .done(response => {
                ECP_Admin.showAdminMessage(response.data.message || 'Success!', response.success ? 'success' : 'error');
                if (response.success && form.attr('id') === 'ecp-user-details-form') loadNamedView('user_list');
            })
            .fail(() => ECP_Admin.showAdminMessage('An unknown error occurred.', 'error'))
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

        ECP_Admin.showBlockingLoader();
        $.post(ecp_ajax.ajax_url, ajaxData)
            .done(response => {
                ECP_Admin.showAdminMessage(response.data.message, response.success ? 'success' : 'error');
                if (response.success) fetchUserListTable($('#ecp-admin-user-search').val());
            })
            .fail(() => ECP_Admin.showAdminMessage('An unknown server error occurred.', 'error'))
            .always(() => ECP_Admin.hideBlockingLoader());
    });
    
    mainContentArea.on('change.adminDashboard', '#ecp_user_role', function() {
        const role = $(this).val(), form = $(this).closest('form');
        form.find('.ecp-business-admin-field').toggle(role === 'ecp_business_admin');
        form.find('.ecp-client-field').toggle(role === 'ecp_client' || role === 'scp_client');
    }).trigger('change');

    if ($('#ecp-admin-user-list-container').length) {
        fetchUserListTable();
    }
});

