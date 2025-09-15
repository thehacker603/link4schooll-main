<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPmailer-master/src/Exception.php';
require 'PHPmailer-master/src/PHPMailer.php';
require 'PHPmailer-master/src/SMTP.php';

// Connessione al DB
$conn = new mysqli('localhost', 'root', '', 'my_website');
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Esegui solo se arriva una vera richiesta POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && !empty($_POST['email'])) {

    $email = $conn->real_escape_string($_POST['email']);

    // Verifica che l'email esista
    $result = $conn->query("SELECT id FROM users WHERE email='$email'");
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Genera token univoco
        $token = bin2hex(random_bytes(16));
        $expiry = date('Y-m-d H:i:s', time() + 3600);

        // Salva token nel DB
        $conn->query("UPDATE users SET token='$token', token_expiry='$expiry' WHERE id=" . $user['id']);

        // Crea link di reset modificare il link in base al dominio e percorso del tuo sito
        $resetLink = "https://36aff3117c07.ngrok-free.app/link4schooll-sito1/link4schooll-main44/reset_password.php?token=$token";

        // Invia email
// Invia email
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'link4schooll@gmail.com';
    $mail->Password   = 'scwxilcoxbgllxhs'; // password app Gmail
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('link4schooll@gmail.com', 'Link4Schooll');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'Reset della tua password';

    // Corpo email con pulsante
    $mail->Body = "
    <div style='font-family: Arial, sans-serif; color:#333;'>
      <h2 style='color:#3d8bff;'>Reset Password</h2>
      <p>Ciao,<br><br>Hai richiesto il reset della tua password. Clicca il pulsante qui sotto per reimpostarla:</p>
      <p style='text-align:center;'>
        <a href='$resetLink' 
           style='display:inline-block; padding:12px 24px; background-color:#3d8bff; color:#fff; text-decoration:none; border-radius:6px; font-weight:bold;'>
          Reimposta Password
        </a>
      </p>
      <p>Il link è valido per 1 ora.</p>
      <hr>
      <p style='font-size:0.8em; color:#888;'>Se non hai richiesto tu questa email, ignorala.</p>
    </div>
    ";

    $mail->send();

    header("Location: reset_sent.html");
    exit;
} catch (Exception $e) {
    echo "Errore nell'invio: {$mail->ErrorInfo}";
}

}
}
?>

<!-- Form -->
 <style>
  body {
  margin: 0;
  font-family: 'Inter', sans-serif;
  background-color: #0d0d12;
  color: white;
  overflow-x: hidden;
}

.form-container {
  max-width: 400px;
  margin: 6rem auto;
  background: #1a1a1f;
  padding: 3rem 2rem;
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  position: relative;
}

.form-container h1{
  text-align: center;
  font-size: 2rem;
  font-weight: 800;
  margin-bottom: 1rem;
}

.form-container p {
  text-align: center;
  color: #ccc;
  font-size: 0.95rem;
  margin-bottom: 2rem;
}


form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.input-group {
  display: flex;
  flex-direction: column;
}

.input-group label {
  margin-bottom: 0.5rem;
  font-weight: 600;
}

input[type="email"] {
  padding: 1rem;
  border: 1px solid #3d8bff;
  background: transparent;
  border-radius: 8px;
  font-size: 1rem;
  color: white;
  transition: 0.3s;
}

input[type="email"]:focus {
  border-color: #3d8bff;
  outline: none;
}

button[type="submit"] {
  padding: 14px 28px;
  background-color: #3d8bff;
  border: none;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  color: white;
  transition: 0.3s;
}

button[type="submit"]:hover {
  background-color: #2d6fd1;
}

.error {
  margin-top: 1rem;
  color: red;
  text-align: center;
}

.success {
  margin-top: 1rem;
  color: #3dff91;
  text-align: center;
}

.cyber-footer {
  text-align: center;
  margin-top: 2rem;
  font-size: 0.9rem;
  color: #888;
}
 </style>
<div class="form-container">
  <h1>Reset Password</h1>
  <p>Inserisci la tua email e riceverai un link per reimpostare la password.</p>
  <form method="POST" action="">
    <div class="input-group">
      <label for="email">Email</label>
      <input type="email" name="email" id="email" placeholder="tuo@esempio.com" required>
    </div>
    <button type="submit">Invia</button>
  </form>
</div>

