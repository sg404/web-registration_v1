<?php
session_start();

// Check authentication and single session
require_once 'auth_check.php';

// Set page title and current page for navigation highlighting
$pageTitle = "Vehicles";
$currentPage = "vehicles";
$cssFiles = ["vehicles.css"];
$jsFiles = ["script.js", "admin-auth.js", "vehicles.js"];

// Include database connection
require_once 'dbConnection.php';

// Create database instance
$db = new Database();
$conn = $db->getConnection();

// Get all vehicles from vehicle table with owner info and pending violations count
$query = "SELECT v.*, vo.fName, vo.lName, vo.email, 
          COUNT(viol.violationID) as pending_violations
          FROM vehicle v 
          LEFT JOIN vehicleowner vo ON v.OwnerID = vo.OwnerID 
          LEFT JOIN violations viol ON v.plateNum = viol.plateNum AND viol.status = 'pending'
          GROUP BY v.plateNum
          ORDER BY v.plateNum";
$result = $conn->query($query);

// Include header
include_once '../includes/header.php';
?>

<main class="main">
    <div class="container">
        <div class="header">
            <h2>Vehicles</h2>
            <div class="header-actions">
                <div class="search-container">
                    <input type="text" id="searchInput" placeholder="Search vehicles..." />
                    <button class="search-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-search">
                            <circle cx="11" cy="11" r="8" />
                            <path d="m21 21-4.3-4.3" />
                        </svg>
                    </button>
                </div>
                <div class="filter-container">
                    <select id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <?php if ($_SESSION['role'] === 'SSEDMMO Admin'): ?>
                    <button class="add-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="lucide lucide-plus">
                            <path d="M5 12h14" />
                            <path d="M12 5v14" />
                        </svg>
                        Add Vehicle
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Plate Number</th>
                        <th>Owner</th>
                        <th>Vehicle Type</th>
                        <th>Model</th>
                        <th>Manufacturer</th>
                        <th>Violations</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (empty($row['stickerID']) || empty($row['carpassid'])): ?>
                                        <span title="Missing RFID or Car Pass" style="color: #f59e0b; margin-right: 5px;">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                class="lucide lucide-alert-triangle">
                                                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" />
                                                <path d="M12 9v4" />
                                                <path d="M12 17h.01" />
                                            </svg>
                                        </span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($row['plateNum']); ?>
                                </td>
                                <td>
                                    <div class="owner-info">
                                        <strong><?php echo htmlspecialchars(($row['fName'] ?? '') . ' ' . ($row['lName'] ?? '')); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['vehicleType']); ?></td>
                                <td><?php echo htmlspecialchars($row['model']); ?></td>
                                <td><?php echo htmlspecialchars($row['manufacturer']); ?></td>
                                <td>
                                    <?php if ($row['pending_violations'] > 0): ?>
                                        <span class="violation-badge" data-plate="<?php echo $row['plateNum']; ?>" style="cursor: pointer;">⚠️
                                            <?php echo $row['pending_violations']; ?> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-view" data-plate="<?php echo $row['plateNum']; ?>">View</button>
                                        <?php if ($_SESSION['role'] === 'SSEDMMO Admin'): ?>
                                            <button class="btn-edit" data-plate="<?php echo $row['plateNum']; ?>">Edit</button>
                                            <button class="btn-delete" data-plate="<?php echo $row['plateNum']; ?>">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="no-data">No vehicles found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<div id="addModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Add New Vehicle</h3>
        <form id="addForm">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label>Owner:</label>
                <select id="addOwnerID" name="ownerID" required>
                    <option value="">Select Owner</option>
                    <?php
                    $ownerQuery = "SELECT OwnerID, fName, lName, email FROM vehicleowner ORDER BY lName, fName";
                    $ownerResult = $conn->query($ownerQuery);
                    if ($ownerResult && $ownerResult->num_rows > 0) {
                        while ($owner = $ownerResult->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($owner['OwnerID']) . '">' .
                                htmlspecialchars($owner['fName'] . ' ' . $owner['lName'] . ' (' . $owner['email'] . ')') . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Plate Number:</label>
                <input type="text" id="addPlateNum" name="plateNum" required>
            </div>

            <div class="form-group">
                <label>Vehicle Type:</label>
                <select id="addVehicleType" name="vehicleType" required>
                    <option value="">Select Type</option>
                    <option value="Car">Car</option>
                    <option value="Motorcycle">Motorcycle</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Model:</label>
                <input type="text" id="addModel" name="model" required>
            </div>

            <div class="form-group">
                <label>Manufacturer:</label>
                <input type="text" id="addManufacturer" name="manufacturer" required>
            </div>

            <div class="form-group">
                <label>Color:</label>
                <input type="text" id="addColor" name="color" required>
            </div>

            <div class="form-group">
                <label>Cubic Capacity (cc):</label>
                <input type="number" id="addCubicCapacity" name="cubicCapacity">
            </div>

            <div class="form-group">
                <label>Number of Wheels:</label>
                <select id="addNumWheels" name="numOfWheels" required>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                </select>
            </div>

            <div class="form-group">
                <label>Fuel Type:</label>
                <select id="addFuelType" name="fuelType" required>
                    <option value="Gasoline">Gasoline</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Electric">Electric</option>
                    <option value="Hybrid">Hybrid</option>
                </select>
            </div>

            <hr style="margin:15px 0;">
            <p style="color:#dc2626;font-weight:600;">Admin Confirmation Required</p>

            <div class="form-group">
                <label>Admin Password:</label>
                <input type="password" id="addVehicleAdminPassword" name="adminPassword" required placeholder="Confirm your password">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="button" id="saveAdd" class="btn-save">Add Vehicle</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Edit Vehicle</h3>
        <form id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="editPlateNum" name="plateNum">

            <div class="form-group">
                <label>Type:</label>
                <input type="text" id="editVehicleType" name="vehicleType">
            </div>

            <div class="form-group">
                <label>Model:</label>
                <input type="text" id="editModel" name="model">
            </div>

            <div class="form-group">
                <label>Manufacturer:</label>
                <input type="text" id="editManufacturer" name="manufacturer">
            </div>

            <div class="form-group">
                <label>Color:</label>
                <input type="text" id="editColor" name="color">
            </div>

            <hr style="margin:15px 0;">
            <div class="form-group">
                <label>Admin Password:</label>
                <input type="password" id="editVehicleAdminPassword" name="adminPassword" required placeholder="Confirm password">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="button" id="saveEdit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Vehicle Details</h3>
        <div id="viewContent"></div>
    </div>
</div>

<div id="deleteVehicleModal" class="modal">
    <div class="modal-content small">
        <h3 class="text-danger">Confirm Deletion</h3>
        <p>Are you sure you want to delete this vehicle? This action cannot be undone.</p>
        <form id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="deleteVehiclePlateNum" name="plateNum">
            <div class="form-group">
                <label>Admin Password:</label>
                <input type="password" id="deleteVehicleAdminPassword" name="adminPassword" required placeholder="Enter password">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel">Cancel</button>
                <button type="button" id="confirmDeleteVehicleBtn" class="btn-danger">Delete Vehicle</button>
            </div>
        </form>
    </div>
</div>

<?php
$db->closeConnection();
include_once '../includes/footer.php';
?>