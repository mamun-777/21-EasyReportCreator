<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="color-scheme" content="light" />
  <title>EasyReportCreator — Report app</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="assets/report.css?v=20260902a" />
</head>
<body>
  <header class="top">
    <a class="logo" href="../index.php">EasyReport<span>Creator</span></a>
    <p class="tag">STRATO web app · upload ProcessPower.dcf</p>
  </header>

  <main class="shell">
    <aside class="side panel">
      <div class="panel-bar"><span class="key">Project</span></div>
      <div class="side-body">
        <p class="muted" id="project-meta">Upload ProcessPower.dcf from your Plant 3D project folder. Plant 3D does not need to be running.</p>
        <label class="file-btn btn primary">
          <input id="dcf-file" type="file" accept=".dcf" hidden />
          <span id="btn-upload">Upload .dcf</span>
        </label>
        <div id="upload-status" class="upload-status" hidden></div>
        <h3 class="side-label">Lists</h3>
        <nav id="template-nav" class="nav-list"></nav>
      </div>
    </aside>

    <section class="main">
      <header class="main-head">
        <div>
          <p class="eyebrow">Report preview</p>
          <h1 id="report-title">Select a list</h1>
          <p class="subtitle" id="report-sub">Upload a .dcf file, then choose a list.</p>
        </div>
        <div class="actions">
          <button id="btn-export" class="btn primary" type="button" disabled>Export Excel</button>
        </div>
      </header>
      <section class="stats" id="stats"></section>
      <section class="sheet panel">
        <div class="toolbar">
          <input id="search" type="search" placeholder="Filter rows…" />
          <span class="count" id="row-count"></span>
        </div>
        <div class="grid-wrap">
          <table class="grid" id="grid">
            <thead></thead>
            <tbody></tbody>
          </table>
        </div>
      </section>
    </section>
  </main>

  <dialog id="notice-dialog">
    <form method="dialog" class="dialog panel">
      <div class="panel-bar"><span class="key" id="notice-title">Notice</span></div>
      <div class="dialog-body">
        <p id="notice-message"></p>
        <menu><button class="btn primary" value="default">OK</button></menu>
      </div>
    </form>
  </dialog>

  <script src="assets/report.js?v=20260902a"></script>
</body>
</html>
