<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$my_id     = $_SESSION['user_id'];
$chat_with = isset($_GET['with']) ? intval($_GET['with']) : 0;

// 1. Lista utenti disponibili
$stmt = $conn->prepare("SELECT id, username FROM users WHERE id != ?");
$stmt->bind_param("i", $my_id);
$stmt->execute();
$result_users = $stmt->get_result();
$stmt->close();

// 2. Utenti con cui ho già chattato
$stmt = $conn->prepare("
    SELECT DISTINCT u.id, u.username
    FROM users u
    JOIN messages m ON (u.id = m.sender_id OR u.id = m.receiver_id)
    WHERE u.id != ? AND (m.sender_id = ? OR m.receiver_id = ?)
");
$stmt->bind_param("iii", $my_id, $my_id, $my_id);
$stmt->execute();
$result_chatted = $stmt->get_result();
$stmt->close();

// 3. Richieste pendenti
$stmt = $conn->prepare("
    SELECT cr.id, u.username, u.id AS sender_id
    FROM chat_requests cr
    JOIN users u ON cr.sender_id = u.id
    WHERE cr.receiver_id = ? AND cr.status = 'pending'
");
$stmt->bind_param("i", $my_id);
$stmt->execute();
$result_pending = $stmt->get_result();
$stmt->close();

// 4. Stato della chat selezionata
$chat_username  = null;
$request_status = null;
if ($chat_with) {
    // username interlocutore
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $chat_with);
    $stmt->execute();
    $stmt->bind_result($chat_username);
    $stmt->fetch();
    $stmt->close();

    // stato richiesta
    $stmt = $conn->prepare("
        SELECT status
        FROM chat_requests
        WHERE (sender_id = ? AND receiver_id = ?)
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->bind_param("iiii", $my_id, $chat_with, $chat_with, $my_id);
    $stmt->execute();
    $stmt->bind_result($request_status);
    $stmt->fetch();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Chat Privata</title>
  <style>
    body { margin:0; font-family:'Segoe UI',sans-serif; background:#12161f; color:#e0e6ed; display:flex; height:100vh; }
    .sidebar { width:280px; background:#1b202b; padding:20px; display:flex; flex-direction:column; gap:20px; border-right:1px solid #2b2f3a; }
    .sidebar h2 { color:#00c8ff; margin:0 0 10px; }
    .sidebar select, .sidebar button, .sidebar a { background:#2a2f3c; border:none; color:#e0e6ed; padding:10px; margin-bottom:10px; border-radius:8px; width:100%; text-align:left; text-decoration:none; }
    .sidebar a:hover, .sidebar button:hover, .sidebar select:hover { background:#343b4a; cursor:pointer; }
    .pending-request { color:#f39c12; font-weight:bold; }
    #chat-section { flex:1; display:flex; flex-direction:column; padding:20px; }
    #chat-box { flex:1; background:#1b202b; border-radius:10px; padding:20px; margin-bottom:20px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; }
    .message { max-width:60%; padding:10px 15px; border-radius:12px; word-wrap:break-word; }
    .message.sent { background:#00c8ff; color:#000; align-self:flex-end; border-bottom-right-radius:0; }
    .message.received { background:#2a2f3c; color:#e0e6ed; align-self:flex-start; border-bottom-left-radius:0; }
    #chat-form { display:flex; gap:10px; }
    #chat-form input { flex:1; padding:10px; border:none; border-radius:10px; background:#2a2f3c; color:#e0e6ed; }
    #chat-form button { background:#00c8ff; color:#000; border:none; padding:10px 20px; border-radius:10px; font-weight:bold; cursor:pointer; }
    #chat-form button:hover { background:#00b0e0; }
    .links a { color:#e0e6ed; font-size:0.9em; display:inline-block; margin-top:10px; }
    .pending-actions button { margin-right:5px; padding:5px 10px; border-radius:6px; }
  </style>
</head>
<body>

  <div class="sidebar">
    <h2>Chat Privata</h2>

    <strong>Nuova chat:</strong>
    <form id="select-user-form" action="" method="get">
      <select name="with" id="user-select" onchange="this.form.submit()">
        <option value="">-- Seleziona utente --</option>
        <?php while($u = $result_users->fetch_assoc()): ?>
          <option value="<?= $u['id'] ?>" <?= $chat_with===$u['id']?'selected':'' ?>>
            <?= htmlspecialchars($u['username']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </form>

    <strong>Richieste pendenti:</strong>
    <?php while($r = $result_pending->fetch_assoc()): ?>
      <div>
        <?= htmlspecialchars($r['username']) ?> <span class="pending-request">(In attesa)</span>
        <form method="post" action="handle_request.php" class="pending-actions">
          <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
          <button type="submit" name="action" value="approve">✅ Accetta</button>
          <button type="submit" name="action" value="reject">❌ Rifiuta</button>
        </form>
      </div>
    <?php endwhile; ?>

    <strong>Chat attive:</strong>
    <?php while($c = $result_chatted->fetch_assoc()): ?>
      <div>
        <a href="chat.php?with=<?= $c['id'] ?>">
          <?= htmlspecialchars($c['username']) ?>
        </a>
      </div>
    <?php endwhile; ?>

    <div class="links">
      <a href="dashboard.php">← Dashboard</a><br>
      <a href="logout.php">Logout</a>
    </div>
  </div>

   <div id="chat-section">
    <?php if(!$chat_with): ?>
      <p>Seleziona un utente a sinistra per iniziare.</p>
    <?php elseif($request_status!=='approved'): ?>
      <?php if($request_status==='pending'): ?>
        <p>Richiesta in attesa di approvazione.</p>
      <?php elseif($request_status==='rejected'): ?>
        <p>Richiesta rifiutata.</p>
      <?php else: ?>
        <p>Invia una richiesta di chat per iniziare.</p>
        <form method="post" action="send_request.php">
          <input type="hidden" name="to" value="<?= $chat_with ?>">
          <button type="submit">Invia richiesta chat</button>
        </form>
      <?php endif; ?>
    <?php else: ?>
      <h3>Chat con <?= htmlspecialchars($chat_username) ?></h3>
      <div id="chat-box"></div>

      <!-- form per invio messaggi -->
      <form id="chat-form" action="send_message.php" method="post">
        <input type="text"
               id="msg"
               name="message"
               autocomplete="off"
               placeholder="Scrivi..."
               required>
        <button type="submit">Invia</button>
      </form>
    <?php endif; ?>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const chatBox  = document.getElementById('chat-box');
      const chatForm = document.getElementById('chat-form');
      const msgInput = document.getElementById('msg');
      const to       = <?= $chat_with ?: 'null' ?>;

      console.log('Chat JS inizializzato, to =', to);

      function loadMessages() {
        if (!to) return;
        fetch('get_messages.php?with=' + to)
          .then(r => r.json())
          .then(data => {
            chatBox.innerHTML = '';
            data.forEach(m => {
              const d = document.createElement('div');
              d.className = 'message ' +
                (m.sender_id === <?= $my_id ?> ? 'sent' : 'received');
              d.textContent = m.message;
              chatBox.appendChild(d);
            });
            chatBox.scrollTop = chatBox.scrollHeight;
          })
          .catch(err => console.error('loadMessages error:', err));
      }

      if (to) {
        loadMessages();

        chatForm.addEventListener('submit', e => {
          e.preventDefault();
          const text = msgInput.value.trim();
          if (!text) return;

          fetch('send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `to=${to}&message=${encodeURIComponent(text)}`
          })
          .then(r => r.json())
          .then(resp => {
            if (resp.status === 'ok') {
              msgInput.value = '';
              loadMessages();
            } else {
              console.error('Errore invio:', resp);
            }
          })
          .catch(err => console.error('fetch error:', err));
        });

        // refresh automatico ogni 3 secondi
        setInterval(loadMessages, 1000);
      }
    });
  </script>
</body>
</html>
