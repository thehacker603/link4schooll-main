<?php
session_start();
require 'config.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPmailer-master/src/Exception.php';
require 'PHPmailer-master/src/PHPMailer.php';
require 'PHPmailer-master/src/SMTP.php';

$reporter = $_SESSION['user_id'] ?? 0;
$reported = $_POST['reported_id'] ?? 0;
$message  = $_POST['message'] ?? '';

if ($reporter && $reported) {
    // --- Inserimento segnalazione nel DB ---
    $stmt = $conn->prepare("INSERT INTO chat_reports (reporter_id, reported_id, message) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $reporter, $reported, $message);
    $stmt->execute();
    $stmt->close();

    // --- Recupero email dell'utente segnalato ---
    $stmt = $conn->prepare("SELECT email, username FROM users WHERE id=?");
    $stmt->bind_param("i", $reported);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && !empty($user['email'])) {
        $mail = new PHPMailer(true);
        try {
            // Configurazione SMTP Gmail
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'link4schooll@gmail.com';        // la tua Gmail
            $mail->Password   = 'scwxilcoxbgllxhs';           // password app specifica di Gmail
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            // Mittente e destinatario
            $mail->setFrom('no-reply@example.com', 'Link4School Chat');
            $mail->addAddress($user['email'], $user['username']);

            // Contenuto email
            $mail->Subject = "Sei stato segnalato in chat da un utente";
            $mail->Body    = "Ciao {$user['username']},\n\n"
                           . "L'utente con ID $reporter ha segnalato la tua chat.\n"
                           . "Messaggio della segnalazione:\n"
                           . "$message\n\n"
                           . "Contatta l'amministratore se pensi sia un errore.";

            $mail->send();
        } catch (Exception $e) {
            error_log("Errore invio email: " . $mail->ErrorInfo);
        }
    }
}

// Ritorna alla chat
header("Location: chat.php?with=$reported");
exit;
