<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Devi effettuare il login per gestire le richieste.']);
    exit();
}

include 'config.php';

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connessione al database fallita: ' . $conn->connect_error]);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_id']) && isset($_POST['action'])) {
        $request_id = intval($_POST['request_id']);
        $action = $_POST['action'];

        // Recupera i dettagli della richiesta
        $stmt = $conn->prepare("SELECT group_id, user_id FROM group_requests WHERE id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query: ' . $conn->error]);
            exit();
        }

        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();

        if ($request) {
            $group_id = $request['group_id'];
            $user_id_to_add = $request['user_id'];

            if ($action === 'accept') {
                // Aggiungi l'utente al gruppo
                $stmt_add = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
                if (!$stmt_add) {
                    echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query (aggiunta membro): ' . $conn->error]);
                    exit();
                }

                $stmt_add->bind_param("ii", $group_id, $user_id_to_add);

                if ($stmt_add->execute()) {
                    // Elimina la richiesta
                    $stmt_delete = $conn->prepare("DELETE FROM group_requests WHERE id = ?");
                    if (!$stmt_delete) {
                        echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query (eliminazione richiesta): ' . $conn->error]);
                        exit();
                    }

                    $stmt_delete->bind_param("i", $request_id);
                    $stmt_delete->execute();

                    echo json_encode(['success' => true, 'message' => 'Richiesta accettata con successo.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Errore durante l\'accettazione della richiesta.']);
                }
            } elseif ($action === 'reject') {
                // Elimina la richiesta
                $stmt_delete = $conn->prepare("DELETE FROM group_requests WHERE id = ?");
                if (!$stmt_delete) {
                    echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query (eliminazione richiesta): ' . $conn->error]);
                    exit();
                }

                $stmt_delete->bind_param("i", $request_id);

                if ($stmt_delete->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Richiesta rifiutata con successo.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Errore durante il rifiuto della richiesta.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Azione non valida.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Richiesta non trovata.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Dati non validi.']);
    }
}
?>