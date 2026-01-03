/**
 * ファイル: public_html\js\report_modal.js
 * 使い道: 通報モーダルの送信処理とフィードバック表示を行います。
 * 定義されている関数: なし
 */



document.addEventListener('DOMContentLoaded', function() {
    const reportModal = document.getElementById('reportModal');
    if (!reportModal) {
        return;
    }

    const reportForm = document.getElementById('report-form');
    const reportContentIdInput = document.getElementById('report-content-id');
    const submitReportBtn = document.getElementById('submit-report-btn');
    const reportModalMessage = document.getElementById('report-modal-message');

    reportModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const contentId = button.dataset.contentId;
        
        reportContentIdInput.value = contentId;
        reportModalMessage.classList.add('d-none'); // Hide any previous messages
        reportModalMessage.textContent = '';
        reportForm.reset(); // Reset form fields
        submitReportBtn.disabled = false; // Enable submit button
    });

    reportForm.addEventListener('submit', async function(event) {
        event.preventDefault(); // Prevent default form submission

        submitReportBtn.disabled = true; // Disable button to prevent multiple submissions
        reportModalMessage.classList.add('d-none'); // Hide previous messages

        const formData = new FormData(reportForm); // Collect form data
        
        try {
            const response = await fetch('/api/system/report_content', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || '通報の送信に失敗しました。');
            }

            reportModalMessage.classList.remove('alert-danger', 'd-none');
            reportModalMessage.classList.add('alert-success');
            reportModalMessage.textContent = result.message || '通報が正常に送信されました。ご協力ありがとうございます。';
            
            // }, 2000);

        } catch (error) {
            reportModalMessage.classList.remove('alert-success', 'd-none');
            reportModalMessage.classList.add('alert-danger');
            reportModalMessage.textContent = `エラー: ${error.message}`;
        } finally {
            submitReportBtn.disabled = false; // Re-enable button
            reportModalMessage.classList.remove('d-none'); // Show message
        }
    });
});




