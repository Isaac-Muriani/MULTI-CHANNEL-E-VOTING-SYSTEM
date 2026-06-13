<?php
// =============================================================
//  ADMIN NAVBAR PARTIAL
//  evoting/admin/partials/navbar.php
// =============================================================
$current = basename($_SERVER['PHP_SELF']);
?>
<style>
    :root {
        --bg:      #0a0f1e;
        --surface: #111827;
        --border:  #1e2d45;
        --accent:  #f59e0b;
        --accent2: #ef4444;
        --text:    #e2e8f0;
        --muted:   #64748b;
        --success: #34d399;
        --error:   #f87171;
        --warning: #fbbf24;
        --info:    #60a5fa;
    }
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family:'Sora',sans-serif;
        background:var(--bg); color:var(--text);
        min-height:100vh;
    }
    nav {
        background:var(--surface);
        border-bottom:1px solid var(--border);
        padding:.875rem 2rem;
        display:flex; align-items:center; justify-content:space-between;
        position:sticky; top:0; z-index:100;
    }
    .nav-left { display:flex; align-items:center; gap:2rem; }
    .nav-logo { display:flex; align-items:center; gap:.6rem; font-weight:700; font-size:1rem; text-decoration:none; color:var(--text); }
    .nav-logo-icon {
        width:32px; height:32px;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        border-radius:8px;
        display:flex; align-items:center; justify-content:center; font-size:.9rem;
    }
    .nav-logo span { color:var(--accent); }
    .nav-links { display:flex; gap:.25rem; }
    .nav-link {
        padding:.4rem .9rem; border-radius:6px;
        font-size:.82rem; font-weight:600;
        text-decoration:none; color:var(--muted);
        transition:all .2s;
    }
    .nav-link:hover { color:var(--text); background:var(--border); }
    .nav-link.active { color:var(--accent); background:rgba(245,158,11,0.1); }
    .nav-right { display:flex; align-items:center; gap:1rem; }
    .nav-user { font-size:.82rem; color:var(--muted); }
    .nav-user strong { color:var(--text); }
    .nav-role {
        font-size:.7rem; font-weight:700;
        text-transform:uppercase; letter-spacing:.08em;
        padding:.15rem .6rem; border-radius:20px;
        background:rgba(245,158,11,0.1);
        border:1px solid rgba(245,158,11,0.3);
        color:var(--accent);
        font-family:'JetBrains Mono',monospace;
    }
    .logout-btn {
        background:transparent; border:1px solid var(--border);
        color:var(--muted); padding:.4rem .9rem; border-radius:6px;
        font-family:'Sora',sans-serif; font-size:.8rem;
        cursor:pointer; transition:all .2s; text-decoration:none;
    }
    .logout-btn:hover { border-color:var(--accent2); color:var(--accent2); }
    main { max-width:1100px; margin:0 auto; padding:2.5rem 1.5rem; }
    .page-title { font-size:1.5rem; font-weight:700; letter-spacing:-.03em; margin-bottom:.3rem; }
    .page-sub { color:var(--muted); font-size:.875rem; margin-bottom:2rem; }
    .card {
        background:var(--surface); border:1px solid var(--border);
        border-radius:12px; padding:1.5rem; margin-bottom:1.5rem;
    }
    .btn-primary {
        display:inline-block; padding:.65rem 1.4rem;
        background:linear-gradient(135deg,var(--accent),var(--accent2));
        color:#fff; border:none; border-radius:8px;
        font-family:'Sora',sans-serif; font-size:.875rem;
        font-weight:600; cursor:pointer; text-decoration:none;
        transition:opacity .2s;
    }
    .btn-primary:hover { opacity:.85; }
    .btn-sm {
        display:inline-block; padding:.35rem .85rem;
        border-radius:6px; font-size:.78rem; font-weight:600;
        cursor:pointer; text-decoration:none; border:none;
        font-family:'Sora',sans-serif; transition:all .2s;
    }
    .btn-success { background:rgba(52,211,153,0.15); color:var(--success); border:1px solid rgba(52,211,153,0.3); }
    .btn-danger  { background:rgba(248,113,113,0.15); color:var(--error);   border:1px solid rgba(248,113,113,0.3); }
    .btn-info    { background:rgba(96,165,250,0.15);  color:var(--info);    border:1px solid rgba(96,165,250,0.3); }
    .btn-warning { background:rgba(251,191,36,0.15);  color:var(--warning); border:1px solid rgba(251,191,36,0.3); }
    .btn-success:hover { background:rgba(52,211,153,0.25); }
    .btn-danger:hover  { background:rgba(248,113,113,0.25); }
    .btn-info:hover    { background:rgba(96,165,250,0.25); }
    .btn-warning:hover { background:rgba(251,191,36,0.25); }
    table { width:100%; border-collapse:collapse; font-size:.875rem; }
    th {
        text-align:left; padding:.75rem 1rem;
        font-size:.75rem; font-weight:700;
        text-transform:uppercase; letter-spacing:.08em;
        color:var(--muted); border-bottom:1px solid var(--border);
        font-family:'JetBrains Mono',monospace;
    }
    td { padding:.75rem 1rem; border-bottom:1px solid var(--border); vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    tr:hover td { background:rgba(255,255,255,0.02); }
    .badge {
        display:inline-block; padding:.2rem .7rem;
        border-radius:20px; font-size:.72rem; font-weight:600;
        text-transform:uppercase; letter-spacing:.06em;
    }
    .badge-active    { background:rgba(52,211,153,0.15); color:var(--success); border:1px solid rgba(52,211,153,0.3); }
    .badge-inactive  { background:rgba(100,116,139,0.15); color:var(--muted);  border:1px solid rgba(100,116,139,0.3); }
    .badge-suspended { background:rgba(248,113,113,0.15); color:var(--error);  border:1px solid rgba(248,113,113,0.3); }
    .badge-upcoming  { background:rgba(251,191,36,0.15);  color:var(--warning);border:1px solid rgba(251,191,36,0.3); }
    .badge-completed { background:rgba(96,165,250,0.15);  color:var(--info);   border:1px solid rgba(96,165,250,0.3); }
    .badge-cancelled { background:rgba(248,113,113,0.15); color:var(--error);  border:1px solid rgba(248,113,113,0.3); }
    .badge-pending   { background:rgba(251,191,36,0.15);  color:var(--warning);border:1px solid rgba(251,191,36,0.3); }
    .badge-approved  { background:rgba(52,211,153,0.15);  color:var(--success);border:1px solid rgba(52,211,153,0.3); }
    .badge-rejected  { background:rgba(248,113,113,0.15); color:var(--error);  border:1px solid rgba(248,113,113,0.3); }
    .alert { padding:.875rem 1rem; border-radius:8px; margin-bottom:1.5rem; font-size:.875rem; }
    .alert-success { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:var(--success); }
    .alert-error   { background:rgba(248,113,113,0.1); border:1px solid rgba(248,113,113,0.3); color:var(--error); }
    .form-group { margin-bottom:1.25rem; }
    .form-group label {
        display:block; font-size:.8rem; font-weight:600;
        color:var(--muted); text-transform:uppercase;
        letter-spacing:.06em; margin-bottom:.4rem;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width:100%; background:var(--bg);
        border:1px solid var(--border); border-radius:8px;
        padding:.75rem 1rem; color:var(--text);
        font-family:'Sora',sans-serif; font-size:.9rem;
        transition:border-color .2s; outline:none;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus { border-color:var(--accent); }
    .form-group textarea { resize:vertical; min-height:100px; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
    .section-label {
        font-size:.75rem; font-weight:700;
        text-transform:uppercase; letter-spacing:.1em;
        color:var(--accent); font-family:'JetBrains Mono',monospace;
        margin-bottom:1rem;
    }
    .empty {
        text-align:center; padding:3rem; color:var(--muted);
        border:1px dashed var(--border); border-radius:12px; font-size:.9rem;
    }
</style>

<link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<nav>
    <div class="nav-left">
        <a href="dashboard.php" class="nav-logo">
            <div class="nav-logo-icon">⚙️</div>
            E<span>Vote</span> Admin
        </a>
        <div class="nav-links">
            <a href="dashboard.php"  class="nav-link <?= $current==='dashboard.php'  ?'active':'' ?>">Dashboard</a>
            <a href="elections.php"  class="nav-link <?= $current==='elections.php'  ?'active':'' ?>">Elections</a>
            <a href="positions.php"  class="nav-link <?= $current==='positions.php'  ?'active':'' ?>">Positions</a>
            <a href="candidates.php" class="nav-link <?= $current==='candidates.php' ?'active':'' ?>">Candidates</a>
            <a href="voters.php"     class="nav-link <?= $current==='voters.php'     ?'active':'' ?>">Voters</a>
        </div>
    </div>
    <div class="nav-right">
        <span class="nav-role"><?= htmlspecialchars($admin_role) ?></span>
        <div class="nav-user">Hi, <strong><?= htmlspecialchars($admin_name) ?></strong></div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</nav>
