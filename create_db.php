<?php

require 'connection.php';

$sql = "CREATE DATABASE rustdb";

$query = mysqli_query($con, $sql);

// if ($query) {
// 	echo"SUccess";
// }else{
// 	echo"failed";
// }

?>