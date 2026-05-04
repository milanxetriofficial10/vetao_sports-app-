<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if ($password !== $confirm) {
        $error = "Password mismatch";
    } else {

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hash);

        if ($stmt->execute()) {

            // 🔥 AUTO LOGIN AFTER REGISTER
            $_SESSION['admin_id'] = $stmt->insert_id;
            $_SESSION['admin_user'] = $username;

            header("Location: dashboard.php");
            exit;

        } else {
            $error = "Register failed";
        }
    }
}
?>



<form method="POST">
    <h2>Register</h2>
    <input name="username" placeholder="Username" required>
    <input name="password" type="password" placeholder="Password" required>
    <input name="confirm_password" type="password" placeholder="Confirm Password" required>
    <button>Register</button>
</form>

<?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>