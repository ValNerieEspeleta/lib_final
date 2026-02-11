<?php
include '../includes/session.php';
include '../includes/dbcon.php';

$message = "";

// Handle Staff CRUD
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $facebook = mysqli_real_escape_string($conn, $_POST['facebook']);
    $twitter = mysqli_real_escape_string($conn, $_POST['twitter']);
    $linkedin = mysqli_real_escape_string($conn, $_POST['linkedin']);
    $youtube = mysqli_real_escape_string($conn, $_POST['youtube']);
    
    // Image Handling
    $profile_image = "";
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $target_dir = "../"; // Saving to root for now as per current index.php usage
        $file_extension = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
        $file_name = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '_', $name)) . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
            $profile_image = $file_name;
        }
    }

    if ($action == 'add') {
        $sql = "INSERT INTO lib_library_staff (name, designation, profile_image, facebook, twitter, linkedin, youtube) 
                VALUES ('$name', '$designation', '$profile_image', '$facebook', '$twitter', '$linkedin', '$youtube')";
        if (mysqli_query($conn, $sql)) {
            $message = "<div class='alert alert-success'>Staff added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
        }
    } elseif ($action == 'edit') {
        $id = $_POST['staff_id'];
        $update_fields = "name='$name', designation='$designation', facebook='$facebook', twitter='$twitter', linkedin='$linkedin', youtube='$youtube'";
        if ($profile_image != "") {
            $update_fields .= ", profile_image='$profile_image'";
        }
        $sql = "UPDATE lib_library_staff SET $update_fields WHERE id='$id'";
        if (mysqli_query($conn, $sql)) {
            $message = "<div class='alert alert-success'>Staff updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    mysqli_query($conn, "DELETE FROM lib_library_staff WHERE id='$id'");
    header("Location: manage_staff.php?status=deleted");
    exit();
}

if (isset($_GET['status']) && $_GET['status'] == 'deleted') {
    $message = "<div class='alert alert-success'>Staff deleted successfully!</div>";
}

$staff_list = mysqli_query($conn, "SELECT * FROM lib_library_staff ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --premium-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --glass-bg: rgba(255, 255, 255, 0.9);
      --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }
    body { font-family: 'Outfit', sans-serif !important; background-color: #f0f2f5; }
    .modern-card { background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 20px; box-shadow: var(--card-shadow); overflow: hidden; margin-bottom: 2rem; }
    .modern-card-header { background: var(--premium-gradient); padding: 1.5rem; color: #fff; }
    .staff-img { width: 60px; height: 60px; object-fit: cover; border-radius: 12px; }
    .btn-premium { background: var(--premium-gradient); border: none; border-radius: 12px; padding: 0.8rem; font-weight: 600; color: #fff; transition: all 0.3s ease; }
    .btn-premium:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(118, 75, 162, 0.2); color: #fff; }
    .action-btn { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; border: none; }
    .btn-edit { background: rgba(102, 126, 234, 0.1); color: #667eea; }
    .btn-delete { background: rgba(245, 101, 101, 0.1); color: #f56565; }
    .form-control { border-radius: 10px; padding: 0.75rem; }
  </style>
</head>
<body>
  <div class="container-scroller">
    <?php include "partials/navbar.php";?>
    <div class="container-fluid page-body-wrapper">
      <?php include "partials/sidebar.php";?>
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row mb-4">
            <div class="col-12">
              <h2 class="fw-bold">👥 Library Staff Management</h2>
              <p class="text-muted">Manage the personnel who make the library great.</p>
            </div>
          </div>

          <?php echo $message; ?>

          <div class="row">
            <!-- List Section -->
            <div class="col-lg-8 grid-margin stretch-card">
              <div class="modern-card w-100">
                <div class="modern-card-header">
                  <h5 class="m-0"><i class="fas fa-list me-2"></i> Current Staff Members</h5>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Photo</th>
                          <th>Name</th>
                          <th>Designation</th>
                          <th class="text-end">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php while ($row = mysqli_fetch_assoc($staff_list)): ?>
                        <tr>
                          <td>
                            <img src="../<?php echo htmlspecialchars($row['profile_image'] ?: 'default-avatar.png'); ?>" class="staff-img" alt="">
                          </td>
                          <td><span class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></span></td>
                          <td><?php echo htmlspecialchars($row['designation']); ?></td>
                          <td class="text-end">
                            <button class="action-btn btn-edit me-1" onclick='editStaff(<?php echo json_encode($row); ?>)'>
                              <i class="fas fa-pen"></i>
                            </button>
                            <a href="?delete=<?php echo $row['id']; ?>" class="action-btn btn-delete" onclick="return confirm('Remove this staff member?')">
                              <i class="fas fa-trash"></i>
                            </a>
                          </td>
                        </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Form Section -->
            <div class="col-lg-4 grid-margin stretch-card">
              <div class="modern-card w-100">
                <div class="modern-card-header" style="background: #1e293b;">
                  <h5 class="m-0" id="form-title"><i class="fas fa-user-plus me-2"></i> Add New Staff</h5>
                </div>
                <div class="card-body">
                  <form method="POST" enctype="multipart/form-data" id="staff-form">
                    <input type="hidden" name="action" id="form-action" value="add">
                    <input type="hidden" name="staff_id" id="staff_id">
                    
                    <div class="mb-3">
                      <label class="form-label fw-bold">Full Name</label>
                      <input type="text" name="name" id="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">Designation</label>
                      <input type="text" name="designation" id="designation" class="form-control" placeholder="e.g. Librarian" required>
                    </div>

                    <div class="mb-3">
                      <label class="form-label fw-bold">Profile Image</label>
                      <input type="file" name="profile_image" class="form-control" accept="image/*">
                      <small class="text-muted">Leave blank to keep current image (when editing).</small>
                    </div>

                    <hr>
                    <p class="fw-bold mb-2">Social Links</p>
                    <div class="mb-2">
                       <div class="input-group">
                         <span class="input-group-text"><i class="fab fa-facebook text-primary"></i></span>
                         <input type="text" name="facebook" id="facebook" class="form-control" placeholder="URL">
                       </div>
                    </div>
                    <div class="mb-2">
                       <div class="input-group">
                         <span class="input-group-text"><i class="fab fa-twitter text-info"></i></span>
                         <input type="text" name="twitter" id="twitter" class="form-control" placeholder="URL">
                       </div>
                    </div>
                    <div class="mb-2">
                       <div class="input-group">
                         <span class="input-group-text"><i class="fab fa-linkedin text-primary"></i></span>
                         <input type="text" name="linkedin" id="linkedin" class="form-control" placeholder="URL">
                       </div>
                    </div>
                    <div class="mb-4">
                       <div class="input-group">
                         <span class="input-group-text"><i class="fab fa-youtube text-danger"></i></span>
                         <input type="text" name="youtube" id="youtube" class="form-control" placeholder="URL">
                       </div>
                    </div>

                    <button type="submit" class="btn btn-premium w-100 mb-2">Save Staff Info</button>
                    <button type="button" class="btn btn-light w-100" onclick="resetForm()">Cancel</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include 'partials/footer.php'; ?>
      </div>
    </div>
  </div>

  <script src="static/vendors/js/vendor.bundle.base.js"></script>
  <script src="static/js/off-canvas.js"></script>
  <script src="static/js/hoverable-collapse.js"></script>
  <script src="static/js/template.js"></script>

  <script>
    function editStaff(data) {
      document.getElementById('form-title').innerHTML = '<i class="fas fa-user-edit me-2"></i> Edit Staff';
      document.getElementById('form-action').value = 'edit';
      document.getElementById('staff_id').value = data.id;
      document.getElementById('name').value = data.name;
      document.getElementById('designation').value = data.designation;
      document.getElementById('facebook').value = data.facebook;
      document.getElementById('twitter').value = data.twitter;
      document.getElementById('linkedin').value = data.linkedin;
      document.getElementById('youtube').value = data.youtube;
      
      document.getElementById('staff-form').scrollIntoView({ behavior: 'smooth' });
    }

    function resetForm() {
      document.getElementById('form-title').innerHTML = '<i class="fas fa-user-plus me-2"></i> Add New Staff';
      document.getElementById('form-action').value = 'add';
      document.getElementById('staff-form').reset();
    }
  </script>
</body>
</html>
