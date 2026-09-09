<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$page = $page ?? 'home';
$title = $title ?? SITE['name'];
$description = $description ?? 'Friendlier Plant 3D lists in Excel — without AutoCAD Report Creator.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?></title>
<meta name="description" content="<?= h($description) ?>">
<link rel="stylesheet" href="assets/fonts.css?v=20260908b">
<link rel="stylesheet" href="assets/styles.css?v=20260908b">
</head>
<body>

<header>
  <div class="nav">
    <a href="index.php" class="logo"><span class="mark"></span>EasyReport<span class="dim">Creator</span></a>
    <nav class="links">
      <a href="index.php" class="<?= is_active('home', $page) ? 'active' : '' ?>">Home</a>
      <a href="product.php" class="<?= is_active('product', $page) ? 'active' : '' ?>">Product</a>
      <a href="pricing.php" class="<?= is_active('pricing', $page) ? 'active' : '' ?>">Pricing</a>
      <a href="download.php" class="<?= is_active('download', $page) ? 'active' : '' ?>">Download</a>
      <a href="report/login.php" class="<?= is_active('report', $page) ? 'active' : '' ?>">Report app</a>
      <a href="contact.php" class="<?= is_active('contact', $page) ? 'active' : '' ?>">Contact</a>
    </nav>
    <a href="report/login.php" class="btn">Open the app</a>
  </div>
</header>
