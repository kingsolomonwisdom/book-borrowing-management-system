<?php
require("connection.php");

$targetDir = "uploads/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$errorMessage = ""; // Variable to store general error message
$errors = []; // Array to store individual field errors

// ADD BORROW
if (isset($_POST['submit'])) {
    $borrowerName = mysqli_real_escape_string($con, $_POST['borrowerName']);
    $bookTitle = mysqli_real_escape_string($con, $_POST['bookTitle']);
    $bookID = mysqli_real_escape_string($con, $_POST['bookID']);
    $borrowDate = mysqli_real_escape_string($con, $_POST['borrowDate']);
    $returnDate = mysqli_real_escape_string($con, $_POST['returnDate']);
    $userID = mysqli_real_escape_string($con, $_POST['userID']);

    // Validate inputs: Check if any field is empty
    if (empty($borrowerName)) {
        $errors['borrowerName'] = "Borrower Name is required.";
    }
    if (empty($bookTitle)) {
        $errors['bookTitle'] = "Book Title is required.";
    }
    if (empty($bookID)) {
        $errors['bookID'] = "Book ID is required.";
    }
    if (empty($borrowDate)) {
        $errors['borrowDate'] = "Borrow Date is required.";
    }
    if (empty($returnDate)) {
        $errors['returnDate'] = "Return Date is required.";
    }
    if (empty($userID)) {
        $errors['userID'] = "User  ID is required.";
    }

    // If there are no errors, proceed with saving the data
    if (empty($errors)) {
        $imagePath = "";
        if (isset($_FILES['bookImage']) && $_FILES['bookImage']['error'] == 0) {
            $imageName = time() . "_" . basename($_FILES['bookImage']['name']);
            $imagePath = $targetDir . $imageName;
            move_uploaded_file($_FILES['bookImage']['tmp_name'], $imagePath);
        }

        $sql = "INSERT INTO tbl_Borrowing (BorrowerName, BookTitle, BookID, BorrowDate, ReturnDate, UserID, BookImage)
                VALUES ('$borrowerName', '$bookTitle', '$bookID', '$borrowDate', '$returnDate', '$userID', '$imagePath')";

        if (mysqli_query($con, $sql)) {
            header("Location: borrowform.php");
            exit();
        } else {
            echo "Error: " . mysqli_error($con);
        }
    } else {
        $errorMessage = "Please fix the errors above and try again.";
    }
}

// EDIT BORROW
if (isset($_POST['edit'])) {
    $borrowID = mysqli_real_escape_string($con, $_POST['borrowID']);
    $borrowerName = mysqli_real_escape_string($con, $_POST['borrowerName']);
    $bookTitle = mysqli_real_escape_string($con, $_POST['bookTitle']);
    $bookID = mysqli_real_escape_string($con, $_POST['bookID']);
    $borrowDate = mysqli_real_escape_string($con, $_POST['borrowDate']);
    $returnDate = mysqli_real_escape_string($con, $_POST['returnDate']);
    $userID = mysqli_real_escape_string($con, $_POST['userID']);

    // Validate inputs for edit
    if (empty($borrowerName)) { 
        $errors['borrowerName'] = "Borrower Name is required."; 
    }
    if (empty($bookTitle)) {
        $errors['bookTitle'] = "Book Title is required.";
    }
    if (empty($bookID)) {
        $errors['bookID'] = "Book ID is required.";
    }
    if (empty($borrowDate)) {
        $errors['borrowDate'] = "Borrow Date is required.";
    }
    if (empty($returnDate)) {
        $errors['returnDate'] = "Return Date is required.";
    }
    if (empty($userID)) {
        $errors['userID'] = "User  ID is required.";
    }

    // Update functionality if there are no errors
    if (empty($errors)) {
        $imagePath = "";
        if (isset($_FILES['bookImage']) && $_FILES['bookImage']['error'] == 0) {
            $imageName = time() . "_" . basename($_FILES['bookImage']['name']);
            $imagePath = $targetDir . $imageName;
                        move_uploaded_file($_FILES['bookImage']['tmp_name'], $imagePath);
        }

        $sql = "UPDATE tbl_Borrowing SET 
                    BorrowerName = '$borrowerName',
                    BookTitle = '$bookTitle',
                    BookID = '$bookID',
                    BorrowDate = '$borrowDate',
                    ReturnDate = '$returnDate',
                    UserID = '$userID'";

        if (!empty($imagePath)) {
            $sql .= ", BookImage = '$imagePath'";
        }

        $sql .= " WHERE BorrowID = '$borrowID'";

        if (mysqli_query($con, $sql)) {
            header("Location: borrowform.php");
            exit();
        } else {
            echo "Error: " . mysqli_error($con);
        }
    } else {
        $errorMessage = "Please fix the errors above and try again.";
    }
}

// DELETE BORROW
if (isset($_GET['delete'])) {
    $borrowID = mysqli_real_escape_string($con, $_GET['delete']);
    mysqli_query($con, "DELETE FROM tbl_Borrowing WHERE BorrowID = '$borrowID'");

    $check = mysqli_query($con, "SELECT COUNT(*) as total FROM tbl_Borrowing");
    if (mysqli_fetch_assoc($check)['total'] == 0) {
        mysqli_query($con, "ALTER TABLE tbl_Borrowing AUTO_INCREMENT = 1");
    }

    header("Location: borrowform.php");
    exit();
}

// Fetch all records for display
$borrowings = [];
$query = mysqli_query($con, "SELECT * FROM tbl_Borrowing");
while($row = mysqli_fetch_assoc($query)) {
    $borrowings[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Borrow Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito&display=swap" rel="stylesheet">
  <style>
    body {
      background-color: ghostwhite;
      font-family: 'Nunito', sans-serif;
    }
    .btn-custom {
      background-color: #443627;
      color: #fff;
      border-radius: 20px;
    }
    .btn-custom:hover {
      background-color: #A27B5C;
      color: white;
      transform: scale(1.05);
    }
    .navbar {
      background-color: #443627;
    }
    .nav-link {
      color: white;
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
        <li class="nav-item"><a class="nav-link" href="bookdetails.html">HOME</a></li>
        <li class="nav-item"><a class="nav-link" href="borrowform.php">DASHBOARD</a></li>
        <li class="nav-item"><a class="nav-link" href="visit.html">VISITATIONS</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">RECORDS</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="abtuser.html">Transactions</a></li>
            <li><a class="dropdown-item" href="fines.html">Fines</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="userlogin.html">LOG OUT</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container mt-5">
  <h3 class="mb-4">Dashboard</h3>
  <div class="row g-4">
    <div class="col-md-3">
      <div class="card text-white text-center" style="background-color: #443627;">
        <div class="card-body">
          <h5 class="card-title">Total Borrowings</h5>
          <p class="card-text fs-4">
            <?php
              $res = mysqli_query($con, "SELECT COUNT(*) as total FROM tbl_Borrowing");
                            echo mysqli_fetch_assoc($res)['total'];
            ?>
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white text-center" style="background-color: #6D4C41;">
        <div class="card-body">
          <h5 class="card-title">Currently Borrowed</h5>
          <p class="card-text fs-4">58</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white text-center" style="background-color: #A27B5C;">
        <div class="card-body">
          <h5 class="card-title">Overdue Books</h5>
          <p class="card-text fs-4">7</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card text-white text-center" style="background-color: #C9B194;">
        <div class="card-body">
          <h5 class="card-title">Active Borrowers</h5>
          <p class="card-text fs-4">41</p>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="container py-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3>Manage Book Borrowing</h3>
    <button class="btn btn-custom" data-bs-toggle="modal" data-bs-target="#borrowModal">Add Borrow Record</button>
  </div>

  <div class="card p-3">
    <h5>Borrowed Books</h5>
    <div class="table-responsive">
      <table class="table table-bordered table-striped mt-3">
        <thead class="table-dark text-center">
          <tr>
            <th>ID</th>
            <th>Borrower</th>
            <th>Book Title</th>
            <th>Book ID</th>
            <th>Borrow Date</th>
            <th>Return Date</th>
            <th>User ID</th>
            <th>Book Image</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($borrowings as $row): ?>
          <tr class="text-center">
            <td><?= $row['BorrowID']; ?></td>
            <td><?= $row['BorrowerName']; ?></td>
            <td><?= $row['BookTitle']; ?></td>
            <td><?= $row['BookID']; ?></td>
            <td><?= $row['BorrowDate']; ?></td>
            <td><?= $row['ReturnDate']; ?></td>
            <td><?= $row['UserID']; ?></td>
            <td><img src="<?= $row['BookImage']; ?>" style="width:50px;height:50px;"></td>
            <td>
              <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['BorrowID']; ?>">Edit</button>
              <a href="?delete=<?= $row['BorrowID']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
            </td>
          </tr>

          <!-- Edit Modal -->
          <div class="modal fade" id="editModal<?= $row['BorrowID']; ?>" tabindex="-1">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Edit Borrow Record</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="borrowID" value="<?= $row['BorrowID']; ?>">
                    <div class="mb-3">
                      <label for="borrowerName" class="form-label">Borrower Name</label>
                      <input type="text" name="borrowerName" class="form-control" value="<?= $row['BorrowerName']; ?>">
                    </div>
                    <div class="mb-3">
                      <label for="bookTitle" class="form-label">Book Title</label>
                      <input type="text" name="bookTitle" class="form-control" value="<?= $row['BookTitle']; ?>">
                    </div>
                                        <div class="mb-3">
                      <label for="bookID" class="form-label">Book ID</label>
                      <input type="text" name="bookID" class="form-control" value="<?= $row['BookID']; ?>">
                    </div>
                    <div class="mb-3">
                      <label for="borrowDate" class="form-label">Borrow Date</label>
                      <input type="date" name="borrowDate" class="form-control" value="<?= $row['BorrowDate']; ?>">
                    </div>
                    <div class="mb-3">
                      <label for="returnDate" class="form-label">Return Date</label>
                      <input type="date" name="returnDate" class="form-control" value="<?= $row['ReturnDate']; ?>">
                    </div>
                    <div class="mb-3">
                      <label for="userID" class="form-label">User  ID</label>
                      <input type="text" name="userID" class="form-control" value="<?= $row['User ID']; ?>">
                    </div>
                    <div class="mb-3">
                      <label for="bookImage" class="form-label">Book Image</label>
                      <input type="file" name="bookImage" class="form-control">
                      <small class="text-muted">Leave blank to keep the current image.</small>
                    </div>
                    <button type="submit" name="edit" class="btn btn-custom w-100">Update Borrow Record</button>
                  </form>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="borrowModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Borrow Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="" enctype="multipart/form-data">
          <?php if ($errorMessage): ?>
          <div class="alert alert-danger"><?= $errorMessage; ?></div>
          <?php endif; ?>
          
          <div class="mb-3">
            <label for="borrowerName" class="form-label">Borrower Name</label>
            <input type="text" name="borrowerName" class="form-control" value="<?= $borrowerName ?? ''; ?>">
            <?php if (isset($errors['borrowerName'])): ?>
            <small class="text-danger"><?= $errors['borrowerName']; ?></small>
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <label for="bookTitle" class="form-label">Book Title</label>
            <input type="text" name="bookTitle" class="form-control" value="<?= $bookTitle ?? ''; ?>">
            <?php if (isset($errors['bookTitle'])): ?>
            <small class="text-danger"><?= $errors['bookTitle']; ?></small>
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <label for="bookID" class="form-label">Book ID</label>
            <input type="text" name="bookID" class="form-control" value="<?= $bookID ?? ''; ?>">
            <?php if (isset($errors['bookID'])): ?>
            <small class="text-danger"><?= $errors['bookID']; ?></small>
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <label for="borrowDate" class="form-label">Borrow Date</label>
            <input type="date" name="borrowDate" class="form-control" value="<?= $borrowDate ?? ''; ?>">
            <?php if (isset($errors['borrowDate'])): ?>
            <small class="text-danger"><?= $errors['borrowDate']; ?></small>
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <label for="returnDate" class="form-label">Return Date</label>
            <input type="date" name="returnDate" class="form-control" value="<?= $returnDate ?? ''; ?>">
            <?php if (isset($errors['returnDate'])): ?>
            <small class="text-danger"><?= $errors['returnDate']; ?></small>
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <label for="userID" class="form-label">User  ID</label>
                       <input type="text" name="userID" class="form-control" value="<?= $userID ?? ''; ?>">
            <?php if (isset($errors['userID'])): ?>
            <small class="text-danger"><?= $errors['userID']; ?></small>
            <?php endif; ?>
          </div>
          <div class="mb-3">
            <label for="bookImage" class="form-label">Book Image</label>
            <input type="file" name="bookImage" class="form-control">
          </div>
          <button type="submit" name="submit" class="btn btn-custom w-100">Add Borrow Record</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>