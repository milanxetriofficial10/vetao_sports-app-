<?php
session_start();

// 1. Database Connection
$host = "localhost";
$user = "root";
$pass = "Milan@1234";
$dbname = "jersey_ghar"; // Change this to your actual DB name

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (isset($_POST['register_seller'])) {
    
    // 2. Collect Form Data
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $address   = mysqli_real_escape_string($conn, $_POST['address']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $password  = $_POST['password'];
    $conf_pass = $_POST['conf_password'];
    $pan       = mysqli_real_escape_string($conn, $_POST['pan_number']);
    $shop      = mysqli_real_escape_string($conn, $_POST['shop_name']);

    // 3. Validation
    if ($password !== $conf_pass) {
        echo "<script>alert('Passwords do not match!'); window.location='register.php';</script>";
        exit();
    }

    // Encrypt password for security
    $hashed_pass = password_hash($password, PASSWORD_DEFAULT);

    // 4. Handle Citizenship Image Uploads
    $target_dir = "../uploads/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true); // Create folder if it doesn't exist
    }

    // Rename files to avoid duplicates
    $front_filename = time() . "_front_" . basename($_FILES["citizen_front"]["name"]);
    $back_filename  = time() . "_back_" . basename($_FILES["citizen_back"]["name"]);
    
    $front_path = $target_dir . $front_filename;
    $back_path  = $target_dir . $back_filename;

    if (move_uploaded_file($_FILES["citizen_front"]["tmp_name"], $front_path) && 
        move_uploaded_file($_FILES["citizen_back"]["tmp_name"], $back_path)) {
        
        // 5. Insert into Database
        $sql = "INSERT INTO sellers (full_name, address, email, password, pan_number, citizenship_front, citizenship_back, shop_name) 
                VALUES ('$full_name', '$address', '$email', '$hashed_pass', '$pan', '$front_path', '$back_path', '$shop')";

        if ($conn->query($sql) === TRUE) {
            // 6. Success! Set session and go to Dashboard
            $_SESSION['seller_id'] = $conn->insert_id;
            $_SESSION['seller_name'] = $full_name;
            
            header("Location: seller_dashboard.php");
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }

    } else {
        echo "Failed to upload images. Check folder permissions.";
    }
}

$conn->close();
?>