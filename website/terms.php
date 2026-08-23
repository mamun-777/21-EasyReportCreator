<?php
declare(strict_types=1);
$page = 'terms';
$title = 'Terms & delivery — EasyReportCreator | easyreportcreator.com';
$description = 'Delivery scope for EasyReportCreator Version 1: web application, English Excel, sample project acceptance, one feedback round.';
require __DIR__ . '/inc/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <div class="eyebrow">Terms</div>
    <h1>What Version 1 delivers.</h1>
    <p class="sub">Short delivery terms for the 90–100 hour web application package. Not a consumer webshop checkout.</p>
  </div>
</section>

<section class="tight">
  <div class="wrap prose">
    <h2>Scope</h2>
    <p>EasyReportCreator Version 1 is a web application that reads an AutoCAD Plant 3D project database and issues English Excel lists. Acceptance is against the sample project MN-P-RHN-PID-0001. The public site at easyreportcreator.com is included as a light-themed product site hosted on STRATO.</p>

    <h2>What is not included</h2>
    <p>Live write-back into ProcessPower.dcf, a Windows installer, public cloud hosting of your project files, SSO / multi-tenant accounts, and full 3D isometric lists are optional later packages.</p>

    <h2>Support</h2>
    <p>Email <?= h(SITE['support_email']) ?>. One consolidated feedback round is included after the first complete demo of this package. Extra UAT rounds are a change request.</p>

    <h2>Trademarks</h2>
    <p>AutoCAD, Plant 3D and Inventor are trademarks of Autodesk, Inc. EasyReportCreator is not affiliated with Autodesk.</p>
  </div>
</section>

<?php require __DIR__ . '/inc/footer.php'; ?>
