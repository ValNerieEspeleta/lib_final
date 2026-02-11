<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require '../includes/session.php';
require '../includes/dbcon.php';

$statusMsg = ""; // For status messages

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uid       = mysqli_real_escape_string($conn, $_POST['uid']);
    $studentId = mysqli_real_escape_string($conn, $_POST['student_id'] ?? '');
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $year      = isset($_POST['year']) ? mysqli_real_escape_string($conn, $_POST['year']) : "";
    $section   = isset($_POST['section']) ? mysqli_real_escape_string($conn, $_POST['section']) : "";
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $regType   = mysqli_real_escape_string($conn, $_POST['registration_type']);
    $course    = isset($_POST['course']) ? mysqli_real_escape_string($conn, $_POST['course']) : "";
    $address   = isset($_POST['address']) ? mysqli_real_escape_string($conn, $_POST['address']) : "";
    $date_created = date("Y-m-d H:i:s");

    $imagePath = "";
    if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] == 0) {
        $targetDir = "../uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = time() . "_" . basename($_FILES["profile_image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = array("jpg","jpeg","png","gif");
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $targetFilePath)) {
                $imagePath = $targetFilePath;
            }
        }
    }

    if ($regType === "Student" || $regType === "Elementary" || $regType === "Junior High" || $regType === "Senior High") {
      // Server-side validation: student ID is required for students
      if (empty($studentId)) {
        $error = urlencode('Student ID is required for Student registration.');
        header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
        exit();
      }
      
        // Check for duplicate Student ID or RFID in STUDENTS table
        $checkDup = mysqli_query($conn, "SELECT student_id, first_name, last_name, rfid_number FROM students WHERE student_id = '$studentId' OR rfid_number = '$uid' LIMIT 1");
        if (mysqli_num_rows($checkDup) > 0) {
            $errRow = mysqli_fetch_assoc($checkDup);
            if ($errRow['student_id'] == $studentId) {
                $errMsg = "Student ID ($studentId) is already taken by " . $errRow['first_name'] . " " . $errRow['last_name'];
            } else {
                $errMsg = "RFID Number is already registered to student: " . $errRow['first_name'] . " " . $errRow['last_name'];
            }
            $error = urlencode($errMsg);
            header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
            exit();
        }
        
        // Check for duplicate RFID in EMPLOYEES table (Students cannot share RFID with staff)
        $checkDupEmp = mysqli_query($conn, "SELECT firstname, lastname FROM employees WHERE rfid_number = '$uid' LIMIT 1");
        if (mysqli_num_rows($checkDupEmp) > 0) {
             $empRow = mysqli_fetch_assoc($checkDupEmp);
             $errMsg = "RFID Number is already registered to employee: " . $empRow['firstname'] . " " . $empRow['lastname'];
             $error = urlencode($errMsg);
             header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
             exit();
        }

        // Get grade and strand
      $grade = isset($_POST['grade']) && !empty($_POST['grade']) ? mysqli_real_escape_string($conn, $_POST['grade']) : NULL;
      $strand = isset($_POST['strand']) && !empty($_POST['strand']) ? mysqli_real_escape_string($conn, $_POST['strand']) : NULL;
      $department = $regType; // Use the registration type as department
      
      // Validation based on level
      if ($regType === "Student") { // College
          if (empty($year) || empty($section) || empty($course)) {
            $error = urlencode('Please fill Year, Section, and Course for College students.');
            header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
            exit();
          }
      } elseif ($regType === "Elementary" || $regType === "Junior High") {
          if (empty($grade) || empty($section)) {
            $error = urlencode('Please fill Grade and Section.');
            header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
            exit();
          }
      } elseif ($regType === "Senior High") {
          if (empty($grade) || empty($section) || empty($strand)) {
            $error = urlencode('Please fill Grade, Section, and Strand.');
            header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
            exit();
          }
      }
        
        // Generate a default password
        $defaultPassword = 'student123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        
        $eligibleStatus = 1;
        
        // Prepare NULL-safe values
        $yearVal = (!empty($year) && intval($year) > 0) ? intval($year) : 'NULL';
        
        $sectionVal = 'NULL';
        if (!empty($section)) {
            $sName = mysqli_real_escape_string($conn, $section);
            $yId = ($yearVal !== 'NULL') ? intval($yearVal) : 0;
            // Map section to ID or save name if needed. For now, let's just save names or manage sections.
            // Using existing logic to maintain compatibility
            if ($regType === "Student") {
                $checkSection = @mysqli_query($conn, "SELECT section_id FROM sections WHERE section_name = '$sName' AND level = '$yId' LIMIT 1");
                if ($checkSection && $rowS = mysqli_fetch_assoc($checkSection)) {
                    $sectionVal = $rowS['section_id'];
                } else {
                    $insertSec = @mysqli_query($conn, "INSERT INTO sections (section_name, level) VALUES ('$sName', '$yId')");
                    if ($insertSec) $sectionVal = mysqli_insert_id($conn);
                }
            } else {
                // For non-college, section might just be a string name in section_id if not using standard levels
                // But let's try to handle it consistently
                $sectionVal = "'$sName'"; // Store as string if possible or update schema
                // Actually most tables use INT for section_id. I'll stick to string insertion if that's what the DB allows or use a mapping.
                // The current schema uses sections table. I'll just save the name for now if I can't find an ID.
            }
        }
        
        $gradeVal = ($grade !== NULL) ? "'$grade'" : 'NULL';
        $strandVal = ($strand !== NULL) ? "'" . $strand . "'" : 'NULL';
        $courseVal = !empty($course) ? "'$course'" : 'NULL';
        
        $sql = "INSERT INTO students 
          (student_id, rfid_number, first_name, last_name, year_id, section_id, email, address, 
           profile_picture, status, eligible_status, created_at, password, grade, strand, course, department) 
          VALUES 
          ('$studentId', '$uid', '$firstname', '$lastname', $yearVal, $sectionVal, '$email', '$address', 
           '$imagePath', 'active', $eligibleStatus, '$date_created', '$hashedPassword', $gradeVal, $strandVal, $courseVal, '$department')";
    } elseif ($regType === "Teaching" || $regType === "Nonteaching") {
        // Insert into employees table for teaching/non-teaching staff
        $defaultPassword = 'employee123';
        $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
        $assignedCourse = mysqli_real_escape_string($conn, $_POST['assigned_course'] ?? '');
        
        // Determine role name
        $roleName = ($regType === "Teaching") ? "Teaching Staff" : "Non-Teaching Staff";
        
        // Fetch role_id from database
        $roleResult = mysqli_query($conn, "SELECT role_id FROM roles WHERE role_name = '$roleName' LIMIT 1");
        if ($roleResult && mysqli_num_rows($roleResult) > 0) {
            $roleRow = mysqli_fetch_assoc($roleResult);
            $roleId = $roleRow['role_id'];
        } else {
            // Fallback if role doesn't exist (though user said it does)
            // Try to find ANY 'Staff' role or default to 2
             $roleId = 2; 
        }
        
        $sql = "INSERT INTO employees 
                (firstname, lastname, email, address, password, role_id, profile_pic, assigned_course, rfid_number) 
                VALUES 
                ('$firstname', '$lastname', '$email', '$address', '$hashedPassword', $roleId, '$imagePath', '$assignedCourse', '$uid')";
    }

    if (mysqli_query($conn, $sql)) {
        $updateRFID = "UPDATE lib_rfid_auth SET inuse = 1 WHERE uid = '$uid'";
        mysqli_query($conn, $updateRFID);
        header("Location: ".$_SERVER['PHP_SELF']."?status=success");
        exit();
    } else {
        $error = urlencode(mysqli_error($conn));
        header("Location: ".$_SERVER['PHP_SELF']."?status=error&msg=$error");
        exit();
    }
}

if (isset($_GET['status'])) {
    if ($_GET['status'] == "success") {
        $statusMsg = '<div class="alert alert-success">✅ Registered successfully!</div>';
    } elseif ($_GET['status'] == "error" && isset($_GET['msg'])) {
        $statusMsg = '<div class="alert alert-danger">❌ Error: '.htmlspecialchars($_GET['msg']).'</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
  <style>
  #loading-spinner {
    display: none;
    text-align: center;
    margin-top: 15px;
  }
  .spinner-border {
    width: 2rem;
    height: 2rem;
    border: 0.25em solid #ccc;
    border-top: 0.25em solid #007bff;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-bottom: 10px;
  }
  @keyframes spin {
    100% { transform: rotate(360deg); }
  }
  .card-header-custom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 15px;
  }
  .dropdown-select {
    width: 200px;
  }
  </style>
</head>
<body>
  <div class="container-scroller">
    <?php include "partials/navbar.php";?>
    <div class="container-fluid page-body-wrapper">
      <?php include "partials/settings-panel.php";?>
      <?php include "partials/sidebar.php";?>

      <div class="main-panel">
        <div class="content-wrapper">

          <?php if ($statusMsg) echo $statusMsg; ?>

          <form class="forms-sample w-100" method="POST" action="" enctype="multipart/form-data">
            <div class="row">

              <div class="col-md-4 grid-margin stretch-card">
                <div class="card" id="rfid-card">
                  <div class="card-body text-center">
                    <h4 class="card-title">Scan RFID Card</h4>
                    <p class="card-description">Tap an RFID card to generate UID</p>
                    <div id="rfid-animation" class="mt-3">
                      <div id="rfid-circle" class="rfid-circle green"></div>
                      <div class="rfid-check">✔</div>
                      <p id="rfid-status" class="text-success font-weight-bold mt-2">Card Scanned!</p>
                    </div>
                    <?php include 'partials/spinner.php'; ?>
                    <div class="form-group">
                      <label for="uid">UID</label>
                      <p id="uid-display" class="form-control text-center font-weight-bold" 
                         style="background:#f8f9fa;">Waiting for scan...</p>
                      <input type="hidden" id="uid" name="uid" required>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-md-8 grid-margin stretch-card">
                <div class="card">
                  <div class="card-body">
                    <div class="card-header-custom">
                      <h4 class="card-title">Registration Form</h4>
                      <select class="form-control dropdown-select" id="registration-type" name="registration_type" required>
                          <option value="" disabled selected>Choose first</option>
                          <option value="Elementary">Elementary</option>
                          <option value="Junior High">Junior High (IHS)</option>
                          <option value="Senior High">Senior High</option>
                          <option value="Student">College</option>
                          <option value="Teaching">Teaching</option>
                          <option value="Nonteaching">Nonteaching</option>
                        </select>

                    </div>
                    <p class="card-description">Fill out the form to register</p>

                    <div class="alert alert-info" role="alert" style="font-size: 0.9rem;">
                      <strong>📝 Note:</strong> Fill out the fields based on the selected registration type.
                    </div>

                    <div class="form-group" id="student-id-group" style="display:none;">
                      <label for="student_id">Student ID</label>
                      <input type="text" class="form-control" id="student_id" name="student_id" placeholder="Enter Student ID" disabled>
                    </div>
                    <div class="form-group">
                      <label for="firstname">First Name</label>
                      <input type="text" class="form-control" id="firstname" name="firstname" placeholder="First Name" required disabled>
                    </div>
                    <div class="form-group">
                        <label for="lastname">Last Name</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Last Name" required disabled>
                    </div>

                    <div class="row" id="year-section-group">
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="course">Course <span id="course-required-badge" class="badge badge-danger" style="display:none;">Required if College</span></label>
                          <input type="text" class="form-control" id="course" name="course" placeholder="e.g. BSIT, BSN" disabled>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="year">Year <span id="year-required-badge" class="badge badge-danger" style="display:none;">Required if College</span></label>
                          <select class="form-control" id="year" name="year" disabled>
                            <option value="" disabled selected>Select Year</option>
                            <?php 

                              $yq = "SELECT year_id, year_name FROM year_levels ORDER BY year_id ASC";
                              $yr = @mysqli_query($conn, $yq);
                              if ($yr) {
                                  while($y = mysqli_fetch_assoc($yr)) {
                                    echo '<option value="'.$y['year_id'].'">'.htmlspecialchars($y['year_name']).'</option>';
                                  }
                              } else {
                                  // Fallback if table missing
                                  echo '<option value="1">1st Year</option>';
                                  echo '<option value="2">2nd Year</option>';
                                  echo '<option value="3">3rd Year</option>';
                                  echo '<option value="4">4th Year</option>';
                              }
                            ?>
                          </select>
                        </div>
                      </div>
                      <div class="col-md-4">
                        <div class="form-group">
                          <label for="section">Section <span id="section-required-badge" class="badge badge-danger" style="display:none;">Required</span></label>
                          <input type="text" class="form-control" id="section" name="section" placeholder="e.g. A, B, C, or D" disabled>
                        </div>
                      </div>
                    </div>

                    <div class="row" id="grade-strand-group" style="display:none;">
                      <div class="col-md-6">
                        <div class="form-group">
                          <label for="grade">Grade <span id="grade-required-badge" class="badge badge-danger">Required</span></label>
                          <select class="form-control" id="grade" name="grade" disabled>
                            <option value="" disabled selected>Select Grade</option>
                            <!-- Grades will be populated by JS based on level -->
                          </select>
                        </div>
                      </div>
                      <div class="col-md-6" id="strand-col" style="display:none;">
                        <div class="form-group">
                          <label for="strand">Strand <span id="strand-required-badge" class="badge badge-danger">Required (SHS)</span></label>
                          <select class="form-control" id="strand" name="strand" disabled>
                            <option value="" disabled selected>Select Strand</option>
                            <option value="ABM">ABM (Accounting, Business & Management)</option>
                            <option value="TVL">TVL (Technical-Vocational Livelihood)</option>
                            <option value="STEM">STEM (Science, Technology, Engineering & Math)</option>
                            <option value="HUMS">HUMS (Humanities & Social Sciences)</option>
                          </select>
                        </div>
                      </div>
                    </div>

                    <div class="form-group" id="assigned-course-group" style="display:none;">
                        <label for="assigned_course">Department</label>
                        <input type="text" class="form-control" id="assigned_course" name="assigned_course" placeholder="e.g. Computer Science, Nursing" disabled>
                    </div>

                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="Email" required disabled>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" class="form-control" id="address" name="address" placeholder="Address" required disabled>
                    </div>

                    <div class="form-group">
                        <label for="profile_image">Profile Picture</label>
                        <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*" disabled>
                    </div>

                    <div class="form-group" id="eligible-status-group">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="eligible_status" name="eligible_status" value="1" checked>
                            <label class="form-check-label" for="eligible_status">Eligible for Borrowing</label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mr-2" disabled id="submit-btn">Submit</button>
                    <button type="reset" class="btn btn-light">Cancel</button>
                  </div>
                </div>
              </div>

            </div>
          </form>

        </div>
        <?php include 'partials/footer.php'; ?>
      </div>
    </div>   
  </div>

  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="static/vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="static/vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <script src="static/js/dataTables.select.min.js"></script>
  <script src="static/js/off-canvas.js"></script>
  <script src="static/js/hoverable-collapse.js"></script>
  <script src="static/js/template.js"></script>
  <script src="static/js/settings.js"></script>
  <script src="static/js/todolist.js"></script>
  <script src="static/js/dashboard.js"></script>
  <script src="static/js/Chart.roundedBarCharts.js"></script>

  <script>
  const uidInput = document.getElementById("uid");
  const uidDisplay = document.getElementById("uid-display");
  const rfidAnimation = document.getElementById("rfid-animation");
  const rfidCircle = document.getElementById("rfid-circle");
  const rfidStatus = document.getElementById("rfid-status");
  const loadingSpinner = document.getElementById("loading-spinner");

  const firstname = document.getElementById("firstname");
  const lastname = document.getElementById("lastname");
  const studentId = document.getElementById("student_id");
  const studentIdGroup = document.getElementById("student-id-group");
  const year = document.getElementById("year");
  const section = document.getElementById("section");
  const grade = document.getElementById("grade");
  const strand = document.getElementById("strand");
  const email = document.getElementById("email");
  const address = document.getElementById("address");
  const assignedCourse = document.getElementById("assigned_course");
  const profileImage = document.getElementById("profile_image");
  const submitBtn = document.getElementById("submit-btn");
  const regType = document.getElementById("registration-type");
  const yearSectionGroup = document.getElementById("year-section-group");
  const gradStrandGroup = document.getElementById("grade-strand-group");
  const assignedCourseGroup = document.getElementById("assigned-course-group");
  const eligibleStatusGroup = document.getElementById("eligible-status-group");
  const eligibleStatus = document.getElementById("eligible_status");
  const strandCol = document.getElementById("strand-col");

  let scanning = false;
  let buffer = "";

  // Toggle fields based on registration type
  regType.addEventListener("change", () => {
    const val = regType.value;
    const isStudent = ['Student', 'Elementary', 'Junior High', 'Senior High'].includes(val);
    const isEmployee = val === 'Teaching' || val === 'Nonteaching';
    
    // Enable common fields if a type is selected
    const inputs = document.querySelectorAll('#student_id, #firstname, #lastname, #email, #address, #profile_image');
    inputs.forEach(input => input.disabled = !val);
    
    // College specific
    yearSectionGroup.style.display = (val === 'Student') ? 'flex' : 'none';
    if (val === 'Student') {
        document.getElementById('course').disabled = false;
        document.getElementById('course').required = true;
        year.disabled = false;
        year.required = true;
        section.disabled = false;
        section.required = true;
    }

    // Elementary / JHS / SHS specific
    gradStrandGroup.style.display = (['Elementary', 'Junior High', 'Senior High'].includes(val)) ? 'flex' : 'none';
    if (['Elementary', 'Junior High', 'Senior High'].includes(val)) {
        grade.disabled = false;
        grade.required = true;
        section.disabled = false;
        section.required = true;
        yearSectionGroup.style.display = 'flex'; // Show it for Section input
        document.getElementById('course').disabled = true;
        document.getElementById('course').parentElement.parentElement.style.display = 'none';
        year.disabled = true;
        year.parentElement.parentElement.style.display = 'none';
        
        // Populate Grades
        grade.innerHTML = '<option value="" disabled selected>Select Grade</option>';
        if (val === 'Elementary') {
            ['Senior Kinder', '1', '2', '3', '4', '5', '6'].forEach(g => {
                grade.innerHTML += `<option value="${g}">${g === 'Senior Kinder' ? g : 'Grade ' + g}</option>`;
            });
            strandCol.style.display = 'none';
            strand.required = false;
        } else if (val === 'Junior High') {
            ['7', '8', '9', '10'].forEach(g => {
                grade.innerHTML += `<option value="${g}">Grade ${g}</option>`;
            });
            strandCol.style.display = 'none';
            strand.required = false;
        } else if (val === 'Senior High') {
            ['11', '12'].forEach(g => {
                grade.innerHTML += `<option value="${g}">Grade ${g}</option>`;
            });
            strandCol.style.display = 'block';
            strand.disabled = false;
            strand.required = true;
        }
    } else {
        // Reset visibility for College or Staff
        document.getElementById('course').parentElement.parentElement.style.display = 'block';
        year.parentElement.parentElement.style.display = 'block';
    }

    // Student ID
    studentIdGroup.style.display = isStudent ? 'block' : 'none';
    studentId.required = isStudent;
    
    // Employee assigned course
    assignedCourseGroup.style.display = isEmployee ? 'block' : 'none';
    assignedCourse.disabled = !isEmployee;
    assignedCourse.required = isEmployee;
    
    eligibleStatusGroup.style.display = isStudent ? 'block' : 'none';
    
    if (uidInput.value) submitBtn.disabled = !val;
  });

  document.querySelector('form').addEventListener('submit', function(e) {
    const val = regType.value;
    if (['Student', 'Elementary', 'Junior High', 'Senior High'].includes(val)) {
        if (!studentId.value || !firstname.value || !lastname.value || !section.value) {
            e.preventDefault();
            alert('Please fill out all required fields.');
        }
    }
  });

  document.addEventListener("keydown", function(e) {
    if (!scanning) {
      buffer = "";
      scanning = true;
    }

    if (e.key === "Enter") {
      e.preventDefault();
      if (buffer.trim() !== "") {
        const uid = buffer.trim();
        uidInput.value = uid;
        uidDisplay.textContent = uid;

        loadingSpinner.style.display = "block";
        rfidAnimation.style.display = "none";

        setTimeout(() => {
          fetch("validate_uid.php?uid=" + uid)
            .then(res => res.json())
            .then(data => {
              loadingSpinner.style.display = "none";
              rfidAnimation.style.display = "block";

              if (data.status === "valid") {
                rfidCircle.classList.remove("red");
                rfidCircle.classList.add("green");
                rfidStatus.textContent = "✅ Valid Card!";
                rfidStatus.classList.remove("text-danger");
                rfidStatus.classList.add("text-success");

                firstname.disabled = false;
                lastname.disabled = false;
                year.disabled = false;
                section.disabled = false;
                document.getElementById('course').disabled = false;
                grade.disabled = false;
                strand.disabled = false;
                email.disabled = false;
                address.disabled = false;
                if (studentId) studentId.disabled = (regType.value !== 'Student');
                profileImage.disabled = false;
                submitBtn.disabled = false;
              } else {
                rfidCircle.classList.remove("green");
                rfidCircle.classList.add("red");
                rfidStatus.textContent = "❌ Invalid Card!";
                rfidStatus.classList.remove("text-success");
                rfidStatus.classList.add("text-danger");

                firstname.disabled = true;
                lastname.disabled = true;
                year.disabled = true;
                section.disabled = true;
                document.getElementById('course').disabled = true;
                grade.disabled = true;
                strand.disabled = true;
                email.disabled = true;
                address.disabled = true;
                if (studentId) studentId.disabled = true;
                profileImage.disabled = true;
                submitBtn.disabled = true;
              }
            });
        }, 2000);
      }
      scanning = false;
      buffer = "";
    } else {
      if (e.key.length === 1) {
        buffer += e.key;
      }
    }
  });
  </script>

</body>
</html>
