<?php
session_start(); // Starting Session
$error=''; // Variable To Store Error Message

if (isset($_POST['submit'])) {
if (empty($_POST['client_username']) || empty($_POST['client_password'])) {
$error = "Username or Password is invalid";
}
else
{
// Define $username and $password
$client_username=$_POST['client_username'];
$client_password=$_POST['client_password'];
// Establishing Connection with Server by passing server_name, user_id and password as a parameter
require 'connection.php';
$conn = Connect();

// SQL query to fetch information of registerd users and finds user match.
$query = "SELECT client_username, client_password FROM clients WHERE client_username=? AND client_password=? LIMIT 1";

// To protect MySQL injection for Security purpose
$stmt = $conn->prepare($query);
$stmt -> bind_param("ss", $client_username, $client_password);
$stmt -> execute();
$stmt -> bind_result($client_username, $client_password);
$stmt -> store_result();

if ($stmt->fetch())  //fetching the contents of the row
{
	$_SESSION['login_client']=$client_username; // Initializing Session
	header("location: index.php"); // Redirecting To Other Page
} else {
$error = "Username or Password is invalid";
}
mysqli_close($conn); // Closing Connection
}
}
?>