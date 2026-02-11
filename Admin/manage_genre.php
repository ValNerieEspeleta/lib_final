<?php
include '../includes/session.php';
include '../includes/dbcon.php';

$message = "";

// Handle Add Genre
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'add') {
    $genre_name = mysqli_real_escape_string($conn, $_POST['genre_name']);
    
    if (empty($genre_name)) {
        $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-exclamation-circle'></i> Genre name is required!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $checkGenre = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM lib_genres WHERE name = '$genre_name'");
        $checkRow = mysqli_fetch_assoc($checkGenre);
        
        if ($checkRow['cnt'] > 0) {
            $message = "<div class='alert alert-warning alert-dismissible fade show'><i class='fas fa-exclamation-triangle'></i> Genre already exists!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
        } else {
            if (mysqli_query($conn, "INSERT INTO lib_genres (name) VALUES ('$genre_name')")) {
                $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> Genre added successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            } else {
                $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-exclamation-circle'></i> Error: " . mysqli_error($conn) . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
            }
        }
    }
}

// Handle Delete Genre
if (isset($_GET['delete_id'])) {
    $genre_id = intval($_GET['delete_id']);
    if (mysqli_query($conn, "DELETE FROM lib_genres WHERE id = '$genre_id'")) {
        $message = "<div class='alert alert-success alert-dismissible fade show'><i class='fas fa-check-circle'></i> Genre deleted successfully!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    } else {
        $message = "<div class='alert alert-danger alert-dismissible fade show'><i class='fas fa-exclamation-circle'></i> Error deleting genre!<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// Fetch all genres
$genres_result = mysqli_query($conn, "SELECT id, name FROM lib_genres ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include "partials/head.php";?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    .card { box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: none; }
    .genre-list { max-height: 500px; overflow-y: auto; }
    .genre-item { padding: 12px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .genre-item:hover { background-color: #f8f9fa; }
  </style>
</head>
<body style="background-color: #f8f9fa;">
  <div class="container-scroller">
    <?php include "partials/navbar.php";?>
    <div class="container-fluid page-body-wrapper">
      <?php include "partials/settings-panel.php";?>
      <?php include "partials/sidebar.php";?>
      <div class="main-panel">
        <div class="content-wrapper">
          <div class="row">
            <div class="col-md-5">
              <div class="card">
                <div class="card-header bg-primary text-white">
                  <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Add New Genre</h5>
                </div>
                <div class="card-body">
                  <?php if (!empty($message)) echo $message; ?>
                  <form method="POST" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                      <label for="genre_name" class="form-label">Genre Name <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="genre_name" name="genre_name" placeholder="e.g., Fiction, Science, History" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                      <i class="fas fa-save"></i> Add Genre
                    </button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col-md-7">
              <div class="card">
                <div class="card-header bg-success text-white">
                  <h5 class="mb-0"><i class="fas fa-list"></i> All Genres</h5>
                </div>
                <div class="genre-list">
                  <?php if ($genres_result && mysqli_num_rows($genres_result) > 0): ?>
                    <?php while ($genre = mysqli_fetch_assoc($genres_result)): ?>
                      <div class="genre-item">
                        <span><strong><?php echo htmlspecialchars($genre['name']); ?></strong></span>
                        <a href="?delete_id=<?php echo $genre['id']; ?>" onclick="return confirm('Delete this genre?');" class="btn btn-sm btn-danger">
                          <i class="fas fa-trash-alt"></i>
                        </a>
                      </div>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <div class="p-4 text-center text-muted">
                      <i class="fas fa-inbox" style="font-size: 2rem;"></i>
                      <p class="mt-2">No genres added yet.</p>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <?php include 'partials/footer.php'; ?>
      </div>
    </div>  
  </div>
</body>
</html>
