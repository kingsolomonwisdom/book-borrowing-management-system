<?php 
require("connection.php");

if (isset($_GET['delete'])) {
    $userID = $_GET['delete'];


    $sql = "DELETE FROM tbl_User WHERE UserID = ?";
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        echo "Error preparing query: " . mysqli_error($con) . "<br>";
        exit;
    }
    mysqli_stmt_bind_param($stmt, "i", $userID);
    $query = mysqli_stmt_execute($stmt);

    if ($query) {

        header("Location: borrowform.php");
        exit;
    } else {
        echo "Error deleting user: " . mysqli_error($con) . "<br>";
    }
}
?>
