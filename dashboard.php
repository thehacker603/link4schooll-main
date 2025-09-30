<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'config.php';

/* ===================== HELPERS ===================== */
function json_response($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPmailer-master/src/Exception.php';
require 'PHPmailer-master/src/PHPMailer.php';
require 'PHPmailer-master/src/SMTP.php';

function sendReportMailPHPMailer(string $userEmail, string $subject, string $reportedMessage): bool {
    $mail = new PHPMailer(true);

    try {
        // Configurazione server SMTP (usa Gmail come esempio)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'link4schooll@gmail.com'; // tua email
        $mail->Password   = 'scwxilcoxbgllxhs'; // password app o token
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Mittente e destinatario
        $mail->setFrom('link4schooll@gmail.com', 'Link4Class');
        $mail->addAddress($userEmail);

        // Contenuto
        $mail->isHTML(false); // testuale
        $mail->Subject = $subject;
        $body = "Ciao,\n\nÈ stato segnalato il seguente messaggio:\n\n";
        $body .= $reportedMessage . "\n\n";
        $body .= "Grazie per averci contattato.";
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        // fallback: salva su file
        return saveReportToFile($subject, $reportedMessage);
    }
}

// Funzione fallback (puoi tenere la tua versione originale)
function saveReportToFile(string $subject, string $body): bool {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
    $file = $dir . '/reports.log';
    $stamp = date('Y-m-d H:i:s');
    $line = "[$stamp] $subject\n$body\n--------------------------\n";
    return (bool)@file_put_contents($file, $line, FILE_APPEND);
}


/* ===================== GHOST: auto-migrazione colonne ===================== */
function ensureGhostColumns(mysqli $conn){
  $checks = [
    ['table'=>'group_posts','col'=>'is_ghost','sql'=>"ALTER TABLE group_posts ADD COLUMN is_ghost TINYINT(1) NOT NULL DEFAULT 0 AFTER created_at"],
    ['table'=>'group_posts','col'=>'ghost_name','sql'=>"ALTER TABLE group_posts ADD COLUMN ghost_name VARCHAR(32) NULL AFTER is_ghost"],
    ['table'=>'group_posts','col'=>'ghost_seed','sql'=>"ALTER TABLE group_posts ADD COLUMN ghost_seed VARCHAR(64) NULL AFTER ghost_name"],
    ['table'=>'replies','col'=>'is_ghost','sql'=>"ALTER TABLE replies ADD COLUMN is_ghost TINYINT(1) NOT NULL DEFAULT 0 AFTER created_at"],
    ['table'=>'replies','col'=>'ghost_name','sql'=>"ALTER TABLE replies ADD COLUMN ghost_name VARCHAR(32) NULL AFTER is_ghost"],
    ['table'=>'replies','col'=>'ghost_seed','sql'=>"ALTER TABLE replies ADD COLUMN ghost_seed VARCHAR(64) NULL AFTER ghost_name"],
  ];
  foreach($checks as $c){
    $res = $conn->query("SHOW COLUMNS FROM {$c['table']} LIKE '{$c['col']}'");
    if(!$res || $res->num_rows===0){
      @$conn->query($c['sql']); // best effort in dev
    }
  }
}
ensureGhostColumns($conn);

/* ===================== DATA ===================== */
$user_id = (int)($_SESSION['user_id'] ?? 0);

/* Gruppi dell'utente */
$stmt_groups = $conn->prepare("
    SELECT g.id, g.nome 
    FROM groups g 
    JOIN group_members gm ON g.id = gm.group_id 
    WHERE gm.user_id = ?
    ORDER BY g.nome ASC
");
if (!$stmt_groups) { die("Errore nella query dei gruppi: " . $conn->error); }
$stmt_groups->bind_param("i", $user_id);
$stmt_groups->execute();
$res_groups = $stmt_groups->get_result();
$user_groups = [];
while ($r = $res_groups->fetch_assoc()) { $user_groups[] = $r; }

/* Gruppo selezionato */
$selected_group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;
$selected_group_name = null;
if ($selected_group_id) {
    $stmt_group_name = $conn->prepare("SELECT nome FROM groups WHERE id = ?");
    $stmt_group_name->bind_param("i", $selected_group_id);
    $stmt_group_name->execute();
    $res_name = $stmt_group_name->get_result();
    if ($row = $res_name->fetch_assoc()) $selected_group_name = $row['nome'];
}

$posts_result = null;
$is_leader = false;
$group_access_denied = false;

/* ===================== ACTIONS ===================== */
/* A) Creazione gruppo (AJAX o fallback) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_group') {
    $group_name = trim($_POST['group_name'] ?? '');
    $group_type = trim($_POST['group_type'] ?? 'public'); // 'public' | 'private'
    $is_ajax    = isset($_POST['ajax']) && $_POST['ajax'] === '1';

    if ($group_name === '' || !in_array($group_type, ['public','private'], true)) {
        if ($is_ajax) json_response(['success'=>false, 'message'=>'Dati non validi.']);
        $_SESSION['create_group_message'] = "Nome o tipo gruppo non valido.";
        header("Location: dashboard.php"); exit();
    }

    $stmt = $conn->prepare("INSERT INTO groups (nome, type, creator_id) VALUES (?, ?, ?)");
    if (!$stmt) {
        if ($is_ajax) json_response(['success'=>false, 'message'=>'Errore SQL: '.$conn->error]);
        $_SESSION['create_group_message'] = "Errore nella preparazione della query: " . $conn->error;
        header("Location: dashboard.php"); exit();
    }
    $stmt->bind_param("ssi", $group_name, $group_type, $user_id);

    if ($stmt->execute()) {
        $group_id = $stmt->insert_id;
        $stmt_add_creator = $conn->prepare("INSERT INTO group_members (group_id, user_id, is_leader) VALUES (?, ?, 1)");
        if ($stmt_add_creator) { $stmt_add_creator->bind_param("ii", $group_id, $user_id); $stmt_add_creator->execute(); }

        if ($is_ajax) json_response(['success'=>true, 'message'=>'Gruppo creato con successo!', 'group'=>['id'=>$group_id, 'nome'=>$group_name]]);
        $_SESSION['create_group_message'] = "Gruppo creato con successo!";
        header("Location: dashboard.php"); exit();
    } else {
        if ($is_ajax) json_response(['success'=>false, 'message'=>'Errore durante la creazione: '.$stmt->error]);
        $_SESSION['create_group_message'] = "Errore durante la creazione del gruppo: " . $stmt->error;
        header("Location: dashboard.php"); exit();
    }
}

/* B) Nuovo post (con GHOST) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'new_post' && $selected_group_id) {
    $post_content = trim($_POST['post_content'] ?? '');
    $is_ghost   = isset($_POST['ghost_mode']) ? 1 : 0;
    $ghost_name = $is_ghost ? trim($_POST['ghost_name'] ?? '') : null;
    $ghost_seed = $is_ghost ? trim($_POST['ghost_seed'] ?? '') : null;

    if ($is_ghost && $ghost_name === '') { $ghost_name = 'Nebula#'.mt_rand(1000,9999); }
    if ($is_ghost && $ghost_seed === '') {
        try { $ghost_seed = bin2hex(random_bytes(6)); } catch (Throwable $e) { $ghost_seed = substr(str_shuffle('abcdef0123456789'),0,12); }
    }

    if ($post_content !== '') {
        $stmt_post = $conn->prepare("
            INSERT INTO group_posts (group_id, user_id, content, created_at, is_ghost, ghost_name, ghost_seed) 
            VALUES (?, ?, ?, NOW(), ?, ?, ?)
        ");
        if ($stmt_post) {
            $stmt_post->bind_param("iisiss", $selected_group_id, $user_id, $post_content, $is_ghost, $ghost_name, $ghost_seed);
            $stmt_post->execute();
            header("Location: dashboard.php?group_id=" . $selected_group_id); exit();
        } else {
            die("Errore nella preparazione della query di inserimento post: " . $conn->error);
        }
    }
}

/* C) Nuovo commento (con GHOST) */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'new_comment' &&
    isset($_POST['comment_content'], $_POST['post_id']) &&
    $selected_group_id
) {
    $comment_content = trim($_POST['comment_content']);
    $post_id = (int)$_POST['post_id'];

    $is_ghost   = isset($_POST['ghost_mode']) ? 1 : 0;
    $ghost_name = $is_ghost ? trim($_POST['ghost_name'] ?? '') : null;
    $ghost_seed = $is_ghost ? trim($_POST['ghost_seed'] ?? '') : null;

    if ($is_ghost && $ghost_name === '') { $ghost_name = 'Comet#'.mt_rand(1000,9999); }
    if ($is_ghost && $ghost_seed === '') {
        try { $ghost_seed = bin2hex(random_bytes(6)); } catch (Throwable $e) { $ghost_seed = substr(str_shuffle('abcdef0123456789'),0,12); }
    }

    if ($comment_content !== '') {
        $stmt_check_post = $conn->prepare("SELECT 1 FROM group_posts WHERE id = ? AND group_id = ?");
        $stmt_check_post->bind_param("ii", $post_id, $selected_group_id);
        $stmt_check_post->execute();
        $stmt_check_post->store_result();

        if ($stmt_check_post->num_rows > 0) {
            $stmt_comment = $conn->prepare("
                INSERT INTO replies (post_id, user_id, content, created_at, is_ghost, ghost_name, ghost_seed) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?)
            ");
            if ($stmt_comment) {
                $stmt_comment->bind_param("iisiss", $post_id, $user_id, $comment_content, $is_ghost, $ghost_name, $ghost_seed);
                $stmt_comment->execute();
            } else {
                die("Errore nella query di inserimento commento: " . $conn->error);
            }
        }
    }
}

/* D) Report post/commento (già presente) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report') {
    $item_type = $_POST['item_type'] ?? '';
    $item_id   = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;

    if (!in_array($item_type, ['post','comment'], true) || $item_id <= 0) {
        json_response(['success'=>false, 'message'=>'Parametri non validi.']);
    }

    if ($item_type === 'post') {
        $stmt = $conn->prepare("
            SELECT u.username, p.content, p.group_id
            FROM group_posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.id = ?
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT u.username, r.content, p.group_id
            FROM replies r
            JOIN users u ON u.id = r.user_id
            JOIN group_posts p ON p.id = r.post_id
            WHERE r.id = ?
        ");
    }
    if (!$stmt) { json_response(['success'=>false, 'message'=>'Errore SQL: '.$conn->error]); }
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if (!$row) { json_response(['success'=>false, 'message'=> ucfirst($item_type === 'post' ? 'post' : 'commento').' non trovato.']); }

    $author_username = $row['username'] ?? '';
    $content         = $row['content']  ?? '';
    $group_id_of_item= (int)$row['group_id'];

    $stmtL = $conn->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ? AND is_leader = 1");
    $stmtL->bind_param("ii", $user_id, $group_id_of_item);
    $stmtL->execute();
    $stmtL->store_result();
    if ($stmtL->num_rows === 0) { json_response(['success'=>false, 'message'=>'Solo il leader del gruppo può effettuare il report.']); }

    $to      = "link4class@gmail.com";
    $what    = ($item_type === 'post') ? 'post' : 'commento';
    $subject = "Segnalazione ".strtoupper($what)." - Gruppo #{$group_id_of_item}";
    $body    = "È stato reportato il {$what} di {$author_username} con scritto: {$content}";

$ok = sendReportMailPHPMailer($to, $subject, $body);

    if ($ok) {
        json_response(['success'=>true, 'message'=>'Segnalazione inviata.']);
    } else {
        $stored = saveReportToFile($subject, $body);
        if ($stored) json_response(['success'=>true, 'message'=>'Segnalazione salvata (offline). Configura mail() per l’invio.']);
        json_response(['success'=>false, 'message'=>'Impossibile inviare la segnalazione (mail) e salvare su file.']);
    }
}

/* Carica post/ruolo se c'è gruppo */
if ($selected_group_id) {
    $stmt_check = $conn->prepare("SELECT 1 FROM group_members WHERE user_id = ? AND group_id = ?");
    if (!$stmt_check) { die("Errore nella query di controllo gruppo: " . $conn->error); }
    $stmt_check->bind_param("ii", $user_id, $selected_group_id);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $stmt_leader_check = $conn->prepare("SELECT is_leader FROM group_members WHERE user_id = ? AND group_id = ?");
        if ($stmt_leader_check) {
            $stmt_leader_check->bind_param("ii", $user_id, $selected_group_id);
            $stmt_leader_check->execute();
            $result_leader = $stmt_leader_check->get_result();
            $row_leader = $result_leader->fetch_assoc();
            $is_leader = ($row_leader && (int)$row_leader['is_leader'] === 1);
        }

        $stmt_posts = $conn->prepare("
            SELECT p.id, p.content, p.created_at, u.username, p.user_id,
                   p.is_ghost, p.ghost_name
            FROM group_posts p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.group_id = ? 
            ORDER BY p.created_at DESC
        ");
        if (!$stmt_posts) { die("Errore nella query dei post: " . $conn->error); }
        $stmt_posts->bind_param("i", $selected_group_id);
        $stmt_posts->execute();
        $posts_result = $stmt_posts->get_result();
    } else {
        $group_access_denied = true;
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <title>Dashboard - Link4School</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800;900&display=swap" rel="stylesheet">
  <script>(function(){const t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>

  <style>
/* RESET */
*, *::before, *::after{ box-sizing:border-box }
html,body{ height:100% }
body,h1,h2,h3,h4,p,ul,ol,li,figure,blockquote,dl,dd{ margin:0 }
img{ max-width:100%; display:block }
input,button,textarea,select{ font:inherit }

/* TOKENS — DARK DEFAULT */
:root{
  --accent-300:#7dc7ff; --accent-400:#49acff; --accent-500:#1f96ff; --accent-600:#0e7ee0;
  --fx-1:#b392ff; --fx-2:#57e9d8; --fx-3:#7dbdff;

  --bg:#0a0d12;
  --bg-aura:
    radial-gradient(1000px 700px at 86% -10%, rgba(179,146,255,.14), transparent 60%),
    radial-gradient(900px 600px at -10% 0%, rgba(87,233,216,.12), transparent 55%),
    linear-gradient(180deg, #0c111b, #0a0d12);

  --surface-1: rgba(255,255,255,.10);
  --surface-2: rgba(255,255,255,.08);
  --line: rgba(200,220,255,.16);

  --text-1:#ebf1ff; --text-2:#b8c4da;

  --shadow-1: 0 18px 46px rgba(0,0,0,.55);
  --shadow-2: 0 36px 90px rgba(0,0,0,.70);

  --glass-blur: 16px;

  --radius:16px; --radius-lg:22px;

  --container:1200px; --header-h:72px;
  --ring:0 0 0 4px color-mix(in oklab, var(--accent-500) 36%, transparent);
}

/* TOKENS — LIGHT */
html[data-theme="light"]{
  --bg:#f3f6fb;
  --bg-aura:
    radial-gradient(1100px 700px at 82% -10%, rgba(122,192,255,.20), transparent 60%),
    radial-gradient(900px 600px at -10% 0%, rgba(111,232,214,.12), transparent 55%),
    linear-gradient(180deg,#ffffff,#eef3fb);
  --surface-1: rgba(255,255,255,.70);
  --surface-2: rgba(255,255,255,.55);
  --line: rgba(120,140,170,.22);
  --text-1:#0f141b; --text-2:#5c6577;
  --shadow-1: 0 12px 32px rgba(15,18,22,.12);
  --shadow-2: 0 24px 60px rgba(15,18,22,.18);
}

/* BASE + HEADER (identico impianto) */
html,body{ background:var(--bg-aura); color:var(--text-1); font-family:Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; line-height:1.5 }
header{
  position:sticky; top:0; z-index:80; height:var(--header-h);
  display:flex; align-items:center; justify-content:space-between;
  padding:0 clamp(16px,4vw,32px);
  background: linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.04));
  border-bottom:1px solid var(--line);
  -webkit-backdrop-filter: blur(12px) saturate(1.05);
  backdrop-filter: blur(12px) saturate(1.05);
  box-shadow: 0 8px 28px rgba(0,0,0,.30);
}
.header-left{ display:flex; align-items:center; gap:10px }
.logo{ font-weight:900; font-size:clamp(18px,2.2vw,22px); letter-spacing:.2px; color:var(--text-1); text-decoration:none }
.current-group{
  display:inline-flex; align-items:center; gap:8px; margin-left:8px;
  padding:8px 12px; border-radius:999px;
  background:var(--surface-2); border:1px solid var(--line); font-weight:800;
  -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
}
.close-group{
  display:inline-grid; place-items:center; width:34px; height:34px; border-radius:12px; text-decoration:none; margin-left:10px;
  background:var(--surface-2); border:1px solid var(--line); color:var(--text-1); font-weight:900;
  -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
}
.header-right{ display:flex; align-items:center; gap:12px }

/* Toggle tema */
.theme-toggle{ display:flex; align-items:center; gap:10px }
#theme-toggle{ position:absolute; left:-9999px }
.theme-label{ --switch-w:58px; --switch-h:30px; position:relative; display:inline-flex; align-items:center; gap:10px; cursor:pointer; padding:6px 10px; border-radius:999px; background:var(--surface-2); border:1px solid var(--line) }
.theme-label::after{ content:""; width:var(--switch-w); height:var(--switch-h); border-radius:999px; background:var(--surface-1); border:1px solid var(--line) }
.theme-label::before{ content:"☀️"; position:absolute; left:10px; width:24px; height:24px; border-radius:50%; display:grid; place-items:center; top:50%; translate:0 -50%; transition:transform .25s cubic-bezier(.22,.61,.36,1) }
#theme-toggle:checked + .theme-label::before{ content:"🌙"; transform:translateX(24px) }

/* WRAPPER */
.main{ width:100%; max-width:var(--container); margin:0 auto; padding:24px clamp(12px,4vw,24px) }

/* ===================== HOME (layout originale) ===================== */
.home-hero{ text-align:center; margin-bottom:20px; margin-top:6px; }
.home-title{
  font-weight:900; font-size: clamp(46px, 7.8vw, 88px); letter-spacing:-.02em; line-height:1.02; margin-bottom: 8px;
  background: linear-gradient(90deg, var(--accent-400), color-mix(in oklab, var(--accent-500) 80%, var(--fx-3)));
  -webkit-background-clip: text; background-clip: text; color: transparent;
}
.home-sub{ color:var(--text-2); font-size: clamp(16px,2vw,20px) }

/* Due pannelli affiancati (layout identico, stile più “vetro”) */
.home-panels{
  display:grid; gap:18px;
  grid-template-columns: 1fr 1fr;
  align-items:stretch;
  min-height: calc(100dvh - var(--header-h) - 140px);
}
@media (max-width: 920px){ .home-panels{ grid-template-columns: 1fr; min-height: auto; }}

/* Pannelli */
.panel{
  background:
    linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,.06)),
    var(--surface-1);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-1);
  padding: clamp(14px, 2vw, 18px);
  display:flex; flex-direction:column;
  -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(1.06);
  backdrop-filter: blur(var(--glass-blur)) saturate(1.06);
}
.panel h3{ font-size:12px; text-transform:uppercase; letter-spacing:.14em; color:var(--text-2); margin-bottom: 12px }

/* Lista gruppi */
.groups-search{ display:flex; gap:10px; margin-bottom: 12px }
.groups-search input{
  flex:1; padding:12px 14px; border-radius:12px;
  border:1px solid var(--line); background:var(--surface-2); color:var(--text-1);
  -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
}
.group-list{ list-style:none; padding:0; margin:0; display:grid; gap:10px; overflow:auto }
.group-item{
  display:flex; align-items:center; justify-content:space-between; gap:10px;
  padding:12px 14px; border-radius:14px; background: var(--surface-2); border:1px solid var(--line);
  cursor:pointer; transition: transform .12s ease, box-shadow .12s ease;
  -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
}
.group-item:hover{ transform: translateY(-1px); box-shadow: 0 10px 24px rgba(0,0,0,.22) }
.group-item.is-active{ outline: 2px solid color-mix(in oklab, var(--accent-500) 32%, transparent) }
.panel .muted{ color:var(--text-2) }

/* Azioni (Crea / Unisciti / Link) */
.actions{ display:grid; gap:14px; margin-top: 4px }
.actions .card{
  background: var(--surface-2); border:1px solid var(--line);
  border-radius:14px; padding:14px;
  -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
}
.actions .card h4{ margin:0 0 8px 0 }

/* Inputs */
input[type="text"], textarea, select{
  border:1px solid var(--line); background:var(--surface-1);
  color:var(--text-1); border-radius:12px; padding:10px 12px;
  -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
}

/* ===================== FEED ===================== */
.feed{
  background:
    radial-gradient(720px 320px at 25% -10%, color-mix(in oklab, var(--fx-1) 12%, transparent), transparent 70%),
    radial-gradient(640px 280px at 110% 10%, color-mix(in oklab, var(--fx-2) 10%, transparent), transparent 70%),
    linear-gradient(180deg, rgba(255,255,255,.16), rgba(255,255,255,.06)),
    var(--surface-1);
  -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(1.06);
  backdrop-filter: blur(var(--glass-blur)) saturate(1.06);
  border:1px solid var(--line); border-radius:22px; padding:26px; box-shadow:var(--shadow-2); overflow:clip;
}
.feed h2{ font-size:18px; margin:0 0 12px; color:var(--text-2) }
.form-container{ display:grid; gap:10px; margin:12px 0 20px }
textarea{ border-radius:14px; padding:12px 14px }

.message-list{ list-style:none; padding:0; margin:0; display:grid; gap:16px }
.message-list li{
  position:relative;
  background:
    linear-gradient(180deg, rgba(255,255,255,.16), rgba(255,255,255,.06)),
    var(--surface-2);
  -webkit-backdrop-filter: blur(calc(var(--glass-blur) * .6));
  backdrop-filter: blur(calc(var(--glass-blur) * .6));
  border:1px solid var(--line); border-radius:18px; padding:16px 16px 14px 16px; box-shadow:0 16px 40px rgba(0,0,0,.30);
}
.message-list li small{ color:var(--text-2) }
.message-list li p{ margin:8px 0 0; white-space:pre-wrap }

.comment-container{ margin-top:12px; padding-left:14px; border-left:2px solid var(--line) }
.comment-list{ list-style:none; padding:0; margin:8px 0 0; display:grid; gap:10px }
.comment-list li{
  background:var(--surface-2); border:1px solid var(--line); border-radius:12px; padding:10px 12px;
  -webkit-backdrop-filter: blur(10px); backdrop-filter: blur(10px);
}
.toggle-comments-btn{
  appearance:none; background:none; border:none; color:var(--text-2);
  font-weight:800; text-decoration:underline; cursor:pointer
}

/* ===================== BUTTONS ===================== */
/* Base (non liquidi) */
.btn{
  appearance:none; color:#0d0f11; text-decoration:none; cursor:pointer;
  border:1px solid var(--line); border-radius:14px; padding:12px 16px; font-weight:900; letter-spacing:.2px;
  background: linear-gradient(135deg, color-mix(in oklab, var(--accent-300) 86%, transparent), color-mix(in oklab, var(--accent-600) 86%, transparent));
  box-shadow: 0 14px 30px color-mix(in oklab, var(--accent-600) 18%, transparent);
  transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
}
.btn:hover{ transform: translateY(-1px); filter: saturate(1.04) }
.btn.secondary{ background: var(--surface-1); color:var(--text-1) }

.reply-btn{
  background: var(--surface-2); border:1px solid var(--line); border-radius:10px; padding:8px 12px; font-weight:800; cursor:pointer;
  -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
}

.delete-post-btn{
  position:absolute; top:10px; right:10px;
  background:linear-gradient(135deg,#ef8f98,#d74a55); border:1px solid rgba(239,143,152,.42);
  color:#fff; border-radius:12px; padding:6px 10px; font-weight:800; cursor:pointer;
}

.report-btn{
  appearance:none; border:1px solid var(--line); border-radius:10px; padding:8px 12px; font-weight:900; letter-spacing:.2px; cursor:pointer;
  background: linear-gradient(135deg, #ffd27a, #ffb25a); color:#2a1500;
  box-shadow: 0 10px 22px rgba(255,178,90,.22);
}
.report-btn[data-flag]::before{ content:"🚩"; margin-right:.35rem; }

.chat_priv{
  appearance:none; color:#0d0f11; text-decoration:none; cursor:pointer;
  border:1px solid var(--line); border-radius:14px; padding:11px 16px; font-weight:900; letter-spacing:.2px;
  background: linear-gradient(135deg, color-mix(in oklab, var(--accent-300) 86%, transparent), color-mix(in oklab, var(--accent-600) 86%, transparent));
}

/* =============== LIQUID GLASS (selezionati) ===============
   Applica .btn--liquid / .chat_priv--liquid / .report-btn--liquid
   Effetto: vetro “distorto” che deforma il contenuto sottostante al hover.
   (Solo CSS: pseudo-elemento con backdrop-filter + clip-path che cambia)
============================================================ */
.btn--liquid,
.chat_priv--liquid,
.report-btn--liquid{
  position:relative; isolation:isolate; overflow:clip;
  color: var(--text-1);
  border-radius:18px;
  border:1px solid color-mix(in oklab, var(--accent-500) 36%, var(--line));
  background: transparent;
  box-shadow:
    0 12px 32px color-mix(in oklab, var(--accent-600) 20%, transparent),
    0 0 0 1px rgba(255,255,255,.06) inset;
}

/* Strato “vetro liquido” */
.btn--liquid::before,
.chat_priv--liquid::before,
.report-btn--liquid::before{
  content:""; position:absolute; inset:-12% -16%; border-radius:28px; z-index:-1;

  /* deforma il background sottostante */
  -webkit-backdrop-filter: blur(9px) saturate(1.22) contrast(1.02);
  backdrop-filter: blur(9px) saturate(1.22) contrast(1.02);

  /* luci interne leggere + tinta appena percettibile */
  background:
    radial-gradient(120% 160% at 12% 8%, rgba(255,255,255,.66) 0%, rgba(255,255,255,.18) 42%, transparent 62%),
    linear-gradient(180deg, rgba(255,255,255,.20), rgba(255,255,255,.06)),
    linear-gradient(135deg, color-mix(in oklab, var(--accent-300) 18%, transparent), color-mix(in oklab, var(--accent-600) 18%, transparent));
  border:1px solid rgba(255,255,255,.36);

  /* blob irregolare (simula rifrazione) */
  clip-path: polygon(
    10% 30%, 28% 14%, 54% 10%, 80% 22%, 92% 42%,
    94% 60%, 86% 82%, 60% 94%, 36% 94%, 16% 82%, 8% 58%
  );

  transition: clip-path .30s cubic-bezier(.22,.61,.36,1), transform .30s cubic-bezier(.22,.61,.36,1), opacity .2s ease;
}

/* Gloss sottile */
.btn--liquid::after,
.chat_priv--liquid::after,
.report-btn--liquid::after{
  content:""; position:absolute; inset:0; border-radius:inherit; pointer-events:none;
  background:
    linear-gradient(180deg, rgba(255,255,255,.34) 0%, rgba(255,255,255,.10) 36%, rgba(255,255,255,0) 58%, rgba(255,255,255,.14) 100%);
  mix-blend-mode: soft-light;
  border:1px solid rgba(255,255,255,.12);
}

/* Hover: “storpia” di più (blob più largo) */
.btn--liquid:hover::before,
.chat_priv--liquid:hover::before,
.report-btn--liquid:hover::before{
  clip-path: polygon(
    8% 38%, 30% 16%, 58% 12%, 82% 26%, 94% 44%,
    96% 58%, 86% 82%, 58% 94%, 32% 94%, 12% 82%, 6% 54%
  );
  transform: translateY(-1px) scale(1.01);
}
.btn--liquid:hover,
.chat_priv--liquid:hover,
.report-btn--liquid:hover{ transform: translateY(-1px) }

/* Active + focus */
.btn--liquid:active,
.chat_priv--liquid:active,
.report-btn--liquid:active{ transform: translateY(0) }
.btn--liquid:focus-visible,
.chat_priv--liquid:focus-visible,
.report-btn--liquid:focus-visible{ outline:none; box-shadow: var(--ring) }

/* Variante warning liquida (report) */
.report-btn--liquid{
  color:#1b0f00;
  border-color: color-mix(in oklab, #ffb25a 46%, var(--line));
}
.report-btn--liquid::before{
  background:
    radial-gradient(120% 160% at 12% 8%, rgba(255,255,255,.62) 0%, rgba(255,255,255,.18) 40%, transparent 62%),
    linear-gradient(180deg, rgba(255,255,255,.20), rgba(255,255,255,.06)),
    linear-gradient(135deg, color-mix(in oklab, #ffd27a 24%, transparent), color-mix(in oklab, #ffb25a 24%, transparent));
}

/* ===================== GHOST UI ( invariata ) ===================== */
.ghost-name{
  background: linear-gradient(90deg,#a78bfa,#22d3ee,#7dd3fc,#a78bfa);
  -webkit-background-clip:text; background-clip:text; color:transparent; font-weight:900; letter-spacing:.2px;
}
.badge-ghost{
  display:inline-block; margin-left:8px; padding:2px 8px; font-size:11px; font-weight:900; line-height:1;
  background:
    linear-gradient(var(--surface-1),var(--surface-1)) padding-box,
    linear-gradient(90deg,#a78bfa,#22d3ee) border-box;
  border:1px solid transparent; border-radius:999px; color:#10131a;
}
.ghost-toggle{
  position:relative; gap:10px; font-weight:800; user-select:none;
  padding:6px 10px; border-radius:12px; border:1px solid var(--line);
  background: var(--surface-2);
}
.ghost-toggle input[type="checkbox"]{
  appearance:none; inline-size:44px; block-size:24px; border-radius:999px;
  background: linear-gradient(90deg, color-mix(in oklab,#a78bfa 40%, transparent), color-mix(in oklab,#22d3ee 40%, transparent));
  position:relative; outline:none; border:1px solid var(--line);
  box-shadow: inset 0 0 0 10px rgba(255,255,255,.40);
}
.ghost-toggle input[type="checkbox"]::after{
  content:"👻";
  position:absolute; inset-block:2px; inset-inline-start:2px;
  inline-size:20px; block-size:20px; display:grid; place-items:center;
  border-radius:999px; background:var(--surface-1); border:1px solid var(--line);
  transform: translateX(0); transition: transform .18s ease;
  font-size:12px;
}
.ghost-toggle input[type="checkbox"]:checked::after{ transform: translateX(20px) }
.ghost-fields{
  display:none; grid-template-columns:1fr; gap:8px;
  padding:8px; border-radius:12px; border:1px dashed color-mix(in oklab,#22d3ee 40%, transparent);
  background: color-mix(in oklab,#22d3ee 7%, transparent);
}
.ghost-fields input[type="text"]{
  border:1px solid var(--line); border-radius:10px; padding:10px 12px; background: var(--surface-1);
}

/* ===================== DRAWER & TOAST ( invariati ) ===================== */
.drawer{ position: fixed; inset: 0; z-index: 130; display:none; }
.drawer.is-open{ display:block; }
.drawer__backdrop{ position:absolute; inset:0; background: rgba(5,8,12,.42); -webkit-backdrop-filter: blur(2px); backdrop-filter: blur(2px) }
.drawer__panel{
  position:absolute; top:0; right:0; height:100%; width:min(520px, 92vw);
  background: var(--surface-1); border-left:1px solid var(--line);
  box-shadow: -24px 0 60px rgba(0,0,0,.28); display:flex; flex-direction:column;
  -webkit-backdrop-filter: blur(14px) saturate(1.04); backdrop-filter: blur(14px) saturate(1.04);
}
.drawer__header{ padding:16px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; }
.drawer__title{ font-weight:900; font-size:18px }
.drawer__close{ appearance:none; background:var(--surface-2); border:1px solid var(--line); border-radius:12px; padding:8px 10px; cursor:pointer; font-weight:800; }
.drawer__body{ padding:16px; display:grid; gap:12px; overflow:auto }
.drawer__body label{ font-weight:700; font-size:14px }
.drawer__actions{ padding:16px; border-top:1px solid var(--line); display:flex; gap:10px; justify-content:flex-end }

.toast{
  position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%);
  background: var(--surface-1); border:1px solid var(--line); border-radius: 12px; padding: 10px 14px; box-shadow: var(--shadow-1);
  font-weight:800; z-index: 140; display:none;
}
.toast.show{ display:block }

/* ===================== ACCESSIBILITY ===================== */
.btn:focus-visible,
.reply-btn:focus-visible,
.report-btn:focus-visible,
.chat_priv:focus-visible,
.drawer__close:focus-visible,
.close-group:focus-visible{ outline:none; box-shadow: var(--ring) }

@media (prefers-reduced-motion: reduce){
  *{ transition:none !important }
}
:root{
  --lg-blob-size: 160%;          /* grandezza del blob di vetro */
  --lg-blur: 10px;               /* blur del vetro */
  --lg-saturate: 1.18;
  --lg-contrast: 1.04;
  --lg-scale: 1.00;              /* scala base (press cambia) */
  --lg-noise-opacity: .14;       /* intensità grana */
  --lg-border: 1px solid rgba(255,255,255,.35);
  --lg-outline: 1px solid rgba(255,255,255,.12);
  --lg-tint-1: 210 100% 60%;     /* tinta fredda (HSL) */
  --lg-tint-2: 190 100% 58%;     /* tinta fredda 2 (HSL) */
  --lg-x: 50%;                   /* centro blob (customizzabile via JS) */
  --lg-y: 50%;
}

/* -------- Base stile bottone liquido -------- */
.btn--liquid,
.chat_priv--liquid,
.report-btn--liquid{
  position: relative;
  isolation: isolate;
  overflow: clip;
  border-radius: 18px;
  border: 1px solid color-mix(in oklab, var(--accent-500) 36%, rgba(255,255,255,.18));
  padding: 12px 18px;
  font-weight: 900;
  letter-spacing: .2px;
  background: transparent;
  color: var(--text-1);
  box-shadow:
    0 14px 30px color-mix(in oklab, var(--accent-600) 20%, transparent),
    0 0 0 1px rgba(255,255,255,.05) inset;
  transform: translateZ(0) scale(var(--lg-scale));
  transition: transform .18s cubic-bezier(.22,.61,.36,1), filter .18s ease;
}

/* -------- Strato “vetro” (blob che si deforma) -------- */
.btn--liquid::before,
.chat_priv--liquid::before,
.report-btn--liquid::before{
  content: "";
  position: absolute;
  inset: -25% -30%;
  z-index: -1;
  border-radius: 999px;

  /* pseudo-rifrazione (backdrop) */
  -webkit-backdrop-filter: blur(var(--lg-blur)) saturate(var(--lg-saturate)) contrast(var(--lg-contrast));
  backdrop-filter: blur(var(--lg-blur)) saturate(var(--lg-saturate)) contrast(var(--lg-contrast));

  /* luce + tinta appena percettibile */
  background:
    radial-gradient(120% 160% at 18% 8%, rgba(255,255,255,.72) 0%, rgba(255,255,255,.22) 36%, rgba(255,255,255,.06) 60%, transparent 70%),
    linear-gradient(180deg, rgba(255,255,255,.28), rgba(255,255,255,.08)),
    linear-gradient(135deg, hsl(var(--lg-tint-1) / .20), hsl(var(--lg-tint-2) / .20));
  border: var(--lg-border);

  /* forma “jelly” (morbida) */
  --p1x: 12%; --p1y: 36%;
  --p2x: 32%; --p2y: 16%;
  --p3x: 58%; --p3y: 10%;
  --p4x: 82%; --p4y: 26%;
  --p5x: 94%; --p5y: 46%;
  --p6x: 94%; --p6y: 64%;
  --p7x: 84%; --p7y: 86%;
  --p8x: 56%; --p8y: 94%;
  --p9x: 30%; --p9y: 92%;
  --p10x: 14%; --p10y: 78%;
  --p11x: 8%; --p11y: 56%;

  clip-path: polygon(
    var(--p1x) var(--p1y), var(--p2x) var(--p2y), var(--p3x) var(--p3y),
    var(--p4x) var(--p4y), var(--p5x) var(--p5y), var(--p6x) var(--p6y),
    var(--p7x) var(--p7y), var(--p8x) var(--p8y), var(--p9x) var(--p9y),
    var(--p10x) var(--p10y), var(--p11x) var(--p11y)
  );

  /* lieve ondulazione continua (liquid idle) */
  animation: lg-breathe 6s ease-in-out infinite;
}

/* Hover: il blob si gonfia e si “sposta” */
.btn--liquid:hover::before,
.chat_priv--liquid:hover::before,
.report-btn--liquid:hover::before{
  /* gonfiaggio (modifico leggermente i punti) */
  --p1x: 8%;  --p1y: 40%;
  --p2x: 30%; --p2y: 16%;
  --p3x: 60%; --p3y: 12%;
  --p4x: 84%; --p4y: 28%;
  --p5x: 96%; --p5y: 45%;
  --p6x: 96%; --p6y: 58%;
  --p7x: 86%; --p7y: 82%;
  --p8x: 58%; --p8y: 94%;
  --p9x: 28%; --p9y: 94%;
  --p10x: 12%; --p10y: 80%;
  --p11x: 6%;  --p11y: 54%;
  animation-duration: 3.4s;
}

/* Press: rimbalzo leggero */
.btn--liquid:active,
.chat_priv--liquid:active,
.report-btn--liquid:active{
  --lg-scale: .985;
}

/* -------- Gloss + bordo interno -------- */
.btn--liquid::after,
.chat_priv--liquid::after,
.report-btn--liquid::after{
  content:"";
  position:absolute; inset:0; border-radius:inherit; pointer-events:none;
  background:
    linear-gradient(180deg, rgba(255,255,255,.34) 0%, rgba(255,255,255,.10) 36%, rgba(255,255,255,0) 58%, rgba(255,255,255,.12) 100%),
    radial-gradient(60% 40% at 50% 0%, rgba(255,255,255,.30), transparent 70%);
  border: var(--lg-outline);
  mix-blend-mode: soft-light;
}

/* -------- Grana/Noise (sottile, animata) -------- */
.btn--liquid > .lg-noise,
.chat_priv--liquid > .lg-noise,
.report-btn--liquid > .lg-noise{
  /* OPTIONAL: se vuoi la grana, aggiungi nel markup: <span class="lg-noise" aria-hidden="true"></span> */
  position:absolute; inset:-2px; border-radius:inherit; pointer-events:none; z-index:-1;
  opacity: var(--lg-noise-opacity);
  background-image:
    radial-gradient(2px 2px at 20% 30%, rgba(255,255,255,.45) 0, transparent 60%),
    radial-gradient(2px 2px at 70% 60%, rgba(255,255,255,.35) 0, transparent 60%),
    radial-gradient(1.6px 1.6px at 40% 80%, rgba(255,255,255,.35) 0, transparent 60%),
    radial-gradient(1.2px 1.2px at 80% 20%, rgba(255,255,255,.30) 0, transparent 60%),
    radial-gradient(1px 1px at 10% 90%, rgba(255,255,255,.25) 0, transparent 60%),
    radial-gradient(1.4px 1.4px at 90% 40%, rgba(255,255,255,.28) 0, transparent 60%);
  background-size: 160px 140px, 220px 180px, 180px 180px, 200px 160px, 180px 200px, 220px 220px;
  background-position: 0 0, 40px 10px, -20px 60px, 10px -30px, 40px 100px, -30px -10px;
  animation: lg-noise-shift 8s linear infinite;
}

/* -------- Varianti colore (report “warning”) -------- */
.report-btn--liquid{
  color:#1b0f00;
  border-color: color-mix(in oklab, #ffb25a 46%, rgba(255,255,255,.18));
}
.report-btn--liquid::before{
  background:
    radial-gradient(120% 160% at 18% 8%, rgba(255,255,255,.66) 0%, rgba(255,255,255,.22) 36%, rgba(255,255,255,.06) 60%, transparent 70%),
    linear-gradient(180deg, rgba(255,255,255,.26), rgba(255,255,255,.08)),
    linear-gradient(135deg, hsl(38 100% 64% / .28), hsl(28 100% 64% / .28));
}

/* -------- Keyframes -------- */
@keyframes lg-breathe{
  0%,100%{ transform: translate3d(0,0,0) }
  50%{ transform: translate3d(0,-1px,0) }
}

@keyframes lg-noise-shift{
  0%{
    background-position: 0 0, 40px 10px, -20px 60px, 10px -30px, 40px 100px, -30px -10px;
  }
  50%{
    background-position: -60px -40px, 20px -10px, 10px 10px, -30px 20px, 10px 60px, -10px -20px;
  }
  100%{
    background-position: 0 0, 40px 10px, -20px 60px, 10px -30px, 40px 100px, -30px -10px;
  }
}

/* -------- Focus ring coerente -------- */
.btn--liquid:focus-visible,
.chat_priv--liquid:focus-visible,
.report-btn--liquid:focus-visible{
  outline: none;
  box-shadow: 0 0 0 4px color-mix(in oklab, var(--accent-500) 36%, transparent);
}
  </style>
</head>

<body>
   <svg width="0" height="0" style="position:absolute">
     <defs>
       <filter id="lg-noise">
         <feTurbulence type="fractalNoise" baseFrequency=".8" numOctaves="1" seed="9" result="t"/>
         <feGaussianBlur in="t" stdDeviation="2" result="tb"/>
         <feDisplacementMap in="SourceGraphic" in2="tb" scale="14" xChannelSelector="R" yChannelSelector="G"/>
       </filter>
       <filter id="lg-noise-strong">
         <feTurbulence type="fractalNoise" baseFrequency="1.1" numOctaves="1" seed="12" result="t2"/>
         <feGaussianBlur in="t2" stdDeviation="3" result="t2b"/>
         <feDisplacementMap in="SourceGraphic" in2="t2b" scale="18" xChannelSelector="R" yChannelSelector="G"/>
       </filter>
     </defs>
   </svg>
<header>
  <div class="header-left">
    <a href="dashboard.php" class="logo">Link4class</a>
    <?php if ($selected_group_id && $selected_group_name): ?>
      <a class="close-group" href="dashboard.php" title="Chiudi gruppo">✕</a>
      <span class="current-group" title="Gruppo aperto"><?php echo htmlspecialchars($selected_group_name); ?></span>
    <?php endif; ?>
  </div>

  <a href="chat.php" class="chat_priv chat_priv--liquid">
  Chat privata
  <span class="lg-noise" aria-hidden="true"></span>
</a>

  <div class="header-right">
    <div class="theme-toggle">
      <input type="checkbox" id="theme-toggle">
      <label for="theme-toggle" class="theme-label"></label>
    </div>
    <a href="logout.php" class="btn secondary" id="logout-link">Logout</a>
  </div>
</header>

<main class="main">
  <?php if ($group_access_denied): ?>
    <section class="feed">
      <h2>Accesso negato</h2>
      <p style="color: red;">Non hai accesso a questo gruppo.</p>
    </section>

  <?php elseif ($selected_group_id && $posts_result): ?>
    <!-- FEED -->
    <section class="feed">
      <h2>Post del gruppo</h2>

      <!-- Composer (con Ghost) -->
      <form action="dashboard.php?group_id=<?php echo $selected_group_id; ?>" method="POST" class="form-container">
        <input type="hidden" name="action" value="new_post">
        <textarea name="post_content" placeholder="Scrivi qualcosa..." rows="4" required></textarea>

        <label class="ghost-toggle">
          <input type="checkbox" id="ghostTogglePost" name="ghost_mode" value="1">
          Pubblica in Incognito
        </label>
        
        <div id="ghostFieldsPost" class="ghost-fields">
          <input type="text" name="ghost_name" id="ghostNamePost" placeholder="Pseudonimo (es. Nebula#1234)">
          <input type="hidden" name="ghost_seed" id="ghostSeedPost">
        </div>

        <button type="submit"  class="btn btn--liquid">
  Pubblica Post
  <span class="lg-noise" aria-hidden="true"></span>
</button>

      </form>

      <!-- Feed -->
      <ul class="message-list">
        <?php if ($posts_result->num_rows > 0): ?>
          <?php while ($post = $posts_result->fetch_assoc()): ?>
            <?php
              $isGhost = (int)($post['is_ghost'] ?? 0) === 1;
              $displayName = $isGhost ? ($post['ghost_name'] ?: 'Ghost') : $post['username'];
            ?>
            <li>
              <strong class="<?php echo $isGhost ? 'ghost-name' : ''; ?>">
                <?php echo htmlspecialchars($displayName); ?>
                <?php if ($isGhost): ?><span class="badge-ghost">Incognito</span><?php endif; ?>
              </strong><br>
              <small><?php echo htmlspecialchars($post['created_at']); ?></small><br>
              <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>

              <?php if ($post['user_id'] == $user_id || $is_leader): ?>
                <button class="delete-post-btn" onclick="deletePost(<?php echo $post['id']; ?>, this)">Elimina</button>
              <?php endif; ?>

              <?php
                $stmt_comments = $conn->prepare("
                  SELECT c.id, c.content, c.created_at, u.username,
                         c.is_ghost, c.ghost_name
                  FROM replies c 
                  JOIN users u ON c.user_id = u.id 
                  WHERE c.post_id = ? 
                  ORDER BY c.created_at ASC
                ");
                $stmt_comments->bind_param("i", $post['id']);
                $stmt_comments->execute();
                $comments_result = $stmt_comments->get_result();
              ?>

              <!-- Azioni post -->
              <div class="comment-actions" style="margin-top:8px; display:flex; gap:8px; align-items:center;">
                <button class="toggle-comments-btn" data-post-id="<?php echo $post['id']; ?>">Vedi risposte</button>
                <button class="reply-btn" data-reply-id="<?php echo $post['id']; ?>">Rispondi</button>
                <?php if ($is_leader): ?>
                  <button class="report-btn report-post-btn" data-flag data-post-id="<?php echo $post['id']; ?>">Reporta</button>
                <?php endif; ?>
              </div>

              <!-- Contenitore dei commenti -->
              <div class="comment-container" id="comments-<?php echo $post['id']; ?>" style="display:none;">
                <ul class="comment-list">
                  <?php if ($comments_result->num_rows > 0): ?>
                    <?php while ($comment = $comments_result->fetch_assoc()): ?>
                      <?php
                        $cGhost = (int)($comment['is_ghost'] ?? 0) === 1;
                        $cName  = $cGhost ? ($comment['ghost_name'] ?: 'Ghost') : $comment['username'];
                      ?>
                      <li>
                        <strong class="<?php echo $cGhost ? 'ghost-name' : ''; ?>">
                          <?php echo htmlspecialchars($cName); ?>
                          <?php if ($cGhost): ?><span class="badge-ghost">GHOST</span><?php endif; ?>
                        </strong><br>
                        <small><?php echo htmlspecialchars($comment['created_at']); ?></small><br>
                        <p><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>

                        <?php if ($is_leader): ?>
                          <button class="report-btn report-comment-btn" data-flag data-comment-id="<?php echo $comment['id']; ?>">Reporta</button>
                        <?php endif; ?>
                      </li>
                    <?php endwhile; ?>
                  <?php else: ?>
                    <li>Nessun commento ancora.</li>
                  <?php endif; ?>
                </ul>
              </div>

              <!-- Form risposta (con Ghost) -->
              <form id="reply-form-<?php echo $post['id']; ?>" action="dashboard.php?group_id=<?php echo $selected_group_id; ?>" method="POST" class="form-container" style="display:none">
                <input type="hidden" name="action" value="new_comment">
                <textarea name="comment_content" placeholder="Scrivi un commento..." rows="2" required></textarea>
                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">

                <label class="ghost-toggle">
                  <input type="checkbox" class="ghostToggleReply" name="ghost_mode" value="1">
                  Rispondi in Incognito
                </label>
                <div class="ghost-fields">
                  <input type="text" name="ghost_name" placeholder="Pseudonimo (es. Comet#5678)">
                  <input type="hidden" name="ghost_seed">
                </div>

                <button type="submit" class="btn">Commenta</button>
              </form>
            </li>
          <?php endwhile; ?>
        <?php else: ?>
          <li>Nessun post ancora in questo gruppo.</li>
        <?php endif; ?>
      </ul>
    </section>

  <?php else: ?>
    <!-- HOME 2-COLS -->
    <section class="home-hero">
      <h1 class="home-title">Benvenuto su Link4Class</h1>
      <p class="home-sub">Gestisci i tuoi gruppi e unisciti a nuove classi in pochi clic.</p>
      <?php if (!empty($_SESSION['create_group_message'])): ?>
        <p class="home-sub" style="margin-top:8px"><?php echo htmlspecialchars($_SESSION['create_group_message']); unset($_SESSION['create_group_message']); ?></p>
      <?php endif; ?>
    </section>

    <section class="home-panels">
      <!-- LEFT: gruppi -->
      <div class="panel">
        <h3>I tuoi gruppi</h3>
        <div class="groups-search"><input type="text" id="groupSearch" placeholder="Cerca gruppo..."></div>
        <?php if (count($user_groups) > 0): ?>
          <ul class="group-list" id="groupsList">
            <?php foreach ($user_groups as $g): ?>
              <li class="group-item" onclick="window.location.href='?group_id=<?php echo $g['id']; ?>'">
                <span><?php echo htmlspecialchars($g['nome']); ?></span><span>›</span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?><p class="muted">Nessun gruppo ancora.</p><?php endif; ?>
      </div>

      <!-- RIGHT: azioni -->
      <div class="panel">
        <h3>Azioni rapide</h3>
        <div class="actions">
          <div class="card">
            <h4>Crea un nuovo gruppo</h4>
            <p class="muted" style="margin-bottom:10px">Imposta nome e tipo, poi condividi il link con la classe.</p>
            <button class="btn" id="openCreate">+ Crea gruppo</button>
          </div>

          <div class="card">
            <h4>Unisciti a un gruppo pubblico</h4>
            <form id="join-group-form" class="form-container" style="margin:10px 0 0">
              <input type="text" name="group_name" placeholder="Es. 3A Matematica" required>
              <button type="submit" class="btn">Unisciti</button>
            </form>
          </div>

          <div class="card">
            <h4>Entra tramite link di invito</h4>
            <form id="join-link-form" class="form-container" style="margin:10px 0 0">
              <input type="text" name="invite_link" placeholder="Incolla qui il link" required>
              <button type="submit" class="btn">Entra con link</button>
            </form>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>
</main>

<!-- DRAWER CREA GRUPPO -->
<div class="drawer" id="createDrawer" aria-hidden="true" role="dialog" aria-label="Crea nuovo gruppo">
  <div class="drawer__backdrop" id="createBackdrop"></div>
  <div class="drawer__panel">
    <div class="drawer__header">
      <div class="drawer__title">Crea un nuovo gruppo</div>
      <button class="drawer__close" id="closeCreate" type="button">Chiudi ✕</button>
    </div>

    <form id="createGroupForm" method="POST" class="drawer__body">
      <input type="hidden" name="action" value="create_group">
      <label for="cg_name">Nome del gruppo</label>
      <input type="text" id="cg_name" name="group_name" placeholder="Es. 5B Scienze" required>

      <label for="cg_type">Tipo di gruppo</label>
      <select id="cg_type" name="group_type" required>
        <option value="public">Pubblico</option>
        <option value="private">Privato</option>
      </select>

      <div class="drawer__actions">
        <button type="button" class="btn secondary" id="closeCreate2">Annulla</button>
        <button type="submit" class="btn">Crea</button>
      </div>
    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// Tema iniziale
(function(){const t=localStorage.getItem('theme')||'light';document.body.classList.add(t);document.documentElement.setAttribute('data-theme',t);})();

// Toggle tema
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('theme-toggle');
  const body = document.body;
  const savedTheme = localStorage.getItem('theme') || 'light';
  toggle.checked = savedTheme === 'dark';
  toggle.addEventListener('change', () => {
    const newTheme = toggle.checked ? 'dark' : 'light';
    body.classList.remove('dark','light'); body.classList.add(newTheme);
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
  });
});

// Filtro gruppi
(function(){
  const search = document.getElementById('groupSearch');
  const list = document.getElementById('groupsList');
  if (!search || !list) return;
  const filter = (q) => {
    q = q.trim().toLowerCase();
    list.querySelectorAll('.group-item').forEach(li=>{
      const txt = li.textContent.toLowerCase();
      li.style.display = txt.includes(q) ? '' : 'none';
    });
  };
  search.addEventListener('input', e=>filter(e.target.value));
})();

// Toggle commenti + reply + logout confirm + report
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.toggle-comments-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-post-id');
      const box = document.getElementById(`comments-${id}`);
      const isHidden = (box.style.display === 'none' || !box.style.display);
      box.style.display = isHidden ? 'block' : 'none';
      btn.textContent = isHidden ? 'Nascondi risposte' : 'Vedi risposte';
    });
  });

  document.querySelectorAll('.reply-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-reply-id');
      const form = document.getElementById(`reply-form-${id}`);
      const nowHidden = (form.style.display === 'none' || !form.style.display);
      form.style.display = nowHidden ? 'flex' : 'none';

      // bind Ghost fields within this reply form (one-time)
      if (nowHidden) return;
      const toggle = form.querySelector('.ghostToggleReply');
      const box    = form.querySelector('.ghost-fields');
      const nameIn = form.querySelector('input[name="ghost_name"]');
      const seedIn = form.querySelector('input[name="ghost_seed"]');
      if (toggle && box && nameIn && seedIn && !toggle._bound){
        toggle._bound = true;
        toggle.addEventListener('change', ()=>{
          box.style.display = toggle.checked ? 'grid' : 'none';
          if (toggle.checked){
            if (!nameIn.value) nameIn.value = 'Comet#'+Math.floor(1000+Math.random()*9000);
            if (!seedIn.value) seedIn.value = Math.random().toString(36).slice(2,10);
          }
        });
      }
    });
  });

  const logoutLink = document.getElementById('logout-link');
  if (logoutLink) {
    logoutLink.addEventListener('click', function(e) {
      if (!confirm('Sei sicuro di voler uscire?')) e.preventDefault();
    });
  }

  // Bind report (server ricontrolla leader)
  document.querySelectorAll('.report-post-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!confirm('Vuoi davvero reportare questo post?')) return;
      const id = btn.getAttribute('data-post-id');
      doReport('post', id);
    });
  });
  document.querySelectorAll('.report-comment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!confirm('Vuoi davvero reportare questo commento?')) return;
      const id = btn.getAttribute('data-comment-id');
      doReport('comment', id);
    });
  });
});

// Ghost composer (post)
(function(){
  const t = document.getElementById('ghostTogglePost');
  const box = document.getElementById('ghostFieldsPost');
  const nameIn = document.getElementById('ghostNamePost');
  const seedIn = document.getElementById('ghostSeedPost');
  if (!t || !box || !nameIn || !seedIn) return;
  t.addEventListener('change', ()=>{
    box.style.display = t.checked ? 'grid' : 'none';
    if (t.checked){
      if (!nameIn.value) nameIn.value = 'Nebula#'+Math.floor(1000+Math.random()*9000);
      if (!seedIn.value) seedIn.value = Math.random().toString(36).slice(2,10);
    }
  });
})();

// Elimina post
function deletePost(postId, button) {
  fetch('delete_post.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: 'post_id=' + encodeURIComponent(postId)
  })
  .then(r => { if(!r.ok) throw new Error('Errore nella risposta del server'); return r.json(); })
  .then(data => {
      if (data.success) {Q
          button.closest('li').remove();
          showToast('Post eliminato.');
      } else {
          showToast(data.message || 'Errore durante l’eliminazione.', true);
      }
  })
  .catch(err => showToast('Errore: ' + err.message, true));
}

// Report helper
function doReport(type, id){
  const fd = new FormData();
  fd.append('action','report');
  fd.append('item_type', type);
  fd.append('item_id', id);
  fetch('dashboard.php', { method:'POST', body: fd })
    .then(r=>r.json())
    .then(res=>{ showToast(res.message || 'Segnalazione inviata.'); })
    .catch(()=> showToast('Errore durante la segnalazione.', true));
}

// Join forms
document.addEventListener('DOMContentLoaded', () => {
  const joinGroupForm = document.getElementById('join-group-form');
  if (joinGroupForm) {
    joinGroupForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const data = new FormData(joinGroupForm);
      fetch('join_group.php', { method:'POST', body:data })
      .then(res => res.json())
      .then(res => { showToast(res.message, !res.success); if (res.reload) location.reload(); })
      .catch(() => showToast('Errore durante la richiesta.', true));
    });
  }
  const joinLinkForm = document.getElementById('join-link-form');
  if (joinLinkForm) {
    joinLinkForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const data = new FormData(joinLinkForm);
      fetch('join_group.php', { method:'POST', body:data })
      .then(res => res.json())
      .then(res => { showToast(res.message, !res.success); if (res.reload) location.reload(); })
      .catch(() => showToast('Errore durante la richiesta.', true));
    });
  }
});

/* Drawer Crea Gruppo */
const openCreate = document.getElementById('openCreate');
const drawer = document.getElementById('createDrawer');
const closeCreate = document.getElementById('closeCreate');
const closeCreate2 = document.getElementById('closeCreate2');
const backdrop = document.getElementById('createBackdrop');
const formCreate = document.getElementById('createGroupForm');

function openDrawer(){
  drawer.classList.add('is-open');
  drawer.setAttribute('aria-hidden','false');
  const f = document.getElementById('cg_name');
  if (f) setTimeout(()=>f.focus(), 50);
}
function closeDrawer(){
  drawer.classList.remove('is-open');
  drawer.setAttribute('aria-hidden','true');
}
if (openCreate) openCreate.addEventListener('click', openDrawer);
[closeCreate, closeCreate2, backdrop].forEach(el=> el && el.addEventListener('click', closeDrawer));
document.addEventListener('keydown', (e)=>{ if(e.key==='Escape' && drawer.classList.contains('is-open')) closeDrawer(); });

/* Submit AJAX crea gruppo */
if (formCreate) {
  formCreate.addEventListener('submit', function(e){
    if (window.fetch) {
      e.preventDefault();
      const fd = new FormData(formCreate);
      fd.append('ajax','1');
      fetch('dashboard.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            showToast(data.message);
            tryAppendGroupToList(data.group);
            formCreate.reset(); closeDrawer();
          } else { showToast(data.message || 'Errore durante la creazione.', true); }
        })
        .catch(() => showToast('Errore di rete durante la creazione.', true));
    }
  });
}

function tryAppendGroupToList(group){
  if (!group || !group.id) return;
  const list = document.getElementById('groupsList');
  if (!list) return;
  const li = document.createElement('li');
  li.className = 'group-item';
  li.innerHTML = '<span>'+escapeHtml(group.nome || 'Nuovo gruppo')+'</span><span>›</span>';
  li.addEventListener('click', ()=> window.location.href = '?group_id='+group.id);
  list.prepend(li);
}
function escapeHtml(str){return String(str).replace(/[&<>\"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s]));}

/* Toast helper */
function showToast(msg, isError=false){
  const t = document.getElementById('toast');
  if (!t) return alert(msg);
  t.textContent = msg;
  t.style.borderColor = isError ? 'rgba(215,74,85,.36)' : 'var(--line)';
  t.classList.add('show');
  setTimeout(()=> t.classList.remove('show'), 2600);
}
</script>
<script>
document.addEventListener('pointermove', e => {
  document.querySelectorAll('.btn--liquid, .chat_priv--liquid, .report-btn--liquid').forEach(b => {
    const r = b.getBoundingClientRect();
    const x = ((e.clientX - r.left) / r.width) * 100;
    const y = ((e.clientY - r.top) / r.height) * 100;
    b.style.setProperty('--lg-x', x + '%');
    b.style.setProperty('--lg-y', y + '%');
  });
});
</script>

</body>
</html>
