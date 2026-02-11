<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  .sidebar .nav-item .collapse {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
  }
  
  .sidebar .nav-item .collapse.show {
    max-height: 500px;
    transition: max-height 0.3s ease-in;
  }
  
  .arrow-icon {
    transition: transform 0.3s ease;
  }
  
  .nav-link[aria-expanded="true"] .arrow-icon {
    transform: rotate(90deg);
  }
  
  .sidebar .sub-menu {
    padding-left: 20px;
    margin-top: 5px;
  }
  
  .sidebar .sub-menu .nav-link {
    font-size: 14px;
    padding: 8px 0;
  }
</style>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var collapseElements = document.querySelectorAll('[data-toggle="collapse"]');
    collapseElements.forEach(function(element) {
      element.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        var targetSelector = this.getAttribute('data-target') || this.getAttribute('href');
        if (!targetSelector || targetSelector === '#') return false;
        
        var target = document.querySelector(targetSelector);
        if (!target) return false;
        
        // Close other open collapses in the sidebar
        var otherCollapses = document.querySelectorAll('.sidebar .collapse.show');
        otherCollapses.forEach(function(collapse) {
          if (collapse !== target) {
            collapse.classList.remove('show');
            var relatedButton = document.querySelector('[data-target="#' + collapse.id + '"], [href="#' + collapse.id + '"]');
            if (relatedButton) {
              relatedButton.setAttribute('aria-expanded', 'false');
            }
          }
        });
        
        // Toggle current collapse
        target.classList.toggle('show');
        var isExpanded = target.classList.contains('show');
        this.setAttribute('aria-expanded', isExpanded);
        
        return false;
      }, true);
    });
  });
</script>
<nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">
          <li class="nav-item">
            <a class="nav-link" href="index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard - Test</span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="javascript:void(0)" data-target="#manage-schedule" aria-expanded="false" aria-controls="manage-schedule">
              <i class="fa-solid fa-calendar-check menu-icon"></i>
              <span class="menu-title">Schedule</span>
              &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa-solid fa-angle-right arrow-icon"></i>
            </a>
            <div class="collapse" id="manage-schedule">
              <ul class="nav flex-column sub-menu">
                <li class="nav-item">
                  <a class="nav-link" href="schedule.php">Set Schedule</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="table_student.php?filter=elementary&search=Senior+Kinder">Senior Kinder List</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="table_student.php?filter=elementary">Elementary List</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="table_student.php?filter=jhs">Junior High List</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="table_student.php?filter=shs">Senior High List</a>
                </li>
              </ul>
            </div>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="javascript:void(0)" data-target="#manage-admin" aria-expanded="false" aria-controls="manage-admin">
              <i class="fa-solid fa-user-gear menu-icon"></i>
              <span class="menu-title">Manage Staff</span>
              &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa-solid fa-angle-right arrow-icon"></i>
            </a>
            <div class="collapse" id="manage-admin">
              <ul class="nav flex-column sub-menu">
                <li class="nav-item">
                  <a class="nav-link" href="add_admin.php">Add Staff</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="table_admin.php">Table</a>
                </li>
              </ul>
            </div>
          </li>


          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="javascript:void(0)" data-target="#manage-rfid" aria-expanded="false" aria-controls="manage-rfid">
              <i class="fa-solid fa-id-card menu-icon"></i>
              <span class="menu-title">Manage RFID</span>
              &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa-solid fa-angle-right arrow-icon"></i>
            </a>
            <div class="collapse" id="manage-rfid">
              <ul class="nav flex-column sub-menu">
                <li class="nav-item">
                  <a class="nav-link" href="rfid_auth.php">Register RFID</a>
                </li>
              </ul>
            </div>
          </li>


          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="javascript:void(0)" data-target="#manage-student" aria-expanded="false" aria-controls="manage-student">
              <i class="fa-solid fa-users menu-icon"></i>
              <span class="menu-title">Manage Users</span>
              &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa-solid fa-angle-right arrow-icon"></i>
            </a>
            <div class="collapse" id="manage-student">
              <ul class="nav flex-column sub-menu">
                <li class="nav-item">
                  <a class="nav-link" href="add_student.php">Add Borrower</a>
                </li>

                <li class="nav-item">
                  <a class="nav-link" href="table_student.php">User Table</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="attend_table.php">Attendance Table</a>
                </li>
              </ul>
            </div>
          </li>




          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="javascript:void(0)" data-target="#manage-books" aria-expanded="false" aria-controls="manage-books">
              <i class="fa-solid fa-book menu-icon"></i>
              <span class="menu-title">Manage Books</span>
              &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa-solid fa-angle-right arrow-icon"></i>
            </a>
            <div class="collapse" id="manage-books">
              <ul class="nav flex-column sub-menu">
                <li class="nav-item">
                  <a class="nav-link" href="add_books.php">Add Books</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="table_book.php">Table</a>
                </li>
               
                <li class="nav-item">
                  <a class="nav-link" href="loan_book.php">Borrow Book</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="return_book.php">Return Book</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="active_borrower.php">Active Borrowers</a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="manage_genre.php">
                    <i class="fas fa-layer-group"></i>
                    <span class="menu-title">Add Genre</span>
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link" href="manage_penalty.php">
                    <i class="fas fa-money-bill-wave"></i>
                    <span class="menu-title">Penalty</span>
                  </a>
                </li>
              </ul>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="javascript:void(0)" data-target="#manage-events" aria-expanded="false" aria-controls="manage-events">
              <i class="fa-solid fa-calendar-days menu-icon"></i>
              <span class="menu-title">Manage Events</span>
              &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa-solid fa-angle-right arrow-icon"></i>
            </a>
            <div class="collapse" id="manage-events">
              <ul class="nav flex-column sub-menu">
                <li class="nav-item">
                  <a class="nav-link" href="manage_events.php">Calendar & Events</a>
                </li>
              </ul>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="javascript:void(0)" data-target="#manage-staff" aria-expanded="false" aria-controls="manage-staff">
              <i class="fa-solid fa-users-gear menu-icon"></i>
              <span class="menu-title">Manage Staff</span>
              &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa-solid fa-angle-right arrow-icon"></i>
            </a>
            <div class="collapse" id="manage-staff">
              <ul class="nav flex-column sub-menu">
                <li class="nav-item">
                  <a class="nav-link" href="manage_staff.php">Library Personnel</a>
                </li>
              </ul>
            </div>
          </li>

          <li class="nav-item">
            <a class="nav-link" href="rfid_attendance.php">
              <i class="fa-solid fa-clock menu-icon"></i>
              <span class="menu-title">Attendance</span>
            </a>
          </li>  
        </ul>      
</nav>