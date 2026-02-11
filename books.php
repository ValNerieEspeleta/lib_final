<?php
session_start();
include 'includes/dbcon.php';

// Fetch books with JOIN to get genre_name, author_name, and accession_number (individual rows)
$books = [];
$sql = "SELECT b.book_id, b.title, b.description, b.status, b.genre_id, b.book_image,
               an.acquisition_type, an.donor,
               g.name as genre_name, 
               (SELECT GROUP_CONCAT(CONCAT(a.first_name, ' ', a.last_name) SEPARATOR ', ') 
                FROM lib_book_authors ba 
                JOIN lib_authors a ON ba.author_id = a.id 
                WHERE ba.book_id = b.book_id) as author_name,
               an.accession_number,
               an.status as accession_status
        FROM lib_books b
        LEFT JOIN lib_genres g ON b.genre_id = g.id
        LEFT JOIN lib_accession_numbers an ON b.book_id = an.book_id
        ORDER BY b.book_id DESC, an.accession_number ASC";
$result = mysqli_query($conn, $sql);
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Shorten description (limit to 20 words for preview)
        $desc = strip_tags($row['description']); 
        $words = explode(" ", $desc);
        if (count($words) > 20) {
            $desc = implode(" ", array_slice($words, 0, 20)) . "...";
        }
        $row['short_description'] = $desc;
        $books[] = $row;
    }
}

// Fetch all genres for dropdown
$genres = [];
$genreSql = "SELECT id, name FROM lib_genres ORDER BY name ASC";
$genreResult = mysqli_query($conn, $genreSql);
if ($genreResult && mysqli_num_rows($genreResult) > 0) {
    while ($row = mysqli_fetch_assoc($genreResult)) {
        $genres[] = $row;
    }
}
// Helper to find book image
function getBookImage($filename) {
    if (empty($filename)) return 'img/carousel-1.jpg'; // Default library image
    
    if (strpos($filename, '/') !== false && file_exists($filename)) {
        return $filename;
    }

    $locations = ['', 'uploads/', 'uploads/books/', 'img/'];
    foreach ($locations as $loc) {
        $path = $loc . basename($filename);
        if (file_exists($path)) return $path;
    }
    return 'img/carousel-1.jpg';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Library Books</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <!-- Favicon -->
    <link href="img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&family=Roboto:wght@700;800&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.4/font/bootstrap-icons.css">

    <!-- Libraries Stylesheet -->
    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="css/style.css" rel="stylesheet">

    <style>
        .search-sort-bar { margin-bottom: 30px; }
        .book-card { display: block; }
        .service-item { height: 100%; display: flex; flex-direction: column; }
        .service-inner { flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
        .service-text { flex-grow: 1; }
        .read-more-btn { margin-top: auto; }
        .library-note { margin-top: 15px; font-style: italic; color: #555; }
    </style>
</head>

<body>
    <!-- Spinner -->
    <?php include 'includes/spinner.php'; ?>

    <!-- Topbar -->
    <?php include 'includes/topbar.php'; ?>

    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Page Header -->
    <div class="container-fluid page-header pt-5 mb-6 wow fadeIn" data-wow-delay="0.1s">
        <div class="container text-center pt-5">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="bg-white p-5">
                        <h1 class="display-6 text-uppercase mb-3 animated slideInDown">Books</h1>
                        <nav aria-label="breadcrumb animated slideInDown">
                            <ol class="breadcrumb justify-content-center mb-0">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item"><a href="#">Pages</a></li>
                                <li class="breadcrumb-item active">Books</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Page Header End -->

    <!-- Main Content -->
    <div class="container-fluid service pt-6 pb-6">
        <div class="container">
            <div class="text-center mx-auto wow fadeInUp" data-wow-delay="0.1s" style="max-width: 600px;">
                <h1 class="display-6 text-uppercase mb-5">Library Books Collection</h1>
            </div>

            <!-- Search and Sort -->
            <div class="row search-sort-bar mb-4">
                <div class="col-md-6 mb-2">
                    <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search by title...">
                </div>
                <div class="col-md-6 mb-2">
                    <select id="genreFilter" class="form-control">
                        <option value="">📂 All Genres</option>
                        <?php foreach ($genres as $genre): ?>
                            <option value="<?php echo $genre['id']; ?>">
                                <?php echo htmlspecialchars($genre['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Books Grid -->
            <div class="row g-4" id="booksContainer">
                <?php if (!empty($books)): ?>
                    <?php foreach ($books as $index => $book): ?>
                        <div class="col-lg-3 col-md-6 wow fadeInUp book-card"
                             data-wow-delay="<?php echo (0.1 * ($index % 4 + 1)); ?>s"
                             data-title="<?php echo strtolower($book['title']); ?>"
                             data-author="<?php echo strtolower($book['author_name'] ?? ''); ?>"
                             data-book-id="<?php echo $book['book_id']; ?>"
                             data-accession="<?php echo strtolower($book['accession_number'] ?? ''); ?>"
                             data-genre-id="<?php echo $book['genre_id']; ?>">
                            <div class="service-item h-100">
                                <div class="position-relative overflow-hidden">
                                    <img class="img-fluid w-100" src="<?php echo getBookImage($book['book_image']); ?>" alt="<?php echo htmlspecialchars($book['title']); ?>" style="height: 250px; object-fit: cover;">
                                </div>
                                <div class="service-inner pb-5">
                                    <div class="service-text px-5 pt-4">
                                        <h5 class="text-uppercase"><?php echo htmlspecialchars($book['title']); ?></h5>
                                        
                                        <?php if (!empty($book['author_name'])): ?>
                                            <p class="mb-1 text-primary"><strong>Author:</strong> <?php echo htmlspecialchars($book['author_name']); ?></p>
                                        <?php endif; ?>
                                        
                                        <p class="mb-1 text-muted small"><strong>Call No:</strong> <?php echo htmlspecialchars($book['book_id']); ?></p>
                                        
                                        <?php if (!empty($book['accession_number'])): ?>
                                            <p class="mb-1 text-truncate small">
                                                <strong>Access No:</strong> <?php echo htmlspecialchars($book['accession_number']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($book['genre_name'])): ?>
                                            <p class="mb-1"><strong>Genre:</strong> <?php echo htmlspecialchars($book['genre_name']); ?></p>
                                        <?php endif; ?>

                                        <p class="mb-2 small">
                                            <strong>Acquisition:</strong> 
                                            <?php if ($book['acquisition_type'] == 'Donated'): ?>
                                                <span class="text-warning"><i class="fas fa-hand-holding-heart"></i> Donated by: <?php echo htmlspecialchars($book['donor'] ?? 'Anonymous'); ?></span>
                                            <?php else: ?>
                                                <span class="text-secondary"><i class="fas fa-school"></i> Purchased by School</span>
                                            <?php endif; ?>
                                        </p>

                                        <p class="mb-3"><?php echo htmlspecialchars($book['short_description']); ?></p>

                                        <p>
                                            <strong>Status: </strong>
                                            <?php 
                                            // Determine status to show: Accession status takes precedence if available
                                            $displayStatus = !empty($book['accession_status']) ? $book['accession_status'] : $book['status'];
                                            if (strtolower($displayStatus) === 'available'): ?>
                                                <span class="badge bg-success">Available</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Borrowed</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <!-- Read More Button (opens modal) -->
                                    <button 
                                        class="btn btn-light px-3 read-more-btn align-self-start" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#bookModal"
                                        data-title="<?php echo htmlspecialchars($book['title']); ?>"
                                        data-description="<?php echo htmlspecialchars($book['description']); ?>"
                                        data-status="<?php echo htmlspecialchars(!empty($book['accession_status']) ? $book['accession_status'] : $book['status']); ?>"
                                        data-genre="<?php echo htmlspecialchars($book['genre_name'] ?? ''); ?>"
                                        data-author="<?php echo htmlspecialchars($book['author_name'] ?? ''); ?>"
                                        data-book-id="<?php echo htmlspecialchars($book['book_id']); ?>"
                                        data-accession="<?php echo htmlspecialchars($book['accession_number'] ?? ''); ?>"
                                        data-acquisition="<?php echo htmlspecialchars($book['acquisition_type'] ?? 'Purchased'); ?>"
                                        data-donor="<?php echo htmlspecialchars($book['donor'] ?? ''); ?>"
                                        data-image="<?php echo getBookImage($book['book_image']); ?>">
                                        Read More<i class="bi bi-chevron-double-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12 text-center">
                        <p class="text-muted">No books available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Main Content End -->

    <!-- Book Modal -->
    <div class="modal fade" id="bookModal" tabindex="-1" aria-labelledby="bookModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="bookModalLabel" style="color: white;">Book Details</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <img id="modalImage" src="" class="img-fluid rounded shadow" alt="Book Cover">
                </div>
                <div class="col-md-8">
                    <h3 id="modalTitle"></h3>
                    <h5 id="modalAuthor" class="text-primary mb-3"></h5>
                    <p><strong>Call Number: </strong><span id="modalBookId"></span></p>
                    <p><strong>Accession Numbers: </strong><span id="modalAccession"></span></p>
                    <p><strong>Genre: </strong><span id="modalGenre"></span></p>
                    <p><strong>Acquisition: </strong><span id="modalAcquisition"></span></p>
                    <div id="modalDonorContainer" style="display: none;">
                        <p><strong>Donated By: </strong><span id="modalDonor"></span></p>
                    </div>
                </div>
            </div>
            <p id="modalDescription"></p>
            <p><strong>Status: </strong><span id="modalStatus"></span></p>
            <div class="library-note">
                📚 Note: To read the full content of this book, please visit the <strong>Santa Rita College of Pampanga Library</strong>.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="color: white;">Close</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- Back to Top -->
    <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>

    <!-- JS -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="js/main.js"></script>

    <!-- Realtime Search & Filter -->
    <script>
        const searchInput = document.getElementById("searchInput");
        const genreFilter = document.getElementById("genreFilter");
        const bookCards = document.querySelectorAll(".book-card");

        function filterBooks() {
            const searchText = searchInput.value.toLowerCase();
            const genreValue = genreFilter.value;

            bookCards.forEach(card => {
                const title = card.getAttribute("data-title");
                const author = card.getAttribute("data-author") || "";
                const bookId = card.getAttribute("data-book-id") || "";
                const genreId = card.getAttribute("data-genre-id");

                const matchesSearch = title.includes(searchText) || author.includes(searchText) || bookId.includes(searchText);
                const matchesGenre = genreValue === "" || genreId === genreValue;

                if (matchesSearch && matchesGenre) {
                    card.style.display = "block";
                } else {
                    card.style.display = "none";
                }
            });
        }

        searchInput.addEventListener("keyup", filterBooks);
        genreFilter.addEventListener("change", filterBooks);

        // Modal setup
        const modalTitle = document.getElementById('modalTitle');
        const modalAuthor = document.getElementById('modalAuthor');
        const modalBookId = document.getElementById('modalBookId');
        const modalAccession = document.getElementById('modalAccession');
        const modalDescription = document.getElementById('modalDescription');
        const modalStatus = document.getElementById('modalStatus');
        const modalGenre = document.getElementById('modalGenre');
        const modalAcquisition = document.getElementById('modalAcquisition');
        const modalDonor = document.getElementById('modalDonor');
        const modalDonorContainer = document.getElementById('modalDonorContainer');
        const modalImage = document.getElementById('modalImage');

        document.querySelectorAll('.read-more-btn').forEach(button => {
            button.addEventListener('click', () => {
                modalTitle.textContent = button.getAttribute('data-title');
                modalImage.src = button.getAttribute('data-image');
                
                const author = button.getAttribute('data-author');
                if (author) {
                    modalAuthor.textContent = "By " + author;
                    modalAuthor.style.display = 'block';
                } else {
                    modalAuthor.textContent = '';
                    modalAuthor.style.display = 'none';
                }

                modalBookId.textContent = button.getAttribute('data-book-id') || 'N/A';
                modalAccession.textContent = button.getAttribute('data-accession') || 'N/A';
                
                modalDescription.textContent = button.getAttribute('data-description');
                
                const status = button.getAttribute('data-status');
                if (status === 'available') {
                    modalStatus.innerHTML = '<span class="badge bg-success">Available</span>';
                } else {
                    modalStatus.innerHTML = '<span class="badge bg-danger">Borrowed</span>';
                }

                modalGenre.textContent = button.getAttribute('data-genre') || 'N/A';

                const acquisition = button.getAttribute('data-acquisition') || 'Purchased';
                const donor = button.getAttribute('data-donor');

                if (acquisition === 'Donated') {
                    modalAcquisition.innerHTML = '<i class="fas fa-hand-holding-heart text-warning"></i> Donated by: <strong>' + (donor || 'Anonymous') + '</strong>';
                    modalDonorContainer.style.display = 'none'; // No need for separate container now
                } else {
                    modalAcquisition.innerHTML = '<i class="fas fa-school text-primary"></i> Purchased by School';
                    modalDonorContainer.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
