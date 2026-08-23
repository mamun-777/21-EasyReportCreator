<?php
declare(strict_types=1);
$page = 'home';
$title = 'EasyReportCreator — Plant 3D lists without Report Creator';
$description = 'Open a Plant 3D project, pick the properties you need, and issue valve, equipment, line and component lists to Excel — with a company header and revision table.';
require __DIR__ . '/inc/header.php';
?>

<section class="hero">
  <div class="wrap hero-grid">
    <div>
      <div class="eyebrow">For AutoCAD Plant 3D</div>
      <h1>Every list.<br>Every property.<br><span class="accent">One export.</span></h1>
      <p class="sub">EasyReportCreator reads the Plant 3D project database, lets you choose the columns that belong on the issued list, and writes English Excel with your logo, title block and revision table — without opening Report Creator.</p>
      <div class="hero-ctas">
        <a href="download.php" class="btn">Run the app</a>
        <a href="product.php" class="btn ghost">See how it works</a>
      </div>
      <div class="compat-row">
        <span><i class="dot"></i>ProcessPower.dcf</span>
        <span><i class="dot"></i>Excel issue (one-way)</span>
        <span><i class="dot"></i>Company templates</span>
      </div>
    </div>

    <div class="panel reveal">
      <div class="panel-bar">
        <span class="file">MN-P-RHN-PID-0001 — Valve List</span>
        <div class="panel-dots"><i></i><i></i><i></i></div>
      </div>
      <div class="prop-row">
        <span class="key">Tag</span>
        <span class="val val new">HV-101</span>
        <span class="tag">Listed</span>
      </div>
      <div class="prop-row">
        <span class="key">Omschrijving</span>
        <span class="val val new">Hand valve DN80</span>
        <span class="tag">Listed</span>
      </div>
      <div class="prop-row updating">
        <span class="key">Header</span>
        <span class="val"><span class="val old">Manual fields</span><span class="val new">S88 Projectcode MNPRHN</span></span>
        <span class="tag">Filled</span>
      </div>
      <div class="prop-row">
        <span class="key">Columns</span>
        <span class="val val new">Tag, Size, Medium, LineNumber</span>
        <span class="tag">Template</span>
      </div>
      <div class="prop-row">
        <span class="key">Export</span>
        <span class="val val new">MN-P-RHN-PID-0001-AL.xlsx</span>
        <span class="tag">Issued</span>
      </div>
      <div class="panel-footer">
        <span>Sample project · no AutoCAD licence for listing</span>
        <span class="n">Ready to issue</span>
      </div>
    </div>
  </div>
</section>

<div class="trust">
  <div class="wrap">
    <span>Built for engineering teams issuing P&amp;ID lists from Plant 3D</span>
    <span>NL / EU — <?= h(SITE['domain']) ?></span>
  </div>
</div>

<section>
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Why it exists</div>
      <h2>Report Creator can do this. It should not feel like this.</h2>
      <p>Valve lists, equipment lists, line lists and a Componentenlijst are still produced from the same DCF — but the interface, the header, and the column set should belong to your company standard, not to a dialog maze.</p>
    </div>
    <div class="features reveal">
      <div class="feature"><div class="body">
        <span class="key">PROJECT HEADER</span>
        <h3>Title block from Project Details</h3>
        <p>Standard fields and user-defined properties (S88 and any other category) fill the issued header, together with your logo and a revision table.</p>
      </div></div>
      <div class="feature"><div class="body">
        <span class="key">COLUMNS</span>
        <h3>Pick any class property</h3>
        <p>Browse the P&amp;ID class properties used on the project and choose which ones appear as columns. Save the set as a reusable template.</p>
      </div></div>
      <div class="feature"><div class="body">
        <span class="key">EXCEL</span>
        <h3>Issue in one direction</h3>
        <p>Export English Excel ready to send. Version 1 does not write back into the live DCF — the original project database stays untouched.</p>
      </div></div>
    </div>
    <p class="after-features"><a href="product.php" class="btn ghost">See all features →</a></p>
  </div>
</section>

<section class="proof">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">In practice</div>
      <h2>The Rhenen sample, issued correctly.</h2>
      <p>Acceptance is against Plant 3D project MN-P-RHN-PID-0001 (Productiebedrijf Rhenen). Open the folder, choose a list, export.</p>
    </div>
    <div class="diff-card reveal">
      <div class="diff-row"><span class="k">Project name</span><span><span class="v-new">MN-P-RHN-PID-0001</span></span></div>
      <div class="diff-row"><span class="k">S88.Projectcode</span><span><span class="v-new">MNPRHN</span></span></div>
      <div class="diff-row"><span class="k">S88.Locatie</span><span><span class="v-new">Rhenen</span></span></div>
      <div class="diff-row"><span class="k">List</span><span><span class="v-old">Report Creator rcfx</span><span class="v-new">EasyReportCreator template</span></span></div>
    </div>
    <div class="diff-meta">
      <div><b>8</b>core list types</div>
      <div><b>1</b>Excel issue</div>
      <div><b>0</b>writes to live DCF</div>
    </div>
  </div>
</section>

<section id="download">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Run it where the project lives</div>
      <h2>The app runs next to Plant 3D, not in the browser cloud.</h2>
      <p>Start the local web app on a PC that can see the project folder. The public site is only the product home — the lists come from your DCF.</p>
    </div>
    <div class="download-card reveal">
      <div class="download-card-main">
        <div class="eyebrow">Local web app</div>
        <h2>EasyReportCreator for Windows</h2>
        <p class="download-meta">
          <span>Version <?= h(SITE['product_version']) ?></span>
          <span>| Python runtime bundled in the run script</span>
          <span>| Plant 3D project folder</span>
        </p>
        <div class="download-actions">
          <a class="btn" href="download.php">Install instructions</a>
          <a href="product.php" class="btn ghost">Requirements</a>
        </div>
      </div>
      <div class="download-card-side">
        <div class="key">AFTER DOWNLOAD</div>
        <ul>
          <li>Unzip on the PC that can see the project</li>
          <li>Run <code>run.bat</code></li>
          <li>Open the project folder (sample: MN-P-RHN-PID-0001)</li>
          <li>Choose a list, pick columns, export Excel</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="cta reveal">
      <div>
        <h2>Ready to stop fighting Report Creator?</h2>
        <p>Version 1 is a delivered web app for your Plant 3D environment — English issued lists, company templates, STRATO product site included.</p>
      </div>
      <a href="contact.php" class="btn">Talk to us</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
