<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['posts'])) {
        // Qui puoi gestire i dati dei post, ad esempio, salvandoli nella sessione
        $_SESSION['posts'] = $data['posts'];

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Dati dei post non trovati']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Metodo non valido']);
}
?>
