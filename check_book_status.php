<?php
require_once 'connection.php';
session_start();

header('Content-Type: application/json');

if (!isset($_GET['isbn'])) {
    echo json_encode(['error' => 'ISBN not provided']);
    exit;
}

$isbn = $_GET['isbn'];
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

try {
    // Check if book exists in database
    $stmt = $pdo->prepare("SELECT id FROM books WHERE isbn = ?");
    $stmt->execute([$isbn]);
    $book = $stmt->fetch();

    if ($book) {
        $bookId = $book['id'];
        
        // Check if book is currently borrowed
        $stmt = $pdo->prepare("
            SELECT id 
            FROM borrows 
            WHERE book_id = ? 
            AND return_date IS NULL 
            AND status = 'borrowed'
        ");
        $stmt->execute([$bookId]);
        $borrow = $stmt->fetch();

        echo json_encode([
            'exists' => true,
            'bookId' => $bookId,
            'isBorrowed' => $borrow ? true : false,
            'borrowId' => $borrow ? $borrow['id'] : null
        ]);
    } else {
        echo json_encode(['exists' => false]);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred']);
}
?> 