<?php
// Include database connection
include '../includes/session.php';
include '../includes/dbcon.php';

$statusMsg = "";

// ✅ Handle Delete Request
if (isset($_GET['delete_id'])) {
    $deleteBookId = mysqli_real_escape_string($conn, $_GET['delete_id']);
    $deleteAccId = isset($_GET['acc_id']) ? intval($_GET['acc_id']) : null;

    if ($deleteAccId) {
        // Delete specific physical copy (and linked ISBN)
        $msg = "";
        // Delete linked ISBN first - use 'accession_id' column but 'id' might be PK
        mysqli_query($conn, "DELETE FROM lib_isbn WHERE accession_id = '$deleteAccId'");
        
        // Delete accession record - PK is now 'id'
        if (mysqli_query($conn, "DELETE FROM lib_accession_numbers WHERE id = '$deleteAccId'")) {
             $statusMsg = '<div class="alert alert-success">✅ Copy deleted successfully!</div>';
        } else {
             $statusMsg = '<div class="alert alert-danger">❌ Error deleting copy: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
        }
    } else {
        // Delete entire book record (cascades to all copies)
        $deleteQuery = "DELETE FROM lib_books WHERE book_id = '$deleteBookId'";
        if (mysqli_query($conn, $deleteQuery)) {
            $statusMsg = '<div class="alert alert-success">✅ Book deleted successfully!</div>';
        } else {
            $statusMsg = '<div class="alert alert-danger">❌ Error deleting book: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
        }
    }
}

// ✅ Handle Edit/Update Request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_id'])) {
    $editBookId = mysqli_real_escape_string($conn, $_POST['edit_id']); // This is book_id (varchar)
    $editAccId = !empty($_POST['edit_acc_id']) ? intval($_POST['edit_acc_id']) : null;
    
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $genre_id = intval($_POST['genre']);
    
    $acc_number = mysqli_real_escape_string($conn, $_POST['accession_number']);
    $isbn_val = mysqli_real_escape_string($conn, $_POST['isbn']);
    $acquisition_type = mysqli_real_escape_string($conn, $_POST['acquisition_type'] ?? 'Purchased');
    $donor = mysqli_real_escape_string($conn, $_POST['donor'] ?? '');

    // Handle Image Upload
    $book_image = "";
    if (isset($_FILES['edit_book_image']) && $_FILES['edit_book_image']['error'] == 0) {
        $target_dir = "../uploads/books/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = strtolower(pathinfo($_FILES["edit_book_image"]["name"], PATHINFO_EXTENSION));
        $clean_title = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '_', $title));
        $file_name = $clean_title . "_" . substr(md5(time()), 0, 8) . "." . $file_extension;
        $target_file = $target_dir . $file_name;
        
        if (move_uploaded_file($_FILES["edit_book_image"]["tmp_name"], $target_file)) {
            $book_image = "uploads/books/" . $file_name;
        }
    }

    // 1. Update general book details
    $updateBook = "UPDATE lib_books SET title='$title', description='$description', status='$status', genre_id=$genre_id";
    if ($book_image != "") {
        $updateBook .= ", book_image='$book_image'";
    }
    $updateBook .= " WHERE book_id='$editBookId'";
    $bookUpdated = mysqli_query($conn, $updateBook);
    
    // 1.5 Update Authors
    if (isset($_POST['authors'])) {
        $authors_input = $_POST['authors'];
        $author_list = explode(',', $authors_input);
        $new_author_ids = [];
        
        foreach ($author_list as $auth_name) {
            $auth_name = trim($auth_name);
            if (empty($auth_name)) continue;
            
            $parts = explode(' ', $auth_name);
            if (count($parts) > 1) {
                $lastName = array_pop($parts);
                $firstName = implode(' ', $parts);
            } else {
                $firstName = $auth_name;
                $lastName = '';
            }
            $firstName = mysqli_real_escape_string($conn, $firstName);
            $lastName = mysqli_real_escape_string($conn, $lastName);
            
            // Get or create author
            $res = mysqli_query($conn, "SELECT id FROM lib_authors WHERE first_name = '$firstName' AND last_name = '$lastName' LIMIT 1");
            if ($a = mysqli_fetch_assoc($res)) {
                $new_author_ids[] = $a['id'];
            } else {
                mysqli_query($conn, "INSERT INTO lib_authors (first_name, last_name) VALUES ('$firstName', '$lastName')");
                $new_author_ids[] = mysqli_insert_id($conn);
            }
        }
        
        if (!empty($new_author_ids)) {
            // Update main author in lib_books
            $main_aid = $new_author_ids[0];
            mysqli_query($conn, "UPDATE lib_books SET author_id = $main_aid WHERE book_id = '$editBookId'");
            
            // Update bridge table
            mysqli_query($conn, "DELETE FROM lib_book_authors WHERE book_id = '$editBookId'");
            foreach ($new_author_ids as $aid) {
                mysqli_query($conn, "INSERT INTO lib_book_authors (book_id, author_id) VALUES ('$editBookId', $aid)");
            }
        }
    }
    
    $extraMsg = "";
    
    // 2. Update Accession Number if specific copy selected
    if ($editAccId && !empty($acc_number)) {
        // Check duplicate accession - PK is now 'id'
        $check = mysqli_query($conn, "SELECT id FROM lib_accession_numbers WHERE accession_number='$acc_number' AND id != $editAccId");
        if (mysqli_num_rows($check) > 0) {
             $extraMsg .= "<br>⚠️ Accession Number '$acc_number' already exists. Skipped update.";
        } else {
             $donorVal = ($acquisition_type === 'Donated' && !empty($donor)) ? "'$donor'" : "NULL";
             mysqli_query($conn, "UPDATE lib_accession_numbers SET accession_number='$acc_number', acquisition_type='$acquisition_type', donor=$donorVal WHERE id=$editAccId");
        }
        
        // 3. Update ISBN linked to this accession
        if (!empty($isbn_val)) {
            // Check if ISBN row exists for this accession
            $checkIsbn = mysqli_query($conn, "SELECT id FROM lib_isbn WHERE accession_id=$editAccId");
            if (mysqli_num_rows($checkIsbn) > 0) {
                mysqli_query($conn, "UPDATE lib_isbn SET isbn_number='$isbn_val' WHERE accession_id=$editAccId");
            } else {
                // Insert new ISBN link
                mysqli_query($conn, "INSERT INTO lib_isbn (book_id, isbn_number, accession_id) VALUES ('$editBookId', '$isbn_val', $editAccId)");
            }
        }
    }

    if ($bookUpdated) {
        $statusMsg = '<div class="alert alert-success">✅ Book details updated!' . $extraMsg . '</div>';
    } else {
        $statusMsg = '<div class="alert alert-danger">❌ Error updating book: ' . htmlspecialchars(mysqli_error($conn)) . '</div>';
    }
}

// ✅ Create borrowing_transactions table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS borrowing_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    book_id INT NOT NULL,
    borrower_id INT NOT NULL,
    borrowing_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    return_date DATETIME,
    status ENUM('borrowed', 'returned', 'overdue', 'lost') DEFAULT 'borrowed',
    INDEX idx_book_id (book_id),
    INDEX idx_borrower_id (borrower_id)
)";
@mysqli_query($conn, $createTableSQL);

// ✅ Fetch book records with physical copies (each accession number = separate row)
$bookQuery = "SELECT 
    b.book_id,
    b.title,
    b.description,
    b.status,
    b.book_image,
    b.date_created,
    g.name as genre_name,
    acc.accession_number,
    acc.id as accession_id,
    acc.acquisition_type,
    acc.donor,
    i.isbn_number,
    (SELECT GROUP_CONCAT(COALESCE(CONCAT(a.first_name, ' ', a.last_name), 'Unknown') SEPARATOR ', ') 
     FROM lib_book_authors ba 
     JOIN lib_authors a ON ba.author_id = a.id 
     WHERE ba.book_id = b.book_id) as author_names,
    (SELECT COUNT(*) FROM lib_accession_numbers WHERE book_id = b.book_id) as total_copies,
    COALESCE(COUNT(DISTINCT br.id), 0) as total_borrows
FROM lib_books b
LEFT JOIN lib_genres g ON b.genre_id = g.id
LEFT JOIN lib_accession_numbers acc ON b.book_id = acc.book_id
LEFT JOIN lib_isbn i ON acc.id = i.accession_id
LEFT JOIN borrowing_transactions br ON b.book_id = br.book_id
GROUP BY b.book_id, acc.id, i.id
ORDER BY b.date_created DESC, acc.accession_number ASC";
$bookResult = mysqli_query($conn, $bookQuery);

// ✅ Fetch all genres for dropdown
$genreQuery = "SELECT id, name FROM lib_genres ORDER BY name ASC";
$genreResult = mysqli_query($conn, $genreQuery);

// ✅ Helper function to shorten description
function shortenText($text, $limit = 10) {
    $words = explode(" ", $text);
    if (count($words) > $limit) {
        return implode(" ", array_slice($words, 0, $limit)) . " ...";
    }
    return $text;
}

// Helper to find book image
function getBookImageAdmin($filename) {
    if (empty($filename)) return '../img/carousel-1.jpg'; // Default library image
    
    // Check direct path first (relative to root)
    if (strpos($filename, '/') !== false && file_exists('../' . $filename)) {
        return '../' . $filename;
    }

    $locations = ['', 'uploads/', 'uploads/books/', 'img/'];
    foreach ($locations as $loc) {
        $path = '../' . $loc . basename($filename);
        if (file_exists($path)) return $path;
    }
    return '../img/carousel-1.jpg';
}
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
    .badge-available {
      background-color: #28a745;
      color: white;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.8rem;
    }
    .badge-unavailable {
      background-color: #dc3545;
      color: white;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 0.8rem;
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
                  <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title mb-0">Book Records</h4>
                    <a href="active_borrower.php" class="btn btn-sm btn-outline-primary shadow-sm border-0 px-3 py-2" style="border-radius: 8px;">
                      <i class="fa-solid fa-users-viewfinder me-2"></i> Active Borrowers
                    </a>
                  </div>

                  <div class="table-responsive">
                    <table id="bookTable" class="table table-hover">
                      <thead>
                        <tr>
                          <th>Photo</th>
                          <th>Call Number</th>
                          <th>Title</th>
                          <th>Author(s)</th>
                          <th>Accession #</th>
                          <th>ISBN</th>
                          <th>Acquisition</th>
                          <th>Copies</th>
                          <th>Status</th>
                          <th>Genre</th>
                          <th>Borrow History</th>
                          <th>Date Created</th>
                          <th>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if ($bookResult && mysqli_num_rows($bookResult) > 0): ?>
                          <?php while ($row = mysqli_fetch_assoc($bookResult)): ?>
                            <tr>
                              <td>
                                <img src="<?php echo getBookImageAdmin($row['book_image']); ?>" style="width: 50px; height: 60px; object-fit: cover; border-radius: 4px;" alt="Cover">
                              </td>
                              <td><strong><?php echo htmlspecialchars($row['book_id']); ?></strong></td>
                              <td><?php echo htmlspecialchars(shortenText($row['title'], 8)); ?></td>
                              <td><small><?php echo htmlspecialchars($row['author_names'] ?? 'N/A'); ?></small></td>
                              <td>
                                <?php if ($row['accession_number']): ?>
                                  <span class="badge bg-primary"><?php echo htmlspecialchars($row['accession_number']); ?></span>
                                <?php else: ?>
                                  <span class="text-muted">N/A</span>
                                <?php endif; ?>
                              </td>
                              <td>
                                <?php if ($row['isbn_number']): ?>
                                  <small><?php echo htmlspecialchars($row['isbn_number']); ?></small>
                                <?php else: ?>
                                  <span class="text-muted">N/A</span>
                                <?php endif; ?>
                              </td>
                              <td>
                                <?php if ($row['acquisition_type'] == 'Donated'): ?>
                                  <span class="badge bg-light text-dark border"><i class="fa-solid fa-hand-holding-heart text-warning"></i> Donated by: <strong><?php echo htmlspecialchars($row['donor']); ?></strong></span>
                                <?php else: ?>
                                  <span class="badge bg-light text-dark border"><i class="fa-solid fa-school text-primary"></i> Purchased by School</span>
                                <?php endif; ?>
                              </td>
                              <td>
                                <span class="badge bg-info"><?php echo $row['total_copies']; ?> <?php echo $row['total_copies'] > 1 ? 'copies' : 'copy'; ?></span>
                              </td>
                              <td>
                                <?php if ($row['status'] == 'available'): ?>
                                  <span class="badge-available"><i class="fa-solid fa-check"></i> Available</span>
                                <?php else: ?>
                                  <span class="badge-unavailable"><i class="fa-solid fa-times"></i> Unavailable</span>
                                <?php endif; ?>
                              </td>
                              <td><?php echo htmlspecialchars($row['genre_name']); ?></td>
                              <td>
                                <button class="btn btn-sm btn-info view-history" 
                                        data-book-id="<?php echo $row['book_id']; ?>"
                                        data-book-title="<?php echo htmlspecialchars($row['title']); ?>"
                                        title="View Borrow History">
                                  <i class="fa-solid fa-history"></i> (<?php echo $row['total_borrows']; ?>)
                                </button>
                              </td>
                              <td><?php echo htmlspecialchars($row['date_created']); ?></td>
                              <td class="action-icons">
                                <!-- Edit button -->
                                <i class="fa-solid fa-pencil edit" 
                                   data-id="<?php echo $row['book_id']; ?>"
                                   data-acc-id="<?php echo $row['accession_id']; ?>"
                                   data-acc-no="<?php echo htmlspecialchars($row['accession_number']); ?>"
                                   data-isbn="<?php echo htmlspecialchars($row['isbn_number']); ?>"
                                   data-title="<?php echo htmlspecialchars($row['title']); ?>"
                                   data-description="<?php echo htmlspecialchars($row['description']); ?>"
                                   data-status="<?php echo htmlspecialchars($row['status']); ?>"
                                   data-author="<?php echo htmlspecialchars($row['author_names'] ?? ''); ?>"
                                   data-genre="<?php echo htmlspecialchars($row['genre_name']); ?>"
                                   data-acquisition="<?php echo htmlspecialchars($row['acquisition_type'] ?? 'Purchased'); ?>"
                                   data-donor="<?php echo htmlspecialchars($row['donor'] ?? ''); ?>"
                                   data-image="<?php echo getBookImageAdmin($row['book_image']); ?>"
                                   title="Edit"></i>

                                <!-- Delete button -->
                                <a href="?delete_id=<?php echo $row['book_id']; ?>&acc_id=<?php echo $row['accession_id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this specific copy?');">
                                   <i class="fa-solid fa-trash delete" title="Delete"></i>
                                </a>
                              </td>
                            </tr>
                          <?php endwhile; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="10" class="text-center">No book records found.</td>
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

  <!-- Edit Book Modal -->
  <div class="modal fade" id="editBookModal" tabindex="-1" aria-labelledby="editBookModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="" enctype="multipart/form-data">
          <div class="modal-header">
            <h5 class="modal-title" id="editBookModalLabel">Edit Book</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="edit_id" id="edit_id">
            <input type="hidden" name="edit_acc_id" id="edit_acc_id">
            
            <div class="mb-3 d-flex align-items-center">
                <img id="edit_image_preview" src="" style="width: 80px; height: 100px; object-fit: cover; margin-right: 15px; border-radius: 4px; display: none;">
                <div class="flex-grow-1">
                    <label for="edit_book_image" class="form-label">Update Book Cover</label>
                    <input type="file" class="form-control" name="edit_book_image" id="edit_book_image" accept="image/*">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="accession_number" class="form-label">Accession Number (This Copy)</label>
                    <input type="text" class="form-control" name="accession_number" id="accession_number">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="isbn" class="form-label">ISBN (This Copy)</label>
                    <input type="text" class="form-control" name="isbn" id="isbn">
                </div>
            </div>
            <div class="mb-3">
              <label for="edit_title" class="form-label">Book Title</label>
              <input type="text" class="form-control" name="title" id="edit_title" required>
            </div>
            <div class="mb-3">
              <label for="edit_authors" class="form-label">Author(s)</label>
              <input type="text" class="form-control" name="authors" id="edit_authors" placeholder="Enter authors separated by comma">
              <small class="text-muted">Example: Author One, Author Two</small>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="edit_acquisition_type" class="form-label">Acquisition Type</label>
                    <select class="form-control" name="acquisition_type" id="edit_acquisition_type" onchange="toggleEditDonorField()">
                        <option value="Purchased">Purchased</option>
                        <option value="Donated">Donated</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3" id="editDonorField" style="display: none;">
                    <label for="edit_donor" class="form-label">Donor Name</label>
                    <input type="text" class="form-control" name="donor" id="edit_donor">
                </div>
            </div>
            <div class="mb-3">
              <label for="edit_description" class="form-label">Description</label>
              <textarea class="form-control" name="description" id="edit_description" rows="3" required></textarea>
            </div>
            <div class="mb-3">
              <label for="status" class="form-label">Status</label>
              <select class="form-control" name="status" id="status" required>
                <option value="available">Available</option>
                <option value="unavailable">Unavailable</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="genre" class="form-label">Genre</label>
              <select class="form-control" name="genre" id="genre" required>
                <?php if ($genreResult && mysqli_num_rows($genreResult) > 0): ?>
                  <?php while ($g = mysqli_fetch_assoc($genreResult)): ?>
                    <option value="<?php echo $g['id']; ?>">
                      <?php echo htmlspecialchars($g['name']); ?>
                    </option>
                  <?php endwhile; ?>
                <?php else: ?>
                  <option value="">No genres available</option>
                <?php endif; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Update Book</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Borrow History Modal -->
  <div class="modal fade" id="borrowHistoryModal" tabindex="-1" aria-labelledby="borrowHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-info text-white">
          <h5 class="modal-title" id="borrowHistoryModalLabel"><i class="fa-solid fa-history"></i> Borrow History - <span id="bookTitleLabel"></span></h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="borrowHistoryContent" style="max-height: 400px; overflow-y: auto;">
            <div class="text-center">
              <div class="spinner-border" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
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

  <!-- DataTable + Modal Script -->
  <script>
    $(document).ready(function () {
        $('#bookTable').DataTable({
            "pageLength": 10,
            "lengthMenu": [5, 10, 25, 50, 100],
            "ordering": false,
            "searching": true,
            "scrollY": "400px",
            "scrollCollapse": true,
            "paging": true
        });

        // Fill modal with book data for editing
        $(".edit").click(function() {
            var id = $(this).data("id");
            var accId = $(this).data("acc-id");
            var accNo = $(this).data("acc-no");
            var isbn = $(this).data("isbn");
            
            var title = $(this).data("title");
            var description = $(this).data("description");
            var status = $(this).data("status");
            var genreName = $(this).data("genre");
            
            var acquisition = $(this).data("acquisition") || 'Purchased';
            var donor = $(this).data("donor");

            $("#edit_id").val(id);
            $("#edit_acc_id").val(accId);
            $("#accession_number").val(accNo);
            $("#isbn").val(isbn);
            
            $("#edit_title").val(title);
            $("#edit_authors").val($(this).data("author"));
            $("#edit_description").val(description);
            $("#status").val(status);
            
            $("#edit_acquisition_type").val(acquisition);
            $("#edit_donor").val(donor);
            toggleEditDonorField();

            var img = $(this).data("image");
            if (img && img.indexOf('carousel-1.jpg') === -1) {
                $("#edit_image_preview").attr("src", img).show();
            } else {
                $("#edit_image_preview").hide();
            }

            // Set genre dropdown by matching text
            $("#genre option").each(function() {
                if ($(this).text() === genreName) {
                    $(this).prop("selected", true);
                }
            });

            var modal = new bootstrap.Modal(document.getElementById('editBookModal'));
            modal.show();
        });

        window.toggleEditDonorField = function() {
            var type = $("#edit_acquisition_type").val();
            if (type === 'Donated') {
                $("#editDonorField").show();
            } else {
                $("#editDonorField").hide();
                $("#edit_donor").val('');
            }
        };

        // View Borrow History
        $(".view-history").click(function() {
            var bookId = $(this).data("book-id");
            var bookTitle = $(this).data("book-title");

            $("#bookTitleLabel").text(bookTitle);
            
            // Fetch borrow history via AJAX
            $.ajax({
                type: "GET",
                url: "get_borrow_history.php",
                data: { book_id: bookId },
                dataType: "html",
                success: function(response) {
                    $("#borrowHistoryContent").html(response);
                },
                error: function() {
                    $("#borrowHistoryContent").html('<div class="alert alert-danger">Error loading borrow history</div>');
                }
            });

            var modal = new bootstrap.Modal(document.getElementById('borrowHistoryModal'));
            modal.show();
        });


    });
  </script>
</body>
</html>
