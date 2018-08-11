<!DOCTYPE html>
<html>
<?php 
session_start(); 
require 'connection.php';
$conn = Connect();
?>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patna Car Rental</title>
    <link rel="shortcut icon" type="image/png" href="assets/img/P.png.png">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lato">
    <link rel="stylesheet" href="assets/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/fonts/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/user.css">
    <link rel="stylesheet" href="assets/w3css/w3.css">
    <link href="http://fonts.googleapis.com/css?family=Open+Sans:300,400,700,400italic,700italic" rel="stylesheet" type="text/css">
    <link href="http://fonts.googleapis.com/css?family=Montserrat:400,700" rel="stylesheet" type="text/css">
</head>

<body>
    <?php 
    $name = $conn->real_escape_string($_POST['name']);
    $e_mail = $conn->real_escape_string($_POST['e_mail']);
    $message = $conn->real_escape_string($_POST['message']);

    $sql = "INSERT INTO feedback values ('" . $name . "','" . $e_mail ."','" . $message ."')";
    $success = $conn->query($sql);


    if(!$success) {
        echo $conn->error;
    }
    else { ?>
        <div class="container">
        <div class="jumbotron" style="text-align: center;">
            Thank you for your feedback!    
            <br><br>
            <a href="index.php" class="btn btn-default"> Go Back </a>
    </div>
     <?php
    }
?>
    </body>
    <footer class="site-footer">
        <div class="container">
            <hr>
            <div class="row">
                <div class="col-sm-6">
                    <h5>© 2018 Patna Car Rental</h5>
                </div>
            </div>
        </div>
    </footer>
</html>