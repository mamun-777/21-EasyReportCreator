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
  <title>Create account — EasyReportCreator</title>
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
      <h1>Create account</h1>
      <p class="auth-lead">Register your company so logo, headers, and list standards stay private to your organisation.</p>

      <form id="register-form" class="auth-form" autocomplete="on">
        <label class="auth-field">
          <span>Company name</span>
          <input name="company_name" type="text" required minlength="2" placeholder="Your company" autocomplete="organization" />
        </label>
        <label class="auth-field">
          <span>Your name</span>
          <input name="display_name" type="text" autocomplete="name" placeholder="Optional" />
        </label>
        <label class="auth-field">
          <span>Email</span>
          <input name="email" type="email" required autocomplete="username" placeholder="you@company.com" />
        </label>
        <label class="auth-field">
          <span>Password</span>
          <input name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters" />
        </label>
        <button class="btn block" type="submit">Create account</button>
      </form>

      <div id="auth-notice" class="auth-notice" hidden></div>

      <p class="auth-switch">
        Already have an account?
        <a href="login.php">Sign in</a>
      </p>
    </section>
  </main>

  <script>
    const notice = (message) => {
      const el = document.getElementById("auth-notice");
      el.hidden = false;
      el.textContent = message;
    };
    document.getElementById("register-form").addEventListener("submit", async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const btn = e.target.querySelector('button[type="submit"]');
      btn.disabled = true;
      try {
        const res = await fetch("api.php?action=register", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            company_name: fd.get("company_name"),
            display_name: fd.get("display_name"),
            email: fd.get("email"),
            password: fd.get("password"),
          }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.ok === false) throw new Error(data.detail || "Could not create account");
        location.href = "index.php";
      } catch (err) {
        notice(err.message);
        btn.disabled = false;
      }
    });
  </script>
</body>
</html>
