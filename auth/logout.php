<?php
// Include auth functions
require_once '../includes/auth.php';

// Log the user out
logout();

// Redirect to the login page
header('Location: ../index.php');
exit;
?>
