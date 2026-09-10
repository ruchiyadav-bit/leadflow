<?php /** @var string $content */ use LeadFlow\Core\View; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LeadFlow</title>
<style>
:root { --bg:#0f1419; --panel:#1a2028; --border:#262f3a; --text:#e6edf3; --muted:#8b949e; --accent:#4493f8; --ok:#3fb950; --warn:#d29922; --err:#f85149; }
* { box-sizing:border-box; }
body { margin:0; font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:var(--bg); color:var(--text); font-size:14px; }
a { color:var(--accent); text-decoration:none; }
a:hover { text-decoration:underline; }
.wrap { display:flex; min-height:100vh; }
.sidebar { width:220px; background:var(--panel); border-right:1px solid var(--border); padding:20px 0; }
.sidebar h1 { margin:0 20px 20px; font-size:18px; }
.sidebar nav a { display:block; padding:10px 20px; color:var(--text); }
.sidebar nav a:hover { background:#252d38; text-decoration:none; }
.main { flex:1; padding:24px 32px; overflow-x:auto; }
.topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
.card { background:var(--panel); border:1px solid var(--border); border-radius:8px; padding:20px; margin-bottom:16px; }
.grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
.stat { background:var(--panel); border:1px solid var(--border); border-radius:8px; padding:16px; }
.stat .lbl { color:var(--muted); font-size:12px; text-transform:uppercase; margin-bottom:6px; }
.stat .val { font-size:24px; font-weight:600; }
table { width:100%; border-collapse:collapse; }
th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--border); }
th { background:#151a21; font-weight:600; color:var(--muted); font-size:12px; text-transform:uppercase; }
tr:hover { background:#1f2731; }
.badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:11px; font-weight:600; }
.badge-ok  { background:rgba(63,185,80,.2); color:var(--ok); }
.badge-err { background:rgba(248,81,73,.2); color:var(--err); }
.badge-warn{ background:rgba(210,153,34,.2); color:var(--warn); }
.badge-info{ background:rgba(68,147,248,.2); color:var(--accent); }
.btn { background:var(--accent); color:white; padding:8px 16px; border-radius:6px; border:none; cursor:pointer; font-size:13px; font-weight:600; }
.btn-secondary { background:#30363d; }
.btn-danger { background:var(--err); }
input, select, textarea { background:#0d1117; border:1px solid var(--border); color:var(--text); padding:8px 12px; border-radius:6px; width:100%; font-family:inherit; font-size:13px; }
label { display:block; margin-bottom:4px; color:var(--muted); font-size:12px; text-transform:uppercase; }
.form-row { margin-bottom:16px; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
textarea { font-family: ui-monospace, Menlo, monospace; min-height:100px; }
pre { background:#0d1117; padding:12px; border-radius:6px; overflow-x:auto; font-size:12px; }
.muted { color:var(--muted); }
</style>
</head>
<body>
<div class="wrap">
<aside class="sidebar">
  <h1>⚡ LeadFlow</h1>
  <nav>
    <a href="/dashboard">Dashboard</a>
    <a href="/leads">Leads</a>
    <a href="/buyers">Buyers</a>
    <a href="/ping-trees">Ping Trees</a>
    <a href="/reports">Reports</a>
    <a href="/logout">Logout</a>
  </nav>
</aside>
<main class="main">
  <div class="topbar">
    <div></div>
    <div class="muted"><?= View::e($user['email'] ?? '') ?> <span class="badge badge-info"><?= View::e($user['role'] ?? '') ?></span></div>
  </div>
  <?= $content ?>
</main>
</div>
</body>
</html>
