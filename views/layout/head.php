<!DOCTYPE html>
<html lang="fr" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plateforme d'élection du président du SAS - Tous unis au service du Christ">
    <title><?= isset($pageTitle) ? SecurityHelper::e($pageTitle) . ' | SAS Élection' : 'SAS Élection' ?></title>

    <link rel="icon" type="image/png" href="assets/images/logo/logosas.png">
    <link rel="apple-touch-icon" href="assets/images/logo/logosas.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <?php if (!empty($customCss)): ?>
        <link rel="stylesheet" href="<?= SecurityHelper::e($customCss) ?>">
    <?php endif; ?>
    <style>
        .badge-subtle-success { background-color: #d1e7dd; color: #0f5132; }
        .badge-subtle-danger { background-color: #f8d7da; color: #842029; }
        .badge-subtle-warning { background-color: #fff3cd; color: #664d03; }
        .badge-subtle-info { background-color: #cff4fc; color: #055160; }
        .candidate-avatar { width: 50px; height: 50px; object-fit: cover; border-radius: 50%; }
        .candidate-card-img { height: 160px; object-fit: cover; }
    </style>
</head>
<body>
<div class="admin-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="admin-main">
        <?php include __DIR__ . '/header.php'; ?>
