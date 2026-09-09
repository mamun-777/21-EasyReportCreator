<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/inc/bootstrap.php';
$user = ErcAuth::currentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}
$companyName = htmlspecialchars($user['company_name'], ENT_QUOTES, 'UTF-8');
$displayName = htmlspecialchars($user['display_name'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="color-scheme" content="light" />
  <title>EasyReportCreator — Report app</title>
  <link rel="stylesheet" href="../assets/fonts.css?v=20260908c" />
  <link rel="stylesheet" href="assets/report.css?v=20260908c" />
</head>
<body>
  <header class="top title-bar">
    <div class="title-bar-brand">
      <a class="logo" href="../index.php"><span class="mark" aria-hidden="true"></span>EasyReport<span class="dim">Creator</span></a>
      <span class="company-chip" id="company-chip" title="Signed-in company"><?= $companyName ?></span>
    </div>
    <div class="title-bar-actions" id="title-bar-actions">
      <button id="btn-header" class="btn ghost" type="button" disabled>Header setup</button>
      <label class="file-btn btn ghost disabled" id="logo-label">
        <input id="logo-file" type="file" accept=".png,.jpg,.jpeg" hidden disabled />
        <span id="btn-logo">Upload logo</span>
      </label>
      <button id="btn-save" class="btn ghost" type="button" disabled>Save profile</button>
      <button id="btn-export" class="btn primary" type="button" disabled>Export Excel</button>
    </div>
    <div class="title-bar-user">
      <span class="user-label" id="user-label"><?= $displayName ?></span>
      <button id="btn-logout" class="btn ghost" type="button">Log out</button>
    </div>
  </header>

  <main class="shell">
    <aside class="side panel">
      <div class="panel-bar"><span class="key">Project</span></div>
      <div class="side-body">
        <div class="project-card" id="project-card">
          <p class="muted" id="project-meta">No project open. Upload ProcessPower.dcf from your Plant 3D project folder. Plant 3D does not need to be running.</p>
        </div>
        <div class="upload-zone" id="upload-zone">
          <p class="upload-zone-title">Drop ProcessPower.dcf here</p>
          <p class="upload-zone-hint muted">or choose the file from your project folder</p>
          <label class="file-btn btn primary" id="upload-label">
            <input id="dcf-file" type="file" accept=".dcf" hidden />
            <span id="btn-upload">Upload .dcf</span>
          </label>
        </div>
        <div id="upload-status" class="upload-status" hidden aria-live="polite" aria-busy="false">
          <div class="upload-status-head">
            <span id="upload-status-label">Uploading…</span>
            <span id="upload-status-meta" class="muted"></span>
          </div>
          <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-fill" id="upload-progress-fill"></div>
          </div>
        </div>

        <div id="notice" class="notice hidden" role="status" aria-live="polite">
          <div class="notice-body">
            <strong id="notice-title"></strong>
            <p id="notice-message"></p>
          </div>
          <button id="notice-close" type="button" class="notice-close" aria-label="Dismiss">×</button>
        </div>

        <h3 class="side-label">Lists</h3>
        <nav id="template-nav" class="nav-list"></nav>
        <p class="side-foot muted">Company logo, header fields, and export options apply to every list. Columns stay per list.</p>
      </div>
    </aside>

    <section class="main">
      <header class="main-head">
        <div class="topbar-intro">
          <p class="eyebrow">Report preview</p>
          <h1 id="report-title">Select a list</h1>
          <p class="subtitle" id="report-sub">Upload a .dcf file, then choose a list.</p>
        </div>
        <div class="actions list-actions">
          <button id="btn-columns" class="btn ghost" type="button" disabled>Columns</button>
        </div>
      </header>

      <section class="sheet panel">
        <div class="sheet-head" id="sheet-head">
          <div class="titleblock">
            <div class="tb-logo" id="tb-logo">
              <img id="tb-logo-img" alt="Company logo" hidden />
              <span id="tb-logo-placeholder">LOGO</span>
            </div>
            <div class="tb-meta">
              <h2 id="tb-title">—</h2>
              <dl id="tb-fields"></dl>
            </div>
            <table class="rev-table" id="rev-table">
              <thead><tr><th>Rev</th><th>Date</th><th>Description</th><th>Drawn</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="toolbar">
            <input id="search" type="search" placeholder="Filter rows…" />
            <span class="count" id="row-count"></span>
          </div>
        </div>
        <div class="grid-wrap" id="grid-wrap">
          <div class="empty-state" id="empty-state">
            <p class="empty-title">Start with your project database</p>
            <p class="empty-copy muted">Upload ProcessPower.dcf, pick a list, adjust columns if needed, then export Excel.</p>
            <button type="button" class="btn primary" id="empty-upload-btn">Upload .dcf</button>
          </div>
          <table class="grid" id="grid" hidden><thead></thead><tbody></tbody></table>
        </div>
      </section>
    </section>
  </main>

  <dialog id="header-dialog">
    <form method="dialog" class="dialog panel wide">
      <div class="panel-bar"><span class="key">Header setup</span><span class="file">All lists</span></div>
      <div class="dialog-body">
        <p class="muted">These company defaults apply to every list. List title and document number can still differ per sheet.</p>
        <div class="header-grid">
          <section>
            <h4>Title block fields</h4>
            <div id="header-field-groups" class="field-groups"></div>
          </section>
          <section>
            <h4>Company &amp; issue defaults</h4>
            <label class="field-row"><span>Company</span><input id="hdr-company" type="text" /></label>
            <label class="field-row"><span>Default title</span><input id="hdr-title" type="text" placeholder="Optional — leave blank to use each list name" /></label>
            <label class="field-row"><span>Revision</span><input id="hdr-rev" type="text" /></label>
            <label class="field-row"><span>Date</span><input id="hdr-date" type="date" /></label>
            <label class="field-row"><span>Document no.</span><input id="hdr-doc" type="text" placeholder="Optional list override" /></label>
            <h4>Revision table</h4>
            <div class="rev-editor-wrap">
              <table class="grid rev-editor" id="rev-editor">
                <thead><tr><th>Rev</th><th>Date</th><th>Description</th><th>Drawn</th></tr></thead>
                <tbody></tbody>
              </table>
            </div>
            <button id="hdr-add-rev" type="button" class="btn ghost">Add row</button>
          </section>
        </div>
        <menu>
          <button value="cancel" class="btn ghost">Cancel</button>
          <button id="header-apply" class="btn primary" type="button">Apply to all lists</button>
        </menu>
      </div>
    </form>
  </dialog>

  <dialog id="columns-dialog">
    <form method="dialog" class="dialog panel">
      <div class="panel-bar"><span class="key">Columns</span><span class="file">This list only</span></div>
      <div class="dialog-body">
        <p class="muted">Choose which columns appear in the preview and Excel export for this list.</p>
        <div id="column-checks" class="field-groups column-checks"></div>
        <menu>
          <button value="cancel" class="btn ghost">Cancel</button>
          <button id="columns-apply" class="btn primary" type="button">Apply to list</button>
        </menu>
      </div>
    </form>
  </dialog>

  <dialog id="save-dialog">
    <form method="dialog" class="dialog panel">
      <div class="panel-bar"><span class="key">Save profile</span><span class="file">Company standard</span></div>
      <div class="dialog-body">
        <p class="muted" id="save-summary">Save company header defaults for all lists, and optionally lock this list’s columns as your company standard.</p>
        <div class="save-options">
          <label class="field-check"><input type="checkbox" id="save-company-header" checked /><span>Save header / revision defaults for all lists</span></label>
          <label class="field-check"><input type="radio" name="save-mode" value="standard" checked /><span>Also save this list as company standard <small id="save-standard-id"></small></span></label>
          <label class="field-check"><input type="radio" name="save-mode" value="overwrite" /><span>Overwrite existing list standard</span></label>
          <label class="field-check"><input type="radio" name="save-mode" value="header_only" /><span>Header defaults only (do not change list columns)</span></label>
          <label class="field-check"><input type="radio" name="save-mode" value="reset" /><span>Reset this list to factory default</span></label>
        </div>
        <menu>
          <button value="cancel" class="btn ghost">Cancel</button>
          <button id="save-confirm" class="btn primary" type="button">Save</button>
        </menu>
      </div>
    </form>
  </dialog>

  <dialog id="export-dialog">
    <form method="dialog" class="dialog panel">
      <div class="panel-bar"><span class="key">Export Excel</span><span class="file">Output options</span></div>
      <div class="dialog-body">
        <p class="muted">Choose what to include. Options are remembered for your company so you need not re-select them every time.</p>
        <div class="save-options">
          <label class="field-check"><input type="checkbox" id="exp-logo" checked /><span>Include company logo</span></label>
          <label class="field-check"><input type="checkbox" id="exp-revision" checked /><span>Include revision history</span></label>
          <label class="field-check"><input type="checkbox" id="exp-pnpid" checked /><span>Include PnPID column</span></label>
          <label class="field-check"><input type="checkbox" id="exp-remember" checked /><span>Remember these options for next export</span></label>
        </div>
        <menu>
          <button value="cancel" class="btn ghost">Cancel</button>
          <button id="export-confirm" class="btn primary" type="button">Export current list</button>
        </menu>
      </div>
    </form>
  </dialog>

  <dialog id="notice-dialog">
    <form method="dialog" class="dialog panel">
      <div class="panel-bar"><span class="key" id="dialog-notice-title">Notice</span></div>
      <div class="dialog-body">
        <p id="dialog-notice-message"></p>
        <menu><button class="btn primary" value="default">OK</button></menu>
      </div>
    </form>
  </dialog>

  <script src="assets/report.js?v=20260908c"></script>
</body>
</html>
