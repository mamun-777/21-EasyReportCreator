<?php
declare(strict_types=1);
$page = 'contact';
$title = 'Contact — EasyReportCreator | easyreportcreator.com';
$description = 'Questions about EasyReportCreator delivery, templates, or STRATO hosting? Email the team that builds the product.';
require __DIR__ . '/inc/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <div class="eyebrow">Contact</div>
    <h1>Questions go to the people who build it.</h1>
    <p class="sub">Delivery status, column templates, logo files, and hosting for easyreportcreator.com — one mailbox.</p>
  </div>
</section>

<section class="tight">
  <div class="wrap contact-grid">
    <div class="reveal">
      <div class="contact-card">
        <div class="panel-bar">
          <span class="file">Contact — <?= h(SITE['domain']) ?></span>
          <div class="panel-dots"><i></i><i></i><i></i></div>
        </div>
        <ul class="contact-list">
          <li><span>SUPPORT</span><?= h(SITE['support_email']) ?></li>
          <li><span>COMPANY</span><?= h(SITE['company']) ?></li>
          <li><span>REGION</span><?= h(SITE['region']) ?></li>
          <li><span>SITE</span><?= h(SITE['url']) ?></li>
          <li><span>RESPONSE</span>1–2 business days (CET)</li>
        </ul>
        <div class="contact-direct">
          <a href="mailto:<?= h(SITE['support_email']) ?>"><?= h(SITE['support_email']) ?> →</a>
        </div>
      </div>
    </div>

    <div class="reveal">
      <div class="section-head" style="margin-bottom:24px;">
        <div class="eyebrow">Send this</div>
        <h2>Useful with the first mail.</h2>
      </div>
      <ul class="checklist">
        <li><b>01</b><span>Plant 3D project name (sample is MN-P-RHN-PID-0001)</span></li>
        <li><b>02</b><span>Company logo (PNG or JPEG) for the Excel title block</span></li>
        <li><b>03</b><span>Preferred paper size / document number if different from the demo</span></li>
        <li><b>04</b><span>Which list is the master deliverable if not Componentenlijst</span></li>
      </ul>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
