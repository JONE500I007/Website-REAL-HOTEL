<?php
$hostName = "db2";
$dbUser = "justthisuser";
$dbPassword = "mysqlpass1122";
$dbName = "my_database";
$conn = mysqli_connect($hostName, $dbUser, $dbPassword, $dbName);
if (!$conn) {
    die("Something went wrong");
}
?>