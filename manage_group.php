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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestisci Gruppo</title>

<!-- FONTE -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">

<!-- CSS -->
<style>
/* RESET */
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0;height:100%;font-family:'Inter',sans-serif;line-height:1.5}
img{max-width:100%;display:block}
ul{list-style:none;padding:0;margin:0}
button,input,textarea,select{font:inherit}

/* ========================= TOKENS ========================= */
:root{
  --accent-300:#7dc7ff; --accent-500:#1f96ff; --accent-600:#0e7ee0;
  --bg:#0a0d12;
  --bg-aura:
    radial-gradient(1000px 700px at 86% -10%, rgba(179,146,255,.14), transparent 60%),
    radial-gradient(900px 600px at -10% 0%, rgba(87,233,216,.12), transparent 55%),
    linear-gradient(180deg, #0c111b, #0a0d12);
  --surface-1: rgba(255,255,255,.10);
  --surface-2: rgba(255,255,255,.08);
  --line: rgba(200,220,255,.16);
  --text-1:#ebf1ff; --text-2:#b8c4da;
  --glass-blur:16px;
  --radius:16px; --radius-lg:22px;
  --shadow-1:0 18px 46px rgba(0,0,0,.55);
  --shadow-2:0 36px 90px rgba(0,0,0,.7);
  --container:1200px;
  --header-h:72px;
  --ring:0 0 0 4px color-mix(in oklab, var(--accent-500) 36%, transparent);
}

/* LIGHT THEME */
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
  --shadow-1:0 12px 32px rgba(15,18,22,.12);
  --shadow-2:0 24px 60px rgba(15,18,22,.18);
}

/* BODY + CONTAINER */
body{background:var(--bg-aura); color:var(--text-1);}
.main{width:100%; max-width:var(--container); margin:0 auto; padding:24px clamp(12px,4vw,24px);}

/* HEADER */
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
.header-right{display:flex;align-items:center;gap:12px;}
header h1{font-weight:900;font-size:1.6rem;}
nav a{
  display:inline-block; padding:0.5rem 1rem;
  border:1px solid var(--line); border-radius:var(--radius);
  background:var(--surface-2); color:var(--text-1); font-weight:700;
  text-decoration:none; transition:.3s;
}
nav a:hover{ background:var(--surface-1); }

/* THEME TOGGLE */
.theme-toggle{cursor:pointer; font-size:1.4rem; background:none; border:none; color:inherit;}

/* PANELS GLASS */
.panel{
  background:
    linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,.06)),
    var(--surface-1);
  border:1px solid var(--line); border-radius:var(--radius-lg);
  box-shadow: var(--shadow-1);
  padding:20px;
  display:flex; flex-direction:column;
  -webkit-backdrop-filter: blur(var(--glass-blur)) saturate(1.06);
  backdrop-filter: blur(var(--glass-blur)) saturate(1.06);
  margin-bottom:1rem;
}

/* INVITE LINK */
.invite-ticket a{
  display:inline-block; margin-top:12px; padding:8px 14px;
  border-radius:14px; background: var(--accent-500); color:#fff;
  font-weight:900; text-decoration:none; -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
  transition:.3s;
}
.invite-ticket a:hover{ background: var(--accent-300); color:#000; }

/* BUTTON LIQUID */
.btn--liquid{
  position: relative; isolation: isolate; overflow:clip;
  border-radius: 18px; border:1px solid rgba(255,255,255,.2);
  padding:12px 18px; font-weight:900; letter-spacing:.2px;
  background:transparent; color:var(--text-1);
  -webkit-backdrop-filter: blur(10px) saturate(1.2); backdrop-filter: blur(10px) saturate(1.2);
  cursor:pointer; transition:.2s;
}
.btn--liquid::before{
  content:""; position:absolute; inset:-25% -25%; z-index:-1; border-radius:999px;
  background: rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.15);
  -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);
  clip-path: polygon(12% 36%,32% 16%,58% 10%,82% 26%,94% 46%,94% 64%,84% 86%,56% 94%,30% 92%,14% 78%,8% 56%);
  transition:.3s;
}
.btn--liquid:hover::before{clip-path: polygon(8% 40%,30% 16%,60% 12%,84% 28%,96% 45%,96% 58%,86% 82%,58% 94%,28% 94%,12% 80%,6% 54%);}
.btn--liquid:hover{transform:translateY(-1px);}

/* LIST MEMBERS */
ul.member-list li{
  display:flex; align-items:center; justify-content:space-between; padding:10px 14px; border-radius:14px;
  background: var(--surface-2); border:1px solid var(--line); margin-bottom:8px;
}

/* FORM ELEMENTS */
input[type="text"], textarea{border-radius:12px; border:1px solid var(--line); padding:10px 12px; background:var(--surface-1); color:var(--text-1); -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px); margin-bottom:12px; width:100%;}
button.button{margin-left:6px;
padding: 10px;}

/* TOAST */
.toast{
  position: fixed; bottom:18px; left:50%; transform:translateX(-50%);
  border-radius:14px; padding:12px 20px; font-weight:700; color:#fff; backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px); box-shadow:0 16px 40px rgba(0,0,0,.35);
  transition:opacity .3s, transform .3s; opacity:0; z-index:140; pointer-events:none;
}
.toast.show{opacity:1; transform:translateX(-50%) translateY(0);}
</style>
</head>
<body>

<header>
  <h1>Gestisci il Gruppo</h1>
  <div class="header-right">
    <button class="theme-toggle" id="themeToggle" title="Cambia tema">🌙</button>
    <nav><a href="dashboard.php?group_id=<?php echo $group_id; ?>">Torna ai post</a></nav>
  </div>
</header>

<main class="main">
  <!-- Genera link invito -->
  <form id="generate-invite-form" class="panel">
    <input type="hidden" name="group_id" value="<?php echo $group_id; ?>">
    <button type="submit" class="btn--liquid">Genera link di invito</button>
  </form>
  <div id="invite-link-container"></div>

  <!-- Membri -->
  <h2>Membri del Gruppo</h2>
  <ul class="member-list panel">
    <?php while($member = $members->fetch_assoc()){ ?>
      <li>
        <span><?php echo htmlspecialchars($member['username']); ?><?php if($member['is_leader']){ ?><strong> (Capo)</strong><?php } ?></span>
        <?php if($member['id'] !== $user_id): ?>
          <div>
            <button type="button" class="btn--liquid remove_utente" data-user-id="<?php echo $member['id']; ?>">Espelli</button>
            <?php if($member['is_leader']): ?>
              <button type="button" class="btn--liquid remove_capo" style="background:#e63946;color:#fff;" data-user-id="<?php echo $member['id']; ?>">Rimuovi Capo</button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </li>
    <?php } ?>
  </ul>

  <!-- Aggiungi nuovo capo -->
  <h2>Aggiungi un nuovo capo</h2>
  <form method="POST" class="panel">
    <label for="new_leader_name">Nome Utente:</label>
    <input type="text" name="new_leader_name" id="new_leader_name" required>
    <button type="submit" class="btn--liquid">Promuovi a Capo</button>
  </form>
</main>

<!-- FOOTER -->
<footer style="text-align:center;padding:20px;color:var(--text-2);">&copy; <?php echo date("Y"); ?> Link4School</footer>

<!-- JS -->
<script>
const toggle = document.getElementById('themeToggle');
const html = document.documentElement;
const savedTheme = localStorage.getItem('theme') || 'dark';
html.setAttribute('data-theme', savedTheme);
toggle.textContent = savedTheme==='dark'?'🌙':'☀️';

toggle.addEventListener('click',()=>{
  const current = html.getAttribute('data-theme');
  const nextTheme = current==='dark'?'light':'dark';
  html.setAttribute('data-theme',nextTheme);
  toggle.textContent = nextTheme==='dark'?'🌙':'☀️';
  localStorage.setItem('theme',nextTheme);
});

// Invito
document.addEventListener('DOMContentLoaded',()=>{
  const generateInviteForm=document.getElementById('generate-invite-form');
  const inviteLinkContainer=document.getElementById('invite-link-container');
  generateInviteForm.addEventListener('submit',e=>{
    e.preventDefault();
    const formData=new FormData(generateInviteForm);
    fetch('generate_invite.php',{method:'POST',body:formData})
    .then(res=>res.json())
    .then(data=>{
      if(data.success){
        inviteLinkContainer.innerHTML=`<div class="panel invite-ticket" style="text-align:center; animation:fadeIn .4s ease;">
          <p>Link di invito generato:</p>
          <a href="${data.invite_link}" target="_blank">${data.invite_link}</a>
        </div>`;
      }else inviteLinkContainer.innerHTML=`<p style="color:#ff6b6b;">${data.message}</p>`;
    }).catch(err=>{
      inviteLinkContainer.innerHTML=`<p style="color:#ff6b6b;">Errore: ${err.message}</p>`;
    });
  });

  // Espelli utente
  document.querySelectorAll('.remove_utente').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const userId=btn.dataset.userId;
      fetch(`manage_group.php?group_id=<?php echo $group_id; ?>`,{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=remove_user&user_id=${userId}`
      }).then(r=>r.json()).then(d=>{
        if(d.success){
          btn.closest('li').remove();
          showToast(d.message);
        }else showToast(d.message,true);
      });
    });
  });

  // Rimuovi capo
  document.querySelectorAll('.remove_capo').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const userId=btn.dataset.userId;
      fetch(`manage_group.php?group_id=<?php echo $group_id; ?>`,{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`action=remove_leader&user_id=${userId}`
      }).then(r=>r.json()).then(d=>{
        if(d.success){
          const strong=btn.closest('li').querySelector('strong');
          if(strong) strong.remove();
          showToast(d.message);
        }else showToast(d.message,true);
      });
    });
  });

  function showToast(message,error=false){
    let toast=document.createElement('div');
    toast.className='toast';
    toast.style.background=error?'rgba(255,50,50,.12)':'rgba(255,255,255,.08)';
    toast.style.border=error?'1px solid rgba(255,50,50,.3)':'1px solid rgba(255,255,255,.12)';
    toast.textContent=message;
    document.body.appendChild(toast);
    toast.classList.add('show');
    setTimeout(()=>{ toast.classList.remove('show'); toast.remove(); },3500);
  }
});
</script>
</body>
</html>

