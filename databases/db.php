<?php
if (!function_exists('getDB')) {
    function getDB() {
        $conn = new mysqli("localhost", "root", "Milan@1234", "jersey_ghar");

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        return $conn;
    }
}
?>