<?php
// Include database connection
include '../includes/session.php';
include '../includes/dbcon.php';

$statusMsg = "";

// ✅ Handle Delete Request
if (isset($_GET['delete_id'])) {
    $deleteId = intval($_GET['delete_id']);
    $deleteQuery = "DELETE FROM employees WHERE employee_id = $deleteId";
    if (mysqli_query($conn, $deleteQuery)) {
        $statusMsg = '<div class="alert alert-success">✅ Staff member deleted successfully!</div>';
    } else {
        $statusMsg = '<div class="alert alert-danger">❌ Error deleting staff: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    }
}

// ✅ Handle Edit/Update Request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_id'])) {
    $editId = intval($_POST['edit_id']);
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $role_id = intval($_POST['role_id']);

    // Handle File Upload
    $imageUpdateSql = "";
    if (isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] == 0) {
        $targetDir = "../uploads/";
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        $fileName = time() . "_" . basename($_FILES["profile_pic"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        
        $allowedTypes = array("jpg", "jpeg", "png", "gif");
        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetFilePath)) {
                $imageUpdateSql = ", profile_pic='$targetFilePath'";
            }
        }
    }

    // If password is not empty, update it with hashing
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $updateQuery = "UPDATE employees SET firstname='$firstname', lastname='$lastname', email='$email', password='$password', role_id=$role_id $imageUpdateSql WHERE employee_id=$editId";
    } else {
        $updateQuery = "UPDATE employees SET firstname='$firstname', lastname='$lastname', email='$email', role_id=$role_id $imageUpdateSql WHERE employee_id=$editId";
    }

    if (mysqli_query($conn, $updateQuery)) {
        $statusMsg = '<div class="alert alert-success">✅ Staff member updated successfully!</div>';
    } else {
        $statusMsg = '<div class="alert alert-danger">❌ Error updating staff: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    }
}

// ✅ Fetch admin records - from employees table with role names
$adminQuery = "SELECT e.employee_id, e.firstname, e.lastname, e.email, e.profile_pic, r.role_name
               FROM employees e
               LEFT JOIN roles r ON e.role_id = r.role_id
               WHERE e.role_id IN (6, 7, 8, 9)
               ORDER BY e.employee_id DESC";
$adminResult = mysqli_query($conn, $adminQuery);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <?php include "partials/head.php";?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .action-icons i {
      cursor: pointer;
      font-size: 1.2rem;
      margin: 0 5px;
    }
    .action-icons i.edit {
      color: #007bff;
    }
    .action-icons i.delete {
      color: #dc3545;
    }
  </style>
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
          <?php if ($statusMsg) echo $statusMsg; ?>
          <div class="row">
            <div class="col-12 grid-margin stretch-card">
              <div class="card shadow-sm rounded-3">
                <div class="card-body">
                  <h4 class="card-title">Staff Members</h4>
                  <div class="table-responsive">
                    <table id="rfidTable" class="table table-hover">
                      <thead>
                        <tr>
                          <th>Profile</th>
                          <th>Full Name</th>
                          <th>Email</th>
                          <th>Role</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($adminResult && mysqli_num_rows($adminResult) > 0): ?>
                          <?php while ($row = mysqli_fetch_assoc($adminResult)): ?>
                            <tr>
                              <td class="py-1">
                                <?php 
                                  $imgSrc = !empty($row['profile_pic']) ? htmlspecialchars($row['profile_pic']) : '../static/images/faces/face1.jpg'; 
                                  // Fallback to a default image if file doesn't exist or is null. You might want to adjust the default path.
                                ?>
                                <img src="<?php echo $imgSrc; ?>" alt="profile" style="width: 40px; height: 40px; border-radius: 100%; object-fit: cover;" onerror="this.onerror=null; this.src='../static/images/faces/face1.jpg';">
                              </td>
                              <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                              <td><?php echo htmlspecialchars($row['email']); ?></td>
                              <td><?php echo htmlspecialchars($row['role_name']); ?></td>
                              <td class="action-icons">
                                <!-- Edit button (opens modal) -->
                                <i class="fa-solid fa-pencil edit" 
                                   data-id="<?php echo $row['employee_id']; ?>"
                                   data-firstname="<?php echo htmlspecialchars($row['firstname']); ?>"
                                   data-lastname="<?php echo htmlspecialchars($row['lastname']); ?>"
                                   data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                   title="Edit"></i>

                                <!-- Delete button -->
                                <a href="?delete_id=<?php echo $row['employee_id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this staff member?');" 
                                   title="Delete">
                                  <i class="fa-solid fa-trash delete"></i>
                                </a>
                              </td>
                            </tr>
                          <?php endwhile; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="5" class="text-center">No staff members found.</td>
                          </tr>
                        <?php endif; ?>
                      </tbody>
                    </table>
                  </div>
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

  <!-- Edit Admin Modal -->
  <div class="modal fade" id="editAdminModal" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title" id="editAdminModalLabel">Edit Staff Member</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="mb-3">
              <label for="firstname" class="form-label">First Name</label>
              <input type="text" class="form-control" name="firstname" id="firstname" required>
            </div>
            <div class="mb-3">
              <label for="lastname" class="form-label">Last Name</label>
              <input type="text" class="form-control" name="lastname" id="lastname" required>
            </div>
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" name="email" id="email" required>
            </div>
            <div class="mb-3">
              <label for="role_id" class="form-label">Role</label>
              <select class="form-control" id="role_id" name="role_id" required>
                <option value="6">Library Consultant</option>
                <option value="7">Library Assistant</option>
                <option value="8">Library Staff</option>
                <option value="9">Library Technician</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password (Leave blank to keep current)</label>
              <input type="password" class="form-control" name="password" id="password">
            </div>
            <div class="mb-3">
              <label for="profile_pic" class="form-label">Profile Picture (Optional)</label>
              <input type="file" class="form-control" name="profile_pic" id="profile_pic" accept="image/*">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Staff Member</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- plugins:js -->
  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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

  <!-- DataTable init -->
  <script>
    $(document).ready(function () {
        $('#rfidTable').DataTable({
            "pageLength": 10,
            "lengthMenu": [5, 10, 25, 50, 100],
            "ordering": false,
            "searching": true,
            "scrollY": "400px",
            "scrollCollapse": true,
            "paging": true
        });

        // Fill modal with current admin data
        $(".edit").click(function() {
            var id = $(this).data("id");
            var firstname = $(this).data("firstname");
            var lastname = $(this).data("lastname");
            var email = $(this).data("email");

            $("#edit_id").val(id);
            $("#firstname").val(firstname);
            $("#lastname").val(lastname);
            $("#email").val(email);
            $("#password").val("");

            var modal = new bootstrap.Modal(document.getElementById('editAdminModal'));
            modal.show();
        });
    });
  </script>
</body>
</html>
