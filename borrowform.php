<?php
require_once 'connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: userlogin.php');
    exit();
}

$targetDir = "uploads/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$errorMessage = ""; // Variable to store general error message
$errors = []; // Array to store individual field errors

// ADD BORROW
if (isset($_POST['submit'])) {
    $userId = $_SESSION['UserID'];
    $bookId = mysqli_real_escape_string($con, $_POST['bookId']);
    $dueDate = date('Y-m-d', strtotime('+14 days')); // Set due date to 14 days from now
    
    // Validate inputs
    if (empty($bookId)) {
        $errors['bookId'] = "Book ID is required.";
    }
    
    // If there are no errors, proceed with saving the data
    if (empty($errors)) {
        try {
            // Call the borrow procedure
            $stmt = $pdo->prepare("CALL sp_BorrowBook(?, ?, ?)");
            $stmt->execute([$userId, $bookId, $dueDate]);
            
            header("Location: borrowform.php");
            exit();
        } catch (PDOException $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Please fix the errors above and try again.";
    }
}

// EDIT BORROW
if (isset($_POST['edit'])) {
    $borrowId = mysqli_real_escape_string($con, $_POST['borrowId']);
    $returnDate = date('Y-m-d'); // Current date for return
    
    try {
        // Call the return procedure
        $stmt = $pdo->prepare("CALL sp_ReturnBook(?)");
        $stmt->execute([$borrowId]);
        
        header("Location: borrowform.php");
        exit();
    } catch (PDOException $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// DELETE BORROW (This should probably be removed or restricted as it breaks referential integrity)
if (isset($_GET['delete'])) {
    $borrowId = mysqli_real_escape_string($con, $_GET['delete']);
    try {
        // Instead of deleting, we'll mark it as returned
        $stmt = $pdo->prepare("CALL sp_ReturnBook(?)");
        $stmt->execute([$borrowId]);
        
        header("Location: borrowform.php");
        exit();
    } catch (PDOException $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
}

// Fetch all records for display
try {
    $query = $pdo->query("
        SELECT 
            b.BorrowID,
            u.Name AS BorrowerName,
            bk.Title AS BookTitle,
            bk.BookID,
            b.BorrowDate,
            b.DueDate,
            b.ReturnDate,
            b.Status,
            bk.CoverImageURL AS BookImage
        FROM tbl_Borrowing b
        JOIN tbl_User u ON b.UserID = u.UserID
        JOIN tbl_Books bk ON b.BookID = bk.BookID
        ORDER BY b.BorrowDate DESC
    ");
    $borrowings = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Error fetching records: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Borrow Management - BBMS</title>
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
        .btn-primary {
            background-color: #443627;
            border: none;
        }
        .btn-primary:hover {
            background-color: #A27B5C;
        }
        .table {
            margin-bottom: 0;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9rem;
        }
        .status-Borrowed {
            background-color: #ffc107;
            color: #000;
        }
        .status-Returned {
            background-color: #28a745;
            color: #fff;
        }
        .status-Overdue {
            background-color: #dc3545;
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
        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="mb-0">Borrow Management</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="bookId" class="form-label">Book ID</label>
                                <input type="text" class="form-control" id="bookId" name="bookId" required>
                                <?php if (isset($errors['bookId'])): ?>
                                    <div class="text-danger"><?= htmlspecialchars($errors['bookId']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" name="submit" class="btn btn-primary">Borrow Book</button>
                        </div>
                    </div>
                </form>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Borrower</th>
                            <th>Book</th>
                            <th>Borrow Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($borrowings as $borrow): ?>
                            <tr>
                                <td><?= htmlspecialchars($borrow['BorrowerName']) ?></td>
                                <td><?= htmlspecialchars($borrow['BookTitle']) ?></td>
                                <td><?= date('Y-m-d', strtotime($borrow['BorrowDate'])) ?></td>
                                <td><?= date('Y-m-d', strtotime($borrow['DueDate'])) ?></td>
                                <td><?= $borrow['ReturnDate'] ? date('Y-m-d', strtotime($borrow['ReturnDate'])) : '-' ?></td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($borrow['Status']) ?>">
                                        <?= htmlspecialchars($borrow['Status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($borrow['Status'] === 'Borrowed' || $borrow['Status'] === 'Overdue'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="borrowId" value="<?= htmlspecialchars($borrow['BorrowID']) ?>">
                                            <button type="submit" name="edit" class="btn btn-sm btn-primary">Return</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>