<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Helpers/AuthHelper.php';
require_once __DIR__ . '/../../app/Services/ParametreService.php';

SessionHelper::start();
AuthHelper::requireLogin();

$parametreService = new ParametreService();
if (!isset($settings) || !is_array($settings)) {
    $settings = $parametreService->getAll();
}
if (!isset($electorConditions) || !is_array($electorConditions)) {
    $electorConditions = $parametreService->getElectorConditions();
}
if (!isset($candidatureConditions) || !is_array($candidatureConditions)) {
    $candidatureConditions = $parametreService->getCandidatureConditions();
}
$currentPage = $_GET['page'] ?? 'settings';

$pageTitle = ($currentPage === 'settings_eligibilite')
    ? 'Paramètres — Conditions d\'éligibilité'
    : 'Paramètres de l\'application';
include __DIR__ . '/../layout/head.php';
?>

<main class="dashboard-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- En-tête -->
        <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="page-heading-copy">
                <span class="page-icon"><i class="bi bi-sliders" aria-hidden="true"></i></span>
                <div>
                    <h1 class="h3 mb-1">Paramètres &rarr; Conditions d'éligibilité</h1>
                    <p class="text-muted mb-0">Configuration centralisée des critères d'âge, d'ancienneté, de candidature et de l'application.</p>
                </div>
            </div>
            <div class="heading-actions d-flex gap-2">
                <a href="index.php?page=membres" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-people me-1"></i> Liste électorale
                </a>
                <a href="index.php?page=candidats" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-person-badge me-1"></i> Candidatures
                </a>
            </div>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <div class="row g-4">
            <!-- =================================================================== -->
            <!-- COLONNE PRINCIPALE : CONDITIONS D'ÉLIGIBILITÉ ET DE CANDIDATURE    -->
            <!-- =================================================================== -->
            <div class="col-12 col-xl-8">
                <form method="post" action="index.php?page=<?= SecurityHelper::e($currentPage) ?>">
                    <?= SessionHelper::csrfField() ?>
                    <input type="hidden" name="form_type" value="eligibilite">
                    <input type="hidden" name="redirect_page" value="<?= SecurityHelper::e($currentPage) ?>">

                    <!-- SECTION 1 : CONDITIONS D'ACCÈS À LA LISTE ÉLECTORALE -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fs-6 fw-bold">
                                <i class="bi bi-person-check-fill me-2 text-primary"></i> 1. Conditions d'accès à la liste électorale (Électeurs)
                            </h5>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                Validation automatique à l'inscription
                            </span>
                        </div>
                        <div class="card-body p-4">
                            <div class="alert alert-info py-2 small mb-4">
                                <i class="bi bi-info-circle me-1"></i>
                                Lorsqu'un membre s'inscrit sur le portail public, le système vérifie automatiquement ces conditions.
                                Si toutes les conditions actives sont respectées, son droit de vote (<code>eligibilite_a_voter = oui</code>) est validé immédiatement.
                            </div>

                            <div class="row g-4">
                                <!-- A. Âge minimum -->
                                <div class="col-12 col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="ageMinActif" name="age_min_actif" value="1"
                                                   <?= $electorConditions['age_min_actif'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="ageMinActif">
                                                Activer la vérification de l'âge
                                            </label>
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold" for="ageMinValeur">
                                                Âge minimum autorisé
                                            </label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="ageMinValeur"
                                                       name="age_min_valeur" min="0" max="120" step="1"
                                                       value="<?= (int)$electorConditions['age_min_valeur'] ?>" required>
                                                <span class="input-group-text">année(s)</span>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block">
                                            Calculé automatiquement et précisément à partir du jour et du mois de naissance.
                                        </small>
                                    </div>
                                </div>

                                <!-- B. Ancienneté minimum -->
                                <div class="col-12 col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="ancMinActif" name="anciennete_min_actif" value="1"
                                                   <?= $electorConditions['anciennete_min_actif'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="ancMinActif">
                                                Activer la vérification de l'ancienneté
                                            </label>
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold" for="ancMinValeur">
                                                Ancienneté minimum requise
                                            </label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="ancMinValeur"
                                                       name="anciennete_min_valeur" min="0" max="100" step="1"
                                                       value="<?= (int)$electorConditions['anciennete_min_valeur'] ?>" required>
                                                <span class="input-group-text">année(s)</span>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block">
                                            Comparée à l'ancienneté déclarée manuellement par le membre (jamais calculée automatiquement).
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2 : CONDITIONS DE CANDIDATURE -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fs-6 fw-bold">
                                <i class="bi bi-award-fill me-2 text-primary"></i> 2. Conditions de candidature
                            </h5>
                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle">
                                Soumis à validation définitive du bureau
                            </span>
                        </div>
                        <div class="card-body p-4">
                            <!-- Option de centralisation âge/ancienneté -->
                            <div class="p-3 rounded-3 bg-light border mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="candCommunes" name="candidature_conditions_communes" value="1"
                                           <?= $candidatureConditions['conditions_communes'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="candCommunes">
                                        Utiliser les mêmes conditions d'âge et d'ancienneté que la liste électorale (configuration centralisée)
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Recommandé : garantit que les règles d'âge et d'ancienneté sont communes et sans aucune contradiction entre électeurs et candidats. Décochez uniquement si vous souhaitez définir un seuil spécifique pour les candidats.
                                </small>
                            </div>

                            <div class="row g-4 mb-4" id="specificCandidatureBlock">
                                <!-- Âge minimum candidature -->
                                <div class="col-12 col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="candAgeActif" name="candidature_age_min_actif" value="1"
                                                   <?= $candidatureConditions['age_min_actif'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="candAgeActif">
                                                Activer la vérification de l'âge (Candidat)
                                            </label>
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold" for="candAgeValeur">
                                                Âge minimum autorisé (Candidat)
                                            </label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="candAgeValeur"
                                                       name="candidature_age_min_valeur" min="0" max="120" step="1"
                                                       value="<?= (int)$candidatureConditions['age_min_valeur'] ?>" required>
                                                <span class="input-group-text">année(s)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Ancienneté minimum candidature -->
                                <div class="col-12 col-md-6">
                                    <div class="border rounded-3 p-3 h-100">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                   id="candAncActif" name="candidature_anciennete_min_actif" value="1"
                                                   <?= $candidatureConditions['anciennete_min_actif'] ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-bold" for="candAncActif">
                                                Activer la vérification de l'ancienneté (Candidat)
                                            </label>
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label small fw-semibold" for="candAncValeur">
                                                Ancienneté minimum requise (Candidat)
                                            </label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="candAncValeur"
                                                       name="candidature_anciennete_min_valeur" min="0" max="100" step="1"
                                                       value="<?= (int)$candidatureConditions['anciennete_min_valeur'] ?>" required>
                                                <span class="input-group-text">année(s)</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Paiement du droit de candidature -->
                            <div class="border rounded-3 p-3 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="candPaiementRequis" name="candidature_paiement_requis" value="1"
                                           <?= $candidatureConditions['paiement_requis'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="candPaiementRequis">
                                        Exiger le paiement du droit de candidature (<code>droit_candidature_paye</code>)
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    Si activé, toute candidature dont le droit n'est pas déclaré payé sera signalée comme non conforme avec le motif « Le droit de candidature n'a pas encore été payé. »
                                </small>
                            </div>

                            <div class="d-flex justify-content-end pt-2">
                                <button type="submit" class="btn btn-primary px-4 fw-bold">
                                    <i class="bi bi-check-lg me-1"></i> Enregistrer les conditions d'éligibilité
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- =================================================================== -->
            <!-- COLONNE LATÉRALE : RÉCAPITULATIF ACTUEL & PARAMÈTRES GÉNÉRAUX      -->
            <!-- =================================================================== -->
            <div class="col-12 col-xl-4">
                <!-- Récapitulatif des règles actives -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6 fw-bold">
                            <i class="bi bi-clipboard-check me-2 text-success"></i> Règles actuellement appliquées
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold small text-uppercase text-muted mb-2">Inscription Membres (Électeurs)</h6>
                        <ul class="list-group list-group-flush mb-3 small">
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span>Âge minimum</span>
                                <?php if ($electorConditions['age_min_actif']): ?>
                                    <span class="badge bg-success text-white">Actif : <?= (int)$electorConditions['age_min_valeur'] ?> ans</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white">Désactivé</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span>Ancienneté minimum</span>
                                <?php if ($electorConditions['anciennete_min_actif']): ?>
                                    <span class="badge bg-success text-white">Actif : <?= (int)$electorConditions['anciennete_min_valeur'] ?> an(s)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white">Désactivé</span>
                                <?php endif; ?>
                            </li>
                        </ul>

                        <h6 class="fw-bold small text-uppercase text-muted mb-2">Dépôt de Candidature</h6>
                        <ul class="list-group list-group-flush small mb-0">
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span>Âge minimum candidat</span>
                                <?php if ($candidatureConditions['age_min_actif']): ?>
                                    <span class="badge bg-primary text-white">Actif : <?= (int)$candidatureConditions['age_min_valeur'] ?> ans</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white">Désactivé</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span>Ancienneté minimum candidat</span>
                                <?php if ($candidatureConditions['anciennete_min_actif']): ?>
                                    <span class="badge bg-primary text-white">Actif : <?= (int)$candidatureConditions['anciennete_min_valeur'] ?> an(s)</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white">Désactivé</span>
                                <?php endif; ?>
                            </li>
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <span>Droit de candidature payé</span>
                                <?php if ($candidatureConditions['paiement_requis']): ?>
                                    <span class="badge bg-success text-white">Exigé</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white">Facultatif</span>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Identité et Coordonnées de l'application -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6 fw-bold">
                            <i class="bi bi-gear me-2 text-primary"></i> Identité et Coordonnées du SAS
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="post" action="index.php?page=<?= SecurityHelper::e($currentPage) ?>">
                            <?= SessionHelper::csrfField() ?>
                            <input type="hidden" name="form_type" value="general">
                            <input type="hidden" name="redirect_page" value="<?= SecurityHelper::e($currentPage) ?>">

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Nom de l'application</label>
                                <input type="text" name="app_name" class="form-control form-control-sm" value="<?= SecurityHelper::e($settings['app_name'] ?? 'SAS Élection') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Devise / Sous-titre du SAS</label>
                                <input type="text" name="app_subtitle" class="form-control form-control-sm" value="<?= SecurityHelper::e($settings['app_subtitle'] ?? 'Tous unis au service du Christ') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Email de contact du bureau</label>
                                <input type="email" name="contact_email" class="form-control form-control-sm" value="<?= SecurityHelper::e($settings['contact_email'] ?? '') ?>" placeholder="contact@sas-eglise.org">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Téléphone d'assistance électorale</label>
                                <input type="tel" name="contact_phone" class="form-control form-control-sm" value="<?= SecurityHelper::e($settings['contact_phone'] ?? '') ?>" placeholder="+225 07 00 00 00 00">
                            </div>

                            <button type="submit" class="btn btn-outline-primary btn-sm w-100 fw-semibold">
                                <i class="bi bi-check-lg me-1"></i> Mettre à jour l'identité
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chkCommunes = document.getElementById('candCommunes');
    const ageMinActif = document.getElementById('ageMinActif');
    const ageMinValeur = document.getElementById('ageMinValeur');
    const ancMinActif = document.getElementById('ancMinActif');
    const ancMinValeur = document.getElementById('ancMinValeur');

    const candAgeActif = document.getElementById('candAgeActif');
    const candAgeValeur = document.getElementById('candAgeValeur');
    const candAncActif = document.getElementById('candAncActif');
    const candAncValeur = document.getElementById('candAncValeur');

    function syncFromElector() {
        if (chkCommunes && chkCommunes.checked) {
            candAgeActif.checked = ageMinActif.checked;
            candAgeValeur.value = ageMinValeur.value;
            candAncActif.checked = ancMinActif.checked;
            candAncValeur.value = ancMinValeur.value;
        }
    }

    if (chkCommunes) {
        chkCommunes.addEventListener('change', syncFromElector);
        [ageMinActif, ageMinValeur, ancMinActif, ancMinValeur].forEach(el => {
            if (el) el.addEventListener('input', syncFromElector);
            if (el) el.addEventListener('change', syncFromElector);
        });

        // Si l'administrateur modifie manuellement un champ spécifique de candidature, décocher "conditions communes"
        [candAgeActif, candAgeValeur, candAncActif, candAncValeur].forEach(el => {
            if (el) {
                el.addEventListener('input', function () {
                    chkCommunes.checked = false;
                });
                el.addEventListener('change', function () {
                    if (
                        candAgeActif.checked !== ageMinActif.checked ||
                        candAgeValeur.value !== ageMinValeur.value ||
                        candAncActif.checked !== ancMinActif.checked ||
                        candAncValeur.value !== ancMinValeur.value
                    ) {
                        chkCommunes.checked = false;
                    }
                });
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
