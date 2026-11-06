<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote confirmé | SAS Élection</title>

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

        <div class="card border-0 shadow-sm p-3 p-sm-4 p-md-5 text-center bg-white">
            <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                <i class="bi bi-check-circle-fill fs-1"></i>
            </div>

            <h1 class="h3 fw-bold text-success mb-2"><?= SecurityHelper::e($message) ?></h1>
            <p class="text-muted mb-4">
                Votre bulletin a été déposé dans l'urne électronique et votre participation a été enregistrée avec succès.
            </p>

            <div class="alert alert-light border small text-muted mb-4 text-start">
                <div class="d-flex align-items-center gap-2 mb-1 text-dark fw-bold">
                    <i class="bi bi-shield-check text-success"></i> Secret du vote préservé
                </div>
                <div>
                    Votre choix de vote est déconnecté de votre identité. Votre émargement atteste uniquement que vous avez voté pour cette élection.
                </div>
            </div>

            <p class="text-muted small mb-4">
                Merci de patienter pour la proclamation officielle des résultats qui sera effectuée dès la clôture définitive du scrutin.
            </p>

            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                <a href="index.php?page=home" class="btn btn-primary px-4 fw-semibold">
                    <i class="bi bi-house me-1"></i> Retour au portail public
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
