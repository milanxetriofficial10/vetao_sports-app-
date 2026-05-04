<?php
session_start();
session_destroy();
header("Location: seller_login.php");
exit;

?>