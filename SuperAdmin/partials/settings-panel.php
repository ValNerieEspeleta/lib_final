<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once '../includes/dbcon.php';

// Get user information from session
$role_name = $_SESSION['role_name'] ?? 'Super Admin';
$firstname = $_SESSION['firstname'] ?? '';
$lastname = $_SESSION['lastname'] ?? '';
$fullname = $_SESSION['fullname'] ?? trim("$firstname $lastname") ?: 'User';
$username = $_SESSION['username'] ?? ($_SESSION['email'] ?? 'N/A');
?>

<div class="sidebar-wrapper">
  <div class="sidebar-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="sidebar-title m-0">Account Management</h4>
      <button type="button" class="close settings-close p-0" style="font-size: 1.5rem;">&times;</button>
    </div>
    
    <!-- User Profile Header -->
    <div class="settings-user-header text-center mb-4">
      <div class="profile-pic-container mb-3">
        <?php 
           $userId = $_SESSION['userId'];
           $query = "SELECT profile_pic FROM employees WHERE employee_id = '$userId'";
           $result = mysqli_query($conn, $query);
           $user_data = mysqli_fetch_assoc($result);
           
           $pic = $user_data['profile_pic'] ?? 'admin.png';
           $picPath = (strpos($pic, 'static/') === 0) ? "../" . $pic : "../Admin/static/images/profile_pics/" . $pic;
           
           // Fallback
           if (!file_exists($picPath) && !strpos($pic, 'static/')) {
               $picPath = "../Admin/static/images/admin.png";
           }
        ?>
        <img src="<?php echo htmlspecialchars($picPath); ?>" alt="Profile" class="rounded-circle shadow" style="width: 100px; height: 100px; object-fit: cover; border: 4px solid #fff;">
        <label for="profile_upload_sa" class="pic-edit-badge">
          <i class="fas fa-camera"></i>
        </label>
      </div>
      <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($_SESSION['fullname']); ?></h5>
      <p class="text-muted small"><?php echo htmlspecialchars($_SESSION['role_name']); ?></p>
    </div>

    <!-- Feedback Message -->
    <div id="sa-settings-message"></div>

    <!-- Theme Settings -->
    <div class="settings-section">
      <div class="d-flex justify-content-between align-items-center">
        <h6 class="m-0"><i class="fas fa-moon me-2"></i> Dark Mode</h6>
        <label class="theme-switch" for="dark-mode-toggle">
          <input type="checkbox" id="dark-mode-toggle">
          <span class="slider round"></span>
        </label>
      </div>
    </div>

    <!-- Profile Pic Upload sa Form -->
    <form id="profile-pic-sa-form" enctype="multipart/form-data" style="display:none;">
      <input type="file" id="profile_upload_sa" name="profile_pic" accept="image/*">
    </form>

    <!-- Change Name Form -->
    <div class="settings-section">
      <h6 class="mb-3"><i class="fas fa-user-edit me-2"></i> Update Name</h6>
      <form id="update-name-sa-form">
        <div class="mb-2">
          <input type="text" name="firstname" class="form-control" placeholder="First Name" value="<?php echo htmlspecialchars($_SESSION['firstname']); ?>" required>
        </div>
        <div class="mb-2">
          <input type="text" name="lastname" class="form-control" placeholder="Last Name" value="<?php echo htmlspecialchars($_SESSION['lastname']); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-sm">Update Name</button>
      </form>
    </div>

    <!-- Change Password Form -->
    <div class="settings-section">
      <h6 class="mb-3"><i class="fas fa-key me-2"></i> Change Password</h6>
      <form id="change-password-sa-form">
        <div class="mb-2">
          <input type="password" name="current_password" class="form-control" placeholder="Current Password" required>
        </div>
        <div class="mb-2">
          <input type="password" name="new_password" class="form-control" placeholder="New Password" required>
        </div>
        <div class="mb-2">
          <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
        </div>
        <button type="submit" class="btn btn-outline-primary btn-block btn-sm">Change Password</button>
      </form>
    </div>

    <!-- Logout -->
    <div class="settings-section border-0">
      <a href="../logout.php" class="btn btn-danger btn-block btn-sm fw-bold">
        <i class="fas fa-sign-out-alt me-2"></i> Logout System
      </a>
    </div>
  </div>
</div>

<style>
  .sidebar-wrapper {
    background-color: #ffffff;
    padding: 30px;
    position: fixed;
    right: -400px;
    top: 0;
    width: 350px;
    height: 100vh;
    overflow-y: auto;
    box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1);
    transition: right 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    z-index: 2000;
  }

  .sidebar-wrapper.active {
    right: 0;
  }

  .sidebar-title {
    color: #1a202c;
    font-size: 1.4rem;
    font-weight: 800;
  }

  .settings-section {
    margin-bottom: 30px;
    padding-bottom: 25px;
    border-bottom: 1px solid #edf2f7;
  }

  .settings-section h6 {
    color: #4a5568;
    font-size: 0.9rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .profile-pic-container {
    position: relative;
    display: inline-block;
  }

  .pic-edit-badge {
    position: absolute;
    bottom: 5px;
    right: 5px;
    background: #667eea;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    border: 3px solid #fff;
    transition: all 0.3s;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  }

  .pic-edit-badge:hover {
    background: #5a67d8;
    transform: scale(1.15) rotate(15deg);
  }

  .form-control {
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    padding: 12px;
    font-size: 0.95rem;
    background: #f7fafc;
  }

  .form-control:focus {
    background: #fff;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
  }

  .btn-sm {
    border-radius: 12px;
    padding: 12px;
    font-weight: 700;
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const settingsTriggers = document.querySelectorAll('.settings-panel-trigger, #settingsTrigger');
    const settingsPanel = document.querySelector('.sidebar-wrapper');
    const closeBtn = document.querySelector('.settings-close');
    const msgContainer = document.getElementById('sa-settings-message');
    
    function showMessage(text, type) {
      msgContainer.innerHTML = `<div class="alert alert-${type} py-2 px-3 small border-0 mb-3" style="font-weight: 700;">${text}</div>`;
      setTimeout(() => { msgContainer.innerHTML = ''; }, 5000);
    }

    if (settingsPanel) {
      settingsTriggers.forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          settingsPanel.classList.add('active');
          const navDropdown = document.querySelector('.navbar-dropdown');
          if (navDropdown) navDropdown.classList.remove('show');
        });
      });

      if (closeBtn) {
        closeBtn.addEventListener('click', () => settingsPanel.classList.remove('active'));
      }

      document.addEventListener('click', function(e) {
        if (!settingsPanel.contains(e.target)) {
          let isTrigger = false;
          settingsTriggers.forEach(btn => { if(btn.contains(e.target)) isTrigger = true; });
          if (!isTrigger) settingsPanel.classList.remove('active');
        }
      });

      // --- Dark Mode ---
      const darkModeToggle = document.getElementById('dark-mode-toggle');
      if (darkModeToggle) {
        darkModeToggle.checked = localStorage.getItem('theme') === 'dark';
        darkModeToggle.addEventListener('change', function() {
          const isDark = this.checked;
          document.body.classList.toggle('dark-mode', isDark);
          document.documentElement.classList.toggle('dark-mode', isDark);
          localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });
      }

      // --- Profile Pic Upload ---
      const picInputSa = document.getElementById('profile_upload_sa');
      if (picInputSa) {
        picInputSa.addEventListener('change', function() {
          if (this.files && this.files[0]) {
            const formData = new FormData();
            formData.append('action', 'update_pic');
            formData.append('profile_pic', this.files[0]);
            
            fetch('../Admin/update_profile.php', { method: 'POST', body: formData })
              .then(res => res.json())
              .then(data => {
                if (data.success) {
                  location.reload();
                } else {
                  showMessage(data.message || 'Upload failed', 'danger');
                }
              });
          }
        });
      }

      // --- Update Name ---
      const nameFormSa = document.getElementById('update-name-sa-form');
      if (nameFormSa) {
        nameFormSa.addEventListener('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          formData.append('action', 'update_name');
          
          fetch('../Admin/update_profile.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                showMessage('Name updated successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
              } else {
                showMessage(data.message || 'Update failed', 'danger');
              }
            });
        });
      }

      // --- Change Password ---
      const passFormSa = document.getElementById('change-password-sa-form');
      if (passFormSa) {
        passFormSa.addEventListener('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          formData.append('action', 'update_password');
          
          fetch('../Admin/update_profile.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
              if (data.success) {
                showMessage('Password changed successfully!', 'success');
                this.reset();
              } else {
                showMessage(data.message || 'Failed to change password', 'danger');
              }
            });
        });
      }
    }
  });
</script>