<?php
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'my_website';

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}
?>
