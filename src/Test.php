<?php
// Hardcoded credentials (Insecure)
$database_username = "admin";
$database_password = "password123";

// SQL Injection vulnerability
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "SELECT * FROM users WHERE id = '$id'";
    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "User: " . $row['username'] . "<br>";
        }
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}

// Cross-Site Scripting (XSS) vulnerability
if (isset($_POST['username'])) {
    $username = $_POST['username'];
    echo "Hello, " . $username . "!"; // Unsanitized user input
}

// Insecure file upload
if (isset($_FILES['file'])) {
    $file_name = $_FILES['file']['name'];
    $file_tmp = $_FILES['file']['tmp_name'];
    move_uploaded_file($file_tmp, "uploads/" . $file_name); // No file type validation
}

// Use of deprecated functions
$input = "Hello, World!";
$hash = md5($input); // MD5 is deprecated and insecure

// Insecure random number generation
$random_number = rand(); // Not cryptographically secure

?>