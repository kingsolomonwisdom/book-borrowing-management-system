<?php
require_once 'connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: userlogin.php');
    exit();
}

// Fetch user information
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_User WHERE UserID = ?");
    $stmt->execute([$_SESSION['UserID']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch user's borrowed books
    $borrowedStmt = $pdo->prepare("
        SELECT b.*, br.BorrowDate, br.DueDate 
        FROM tbl_Books b 
        JOIN tbl_Borrowing br ON b.BookID = br.BookID 
        WHERE br.UserID = ? AND br.Status = 'Borrowed'
        ORDER BY br.BorrowDate DESC
    ");
    $borrowedStmt->execute([$_SESSION['UserID']]);
    $borrowedBooks = $borrowedStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch user's return history
    $historyStmt = $pdo->prepare("
        SELECT b.*, br.BorrowDate, br.ReturnDate 
        FROM tbl_Books b 
        JOIN tbl_Borrowing br ON b.BookID = br.BookID 
        WHERE br.UserID = ? AND br.Status = 'Returned'
        ORDER BY br.ReturnDate DESC
    ");
    $historyStmt->execute([$_SESSION['UserID']]);
    $returnHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching user information: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Information - BBMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #FFF5E4;
        }
        .navbar {
            background-color: #443627;
        }
        .nav-link {
            color: white !important;
        }
        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .profile-section {
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .book-list {
            margin-top: 20px;
        }
        .book-item {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }
        .book-item:last-child {
            border-bottom: none;
        }
        .section-title {
            color: #443627;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #443627;
        }
        .btn-primary {
            background-color: #443627;
            border: none;
        }
        .btn-primary:hover {
            background-color: #A27B5C;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        .status-borrowed {
            background-color: #ffc107;
            color: #000;
        }
        .status-returned {
            background-color: #28a745;
            color: #fff;
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

    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="profile-section">
            <h2 class="section-title">User Profile</h2>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Name:</strong> <?= htmlspecialchars($user['Name'] . ' ' . $user['LastName']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['Email']) ?></p>
                    <p><strong>ID Number:</strong> <?= htmlspecialchars($user['IDNumber']) ?></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Member Since:</strong> <?= date('F j, Y', strtotime($user['RegistrationDate'])) ?></p>
                    <p><strong>Role:</strong> <?= ucfirst(htmlspecialchars($user['Role'])) ?></p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h3 class="section-title">Currently Borrowed Books</h3>
                <div class="book-list">
                    <?php if (empty($borrowedBooks)): ?>
                        <p>No books currently borrowed.</p>
                    <?php else: ?>
                        <?php foreach ($borrowedBooks as $book): ?>
                            <div class="book-item">
                                <div class="row">
                                    <div class="col-md-9">
                                        <h5><?= htmlspecialchars($book['Title']) ?></h5>
                                        <p><strong>Author:</strong> <?= htmlspecialchars($book['Author']) ?></p>
                                        <p><strong>Borrowed Date:</strong> <?= date('F j, Y', strtotime($book['BorrowDate'])) ?></p>
                                        <p><strong>Due Date:</strong> <?= date('F j, Y', strtotime($book['DueDate'])) ?></p>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <span class="status-badge status-borrowed">Borrowed</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-body">
                <h3 class="section-title">Return History</h3>
                <div class="book-list">
                    <?php if (empty($returnHistory)): ?>
                        <p>No return history available.</p>
                    <?php else: ?>
                        <?php foreach ($returnHistory as $book): ?>
                            <div class="book-item">
                                <div class="row">
                                    <div class="col-md-9">
                                        <h5><?= htmlspecialchars($book['Title']) ?></h5>
                                        <p><strong>Author:</strong> <?= htmlspecialchars($book['Author']) ?></p>
                                        <p>
                                            <strong>Borrowed:</strong> <?= date('F j, Y', strtotime($book['BorrowDate'])) ?><br>
                                            <strong>Returned:</strong> <?= date('F j, Y', strtotime($book['ReturnDate'])) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <span class="status-badge status-returned">Returned</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 