<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Helpers/AuthHelper.php';
require_once __DIR__ . '/../../app/Models/Membre.php';
require_once __DIR__ . '/../../app/Services/ParametreService.php';
require_once __DIR__ . '/../../app/Services/EligibilityService.php';

SessionHelper::start();
AuthHelper::requireLogin();

$parametreService = new ParametreService();
$eligibilityService = new EligibilityService(null, $parametreService);

if (!isset($membres) || !is_array($membres)) {
    $membreModel = new Membre();
    $membres = $membreModel->getAll();
    foreach ($membres as &$m) {
        $m['age_calcule'] = $eligibilityService->calculateAge($m['date_naissance'] ?? null);
    }
    unset($m);
}
if (!isset($stats) || !is_array($stats)) {
    $membreModel = new Membre();
    $stats = $membreModel->getStats();
}
if (!isset($electorConditions) || !is_array($electorConditions)) {
    $electorConditions = $parametreService->getElectorConditions();
}
$inscriptionsLocked = $inscriptionsLocked ?? false;
$lockMessage = $lockMessage ?? 'Les inscriptions pour cette élection sont désormais clôturées.';

$maxDate = date('Y-m-d');
$pageTitle = 'Gestion des membres';
include __DIR__ . '/../layout/head.php';
?>

<main class="dashboard-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Heading -->
        <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="page-heading-copy">
                <span class="page-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                <div>
                    <h1 class="h3 mb-1">Liste électorale</h1>
                    <p class="text-muted mb-0">Gestion du fichier électoral</p>
                </div>
            </div>
            <div class="heading-actions d-flex gap-2">
                <a href="index.php?page=settings_eligibilite" class="btn btn-outline-secondary">
                    <i class="bi bi-sliders me-1"></i> Conditions d'éligibilité
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMembreModal" <?= $inscriptionsLocked ? 'disabled' : '' ?>>
                    <i class="bi <?= $inscriptionsLocked ? 'bi-lock-fill' : 'bi-person-plus' ?> me-1"></i> Ajouter un membre
                </button>
            </div>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <?php if ($inscriptionsLocked): ?>
            <div class="alert alert-warning border-warning-subtle d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-lock-fill fs-5 text-warning"></i>
                <div>
                    <strong>Liste électorale verrouillée pour le scrutin en cours :</strong>
                    <?= SecurityHelper::e($lockMessage) ?>
                    L'ajout de nouveaux membres et la modification de l'éligibilité sont temporairement désactivés.
                </div>
            </div>
        <?php endif; ?>

        <!-- Bandeau des conditions d'éligibilité configurées -->
        <!--<div class="alert alert-light border shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 py-2 px-3">-->
        <!--    <div class="small">-->
        <!--        <i class="bi bi-shield-check text-primary me-1"></i>-->
        <!--        <strong>Conditions d'accès à la liste électorale :</strong>-->
        <!--        <span class="ms-2">-->
        <!--            Âge minimum :-->
        <!--            <?php if ($electorConditions['age_min_actif']): ?>-->
        <!--                <span class="badge bg-primary-subtle text-primary"><?= (int)$electorConditions['age_min_valeur'] ?> ans</span>-->
        <!--            <?php else: ?>-->
        <!--                <span class="badge bg-secondary-subtle text-secondary">Désactivé</span>-->
        <!--            <?php endif; ?>-->
        <!--        </span>-->
        <!--        <span class="ms-2">-->
        <!--            Ancienneté minimum :-->
        <!--            <?php if ($electorConditions['anciennete_min_actif']): ?>-->
        <!--                <span class="badge bg-primary-subtle text-primary"><?= (int)$electorConditions['anciennete_min_valeur'] ?> année(s)</span>-->
        <!--            <?php else: ?>-->
        <!--                <span class="badge bg-secondary-subtle text-secondary">Désactivée</span>-->
        <!--            <?php endif; ?>-->
        <!--        </span>-->
        <!--    </div>-->
        <!--    <a href="index.php?page=settings_eligibilite" class="small text-decoration-none fw-semibold">-->
        <!--        Modifier les règles &rarr;-->
        <!--    </a>-->
        <!--</div>-->

        <!-- Barre de recherche et filtres -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="get" action="index.php" class="row g-2 align-items-end">
                    <input type="hidden" name="page" value="membres">

                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1">Recherche par nom, prénom ou téléphone</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Rechercher..." value="<?= SecurityHelper::e($_GET['search'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1">Éligibilité au vote</label>
                        <select name="eligibilite" class="form-select form-select-sm">
                            <option value="">Tous les membres</option>
                            <option value="oui" <?= (($_GET['eligibilite'] ?? '') === 'oui') ? 'selected' : '' ?>>Éligible (Oui)</option>
                            <option value="non" <?= (($_GET['eligibilite'] ?? '') === 'non') ? 'selected' : '' ?>>Non éligible (Non)</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1">Statut du compte</label>
                        <select name="statut" class="form-select form-select-sm">
                            <option value="">Tous les statuts</option>
                            <option value="actif" <?= (($_GET['statut'] ?? '') === 'actif') ? 'selected' : '' ?>>Actif</option>
                            <option value="inactif" <?= (($_GET['statut'] ?? '') === 'inactif') ? 'selected' : '' ?>>Inactif</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-funnel"></i> Filtrer
                        </button>
                        <a href="index.php?page=membres" class="btn btn-outline-secondary btn-sm" title="Réinitialiser">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tableau des membres -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-list-check me-2 text-primary"></i> Liste des membres (<?= count($membres) ?>)
                </h5>
                <div class="d-flex gap-2">
                    <span class="badge bg-success text-white">Éligibles : <?= $stats['eligibles'] ?></span>
                    <span class="badge bg-danger text-white">Non éligibles : <?= $stats['non_eligibles'] ?></span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>N°</th>
                            <th>Membre &amp; Âge</th>
                            <th>Téléphone</th>
                            <th>Ancienneté</th>
                            <th>Profession / Rôle SAS</th>
                            <th>Statut</th>
                            <th>Éligibilité &amp; Motifs</th>
                            <th>Inscrit le</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($membres)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    Aucun membre correspondant aux critères.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($membres as $index => $m): ?>
                                <?php $age = $m['age_calcule'] ?? $eligibilityService->calculateAge($m['date_naissance'] ?? null); ?>
                                <tr>
                                    <td class="text-muted small"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= SecurityHelper::e($m['nom'] . ' ' . $m['prenoms']) ?></div>
                                        <?php if (!empty($m['date_naissance'])): ?>
                                            <small class="text-muted">
                                                Né(e) le <?= date('d/m/Y', strtotime($m['date_naissance'])) ?>
                                                <?php if ($age !== null): ?>
                                                    &bull; <strong><?= $age ?> ans</strong>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted fst-italic">Date de naissance non renseignée</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?= SecurityHelper::e($m['telephone']) ?></code>
                                    </td>
                                    <td>
                                        <?php if ($m['anciennete'] !== null && $m['anciennete'] !== ''): ?>
                                            <span class="badge bg-light text-dark border">
                                                <?= (int)$m['anciennete'] ?> année(s)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?= SecurityHelper::e($m['fonction_pro'] ?? '—') ?></div>
                                        <?php if (!empty($m['responsabilite_sas'])): ?>
                                            <small class="text-primary fw-semibold"><i class="bi bi-bookmark me-1"></i><?= SecurityHelper::e($m['responsabilite_sas']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($m['statut'] === 'actif'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 240px;">
                                        <?php if ($m['eligibilite_a_voter'] === 'oui'): ?>
                                            <span class="badge bg-success text-white px-2 py-1">
                                                <i class="bi bi-check-circle me-1"></i> Éligible
                                            </span>
                                            <?php if (!empty($m['valideur_prenoms'])): ?>
                                                <small class="d-block text-muted mt-1" style="font-size: 0.72rem;">Validé par <?= SecurityHelper::e($m['valideur_prenoms']) ?></small>
                                            <?php else: ?>
                                                <small class="d-block text-success mt-1" style="font-size: 0.72rem;">Validation automatique</small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-danger text-white px-2 py-1">
                                                <i class="bi bi-x-circle me-1"></i> Non éligible
                                            </span>
                                            <?php if (!empty($m['motif_non_eligibilite'])): ?>
                                                <div class="small text-danger mt-1" style="font-size: 0.75rem; line-height: 1.25;">
                                                    <?= SecurityHelper::e($m['motif_non_eligibilite']) ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= date('d/m/Y', strtotime($m['date_enregistrement'])) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}'>
                                                Options
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <!-- Valider/Invalider éligibilité -->
                                                <li>
                                                    <button type="button" class="dropdown-item <?= $inscriptionsLocked ? 'disabled text-muted' : '' ?>" 
                                                            <?= $inscriptionsLocked ? 'disabled' : 'data-bs-toggle="modal" data-bs-target="#eligibiliteModal"' ?>
                                                            data-id="<?= $m['id'] ?>"
                                                            data-nom="<?= SecurityHelper::e($m['nom'] . ' ' . $m['prenoms']) ?>"
                                                            data-eligibilite="<?= $m['eligibilite_a_voter'] ?>"
                                                            data-motif="<?= SecurityHelper::e($m['motif_non_eligibilite'] ?? '') ?>">
                                                        <i class="bi <?= $inscriptionsLocked ? 'bi-lock-fill text-muted' : 'bi-patch-check text-primary' ?> me-2"></i> Valider / Modifier l'éligibilité
                                                    </button>
                                                </li>
                                                <!-- Modifier informations -->
                                                <li>
                                                    <button type="button" class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editMembreModal"
                                                            data-id="<?= $m['id'] ?>"
                                                            data-nom="<?= SecurityHelper::e($m['nom']) ?>"
                                                            data-prenoms="<?= SecurityHelper::e($m['prenoms']) ?>"
                                                            data-telephone="<?= SecurityHelper::e($m['telephone']) ?>"
                                                            data-date-naissance="<?= SecurityHelper::e($m['date_naissance'] ?? '') ?>"
                                                            data-anciennete="<?= SecurityHelper::e((string)($m['anciennete'] ?? '')) ?>"
                                                            data-fonction-pro="<?= SecurityHelper::e($m['fonction_pro'] ?? '') ?>"
                                                            data-responsabilite-sas="<?= SecurityHelper::e($m['responsabilite_sas'] ?? '') ?>"
                                                            data-statut="<?= $m['statut'] ?>">
                                                        <i class="bi bi-pencil me-2 text-secondary"></i> Modifier informations
                                                    </button>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <!-- Basculer statut actif/inactif -->
                                                <li>
                                                    <form method="post" action="index.php?page=membre_toggle_statut" onsubmit="return confirm('Confirmez-vous le changement de statut de ce membre ?');">
                                                        <?= SessionHelper::csrfField() ?>
                                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                        <button type="submit" class="dropdown-item <?= ($m['statut'] === 'actif') ? 'text-warning' : 'text-success' ?>">
                                                            <i class="bi bi-power me-2"></i> <?= ($m['statut'] === 'actif') ? 'Désactiver le compte' : 'Activer le compte' ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<!-- Modal Ajouter un membre -->
<div class="modal fade" id="addMembreModal" tabindex="-1" aria-labelledby="addMembreLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="index.php?page=membre_store">
                <?= SessionHelper::csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="addMembreLabel"><i class="bi bi-person-plus me-2 text-primary"></i> Ajouter un membre</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="nom" class="form-control" required placeholder="Ex: KOUADIO">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prénoms <span class="text-danger">*</span></label>
                            <input type="text" name="prenoms" class="form-control" required placeholder="Ex: Ferdinand">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                            <input type="tel" name="telephone" class="form-control" required placeholder="Ex: 0700000000">
                            <small class="text-muted">Servira d'identifiant pour le vote.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date de naissance</label>
                            <input type="date" name="date_naissance" class="form-control" max="<?= $maxDate ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ancienneté déclarée</label>
                            <div class="input-group">
                                <input type="number" name="anciennete" class="form-control" min="0" max="100" step="1" placeholder="Ex: 2">
                                <span class="input-group-text">année(s)</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fonction professionnelle</label>
                            <input type="text" name="fonction_pro" class="form-control" placeholder="Ex: Enseignant, Ingénieur, etc.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Responsabilité dans le SAS</label>
                            <input type="text" name="responsabilite_sas" class="form-control" placeholder="Ex: Trésorier, Membre, etc.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Statut du membre</label>
                            <select name="statut" class="form-select">
                                <option value="actif" selected>Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Éligibilité au vote</label>
                            <select name="eligibilite_a_voter" id="addEligibilite" class="form-select">
                                <option value="auto" selected>Calculer automatiquement selon les paramètres</option>
                                <option value="oui">Forcer : Éligible (Oui)</option>
                                <option value="non">Forcer : Non éligible (Non)</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="addMotifNonEligibleGroup">
                            <label class="form-label">Motif de non-éligibilité (si Non forcé)</label>
                            <input type="text" name="motif_non_eligibilite" class="form-control" placeholder="Ex: En attente de régularisation...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Enregistrer le membre</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier un membre -->
<div class="modal fade" id="editMembreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="index.php?page=membre_update">
                <?= SessionHelper::csrfField() ?>
                <input type="hidden" name="id" id="editId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2 text-primary"></i> Modifier le membre</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="nom" id="editNom" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prénoms <span class="text-danger">*</span></label>
                            <input type="text" name="prenoms" id="editPrenoms" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                            <input type="tel" name="telephone" id="editTelephone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date de naissance</label>
                            <input type="date" name="date_naissance" id="editDateNaissance" class="form-control" max="<?= $maxDate ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ancienneté déclarée</label>
                            <div class="input-group">
                                <input type="number" name="anciennete" id="editAnciennete" class="form-control" min="0" max="100" step="1">
                                <span class="input-group-text">année(s)</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fonction professionnelle</label>
                            <input type="text" name="fonction_pro" id="editFonctionPro" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Responsabilité dans le SAS</label>
                            <input type="text" name="responsabilite_sas" id="editResponsabiliteSas" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Statut</label>
                            <select name="statut" id="editStatut" class="form-select">
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="recalculer_eligibilite" value="1" id="editRecalculer">
                                <label class="form-check-label small" for="editRecalculer">
                                    Recalculer automatiquement l'éligibilité au vote selon les conditions configurées (âge et ancienneté)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Validation Éligibilité au vote -->
<div class="modal fade" id="eligibiliteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="index.php?page=membre_toggle_eligibilite">
                <?= SessionHelper::csrfField() ?>
                <input type="hidden" name="id" id="eligibiliteId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-patch-check me-2 text-primary"></i> Validation de l'éligibilité au vote</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Membre concerné : <strong id="eligibiliteNom" class="text-primary"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Décision du bureau :</label>
                        <select name="eligibilite" id="eligibiliteSelect" class="form-select" required>
                            <option value="oui">Éligible au vote (Oui)</option>
                            <option value="non">Non éligible (Non)</option>
                        </select>
                    </div>
                    <div class="mb-3" id="motifGroup">
                        <label class="form-label">Motif en cas de non-éligibilité :</label>
                        <textarea name="motif_non_eligibilite" id="eligibiliteMotif" class="form-control" rows="2" placeholder="Ex: Âge ou ancienneté insuffisante..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Valider la décision</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editMembreModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('editId').value = btn.getAttribute('data-id');
            document.getElementById('editNom').value = btn.getAttribute('data-nom');
            document.getElementById('editPrenoms').value = btn.getAttribute('data-prenoms');
            document.getElementById('editTelephone').value = btn.getAttribute('data-telephone');
            document.getElementById('editDateNaissance').value = btn.getAttribute('data-date-naissance');
            document.getElementById('editAnciennete').value = btn.getAttribute('data-anciennete');
            document.getElementById('editFonctionPro').value = btn.getAttribute('data-fonction-pro');
            document.getElementById('editResponsabiliteSas').value = btn.getAttribute('data-responsabilite-sas');
            document.getElementById('editStatut').value = btn.getAttribute('data-statut');
        });
    }

    const eligModal = document.getElementById('eligibiliteModal');
    if (eligModal) {
        eligModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('eligibiliteId').value = btn.getAttribute('data-id');
            document.getElementById('eligibiliteNom').textContent = btn.getAttribute('data-nom');
            const elig = btn.getAttribute('data-eligibilite');
            document.getElementById('eligibiliteSelect').value = elig;
            document.getElementById('eligibiliteMotif').value = btn.getAttribute('data-motif') || '';
        });
    }
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
