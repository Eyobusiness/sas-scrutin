<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scrutin non disponible | SAS Élection</title>

    <link rel="icon" type="image/png" href="assets/images/logo/logosas.png">
    <link rel="apple-touch-icon" href="assets/images/logo/logosas.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
</head>
<body class="bg-light py-5">

    <div class="container" style="max-width: 600px;">
        <div class="text-center mb-4">
            <a href="index.php?page=home" class="d-inline-flex align-items-center gap-2 text-decoration-none text-primary">
                <img src="assets/images/logo/logosas.png" alt="Logo SAS" height="38" class="rounded shadow-sm">
                <span class="fs-4 fw-bold">SAS ÉLECTION</span>
            </a>
        </div>

        <div class="card border-0 shadow-sm p-4 p-md-5 text-center bg-white">
            <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 72px; height: 72px;">
                <i class="bi bi-clock-history fs-1"></i>
            </div>

            <h1 class="h4 fw-bold mb-2">Vote non disponible</h1>
            
            <p class="text-muted mb-4">
                <?= SecurityHelper::e($messageFermeture ?? 'Aucune élection n\'est actuellement ouverte au vote.') ?>
            </p>

            <div class="alert alert-light border small text-muted mb-4 text-start">
                <strong>Rappels utiles :</strong>
                <ul class="mb-0 mt-2 ps-3">
                    <li>Le vote n'est accessible que lorsque l'élection a le statut <em>« Ouverte »</em>.</li>
                    <li>La période légale (date et heure de début/fin) doit être strictement respectée.</li>
                    <li>Après la clôture, aucun nouveau vote n'est accepté.</li>
                </ul>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-center">
                <a href="index.php?page=home" class="btn btn-primary px-4">
                    <i class="bi bi-house me-1"></i> Retour à l'accueil
                </a>
                <a href="index.php?page=resultats" class="btn btn-outline-secondary px-4">
                    <i class="bi bi-trophy me-1"></i> Consulter les résultats
                </a>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>
