<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
    exit();
}

if (!isset($_GET['group_id']) || !is_numeric($_GET['group_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID gruppo non valido']);
    exit();
}

$group_id = (int)$_GET['group_id'];

include 'config.php';

// Controlla che l'utente sia membro del gruppo
$stmt = $conn->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $group_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Accesso negato al gruppo']);
    exit();
}

// Recupera i post del gruppo
$stmt = $conn->prepare("
    SELECT p.id, p.contenuto, p.created_at, u.username
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.group_id = ?
    ORDER BY p.created_at DESC
");
$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
}

echo json_encode(['success' => true, 'posts' => $posts]);
