<?php
session_start();

include 'config.php';

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Connessione al database fallita: ' . $conn->connect_error]);
    exit();
}

// Verifica che l'utente sia autenticato
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Devi effettuare il login per unirti a un gruppo.']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_name'])) {
    // Gestione dell'unione tramite nome del gruppo
    $group_name = trim($_POST['group_name']);

    // Controlla se il gruppo esiste e il tipo
    $stmt = $conn->prepare("SELECT id, type FROM groups WHERE nome = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("s", $group_name);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_assoc();

    if ($group) {
        $group_id = $group['id'];
        $group_type = $group['type'];

        if ($group_type === 'public') {
            // Aggiungi direttamente l'utente al gruppo pubblico
            $stmt_add = $conn->prepare("INSERT INTO group_members (user_id, group_id) VALUES (?, ?)");
            if (!$stmt_add) {
                echo json_encode(['success' => false, 'message' => 'Errore nella preparazione della query: ' . $conn->error]);
                exit();
            }

            $stmt_add->bind_param("ii", $user_id, $group_id);

            if ($stmt_add->execute()) {
                echo json_encode(['success' => true, 'message' => 'Ti sei unito con successo al gruppo pubblico.', 'reload' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore durante l\'aggiunta al gruppo.']);
            }
        } else {
            // Messaggio di errore per gruppi privati
            echo json_encode(['success' => false, 'message' => 'Per unirti a gruppi privati, usa il link di invito.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Il gruppo con il nome specificato non esiste.']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_link'])) {
    // Gestione del link di invito incollato
    $invite_link = trim($_POST['invite_link']);

    // Estrai il token dal link
    $parsed_url = parse_url($invite_link);
    parse_str($parsed_url['query'], $query_params);

    if (isset($query_params['token'])) {
        $token = $query_params['token'];

        // Verifica il token
        $stmt = $conn->prepare("SELECT group_id FROM group_invitations WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $group = $result->fetch_assoc();
            $group_id = $group['group_id'];

            // Aggiungi l'utente al gruppo
            $stmt_insert = $conn->prepare("INSERT INTO group_members (group_id, user_id) VALUES (?, ?)");
            $stmt_insert->bind_param("ii", $group_id, $user_id);

            if ($stmt_insert->execute()) {
                echo json_encode(['success' => true, 'message' => 'Ti sei unito con successo al gruppo tramite link!', 'reload' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Errore durante l\'unione al gruppo.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Link di invito non valido o scaduto.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Link di invito non valido.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Richiesta non valida.']);
}
?>

