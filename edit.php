<?php
require_once 'connection.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['UserID'])) {
    header('Location: userlogin.php');
    exit();
}

if (isset($_POST['edit'])) {
    try {
        $userID = $_POST['userID'];
        $name = $_POST['name'];
        $lastName = $_POST['lastName'];
        $email = $_POST['email'];
        $idNumber = $_POST['idNumber'];
        $registrationDate = $_POST['registrationDate'];

        $stmt = $pdo->prepare("UPDATE tbl_User SET 
            Name = ?,
            LastName = ?,
            Email = ?,
            IDNumber = ?,
            RegistrationDate = ?
            WHERE UserID = ?");

        $stmt->execute([
            $name,
            $lastName,
            $email,
            $idNumber,
            $registrationDate,
            $userID
        ]);

        header("Location: borrowform.php");
        exit();
    } catch (PDOException $e) {
        error_log("Error updating user: " . $e->getMessage());
        echo "Error updating record. Please try again.";
    }
} else {
    echo "Invalid access.";
}
?>
