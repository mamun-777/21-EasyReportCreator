<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
?>
<footer>
  <div class="wrap">
    <div class="f-brand">
      <a href="index.php" class="logo"><span class="mark"></span>EasyReport<span class="dim">Creator</span></a>
      <p>Web application for issuing Plant 3D lists to Excel from ProcessPower.dcf — built as a lighter replacement for AutoCAD Report Creator.</p>
    </div>
    <div class="f-col">
      <h4>Site</h4>
      <div class="f-links">
        <a href="product.php">Product</a>
        <a href="pricing.php">Pricing</a>
        <a href="download.php">Download</a>
        <a href="contact.php">Contact</a>
        <a href="terms.php">Terms &amp; delivery</a>
      </div>
    </div>
    <div class="f-col">
      <h4>Contact</h4>
      <div class="f-address">
        <?= h(SITE['company']) ?><br>
        <?= h(SITE['region']) ?><br>
        <a href="mailto:<?= h(SITE['support_email']) ?>"><?= h(SITE['support_email']) ?></a><br>
        <a href="<?= h(SITE['url']) ?>"><?= h(SITE['domain']) ?></a>
      </div>
    </div>
  </div>
  <div class="copy-row">
    <span>© <?= h(SITE['year']) ?> <?= h(SITE['company']) ?> — <?= h(SITE['domain']) ?></span>
    <span>AutoCAD and Plant 3D are trademarks of Autodesk, Inc.</span>
  </div>
</footer>
<script src="assets/main.js"></script>
</body>
</html>
