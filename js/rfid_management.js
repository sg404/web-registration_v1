document.addEventListener('DOMContentLoaded', function () {
  // Search functionality for pending table
// Inside web-registration_v1/js/rfid_management.js
const searchPending = document.getElementById('searchPending');
if (searchPending) {
  searchPending.addEventListener('keyup', function () {
    const searchValue = this.value.toLowerCase();
    const rows = document.querySelectorAll('#pendingTable tbody tr');

    rows.forEach(row => {
      if (row.querySelector('.no-data')) return;
      
      const name = (row.cells[0] && row.cells[0].textContent) ? row.cells[0].textContent.toLowerCase() : '';
      const plate = (row.cells[1] && row.cells[1].textContent) ? row.cells[1].textContent.toLowerCase() : '';

      row.style.display = (name.includes(searchValue) || plate.includes(searchValue)) ? '' : 'none';
    });
  });
}

  // Search functionality for registered table
  const searchRegistered = document.getElementById('searchRegistered');
  if (searchRegistered) {
    searchRegistered.addEventListener('keyup', function () {
      const searchValue = this.value.toLowerCase();
      const rows = document.querySelectorAll('#registeredTable tbody tr');

      rows.forEach(row => {
        if (row.querySelector('.no-data')) return;
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
      });
    });
  }

  // Modal Elements
  const issueRfidPopup = document.getElementById('issueRfidPopup');
  const addRfidModal = document.getElementById('addRfidModal');
  const successPopup = document.getElementById('successPopup');

  // Button Bindings

  // Add RFID Button
  const btnAddRfid = document.querySelector('.btn-add-rfid');
  if (btnAddRfid) {
    btnAddRfid.addEventListener('click', function () {
      if (addRfidModal) addRfidModal.style.display = 'block';
    });
  }

  // Issue RFID Buttons (Opening Modal)
  document.querySelectorAll('.btn-issue').forEach(btn => {
    btn.addEventListener('click', function () {
        const plateNum = this.getAttribute('data-plate');
      const appId = this.getAttribute('data-appid');
      document.getElementById('plateNumInput').value = plateNum;
      // Registration codes are no longer used on applications; clear any code field
      document.getElementById('registrationCodeInput').value = '';
      document.getElementById('vehiclePlateDisplay').textContent = plateNum;
      loadAvailableRfidTags();
      loadAvailableCarpass();
      // Store application id on the confirm button for reference
      if (issueRfidPopup) issueRfidPopup.style.display = 'block';
    });
  });

  // Registration codes are auto-sent on approval; manual send/resend removed to avoid duplicate emails.

  // Revoke RFID Buttons
  // Use delegation for revocation buttons if they are dynamic, but here they seem static in PHP loop.
  document.querySelectorAll('.btn-revoke').forEach(btn => {
    btn.addEventListener('click', function () {
      const plateNum = this.getAttribute('data-plate');
      const stickerID = this.getAttribute('data-sticker');

      if (confirm(`Are you sure you want to revoke RFID and Car Pass access for vehicle ${plateNum}?`)) {
        revokeAccess(plateNum, stickerID);
      }
    });
  });

  // Cancel Buttons
  document.querySelectorAll('.btn-cancel').forEach(btn => {
    btn.addEventListener('click', function () {
      const modal = this.closest('.popup-overlay');
      if (modal) modal.style.display = 'none';

      // Clear specific fields
      if (modal.id === 'issueRfidPopup') {
        document.getElementById('rfidTagSelect').value = '';
        document.getElementById('carpassIdInput').value = '';
      } else if (modal.id === 'addRfidModal') {
        document.getElementById('newRfidCode').value = '';
      }
    });
  });

  // Close Modals on Overlay Click
  window.addEventListener('click', function (e) {
    if (e.target.classList.contains('popup-overlay')) {
      e.target.style.display = 'none';
    }
  });

  // Confirm Issue RFID
  const btnConfirmIssue = document.querySelector('#issueRfidPopup .btn-confirm');
  if (btnConfirmIssue) {
    btnConfirmIssue.addEventListener('click', confirmIssueRfid);
  }

  // Confirm Add RFID
  const btnAddConfirm = document.querySelector('#addRfidModal .btn-add-confirm');
  if (btnAddConfirm) {
    btnAddConfirm.addEventListener('click', addRfidTag);
  }

  // Success OK Button
  const btnSuccessOk = document.querySelector('.btn-success-ok');
  if (btnSuccessOk) {
    btnSuccessOk.addEventListener('click', function () {
      if (successPopup) successPopup.style.display = 'none';
      window.location.reload();
    });
  }



});

// Helper Functions

function loadAvailableRfidTags() {
  fetch('ajax/get_available_rfid.php')
    .then(response => response.json())
    .then(response => {
      const select = document.getElementById('rfidTagSelect');
      select.innerHTML = '<option value="">Select RFID Tag</option>';

      if (response.success && response.data.length > 0) {
        response.data.forEach(rfid => {
          const option = document.createElement('option');
          option.value = rfid.stickerID;
          option.textContent = `${rfid.stickerID}${rfid.tagCode ? ' - ' + rfid.tagCode.substring(0, 20) + '...' : ''}`;
          select.appendChild(option);
        });
      } else {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No available RFID tags';
        option.disabled = true;
        select.appendChild(option);
      }
    })
    .catch(error => console.error('Error loading RFID tags:', error));
}

function loadAvailableCarpass() {
  fetch('ajax/get_available_carpass.php')
    .then(response => response.json())
    .then(response => {
      const select = document.getElementById('carpassIdInput');
      select.innerHTML = '<option value="">Select Car Pass ID...</option>';

      if (response.success && response.data.length > 0) {
        response.data.forEach(carpassId => {
          const option = document.createElement('option');
          option.value = carpassId;
          option.textContent = carpassId;
          select.appendChild(option);
        });
      } else {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No available Car Pass IDs';
        option.disabled = true;
        select.appendChild(option);
      }
    })
    .catch(error => console.error('Error loading Car Pass IDs:', error));
}

function addRfidTag() {
  const tagCode = document.getElementById('newRfidCode').value;

  if (!tagCode) {
    alert('Please enter RFID tag code');
    return;
  }

  fetch('ajax/add_rfid.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `tagCode=${encodeURIComponent(tagCode)}`
  })
    .then(response => response.json())
    .then(response => {
      if (response.success) {
        document.getElementById('addRfidModal').style.display = 'none';
        document.getElementById('successMessage').textContent = 'RFID Tag Added Successfully!';
        document.getElementById('successDescription').textContent = `Tag assigned ID: ${response.stickerID}`;
        document.getElementById('successPopup').style.display = 'block';
      } else {
        alert('Error: ' + response.message);
      }
    })
    .catch(error => console.error('Error adding RFID tag:', error));
}

function confirmIssueRfid() {
  const rfidTagId = document.getElementById('rfidTagSelect').value;
  const carpassId = document.getElementById('carpassIdInput').value;
  const plateNum = document.getElementById('plateNumInput').value;

  if (!rfidTagId || !carpassId) {
    alert('Please select both RFID Tag and Car Pass ID');
    return;
  }

  fetch('ajax/issue_both.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `plateNum=${encodeURIComponent(plateNum)}&stickerID=${encodeURIComponent(rfidTagId)}&carpassId=${encodeURIComponent(carpassId)}`
  })
    .then(response => response.json())
    .then(response => {
      if (response.success) {
        document.getElementById('issueRfidPopup').style.display = 'none';
        document.getElementById('successMessage').textContent = 'RFID & Car Pass Issued Successfully!';
        document.getElementById('successDescription').textContent = 'The RFID tag and car pass have been assigned to the vehicle.';
        document.getElementById('successPopup').style.display = 'block';
      } else {
        // If server reports the car pass ID was already assigned (race or inconsistent data), remove it from the dropdown and reload available list
        const msg = response.message || '';
        if (/car pass|carpass/i.test(msg) && /already assigned/i.test(msg)) {
          const carpassSelect = document.getElementById('carpassIdInput');
          const selected = carpassSelect ? carpassSelect.value : null;

          // Remove the offending option if present
          if (selected) {
            const opt = carpassSelect.querySelector(`option[value="${selected}"]`);
            if (opt) opt.remove();
          }

          // Reload the available list to reflect current state
          loadAvailableCarpass();

          alert('Selected Car Pass ID is no longer available and has been removed. Please choose another.');
        } else {
          alert('Error: ' + response.message);
        }
      }
    })
    .catch(error => console.error('Error issuing RFID:', error));
}

function revokeAccess(plateNum, stickerID) {
  fetch('ajax/revoke_rfid.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `plateNum=${encodeURIComponent(plateNum)}`
  })
    .then(response => response.json())
    .then(response => {
      if (response.success) {
        document.getElementById('successMessage').textContent = 'Access Revoked Successfully!';
        document.getElementById('successDescription').textContent = 'RFID and Car Pass have been revoked and are now available for reuse.';
        document.getElementById('successPopup').style.display = 'block';
      } else {
        alert('Error: ' + response.message);
      }
    })
    .catch(error => console.error('Error revoking access:', error));
}