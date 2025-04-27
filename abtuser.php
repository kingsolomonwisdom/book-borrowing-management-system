<?php
require_once 'connection.php';

// Function to safely escape output
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Function to format dates
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Fetch latest 5 borrowings
$borrowQuery = $pdo->query("
    SELECT b.BorrowID, u.Name, bk.Title, b.BorrowDate, b.DueDate, b.ReturnDate,
           CASE WHEN b.ReturnDate IS NULL AND CURRENT_DATE > b.DueDate THEN 1 ELSE 0 END as is_overdue
    FROM tbl_Borrowing b
    JOIN tbl_User u ON b.UserID = u.UserID
    JOIN tbl_Books bk ON b.BookID = bk.BookID
    WHERE b.Status = 'Borrowed'
    ORDER BY b.BorrowDate DESC
    LIMIT 5
");
$borrowings = $borrowQuery->fetchAll();

// Fetch latest 5 returns
$returnsQuery = $pdo->query("
    SELECT b.BorrowID, u.Name, bk.Title, b.BorrowDate, b.ReturnDate,
           DATEDIFF(b.ReturnDate, b.DueDate) as days_overdue
    FROM tbl_Borrowing b
    JOIN tbl_User u ON b.UserID = u.UserID
    JOIN tbl_Books bk ON b.BookID = bk.BookID
    WHERE b.Status = 'Returned'
    ORDER BY b.ReturnDate DESC
    LIMIT 5
");
$returns = $returnsQuery->fetchAll();

// Fetch latest 5 fines
$finesQuery = $pdo->query("
    SELECT f.FineID, u.Name, f.Amount, f.Status, f.FineDate,
           b.BorrowID, bk.Title
    FROM tbl_Fines f
    JOIN tbl_Borrowing b ON f.BorrowID = b.BorrowID
    JOIN tbl_User u ON b.UserID = u.UserID
    JOIN tbl_Books bk ON b.BookID = bk.BookID
    ORDER BY f.FineDate DESC
    LIMIT 5
");
$fines = $finesQuery->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - BBMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #443627;
            --primary-light: #5a483a;
            --accent-color: #8B4513;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fa;
            background-image: url('bg2.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
        }
        
        .navbar {
            background-color: var(--primary-color);
        }
        
        .navbar-brand img {
            height: 40px;
        }
        
        .nav-link {
            color: #fff !important;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            background-color: rgba(255, 248, 225, 0.95);
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border-bottom: 2px solid #C9B194;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: var(--primary-light);
            color: white;
        }
        
        .table td {
            color: #443627;
        }
        
        .overdue {
            color: #dc3545;
            font-weight: bold;
        }
        
        .status-Paid {
            color: #198754;
        }
        
        .status-Pending {
            color: #dc3545;
        }
        
        .delay-info {
            font-size: 0.9em;
            color: #856404;
            background-color: #fff3cd;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
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

    <div id="logo" class="text-center mb-4">
        <img src="logo2.png" alt="Book Borrowing Management System" style="max-width: 300px;">
    </div>

    <div class="container">
        <div class="row">
            <!-- Borrowings Panel -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Borrowings</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Book</th>
                                        <th>Due Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrowings as $borrow): ?>
                                    <tr>
                                        <td><?= h($borrow['Name']) ?></td>
                                        <td><?= h($borrow['Title']) ?></td>
                                        <td class="<?= $borrow['is_overdue'] ? 'overdue' : '' ?>">
                                            <?= formatDate($borrow['DueDate']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Returns Panel -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Returns</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Book</th>
                                        <th>Return Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($returns as $return): ?>
                                    <tr>
                                        <td><?= h($return['Name']) ?></td>
                                        <td><?= h($return['Title']) ?></td>
                                        <td><?= formatDate($return['ReturnDate']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fines Panel -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Fines</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fines as $fine): ?>
                                    <tr>
                                        <td><?= h($fine['Name']) ?></td>
                                        <td>₱<?= number_format($fine['Amount'], 2) ?></td>
                                        <td class="status-<?= h($fine['Status']) ?>">
                                            <?= h($fine['Status']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 