<?php
require_once 'connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: userlogin.php');
    exit();
}

// Fetch recent books for initial display
try {
    $recentBooksStmt = $pdo->query("
        SELECT 
            BookID,
            ISBN,
            Title,
            Author,
            Publisher,
            DATE_FORMAT(PublishedDate, '%Y-%m-%d') as PublishedDate,
            Description,
            PageCount,
            CoverImageURL as ImageURL,
            AvailableCopies
        FROM tbl_Books 
        ORDER BY CreatedAt DESC 
        LIMIT 12
    ");
    $recentBooks = $recentBooksStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching recent books: " . $e->getMessage());
    $recentBooks = [];
}

// Add this to handle book status check
if (isset($_GET['check_status']) && isset($_GET['isbn'])) {
    try {
        $status = checkBookStatus($pdo, $_GET['isbn']);
        header('Content-Type: application/json');
        echo json_encode($status ?: ['error' => 'Book not found']);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// Function to check if a book exists in local database
function getBookFromDatabase($pdo, $isbn) {
    $stmt = $pdo->prepare("SELECT * FROM tbl_Books WHERE ISBN = ?");
    $stmt->execute([$isbn]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to add a book to the database
function addBookToDatabase($pdo, $bookData) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tbl_Books (Title, Author, ISBN, Publisher, PublishedDate, Description, ImageURL, TotalCopies, AvailableCopies)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)
        ");
        $stmt->execute([
            $bookData['Title'],
            $bookData['Author'],
            $bookData['ISBN'],
            $bookData['Publisher'],
            $bookData['PublishedDate'],
            $bookData['Description'],
            $bookData['ImageURL']
        ]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error adding book: " . $e->getMessage());
        return false;
    }
}

// Function to check book status
function checkBookStatus($pdo, $isbn) {
    try {
        $stmt = $pdo->prepare("
            SELECT b.BookID, b.AvailableCopies,
                   (SELECT COUNT(*) FROM tbl_Borrowing br 
                    WHERE br.BookID = b.BookID 
                    AND br.UserID = ? 
                    AND br.Status = 'Borrowed') as UserHasBook
            FROM tbl_Books b
            WHERE b.ISBN = ?
        ");
        $stmt->execute([$_SESSION['UserID'], $isbn]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error checking book status: " . $e->getMessage());
        return false;
    }
}

// Handle borrow/return requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        if (isset($_POST['action']) && isset($_POST['isbn'])) {
            $isbn = $_POST['isbn'];
            $userId = $_SESSION['UserID'];
            
            // Get or create book in database
            $book = getBookFromDatabase($pdo, $isbn);
            $bookId = $book ? $book['BookID'] : null;
            
            if (!$bookId && isset($_POST['bookData'])) {
                $bookData = json_decode($_POST['bookData'], true);
                $bookId = addBookToDatabase($pdo, $bookData);
            }
            
            if (!$bookId) {
                throw new Exception('Could not process book data.');
            }
            
            if ($_POST['action'] === 'borrow') {
                // Set due date to 14 days from now
                $dueDate = date('Y-m-d', strtotime('+14 days'));
                
                // Call the borrow procedure
                $stmt = $pdo->prepare("CALL sp_BorrowBook(?, ?, ?)");
                $stmt->execute([$userId, $bookId, $dueDate]);
                
                $response['success'] = true;
                $response['message'] = 'Book borrowed successfully!';
            }
            elseif ($_POST['action'] === 'return') {
                // Get the active borrowing record
                $stmt = $pdo->prepare("
                    SELECT BorrowID 
                    FROM tbl_Borrowing 
                    WHERE UserID = ? AND BookID = ? AND Status = 'Borrowed'
                ");
                $stmt->execute([$userId, $bookId]);
                $borrowing = $stmt->fetch();
                
                if ($borrowing) {
                    // Call the return procedure
                    $stmt = $pdo->prepare("CALL sp_ReturnBook(?)");
                    $stmt->execute([$borrowing['BorrowID']]);
                    
                    $response['success'] = true;
                    $response['message'] = 'Book returned successfully!';
                } else {
                    throw new Exception('No active borrowing found for this book.');
                }
            }
        }
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Function to check if a book is borrowed by the current user
function isBookBorrowed($pdo, $bookId, $userId) {
    $stmt = $pdo->prepare("
        SELECT Status 
        FROM tbl_Borrowing 
        WHERE BookID = ? AND UserID = ? 
        AND Status = 'Borrowed'
    ");
    $stmt->execute([$bookId, $userId]);
    return $stmt->fetch() !== false;
}

// Function to check if a book is available
function isBookAvailable($pdo, $bookId) {
    $stmt = $pdo->prepare("
        SELECT AvailableCopies 
        FROM tbl_Books 
        WHERE BookID = ?
    ");
    $stmt->execute([$bookId]);
    $result = $stmt->fetch();
    return $result && $result['AvailableCopies'] > 0;
}

// Handle AJAX search request
if (isset($_GET['search'])) {
    try {
        $search = $_GET['search'];
        
        // Search Google Books API
        $googleApiUrl = "https://www.googleapis.com/books/v1/volumes?q=" . urlencode($search) . "&maxResults=20";
        $googleResults = @file_get_contents($googleApiUrl);
        $books = [];
        
        if ($googleResults) {
            $googleData = json_decode($googleResults, true);
            if (isset($googleData['items'])) {
                foreach ($googleData['items'] as $book) {
                    $volumeInfo = $book['volumeInfo'];
                    $books[] = [
                        'Title' => $volumeInfo['title'] ?? 'Unknown Title',
                        'Author' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : 'Unknown Author',
                        'ISBN' => $volumeInfo['industryIdentifiers'][0]['identifier'] ?? '',
                        'ImageURL' => isset($volumeInfo['imageLinks']) ? 
                            str_replace('http://', 'https://', $volumeInfo['imageLinks']['thumbnail']) : 
                            'images/default-book.jpg',
                        'Description' => $volumeInfo['description'] ?? 'No description available.',
                        'Publisher' => $volumeInfo['publisher'] ?? 'Unknown Publisher',
                        'PublishedDate' => $volumeInfo['publishedDate'] ?? 'Unknown Date',
                        'PageCount' => $volumeInfo['pageCount'] ?? 'Unknown',
                        'Categories' => isset($volumeInfo['categories']) ? implode(', ', $volumeInfo['categories']) : 'Unknown',
                        'PreviewLink' => $book['volumeInfo']['previewLink'] ?? '',
                        'IsGoogleBook' => true
                    ];
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $books]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Fetch all books for initial display
try {
    $stmt = $pdo->query("SELECT * FROM tbl_Books ORDER BY Title LIMIT 10");
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching books: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book Search - BBMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #443627;
            --primary-light: #5a483a;
            --accent-color: #8B4513;
            --card-bg: rgba(255, 248, 225, 0.95);
        }

        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        body {
            font-family: 'Nunito', sans-serif;
            background-image: url('bg2.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .navbar {
            background-color: var(--primary-color);
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-link {
            color: white !important;
            font-weight: 600;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        #logo {
            text-align: center;
            padding: 2rem 0;
        }

        #logo img {
            max-width: 300px;
            height: auto;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
        }

        .search-container {
            background-color: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            max-width: 800px;
            margin: 0 auto 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .search-input {
            background-color: rgba(255, 255, 255, 0.9);
            border: 2px solid var(--primary-color);
            border-radius: 30px;
            padding: 1rem 1.5rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            background-color: white;
            box-shadow: 0 0 0 0.25rem rgba(68, 54, 39, 0.25);
            border-color: var(--primary-color);
        }

        .search-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .search-button:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        .search-status {
            background-color: var(--card-bg);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .books-container {
            padding: 0 1rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 2rem;
            padding: 1rem 0;
        }

        .book-card {
            background-color: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }

        .book-image-container {
            position: relative;
            padding-top: 140%;
            background-color: #f8f9fa;
            overflow: hidden;
        }

        .book-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 1rem;
            transition: transform 0.3s ease;
        }

        .book-card:hover .book-image {
            transform: scale(1.05);
        }

        .book-info {
            padding: 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            background: linear-gradient(to bottom, rgba(255,255,255,0.5), rgba(255,255,255,0));
        }

        .book-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }

        .book-author {
            color: var(--primary-light);
            font-size: 1rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .book-badges {
            margin-top: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .badge {
            padding: 0.5em 1em;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 20px;
        }

        .view-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 0.8rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
        }

        .view-button:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }

        .modal-content {
            background-color: var(--card-bg);
            border-radius: 15px;
            overflow: hidden;
        }

        .modal-header {
            background-color: var(--primary-color);
            color: white;
            border-bottom: none;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-image {
            max-height: 400px;
            width: 100%;
            object-fit: contain;
            background-color: white;
            border-radius: 10px;
            padding: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .book-details-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .book-details-label {
            font-weight: 700;
            color: var(--primary-color);
            padding-right: 1rem;
        }

        .book-description {
            background-color: rgba(255, 255, 255, 0.5);
            padding: 1rem;
            border-radius: 10px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 1rem;
            line-height: 1.6;
        }

        @media (max-width: 1200px) {
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 1rem;
            }

            .search-container {
                margin: 1rem;
                padding: 1.5rem;
            }

            .book-info {
                padding: 1rem;
            }

            .book-title {
                font-size: 1.1rem;
            }

            #logo img {
                max-width: 200px;
            }
        }

        @media (max-width: 576px) {
            .books-grid {
                grid-template-columns: 1fr;
            }

            .modal-dialog {
                margin: 0.5rem;
            }

            .book-details-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }

            .book-details-label {
                margin-bottom: 0;
            }
        }

        /* Loading animation */
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="bookdetails.php">HOME</a></li>
                    <li class="nav-item"><a class="nav-link" href="borrowform.php">DASHBOARD</a></li>
                    <li class="nav-item"><a class="nav-link" href="visit.php">VISITATIONS</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">RECORDS</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="abtuser.php">Transactions</a></li>
                            <li><a class="dropdown-item" href="fines.php">Fines</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="userlogin.php">LOG OUT</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div id="logo">
        <img src="logo2.png" alt="BBMS Logo">
    </div>

    <div class="search-container">
        <form id="searchForm" class="d-flex flex-column gap-3">
            <input type="text" class="form-control search-input" id="searchInput" 
                   placeholder="Search for books by title, author, or ISBN..." required>
            <button type="submit" class="btn search-button">
                <span class="search-button-text">Search Books</span>
                <span class="spinner-border spinner-border-sm d-none" role="status"></span>
            </button>
        </form>
    </div>

    <div class="books-container">
        <div id="searchStatus" class="search-status text-center d-none"></div>
        <div class="books-grid" id="booksContainer">
            <?php if (!empty($recentBooks)): ?>
                <?php foreach ($recentBooks as $book): ?>
                    <div class="book-card">
                        <div class="book-image-container">
                            <img src="<?= htmlspecialchars($book['ImageURL'] ?: 'images/default-book.jpg') ?>" 
                                 class="book-image" 
                                 alt="<?= htmlspecialchars($book['Title']) ?>"
                                 onerror="this.onerror=null; this.src='images/default-book.jpg';">
                        </div>
                        <div class="book-info">
                            <h5 class="book-title"><?= htmlspecialchars($book['Title']) ?></h5>
                            <p class="book-author">By <?= htmlspecialchars($book['Author']) ?></p>
                            <div class="book-badges">
                                <span class="badge bg-secondary"><?= htmlspecialchars($book['PublishedDate']) ?></span>
                                <?php if ($book['PageCount']): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($book['PageCount']) ?> pages</span>
                                <?php endif; ?>
                                <?php if ($book['AvailableCopies'] > 0): ?>
                                    <span class="badge bg-success">Available</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Not Available</span>
                                <?php endif; ?>
                            </div>
                            <button class="view-button" onclick='showBookDetails(<?= json_encode($book) ?>)'>
                                View Details
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center w-100">
                    <h3 class="text-white">Recently Added Books Will Appear Here</h3>
                    <p class="text-white">Use the search bar above to find specific books</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Book Details Modal -->
    <div class="modal fade" id="bookDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Book Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bookDetailsContent">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const searchForm = document.getElementById('searchForm');
        const searchInput = document.getElementById('searchInput');
        const booksContainer = document.getElementById('booksContainer');
        const searchStatus = document.getElementById('searchStatus');
        const searchButton = searchForm.querySelector('button[type="submit"]');
        const searchButtonText = searchButton.querySelector('.search-button-text');
        const searchSpinner = searchButton.querySelector('.spinner-border');

        function setLoading(isLoading) {
            searchButton.disabled = isLoading;
            searchSpinner.classList.toggle('d-none', !isLoading);
            searchButtonText.textContent = isLoading ? 'Searching...' : 'Search Books';
        }

        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = searchInput.value.trim();
            
            if (query.length < 2) {
                searchStatus.innerHTML = '<div class="alert alert-warning">Please enter at least 2 characters to search.</div>';
                searchStatus.classList.remove('d-none');
                return;
            }

            setLoading(true);
            searchStatus.classList.add('d-none');
            booksContainer.innerHTML = '<div class="loading-spinner"></div>';

            fetch(`bookdetails.php?search=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.error || 'Failed to fetch books');
                    }
                    
                    const books = result.data;
                    booksContainer.innerHTML = '';
                    
                    if (books.length > 0) {
                        searchStatus.innerHTML = `<h4 class="m-0">Found ${books.length} books matching "${query}"</h4>`;
                        searchStatus.classList.remove('d-none');
                        
                        books.forEach(book => {
                            const bookCard = document.createElement('div');
                            bookCard.className = 'book-card';
                            const bookData = JSON.stringify(book).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                            bookCard.innerHTML = `
                                <div class="book-image-container">
                                    <img src="${book.ImageURL}" class="book-image" alt="${book.Title}"
                                         onerror="this.onerror=null; this.src='images/default-book.jpg';">
                                </div>
                                <div class="book-info">
                                    <h5 class="book-title">${book.Title}</h5>
                                    <p class="book-author">By ${book.Author}</p>
                                    <div class="book-badges">
                                        <span class="badge bg-secondary">${book.PublishedDate}</span>
                                        ${book.PageCount !== 'Unknown' ? 
                                            `<span class="badge bg-info">${book.PageCount} pages</span>` : ''}
                                    </div>
                                    <button class="view-button" onclick='showBookDetails(${bookData})'>
                                        View Details
                                    </button>
                                </div>
                            `;
                            booksContainer.appendChild(bookCard);
                        });
                    } else {
                        searchStatus.innerHTML = '<div class="alert alert-info">No books found. Try a different search term.</div>';
                        searchStatus.classList.remove('d-none');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    searchStatus.innerHTML = '<div class="alert alert-danger">Error searching for books. Please try again.</div>';
                    searchStatus.classList.remove('d-none');
                    booksContainer.innerHTML = '';
                })
                .finally(() => {
                    setLoading(false);
                });
        });

        function showBookDetails(book) {
            const modal = new bootstrap.Modal(document.getElementById('bookDetailsModal'));
            const bookJson = JSON.stringify(book).replace(/'/g, "&#39;").replace(/"/g, "&quot;");
            document.getElementById('bookDetailsContent').innerHTML = `
                <div class="book-details">
                    <div class="row">
                        <div class="col-md-4">
                            <img src="${book.ImageURL || 'images/default-book.jpg'}" 
                                 class="modal-image" 
                                 alt="${book.Title}"
                                 onerror="this.onerror=null; this.src='images/default-book.jpg';">
                        </div>
                        <div class="col-md-8">
                            <h4>${book.Title}</h4>
                            <div class="book-details-grid">
                                <span class="book-details-label">Author</span>
                                <span>${book.Author}</span>
                                
                                <span class="book-details-label">Publisher</span>
                                <span>${book.Publisher || 'Unknown'}</span>
                                
                                <span class="book-details-label">Published Date</span>
                                <span>${book.PublishedDate || 'Unknown'}</span>
                                
                                <span class="book-details-label">ISBN</span>
                                <span>${book.ISBN || 'Not available'}</span>
                                
                                ${book.PageCount ? `
                                    <span class="book-details-label">Pages</span>
                                    <span>${book.PageCount}</span>
                                ` : ''}
                                
                                ${book.Categories ? `
                                    <span class="book-details-label">Categories</span>
                                    <span>${book.Categories}</span>
                                ` : ''}
                                
                                <span class="book-details-label">Status</span>
                                <span>${book.AvailableCopies > 0 ? 'Available' : 'Not Available'}</span>
                            </div>
                            
                            <div class="mt-4">
                                <h5>Description</h5>
                                <div class="book-description">${book.Description || 'No description available.'}</div>
                            </div>
                            
                            <div class="mt-4 d-flex gap-3">
                                <button id="borrow-btn-${book.ISBN || book.BookID}" 
                                        onclick="borrowBook(${bookJson})" 
                                        class="btn search-button" style="display: none;">
                                    Borrow Book
                                </button>
                                <button id="return-btn-${book.ISBN || book.BookID}" 
                                        onclick="returnBook('${book.ISBN || book.BookID}')" 
                                        class="btn btn-warning" style="display: none;">
                                    Return Book
                                </button>
                                ${book.PreviewLink ? `
                                    <a href="${book.PreviewLink}" target="_blank" class="btn search-button">
                                        View on Google Books
                                    </a>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            modal.show();
            checkBookAvailability(book.ISBN || book.BookID);
        }

        function borrowBook(book) {
            const bookData = {
                Title: book.Title,
                Author: book.Author,
                ISBN: book.ISBN || generateISBN(), // Generate ISBN if not provided
                Publisher: book.Publisher,
                PublishedDate: book.PublishedDate,
                Description: book.Description,
                ImageURL: book.ImageURL,
                PageCount: book.PageCount || null,
                BookID: book.BookID || null
            };

            fetch('bookdetails.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'borrow',
                    isbn: bookData.ISBN,
                    bookData: JSON.stringify(bookData)
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    // Refresh book status
                    checkBookAvailability(bookData.ISBN);
                    // Refresh the page to update the book list
                    location.reload();
                } else {
                    throw new Error(result.message || 'Failed to borrow book');
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        function returnBook(isbn) {
            fetch('bookdetails.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'return',
                    isbn: isbn
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(result.message);
                    // Refresh book status
                    checkBookAvailability(isbn);
                } else {
                    throw new Error(result.message || 'Failed to return book');
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        function checkBookAvailability(isbn) {
            fetch(`bookdetails.php?check_status=1&isbn=${encodeURIComponent(isbn)}`)
                .then(response => response.json())
                .then(status => {
                    const borrowBtn = document.getElementById(`borrow-btn-${isbn}`);
                    const returnBtn = document.getElementById(`return-btn-${isbn}`);
                    
                    if (borrowBtn && returnBtn) {
                        if (status.UserHasBook > 0) {
                            borrowBtn.style.display = 'none';
                            returnBtn.style.display = 'block';
                        } else if (status.AvailableCopies > 0) {
                            borrowBtn.style.display = 'block';
                            returnBtn.style.display = 'none';
                        } else {
                            borrowBtn.style.display = 'none';
                            returnBtn.style.display = 'none';
                        }
                    }
                })
                .catch(error => console.error('Error checking book status:', error));
        }

        // Add this helper function for generating ISBN when not provided
        function generateISBN() {
            // Generate a 13-digit number starting with 978 (standard book prefix)
            const prefix = '978';
            const random = Math.floor(Math.random() * 10000000000).toString().padStart(10, '0');
            return prefix + random;
        }
    </script>
</body>
</html> 