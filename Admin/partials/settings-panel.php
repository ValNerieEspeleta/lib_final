<div class="sidebar-wrapper">
  <div class="sidebar-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="sidebar-title m-0">Account Settings</h3>
      <button type="button" class="close-settings btn btn-link p-0 text-muted"><i class="fas fa-times"></i></button>
    </div>
    
    <!-- User Profile Header -->
    <div class="settings-user-header text-center mb-4">
      <div class="profile-pic-container mb-2">
        <?php 
          $pic = $_SESSION['profile_pic'] ?? 'admin.png';
          $picPath = (strpos($pic, 'static/') === 0) ? $pic : 'static/images/profile_pics/' . $pic;
          // Fallback if file doesn't exist
          if (!file_exists($picPath) && !strpos($pic, 'static/')) {
              $picPath = 'static/images/admin.png';
          }
        ?>
        <img src="<?php echo htmlspecialchars($picPath); ?>" alt="Profile" class="rounded-circle shadow-sm" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #fff;">
        <label for="profile_upload" class="pic-edit-badge">
          <i class="fas fa-camera"></i>
        </label>
      </div>
      <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($_SESSION['fullname']); ?></h5>
      <p class="text-muted small"><?php echo htmlspecialchars($_SESSION['role_name']); ?></p>
    </div>

    <!-- Feedback Message -->
    <div id="settings-message"></div>

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

    <!-- Change Profile Picture Form -->
    <form id="profile-pic-form" enctype="multipart/form-data" style="display:none;">
      <input type="file" id="profile_upload" name="profile_pic" accept="image/*">
    </form>

    <!-- Change Name Form -->
    <div class="settings-section">
      <h6 class="mb-3"><i class="fas fa-user-edit me-2"></i> Update Name</h6>
      <form id="update-name-form">
        <div class="mb-2">
          <input type="text" name="firstname" class="form-control form-control-sm" placeholder="First Name" value="<?php echo htmlspecialchars($_SESSION['firstname']); ?>" required>
        </div>
        <div class="mb-2">
          <input type="text" name="lastname" class="form-control form-control-sm" placeholder="Last Name" value="<?php echo htmlspecialchars($_SESSION['lastname']); ?>" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm w-100">Update Name</button>
      </form>
    </div>

    <!-- Change Password Form -->
    <div class="settings-section">
      <h6 class="mb-3"><i class="fas fa-key me-2"></i> Change Password</h6>
      <form id="change-password-form">
        <div class="mb-2">
          <input type="password" name="current_password" class="form-control form-control-sm" placeholder="Current Password" required>
        </div>
        <div class="mb-2">
          <input type="password" name="new_password" class="form-control form-control-sm" placeholder="New Password" required>
        </div>
        <div class="mb-2">
          <input type="password" name="confirm_password" class="form-control form-control-sm" placeholder="Confirm Password" required>
        </div>
        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Change Password</button>
      </form>
    </div>

    <!-- Logout -->
    <div class="settings-section border-0">
      <a href="../logout.php" class="btn btn-light btn-sm w-100 text-danger fw-bold">
        <i class="fas fa-sign-out-alt me-2"></i> Logout
      </a>
    </div>
  </div>
</div>

<style>
  .sidebar-wrapper {
    background-color: #ffffff;
    padding: 25px;
    position: fixed;
    right: -320px;
    top: 0;
    width: 300px;
    height: 100vh;
    overflow-y: auto;
    box-shadow: -5px 0 25px rgba(0, 0, 0, 0.05);
    transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1050;
  }

  .sidebar-wrapper.active {
    right: 0;
  }

  .sidebar-title {
    color: #1f2937;
    font-size: 1.2rem;
    font-weight: 700;
  }

  .settings-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f3f4f6;
  }

  .settings-section h6 {
    color: #4b5563;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .profile-pic-container {
    position: relative;
    display: inline-block;
  }

  .pic-edit-badge {
    position: absolute;
    bottom: 0;
    right: 0;
    background: #4B49AC;
    color: white;
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    cursor: pointer;
    border: 2px solid #fff;
    transition: all 0.2s;
  }

  .pic-edit-badge:hover {
    background: #3f3e91;
    transform: scale(1.1);
  }

  .form-control-sm {
    border-radius: 8px;
    border: 1px solid #e5e7eb;
    padding: 10px;
    font-size: 0.85rem;
  }

  .form-control-sm:focus {
    border-color: #4B49AC;
    box-shadow: 0 0 0 3px rgba(75, 73, 172, 0.1);
  }

  .btn-sm {
    border-radius: 8px;
    padding: 8px;
    font-weight: 600;
    font-size: 0.85rem;
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const settingsTriggers = document.querySelectorAll('.settings-panel-trigger, .settings-btn');
    const settingsPanel = document.querySelector('.sidebar-wrapper');
    const closeBtn = document.querySelector('.close-settings');
    const msgContainer = document.getElementById('settings-message');
    
    function showMessage(text, type) {
      msgContainer.innerHTML = `<div class="alert alert-${type} py-2 px-3 small border-0 mb-3" style="font-weight: 600;">${text}</div>`;
      setTimeout(() => { msgContainer.innerHTML = ''; }, 5000);
    }

    if (settingsPanel) {
      settingsTriggers.forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          settingsPanel.classList.add('active');
          // Close other dropdowns if any
          const navDropdown = document.querySelector('.navbar-dropdown');
          if (navDropdown) navDropdown.classList.remove('show');
        });
      });

      if (closeBtn) {
        closeBtn.addEventListener('click', () => settingsPanel.classList.remove('active'));
      }

      // Close when clicking outside
      document.addEventListener('click', function(e) {
        if (!settingsPanel.contains(e.target)) {
          let isTrigger = false;
          settingsTriggers.forEach(btn => { if(btn.contains(e.target)) isTrigger = true; });
          if (!isTrigger) settingsPanel.classList.remove('active');
        }
      });

      // --- Dark Mode Toggle ---
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
      const picInput = document.getElementById('profile_upload');
      if (picInput) {
        picInput.addEventListener('change', function() {
          if (this.files && this.files[0]) {
            const formData = new FormData();
            formData.append('action', 'update_pic');
            formData.append('profile_pic', this.files[0]);
            
            fetch('update_profile.php', { method: 'POST', body: formData })
              .then(res => res.json())
              .then(data => {
                if (data.success) {
                  location.reload(); // Reload to see new pic everywhere
                } else {
                  showMessage(data.message || 'Upload failed', 'danger');
                }
              });
          }
        });
      }

      // --- Update Name ---
      const nameForm = document.getElementById('update-name-form');
      if (nameForm) {
        nameForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          formData.append('action', 'update_name');
          
          fetch('update_profile.php', { method: 'POST', body: formData })
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
      const passForm = document.getElementById('change-password-form');
      if (passForm) {
        passForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const formData = new FormData(this);
          formData.append('action', 'update_password');
          
          fetch('update_profile.php', { method: 'POST', body: formData })
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
