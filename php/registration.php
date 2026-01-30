<?php
// Include database connection
require_once 'dbConnection.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registration Page</title>
  <link rel="stylesheet" href="../css/registration.css" />
  <link rel="stylesheet" href="../css/responsive.css" />
  <script src="../js/responsive.js"></script>
  <script src="../js/registration.js" defer></script>
 <script>
    // Logic to handle conditional fields based on role selection and contact number validation
    document.addEventListener('DOMContentLoaded', function() {
        const userTypeRadios = document.getElementsByName('userType');
        const employmentField = document.getElementById('employmentTypeField');
        const employmentSelect = employmentField.querySelector('select');
        const yearLevelField = document.getElementById('yearLevelField');
        const sectionField = document.getElementById('sectionField');
        const contactInput = document.getElementById('contactNum');
        const contactError = document.getElementById('contactError');
        const registrationForm = document.getElementById('registrationForm');

        // Function to toggle fields based on User Type
        function toggleFields() {
            const checkedRadio = document.querySelector('input[name="userType"]:checked');
            if (!checkedRadio) return; // Exit if no radio is selected yet

            const selectedType = checkedRadio.value;
            
            if (selectedType === 'student') {
                employmentField.classList.add('hidden');
                employmentSelect.required = false;
                employmentSelect.value = "";
                
                yearLevelField.classList.remove('hidden');
                sectionField.classList.remove('hidden');
            } else {
                // Faculty or Non-Teaching
                employmentField.classList.remove('hidden');
                employmentSelect.required = true;
                
                yearLevelField.classList.add('hidden');
                sectionField.classList.add('hidden');
            }
        }

        // Add event listeners for role selection
        userTypeRadios.forEach(radio => {
            radio.addEventListener('change', toggleFields);
        });

        // Contact number real-time numeric validation
        if (contactInput) {
            contactInput.addEventListener('input', function() {
                const originalValue = this.value;
                // Use regex to remove any character that is not a number (0-9)
                const cleanedValue = this.value.replace(/[^0-9]/g, '');
                
                this.value = cleanedValue;

                // Show error message if invalid characters were stripped
                if (originalValue !== cleanedValue) {
                    if (contactError) {
                        contactError.style.display = 'block';
                        // Automatically hide the error message after 2 seconds
                        setTimeout(() => {
                            contactError.style.display = 'none';
                        }, 2000);
                    }
                } else {
                    if (contactError) contactError.style.display = 'none';
                }
            });
        }
        
        toggleFields();
    });
</script>
</head>

<body>
  <header class="header">
    <div class="logo-title">
      <span class="site-name">
        <h3>
          <span class="highlight-blue">Vehicle Registration</span>
        </h3>
      </span>
    </div>
  </header>

  <div class="container">
    <h2><span class="highlight">Vehicle</span> <span>Registration</span></h2>
    <p>
      Please fill out the form completely and upload the required documents.
    </p>

    <form id="registrationForm" method="POST" enctype="multipart/form-data">
      <section>
        <h3>User Information</h3>
        <div class="checkbox-group">
          <label>
            <input type="radio" name="userType" value="student" required />
            Student
          </label>
          <label>
            <input type="radio" name="userType" value="faculty" required />
            Faculty
          </label>
          <label>
            <input type="radio" name="userType" value="non-teaching" required />
            Non-Teaching Personnel
          </label>
        </div>

        <div class="grid-3">
          <div>
            <label>First Name</label>
            <input type="text" name="firstName" placeholder="Enter your first name" required />
          </div>
          <div>
            <label>Last Name</label>
            <input type="text" name="lastName" placeholder="Enter your last name" required />
          </div>
          <div>
            <label>Middle Name</label>
            <input type="text" name="middleName" placeholder="Enter your middle name" />
          </div>
        </div>

        <div class="grid-3">
          <div>
            <label>School ID</label>
            <input type="text" name="schoolID" placeholder="2026-0000-A" required />
          </div>
          <div>
            <label>Email Address</label>
            <input type="email" name="email" placeholder="Enter your email address" required />
          </div>
            <div>
              <label>Contact Number</label>
              <input type="text" 
                    name="contactNum" 
                    id="contactNum" 
                    placeholder="Enter your contact number" 
                    required 
                    inputmode="numeric">
              <span id="contactError" style="color: red; font-size: 0.8em; display: none;">
                  invalid input only numbers
              </span>
          </div>
        </div>

        <div class="grid-3">
          <div>
            <label>College</label>
            <select name="college" required>
              <option value="" disabled selected>Select College</option>
              <option value="CAS">(CAS) College of Arts and Sciences</option>
              <option value="CEA">(CEA) College of Engineering and Architecture</option>
              <option value="CCI">(CCI) College of Computing in Informatics</option>
              <option value="COE">(COE) College of Education</option>
              <option value="CIT">(CIT) College of Industrial Technology</option>
            </select>
          </div>
          <div id="employmentTypeField" class="hidden">
            <label>Employment Status</label>
            <select name="employment_type">
              <option value="" disabled selected>Select Employment Type</option>
              <option value="permanent">Permanent</option>
              <option value="job_hire">Job Hire</option>
              <option value="part_time">Part-time</option>
            </select>
          </div>
          <div id="courseField">
            <label>Course</label>
            <select name="course" id="courseSelect" required>
              <option value="" disabled selected>Select Course</option>
            </select>
          </div>
        </div>

        <div class="grid-3">
          <div id="academicYearField">
            <label>Academic Year</label>
            <select name="academicYear" required>
              <option value="" disabled selected>Select Academic Year</option>
              <option value="2025-2026">2025-2026</option>
              <option value="2026-2027">2026-2027</option>
            </select>
          </div>
          <div id="yearLevelField">
            <label>Year Level (For Students)</label>
            <select name="yearLevel">
              <option value="" disabled selected>Select Year Level</option>
              <option value="1st">1st Year</option>
              <option value="2nd">2nd Year</option>
              <option value="3rd">3rd Year</option>
              <option value="4th">4th Year</option>
              <option value="5th">5th Year</option>
            </select>
          </div>
          <div id="sectionField">
            <label>Section</label>
            <input type="text" name="section" placeholder="Enter your section" />
          </div>
        </div>

        <div class="upload-box">
          <label>Upload Scanned Copy of Driver's License</label>
          <div class="upload-area">
            Drag and drop files here or
            <span class="browse">click to browse</span>
            <input type="file" name="driversLicense" accept="image/*,application/pdf" class="hidden" required />
          </div>
        </div>
      </section>

      <div id="additionalDriverContainer" class="additional-driver-wrapper">
        <button type="button" class="add-driver-btn" id="addDriverBtn">+ Add Additional Driver</button>

        <section class="additional-driver-section hidden" id="additionalDriverSection">
          <div class="section-header">
            <h3>Additional Driver (Optional)</h3>
            <button type="button" class="btn-delete-vehicle" id="removeDriverBtn">Remove</button>
          </div>
          <p>You may register one additional driver who is authorized to use your vehicle.</p>

          <div class="grid-2">
            <div>
              <label>Additional Driver Name</label>
              <input type="text" name="additionalDriverName" id="additionalDriverName"
                placeholder="Enter full name of additional driver" />
            </div>
            <div>
              <label>Relationship to Vehicle Owner</label>
              <select name="additionalDriverRelationship" id="additionalDriverRelationship">
                <option value="" disabled selected>Select Relationship</option>
                <option value="Spouse">Spouse</option>
                <option value="Child">Child</option>
                <option value="Parent">Parent</option>
                <option value="Sibling">Sibling</option>
                <option value="Relative">Other Relative</option>
                <option value="Friend">Friend</option>
                <option value="Employee">Employee</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>
        </section>
      </div>
      <div id="vehicle-sections">
        <section class="vehicle-section">
          <div class="section-header">
            <h3>Vehicle Information</h3>
            <button type="button" class="btn-delete-vehicle">Remove</button>
          </div>

          <div class="grid-3">
            <div class="vehicle-type-group">
              <label>Vehicle Type</label>
              <select name="vehicleType[]" class="vehicle-type-select" required>
                <option value="" disabled selected>Select Vehicle Type</option>
                <option value="Car">Car</option>
                <option value="Motorcycle">Motorcycle</option>
                <option value="Other">Other</option>
              </select>
              <input type="text" name="otherVehicleType[]" class="other-vehicle-type hidden"
                placeholder="Specify Vehicle Type" disabled />
            </div>
            <div>
              <label>Manufacturer</label>
              <input type="text" name="manufacturer[]" placeholder="Enter manufacturer" required />
            </div>
            <div>
              <label>Model</label>
              <input type="text" name="model[]" placeholder="Enter model" required />
            </div>
          </div>

          <div class="grid-3">
            <div>
              <label>Color</label>
              <input type="text" name="color[]" placeholder="Enter vehicle color" required />
            </div>
            <div>
              <label>Plate Number</label>
              <input type="text" name="plateNumber[]" placeholder="Enter plate number" required
                oninput="this.value = this.value.replace(/\s/g, '')" />
            </div>
            <div>
              <label>Number of Wheels</label>
              <select name="numWheels[]" class="num-wheels-select" required disabled>
                <option value="" disabled selected>Select Number of Wheels</option>
                <option value="2">2 Wheels</option>
                <option value="3">3 Wheels</option>
                <option value="4">4 Wheels</option>
              </select>
              <input type="number" name="numWheels[]" class="num-wheels-input hidden" placeholder="Enter # of wheels"
                disabled />
            </div>
          </div>

          <div class="grid-3">
            <div>
              <label>Fuel Type</label>
              <select name="fuelType[]" required>
                <option value="" disabled selected>Select Fuel Type</option>
                <option value="Gasoline">Gasoline</option>
                <option value="Diesel">Diesel</option>
                <option value="Electric">Electric</option>
                <option value="Hybrid">Hybrid</option>
              </select>
            </div>
            <div>
              <label>Cubic Capacity (For Motorcycles)</label>
              <input type="text" name="cubicCapacity[]" placeholder="Enter cubic capacity (cc)" required disabled />
            </div>
          </div>

          <div class="upload-box">
            <label>Upload Scanned Copy of Official Receipt (OR)</label>
            <div class="upload-area">
              Drag and drop files here or
              <span class="browse">click to browse</span>
              <input type="file" name="officialReceipt[]" accept="image/*,application/pdf" class="hidden" required />
            </div>
          </div>

          <div class="upload-box">
            <label>Upload Scanned Copy of Certificate of Registration (CR)</label>
            <div class="upload-area">
              Drag and drop files here or
              <span class="browse">click to browse</span>
              <input type="file" name="certRegistration[]" accept="image/*,application/pdf" class="hidden" required />
            </div>
          </div>
        </section>
      </div>
      <button type="button" class="add-btn" id="addVehicleBtn">+ Add More Vehicle</button>

      <div class="agreement">
        <input type="hidden" id="termsCheckbox" name="termsAccepted" required />
        <span id="termsStatusText">Please read and agree to the</span>
        <a href="#" id="termsLink">terms and conditions</a>
        <span id="termsAcceptedIcon" class="check-icon hidden">✓ Accepted</span>
        <span class="required-asterisk">*</span>
      </div>
      <div class="button-row">
        <button type="submit" class="submit-btn">Submit Application</button>
      </div>
    </form>
  </div>

  <div id="termsModal" class="popup">
    <div class="popup-content terms-content-container">
      <div class="terms-header">
        <h2>Vehicle Registration System</h2>
        <p>Gate Pass and Parking Facility Terms and Conditions</p>
        <button onclick="closeTermsModal()" class="close-terms-btn">&times;</button>
      </div>

      <div id="termsContent" class="terms-body">
        <section class="terms-section">
          <h3>
            <span class="terms-number-badge">1</span>
            Scope and Acceptance
          </h3>
          <ul class="terms-list">
            <li><strong>1.1.</strong> These Terms and Conditions
              (T&C) apply to all Faculty, Staff, and Guests (the "Registrant") using the parking facilities within the
              premises.</li>
            <li><strong>1.2.</strong> This policy will be
              implemented starting the First Semester of A.Y. 2025-2026.</li>
            <li><strong>1.3.</strong> Parking spaces are
              allocated on a first-come, first-served basis to registered employees and students. Parking spaces are
              neither guaranteed nor a permanent entitlement.</li>
          </ul>
        </section>

        <section class="terms-section">
          <h3>
            <span class="terms-number-badge">2</span>
            Car Pass Requirement and Registration
          </h3>
          <ul class="terms-list">
            <li><strong>2.1.</strong> <span class="mandatory-highlight">Mandatory Pass: All Registrants must secure and
                possess a
                valid GATE PASS to park within the premises. A "NO GATE PASS, NO ENTRY" policy is strictly
                enforced.</span></li>
            <li><strong>2.2.</strong>
              <div>
                <strong>Registration Requirements:</strong> Issuance of a Gate Pass requires the submission of:
                <ul class="terms-sublist">
                  <li>Photocopy of the Official Receipt (OR) and Certificate of Registration (CR).</li>
                  <li>Photocopy of a valid Driver's License.</li>
                </ul>
              </div>
            </li>
            <li><strong>2.3.</strong> <strong>Validity and
                Renewal:</strong> The Gate Pass privilege is valid only during the Registrant's employment and is
              subject to renewal every year.</li>
            <li><strong>2.4.</strong> <strong>Multiple
                Vehicles:</strong> A Registrant may register multiple vehicles but is authorized to enter the premises
              with only one registered vehicle at a time.</li>
            <li><strong>2.5.</strong>
              <div>
                <strong>Gate Pass Display:</strong> The Gate Pass must be prominently and visibly displayed at all
                times:
                <ul class="terms-sublist">
                  <li>4-Wheeled Vehicles: Upper right-hand corner of the windshield.</li>
                  <li>Motorcycle/E-bikes: Upper left-hand corner of the windshield.</li>
                  <li>Tricycle: Lower left-hand corner of the windshield of the sidecar.</li>
                </ul>
              </div>
            </li>
          </ul>
        </section>

        <section class="terms-section">
          <h3>
            <span class="terms-number-badge">3</span>
            Rules and Regulations
          </h3>
          <div class="rules-container">
            <p><strong>3.1. Speed Limit:</strong> Drivers shall observe a maximum speed
              limit of 5 km/h within the premises.</p>
            <p><strong>3.2. Mufflers:</strong> Vehicles with modified mufflers exceeding
              the allowed LTO sound limit of 99 decibels are prohibited.</p>
            <p><strong>3.3. Maintenance and Repair:</strong> Repairs (except emergency
              repairs like flat tires) are prohibited in parking spaces.</p>
            <p><strong>3.4. Drop-off/Pick-up:</strong> Prohibited inside except for
              emergencies. Use designated area at main gate.</p>
            <div>
              <strong>3.5. Prohibited Parking Violations:</strong>
              <ul class="terms-sublist">
                <li>Parking on a roadside or in "No Parking" areas.</li>
                <li>Blocking a driveway, sidewalk, or path walk (obstruction).</li>
                <li>Parking at or inside an intersection or on a pedestrian crossing.</li>
                <li>Parking a motorcycle/e-bike in a 4-wheeled space, or vice versa.</li>
                <li>Parking with an expired or tampered gate pass.</li>
              </ul>
            </div>
          </div>
        </section>

        <div class="terms-footer">
          <p>End of Document</p>
        </div>
      </div>

      <div class="terms-actions-container">
        <label class="terms-checkbox-label">
          <div class="terms-checkbox-wrapper">
            <input type="checkbox" id="modalTermsCheckbox" class="modal-terms-checkbox"
              onchange="updateCheckboxStyle(this)" />
            <span id="checkmark" class="checkmark">✓</span>
          </div>
          <span class="terms-text">I have read and agree to the terms and
            conditions stated above.</span>
        </label>

        <div class="terms-buttons">
          <button onclick="closeTermsModal()" class="decline-btn">Decline</button>
          <button id="agreeBtn" onclick="agreeToTerms()" disabled class="agree-btn">I
            Agree</button>
        </div>
      </div>
    </div>
  </div>

  <div id="successPopup" class="popup">
    <div class="popup-content">
      <h3>Registration Submitted Successfully!</h3>
      <p>Your application has been received and will be reviewed by an administrator.</p>
      <p>You will receive your unique registration code via email once your application is approved.</p>
      <p id="submissionTiming" class="submission-timing" style="font-size:0.9em;color:#666;margin-top:8px;"></p>
      <button class="popup-btn" onclick="closePopup()">OK</button>
    </div>
  </div>
</body>

</html>