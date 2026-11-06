<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Services/ParametreService.php';
require_once __DIR__ . '/../../app/Services/ElectionService.php';

SessionHelper::start();

if (!isset($candidatureConditions) || !is_array($candidatureConditions)) {
    $candidatureConditions = (new ParametreService())->getCandidatureConditions();
}
if (!isset($derniereElection)) {
    $electionService = new ElectionService();
    $derniereElection = $electionService->getDerniereElection();
    $candCheck = $electionService->canSubmitCandidature($derniereElection);
    $candidaturesOuvertes = $candCheck['allowed'];
    $candidatureBlockReason = $candCheck['reason'];
    $messageVerrouillageCandidature = $candCheck['message'];
} else {
    $candidaturesOuvertes = $candidaturesOuvertes ?? false;
    $candidatureBlockReason = $candidatureBlockReason ?? ($derniereElection ? 'closed' : 'no_election');
    $messageVerrouillageCandidature = $messageVerrouillageCandidature ?? (
        $derniereElection
            ? ElectionService::MSG_CANDIDATURES_CLOTUREES
            : ElectionService::MSG_AUCUNE_ELECTION_CANDIDATURE
    );
}

$candidatureResult = $candidatureResult ?? null;
$maxDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dépôt de candidature | SAS Élection</title>

    <link rel="icon" type="image/png" href="assets/images/logo/logosas.png">
    <link rel="apple-touch-icon" href="assets/images/logo/logosas.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
</head>
<body class="bg-light py-5">

    <div class="container" style="max-width: 740px;">
        <div class="text-center mb-4">
            <a href="index.php?page=home" class="d-inline-flex align-items-center gap-2 text-decoration-none text-primary mb-2">
                <img src="assets/images/logo/logosas.png" alt="Logo SAS" height="38" class="rounded shadow-sm">
                <span class="fs-4 fw-bold">SAS ÉLECTION</span>
            </a>
            <h1 class="h3 fw-bold">Dépôt d'une candidature</h1>
            <p class="text-muted">Proposez votre candidature à la présidence du SAS.</p>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <?php if (!empty($candidatureResult)): ?>
            <?php if (!empty($candidatureResult['conforme'])): ?>
                <!-- Toutes les conditions de candidature sont respectées -->
                <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4 border-top border-success border-4">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-success-subtle text-success d-inline-flex align-items-center justify-content-center mb-3" style="width: 68px; height: 68px;">
                            <i class="bi bi-file-earmark-check-fill fs-1"></i>
                        </div>
                        <div class="d-flex justify-content-center gap-2 mb-2">
                            <span class="badge bg-warning text-dark px-3 py-1">Candidature en attente</span>
                            <span class="badge bg-success text-white px-3 py-1">Dossier conforme</span>
                        </div>
                        <h2 class="h4 fw-bold text-success mb-2">Candidature transmise au bureau</h2>
                    </div>

                    <div class="alert alert-success border-success-subtle mb-4">
                        <p class="fw-bold mb-2">
                            Votre dossier de candidature est conforme aux conditions requises et a bien été enregistré avec le statut « En attente ».
                        </p>
                        <p class="mb-0">
                            Il a été transmis au bureau du SAS et est désormais <strong>en attente de validation définitive du bureau</strong> avant son affichage sur le bulletin de vote.
                        </p>
                    </div>

                    <div class="text-center">
                        <a href="index.php?page=home" class="btn btn-primary px-4">
                            <i class="bi bi-house me-1"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Une ou plusieurs conditions ne sont pas respectées -->
                <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4 border-top border-warning border-4">
                    <div class="text-center mb-3">
                        <div class="rounded-circle bg-warning-subtle text-warning d-inline-flex align-items-center justify-content-center mb-3" style="width: 68px; height: 68px;">
                            <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                        </div>
                        <div class="d-flex justify-content-center gap-2 mb-2">
                            <span class="badge bg-warning text-dark px-3 py-1">Candidature en attente</span>
                            <span class="badge bg-danger text-white px-3 py-1">Conditions non respectées</span>
                        </div>
                        <h2 class="h4 fw-bold text-dark mb-2">Dossier en attente d'examen par le bureau</h2>
                    </div>

                    <div class="alert alert-warning border-warning-subtle mb-4">
                        <p class="fw-bold mb-2">
                            Votre candidature ne respecte pas encore toutes les conditions requises :
                        </p>
                        <ul class="mb-3">
                            <?php foreach ($candidatureResult['motifs'] as $motif): ?>
                                <li class="fw-semibold"><?= SecurityHelper::e($motif) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="mb-0 small">
                            Votre dossier a été conservé en attente de traitement. Le bureau du SAS consultera ces motifs et prendra sa décision.
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
            <?php if (!$derniereElection): ?>
                <!-- Cas 1 : Aucune élection n'existe -->
                <div class="alert alert-warning border-warning-subtle text-center p-4 mb-0">
                    <i class="bi bi-exclamation-triangle-fill fs-2 text-warning d-block mb-2"></i>
                    <p class="fw-bold mb-0"><?= SecurityHelper::e(ElectionService::MSG_AUCUNE_ELECTION_CANDIDATURE) ?></p>
                </div>
                <div class="text-center mt-4">
                    <a href="index.php?page=home" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Retour à l'accueil
                    </a>
                </div>
            <?php else: ?>
                <!-- Affichage automatique en lecture seule du dernier scrutin enregistré -->
                <div class="p-3 rounded-3 bg-primary-subtle border border-primary-subtle mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <span class="text-primary fw-bold">
                            <i class="bi bi-award-fill me-1"></i> Candidature pour : <?= SecurityHelper::e($derniereElection['titre']) ?>
                        </span>
                        <?php if (!empty($derniereElection['date_ouverture_candidatures']) && !empty($derniereElection['date_fermeture_candidatures'])): ?>
                            <div class="small text-muted mt-1">
                                Période de dépôt : du <strong><?= date('d/m/Y à H:i', strtotime($derniereElection['date_ouverture_candidatures'])) ?></strong>
                                au <strong><?= date('d/m/Y à H:i', strtotime($derniereElection['date_fermeture_candidatures'])) ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($candidaturesOuvertes): ?>
                        <span class="badge bg-success"><i class="bi bi-unlock-fill me-1"></i> Période ouverte</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i> Période clôturée</span>
                    <?php endif; ?>
                </div>

                <?php if (!$candidaturesOuvertes): ?>
                    <!-- Cas 2 : La période de candidature n'est pas ouverte ou est clôturée -->
                    <div class="alert alert-danger border-danger-subtle d-flex align-items-start gap-3 mb-4" role="alert">
                        <i class="bi bi-lock-fill fs-3 text-danger"></i>
                        <div>
                            <h2 class="h6 fw-bold mb-1">Dépôt des candidatures verrouillé</h2>
                            <p class="mb-0 fw-semibold"><?= SecurityHelper::e(ElectionService::MSG_CANDIDATURES_CLOTUREES) ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info py-2 small mb-4">
                        <i class="bi bi-info-circle me-1"></i> <strong>Règle statutaire :</strong>
                        Toute nouvelle candidature est enregistrée avec le statut <span class="badge bg-warning text-dark">Candidature en attente</span>.
                        La conformité aux critères d'éligibilité ne remplace jamais la validation définitive du bureau : seules les candidatures validées par le bureau figureront sur le bulletin de vote officiel.
                    </div>
                <?php endif; ?>

                <form method="post" action="index.php?page=candidater" enctype="multipart/form-data">
                    <?= SessionHelper::csrfField() ?>
                    <fieldset <?= !$candidaturesOuvertes ? 'disabled' : '' ?>>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nom de famille <span class="text-danger">*</span></label>
                                <input type="text" name="nom" class="form-control" required placeholder="Ex: KOUADIO">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Prénoms <span class="text-danger">*</span></label>
                                <input type="text" name="prenoms" class="form-control" required placeholder="Ex: Jean Ferdinand">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Numéro de téléphone <span class="text-danger">*</span></label>
                                <input type="tel" name="telephone" class="form-control" required placeholder="Ex: 0700000000">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Date de naissance <span class="text-danger">*</span></label>
                                <input type="date" name="date_naissance" class="form-control" max="<?= $maxDate ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Ancienneté déclarée <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="anciennete" class="form-control" min="0" max="100" step="1" required placeholder="Ex: 3">
                                    <span class="input-group-text">année(s)</span>
                                </div>
                                <small class="text-muted">Nombre d'années d'ancienneté au sein du SAS (minimum : 0).</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Photo du candidat</label>
                                <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <small class="text-muted">JPG, PNG ou WEBP (max 3 Mo).</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold d-block">État du paiement du droit de candidature <span class="text-danger">*</span></label>
                                <div class="d-flex flex-wrap gap-4 p-3 rounded border bg-light">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="droit_candidature_paye" id="droitPayeOui" value="1" required>
                                        <label class="form-check-label fw-semibold text-success" for="droitPayeOui">
                                            <i class="bi bi-check-circle me-1"></i> Oui, droit de candidature payé
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="droit_candidature_paye" id="droitPayeNon" value="0" required>
                                        <label class="form-check-label fw-semibold text-danger" for="droitPayeNon">
                                            <i class="bi bi-x-circle me-1"></i> Non, pas encore payé
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Motif de candidature / Profession de foi <span class="text-danger">*</span></label>
                                <textarea name="motif_candidature" class="form-control" rows="3" maxlength="200" required placeholder="Présentez vos motivations, votre engagement chrétien et votre vision pour le SAS..."></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold" <?= !$candidaturesOuvertes ? 'disabled' : '' ?>>
                                <i class="bi bi-send me-1"></i> Soumettre ma candidature
                            </button>
                        </div>
                    </fieldset>
                </form>

                <div class="text-center mt-3 pt-2">
                    <a href="index.php?page=home" class="text-decoration-none text-muted small">
                        <i class="bi bi-arrow-left me-1"></i> Retour à l'accueil
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>
