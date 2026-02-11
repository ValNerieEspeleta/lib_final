<style>
  .dropdown-menu {
    display: none;
    position: absolute;
    background-color: #f9f9f9;
    min-width: 200px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
  }
  
  .dropdown-menu.show {
    display: block;
  }
  
  .dropdown-item {
    color: black;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    cursor: pointer;
  }
  
  .dropdown-item:hover {
    background-color: #f1f1f1;
  }
  
  .dropdown-divider {
    height: 0;
    margin: 0.5rem 0;
    overflow: hidden;
    border-top: 1px solid #e3e6f0;
  }
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    var profileDropdown = document.getElementById('profileDropdown');
    var dropdownMenu = document.querySelector('.navbar-dropdown');
    
    if (profileDropdown && dropdownMenu) {
      profileDropdown.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
      });
      
      // Close dropdown when clicking outside
      document.addEventListener('click', function(e) {
        if (!profileDropdown.contains(e.target) && !dropdownMenu.contains(e.target)) {
          dropdownMenu.classList.remove('show');
        }
      });
      
      // Make dropdown items clickable
      var dropdownItems = dropdownMenu.querySelectorAll('.dropdown-item');
      dropdownItems.forEach(function(item) {
        item.addEventListener('click', function(e) {
          if (this.href && this.href !== '#') {
            window.location.href = this.href;
          }
        });
      });
    }
  });
</script>

<nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo mr-5" href="index.php"><img src="static/images/srclogo.png" class="mr-2" alt="logo"/></a>
        <a class="navbar-brand brand-logo-mini" href="index.php"><img src="static/images/srclogo.png" alt="logo"/></a>
      </div>
      <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
          <span class="icon-menu"></span>
        </button>
        <ul class="navbar-nav mr-lg-2">
        </ul>
        <ul class="navbar-nav navbar-nav-right">
        
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="javascript:void(0)" id="profileDropdown" style="cursor: pointer; padding: 0;">
              <?php 
                $profilePic = $_SESSION['profile_pic'] ?? '';
                $navPicPath = 'static/images/admin.png'; // Default for Admin folder

                if (!empty($profilePic)) {
                    // Try different possible paths
                    $possiblePaths = [
                        $profilePic,                           // As stored (e.g. ../uploads/...)
                        '../' . $profilePic,                   // Relative to root
                        '../static/images/profile_pics/' . $profilePic // Just filename
                    ];

                    foreach ($possiblePaths as $testPath) {
                        if (file_exists($testPath)) {
                            $navPicPath = $testPath;
                            break;
                        }
                    }
                }
              ?>
              <img src="<?php echo htmlspecialchars($navPicPath); ?>" alt="profile" style="height: 40px; width: 40px; object-fit: cover; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"/>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" style="position: absolute; right: 0; top: 100%; margin-top: 5px;">
              <a class="dropdown-item settings-panel-trigger" href="javascript:void(0)">
                <i class="fa-solid fa-gear text-primary"></i>
                Settings
              </a>
              <div class="dropdown-divider"></div>
              <a class="dropdown-item" href="../logout.php">
                <i class="fa-solid fa-right-from-bracket text-primary"></i>
                Logout
              </a>
            </div>
          </li>
          
        </ul>
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>