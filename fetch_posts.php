<?php
session_start();
include 'config.php';

if (!isset($_GET['group_id']) || !is_numeric($_GET['group_id'])) {
    echo json_encode([]);
    exit();
}

$group_id = intval($_GET['group_id']);

// Recupera i post del gruppo
$stmt = $conn->prepare("
    SELECT gp.id, gp.content, gp.created_at, u.username
    FROM group_posts gp
    JOIN users u ON gp.user_id = u.id
    WHERE gp.group_id = ?
    ORDER BY gp.created_at DESC
");

if (!$stmt) {
    die("Errore nella query SQL (recupero post): " . $conn->error);
}

$stmt->bind_param("i", $group_id);
$stmt->execute();
$result = $stmt->get_result();

$posts = [];
while ($row = $result->fetch_assoc()) {
    // Recupera le risposte per ogni post
    $stmt_replies = $conn->prepare("
        SELECT r.content, r.created_at, u.username
        FROM replies r
        JOIN users u ON r.user_id = u.id
        WHERE r.post_id = ?
        ORDER BY r.created_at ASC
    ");

    if (!$stmt_replies) {
        die("Errore nella query SQL (recupero risposte): " . $conn->error);
    }

    $stmt_replies->bind_param("i", $row['id']);
    $stmt_replies->execute();
    $replies_result = $stmt_replies->get_result();

    $replies = [];
    while ($reply = $replies_result->fetch_assoc()) {
        $replies[] = $reply;
    }

    $row['replies'] = $replies;
    $posts[] = $row;
}

echo json_encode($posts);
?>