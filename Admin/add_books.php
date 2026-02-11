<?php
include '../includes/session.php';
include '../includes/dbcon.php';

// ==================== CREATE NORMALIZED TABLES ====================
$setupSQL = [
    "CREATE TABLE IF NOT EXISTS lib_authors (id INT PRIMARY KEY AUTO_INCREMENT, first_name VARCHAR(255), last_name VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS lib_publishers (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL UNIQUE, place_of_publication VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS lib_genres (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100) NOT NULL UNIQUE)",
    "CREATE TABLE IF NOT EXISTS lib_isbn (id INT PRIMARY KEY AUTO_INCREMENT, book_id VARCHAR(6), isbn_number VARCHAR(20) UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS lib_accession_numbers (id INT PRIMARY KEY AUTO_INCREMENT, book_id VARCHAR(6) NOT NULL, accession_number VARCHAR(255) UNIQUE NOT NULL, status VARCHAR(20), acquisition_type VARCHAR(50), donor VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    "CREATE TABLE IF NOT EXISTS lib_book_authors (id INT PRIMARY KEY AUTO_INCREMENT, book_id VARCHAR(6), author_id INT)"
];

foreach ($setupSQL as $sql) @mysqli_query($conn, $sql);

@mysqli_query($conn, "ALTER TABLE lib_authors ADD COLUMN IF NOT EXISTS author_name VARCHAR(255) AFTER id");
@mysqli_query($conn, "ALTER TABLE lib_authors ADD COLUMN IF NOT EXISTS first_name VARCHAR(255) AFTER author_name");
@mysqli_query($conn, "ALTER TABLE lib_authors ADD COLUMN IF NOT EXISTS last_name VARCHAR(255) AFTER first_name");

$alterSQL = [
    "ALTER TABLE lib_books ADD COLUMN IF NOT EXISTS author_id INT",
    "ALTER TABLE lib_books ADD COLUMN IF NOT EXISTS publisher_id INT",
    "ALTER TABLE lib_books ADD COLUMN IF NOT EXISTS isbn_id INT",
    "ALTER TABLE lib_books ADD COLUMN IF NOT EXISTS location VARCHAR(255)",
    "ALTER TABLE lib_books ADD COLUMN IF NOT EXISTS physical_description VARCHAR(255)",
    "ALTER TABLE lib_books ADD COLUMN IF NOT EXISTS date_receive DATETIME"
];

foreach ($alterSQL as $sql) @mysqli_query($conn, $sql);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $book_id = mysqli_real_escape_string($conn, $_POST['book_id']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $genre_id = (int) $_POST['genre'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $date_created = date("Y-m-d H:i:s");
    
    // New fields
    $first_names = $_POST['first_names'] ?? [];
    $last_names = $_POST['last_names'] ?? [];
    $publisher_name = mysqli_real_escape_string($conn, $_POST['publisher_name'] ?? '');
    $place_of_pub = mysqli_real_escape_string($conn, $_POST['place_of_publication'] ?? '');
    $location_name = mysqli_real_escape_string($conn, $_POST['location_name'] ?? '');
    $physical_desc = mysqli_real_escape_string($conn, $_POST['physical_description'] ?? '');
    $isbn_number = mysqli_real_escape_string($conn, $_POST['isbn_number'] ?? '');
    $date_receive = !empty($_POST['date_receive']) ? mysqli_real_escape_string($conn, $_POST['date_receive']) : NULL;
    $accession_number = mysqli_real_escape_string($conn, $_POST['accession_number'] ?? '');
    
    $acquisition_type = mysqli_real_escape_string($conn, $_POST['acquisition_type'] ?? 'Purchased');
    $donor = mysqli_real_escape_string($conn, $_POST['donor'] ?? '');
    
    $acquisitionStr = !empty($acquisition_type) ? "'$acquisition_type'" : "'Purchased'";
    $donorStr = !empty($donor) ? "'$donor'" : "NULL";


    // Check if book already exists
    $checkQuery = "SELECT COUNT(*) as cnt, title FROM lib_books WHERE book_id = '$book_id' GROUP BY title";
    $checkResult = mysqli_query($conn, $checkQuery);
    $existingBook = mysqli_fetch_assoc($checkResult);
    
    $bookExists = $existingBook && $existingBook['cnt'] > 0;
    
    if ($bookExists) {
        // Book already exists - just add new ISBN and/or Accession Number (new physical copy)
        $existingTitle = $existingBook['title'];
        $addedItems = [];
        $new_accession_id = null;
        
        // Add new Accession Number first (new physical copy)
        if (!empty($accession_number)) {
            // Check if Accession Number already exists (globally unique)
            $checkAcc = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM lib_accession_numbers WHERE accession_number = '$accession_number'");
            $accExists = mysqli_fetch_assoc($checkAcc)['cnt'] > 0;
            
            if ($accExists) {
                $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Accession Number <strong>$accession_number</strong> already exists in the system!</div>";
            } else {
                $acc_query = "INSERT INTO lib_accession_numbers (book_id, accession_number, acquisition_type, donor) VALUES ('$book_id', '$accession_number', $acquisitionStr, $donorStr)";
                if (mysqli_query($conn, $acc_query)) {
                    $new_accession_id = mysqli_insert_id($conn); // Get the new accession_id
                    $addedItems[] = "Accession: <strong>$accession_number</strong>";
                } else {
                    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Accession Error: " . mysqli_error($conn) . "</div>";
                }
            }
        }
        
        // Add new ISBN if provided (link to the accession number)
        if (!empty($isbn_number) && empty($message)) {
            // Check if ISBN already exists (globally unique)
            $checkISBN = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM lib_isbn WHERE isbn_number = '$isbn_number'");
            $isbnExists = mysqli_fetch_assoc($checkISBN)['cnt'] > 0;
            
            if ($isbnExists) {
                $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> ISBN <strong>$isbn_number</strong> already exists in the system!</div>";
            } else {
                // Link ISBN to the accession number if available
                $isbn_query = "INSERT INTO lib_isbn (book_id, isbn_number, accession_id) VALUES ('$book_id', '$isbn_number', " . ($new_accession_id ?: "NULL") . ")";
                if (mysqli_query($conn, $isbn_query)) {
                    $addedItems[] = "ISBN: <strong>$isbn_number</strong>";
                } else {
                    $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> ISBN Error: " . mysqli_error($conn) . "</div>";
                }
            }
        }
        
        if (!empty($addedItems) && empty($message)) {
            $itemsList = implode(", ", $addedItems);
            $message = "<div class='alert alert-info alert-dismissible fade show'><i class='fas fa-copy'></i> New copy added to existing book \"<strong>$existingTitle</strong>\"<br>Added: $itemsList<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } elseif (empty($addedItems) && empty($message)) {
            $message = "<div class='alert alert-warning'><i class='fas fa-info-circle'></i> Book <strong>$book_id</strong> already exists. Please provide a new ISBN or Accession Number to add another copy.</div>";
        }
        
    } else {
        // New book - insert everything
        // Get or create Authors
        $author_ids = [];
        foreach ($first_names as $index => $firstName) {
            $firstName = trim($firstName);
            $lastName = trim($last_names[$index] ?? '');
            
            if (empty($firstName) && empty($lastName)) continue;
            
            $fullName = trim($firstName . ' ' . $lastName);
            $firstNameEsc = mysqli_real_escape_string($conn, $firstName);
            $lastNameEsc = mysqli_real_escape_string($conn, $lastName);
            $fullNameEsc = mysqli_real_escape_string($conn, $fullName);
            
            // Check if author exists
            $checkAuthorQuery = "SELECT id FROM lib_authors WHERE first_name = '$firstNameEsc' AND last_name = '$lastNameEsc' LIMIT 1";
            $checkAuthorResult = mysqli_query($conn, $checkAuthorQuery);
            
            if ($a = mysqli_fetch_assoc($checkAuthorResult)) {
                $author_ids[] = $a['id'];
            } else {
                // Insert new author - populating all 3 for consistency
                @mysqli_query($conn, "INSERT INTO lib_authors (author_name, first_name, last_name) VALUES ('$fullNameEsc', '$firstNameEsc', '$lastNameEsc')");
                $author_ids[] = mysqli_insert_id($conn);
            }
        }
        
        // Use the first author as the "main" author_id in lib_books for backward compatibility
        $main_author_id = !empty($author_ids) ? $author_ids[0] : null;
        
        // Get or create Publisher
        $publisher_id = null;
        if (!empty($publisher_name)) {
            @mysqli_query($conn, "INSERT INTO lib_publishers (name, place_of_publication) VALUES ('$publisher_name', '$place_of_pub') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
            $r = mysqli_query($conn, "SELECT id FROM lib_publishers WHERE name = '$publisher_name' LIMIT 1");
            $p = mysqli_fetch_assoc($r);
            $publisher_id = $p['id'];
        }
        
        // Location and Physical Description are now direct fields in lib_books as per image
        $location = $location_name;
        $physical = $physical_desc;
        
        // Insert new book
        $dateStr = !empty($date_receive) ? "'$date_receive'" : "NULL";
        $query = "INSERT INTO lib_books (book_id, title, description, genre_id, status, date_created, author_id, publisher_id, location, physical_description, date_receive) 
                  VALUES ('$book_id', '$title', '$description', '$genre_id', '$status', '$date_created', " . ($main_author_id ?: "NULL") . ", " . ($publisher_id ?: "NULL") . ", '$location', '$physical', $dateStr)";
        
        if (mysqli_query($conn, $query)) {
            // Save to bridge table
            foreach ($author_ids as $aid) {
                mysqli_query($conn, "INSERT INTO lib_book_authors (book_id, author_id) VALUES ('$book_id', $aid)");
            }
            
            $new_accession_id = null;
            
            // Insert Accession Number first with duplicate check
            if (!empty($accession_number)) {
                // Check if Accession Number already exists globally
                $checkAcc = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM lib_accession_numbers WHERE accession_number = '$accession_number'");
                $accExists = mysqli_fetch_assoc($checkAcc)['cnt'] > 0;
                
                if ($accExists) {
                    error_log("Accession Duplicate: $accession_number");
                    $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Book added but Accession Number <strong>$accession_number</strong> already exists in the system!</div>";
                } else {
                    $acc_query = "INSERT INTO lib_accession_numbers (book_id, accession_number, acquisition_type, donor) VALUES ('$book_id', '$accession_number', $acquisitionStr, $donorStr)";
                    if (mysqli_query($conn, $acc_query)) {
                        $new_accession_id = mysqli_insert_id($conn); // Get the new accession_id
                    } else {
                        error_log("Accession Insert Error: " . mysqli_error($conn));
                        $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Book added but Accession Number failed: " . mysqli_error($conn) . "</div>";
                    }
                }
            }
            
            // Insert ISBN with duplicate check (link to accession)
            if (!empty($isbn_number) && empty($message)) {
                // Check if ISBN already exists globally
                $checkISBN = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM lib_isbn WHERE isbn_number = '$isbn_number'");
                $isbnExists = mysqli_fetch_assoc($checkISBN)['cnt'] > 0;
                
                if ($isbnExists) {
                    error_log("ISBN Duplicate: $isbn_number");
                    $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Book added but ISBN <strong>$isbn_number</strong> already exists in the system!</div>";
                } else {
                    // Link ISBN to the accession number if available
                    $isbn_query = "INSERT INTO lib_isbn (book_id, isbn_number, accession_id) VALUES ('$book_id', '$isbn_number', " . ($new_accession_id ?: "NULL") . ")";
                    if (!mysqli_query($conn, $isbn_query)) {
                        error_log("ISBN Insert Error: " . mysqli_error($conn));
                        $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Book added but ISBN failed: " . mysqli_error($conn) . "</div>";
                    }
                }
            }
            
            // Success message
            if (empty($message)) {
                $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> New Book Added Successfully!<br>Call Number: <strong>$book_id</strong> | Title: <strong>$title</strong><br>Accession: <strong>$accession_number</strong><button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
        } else {
            $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Error: " . mysqli_error($conn) . "</div>";
        }
    }
}

// Fetch genres
$genres = [];
$result = mysqli_query($conn, "SELECT id, name FROM lib_genres ORDER BY name ASC");
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $genres[] = $row;
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
                  <h4 class="card-title">Add Book</h4>
                  <p class="card-description">Fill out the form to add a new book</p>

                  <!-- Show success/error message -->
                  <?php if (!empty($message)) { echo $message; } ?>

                  <form class="forms-sample" method="POST" action="">
                    
                    <!-- Accession Number -->
                    <div class="form-group">
                      <label for="accession_number">Accession Number <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="accession_number" name="accession_number" placeholder="e.g., LIB-2026-001" required>
                      <small class="text-muted">Unique identifier for each copy of the book</small>
                    </div>

                    <!-- Book ID -->
                    <div class="form-group">
                      <label for="book_id">Call Number <span class="text-danger">*</span></label>
                      <input type="number" class="form-control" id="book_id" name="book_id" placeholder="e.g., 1 to 999999" min="1" max="999999" required>
                      <small class="text-muted">Enter 1 to 6 digits (1 - 999999)</small>
                    </div>

                    <!-- Title -->
                    <div class="form-group">
                      <label for="title">Book Title <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="title" name="title" placeholder="Enter book title" required>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                      <label for="description">Description <span class="text-danger">*</span></label>
                      <textarea class="form-control" id="description" name="description" rows="3" placeholder="Enter description" required></textarea>
                    </div>

                    <!-- Genre -->
                    <div class="form-group">
                      <label for="genre">Genre <span class="text-danger">*</span></label>
                      <select class="form-control" id="genre" name="genre" required>
                        <option value="">-- Select Genre --</option>
                        <?php foreach ($genres as $g): ?>
                          <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>

                    <!-- Status -->
                    <div class="form-group">
                      <label for="status">Status <span class="text-danger">*</span></label>
                      <select class="form-control" id="status" name="status" required>
                        <option value="">-- Select Status --</option>
                        <option value="available">Available</option>
                        <option value="not available">Not Available</option>
                      </select>
                    </div>

                    <!-- Author -->
                    <div class="form-group">
                      <label>Author(s) <button type="button" class="btn btn-sm btn-success ml-2" onclick="addAuthorField()"><i class="fas fa-plus"></i> Add More</button></label>
                      <div id="authors-container">
                        <div class="author-input-group d-flex mb-2">
                          <input type="text" class="form-control mr-1" name="first_names[]" placeholder="First Name">
                          <input type="text" class="form-control" name="last_names[]" placeholder="Last Name">
                          <button type="button" class="btn btn-danger btn-sm ml-2" onclick="removeAuthorField(this)" style="display:none;"><i class="fas fa-trash"></i></button>
                        </div>
                      </div>
                      <small class="text-muted">Enter first and last names separately</small>
                    </div>

                    <!-- Publisher -->
                    <div class="form-group">
                      <label for="publisher_name">Publisher</label>
                      <input type="text" class="form-control" id="publisher_name" name="publisher_name" placeholder="Type publisher name" list="publishersList" onchange="fetchPlaceOfPublication()">
                      <datalist id="publishersList">
                        <?php 
                          $publishersResult = mysqli_query($conn, "SELECT DISTINCT name FROM lib_publishers ORDER BY name");
                          while ($pub = mysqli_fetch_assoc($publishersResult)): ?>
                          <option value="<?php echo htmlspecialchars($pub['name']); ?>">
                        <?php endwhile; ?>
                      </datalist>
                      <small class="text-muted">Start typing to see suggestions</small>
                    </div>

                    <!-- Place of Publication (Auto-fill) -->
                    <div class="form-group">
                      <label for="place_of_publication">Place of Publication</label>
                      <input type="text" class="form-control" id="place_of_publication" name="place_of_publication" placeholder="Auto-filled or enter manually">
                      <small class="text-muted">Automatically filled when publisher is selected</small>
                    </div>

                    <!-- Location -->
                    <div class="form-group">
                      <label for="location_name">Location</label>
                      <input type="text" class="form-control" id="location_name" name="location_name" placeholder="e.g., Shelf A1, Room 102" list="locationsList">
                      <datalist id="locationsList">
                        <?php 
                          $locationsResult = mysqli_query($conn, "SELECT DISTINCT location_name FROM lib_locations ORDER BY location_name");
                          while ($loc = mysqli_fetch_assoc($locationsResult)): ?>
                          <option value="<?php echo htmlspecialchars($loc['location_name']); ?>">
                        <?php endwhile; ?>
                      </datalist>
                      <small class="text-muted">Where the book is stored</small>
                    </div>

                    <!-- ISBN -->
                    <div class="form-group">
                      <label for="isbn_number">ISBN</label>
                      <input type="text" class="form-control" id="isbn_number" name="isbn_number" placeholder="e.g., 978-3-16-148410-0">
                      <small class="text-muted">International Standard Book Number (One-to-One)</small>
                    </div>

                    <!-- Physical Description -->
                    <div class="form-group">
                      <label for="physical_description">Physical Description</label>
                      <input type="text" class="form-control" id="physical_description" name="physical_description" placeholder="e.g., 200 pages, hardcover, 6 x 9 inches">
                      <small class="text-muted">Pages, binding, size, condition, etc.</small>
                    </div>

                    <!-- Date Receipt -->
                    <div class="form-group">
                      <label for="date_receive">Date Receive</label>
                      <input type="datetime-local" class="form-control" id="date_receive" name="date_receive">
                      <small class="text-muted">When the book was acquired</small>
                    </div>

                    <!-- Acquisition Type -->
                    <div class="form-group">
                      <label for="acquisition_type">Acquisition Type</label>
                      <select class="form-control" id="acquisition_type" name="acquisition_type" onchange="toggleDonorField()">
                        <option value="Purchased">Purchased by School</option>
                        <option value="Donated">Donated</option>
                      </select>
                    </div>

                    <!-- Donor Name (Hidden by default) -->
                    <div class="form-group" id="donorField" style="display: none;">
                      <label for="donor">Donor Name</label>
                      <input type="text" class="form-control" id="donor" name="donor" placeholder="Name of the donor">
                    </div>

                    <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-save"></i> Add Book</button>
                    <button type="reset" class="btn btn-light"><i class="fas fa-redo"></i> Clear</button>
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
  
  <!-- Auto-fill Publisher Place of Publication -->
  <script>
    function fetchPlaceOfPublication() {
      const publisherName = document.getElementById('publisher_name').value;
      if (publisherName) {
        fetch('get_publisher_details.php?publisher=' + encodeURIComponent(publisherName))
          .then(response => response.json())
          .then(data => {
            if (data.place_of_publication) {
              document.getElementById('place_of_publication').value = data.place_of_publication;
            }
          })
          .catch(error => console.log('Could not fetch publisher details'));
      }
    }

    function toggleDonorField() {
      const acquisitionType = document.getElementById('acquisition_type').value;
      const donorField = document.getElementById('donorField');
      const donorInput = document.getElementById('donor');
      
      if (acquisitionType === 'Donated') {
        donorField.style.display = 'block';
        donorInput.setAttribute('required', 'required');
      } else {
        donorField.style.display = 'none';
        donorInput.removeAttribute('required');
        donorInput.value = '';
      }
    }

    function addAuthorField() {
      const container = document.getElementById('authors-container');
      const firstField = container.querySelector('.author-input-group');
      const newField = firstField.cloneNode(true);
      newField.querySelectorAll('input').forEach(input => input.value = '');
      newField.querySelector('button').style.display = 'block';
      container.appendChild(newField);
    }

    function removeAuthorField(btn) {
      const container = document.getElementById('authors-container');
      if (container.querySelectorAll('.author-input-group').length > 1) {
        btn.parentElement.remove();
      }
    }
    
    // Run on page load in case of browser refresh with value
    window.addEventListener('DOMContentLoaded', toggleDonorField);
  </script>
  <!-- End custom js for this page-->
</body>

</html>
