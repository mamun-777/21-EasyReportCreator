<?php
declare(strict_types=1);
$page = 'download';
$title = 'Download — EasyReportCreator | easyreportcreator.com';
$description = 'Run EasyReportCreator on the PC that can see your AutoCAD Plant 3D project folder. Local web app — not a cloud upload of your DCF.';
require __DIR__ . '/inc/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <div class="eyebrow">Download</div>
    <h1>Install on the PC that can see the project.</h1>
    <p class="sub">The lists are generated where ProcessPower.dcf already is. Unzip, run, open the project folder, export Excel.</p>
  </div>
</section>

<section class="tight">
  <div class="wrap">
    <div class="download-card reveal">
      <div class="download-card-main">
        <div class="eyebrow">Local web app</div>
        <h2>EasyReportCreator</h2>
        <p class="download-meta">
          <span>Version <?= h(SITE['product_version']) ?></span>
          <span>| Windows</span>
          <span>| Plant 3D project folder required</span>
        </p>
        <p class="download-note">Run the local app from the <code>app/</code> folder on a PC that can see your Plant 3D project (or the sample under <code>samples/MN-P-RHN-PID-0001</code>).</p>
        <div class="download-actions">
          <a class="btn" href="contact.php">Request the run package</a>
          <a href="product.php" class="btn ghost">Requirements</a>
        </div>
      </div>
      <div class="download-card-side">
        <div class="key">INSTALL</div>
        <ul>
          <li>Copy the app folder onto the workstation</li>
          <li>Run <code>run.bat</code> (creates a local URL)</li>
          <li>Open the Plant 3D project directory</li>
          <li>Choose a list → pick columns → Export Excel</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Why local</div>
      <h2>STRATO hosts the site. Your DCF stays with Plant 3D.</h2>
      <p>easyreportcreator.com is the public product site. Shared hosting cannot see a project folder on your LAN, and Version 1 does not upload engineering databases to the cloud.</p>
    </div>
    <div class="spec-table reveal">
      <div class="spec-row"><span class="k">This website</span><span class="v">PHP on STRATO — Home, Product, Pricing, Download, Contact</span></div>
      <div class="spec-row"><span class="k">The app</span><span class="v">Local Python web app — reads ProcessPower.dcf, writes Excel on that PC</span></div>
      <div class="spec-row"><span class="k">Sample</span><span class="v">MN-P-RHN-PID-0001 (Productiebedrijf Rhenen)</span></div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
