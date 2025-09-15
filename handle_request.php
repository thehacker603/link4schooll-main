<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['request_id'], $_POST['action'])) {
    header("Location: home.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$request_id = (int)$_POST['request_id'];
$action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';

$stmt = $conn->prepare("UPDATE chat_requests SET status = ? WHERE id = ? AND receiver_id = ?");
$stmt->bind_param("sii", $action, $request_id, $user_id);
$stmt->execute();

header("Location: chat.php");
exit;
