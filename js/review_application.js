document.addEventListener('DOMContentLoaded', function () {
    // Bind Decline Button via ID to ensure it is clickable
    const declineBtn = document.getElementById('declineBtn');
    if (declineBtn) {
        declineBtn.addEventListener('click', function (e) {
            e.preventDefault();
            showRejectModal();
        });
    }
});

function showRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) {
        // Remove 'hidden' class and set display flex for the overlay
        modal.classList.remove('hidden');
        modal.style.display = 'flex'; 
    }
}

function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
        document.getElementById('rejectReasonText').value = '';
    }
}

function submitRejection() {
    const reason = document.getElementById('rejectReasonText').value.trim();
    if (!reason) {
        alert('Please provide a reason for rejection.');
        return;
    }

    const btn = document.getElementById('confirmRejectBtn');
    btn.disabled = true;
    btn.textContent = 'Processing...';

    // Transfer reason to the hidden input in the main form
    document.getElementById('rejectionReason').value = reason;

    // Create a hidden input to specify the 'reject' action for PHP
    const form = document.getElementById('reviewForm');
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'reject';
    form.appendChild(actionInput);

    form.submit();
}