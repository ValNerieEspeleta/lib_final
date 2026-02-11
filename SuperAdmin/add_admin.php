<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Manila');

include '../includes/session.php';
include '../includes/dbcon.php';

// Define library staff roles
$roles = array(
    6 => 'Library Consultant',
    7 => 'Library Assistant',
    8 => 'Library Staff',
    9 => 'Library Technician'
);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form inputs safely
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname  = mysqli_real_escape_string($conn, $_POST['lastname']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $password  = mysqli_real_escape_string($conn, $_POST['password']);
    $role_id_input   = (int)$_POST['role_id'];
    
    // Hash the password using bcrypt
    $hashed_password = password_hash($password, PASSWORD_BCRYPT); // Or PASSWORD_DEFAULT

    
    // Check if role is valid
    if (array_key_exists($role_id_input, $roles)) {
        
        // Handle File Upload
        $imagePath = NULL;
        if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] == 0) {
            $targetDir = "../uploads/";
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            // Create unique filename
            $fileName = time() . "_" . basename($_FILES["profile_pic"]["name"]);
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
            
            // Allow certain file formats
            $allowedTypes = array("jpg", "jpeg", "png", "gif");
            if (in_array($fileType, $allowedTypes)) {
                if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetFilePath)) {
                    $imagePath = $targetFilePath;
                }
            }
        }
        
        $imagePathValue = $imagePath ? "'$imagePath'" : 'NULL';

        // Insert into employees table
        // Note: Storing integer role_id based on schema convention for admins
        $sql = "INSERT INTO employees (firstname, lastname, email, password, role_id, profile_pic, assigned_course) 
                VALUES ('$firstname', '$lastname', '$email', '$hashed_password', '$role_id_input', $imagePathValue, NULL)";
        
        if (mysqli_query($conn, $sql)) {
            $employee_id = mysqli_insert_id($conn);
            echo "<script>alert('Staff member added successfully! Employee ID: " . $employee_id . "'); window.location.href='add_admin.php';</script>";
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    } else {
        echo "<script>alert('Invalid role selected!'); window.location.href='add_admin.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <?php include "partials/head.php";?>
</head>
<body>
  <div class="container-scroller">
    <!-- partial:partials/_navbar.html -->
    <?php include "partials/navbar.php";?>
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_settings-panel.html -->
      <?php include "partials/settings-panel.php";?>
     
      <!-- partial -->
      <!-- partial:partials/_sidebar.html -->
      <?php include "partials/sidebar.php";?>
      <!-- partial -->
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">
                  <h4 class="card-title">Add Staff Member</h4>
                  <p class="card-description">Fill out the form to add a new staff member</p>
                  <form class="forms-sample" method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                      <label for="firstname">First Name</label>
                      <input type="text" class="form-control" id="firstname" name="firstname" placeholder="First Name" required>
                    </div>
                    <div class="form-group">
                      <label for="lastname">Last Name</label>
                      <input type="text" class="form-control" id="lastname" name="lastname" placeholder="Last Name" required>
                    </div>
                    <div class="form-group">
                      <label for="email">Email address</label>
                      <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                    </div>
                    <div class="form-group">
                      <label for="role_id">Role</label>
                      <select class="form-control" id="role_id" name="role_id" required>
                        <option value="">Select a role</option>
                        <?php foreach ($roles as $id => $name): ?>
                          <option value="<?php echo $id; ?>">
                            <?php echo htmlspecialchars($name); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group">
                      <label for="password">Password</label>
                      <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    </div>
                    
                    <div class="form-group">
                      <label>Profile Picture</label>
                      <input type="file" name="profile_pic" class="form-control file-upload-info" placeholder="Upload Image" accept="image/*">
                      <small class="form-text text-muted">Allowed formats: JPG, JPEG, PNG, GIF</small>
                    </div>

                    <button type="submit" class="btn btn-primary mr-2">Submit</button>
                    <button type="reset" class="btn btn-light">Cancel</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
        <?php include 'partials/footer.php'; ?>
        <!-- partial -->
      </div>
      <!-- main-panel ends -->
    </div>   
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <!-- plugins:js -->
  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script src="static/vendors/datatables.net/jquery.dataTables.js"></script>
  <script src="static/vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
  <script src="static/js/dataTables.select.min.js"></script>

  <!-- End plugin js for this page -->
  <!-- inject:js -->
  <script src="static/js/off-canvas.js"></script>
  <script src="static/js/hoverable-collapse.js"></script>
  <script src="static/js/template.js"></script>
  <script src="static/js/settings.js"></script>
  <script src="static/js/todolist.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
  <script src="static/js/dashboard.js"></script>
  <script src="static/js/Chart.roundedBarCharts.js"></script>
  <!-- End custom js for this page-->
</body>

</html>
