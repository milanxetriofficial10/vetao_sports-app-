<?php
// Start the session
session_start();

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the form data
    $username = $_POST["username"];
    $email = $_POST["email"];
    $password = $_POST["password"];

    // Validate the form data
    if (empty($username) || empty($email) || empty($password)) {
        $_SESSION["error"] = "All fields are required.";
        header("Location: signup.php");
        exit();
    }

    // Here you would typically save the user to a database
    // For this example, we'll just display a success message

    $_SESSION["success"] = "You have successfully signed up.";
    header("Location: signup.php");
    exit();
}
?>
<script>
// Toggle dropdown menu
function toggleMenu(){
  const drop = document.getElementById("drop");
  drop.classList.toggle("show");
}
// Close the dropdown if the user clicks outside of it
window.onclick = function(event) {
  if (!event.target.matches('.icon')) { 
    const dropdowns = document.getElementsByClassName("dropdown");
    for (let i = 0; i < dropdowns.length; i++) {
      const openDropdown = dropdowns[i];
      if (openDropdown.classList.contains('show')) {
        openDropdown.classList.remove('show');
      }
    }
  }
}

</script>