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
    SELECT DISTINCT u.id, u.username, u.user_image
    FROM users u
    LEFT JOIN messages m 
        ON (u.id = m.sender_id OR u.id = m.receiver_id)
    LEFT JOIN chat_requests r 
        ON ( (r.sender_id = u.id AND r.receiver_id = ?) 
             OR (r.sender_id = ? AND r.receiver_id = u.id) )
    WHERE u.id != ? 
      AND (
          m.id IS NOT NULL 
          OR (r.status = 'approved')
      )
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
$blocked = false;
if ($chat_with) {
    $stmt = $conn->prepare("SELECT 1 FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $chat_with);
    $stmt->execute();
    $blocked = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat Privata</title>
<!-- il CSS fornito -->
</head>
 <style>
    /* ================= THEME TOKENS ================= */
    :root{
      /* Light */
      --bg:#edf2f8; --bg-2:#e7ecf6;
      --text:#0a0e14; --text-dim:#5b667a;

      --accent:#22d3ee; --accent-2:#60e3ff;
      --flare-1:#ff86d7; --flare-2:#8d7bff;

      --glass: rgba(255,255,255,.72);
      --glass-2: rgba(255,255,255,.58);
      --stroke: rgba(6,10,18,.14);
      --stroke-soft: rgba(6,10,18,.10);

      --blur: 18px;
      --radius: 18px; --radius-lg: 22px;
      --gap: 22px;

      --danger:#ff647a;
      --ok:#2cdd8a;
    }
    html[data-theme="dark"]{
      --bg:#0b0f16; --bg-2:#0d121b;
      --text:#eef3fb; --text-dim:#b8c2d7;
      --glass: rgba(255,255,255,.08);
      --glass-2: rgba(255,255,255,.06);
      --stroke: rgba(255,255,255,.22);
      --stroke-soft: rgba(255,255,255,.14);
    }
    @media (prefers-color-scheme: dark){
      :root{
        --bg:#0b0f16; --bg-2:#0d121b;
        --text:#eef3fb; --text-dim:#b8c2d7;
        --glass: rgba(255,255,255,.08);
        --glass-2: rgba(255,255,255,.06);
        --stroke: rgba(255,255,255,.22);
        --stroke-soft: rgba(255,255,255,.14);
      }
    }

    /* ================= APP LAYOUT ================= */
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; color:var(--text);
      font-family: ui-sans-serif, system-ui, -apple-system, Inter, Segoe UI, Roboto, Arial, sans-serif;
      background:
        radial-gradient(1100px 600px at 85% -10%, color-mix(in oklab, var(--accent) 24%, transparent), transparent 60%),
        radial-gradient(900px 520px at -10% 0%, color-mix(in oklab, var(--flare-2) 16%, transparent), transparent 55%),
        linear-gradient(180deg, var(--bg), var(--bg-2));
      min-height:100vh;
      display:grid;
      grid-template-columns: 80px 320px 1fr 320px;
      grid-template-rows: 1fr;
      gap: var(--gap);
      padding: var(--gap);
      overflow: hidden;
    }
    body::before{
      content:""; position:fixed; inset:0; pointer-events:none; z-index:0;
      background:
        radial-gradient(800px 360px at 20% 8%, color-mix(in oklab, var(--flare-1) 12%, transparent), transparent 70%),
        radial-gradient(720px 300px at 85% 100%, color-mix(in oklab, var(--accent) 10%, transparent), transparent 70%);
      mix-blend-mode: screen; opacity:.45;
    }

    /* Base glass card */
    .glass{
      background: linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.02)), var(--glass);
      border:1px solid var(--stroke);
      border-radius: var(--radius-lg);
      -webkit-backdrop-filter: blur(var(--blur)); backdrop-filter: blur(var(--blur));
      box-shadow: 0 22px 60px rgba(0,0,0,.28);
    }

    /* ============ Left App Bar ============ */
    .appbar{
      z-index:3; display:flex; flex-direction:column; align-items:center; gap:14px; padding:12px;
      border-radius: 24px; border:1px solid var(--stroke); background: var(--glass);
      -webkit-backdrop-filter: blur(var(--blur)); backdrop-filter: blur(var(--blur));
    }
    .brand{
      width:44px; height:44px; border-radius:14px; display:grid; place-items:center;
      background: linear-gradient(135deg, var(--accent), var(--flare-2)); color:#001018; font-weight:900;
      box-shadow: 0 12px 30px rgba(0,0,0,.25);
    }
    .appnav{ display:grid; gap:8px; margin-top:8px; }
    .iconbtn{
      width:44px; height:44px; border-radius:14px; display:grid; place-items:center; cursor:pointer;
      background: var(--glass-2); border:1px solid var(--stroke-soft);
      transition: transform .12s ease, box-shadow .12s ease;
    }
    .iconbtn:hover{ transform:translateY(-2px); box-shadow:0 12px 26px rgba(0,0,0,.25); }
    .iconbtn.active{ outline: 3px solid color-mix(in oklab, var(--accent) 38%, transparent); }

    /* ============ People (left) ============ */
    .people{
      z-index:2; padding:16px; display:flex; flex-direction:column; gap:14px; overflow:auto;
    }
    .title{
      margin:0; font-size:14px; letter-spacing:.14em; text-transform:uppercase; color:var(--text-dim);
    }
    .select, .pill, .link{
      background: var(--glass-2);
      border:1px solid var(--stroke-soft);
      border-radius:14px; padding:12px; width:100%;
      -webkit-backdrop-filter: blur(calc(var(--blur)*.6));
      backdrop-filter: blur(calc(var(--blur)*.6));
      color:var(--text); text-decoration:none;
      transition: transform .12s ease, box-shadow .12s ease;
    }
    .select:hover, .pill:hover, .link:hover{ transform: translateY(-1px); box-shadow: 0 12px 26px rgba(0,0,0,.22); }
    .list{ display:grid; gap:10px; }
    .userrow{
      display:flex; align-items:center; gap:10px; padding:10px 12px;
      background: var(--glass-2); border:1px solid var(--stroke-soft);
      border-radius: 14px; cursor:pointer;
      transition: transform .12s ease, box-shadow .12s ease;
    }
    .userrow:hover{ transform:translateY(-1px); box-shadow:0 12px 28px rgba(0,0,0,.22) }
    .avatar{
      width:34px; height:34px; border-radius:999px; display:grid; place-items:center; font-weight:900; font-size:14px;
      background: linear-gradient(135deg, var(--accent), var(--flare-1)); color:#031015;
    }
    .muted{ color:var(--text-dim); font-size:13px }
    .pending .muted{ color:#f8c574; font-weight:800 }

    .inline-actions{ display:flex; gap:6px; margin-top:6px }
    .btn-sm{
      appearance:none; border:1px solid var(--stroke-soft); border-radius:10px; padding:6px 9px; cursor:pointer;
      background: var(--glass); color:var(--text); font-weight:800;
    }
    .btn-approve{ background: color-mix(in oklab, var(--ok) 22%, var(--glass)); }
    .btn-reject{ background: color-mix(in oklab, var(--danger) 22%, var(--glass)); }

    /* ============ Conversation (center) ============ */
    .conversation{
      z-index:1; display:grid; grid-template-rows: auto 1fr auto; gap:14px; padding:16px; min-width:0;
    }
    .chat-head{
      display:flex; align-items:center; justify-content:space-between; padding:14px;
      border-radius:16px; border:1px solid var(--stroke); background: var(--glass);
      -webkit-backdrop-filter: blur(var(--blur)); backdrop-filter: blur(var(--blur));
    }
    .head-left{ display:flex; align-items:center; gap:12px }
    .bigavatar{
      width:42px; height:42px; border-radius:12px; display:grid; place-items:center; font-weight:900;
      background: linear-gradient(135deg, var(--accent), var(--flare-2)); color:#041016;
    }
    .name{ font-weight:900; letter-spacing:.2px }
    .sub{ font-size:12px; color:var(--text-dim) }

    .head-right{ display:flex; align-items:center; gap:8px }
    .ghost-toggle{
      border:1px solid var(--stroke); border-radius:12px; padding:10px 12px; cursor:pointer; font-weight:900;
      background: var(--glass-2);
    }
    .theme-toggle{ width:44px; height:44px; border-radius:14px; border:1px solid var(--stroke); background:var(--glass-2); display:grid; place-items:center; cursor:pointer }

    .chat-box{
      border:1px solid var(--stroke); border-radius:18px; padding:18px; overflow:auto; min-height:0;
      background: linear-gradient(180deg, rgba(255,255,255,.05), rgba(255,255,255,.02)), var(--glass-2);
      -webkit-backdrop-filter: blur(calc(var(--blur)*.75)); backdrop-filter: blur(calc(var(--blur)*.75));
      display:flex; flex-direction:column; gap:14px;
      scrollbar-gutter: stable;
    }
    .chat-box::-webkit-scrollbar{ width:10px }
    .chat-box::-webkit-scrollbar-thumb{
      background: color-mix(in oklab, var(--stroke) 50%, var(--accent) 30%);
      border-radius:999px; border:2px solid transparent; background-clip: padding-box;
    }

    .bubble{
      max-width:min(72%, 720px); padding:12px 14px; border-radius:18px; position:relative;
      border:1px solid var(--stroke-soft); -webkit-backdrop-filter: blur(calc(var(--blur)*.45)); backdrop-filter: blur(calc(var(--blur)*.45));
      box-shadow: 0 14px 36px rgba(0,0,0,.26); word-wrap:break-word; animation:bub .14s ease-out;
    }
    @keyframes bub{ from{opacity:0; transform:translateY(6px) scale(.985)} to{opacity:1; transform:none} }
    .bubble small{ display:block; opacity:.75; margin-top:6px; font-size:11px; color:var(--text-dim) }
    .bubble audio{ width:100%; max-width:300px; display:block; margin-top:6px; }

    .recv{ align-self:flex-start; background: var(--glass-2); color:var(--text); border-bottom-left-radius:10px }
    .recv::after{
      content:""; position:absolute; left:-6px; bottom:8px; width:10px; height:10px; transform:rotate(45deg);
      background:inherit; border-left:1px solid var(--stroke-soft); border-bottom:1px solid var(--stroke-soft); border-radius:2px;
    }
    .sent{
      align-self:flex-end; color:#03151a;
      background: linear-gradient(180deg, color-mix(in oklab, var(--accent) 40%, transparent), color-mix(in oklab, var(--accent-2) 36%, transparent)), var(--glass);
      border-color: color-mix(in oklab, var(--accent) 44%, var(--stroke-soft));
      border-bottom-right-radius:10px;
      box-shadow: 0 18px 44px color-mix(in oklab, rgba(34,211,238,.40) 60%, rgba(0,0,0,.32) 40%);
    }
    .sent::after{
      content:""; position:absolute; right:-6px; bottom:8px; width:10px; height:10px; transform:rotate(45deg);
      background:inherit; border-right:1px solid color-mix(in oklab, var(--accent) 36%, var(--stroke-soft));
      border-bottom:1px solid var(--stroke-soft); border-radius:2px;
    }

    .composer{
      display:flex; gap:10px; align-items:center; padding:10px;
      border:1px solid var(--stroke); border-radius:16px; background: var(--glass);
      -webkit-backdrop-filter: blur(var(--blur)); backdrop-filter: blur(var(--blur));
      box-shadow: 0 20px 60px rgba(0,0,0,.26);
    }
    .composer input{
      flex:1; padding:12px 14px; border:none; border-radius:12px; outline:none;
      background: color-mix(in oklab, var(--glass-2) 82%, transparent);
      -webkit-backdrop-filter: blur(calc(var(--blur)*.55)); backdrop-filter: blur(calc(var(--blur)*.55));
      color:var(--text); border:1px solid var(--stroke-soft);
    }
    .composer input::placeholder{ color:var(--text-dim) }
    .composer input:focus{ box-shadow: 0 0 0 3px color-mix(in oklab, var(--accent) 26%, transparent) }
    .btn{
      background: linear-gradient(135deg, var(--accent), var(--accent-2)); color:#001018;
      border:1px solid color-mix(in oklab, var(--accent) 45%, var(--stroke)); padding:12px 16px; border-radius:12px;
      font-weight:900; cursor:pointer; box-shadow: 0 16px 40px rgba(0,200,255,.30);
      transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
    }
    .btn:hover{ transform: translateY(-1px) }
    .mic{
      width:44px; height:44px; border-radius:12px; border:1px solid var(--stroke); background: var(--glass-2);
      display:grid; place-items:center; cursor:pointer;
    }
    .mic.rec{ background: linear-gradient(135deg, #ff8a7f, #ff5d6e); color:#140203; border-color: color-mix(in oklab, #ff5d6e 40%, #000 0%); }

    /* ============ Right Details ============ */
    .details{
      z-index:2; padding:16px; display:flex; flex-direction:column; gap:12px; overflow:auto;
    }
    .card{ padding:14px; border:1px solid var(--stroke-soft); border-radius:16px; background: var(--glass-2); }
    .kv{ display:grid; grid-template-columns:auto 1fr; gap:6px 12px; font-size:14px }
    .kv span{ color:var(--text-dim) }

    /* ============ Mobile drawers ============ */
    @media (max-width: 1100px){
      body{ grid-template-columns: 64px 1fr; grid-template-rows: auto 1fr auto; gap:14px; overflow:auto; }
      .people, .details{ position:fixed; top:14px; bottom:14px; width:min(92vw, 420px); z-index:30; }
      .people{ left:84px; display:none }
      .details{ right:14px; display:none }
      .drawer-open-left .people{ display:block }
      .drawer-open-right .details{ display:block }
    }
    /* === FX buttons: tilt + shine + ripple === */
.btn, .iconbtn, .pill, .btn-sm, .ghost-toggle, .mic{
  position: relative; isolation: isolate; overflow: hidden;
  transform: translateZ(0);
  transition: transform .14s cubic-bezier(.22,.61,.36,1), box-shadow .18s ease;
  will-change: transform;
}
.btn:hover, .iconbtn:hover, .pill:hover, .btn-sm:hover, .ghost-toggle:hover, .mic:hover{
  box-shadow: 0 16px 40px rgba(0,0,0,.25);
}

/* Shine diagonale */
.btn::before, .iconbtn::before, .pill::before, .btn-sm::before, .ghost-toggle::before, .mic::before{
  content:""; position:absolute; inset:-40% -60%; z-index:0;
  background:
    linear-gradient(120deg, transparent 30%, rgba(255,255,255,.35) 48%, rgba(255,255,255,.08) 52%, transparent 70%);
  transform: translateX(-60%) rotate(12deg);
  transition: transform .7s cubic-bezier(.22,.61,.36,1), opacity .3s ease;
  opacity:.0; pointer-events:none;
}
.btn:hover::before, .iconbtn:hover::before, .pill:hover::before, .btn-sm:hover::before, .ghost-toggle:hover::before, .mic:hover::before{
  transform: translateX(30%) rotate(12deg);
  opacity:.9;
}

/* Ripple (creato via JS come <span class="rpl">) */
.rpl{
  position:absolute; z-index:1;
  border-radius:999px;
  pointer-events:none;
  transform: scale(0);
  background: radial-gradient(circle, rgba(255,255,255,.55) 0%, rgba(255,255,255,.35) 35%, transparent 60%);
  mix-blend-mode: soft-light;
  animation: rpl .6s ease-out forwards;
}
@keyframes rpl{ to{ transform:scale(18); opacity:0 } }

/* Press feedback */
.btn:active, .iconbtn:active, .pill:active, .btn-sm:active, .ghost-toggle:active, .mic:active{
  transform: translateZ(0) scale(.985);
}

/* Accessibilità focus */
.btn:focus-visible, .iconbtn:focus-visible, .pill:focus-visible, .btn-sm:focus-visible, .ghost-toggle:focus-visible, .mic:focus-visible{
  outline:none;
  box-shadow: 0 0 0 4px color-mix(in oklab, var(--accent) 35%, transparent), 0 16px 40px rgba(0,0,0,.25);
  transition: box-shadow .12s ease;
}
/* === Cursor Glow Trail (glass-style) === */
.cursor-trail{ position:fixed; inset:0; pointer-events:none; z-index:1000 }
.cursor-trail .spark{
  position:fixed; width:14px; height:14px; border-radius:50%;
  background: radial-gradient(circle at 30% 30%,
              rgba(255,255,255,.92),
              rgba(255,255,255,.38) 40%,
              transparent 70%);
  filter: blur(10px);
  mix-blend-mode: screen;
  opacity:0;
  transform: translate(-7px,-7px);
}
html[data-theme="dark"] .cursor-trail .spark{
  background: radial-gradient(circle at 30% 30%,
              rgba(255,255,255,.9),
              rgba(120,200,255,.45) 40%,
              transparent 70%);
}

/* === Pulse ring per evidenziare bottoni "live" === */
.pulse-live{ position:relative; isolation:isolate }
.pulse-live::after{
  content:""; position:absolute; inset:-2px; border-radius:inherit;
  box-shadow:0 0 0 0 rgba(32,180,255,.70);
  opacity:.75; animation:pulseRing 1.6s ease-out infinite;
  z-index:-1;
}
@keyframes pulseRing{
  0%   { box-shadow:0 0 0 0 rgba(32,180,255,.70); opacity:.7 }
  70%  { box-shadow:0 0 0 14px rgba(32,180,255,0); opacity:0 }
  100% { box-shadow:0 0 0 0 rgba(32,180,255,0); opacity:0 }
}

/* === Mic: breathing glow quando recording === */
.mic.recording{
  animation: breatheGlow 1.2s ease-in-out infinite;
  box-shadow: 0 0 0 0 rgba(231,76,60,.55), inset 0 0 22px rgba(231,76,60,.45);
}
@keyframes breatheGlow{
  0%   { box-shadow:0 0 0 0 rgba(231,76,60,.55), inset 0 0 12px rgba(231,76,60,.35); transform:scale(1) }
  50%  { box-shadow:0 0 0 14px rgba(231,76,60,0), inset 0 0 30px rgba(231,76,60,.6); transform:scale(1.03) }
  100% { box-shadow:0 0 0 0 rgba(231,76,60,0), inset 0 0 12px rgba(231,76,60,.35); transform:scale(1) }
}

/* === Ripple + shine + tilt base (aggancia alle classi bottone) === */
.btn, .iconbtn, .pill, .btn-sm, .ghost-toggle, .mic{
  position:relative; overflow:hidden; isolation:isolate; transform:translateZ(0);
  transition: transform .14s cubic-bezier(.22,.61,.36,1), box-shadow .18s ease;
}
.btn:hover, .iconbtn:hover, .pill:hover, .btn-sm:hover, .ghost-toggle:hover, .mic:hover{
  box-shadow: 0 16px 40px rgba(0,0,0,.25);
}
/* Shine diagonale */
.btn::before, .iconbtn::before, .pill::before, .btn-sm::before, .ghost-toggle::before, .mic::before{
  content:""; position:absolute; inset:-40% -60%; z-index:0; pointer-events:none;
  background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,.35) 48%, rgba(255,255,255,.08) 52%, transparent 70%);
  transform: translateX(-60%) rotate(12deg);
  transition: transform .7s cubic-bezier(.22,.61,.36,1), opacity .3s ease; opacity:0;
}
.btn:hover::before, .iconbtn:hover::before, .pill:hover::before, .btn-sm:hover::before, .ghost-toggle:hover::before, .mic:hover::before{
  transform: translateX(30%) rotate(12deg); opacity:.9;
}
/* Ripple (creato via JS come <span class="rpl">) */
.rpl{
  position:absolute; border-radius:999px; pointer-events:none; z-index:1;
  transform: scale(0);
  background: radial-gradient(circle, rgba(255,255,255,.55) 0%, rgba(255,255,255,.35) 35%, transparent 60%);
  mix-blend-mode: soft-light;
  animation: rpl .6s ease-out forwards;
}
@keyframes rpl{ to{ transform:scale(18); opacity:0 } }

/* Press feedback */
.btn:active, .iconbtn:active, .pill:active, .btn-sm:active, .ghost-toggle:active, .mic:active{
  transform: translateZ(0) scale(.985);
}

/* Riduci motion */
@media (prefers-reduced-motion: reduce){
  .rpl, .cursor-trail{ display:none }
  .btn, .iconbtn, .pill, .btn-sm, .ghost-toggle, .mic{ transition:none }
}
/* ======= GLASS BACKDROP: morphing blobs ======= */
:root{
  --fx-ac1:#6ae1ff; --fx-ac2:#b07dff; --fx-ac3:#66ffc8; --fx-ac4:#ff9ab8;
  --fx-neon:#5be0ff; --fx-neon2:#82ffde;
}
html,body{overscroll-behavior: none}

body::before,
body::after{
  content:""; position:fixed; inset:-20vmax; z-index:-2; pointer-events:none;
  filter: blur(48px) saturate(120%);
  background:
    radial-gradient(40vmax 40vmax at 15% 10%, color-mix(in oklab, var(--fx-ac1) 56%, transparent), transparent 60%),
    radial-gradient(42vmax 42vmax at 85% 20%, color-mix(in oklab, var(--fx-ac2) 50%, transparent), transparent 62%),
    radial-gradient(38vmax 38vmax at 30% 90%, color-mix(in oklab, var(--fx-ac3) 46%, transparent), transparent 65%);
  animation: blobsA 18s ease-in-out infinite alternate;
}
body::after{
  z-index:-3; filter: blur(90px) saturate(110%) opacity(.7);
  background:
    radial-gradient(55vmax 55vmax at 75% 80%, color-mix(in oklab, var(--fx-ac4) 38%, transparent), transparent 65%);
  animation: blobsB 22s ease-in-out infinite alternate;
}
@keyframes blobsA{ 0%{transform:translate3d(0,0,0) scale(1)}
  50%{transform:translate3d(-2%,1%,0) scale(1.05)}
  100%{transform:translate3d(2%,-1%,0) scale(1.02)}}
@keyframes blobsB{ 0%{transform:translate3d(1%,-2%,0) scale(1)}
  100%{transform:translate3d(-1%,2%,0) scale(1.06)}}

/* ======= Grain analogico (sottilissimo) ======= */
.fx-grain{
  position:fixed; inset:0; pointer-events:none; z-index: 999;
  opacity:.12; mix-blend-mode: overlay;
  background-image:
    radial-gradient(2px 2px at 20px 30px, rgba(255,255,255,.08) 50%, transparent 51%),
    radial-gradient(2px 2px at 50px 80px, rgba(0,0,0,.08) 50%, transparent 51%),
    radial-gradient(2px 2px at 70px 120px, rgba(255,255,255,.08) 50%, transparent 51%);
  background-size: 200px 200px, 240px 240px, 260px 260px;
  animation: grainMove 2.6s steps(6) infinite;
}
@keyframes grainMove{ to{ transform: translate3d(-20px, -30px, 0) } }

/* ======= Spotlight che segue il mouse ======= */
#fx-spotlight{
  position:fixed; inset:0; pointer-events:none; z-index: 998;
  background: radial-gradient(18rem 18rem at var(--sx,50%) var(--sy,50%),
              rgba(255,255,255,.12), transparent 60%);
  mix-blend-mode: soft-light;
}

/* ======= Confetti canvas ======= */
#fx-confetti{ position:fixed; inset:0; pointer-events:none; z-index: 1000 }

/* ======= Neon border animato (aggiungi .neon) ======= */
.neon{
  position:relative; border-radius:16px; isolation:isolate;
  background: color-mix(in oklab, rgba(255,255,255,.12) 70%, transparent);
  backdrop-filter: blur(10px);
}
.neon::before{
  content:""; position:absolute; inset:-1px; border-radius: inherit; z-index:-1;
  padding:1px; -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
  -webkit-mask-composite: xor; mask-composite: exclude;
  background: conic-gradient(from var(--deg,0deg),
     var(--fx-neon), var(--fx-neon2), var(--fx-neon), #fff, var(--fx-neon));
  animation: neonSpin 6s linear infinite;
}
@keyframes neonSpin{ to{ --deg: 360deg } }

/* ======= Bottone FX: ripple, shine, tilt, pulse (riusa le tue .btn/.mic ecc.) ======= */
.btn,.iconbtn,.pill,.btn-sm,.ghost-toggle,.mic{
  position:relative; overflow:hidden; isolation:isolate; transform:translateZ(0);
  transition: transform .14s cubic-bezier(.22,.61,.36,1), box-shadow .18s ease, filter .2s ease;
}
.btn:hover,.iconbtn:hover,.pill:hover,.btn-sm:hover,.ghost-toggle:hover,.mic:hover{
  box-shadow: 0 16px 40px rgba(0,0,0,.25); filter:saturate(1.06) contrast(1.03);
}
/* shine */
.btn::before,.iconbtn::before,.pill::before,.btn-sm::before,.ghost-toggle::before,.mic::before{
  content:""; position:absolute; inset:-40% -60%; z-index:0; pointer-events:none;
  background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,.35) 48%, rgba(255,255,255,.08) 52%, transparent 70%);
  transform: translateX(-60%) rotate(12deg);
  transition: transform .7s cubic-bezier(.22,.61,.36,1), opacity .3s ease; opacity:0;
}
.btn:hover::before,.iconbtn:hover::before,.pill:hover::before,.btn-sm:hover::before,.ghost-toggle:hover::before,.mic:hover::before{
  transform: translateX(30%) rotate(12deg); opacity:.9;
}
/* ripple */
.rpl{ position:absolute; border-radius:999px; pointer-events:none; z-index:1;
  transform: scale(0);
  background: radial-gradient(circle, rgba(255,255,255,.55) 0%, rgba(255,255,255,.35) 35%, transparent 60%);
  mix-blend-mode: soft-light; animation: rpl .6s ease-out forwards;
}
@keyframes rpl{ to{ transform:scale(18); opacity:0 } }
.btn:active,.iconbtn:active,.pill:active,.btn-sm:active,.ghost-toggle:active,.mic:active{ transform: scale(.985) }

/* mic live + breathing (riusa classi già viste) */
.pulse-live{ position:relative; isolation:isolate }
.pulse-live::after{
  content:""; position:absolute; inset:-2px; border-radius:inherit;
  box-shadow:0 0 0 0 rgba(32,180,255,.70); opacity:.75; animation:pulseRing 1.6s ease-out infinite; z-index:-1;
}
@keyframes pulseRing{
  0%{ box-shadow:0 0 0 0 rgba(32,180,255,.70); opacity:.7 }
  70%{ box-shadow:0 0 0 14px rgba(32,180,255,0); opacity:0 }
  100%{ box-shadow:0 0 0 0 rgba(32,180,255,0); opacity:0 }
}
.mic.recording{
  animation: breatheGlow 1.2s ease-in-out infinite;
  box-shadow: 0 0 0 0 rgba(231,76,60,.55), inset 0 0 22px rgba(231,76,60,.45);
}
@keyframes breatheGlow{
  0%{ box-shadow:0 0 0 0 rgba(231,76,60,.55), inset 0 0 12px rgba(231,76,60,.35); transform:scale(1)}
  50%{ box-shadow:0 0 0 14px rgba(231,76,60,0), inset 0 0 30px rgba(231,76,60,.6); transform:scale(1.03)}
  100%{ box-shadow:0 0 0 0 rgba(231,76,60,0), inset 0 0 12px rgba(231,76,60,.35); transform:scale(1)}
}

/* ======= Messaggi: reveal cinematico + glass ======= */
.message{
  backdrop-filter: blur(10px) saturate(120%); -webkit-backdrop-filter: blur(10px) saturate(120%);
  position:relative; overflow:hidden;
  animation: bubbleIn .35s cubic-bezier(.22,.61,.36,1);
}
.message.sent{
  background: linear-gradient(180deg, rgba(255,255,255,.28), rgba(255,255,255,.16));
  border:1px solid rgba(255,255,255,.24);
}
.message.received{
  background: linear-gradient(180deg, rgba(0,0,0,.18), rgba(0,0,0,.10));
  border:1px solid rgba(255,255,255,.12);
}
@keyframes bubbleIn{
  from{ transform: translateY(8px) scale(.98); opacity:0; filter: blur(6px) }
  to{   transform: none; opacity:1; filter: blur(0) }
}

/* Skeleton shimmer per caricamento chat */
#chat-box.loading{
  position:relative; overflow:hidden;
}
#chat-box.loading::after{
  content:""; position:absolute; inset:0; pointer-events:none;
  background: linear-gradient(100deg, transparent 30%, rgba(255,255,255,.08) 45%, transparent 60%);
  animation: shimmer 1.2s linear infinite;
}
@keyframes shimmer{ to{ transform: translateX(100%) } }

/* Emoji burst nel box */
.fx-emoji{
  position:absolute; pointer-events:none; z-index:5; font-size:18px;
  will-change: transform, opacity; filter: drop-shadow(0 2px 6px rgba(0,0,0,.25));
  animation: emojiUp .9s ease-out forwards;
}
@keyframes emojiUp{
  0%{ transform: translate(var(--sx,0), var(--sy,0)) scale(.9); opacity:0 }
  15%{ opacity:1 }
  100%{ transform: translate(calc(var(--sx,0) * .6), calc(var(--sy,0) - 70px)) rotate(14deg) scale(1.1); opacity:0 }
}

/* Riduci motion */
@media (prefers-reduced-motion: reduce){
  #fx-spotlight,.fx-grain,.rpl,.fx-emoji{ display:none !important }
  body::before, body::after{ animation:none }
  .message{ animation:none }
}
/* kill cursor trail */
.cursor-trail, .cursor-trail .spark { display:none !important; }
/* ======= Input segnalazione ======= */
form.report-chat {
  display: block;
  gap: 8px;
  margin-top: 12px;
  align-items: center;
}

form.report-chat input[type="text"] {
  flex: 1;
  padding: 12px 14px;
  margin-bottom: 12px;
  border-radius: 14px;
  
  border: 1px solid var(--stroke-soft);
  background: var(--glass-2);
  color: var(--text);
  font-size: 14px;
  -webkit-backdrop-filter: blur(calc(var(--blur) * 0.55));
  backdrop-filter: blur(calc(var(--blur) * 0.55));
  transition: box-shadow 0.12s ease, transform 0.12s ease;
}

form.report-chat input[type="text"]::placeholder {
  color: var(--text-dim);
}

form.report-chat input[type="text"]:focus {
  outline: none;
  box-shadow: 0 0 0 3px color-mix(in oklab, var(--accent) 26%, transparent);
  transform: translateY(-1px);
}

form.report-chat button.pill {
  min-width: 120px;
  padding: 12px 16px;
  border-radius: 14px;
  border: 1px solid var(--stroke-soft);
  background: linear-gradient(135deg, var(--accent), var(--accent-2));
  color: #001018;
  font-weight: 900;
  cursor: pointer;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}

form.report-chat button.pill:hover {
  transform: translateY(-1px);
  box-shadow: 0 16px 40px rgba(0,0,0,.25);
}

/* Optional: aggiunge effetto shine come per gli altri pulsanti */
form.report-chat button.pill::before {
  content: "";
  position: absolute;
  inset: -40% -60%;
  z-index: 0;
  background: linear-gradient(120deg, transparent 30%, rgba(255,255,255,.35) 48%, rgba(255,255,255,.08) 52%, transparent 70%);
  transform: translateX(-60%) rotate(12deg);
  transition: transform 0.7s cubic-bezier(.22,.61,.36,1), opacity .3s ease;
  opacity: 0;
  pointer-events: none;
}

form.report-chat button.pill:hover::before {
  transform: translateX(30%) rotate(12deg);
  opacity: 0.9;
}
.emoji-picker {
  position: absolute;   /* resta ancorato sotto l'input */
  bottom: 60px;         /* distanza dall'input */
  left: 0;
  width: 100%;
  max-height: 180px;    /* altezza massima */
  overflow-y: auto;     /* scroll verticale se troppe emoji */
  display: grid;
  grid-template-columns: repeat(auto-fill, 36px);
  gap: 6px;
  padding: 8px;
  border-radius: 12px;
  background: var(--surface-1);
  border: 1px solid var(--line);
  -webkit-backdrop-filter: blur(10px);
  backdrop-filter: blur(10px);
  z-index: 100;
}

.emoji-picker .emoji {
  width: 36px;
  height: 36px;
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 22px;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.15s ease;
  background: white;
}

.emoji-picker .emoji:hover {
  background: rgba(255, 255, 255, 0.12);
}

.conversation {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 20px); /* altezza della finestra, meno padding/margini se vuoi */
}

.chat-box {
  flex: 1;                  /* prende tutto lo spazio rimasto */
  overflow-y: auto;         /* scrollbar verticale solo per i messaggi */
  padding: 10px;
}

.composer {
  display: flex;
  gap: 6px;
  padding: 8px;
  border-top: 1px solid var(--line);
  background: var(--background);
  flex-shrink: 0;           /* non si restringe */
  position: relative;        /* necessaria per emoji picker */
}

.emoji-picker {
  position: absolute;
  bottom: 50px;             /* sopra la textbox */
  left: 0;
  right: 0;
  display: none;
  grid-template-columns: repeat(auto-fill, minmax(32px, 1fr));
  gap: 4px;
  max-height: 150px;
  overflow-y: auto;
  background: var(--background-alt);
  padding: 6px;
  border: 1px solid var(--line);
  border-radius: 8px;
  z-index: 10;
}



  </style>
<body data-theme="dark">

  <!-- ======= Appbar sinistra ======= -->
  <nav class="appbar glass">
    <div class="brand">L4C</div>
    <div class="appnav">
      <a class="iconbtn" href="dashboard.php" title="Dashboard">🏠</a>
      <button class="iconbtn active" type="button" title="Contatti" id="toggle-people">👥</button>
      <button class="iconbtn" type="button" title="Dettagli" id="toggle-details">ℹ️</button>
      <a class="iconbtn" href="logout.php" title="Logout">↪️</a>
    </div>
  </nav>

  <!-- ======= People list ======= -->
  <div class="people glass">
    <h3 class="title">Nuova chat</h3>
    <form id="select-user-form" action="" method="get">
      <select name="with" class="select" onchange="this.form.submit()">
        <option value="">-- Seleziona utente --</option>
        <?php while($u = $result_users->fetch_assoc()): ?>
          <option value="<?= $u['id'] ?>" <?= $chat_with===$u['id']?'selected':'' ?>>
            <?= htmlspecialchars($u['username']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </form>

    <h3 class="title">Richieste pendenti</h3>
    <div class="list">
      <?php while($r = $result_pending->fetch_assoc()): ?>
        <div class="userrow pending">
          <div class="avatar"><?= strtoupper($r['username'][0]) ?></div>
          <div class="muted"><?= htmlspecialchars($r['username']) ?> (In attesa)</div>
          <div class="inline-actions">
            <form method="post" action="handle_request.php">
              <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
              <button type="submit" name="action" value="approve" class="btn-sm btn-approve">✅</button>
              <button type="submit" name="action" value="reject" class="btn-sm btn-reject">❌</button>
            </form>
          </div>
        </div>
      <?php endwhile; ?>
    </div>

<h3 class="title">Chat attive</h3>
<div class="list">
  <?php while($c = $result_chatted->fetch_assoc()): ?>
    <?php 
      $chat_img = !empty($c['user_image']) ? $c['user_image'] : "uploads/profile_default.jpg";
    ?>
    <a href="chat.php?with=<?= $c['id'] ?>" class="pill" style="display:flex; align-items:center; gap:8px;">
      <img src="<?= htmlspecialchars($chat_img) ?>" alt="<?= htmlspecialchars($c['username']) ?>" 
           style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
      <?= htmlspecialchars($c['username']) ?>
    </a>
  <?php endwhile; ?>
</div>


    <a href="dashboard.php" class="link">← Dashboard</a>
    <a href="logout.php" class="link">Logout</a>
  </div>

  <!-- ======= Conversation ======= -->
  <div class="conversation glass">
    <?php if(!$chat_with): ?>
      <p class="muted">Seleziona un utente per iniziare.</p>
    <?php elseif($request_status!=='approved'): ?>
      <?php if($request_status==='pending'): ?>
        <p class="muted">Richiesta in attesa di approvazione.</p>
      <?php elseif($request_status==='rejected'): ?>
        <p class="muted">Richiesta rifiutata.</p>
      <?php else: ?>
        <p class="muted">Invia una richiesta di chat per iniziare.</p>
        <form method="post" action="send_request.php">
          <input type="hidden" name="to" value="<?= $chat_with ?>">
          <button class="btn" type="submit">Invia richiesta chat</button>
        </form>
      <?php endif; ?>
    <?php else: ?>
<div class="chat-head">
  <div class="head-left">
    <div class="bigavatar">
      <?php 
        $chat_img = !empty($chat_user_image) 
                     ? $chat_user_image 
                     : "uploads/profile_default.jpg"
      ?>
      <img src="<?= htmlspecialchars($chat_img) ?>" 
           alt="<?= htmlspecialchars($chat_username) ?>" 
           style="width:50px; height:50px; border-radius:50%; object-fit:cover;">
    </div>
    <div>
      <div class="name"><?= htmlspecialchars($chat_username) ?></div>
      <div class="sub">Chat privata</div>
    </div>
  </div>
</div>


      <div id="chat-box" class="chat-box"></div>

  <form id="chat-form" class="composer">
    <button type="button" id="emoji-btn" class="btn">😊</button>
    <input type="text" id="msg" name="message" placeholder="Scrivi..." required>
    <button class="btn" type="submit">Invia</button>

  <!-- Emoji picker -->
  <!-- Contenitore emoji -->
<!-- Emoji picker sotto l'input -->
<div class="emoji-picker" id="emoji-picker">
  <span class="emoji">😀</span><span class="emoji">😃</span><span class="emoji">😄</span><span class="emoji">😁</span>
  <span class="emoji">😆</span><span class="emoji">😅</span><span class="emoji">😂</span><span class="emoji">🤣</span>
  <span class="emoji">😊</span><span class="emoji">😇</span><span class="emoji">🙂</span><span class="emoji">🙃</span>
  <span class="emoji">😉</span><span class="emoji">😌</span><span class="emoji">😍</span><span class="emoji">🥰</span>
  <span class="emoji">😘</span><span class="emoji">😗</span><span class="emoji">😙</span><span class="emoji">😚</span>
  <span class="emoji">😋</span><span class="emoji">😛</span><span class="emoji">😝</span><span class="emoji">😜</span>
  <span class="emoji">🤪</span>
  <span class="emoji">😀</span><span class="emoji">😃</span><span class="emoji">😄</span><span class="emoji">😁</span>
<span class="emoji">😆</span><span class="emoji">😅</span><span class="emoji">😂</span><span class="emoji">🤣</span>
<span class="emoji">😊</span><span class="emoji">😇</span><span class="emoji">🙂</span><span class="emoji">🙃</span>
<span class="emoji">😉</span><span class="emoji">😌</span><span class="emoji">😍</span><span class="emoji">🥰</span>
<span class="emoji">😘</span><span class="emoji">😗</span><span class="emoji">😙</span><span class="emoji">😚</span>
<span class="emoji">😋</span><span class="emoji">😛</span><span class="emoji">😝</span><span class="emoji">😜</span>
<span class="emoji">🤪</span><span class="emoji">🤨</span><span class="emoji">🧐</span><span class="emoji">🤓</span>
<span class="emoji">😎</span><span class="emoji">🥳</span><span class="emoji">😏</span><span class="emoji">😒</span>
<span class="emoji">😞</span><span class="emoji">😔</span><span class="emoji">😟</span><span class="emoji">😕</span>
<span class="emoji">🙁</span><span class="emoji">☹️</span>
<span class="emoji">🤗</span><span class="emoji">🤔</span><span class="emoji">🤫</span><span class="emoji">🤭</span>
<span class="emoji">🤯</span><span class="emoji">😳</span><span class="emoji">🥺</span><span class="emoji">😢</span>
<span class="emoji">😭</span><span class="emoji">😤</span><span class="emoji">😠</span><span class="emoji">😡</span>
<span class="emoji">🤬</span><span class="emoji">🤐</span><span class="emoji">🤧</span><span class="emoji">😷</span>
<span class="emoji">🤒</span><span class="emoji">🤕</span><span class="emoji">🤑</span><span class="emoji">🤠</span>
<span class="emoji">😈</span><span class="emoji">👿</span><span class="emoji">👹</span><span class="emoji">👺</span>
<span class="emoji">💀</span><span class="emoji">☠️</span><span class="emoji">👻</span><span class="emoji">👽</span>
<span class="emoji">🤖</span><span class="emoji">🎅</span>
<span class="emoji">⚽</span><span class="emoji">🏀</span><span class="emoji">🏈</span><span class="emoji">⚾</span>
<span class="emoji">🎾</span><span class="emoji">🏐</span><span class="emoji">🏉</span><span class="emoji">🎱</span>
<span class="emoji">🥊</span><span class="emoji">🥋</span><span class="emoji">🎯</span><span class="emoji">🎳</span>
<span class="emoji">🎮</span><span class="emoji">🕹️</span><span class="emoji">🎲</span><span class="emoji">♟️</span>
<span class="emoji">🎼</span><span class="emoji">🎤</span><span class="emoji">🎧</span><span class="emoji">🎷</span>
<span class="emoji">🚗</span><span class="emoji">🚕</span><span class="emoji">🚙</span><span class="emoji">🚌</span>
<span class="emoji">🚎</span><span class="emoji">🏎️</span><span class="emoji">🚓</span><span class="emoji">🚑</span>
<span class="emoji">🚒</span><span class="emoji">🚐</span><span class="emoji">🛻</span><span class="emoji">🚚</span>
<span class="emoji">🛵</span><span class="emoji">🏍️</span><span class="emoji">🛺</span><span class="emoji">✈️</span>
<span class="emoji">🚀</span><span class="emoji">🛸</span><span class="emoji">🛳️</span><span class="emoji">⛴️</span>
<span class="emoji">🍏</span><span class="emoji">🍎</span><span class="emoji">🍐</span><span class="emoji">🍊</span>
<span class="emoji">🍋</span><span class="emoji">🍌</span><span class="emoji">🍉</span><span class="emoji">🍇</span>
<span class="emoji">🍓</span><span class="emoji">🫐</span><span class="emoji">🥝</span><span class="emoji">🍒</span>
<span class="emoji">🍑</span><span class="emoji">🥭</span><span class="emoji">🍍</span><span class="emoji">🥥</span>
<span class="emoji">🥑</span><span class="emoji">🥦</span><span class="emoji">🥬</span><span class="emoji">🥒</span>
<span class="emoji">🐶</span><span class="emoji">🐱</span><span class="emoji">🐭</span><span class="emoji">🐹</span>
<span class="emoji">🐰</span><span class="emoji">🦊</span><span class="emoji">🐻</span><span class="emoji">🐼</span>
<span class="emoji">🐨</span><span class="emoji">🐯</span><span class="emoji">🦁</span><span class="emoji">🐮</span>
<span class="emoji">🐷</span><span class="emoji">🐸</span><span class="emoji">🐵</span><span class="emoji">🐔</span>
<span class="emoji">🐧</span><span class="emoji">🐦</span><span class="emoji">🐤</span><span class="emoji">🦄</span>

</div>


</form>

    <?php endif; ?>
  </div>

  <!-- ======= Right Details (vuoto per ora) ======= -->

 <aside class="details glass">
  <h3 class="title">Dettagli</h3>
  <?php if($chat_with && $request_status==='approved'): ?>
    <div class="card">
      <div class="head-left">
        <div class="bigavatar">
          <?php 
            $chat_img = !empty($chat_user_image) 
                         ? $chat_user_image 
                         : "uploads/profile_default.jpg"
          ?>
          <img src="<?= htmlspecialchars($chat_img) ?>" 
               alt="<?= htmlspecialchars($chat_username) ?>" 
               style="width:50px; height:50px; border-radius:50%; object-fit:cover;">
        </div>
        <div>
          <div class="name"><?= htmlspecialchars($chat_username) ?></div>
          <div class="sub">Partner di chat</div>
        </div>
      </div>
    </div>

<form method="post" action="block_user.php" style="display:inline;">
    <input type="hidden" name="blocked_id" value="<?= $chat_with ?>">
    <input type="hidden" name="action" value="<?= $blocked ? 'unblock' : 'block' ?>">
    <button class="pill" type="submit">
        <?= $blocked ? 'Sblocca' : 'Blocca' ?>
    </button>
</form>


<form class="report-chat" method="post" action="report_chat.php">
  <input type="hidden" name="reported_id" value="<?= $chat_with ?>">
  <input type="text" name="message" placeholder="Motivo segnalazione" required>
  <button class="pill" type="submit">Segnala</button>
</form>



  <?php else: ?>
    <div class="card">
      <div class="muted">Seleziona una chat per vedere i dettagli.</div>
    </div>
  <?php endif; ?>
</aside>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const chatBox = document.getElementById('chat-box');
  const chatForm = document.getElementById('chat-form');
  const msgInput = document.getElementById('msg');
  const to = <?= $chat_with ?: 'null' ?>;

  function loadMessages() {
    if (!to) return;
    fetch('get_messages.php?with=' + to)
      .then(r => r.json())
      .then(data => {
        chatBox.innerHTML = '';
        data.forEach(m => {
          const d = document.createElement('div');
          d.className = 'bubble ' + (m.sender_id === <?= $my_id ?> ? 'sent' : 'recv');
          d.textContent = m.message;
          chatBox.appendChild(d);
        });
        chatBox.scrollTop = chatBox.scrollHeight;
      });
  }

  if(to){
    loadMessages();
    chatForm.addEventListener('submit', e => {
      e.preventDefault();
      const text = msgInput.value.trim();
      if(!text) return;
      fetch('send_message.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`to=${to}&message=${encodeURIComponent(text)}`
      }).then(r=>r.json()).then(resp=>{
        if(resp.status==='ok'){
          msgInput.value='';
          loadMessages();
        }
      });
    });

  }
});

function togglePeople(){ document.body.classList.toggle('drawer-open-left'); }
function toggleDetails(){ document.body.classList.toggle('drawer-open-right'); }
const emojiBtn = document.getElementById('emoji-btn');
const emojiPicker = document.getElementById('emoji-picker');
const msgInput = document.getElementById('msg');

// Mostra/nascondi emoji picker
emojiBtn.addEventListener('click', e => {
  e.stopPropagation();
  emojiPicker.style.display = emojiPicker.style.display==='grid' ? 'none' : 'grid';
});

// Inserisci emoji nel campo
emojiPicker.querySelectorAll('.emoji').forEach(span=>{
  span.addEventListener('click', ()=>{
    msgInput.value += span.textContent;
    msgInput.focus();
  });
});

// Chiudi emoji picker se clicchi fuori
document.addEventListener('click', ()=>{ emojiPicker.style.display='none'; });
emojiPicker.addEventListener('click', e=> e.stopPropagation());

</script>
</body>
</html>
