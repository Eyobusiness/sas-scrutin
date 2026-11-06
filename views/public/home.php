<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Services/ElectionService.php';

if (!isset($derniereElection)) {
    $derniereElection = (new ElectionService())->getDerniereElection();
}
$phaseInfo = $derniereElection['phase_info'] ?? null;
$activeElection = $activeElection ?? (($derniereElection && !empty($phaseInfo['vote_open'])) ? $derniereElection : null);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAS Élection &mdash; Tous unis au service du Christ</title>

    <link rel="icon" type="image/png" href="assets/images/logo/logosas.png">
    <link rel="apple-touch-icon" href="assets/images/logo/logosas.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        .public-navbar {
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            background: #ffffff;
        }
        
        body {
            background-image: url('https://electionsas.l2vservices.com/assets/images/logo/sas.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }

        .hero-section {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #ffffff;
            padding: 4rem 1rem;
            border-radius: 0 0 2rem 2rem;
        }

        .portal-card {
            border: 1px solid rgba(0, 0, 0, 0.06);
            border-radius: 1rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            background: #ffffff;
        }

        .portal-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }

        .portal-icon {
            width: 56px;
            height: 56px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }
    </style>
</head>

<body class="bg-light">

    <!-- Navbar publique -->
    <nav class="navbar public-navbar navbar-expand-lg sticky-top shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-primary" href="index.php?page=home">
                <img src="assets/images/logo/logosas.png" alt="Logo SAS" height="38" class="rounded shadow-sm">
                <div>
                    <span class="d-block lh-1 fs-6">SAS ÉLECTION</span>
                </div>
            </a>

            <div class="ms-auto d-flex align-items-center gap-2">
                <a href="index.php?page=login" class="btn btn-outline-primary btn-sm px-3">
                    <i class="bi bi-shield-lock me-1"></i> Espace ad hoc
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section text-center mb-5 shadow-sm">
        <div class="container py-3">
           <span class="badge bg-white text-primary px-3 py-2 rounded-pill fw-bold text-uppercase mb-3 shadow-sm">
                <i class="bi bi-cross me-1"></i> Élection du Président du SAS
            </span>
            
            <h1 class="display-5 fw-bold mb-3 text-white">
                Plateforme Officielle de Scrutin
            </h1>
            
            <p class="lead mb-4 mx-auto text-white-50" style="max-width: 750px;">
                Bienvenue sur la plateforme officielle du SAS, dédiée à l'inscription des membres, aux candidatures et au vote, dans un esprit de transparence, d'équité et de fraternité chrétienne.
            </p>

            <?php if ($activeElection): ?>
                <div class="d-inline-flex flex-wrap gap-2 justify-content-center">
                    <a href="index.php?page=voter" class="btn btn-warning btn-lg px-4 py-2 fw-bold shadow">
                        <i class="bi bi-fingerprint me-2"></i> Voter maintenant
                    </a>
                    <a href="index.php?page=resultats" class="btn btn-outline-light btn-lg px-4 py-2">
                        <i class="bi bi-trophy me-1"></i> État du scrutin
                    </a>
                </div>
            <?php else: ?>
                <div class="d-inline-flex flex-wrap gap-2 justify-content-center">
                    <?php if ($phaseInfo && !empty($phaseInfo['ins_open'])): ?>
                        <a href="index.php?page=inscription" class="btn btn-warning btn-lg px-4 py-2 fw-bold shadow">
                            <i class="bi bi-person-plus me-1"></i> S'inscrire comme membre
                        </a>
                    <?php endif; ?>
                    <a href="index.php?page=resultats" class="btn btn-outline-light btn-lg px-4 py-2">
                        <i class="bi bi-trophy me-1"></i> Consulter les résultats du scrutin
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="container mb-5">
        <?= SessionHelper::renderFlashes() ?>

        <!-- Bandeau du dernier scrutin de référence -->
        <?php if ($derniereElection && $phaseInfo): ?>
            <div class="card border-0 shadow-sm mb-4 bg-white border-start border-<?= SecurityHelper::e($phaseInfo['badge']) ?> border-4 p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <span class="badge bg-<?= SecurityHelper::e($phaseInfo['badge']) ?> px-3 py-1">
                                Phase actuelle : <?= SecurityHelper::e($phaseInfo['label']) ?>
                            </span>
                            <?php if (!empty($phaseInfo['locks']['inscriptions_cloturees'])): ?>
                                <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-lock-fill me-1"></i>Inscriptions clôturées</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border"><i class="bi bi-unlock-fill me-1"></i>Inscriptions ouvertes</span>
                            <?php endif; ?>
                            <?php if (!empty($phaseInfo['locks']['candidatures_cloturees'])): ?>
                                <span class="badge bg-secondary-subtle text-secondary border"><i class="bi bi-lock-fill me-1"></i>Candidatures clôturées</span>
                            <?php else: ?>
                                <span class="badge bg-info-subtle text-info border"><i class="bi bi-unlock-fill me-1"></i>Candidatures ouvertes</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="h4 mb-1 fw-bold"><?= SecurityHelper::e($derniereElection['titre']) ?></h2>
                        <p class="text-muted mb-0 small">
                            Période de vote : Du <strong><?= date('d/m/Y à H:i', strtotime($derniereElection['date_debut_vote'] ?? $derniereElection['date_debut'])) ?></strong>
                            au <strong><?= date('d/m/Y à H:i', strtotime($derniereElection['date_fin_vote'] ?? $derniereElection['date_fin'])) ?></strong>
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!empty($phaseInfo['vote_open'])): ?>
                            <a href="index.php?page=voter" class="btn btn-primary px-4 py-2 fw-semibold">
                                <i class="bi bi-check2-circle me-1"></i> Accéder au bulletin
                            </a>
                        <?php elseif (!empty($phaseInfo['is_closed'])): ?>
                            <a href="index.php?page=resultats" class="btn btn-success px-4 py-2 fw-semibold">
                                <i class="bi bi-trophy me-1"></i> Voir les résultats
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- 4 Actions principales -->
        <div class="row g-4 mb-5">
            <!-- 1. Voter -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="portal-card h-100 p-4 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="portal-icon bg-primary-subtle text-primary mx-auto">
                            <i class="bi bi-fingerprint"></i>
                        </div>
                        <h3 class="h5 fw-bold mb-2">Vote électronique</h3>
                        <p class="text-muted small mb-4">
                            Exercez votre droit de vote facilement et en toute sécurité avec votre numéro de téléphone et votre date de naissance.
                        </p>
                    </div>
                    <a href="index.php?page=voter" class="btn btn-outline-primary btn-sm w-100 fw-semibold">
                        Participer au vote &rarr;
                    </a>
                </div>
            </div>

            <!-- 2. S'inscrire -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="portal-card h-100 p-4 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="portal-icon bg-success-subtle text-success mx-auto">
                            <i class="bi bi-person-plus"></i>
                        </div>
                        <h3 class="h5 fw-bold mb-2">Inscription Membre</h3>
                        <p class="text-muted small mb-4">
                            Enregistrez-vous sur le registre du SAS pendant la période d'ouverture des inscriptions.
                        </p>
                    </div>
                    <a href="index.php?page=inscription" class="btn btn-outline-success btn-sm w-100 fw-semibold">
                        S'inscrire en ligne &rarr;
                    </a>
                </div>
            </div>

            <!-- 3. Candidater -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="portal-card h-100 p-4 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="portal-icon bg-info-subtle text-info mx-auto">
                            <i class="bi bi-person-badge"></i>
                        </div>
                        <h3 class="h5 fw-bold mb-2">Dépôt Candidature</h3>
                        <p class="text-muted small mb-4">
                            Déposez votre candidature et votre motivation pour le scrutin en cours.
                        </p>
                    </div>
                    <a href="index.php?page=candidater" class="btn btn-outline-info btn-sm w-100 fw-semibold">
                        Déposer candidature &rarr;
                    </a>
                </div>
            </div>

            <!-- 4. Résultats -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="portal-card h-100 p-4 text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="portal-icon bg-warning-subtle text-warning mx-auto">
                            <i class="bi bi-trophy"></i>
                        </div>
                        <h3 class="h5 fw-bold mb-2">Résultats Officiels</h3>
                        <p class="text-muted small mb-4">
                            Consultez les résultats du dernier scrutin dès sa clôture définitive.
                        </p>
                    </div>
                    <a href="index.php?page=resultats" class="btn btn-outline-warning btn-sm w-100 fw-semibold">
                        Voir les résultats &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Principes et garanties du scrutin -->
        <div class="row g-4 mb-4">
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-shield-check fs-2 text-primary"></i>
                        <div>
                            <h4 class="h6 fw-bold mb-1">Secret du vote absolu</h4>
                            <p class="text-muted small mb-0">Le bulletin de vote est complètement séparé de l'identité du membre.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-person-check fs-2 text-success"></i>
                        <div>
                            <h4 class="h6 fw-bold mb-1">Vérification d'éligibilité</h4>
                            <p class="text-muted small mb-0">Seuls les membres éligibles inscrits avant l'ouverture du scrutin peuvent voter.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm p-3 h-100">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-award fs-2 text-info"></i>
                        <div>
                            <h4 class="h6 fw-bold mb-1">Unicité du vote</h4>
                            <p class="text-muted small mb-0">Chaque membre ne peut voter qu'une seule fois par élection.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer public -->
    <footer class="bg-white py-4 border-top">
        <div class="container text-center text-muted small">
            <p class="mb-1"><strong>SAS Élection</strong> &mdash; <em>« Tous unis au service du Christ »</em></p>
            <p class="mb-0 text-muted">&copy; <?= date('Y') ?> &bull; Assistance : <?= SecurityHelper::e($appParams['contact_phone'] ?? '+225 07 00 00 00 00') ?> &bull; <?= SecurityHelper::e($appParams['contact_email'] ?? 'contact@sas-eglise.org') ?></p>
        </div>
    </footer>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>
