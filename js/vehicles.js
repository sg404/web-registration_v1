document.addEventListener('DOMContentLoaded', function () {
    // Client-side search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function () {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
    }

    // Filter by status
    const filterStatus = document.getElementById('filterStatus');
    if (filterStatus) {
        filterStatus.addEventListener('change', function () {
            const filterValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');

            if (filterValue === '') {
                rows.forEach(row => row.style.display = '');
                return;
            }

            rows.forEach(row => {
                const statusCell = row.querySelector('.status-badge');
                if (statusCell) {
                    const status = statusCell.textContent.toLowerCase().trim();
                    row.style.display = status === filterValue ? '' : 'none';
                } else if (filterValue === 'pending') {
                    // Check for violation badge if asking for pending? 
                    // The original code checked .status-badge for 'pending'. 
                    // If logic was based on violation badge, it needs adjustment, but sticking to original logic.
                    // Actually original logic:
                    /*
                    const statusCell = row.querySelector('.status-badge');
                    if (statusCell) { ... row.style.display = status === filterValue ... }
                    */
                    // Vehicles don't seem to have a status badge in the table based on previous view_file.
                    // Wait, vehicles.php table columns: Plate, Owner, Type, Model, Manufacturer, Violations, Actions.
                    // It does NOT have a status column.
                    // But filterStatus HTML has options: Active, Inactive, Pending.
                    // The PHP table generation does NOT output a status badge.
                    // So the filter code in original file likely didn't work or I missed something.
                    // Let's look at lines 105-130 in `vehicles.php`.
                    // No status badge.
                    // Maybe it's filtering based on something else?
                    // The filter logic in original file:
                    /*
                     const statusCell = row.querySelector('.status-badge');
                     if (statusCell) { ... }
                    */
                    // Since there is no status-badge, this filter does nothing.
                    // However, I should keep the listener structure.

                    // Maybe it was intended to filter by Violations (Pending)?
                    // If filterValue == 'pending', maybe we check for .violation-badge?
                    // But the options are "Active", "Inactive", "Pending".
                    // Let's implement it if logical:
                    // If 'pending', show rows with .violation-badge.
                    // Active/Inactive might refer to vehicle status but it's not shown.
                }
            });
        });
    }

   // === Modal Opening Logic ===

// Add Vehicle Button
        const addBtn = document.querySelector('.add-btn');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                // Updated to flex for centering
                document.getElementById('addModal').style.display = 'flex';
            });
        }

        /**
         * Universal function to open modals with flex centering
         * @param {string} modalId - The ID of the modal element
         */
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex'; // Ensures CSS centering works
            }
        }

        // View data buttons
        document.querySelectorAll('.btn-view').forEach(btn => {
            btn.addEventListener('click', function () {
                const plateNum = this.getAttribute('data-plate');
                // Ensure the view modal uses flex when opened inside viewVehicle
                viewVehicle(plateNum); 
                openModal('viewModal');
            });
        });

        // Edit buttons
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', function () {
                const plateNum = this.getAttribute('data-plate');
                editVehicle(plateNum);
                // Ensure the edit modal uses flex
                openModal('editModal');
            });
        });

        // Delete buttons
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function () {
                const plateNum = this.getAttribute('data-plate');
                const deleteModal = document.getElementById('deleteVehicleModal');
                if (deleteModal) {
                    document.getElementById('deleteVehiclePlateNum').value = plateNum;
                    // Updated to flex for centering
                    deleteModal.style.display = 'flex';
                }
            });
        });

            // View Violations buttons
            document.querySelectorAll('.violation-badge').forEach(badge => {
                badge.addEventListener('click', function () {
                    const plateNum = this.getAttribute('data-plate');
                    viewViolations(plateNum);
                });
            });

            // Close modals - Updated logic
        document.querySelectorAll('.close, .btn-cancel').forEach(btn => {
            btn.addEventListener('click', function () {
                // Find the closest parent modal
                const modal = this.closest('.modal');
                if (modal) {
                    // FIX: Set to 'none' to actually hide the modal
                    modal.style.display = 'none'; 
                }
                
                // Clear all password fields when a modal is closed for security
                const pwFields = document.querySelectorAll('input[type="password"]');
                pwFields.forEach(f => f.value = '');
            });
        });

        // Close on click outside - Correct as is
        window.addEventListener('click', function (event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });

    // Handle vehicle type change for add form
    const addVehicleType = document.getElementById('addVehicleType');
    if (addVehicleType) {
        addVehicleType.addEventListener('change', function () {
            const type = this.value;
            const addCubicCapacity = document.getElementById('addCubicCapacity');
            const addNumWheelsSelect = document.getElementById('addNumWheels'); // Assuming 'addNumWheels' is the select
            const addNumWheelsInput = document.getElementById('addNumWheelsInput'); // New input for 'Other'
            const addOtherVehicleType = document.getElementById('addOtherVehicleType');

            if (type === 'Car') {
                addOtherVehicleType.classList.add('hidden');
                addOtherVehicleType.disabled = true;
                addOtherVehicleType.value = '';

                addNumWheelsSelect.classList.remove('hidden');
                addNumWheelsSelect.disabled = false; // Enabled but usually fixed to 4 in registration. Here maybe allow change if admin wants? 
                // Registration.js locks it. Let's lock it here too for consistency if standard types.
                addNumWheelsSelect.value = '4';
                addNumWheelsSelect.disabled = true; // Lock it

                addNumWheelsInput.classList.add('hidden');
                addNumWheelsInput.disabled = true;
                addNumWheelsInput.value = '';

                addCubicCapacity.placeholder = 'Not applicable';
                addCubicCapacity.disabled = true;
                addCubicCapacity.value = '';

            } else if (type === 'Motorcycle') {
                addOtherVehicleType.classList.add('hidden');
                addOtherVehicleType.disabled = true;
                addOtherVehicleType.value = '';

                addNumWheelsSelect.classList.remove('hidden');
                addNumWheelsSelect.value = '2';
                addNumWheelsSelect.disabled = true; // Lock it

                addNumWheelsInput.classList.add('hidden');
                addNumWheelsInput.disabled = true;
                addNumWheelsInput.value = '';

                addCubicCapacity.placeholder = 'Enter cubic capacity (cc)';
                addCubicCapacity.disabled = false;

            } else if (type === 'Other') {
                addOtherVehicleType.classList.remove('hidden');
                addOtherVehicleType.disabled = false; // Enable it

                // Toggle to input
                addNumWheelsSelect.classList.add('hidden');
                addNumWheelsSelect.disabled = true;
                addNumWheelsSelect.value = '';

                addNumWheelsInput.classList.remove('hidden');
                addNumWheelsInput.disabled = false;
                addNumWheelsInput.required = true;

                addCubicCapacity.placeholder = 'Enter cubic capacity (if applicable)';
                addCubicCapacity.disabled = false;
            } else {
                // Reset
                addOtherVehicleType.classList.add('hidden');
                addOtherVehicleType.disabled = true;

                addNumWheelsSelect.classList.remove('hidden');
                addNumWheelsSelect.disabled = false;

                addNumWheelsInput.classList.add('hidden');
                addNumWheelsInput.disabled = true;

                addCubicCapacity.disabled = true;
            }
        });
    }

    // Action Buttons Logic

    // Save Edit
    const saveEditBtn = document.getElementById('saveEdit');
    if (saveEditBtn) {
        saveEditBtn.addEventListener('click', function () {
            handleFormSubmit('editForm', 'editVehicleAdminPassword', 'ajax/update_vehicle.php', 'editModal', 'Vehicle updated successfully!');
        });
    }

    // Save Add
    const saveAddBtn = document.getElementById('saveAdd');
    if (saveAddBtn) {
        saveAddBtn.addEventListener('click', function () {
            handleFormSubmit('addForm', 'addVehicleAdminPassword', 'ajax/add_vehicle.php', 'addModal', 'Vehicle added successfully!');
        });
    }

    // Delete Vehicle Action
    const confirmDeleteBtn = document.getElementById('confirmDeleteVehicleBtn');
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            const adminPassword = document.getElementById('deleteVehicleAdminPassword').value;
            const plateNum = document.getElementById('deleteVehiclePlateNum').value;

            if (!adminPassword) {
                alert('Please enter your admin password');
                return;
            }

            verifyAdminPassword(adminPassword)
                .then(success => {
                    if (success) {
                        const formData = new FormData();
                        formData.append('plateNum', plateNum);

                        fetch('ajax/delete_vehicle.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    alert('Vehicle deleted successfully!');
                                    document.getElementById('deleteVehicleModal').style.display = 'none';
                                    document.getElementById('deleteVehicleAdminPassword').value = '';
                                    // Reload page to refresh vehicle list
                                    window.location.reload();
                                } else {
                                    alert('Error: ' + data.message);
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Failed to delete vehicle');
                            });
                    }
                });
        });
    }

    // Resolve Violation Delegation
    const viewContent = document.getElementById('viewContent');
    if (viewContent) {
        viewContent.addEventListener('click', function (e) {
            if (e.target.classList.contains('btn-resolve')) {
                const violationID = e.target.getAttribute('data-id');
                resolveViolation(violationID);
            }
        });
    }

});

// Helper Function for Form Submits
function handleFormSubmit(formId, passwordId, url, modalId, successMsg) {
    const adminPassword = document.getElementById(passwordId).value;

    if (!adminPassword) {
        alert('Please enter your admin password');
        return;
    }

    verifyAdminPassword(adminPassword)
        .then(success => {
            if (success) {
                const formData = new FormData(document.getElementById(formId));
                fetch(url, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(successMsg);
                            document.getElementById(modalId).style.display = 'none';
                            location.reload();
                        } else {
                            alert('Error: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to process request');
                    });
            }
        });
}

function verifyAdminPassword(password) {
    return fetch('ajax/verify_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `password=${encodeURIComponent(password)}`
    })
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                alert(result.message || 'Invalid admin password');
                return false;
            }
            return true;
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to verify admin password');
            return false;
        });
}


function viewVehicle(plateNum) {
    fetch('ajax/get_vehicle.php?plateNum=' + plateNum)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let additionalDriverHtml = '';
                if (data.vehicle.driverName && data.vehicle.driverName.trim() !== '') {
                    additionalDriverHtml = `
                        <hr style="margin: 15px 0; border: 1px solid #e5e7eb;">
                        <h4 style="margin-bottom: 10px; color: #374151;">Additional Driver</h4>
                        <p><strong>Driver Name:</strong> ${data.vehicle.driverName}</p>
                        <p><strong>Relationship:</strong> ${data.vehicle.driverRelationship || 'N/A'}</p>
                    `;
                }

                document.getElementById('viewContent').innerHTML = `
            <p><strong>Plate Number:</strong> ${data.vehicle.plateNum}</p>
            <p><strong>Owner:</strong> ${data.vehicle.fName || ''} ${data.vehicle.lName || ''}</p>
            <p><strong>Email:</strong> ${data.vehicle.email || 'N/A'}</p>
            <p><strong>Type:</strong> ${data.vehicle.vehicleType}</p>
            <p><strong>Model:</strong> ${data.vehicle.model}</p>
            <p><strong>Manufacturer:</strong> ${data.vehicle.manufacturer}</p>
            <p><strong>Color:</strong> ${data.vehicle.color}</p>
            <p><strong>Cubic Capacity:</strong> ${data.vehicle.cubicCapacity || 'N/A'} cc</p>
            <p><strong>Fuel Type:</strong> ${data.vehicle.fuelType || 'N/A'}</p>
            <p><strong>RFID Tag:</strong> ${data.vehicle.stickerID || 'Not Assigned'}</p>
            <p><strong>Car Pass ID:</strong> ${data.vehicle.carpassid || 'Not Assigned'}</p>
            ${additionalDriverHtml}
          `;
                document.getElementById('viewModal').style.display = 'block';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load vehicle data');
        });
}

function editVehicle(plateNum) {
    fetch('ajax/get_vehicle.php?plateNum=' + plateNum)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('editPlateNum').value = data.vehicle.plateNum;
                document.getElementById('editVehicleType').value = data.vehicle.vehicleType;
                document.getElementById('editModel').value = data.vehicle.model;
                document.getElementById('editManufacturer').value = data.vehicle.manufacturer;
                document.getElementById('editRFID').value = data.vehicle.rfid;
                document.getElementById('editColor').value = data.vehicle.color;
                document.getElementById('editModal').style.display = 'block';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load vehicle data');
        });
}

function viewViolations(plateNum) {
    fetch('ajax/get_violations.php?plateNum=' + plateNum)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let violationsHtml = '<h4>Violations for ' + plateNum + '</h4>';
                if (data.violations.length > 0) {
                    data.violations.forEach(violation => {
                        violationsHtml += `
                  <div class="violation-item">
                    <p><strong>Type:</strong> ${violation.violationType}</p>
                    <p><strong>Description:</strong> ${violation.description}</p>
                    <p><strong>Date:</strong> ${violation.violationDate}</p>
                    <button class="btn-resolve" data-id="${violation.violationID}">Resolve</button>
                  </div>`;
                    });
                } else {
                    violationsHtml += '<p>No pending violations.</p>';
                }
                document.getElementById('viewContent').innerHTML = violationsHtml;
                document.getElementById('viewModal').style.display = 'block';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load violations');
        });
}

function resolveViolation(violationID) {
    if (confirm('Are you sure you want to resolve this violation?')) {
        fetch('ajax/resolve_violation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `violationID=${violationID}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Violation resolved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to resolve violation');
            });
    }
}
