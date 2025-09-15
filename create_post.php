<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php"); // Se non è loggato, torna alla pagina di login
    exit();
}

include 'config.php'; // Include la configurazione del DB

$user_id = $_SESSION['user_id'];
$group_id = $_GET['group_id'];

// Verifica che il gruppo a cui l'utente sta cercando di accedere esista
$stmt_check_group = $conn->prepare("SELECT id FROM groups WHERE id = ?");
$stmt_check_group->bind_param("i", $group_id);
$stmt_check_group->execute();
$result_group = $stmt_check_group->get_result();

if ($result_group->num_rows === 0) {
    die("Il gruppo selezionato non esiste.");
}

// Controlla che l'utente partecipi effettivamente al gruppo e verifica se è un capo
$stmt_check_membership = $conn->prepare("
    SELECT is_leader FROM group_members WHERE user_id = ? AND group_id = ?
");
$stmt_check_membership->bind_param("ii", $user_id, $group_id);
$stmt_check_membership->execute();
$membership_result = $stmt_check_membership->get_result();

if ($membership_result->num_rows === 0) {
    die("Non fai parte di questo gruppo.");
}

$membership = $membership_result->fetch_assoc();
$is_leader = $membership['is_leader'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $content = $_POST['content'];

    // Verifica che il contenuto non sia vuoto
    if (empty($content)) {
        $error = "Il contenuto del post non può essere vuoto.";
    } else {
        // Inserisci il nuovo post nel database
        $stmt = $conn->prepare("INSERT INTO posts (content, user_id, group_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sii", $content, $user_id, $group_id);
        $stmt->execute();

        // Reindirizza alla pagina con tutti i post del gruppo
        header("Location: group_posts.php?group_id=$group_id");
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Crea un Post</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Crea un nuovo post nel gruppo</h1>

    <form method="POST">
        <textarea name="content" placeholder="Scrivi qualcosa..." required></textarea>
        <button type="submit">Pubblica Post</button>
    </form>

    <?php if (isset($error)) { echo "<p>$error</p>"; } ?>

    <p><a href="group_posts.php?group_id=<?php echo htmlspecialchars($group_id); ?>">Torna ai post del gruppo</a></p>
</body>
</html>
