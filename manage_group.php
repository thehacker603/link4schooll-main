<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

$user_id = $_SESSION['user_id'];
$group_id = $_GET['group_id'];

// Verifica che l'utente sia un capo del gruppo
$stmt_check_leader = $conn->prepare("
    SELECT is_leader FROM group_members WHERE user_id = ? AND group_id = ?
");
$stmt_check_leader->bind_param("ii", $user_id, $group_id);
$stmt_check_leader->execute();
$leader_result = $stmt_check_leader->get_result();

if ($leader_result->num_rows === 0 || !$leader_result->fetch_assoc()['is_leader']) {
    die("Non hai i permessi per gestire questo gruppo.");
}

// Verifica se l'utente è il creatore del gruppo
$stmt_check_creator = $conn->prepare("
    SELECT id FROM groups WHERE id = ? AND creator_id = ?
");
$stmt_check_creator->bind_param("ii", $group_id, $user_id);
$stmt_check_creator->execute();
$creator_result = $stmt_check_creator->get_result();

$is_creator = $creator_result->num_rows > 0;

// Gestione AJAX per rimuovere il titolo di capo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_leader') {
    $remove_leader_id = intval($_POST['user_id']);
    if ($remove_leader_id === $user_id) {
        echo json_encode(['success' => false, 'message' => "Non puoi rimuovere il tuo titolo di capo."]);
        exit();
    }
    $stmt_remove_leader = $conn->prepare("
        UPDATE group_members SET is_leader = 0 WHERE user_id = ? AND group_id = ?
    ");
    $stmt_remove_leader->bind_param("ii", $remove_leader_id, $group_id);
    $stmt_remove_leader->execute();
    echo json_encode(['success' => true, 'message' => "Il titolo di capo è stato rimosso."]);
    exit();
}

// Espelli un utente dal gruppo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_user') {
    $remove_user_id = intval($_POST['user_id']);
    if ($remove_user_id === $user_id) {
        echo json_encode(['success' => false, 'message' => "Non puoi espellere te stesso."]);
        exit();
    }
    $stmt_remove_user = $conn->prepare("
        DELETE FROM group_members WHERE user_id = ? AND group_id = ?
    ");
    $stmt_remove_user->bind_param("ii", $remove_user_id, $group_id);
    $stmt_remove_user->execute();
    echo json_encode(['success' => true, 'message' => "L'utente è stato espulso dal gruppo."]);
    exit();
}

// Aggiungi un nuovo capo tramite nome utente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_leader_name'])) {
    $new_leader_name = trim($_POST['new_leader_name']);
    $stmt_check_user = $conn->prepare("
        SELECT u.id FROM users u
        JOIN group_members gm ON u.id = gm.user_id
        WHERE u.username = ? AND gm.group_id = ?
    ");
    $stmt_check_user->bind_param("si", $new_leader_name, $group_id);
    $stmt_check_user->execute();
    $user_result = $stmt_check_user->get_result();
    if ($user_result->num_rows > 0) {
        $new_leader_id = $user_result->fetch_assoc()['id'];
        $stmt_promote = $conn->prepare("
            UPDATE group_members SET is_leader = 1 WHERE user_id = ? AND group_id = ?
        ");
        $stmt_promote->bind_param("ii", $new_leader_id, $group_id);
        $stmt_promote->execute();
        $success_message = "L'utente è stato promosso a capo.";
    } else {
        $error_message = "L'utente non esiste o non è un membro del gruppo.";
    }
}

// Gestione delle richieste di unione
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    $request_id = intval($_POST['request_id']);
    if ($_POST['action'] === 'accept') {
        $stmt_accept = $conn->prepare("UPDATE group_requests SET status = 'accepted' WHERE id = ?");
        $stmt_accept->bind_param("i", $request_id);
        $stmt_accept->execute();
        $stmt_add_member = $conn->prepare("
            INSERT INTO group_members (group_id, user_id)
            SELECT group_id, user_id FROM group_requests WHERE id = ?
        ");
        $stmt_add_member->bind_param("i", $request_id);
        $stmt_add_member->execute();
        echo json_encode(['success' => true, 'message' => 'Richiesta accettata.']);
    } elseif ($_POST['action'] === 'reject') {
        $stmt_reject = $conn->prepare("UPDATE group_requests SET status = 'rejected' WHERE id = ?");
        $stmt_reject->bind_param("i", $request_id);
        $stmt_reject->execute();
        echo json_encode(['success' => true, 'message' => 'Richiesta rifiutata.']);
    }
    exit();
}

// Recupera tutti i membri del gruppo
$stmt_members = $conn->prepare("
    SELECT u.id, u.username, gm.is_leader 
    FROM users u
    JOIN group_members gm ON u.id = gm.user_id
    WHERE gm.group_id = ?
");
$stmt_members->bind_param("i", $group_id);
$stmt_members->execute();
$members = $stmt_members->get_result();

// Verifica se l'utente è un capo del gruppo
$is_leader = false;
$stmt_check_leader = $conn->prepare("
    SELECT is_leader FROM group_members WHERE user_id = ? AND group_id = ?
");
$stmt_check_leader->bind_param("ii", $user_id, $group_id);
$stmt_check_leader->execute();
$leader_result = $stmt_check_leader->get_result();
if ($leader_result->num_rows > 0) {
    $is_leader = $leader_result->fetch_assoc()['is_leader'];
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<title>Gestisci Gruppo</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root {
    --accent-color: #8400ff;
    --border-radius: 12px;
    --transition: 0.3s ease;
    --bg-light: #ffffff;
    --bg-dark: #1e1e1e;
    --text-light: #000000;
    --text-dark: #ffffff;
    --card-light: #f9f9f9;
    --card-dark: #2c2c2c;
}

body { margin:0; font-family:'Segoe UI',sans-serif; background-color:var(--bg-light); color:var(--text-light); transition:var(--transition);}
body.dark { background-color: var(--bg-dark); color: var(--text-dark);}

header { display:flex; justify-content:space-between; align-items:center; padding:1.5rem 2rem; border-bottom:1px solid #ccc;}
.header-right { display:flex; align-items:center; gap:1rem;}
.theme-toggle { cursor:pointer; background:none; border:none; font-size:1.5rem; color:inherit;}
nav a { display:inline-block; padding:0.5rem 1rem; border:2px solid var(--text-light); border-radius:var(--border-radius); background-color:var(--card-light); color:var(--text-light); font-weight:bold; text-decoration:none; transition:var(--transition);}
body.dark nav a { border-color: var(--text-dark); background-color: var(--card-dark); color: var(--text-dark);}
nav a:hover { background-color: var(--text-light); color: var(--card-light);}
body.dark nav a:hover { background-color: var(--text-dark); color: var(--card-dark);}

main.section { max-width:800px; margin:2rem auto; padding:0 1rem;}
.card { background-color: var(--card-light); padding:1rem; border-radius:var(--border-radius); margin-bottom:1rem;}
body.dark .card { background-color: var(--card-dark); }
.button { background-color: var(--accent-color); color:#fff; border:none; padding:0.5rem 1rem; border-radius:var(--border-radius); cursor:pointer; font-weight:bold; transition:var(--transition);}
.button:hover { background-color:#00ccff; color:#000;}

/* Ticket link invito */
.invite-ticket {
    position: relative;
    width: 100%;
    max-width: 360px;
    margin: 1rem auto;
    border-radius: var(--border-radius);
    background: linear-gradient(to bottom, #1b1b1b, #2e2e44);
    color: #fff;
    padding: 1rem;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    transition: transform 0.3s ease;
}
.invite-ticket:hover { transform: translateY(-5px); }
.invite-ticket a {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.6rem 1rem;
    border-radius: 6px;
    background: var(--accent-color);
    color: #fff;
    font-weight: bold;
    text-decoration: none;
    transition: 0.3s ease;
}
.invite-ticket a:hover { background: #00ccff; color: #000; }

footer { text-align:center; padding:2rem 0; font-size:0.9rem; }
</style>
</head>
<body class="light">
<header>
<h1>Gestisci il Gruppo</h1>
<div class="header-right">
<button class="theme-toggle" id="themeToggle" title="Cambia tema">🌙</button>
<nav>
<a href="dashboard.php?group_id=<?php echo $group_id; ?>">Torna ai post</a>
</nav>
</div>
</header>

<main class="section">
<form id="generate-invite-form" class="generate-invite-form" style="margin-bottom: 2rem;">
<input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
<button type="submit" class="button">Genera link di invito</button>
</form>
<div id="invite-link-container"></div>

<h2>Membri del Gruppo</h2>
<ul class="card" style="padding:1rem; list-style:none;">
<?php while ($member = $members->fetch_assoc()) { ?>
<li style="margin-bottom:1rem;">
<?php echo htmlspecialchars($member['username']); ?>
<?php if ($member['is_leader']) { ?><strong> (Capo)</strong><?php } ?>
<?php if ($member['id'] !== $user_id): ?>
<button type="button" class="button remove_utente" style="margin-left:1rem;" data-user-id="<?php echo $member['id']; ?>">Espelli</button>
<?php if ($member['is_leader']): ?>
<button type="button" class="button remove_capo" style="margin-left:0.5rem; background:#e63946;" data-user-id="<?php echo $member['id']; ?>">Rimuovi Capo</button>
<?php endif; ?>
<?php endif; ?>
</li>
<?php } ?>
</ul>

<h2 style="margin-top:3rem;">Aggiungi un nuovo capo</h2>
<form method="POST" class="card" style="padding:1rem;">
<label for="new_leader_name">Nome Utente:</label><br>
<input type="text" name="new_leader_name" id="new_leader_name" required style="margin:0.5rem 0; padding:0.8rem; width:95%; border-radius:var(--border-radius); border:1px solid #ccc;"><br>
<button type="submit" class="button">Promuovi a Capo</button>
</form>
</main>

<footer>&copy; <?php echo date("Y"); ?> Link4School</footer>

<script>
// Tema
const toggle = document.getElementById('themeToggle');
const body = document.body;
const savedTheme = localStorage.getItem('theme') || 'light';
body.classList.add(savedTheme);
toggle.textContent = savedTheme === 'dark' ? '☀️' : '🌙';
toggle.addEventListener('click', () => {
    const isDark = body.classList.contains('dark');
    body.classList.toggle('dark', !isDark);
    body.classList.toggle('light', isDark);
    toggle.textContent = isDark ? '🌙' : '☀️';
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
});

// Gestione invito
document.addEventListener('DOMContentLoaded', () => {
    const generateInviteForm = document.getElementById('generate-invite-form');
    const inviteLinkContainer = document.getElementById('invite-link-container');
    generateInviteForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(generateInviteForm);
        fetch('generate_invite.php', { method:'POST', body:formData })
        .then(res => res.json())
        .then(data => {
            if(data.success){
                inviteLinkContainer.innerHTML = `<div class="invite-ticket"><p>Link di invito generato:</p><a href="${data.invite_link}" target="_blank">${data.invite_link}</a></div>`;
            } else {
                inviteLinkContainer.innerHTML = `<p class="error">${data.message}</p>`;
            }
        }).catch(err => {
            inviteLinkContainer.innerHTML = `<p class="error">Errore: ${err.message}</p>`;
        });
    });

    // Espelli
    document.querySelectorAll('.remove_utente').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const userId = btn.getAttribute('data-user-id');
            fetch('manage_group.php?group_id=<?php echo $group_id; ?>', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=remove_user&user_id=${userId}`
            }).then(r=>r.json()).then(d=>{
                if(d.success){btn.closest('li').remove(); alert(d.message);}
                else alert(d.message);
            });
        });
    });

    // Rimuovi capo
    document.querySelectorAll('.remove_capo').forEach(btn=>{
        btn.addEventListener('click', ()=>{
            const userId = btn.getAttribute('data-user-id');
            fetch('manage_group.php?group_id=<?php echo $group_id; ?>', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=remove_leader&user_id=${userId}`
            }).then(r=>r.json()).then(d=>{
                if(d.success){
                    const strong = btn.closest('li').querySelector('strong');
                    if(strong) strong.remove();
                    alert(d.message);
                } else alert(d.message);
            });
        });
    });
});
</script>
</body>
</html>
