<?php
include '../includes/dbcon.php';

header('Content-Type: application/json');

if (isset($_GET['book_id'])) {
    $book_id = mysqli_real_escape_string($conn, $_GET['book_id']);
    
    $query = "SELECT lib_books.*, lib_genres.name as genre_name, lib_publishers.name as publisher_name, lib_publishers.place_of_publication 
              FROM lib_books 
              LEFT JOIN lib_genres ON lib_books.genre_id = lib_genres.id
              LEFT JOIN lib_publishers ON lib_books.publisher_id = lib_publishers.id
              WHERE lib_books.book_id = '$book_id' 
              LIMIT 1";
    
    $result = mysqli_query($conn, $query);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $book = mysqli_fetch_assoc($result);
        echo json_encode([
            'success' => true,
            'title' => $book['title'] ?? '',
            'description' => $book['description'] ?? '',
            'genre_id' => $book['genre_id'] ?? '',
            'publisher_name' => $book['publisher_name'] ?? '',
            'place_of_publication' => $book['place_of_publication'] ?? '',
            'status' => $book['status'] ?? 'available',
            'isbn_id' => $book['isbn_id'] ?? '',
            'accession_id' => $book['accession_id'] ?? ''
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>