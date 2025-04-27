<?php
require_once 'connection.php';
session_start();

// Function to safely escape output
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Function to format dates
function formatDate($date) {
    return date('Y-m-d', strtotime($date));
}

$success_message = '';
$error_message = '';

// Fetch available borrow IDs (those that don't have fines yet and are overdue)
try {
    $borrowQuery = $pdo->query("
        SELECT 
            b.BorrowID,
            b.BorrowerName,
            bk.Title AS BookTitle,
            b.DueDate,
            DATEDIFF(CURRENT_DATE, b.DueDate) as days_overdue
        FROM tbl_Borrowing b
        LEFT JOIN tbl_Fines f ON b.BorrowID = f.BorrowID
        JOIN tbl_Books bk ON b.BookID = bk.BookID
        WHERE f.FineID IS NULL 
        AND b.Status = 'Borrowed'
        AND b.DueDate < CURRENT_DATE
        ORDER BY b.DueDate ASC
    ");
    $availableBorrows = $borrowQuery->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Error fetching borrow records.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        $borrowId = isset($_POST['borrowId']) ? trim($_POST['borrowId']) : '';
        $amount = isset($_POST['amount']) ? trim($_POST['amount']) : '';
        $fineDate = isset($_POST['fineDate']) ? trim($_POST['fineDate']) : '';
        
        if (empty($borrowId) || empty($amount) || empty($fineDate)) {
            throw new Exception('All fields are required.');
        }
        
        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception('Please enter a valid amount.');
        }
        
        // Verify if the borrow record exists
        $stmt = $pdo->prepare("SELECT UserID FROM tbl_Borrowing WHERE BorrowID = ?");
        $stmt->execute([$borrowId]);
        $borrow = $stmt->fetch();
        
        if (!$borrow) {
            throw new Exception('Invalid Borrow ID.');
        }
        
        // Insert the fine record
        $stmt = $pdo->prepare("
            INSERT INTO tbl_Fines (BorrowID, UserID, Amount, FineDate, Status)
            VALUES (?, ?, ?, ?, 'Pending')
        ");
        
        $stmt->execute([$borrowId, $borrow['UserID'], $amount, formatDate($fineDate)]);
        $success_message = 'Fine has been recorded successfully.';
        
        // Update the borrowing status to reflect the fine
        $stmt = $pdo->prepare("
            UPDATE tbl_Borrowing 
            SET Status = 'Overdue'
            WHERE BorrowID = ?
        ");
        $stmt->execute([$borrowId]);
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch all fines for display
$fines = [];
try {
    $query = $pdo->query("
        SELECT 
            f.FineID,
            f.Amount,
            f.FineDate,
            f.Status,
            u.Name AS UserName,
            b.BorrowID,
            bk.Title AS BookTitle,
            DATEDIFF(f.FineDate, b.DueDate) as days_overdue
        FROM tbl_Fines f
        JOIN tbl_Borrowing b ON f.BorrowID = b.BorrowID
        JOIN tbl_User u ON f.UserID = u.UserID
        JOIN tbl_Books bk ON b.BookID = bk.BookID
        ORDER BY f.FineDate DESC
    ");
    $fines = $query->fetchAll();
} catch (PDOException $e) {
    $error_message = 'Error fetching fines data.';
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fines Management - BBMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito&display=swap" rel="stylesheet">
    <style type="text/css">
        body {
            font-family: 'Nunito', sans-serif;
            background-image: url('bg2.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
        }

        .navbar {
            background-color: #443627;
        }

        .nav-link {
            color: white !important;
        }

        .content-wrapper {
            background-color: rgba(245, 236, 224, 0.9);
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .form-control {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #443627;
            margin-bottom: 15px;
        }

        .submit-button {
            background-color: #443627;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }

        .submit-button:hover {
            background-color: #A27B5C;
            transform: scale(1.05);
        }

        .table {
            background-color: rgba(255, 255, 255, 0.9);
        }

        .status-Pending {
            color: #dc3545;
            font-weight: bold;
        }

        .status-Paid {
            color: #198754;
            font-weight: bold;
        }

        .alert {
            margin-top: 20px;
        }

        #logo img {
            max-width: 300px;
            margin: 20px 0;
        }

        .overdue-info {
            font-size: 0.9em;
            color: #dc3545;
            margin-top: 5px;
        }
        
        select.form-control {
            padding: 10px;
            height: auto;
        }
        
        .calculated-amount {
            font-size: 1.2em;
            font-weight: bold;
            margin-top: 10px;
            color: #443627;
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

    <div class="container">
        <div id="logo" class="text-center">
            <img src="logo2.png" alt="Logo">
        </div>

        <div class="content-wrapper">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?= h($success_message) ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?= h($error_message) ?></div>
            <?php endif; ?>

            <div class="row">
                <!-- Fine Entry Form -->
                <div class="col-md-4">
                    <h3 class="mb-4">Record New Fine</h3>
                    <form method="POST" action="" id="fineForm">
                        <div class="mb-3">
                            <select name="borrowId" class="form-control" required onchange="calculateFine(this)">
                                <option value="">Select Overdue Borrowing</option>
                                <?php foreach ($availableBorrows as $borrow): ?>
                                    <option value="<?= h($borrow['BorrowID']) ?>" 
                                            data-days="<?= h($borrow['days_overdue']) ?>">
                                        ID: <?= h($borrow['BorrowID']) ?> - 
                                        <?= h($borrow['BorrowerName']) ?> - 
                                        <?= h($borrow['BookTitle']) ?>
                                        (<?= h($borrow['days_overdue']) ?> days overdue)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <input type="number" name="amount" id="fineAmount" class="form-control" placeholder="Amount" step="0.01" required readonly>
                            <div class="calculated-amount" id="calculatedAmount"></div>
                        </div>
                        <div class="mb-3">
                            <input type="date" name="fineDate" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <button type="submit" class="btn submit-button">Record Fine</button>
                    </form>
                </div>

                <!-- Fines Table -->
                <div class="col-md-8">
                    <h3 class="mb-4">Fines Records</h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Fine ID</th>
                                    <th>User</th>
                                    <th>Book</th>
                                    <th>Amount</th>
                                    <th>Days Overdue</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fines as $fine): ?>
                                <tr>
                                    <td><?= h($fine['FineID']) ?></td>
                                    <td><?= h($fine['UserName']) ?></td>
                                    <td><?= h($fine['BookTitle']) ?></td>
                                    <td>₱<?= number_format($fine['Amount'], 2) ?></td>
                                    <td><?= h($fine['days_overdue']) ?> days</td>
                                    <td><?= formatDate($fine['FineDate']) ?></td>
                                    <td class="status-<?= h($fine['Status']) ?>"><?= h($fine['Status']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function calculateFine(select) {
        const selectedOption = select.options[select.selectedIndex];
        const daysOverdue = parseInt(selectedOption.dataset.days);
        const finePerDay = 50; // ₱50 per day
        const totalFine = daysOverdue * finePerDay;
        
        document.getElementById('fineAmount').value = totalFine;
        document.getElementById('calculatedAmount').innerHTML = 
            `Fine Calculation: ${daysOverdue} days × ₱${finePerDay} = ₱${totalFine.toFixed(2)}`;
    }
    </script>
</body>
</html> 