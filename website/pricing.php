<?php
declare(strict_types=1);
$page = 'pricing';
$title = 'Pricing — EasyReportCreator | easyreportcreator.com';
$description = 'Version 1 of EasyReportCreator is a delivered web application for your Plant 3D environment. Further licences and Pro write-back are quoted separately.';
require __DIR__ . '/inc/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <div class="eyebrow">Pricing</div>
    <h1>Version 1 is a delivery, not a per-click fee.</h1>
    <p class="sub">The current engagement is a 90–100 hour web application for your Plant 3D lists. Additional seats, write-back, or a later product licence are quoted when you need them.</p>
  </div>
</section>

<section class="tight">
  <div class="wrap">
    <div class="plans reveal">
      <div class="plan featured">
        <div class="plan-name">Version 1 — delivered app</div>
        <div class="plan-desc">The agreed web application on your sample project, plus this public site.</div>
        <div class="plan-price-row">As quoted</div>
        <div class="plan-price">90–100<span class="unit"> hours</span></div>
        <div class="plan-sub">Target handover 11 September 2026</div>
        <ul class="plan-feats">
          <li>Eight core list types on MN-P-RHN-PID-0001</li>
          <li>Header from Project Details + custom properties</li>
          <li>Column picker and company templates</li>
          <li>English Excel with logo and revision table</li>
          <li>Light web UI + easyreportcreator.com on STRATO</li>
          <li>One feedback round after first complete demo</li>
        </ul>
        <a href="contact.php" class="btn block">Ask about delivery</a>
      </div>

      <div class="plan">
        <div class="plan-name">Later packages</div>
        <div class="plan-desc">Optional work after Version 1, quoted separately.</div>
        <div class="plan-price-row">Change request</div>
        <div class="plan-price custom">On request</div>
        <div class="plan-sub">Not included in the 100 hour package</div>
        <ul class="plan-feats">
          <li>Pro write-back (user-defined properties, preview + approval)</li>
          <li>Windows installer wrapper</li>
          <li>Cloud / SSO hosting</li>
          <li>3D / isometric list support</li>
        </ul>
        <a href="contact.php" class="btn ghost block">Talk to us</a>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="wrap prose">
    <div class="section-head reveal">
      <div class="eyebrow">Questions</div>
      <h2>What is and is not billed in Version 1.</h2>
    </div>
    <div class="faq">
      <div class="faq-item">
        <h3>Is this a yearly licence like Inventor iProperties Manager?</h3>
        <p>Not in Version 1. That product is a per-computer annual key. EasyReportCreator Version 1 is a delivered web app for the agreed Plant 3D workflow. A later retail licence can be designed if you want to sell it the same way.</p>
      </div>
      <div class="faq-item">
        <h3>Does the public site include hosting of my DCF?</h3>
        <p>No. easyreportcreator.com is the product site. The report app runs on a PC or intranet machine that can see your project folder. Public cloud hosting is a separate package.</p>
      </div>
      <div class="faq-item">
        <h3>Can we add write-back later?</h3>
        <p>Yes. User-defined properties only, AutoCAD closed, preview and approval — quoted as a Pro package (about 40–65 hours).</p>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
