<?php
require("connection.php");

if (isset($_POST['edit'])) {
    $userID = mysqli_real_escape_string($con, $_POST['userID']);
    $firstName = mysqli_real_escape_string($con, $_POST['firstName']);
    $lastName = mysqli_real_escape_string($con, $_POST['lastName']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $idNumber = mysqli_real_escape_string($con, $_POST['idNumber']);
    $registrationDate = mysqli_real_escape_string($con, $_POST['registrationDate']);

    $sql = "UPDATE tbl_User SET 
                FirstName = '$firstName',
                LastName = '$lastName',
                Email = '$email',
                IDNumber = '$idNumber',
                RegistrationDate = '$registrationDate'
            WHERE UserID = '$userID'";

    if (mysqli_query($con, $sql)) {
        header("Location: borrowform.php");
        exit();
    } else {
        echo "Error updating record: " . mysqli_error($con);
    }
} else {
    echo "Invalid access.";
}
?>
