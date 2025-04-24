<?php
// Define error variables
$emailErr = $passwordErr = "";
$email = $password = "";

// Validate input when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

  if (empty($_POST["email"])) {
    $emailErr = "Email is required";
  } else {
    $email = test_input($_POST["email"]);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $emailErr = "Invalid email format";
    }
  }

  if (empty($_POST["password"])) {
    $passwordErr = "Password is required";
  } else {
    $password = test_input($_POST["password"]);
    if (!preg_match('/\d/', $password)) {
      $passwordErr = "Password must contain at least one number";
    }
  }

  // Redirect if no errors
  if (empty($emailErr) && empty($passwordErr)) {
    header("Location: bookdetails.html");
    exit();
  }
}

// Sanitize input
function test_input($data) {
  $data = trim($data);
  $data = stripslashes($data);
  $data = htmlspecialchars($data);
  return $data;
}
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-image: url('bg2.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      min-height: 100vh;
    }
    #logo {
      text-align: center;
      padding-top: 2rem;
    }
    #logo img {
      width: 30%;
      height: auto;
      max-width: 200px;
    }
    #login {
      background-color: rgba(245, 236, 224, 0.9);
      border-radius: 10px;
      padding: 2rem;
      margin: 3rem auto;
      width: 90%;
      max-width: 400px;
    }
    .form-control {
      background-color: rgba(255, 255, 255, 0.2);
      border: 1px solid rgba(0, 0, 0, 0.3);
      color: black;
    }
    .form-control::placeholder {
      color: rgba(0, 0, 0, 0.5);
    }
    .form-control:focus {
      background-color: rgba(255, 255, 255, 0.3);
      box-shadow: none;
    }
    #btn {
      background-color: #443627;
      border-radius: 30px;
      color: #FFF5E4;
      width: 100%;
      padding: 10px;
      border: none;
      transition: all 0.3s ease;
    }
    #btn:hover {
      background-color: #A27B5C;
      color: white;
      transform: scale(1.03);
    }
    .error {
      color: red;
      font-size: 0.9em;
      display: block;
      margin-top: 5px;
    }
  </style>
</head>
<body>

  <div id="logo">
    <img src="logo2.png" alt="Logo">
  </div>

  <div id="login">
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input 
          type="text" 
          class="form-control" 
          id="email" 
          name="email" 
          placeholder="name@gmail.com" 
          value="<?php echo htmlspecialchars($email); ?>">
        <?php if (!empty($emailErr)): ?>
          <span class="error">* <?php echo $emailErr; ?></span>
        <?php endif; ?>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input 
          type="password" 
          class="form-control" 
          id="password" 
          name="password" 
          placeholder="Password">
        <?php if (!empty($passwordErr)): ?>
          <span class="error">* <?php echo $passwordErr; ?></span>
        <?php endif; ?>
      </div>

      <div class="form-check mb-3">
        <input type="checkbox" class="form-check-input" id="remember">
        <label class="form-check-label" for="remember">Remember me</label>
      </div>

      <button type="submit" id="btn">Sign in</button>
    </form>
  </div>

</body>
</html>
