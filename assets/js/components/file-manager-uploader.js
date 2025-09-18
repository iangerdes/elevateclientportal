// File: elevate-client-portal/assets/js/components/file-manager-uploader.js
/**
 * Handles all logic for the file uploader component, including drag-and-drop,
 * progress bars, and queue management.
 *
 * @package Elevate_Client_Portal
 * @version 66.0.0
 * @comment Fixed progress bar visibility. The script now explicitly shows the progress container as soon as files are added to the queue, ensuring the user gets immediate feedback.
 */

jQuery(function ($) {
    const mainContentArea = $('#ecp-dashboard-main-content');
    let fileQueue = [];
    let isUploading = false;

    // This setup function ensures event handlers are not bound multiple times.
    function setupUploaderEvents(context) {
        const uploaderForms = $(context).find('.ecp-upload-form');
        
        // Unbind any previous uploader events from this context to prevent duplicates.
        uploaderForms.off('.uploader');
        mainContentArea.off('.uploaderDropzone');

        uploaderForms.on('change.uploader', '.ecp-file-upload-input', function(e) {
            const form = $(this).closest('form');
            const progressContainer = form.find('#ecp-upload-progress-container').show();
            $.each(e.target.files, (i, file) => {
                file.queueId = new Date().getTime() + i;
                addToQueue(file, progressContainer);
            });
            if (!isUploading) processQueue(form);
        });

        uploaderForms.on('change.uploader', '.ecp-encrypt-toggle', function() {
            $(this).closest('.ecp-encryption-section').find('.ecp-password-fields').slideToggle($(this).is(':checked'));
        });
        
        mainContentArea.on('dragover.uploaderDropzone', '.ecp-dropzone-area', function(e) {
            e.preventDefault(); e.stopPropagation(); $(this).addClass('dragover');
        });

        mainContentArea.on('dragleave.uploaderDropzone', '.ecp-dropzone-area', function(e) {
            e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover');
        });

        mainContentArea.on('drop.uploaderDropzone', '.ecp-dropzone-area', function(e) {
            e.preventDefault(); e.stopPropagation(); $(this).removeClass('dragover');
            const form = $(this).closest('form');
            const progressContainer = form.find('#ecp-upload-progress-container').show();
            const files = e.originalEvent.dataTransfer.files;
            $.each(files, (i, file) => {
                file.queueId = new Date().getTime() + i;
                addToQueue(file, progressContainer);
            });
            if (!isUploading) processQueue(form);
        });
        
        mainContentArea.on('click.uploaderDropzone', '.ecp-dropzone-area', function() {
            $(this).closest('form').find('.ecp-file-upload-input').trigger('click');
        });
    }

    function addToQueue(file, progressContainer) {
        fileQueue.push(file);
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
                setTimeout(() => {
                    refreshFileManager(form.find('input[name="user_id"]').val());
                }, 1000); // Add a small delay to ensure server has processed the file
            }
            return;
        }

        isUploading = true;
        const file = fileQueue.shift();
        
        const formData = new FormData(form[0]);
        formData.append('ecp_file_upload', file);
        formData.append('original_filename', file.name);
        formData.set('nonce', ecp_ajax.nonces.fileManagerNonce); // Ensure nonce is set correctly for AJAX calls

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

    // Use a MutationObserver to re-apply events when the view changes.
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.addedNodes.length) {
                // Check if a file manager view was added
                if ($(mutation.target).find('.ecp-file-manager').length > 0) {
                    setupUploaderEvents(mutation.target);
                    // Disconnect and reconnect to avoid observing our own changes
                    observer.disconnect();
                    // Re-observe after a short delay
                    setTimeout(() => observeDashboard(), 0);
                    break;
                }
            }
        }
    });

    function observeDashboard() {
        if (document.getElementById('ecp-dashboard-main-content')) {
            observer.observe(document.getElementById('ecp-dashboard-main-content'), {
                childList: true,
                subtree: true
            });
        }
    }
    
    // Initial setup
    setupUploaderEvents(mainContentArea);
    observeDashboard();
});

