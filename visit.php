<?php
require_once 'connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: userlogin.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $visitDate = $_POST['visitDate'] ?? '';
    $nextVisitDate = $_POST['nextVisitDate'] ?? '';
    $visitTime = $_POST['visitTime'] ?? '';
    
    if (!empty($visitDate) && !empty($nextVisitDate) && !empty($visitTime)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tbl_Visitation (UserID, VisitDate, NextVisitDate, VisitTime) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['UserID'], $visitDate, $nextVisitDate, $visitTime]);
            $success = "Visitation details recorded successfully!";
        } catch (PDOException $e) {
            $error = "Error recording visitation: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Visitation - BBMS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Nunito&display=swap" rel="stylesheet">

<style type="text/css">
  html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    width: 100%;
  }
  body {
    background-image: url('bg2.jpg'); 
    background-size: cover; 
    background-position: center; 
    background-repeat: no-repeat;  
    overflow: hidden;
    height: 100%;
  }

  .navbar {
    margin: 0;
    padding: 0;
  }

  #logo {
    text-align: center;
    margin-top: 20px;
  }

  #logo img {
    width: 30%;
    height: auto;
    max-width: 250px;
  }

  #login {
    background-color: rgba(245, 236, 224, 0.8);
    border-radius: 10px;
    width: 60%;
    max-width: 500px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: #443627;
    padding: 20px;
  }

  .form-control {
    background-color: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(0, 0, 0, 0.3);
    color: black;
    padding: 10px;
    margin-bottom: 15px;
  }

  .form-control::placeholder {
    color: rgba(0, 0, 0, 0.5); 
  }

  .form-control:focus {
    background-color: rgba(255, 255, 255, 0.3); 
    outline: none; 
    box-shadow: 0px 0px 5px rgba(0, 0, 0, 0.2);
  }

  .navbar {
    background-color: #443627;
  }

  .nav-link {
    color: white;
    transition: all 0.2s ease;
  }

  .navbar-nav .nav-link:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  }

  .submit-button {
    background-color: #443627;
    border-radius: 30px;
    color: #FFF5E4;
    width: 30%; 
    transition: all 0.3s ease;
    padding: 10px 20px;
  }

  .submit-button:hover {
    background-color: #A27B5C;
    color: white;
    transform: scale(1.05);
  }

  @media (max-width: 768px) {
    #logo img {
      width: 50%;
    }

    #login {
      width: 80%;
    }

    .form-control {
      width: 100%;
    }

    .submit-button {
      width: 50%;
    }
  }

  @media (max-width: 576px) {
    #logo img {
      width: 70%;
    }

    #login {
      width: 90%;
    }

    .submit-button {
      width: 60%;
    }
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

  <div class="col-lg-12" id="logo">
    <img src="logo2.png" class="img-fluid" alt="Logo">
  </div>

  <div class="col-lg-12" id="login"> 
    <h3 style="font-family: 'Nunito', sans-serif; padding-top: 20px;">Enter Visitation Details</h3>
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form class="px-4 py-3" method="POST">
      <div class="mb-3">
        <input type="date" name="visitDate" class="form-control" placeholder="Date of Visitation" required>
        <input type="date" name="nextVisitDate" class="form-control" placeholder="Date of Next Possible Destination" required>
        <input type="time" name="visitTime" class="form-control" placeholder="Time of Visitation" required>
      </div>
      <center>
        <button type="submit" class="btn submit-button">Proceed</button>
      </center>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 