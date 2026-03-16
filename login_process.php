<?php
session_start();
require_once "database/database.php";

if($_SERVER["REQUEST_METHOD"] == "POST"){

    $username = $_POST['username'];
    $password = hash('sha256', $_POST['password']); // SHA256 hash

    $stmt = $conn->prepare("SELECT * FROM tab1 WHERE username = :username AND password = :password");
    $stmt->execute([
        ':username' => $username,
        ':password' => $password
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user){

        $_SESSION['eid'] = $user['eid'];
        $_SESSION['username'] = $user['username'];

        header("Location: employee_dashboard.php");
        exit;

    }else{
        echo "Invalid username or password";
    }
}
?>
                    // continue but do not block — adjust if you want strict auth
