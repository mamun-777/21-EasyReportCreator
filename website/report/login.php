<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/bootstrap.php';
if (ErcAuth::currentUser()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="color-scheme" content="light" />
  <title>Sign in — EasyReportCreator</title>
  <link rel="stylesheet" href="../assets/fonts.css?v=20260908b" />
  <link rel="stylesheet" href="../assets/styles.css?v=20260908b" />
  <link rel="stylesheet" href="assets/auth.css?v=20260908b" />
</head>
<body class="auth-body">
  <header class="auth-top">
    <a class="logo" href="../index.php"><span class="mark"></span>EasyReport<span class="dim">Creator</span></a>
  </header>

  <main class="auth-main">
    <section class="auth-card">
      <p class="eyebrow">Report app</p>
      <h1>Sign in</h1>
      <p class="auth-lead">Access your company logo, header defaults, and saved list standards.</p>

      <form id="login-form" class="auth-form" autocomplete="on">
        <label class="auth-field">
          <span>Email</span>
          <input name="email" type="email" required autocomplete="username" placeholder="you@company.com" />
        </label>
        <label class="auth-field">
          <span>Password</span>
          <input name="password" type="password" required minlength="8" autocomplete="current-password" />
        </label>
        <button class="btn block" type="submit">Sign in</button>
      </form>

      <div id="auth-notice" class="auth-notice" hidden></div>

      <p class="auth-switch">
        Don’t have an account?
        <a href="register.php">Create account</a>
      </p>
    </section>
  </main>

  <script>
    const notice = (message) => {
      const el = document.getElementById("auth-notice");
      el.hidden = false;
      el.textContent = message;
    };
    document.getElementById("login-form").addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const btn = e.target.querySelector('button[type="submit"]');
      btn.disabled = true;
      try {
        const res = await fetch("api.php?action=login", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ email: fd.get("email"), password: fd.get("password") }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) throw new Error(data.detail || "Sign in failed");
        location.href = "index.php";
      } catch (err) {
        notice(err.message);
        btn.disabled = false;
      }
    });
  </script>
</body>
</html>
