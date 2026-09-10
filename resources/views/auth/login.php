<?php use LeadFlow\Core\View; ?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Login</title>
<style>
body{margin:0;font-family:-apple-system,sans-serif;background:#0f1419;color:#e6edf3;display:flex;align-items:center;justify-content:center;min-height:100vh}
.box{background:#1a2028;border:1px solid #262f3a;padding:32px;border-radius:8px;width:360px}
h1{margin:0 0 20px;font-size:22px}
input{width:100%;padding:10px;background:#0d1117;border:1px solid #262f3a;color:#e6edf3;border-radius:6px;margin-bottom:12px;font-family:inherit;font-size:14px}
button{width:100%;padding:10px;background:#4493f8;color:white;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer}
.err{color:#f85149;margin-bottom:12px;font-size:13px}
.muted{color:#8b949e;font-size:12px;margin-top:16px;text-align:center}
</style></head>
<body>
<form class="box" method="post" action="/login">
  <h1>⚡ LeadFlow</h1>
  <?php if (!empty($error)): ?><div class="err"><?= View::e($error) ?></div><?php endif; ?>
  <input type="email" name="email" placeholder="Email" required autofocus>
  <input type="password" name="password" placeholder="Password" required>
  <button type="submit">Sign In</button>
  <div class="muted">Default: admin@leadflow.local / admin1234</div>
</form>
</body></html>
