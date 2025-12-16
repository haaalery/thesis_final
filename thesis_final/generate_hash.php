<?php
// Generate password hash
$password = "Password123"; // Change this to whatever you want
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: " . $password . "<br>";
echo "Hash: " . $hash;
?>  