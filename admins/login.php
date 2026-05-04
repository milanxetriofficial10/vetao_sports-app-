<?php
session_start();
require_once __DIR__ . '/../databases/db.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT id, password FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();
    $admin = $res->fetch_assoc();

    if ($admin && password_verify($password, $admin['password'])) {

        // 🔐 SESSION
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_user'] = $username;

        // 🔥 IMPORTANT: correct path
        header("Location: dashboard.php");
        exit;

    } else {
        $error = "Wrong login";
    }
}
?>


<h2>Login</h2>

<form method="POST">
    <input name="username" required>
    <input name="password" type="password" required>
    <button>Login</button>
</form>

<?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>