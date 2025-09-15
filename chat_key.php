<?php
<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}
$my_id = $_SESSION['user_id'];
$chat_with = isset($_POST['with']) ? intval($_POST['with']) : (isset($_GET['with']) ? intval($_GET['with']) : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Salva la chiave pubblica
    $pubkey = $_POST['pubkey'] ?? '';
    if ($chat_with && $pubkey) {
        $stmt = $conn->prepare("REPLACE INTO user_chat_keys (user_id, chat_with, pubkey) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $my_id, $chat_with, $pubkey);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['status'=>'ok']);
    } else {
        echo json_encode(['status'=>'error']);
    }
} else {
    // Recupera la chiave pubblica dell'altro utente
    if ($chat_with) {
        $stmt = $conn->prepare("SELECT pubkey FROM user_chat_keys WHERE user_id = ? AND chat_with = ?");
        $stmt->bind_param("ii", $chat_with, $my_id);
        $stmt->execute();
        $stmt->bind_result($pubkey);
        $stmt->fetch();
        $stmt->close();
        echo json_encode(['pubkey'=>$pubkey]);
    } else {
        echo json_encode(['pubkey'=>null]);
    }
}