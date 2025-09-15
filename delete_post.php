<?php
// delete_post.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'config.php';

// Disabilitiamo qualsiasi output buffering precedente
if (ob_get_length()) {
    ob_clean();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Devi effettuare il login.']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    $post_id = intval($_POST['post_id']);
    // Controlliamo i permessi
    $stmt = $conn->prepare("
        SELECT p.id 
        FROM group_posts p
        JOIN group_members gm ON p.group_id = gm.group_id
        WHERE p.id = ? AND (p.user_id = ? OR (gm.user_id = ? AND gm.is_leader = 1))
    ");
    $stmt->bind_param("iii", $post_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt_delete = $conn->prepare("DELETE FROM group_posts WHERE id = ?");
        $stmt_delete->bind_param("i", $post_id);
        if ($stmt_delete->execute()) {
            echo json_encode(['success' => true]);
        } 

} else {
    echo json_encode(['success' => false, 'message' => 'Richiesta non valida.']);
}
}
