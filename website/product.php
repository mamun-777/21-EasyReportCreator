<?php
declare(strict_types=1);
$page = 'product';
$title = 'Product — EasyReportCreator | easyreportcreator.com';
$description = 'What EasyReportCreator does: open a Plant 3D project, fill the header from Project Details, pick class properties as columns, save a template, and issue Excel.';
require __DIR__ . '/inc/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <div class="eyebrow">Product</div>
    <h1>Open the project. Issue the list.</h1>
    <p class="sub">Everything EasyReportCreator does to turn ProcessPower.dcf into company-standard Excel lists — without AutoCAD Report Creator, and without writing back to the live database.</p>
  </div>
</section>

<section class="tight">
  <div class="wrap">
    <div class="features reveal">
      <div class="feature"><div class="body">
        <span class="key">OPEN</span>
        <h3>Plant 3D project folder</h3>
        <p>Upload ProcessPower.dcf in the browser. It reads Project Details and engineering tables. AutoCAD does not need to be running for listing.</p>
      </div></div>
      <div class="feature"><div class="body">
        <span class="key">HEADER</span>
        <h3>Project Details + custom properties</h3>
        <p>Standard name, description and number, plus every user-defined category (S88 on the Rhenen sample) can appear in the title block with your logo and revision rows.</p>
      </div></div>
      <div class="feature"><div class="body">
        <span class="key">COLUMNS</span>
        <h3>Class property catalogue</h3>
        <p>Select any standard or user-defined property available on the list’s class in the sample project. The selection is not a hard-coded handful of fields.</p>
      </div></div>
      <div class="feature"><div class="body">
        <span class="key">TEMPLATES</span>
        <h3>Company standards</h3>
        <p>Save column set, sort, header fields and revision table. Reload the standard the next time you issue the same list type.</p>
      </div></div>
      <div class="feature"><div class="body">
        <span class="key">LISTS</span>
        <h3>The lists you already produce</h3>
        <p>Valve, control valve, equipment, line, line summary, instrument, drawing, and Componentenlijst — proven on MN-P-RHN-PID-0001.</p>
      </div></div>
      <div class="feature"><div class="body">
        <span class="key">EXCEL</span>
        <h3>One-way issued workbook</h3>
        <p>English Excel with logo, title block and revision table. Version 1 does not import changes back into the live DCF.</p>
      </div></div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Workflow</div>
      <h2>Five steps, not a Report Creator session.</h2>
      <p>The DCF stays where Plant 3D left it. Excel stays the issued document.</p>
    </div>
    <div class="steps reveal">
      <div class="step">
        <div class="step-num">01</div>
        <h3>Open the folder</h3>
        <p>Select the Plant 3D project. The app reads identity, drawings and counts from the database.</p>
      </div>
      <div class="step">
        <div class="step-num">02</div>
        <h3>Choose the list</h3>
        <p>Valve, equipment, line, instrument, drawing, Componentenlijst — or a saved company template.</p>
      </div>
      <div class="step">
        <div class="step-num">03</div>
        <h3>Pick columns &amp; header</h3>
        <p>Tick the properties that belong on this issue. Confirm logo, Project Details fields and revision rows.</p>
      </div>
    </div>
  </div>
</section>

<section class="tight">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Requirements</div>
      <h2>What the app needs on the PC.</h2>
    </div>
    <div class="spec-table reveal">
      <div class="spec-row"><span class="k">OS</span><span class="v">Windows 10 / 11 — the machine that can see the Plant 3D project folder</span></div>
      <div class="spec-row"><span class="k">Project</span><span class="v">AutoCAD Plant 3D project with ProcessPower.dcf (SQLite). Sample: MN-P-RHN-PID-0001</span></div>
      <div class="spec-row"><span class="k">AutoCAD licence</span><span class="v">Not required for listing and Excel export</span></div>
      <div class="spec-row"><span class="k">Public site</span><span class="v">easyreportcreator.com on STRATO — product pages + report app; upload ProcessPower.dcf</span></div>
      <div class="spec-row"><span class="k">Language</span><span class="v">English issued lists (Version 1)</span></div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
