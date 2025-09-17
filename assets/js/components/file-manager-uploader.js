// File: elevate-client-portal/assets/js/components/file-manager-uploader.js
/**
 * Handles all logic for the file uploader component, including drag-and-drop,
 * progress bars, and queue management.
 *
 * @package Elevate_Client_Portal
 * @version 42.0.0 (Final Audit & Refactor)
 */

jQuery(function ($) {
    const mainContentArea = $('#ecp-dashboard-main-content');
    let fileQueue = [];
    let isUploading = false;

    function addToQueue(file, form) {
        fileQueue.push(file);
        const progressContainer = form.find('#ecp-upload-progress-container');
        progressContainer.append(`
            <div class="ecp-progress-item" id="file-${file.queueId}">
                <div class="ecp-progress-filename">${file.name}</div>
                <div class="ecp-progress-bar-outer">
                    <div class="ecp-progress-bar-inner" style="width: 0%;"></div>
                </div>
            </div>
        `);
    }

    function processQueue(form) {
        if (isUploading || fileQueue.length === 0) {
            if (!isUploading) { 
                refreshFileManager(form.find('input[name="user_id"]').val());
            }
            return;
        }

        isUploading = true;
        const file = fileQueue.shift();
        const userId = form.find('input[name="user_id"]').val();
        const folder = form.find('select[name="ecp_file_folder"]').val();
        const encrypt = form.find('input.ecp-encrypt-toggle').is(':checked');
        const password = form.find('input#ecp-encrypt-password').val();
        const notify = form.find('input[name="ecp_notify_client"]').is(':checked');

        const formData = new FormData();
        formData.append('action', 'ecp_file_manager_actions');
        formData.append('nonce', ecp_ajax.nonces.fileManagerNonce);
        formData.append('sub_action', 'upload_file');
        formData.append('user_id', userId);
        formData.append('ecp_file_folder', folder);
        formData.append('ecp_file_upload', file);
        formData.append('original_filename', file.name);

        if (notify) {
            formData.append('ecp_notify_client', '1');
        }
        if (encrypt && password) {
            formData.append('ecp_encrypt_toggle', '1');
            formData.append('ecp_encrypt_password', password);
        }

        $.ajax({
            url: ecp_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = evt.loaded / evt.total * 100;
                        $(`#file-${file.queueId} .ecp-progress-bar-inner`).css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            }
        }).done(response => {
            const message = response.data.message || (response.success ? 'Upload complete.' : 'Upload failed.');
            showAdminMessage(message, response.success ? 'success' : 'error');
        }).fail(() => {
            showAdminMessage(`Error uploading ${file.name}.`, 'error');
        }).always(() => {
            isUploading = false;
            processQueue(form);
        });
    }

    mainContentArea.on('change.uploader', '.ecp-file-upload-input', function(e) {
        const form = $(this).closest('form');
        $.each(e.target.files, (i, file) => {
            file.queueId = new Date().getTime() + i;
            addToQueue(file, form);
        });
        processQueue(form);
    });

    mainContentArea.on('dragover.uploader', '.ecp-dropzone-area', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });

    mainContentArea.on('dragleave.uploader', '.ecp-dropzone-area', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    mainContentArea.on('drop.uploader', '.ecp-dropzone-area', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
        const form = $(this).closest('form');
        const files = e.originalEvent.dataTransfer.files;
        $.each(files, (i, file) => {
            file.queueId = new Date().getTime() + i;
            addToQueue(file, form);
        });
        processQueue(form);
    });
    
    mainContentArea.on('click.uploader', '.ecp-dropzone-area', function() {
        $(this).closest('form').find('.ecp-file-upload-input').trigger('click');
    });

    mainContentArea.on('change.uploader', '.ecp-encrypt-toggle', function() {
        $(this).closest('.ecp-encryption-section').find('.ecp-password-fields').slideToggle($(this).is(':checked'));
    });
});

