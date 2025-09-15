<?php
$conn = new mysqli('localhost', 'root', '', 'my_website');

if (!isset($_GET['token'])) {
    die('Token mancante.');
}

$token = $conn->real_escape_string($_GET['token']);

$result = $conn->query("SELECT id, token_expiry FROM users WHERE token='$token'");
if ($result->num_rows != 1) {
    die('Token non valido.');
}

$user = $result->fetch_assoc();
if (strtotime($user['token_expiry']) < time()) {
    die('Token scaduto.');
}

?>

<form method="POST" action="reset_password.php?token=<?= htmlspecialchars($token) ?>">
  Nuova password: <input type="password" name="password" required>
  Conferma password: <input type="password" name="password_confirm" required>
  <button type="submit">Salva nuova password</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'];
    $pass_confirm = $_POST['password_confirm'];

    if ($pass !== $pass_confirm) {
        echo "Le password non coincidono.";
        exit;
    }

    // Aggiorna password (hash)
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password='$hash', token=NULL, token_expiry=NULL WHERE id=" . $user['id']);
    echo "Password aggiornata con successo.";
}
?>
