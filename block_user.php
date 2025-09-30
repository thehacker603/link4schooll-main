<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$blocker_id = $_SESSION['user_id'];
$blocked_id = intval($_POST['blocked_id'] ?? 0);
$action     = $_POST['action'] ?? '';

if ($blocked_id <= 0) {
    die("ID utente non valido.");
}

if ($action === 'block') {
    $stmt = $conn->prepare("INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $blocker_id, $blocked_id);
    $stmt->execute();
    $stmt->close();
} elseif ($action === 'unblock') {
    $stmt = $conn->prepare("DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->bind_param("ii", $blocker_id, $blocked_id);
    $stmt->execute();
    $stmt->close();
}

// Torna alla chat
header("Location: chat.php?with=".$blocked_id);
exit;
