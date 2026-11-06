<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Services/ParametreService.php';
require_once __DIR__ . '/../../app/Services/ElectionService.php';

SessionHelper::start();

if (!isset($electorConditions) || !is_array($electorConditions)) {
    $electorConditions = (new ParametreService())->getElectorConditions();
}
if (!isset($derniereElection)) {
    $electionService = new ElectionService();
    $derniereElection = $electionService->getDerniereElection();
    $regCheck = $electionService->canRegisterMember($derniereElection);
    $inscriptionsOuvertes = $regCheck['allowed'];
    $messageVerrouillage = $regCheck['message'];
} else {
    $inscriptionsOuvertes = $inscriptionsOuvertes ?? true;
    $messageVerrouillage = $messageVerrouillage ?? ElectionService::MSG_INSCRIPTIONS_CLOTUREES;
}

$inscriptionResult = $inscriptionResult ?? null;
$maxDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Membre | SAS Élection</title>

    <link rel="icon" type="image/png" href="assets/images/logo/logosas.png">
    <link rel="apple-touch-icon" href="assets/images/logo/logosas.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
</head>


<body class="bg-light py-5">

    <div class="container" style="max-width: 700px;">
        <div class="text-center mb-4">
            <a href="index.php?page=home" class="d-inline-flex align-items-center gap-2 text-decoration-none text-primary mb-2">
                <img src="assets/images/logo/logosas.png" alt="Logo SAS" height="38" class="rounded shadow-sm">
                <span class="fs-4 fw-bold">SAS ÉLECTION</span>
            </a>
            <h1 class="h3 fw-bold">Inscription d'un membre</h1>
            <p class="text-muted mb-2">Enregistrez-vous sur le registre officiel des membres et électeurs du SAS.</p>
            <?php if (!empty($derniereElection)): ?>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                    <i class="bi bi-calendar2-check me-1"></i> Scrutin de référence : <strong><?= SecurityHelper::e($derniereElection['titre']) ?></strong>
                </span>
            <?php endif; ?>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <?php if (!empty($inscriptionResult)): ?>
            <?php if (!empty($inscriptionResult['eligible'])): ?>
                <!-- CAS 1 : Toutes les conditions sont respectées -> Éligibilité automatique -->
                <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4 border-top border-success border-4">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 68px; height: 68px;">
                            <i class="bi bi-check-circle-fill fs-1"></i>
                        </div>
                        <span class="badge bg-success text-white px-3 py-1 d-block mx-auto mb-2" style="width: fit-content;">
                            Éligible au vote
                        </span>
                        <h2 class="h4 fw-bold text-success mb-2">Inscription validée</h2>
                    </div>

                    <div class="alert alert-success border-success-subtle mb-4">
                        <p class="fw-bold mb-2">
                            Félicitations ! Votre inscription a été enregistrée avec succès. Vous êtes autorisé(e) à participer au vote du SAS.
                        </p>
                        <p class="mb-0">
                            Pour voter, vous aurez besoin de votre numéro de téléphone et de votre date de naissance. Conservez soigneusement ces informations.
                        </p>
                    </div>

                    <div class="card bg-light border p-3 mb-4">
                        <h3 class="h6 fw-bold text-dark mb-3">
                            <i class="bi bi-key-fill text-primary me-1"></i> Vos identifiants de vote enregistrés :
                        </h3>
                        <div class="row g-3">
                            <div class="col-12 col-sm-6">
                                <small class="text-muted d-block">Numéro de téléphone enregistré</small>
                                <span class="fs-5 fw-bold text-primary font-monospace"><?= SecurityHelper::e($inscriptionResult['telephone']) ?></span>
                            </div>
                            <div class="col-12 col-sm-6">
                                <small class="text-muted d-block">Date de naissance enregistrée</small>
                                <span class="fs-5 fw-bold text-primary">
                                    <?= date('d/m/Y', strtotime($inscriptionResult['date_naissance'])) ?>
                                </span>
                                <small class="text-muted d-block">(<?= SecurityHelper::e($inscriptionResult['date_naissance']) ?>)</small>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
                        <a href="index.php?page=voter" class="btn btn-success px-4 fw-bold">
                            <i class="bi bi-fingerprint me-1"></i> Accéder au vote
                        </a>
                        <a href="index.php?page=home" class="btn btn-outline-secondary px-4">
                            <i class="bi bi-house me-1"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- CAS 2 : Une ou plusieurs conditions ne sont pas respectées -->
                <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4 border-top border-warning border-4">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center mb-3" style="width: 68px; height: 68px;">
                            <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                        </div>
                        <span class="badge bg-danger text-white px-3 py-1 d-block mx-auto mb-2" style="width: fit-content;">
                            Non éligible au vote
                        </span>
                        <h2 class="h4 fw-bold text-dark mb-2">Inscription enregistrée sans droit de vote</h2>
                    </div>

                    <div class="alert alert-warning border-warning-subtle mb-4">
                        <p class="fw-bold mb-2">
                            Votre inscription a bien été enregistrée, mais vous n'êtes pas autorisé(e) à voter pour le moment.
                        </p>

                        <?php if (!empty($inscriptionResult['explications'])): ?>
                            <?php foreach ($inscriptionResult['explications'] as $exp): ?>
                                <p class="mb-2"><?= SecurityHelper::e($exp) ?></p>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($inscriptionResult['motifs'])): ?>
                            <div class="mt-2 mb-2">
                                <strong>Motifs :</strong>
                                <ul class="mb-0 mt-1">
                                    <?php foreach ($inscriptionResult['motifs'] as $motif): ?>
                                        <li><?= SecurityHelper::e($motif) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <p class="mb-0 mt-2 fw-semibold">
                            Veuillez contacter le bureau du SAS pour plus d'informations.
                        </p>
                    </div>

                    <div class="text-center">
                        <a href="index.php?page=home" class="btn btn-primary px-4">
                            <i class="bi bi-house me-1"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card border-0 shadow-sm p-3 p-sm-4 p-md-5 bg-white">
            <?php if (!$inscriptionsOuvertes): ?>
                <!-- Verrouillage automatique des inscriptions -->
                <div class="alert alert-danger border-danger-subtle d-flex align-items-start gap-3 mb-4" role="alert">
                    <i class="bi bi-lock-fill fs-3 text-danger"></i>
                    <div>
                        <h2 class="h6 fw-bold mb-1">Inscriptions verrouillées</h2>
                        <p class="mb-0 fw-semibold"><?= SecurityHelper::e(ElectionService::MSG_INSCRIPTIONS_CLOTUREES) ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info py-2 small mb-4">
                    <i class="bi bi-info-circle me-1"></i> <strong>Information :</strong>
                    Après soumission de ce formulaire, le système vérifie automatiquement les conditions d'éligibilité.
                    Votre numéro de téléphone et votre date de naissance vous permettront de vous authentifier le jour du vote.
                </div>
            <?php endif; ?>

            <form method="post" action="index.php?page=inscription">
                <?= SessionHelper::csrfField() ?>
                <fieldset <?= !$inscriptionsOuvertes ? 'disabled' : '' ?>>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">1. Nom <span class="text-danger">*</span></label>
                            <input type="text" name="nom" class="form-control" required placeholder="Ex: KOUADIO">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">2. Prénoms <span class="text-danger">*</span></label>
                            <input type="text" name="prenoms" class="form-control" required placeholder="Ex: Jean Ferdinand">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">3. Numéro de téléphone <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                <input type="tel" name="telephone" class="form-control" required placeholder="Ex: 0700000000">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">4. Date de naissance <span class="text-danger">*</span></label>
                            <input type="date" name="date_naissance" class="form-control" max="<?= $maxDate ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">5. Fonction professionnelle <span class="text-danger">*</span></label>
                            <input type="text" name="fonction_pro" class="form-control" required placeholder="Ex: Enseignant, Comptable, Étudiant...">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">6. Responsabilité dans le SAS <span class="text-danger">*</span></label>
                            <input type="text" name="responsabilite_sas" class="form-control" required placeholder="Ex: Membre, Secrétaire, Chorale...">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">7. Ancienneté dans le SAS <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="anciennete" class="form-control" min="0" max="100" step="1" required placeholder="Ex: 2">
                                <span class="input-group-text">année(s)</span>
                            </div>
                            <small class="text-muted">Indiquez votre nombre d'années d'ancienneté au sein du SAS (valeur minimale : 0).</small>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold" <?= !$inscriptionsOuvertes ? 'disabled' : '' ?>>
                            <i class="bi bi-check-circle me-1"></i> Valider mon inscription
                        </button>
                    </div>
                </fieldset>
            </form>

            <div class="text-center mt-3 pt-2">
                <a href="index.php?page=home" class="text-decoration-none text-muted small">
                    <i class="bi bi-arrow-left me-1"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>
