<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Helpers/AuthHelper.php';
require_once __DIR__ . '/../../app/Models/Candidat.php';
require_once __DIR__ . '/../../app/Models/Election.php';
require_once __DIR__ . '/../../app/Services/CandidatureService.php';

SessionHelper::start();
AuthHelper::requireLogin();

$candidatureService = new CandidatureService();

if (!isset($candidats) || !is_array($candidats)) {
    $candidatModel = new Candidat();
    $rawCandidats = $candidatModel->getAll();
    $candidats = [];
    foreach ($rawCandidats as $rc) {
        $candidats[] = $candidatureService->enrichCandidat($rc);
    }
}
if (!isset($elections) || !is_array($elections)) {
    $electionModel = new Election();
    $elections = $electionModel->getAll();
}
if (!isset($stats) || !is_array($stats)) {
    $candidatModel = new Candidat();
    $stats = $candidatModel->getStats();
}
if (!isset($candidatureConditions) || !is_array($candidatureConditions)) {
    $candidatureConditions = $candidatureService->getParametreService()->getCandidatureConditions();
}
$candidaturesLocked = $candidaturesLocked ?? false;
$candidatureLockMessage = $candidatureLockMessage ?? 'La période de dépôt des candidatures pour cette élection est clôturée. Aucune nouvelle candidature ne peut être enregistrée.';
$electionId = $electionId ?? (!empty($_GET['election_id']) ? SecurityHelper::decryptId($_GET['election_id']) : null);
$maxDate = date('Y-m-d');

$pageTitle = 'Gestion des candidatures';
include __DIR__ . '/../layout/head.php';
?>

<main class="dashboard-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Heading -->
        <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="page-heading-copy">
                <span class="page-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
                <div>
                    <h1 class="h3 mb-1">Candidatures</h1>
                    <p class="text-muted mb-0">Vérification d'éligibilité, contrôle du droit de candidature</p>
                </div>
            </div>
            <div class="heading-actions d-flex gap-2">
                <a href="index.php?page=settings_eligibilite" class="btn btn-outline-secondary">
                    <i class="bi bi-sliders me-1"></i> Conditions configurées
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCandidatModal" <?= $candidaturesLocked ? 'disabled' : '' ?>>
                    <i class="bi <?= $candidaturesLocked ? 'bi-lock-fill' : 'bi-person-plus' ?> me-1"></i> Ajouter un candidat
                </button>
            </div>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <?php if ($candidaturesLocked): ?>
            <div class="alert alert-warning border-warning-subtle d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-lock-fill fs-5 text-warning"></i>
                <div>
                    <strong>Dépôt de candidatures verrouillé :</strong>
                    <?= SecurityHelper::e($candidatureLockMessage) ?>
                    Les dossiers existants restent consultables ci-dessous.
                </div>
            </div>
        <?php endif; ?>

        <!-- Bandeau des conditions de candidature configurées -->
        <!--<div class="alert alert-light border shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4 py-2 px-3">-->
        <!--    <div class="small">-->
        <!--        <i class="bi bi-award text-primary me-1"></i>-->
        <!--        <strong>Conditions de candidature configurées :</strong>-->
        <!--        <span class="ms-2">-->
        <!--            Âge min. :-->
        <!--            <?php if ($candidatureConditions['age_min_actif']): ?>-->
        <!--                <span class="badge bg-primary-subtle text-primary"><?= (int)$candidatureConditions['age_min_valeur'] ?> ans</span>-->
        <!--            <?php else: ?>-->
        <!--                <span class="badge bg-secondary-subtle text-secondary">Désactivé</span>-->
        <!--            <?php endif; ?>-->
        <!--        </span>-->
        <!--        <span class="ms-2">-->
        <!--            Ancienneté min. :-->
        <!--            <?php if ($candidatureConditions['anciennete_min_actif']): ?>-->
        <!--                <span class="badge bg-primary-subtle text-primary"><?= (int)$candidatureConditions['anciennete_min_valeur'] ?> année(s)</span>-->
        <!--            <?php else: ?>-->
        <!--                <span class="badge bg-secondary-subtle text-secondary">Désactivée</span>-->
        <!--            <?php endif; ?>-->
        <!--        </span>-->
        <!--        <span class="ms-2">-->
        <!--            Paiement du droit :-->
        <!--            <?php if ($candidatureConditions['paiement_requis']): ?>-->
        <!--                <span class="badge bg-primary-subtle text-primary">Exigé</span>-->
        <!--            <?php else: ?>-->
        <!--                <span class="badge bg-secondary-subtle text-secondary">Non exigé</span>-->
        <!--            <?php endif; ?>-->
        <!--        </span>-->
        <!--    </div>-->
        <!--    <a href="index.php?page=settings_eligibilite" class="small text-decoration-none fw-semibold">-->
        <!--        Configurer &rarr;-->
        <!--    </a>-->
        <!--</div>-->

        <!-- Filtres -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="get" action="index.php" class="row g-2 align-items-end">
                    <input type="hidden" name="page" value="candidats">

                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1">Élection</label>
                        <select name="election_id" class="form-select form-select-sm">
                            <option value="">Toutes les élections</option>
                            <?php foreach ($elections as $el): ?>
                                <option value="<?= SecurityHelper::encryptId($el['id']) ?>" <?= (!empty($electionId) && $electionId == $el['id']) ? 'selected' : '' ?>>
                                    <?= SecurityHelper::e($el['titre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1">Statut de la candidature</label>
                        <select name="statut" class="form-select form-select-sm">
                            <option value="">Tous les statuts</option>
                            <option value="en_attente" <?= (($_GET['statut'] ?? '') === 'en_attente') ? 'selected' : '' ?>>Candidature en attente</option>
                            <option value="validee" <?= (($_GET['statut'] ?? '') === 'validee') ? 'selected' : '' ?>>Candidature validée (Sur le bulletin)</option>
                            <option value="rejetee" <?= (($_GET['statut'] ?? '') === 'rejetee') ? 'selected' : '' ?>>Candidature rejetée</option>
                            <option value="retiree" <?= (($_GET['statut'] ?? '') === 'retiree') ? 'selected' : '' ?>>Retirée</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1">Recherche</label>
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Nom ou prénom..." value="<?= SecurityHelper::e($_GET['search'] ?? '') ?>">
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-funnel"></i> Filtrer
                        </button>
                        <a href="index.php?page=candidats" class="btn btn-outline-secondary btn-sm" title="Réinitialiser">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tableau des candidatures -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-person-lines-fill me-2 text-primary"></i> Liste des candidatures (<?= count($candidats) ?>)
                </h5>
                <div class="d-flex flex-wrap gap-1">
                    <span class="badge bg-success text-white"><?= $stats['validees'] ?> Candidature(s) validée(s)</span>
                    <span class="badge bg-warning text-dark"><?= $stats['en_attente'] ?> Candidature(s) en attente</span>
                    <span class="badge bg-danger text-white"><?= $stats['rejetees'] ?> Rejetée(s)</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>N°</th>
                            <th>Candidat</th>
                            <th>Âge &amp; Ancienneté</th>
                            <th>Droit de candidature</th>
                            <th>Conformité &amp; Motifs</th>
                            <th>Statut</th>
                            <th>Voix</th>
                            <th class="text-end">Traitement Bureau</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($candidats)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Aucune candidature enregistrée pour ces critères.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($candidats as $index => $c): ?>
                                <?php
                                $age = $c['age_calcule'] ?? null;
                                $anc = ($c['anciennete'] !== null && $c['anciennete'] !== '') ? (int)$c['anciennete'] : null;
                                $droitPaye = !empty($c['droit_candidature_paye']);
                                $conforme = !empty($c['dossier_conforme']);
                                $motifsNonConf = $c['motifs_non_conformite'] ?? [];
                                ?>
                                <tr>
                                    <td class="text-muted small"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($c['photo'])): ?>
                                                <img src="uploads/candidats/<?= SecurityHelper::e($c['photo']) ?>" alt="Photo" class="candidate-avatar">
                                            <?php else: ?>
                                                <div class="avatar-sm rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                                                    <i class="bi bi-person"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?= SecurityHelper::e($c['nom'] . ' ' . $c['prenoms']) ?></div>
                                                <?php if (!empty($c['telephone'])): ?>
                                                    <small class="text-muted d-block"><?= SecurityHelper::e($c['telephone']) ?></small>
                                                <?php endif; ?>
                                                <span class="badge bg-light text-dark border mt-1" style="font-size: 0.7rem;">
                                                    <?= SecurityHelper::e($c['election_titre']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">
                                            <strong>Âge :</strong>
                                            <?php if ($age !== null): ?>
                                                <?= $age ?> ans <span class="text-muted">(<?= date('d/m/Y', strtotime($c['date_naissance'])) ?>)</span>
                                            <?php else: ?>
                                                <span class="text-muted">Non renseigné</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small mt-1">
                                            <strong>Ancienneté :</strong>
                                            <?php if ($anc !== null): ?>
                                                <?= $anc ?> année(s)
                                            <?php else: ?>
                                                <span class="text-muted">Non renseignée</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($droitPaye): ?>
                                            <span class="badge bg-success text-white px-2 py-1">
                                                <i class="bi bi-check-circle me-1"></i> Droit payé
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger text-white px-2 py-1">
                                                <i class="bi bi-x-circle me-1"></i> Droit non payé
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 240px;">
                                        <?php if ($conforme): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                <i class="bi bi-shield-check me-1"></i> Conditions respectées
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle mb-1">
                                                <i class="bi bi-exclamation-triangle me-1"></i> Conditions non remplies
                                            </span>
                                            <ul class="mb-0 ps-3 text-danger" style="font-size: 0.73rem; line-height: 1.25;">
                                                <?php foreach ($motifsNonConf as $mnc): ?>
                                                    <li><?= SecurityHelper::e($mnc) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['statut'] === 'validee'): ?>
                                            <span class="badge bg-success text-white px-2 py-1">
                                                <i class="bi bi-check-circle me-1"></i> Candidature validée
                                            </span>
                                            <small class="d-block text-success" style="font-size: 0.7rem;">Sur le bulletin</small>
                                        <?php elseif ($c['statut'] === 'en_attente'): ?>
                                            <span class="badge bg-warning text-dark px-2 py-1">
                                                <i class="bi bi-hourglass-split me-1"></i> Candidature en attente
                                            </span>
                                        <?php elseif ($c['statut'] === 'rejetee'): ?>
                                            <span class="badge bg-danger text-white px-2 py-1" data-bs-toggle="tooltip" title="<?= SecurityHelper::e($c['motif_rejet'] ?? 'Rejetée') ?>">
                                                <i class="bi bi-x-circle me-1"></i> Rejetée
                                            </span>
                                            <?php if (!empty($c['motif_rejet'])): ?>
                                                <small class="d-block text-danger text-truncate" style="max-width: 140px; font-size: 0.7rem;" title="<?= SecurityHelper::e($c['motif_rejet']) ?>">
                                                    <?= SecurityHelper::e($c['motif_rejet']) ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary text-white px-2 py-1">
                                                <i class="bi bi-arrow-return-left me-1"></i> Retirée
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border fw-bold"><?= $c['nb_voix'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#reviewModal"
                                                data-id="<?= $c['id'] ?>"
                                                data-nom="<?= SecurityHelper::e($c['nom'] . ' ' . $c['prenoms']) ?>"
                                                data-telephone="<?= SecurityHelper::e($c['telephone'] ?? '—') ?>"
                                                data-date-naissance="<?= !empty($c['date_naissance']) ? date('d/m/Y', strtotime($c['date_naissance'])) : 'Non renseignée' ?>"
                                                data-age="<?= $age !== null ? $age . ' ans' : 'Non calculable' ?>"
                                                data-anciennete="<?= $anc !== null ? $anc . ' année(s)' : 'Non renseignée' ?>"
                                                data-droit-paye="<?= $droitPaye ? '1' : '0' ?>"
                                                data-conforme="<?= $conforme ? '1' : '0' ?>"
                                                data-motifs-non-conf="<?= SecurityHelper::e(implode("\n", $motifsNonConf)) ?>"
                                                data-motif-candidature="<?= SecurityHelper::e($c['motif_candidature']) ?>"
                                                data-election="<?= SecurityHelper::e($c['election_titre']) ?>"
                                                data-statut="<?= $c['statut'] ?>"
                                                data-motif-rejet="<?= SecurityHelper::e($c['motif_rejet'] ?? '') ?>">
                                            <i class="bi bi-ui-checks me-1"></i> Examiner / Traiter
                                        </button>
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

<!-- Modal Ajouter un candidat -->
<div class="modal fade" id="addCandidatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="index.php?page=candidat_store" enctype="multipart/form-data">
                <?= SessionHelper::csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2 text-primary"></i> Ajouter une candidature</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Élection concernée <span class="text-danger">*</span></label>
                            <select name="election_id" class="form-select" required>
                                <option value="">-- Sélectionnez une élection --</option>
                                <?php foreach ($elections as $el): ?>
                                    <option value="<?= $el['id'] ?>">
                                        <?= SecurityHelper::e($el['titre']) ?> (<?= $el['statut'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" name="nom" class="form-control" required placeholder="Ex: KOUASSI">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prénoms <span class="text-danger">*</span></label>
                            <input type="text" name="prenoms" class="form-control" required placeholder="Ex: Jean-Luc">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" name="telephone" class="form-control" placeholder="Ex: 0700000000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date de naissance <span class="text-danger">*</span></label>
                            <input type="date" name="date_naissance" class="form-control" max="<?= $maxDate ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Ancienneté déclarée <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="anciennete" class="form-control" min="0" max="100" step="1" required placeholder="Ex: 3">
                                <span class="input-group-text">année(s)</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Droit de candidature payé <span class="text-danger">*</span></label>
                            <select name="droit_candidature_paye" class="form-select" required>
                                <option value="1">Oui — Droit payé</option>
                                <option value="0">Non — Droit non payé</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Photo du candidat (facultative)</label>
                            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <small class="text-muted">JPG, PNG ou WEBP (max 3 Mo).</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Statut initial</label>
                            <select name="statut" class="form-select">
                                <option value="en_attente" selected>Candidature en attente (Par défaut)</option>
                                <option value="validee">Candidature validée (Directement sur le bulletin)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Profession de foi / Motif de candidature <span class="text-danger">*</span></label>
                            <textarea name="motif_candidature" class="form-control" rows="3" required placeholder="Exposez la vision, le programme et la motivation du candidat..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Enregistrer le candidat</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Traiter une candidature (Bureau) -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="index.php?page=candidat_review">
                <?= SessionHelper::csrfField() ?>
                <input type="hidden" name="id" id="reviewId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-ui-checks me-2 text-primary"></i> Examen et décision du bureau</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Candidat :</small>
                            <strong id="reviewNom" class="fs-5 text-dark"></strong>
                            <small id="reviewTelephone" class="d-block text-muted"></small>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Élection :</small>
                            <span id="reviewElection" class="badge bg-light text-dark border"></span>
                        </div>
                    </div>

                    <!-- Informations d'éligibilité du candidat -->
                    <div class="card bg-light border p-3 mb-3">
                        <h6 class="fw-bold small text-uppercase text-muted mb-2">
                            <i class="bi bi-person-vcard me-1"></i> Vérification des critères du candidat
                        </h6>
                        <div class="row g-2 small">
                            <div class="col-sm-4">
                                <span class="text-muted d-block">Date de naissance</span>
                                <strong id="reviewDateNaissance"></strong>
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted d-block">Âge calculé</span>
                                <strong id="reviewAge" class="text-primary"></strong>
                            </div>
                            <div class="col-sm-4">
                                <span class="text-muted d-block">Ancienneté déclarée</span>
                                <strong id="reviewAnciennete" class="text-primary"></strong>
                            </div>
                        </div>
                        <hr class="my-2">
                        <div class="small">
                            <span class="text-muted d-block">Motivation / Profession de foi :</span>
                            <p id="reviewMotifCandidature" class="mb-0 text-dark fst-italic"></p>
                        </div>
                    </div>

                    <!-- Résultat de la vérification automatique -->
                    <div id="reviewConformiteAlert" class="alert py-2 small mb-3"></div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Paiement du droit de candidature :</label>
                            <select name="droit_candidature_paye" id="reviewDroitPaye" class="form-select">
                                <option value="1">Droit payé (Oui)</option>
                                <option value="0">Droit non payé (Non)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Décision définitive du bureau :</label>
                            <select name="statut" id="reviewStatut" class="form-select" required>
                                <option value="validee">Valider la candidature (Afficher sur le bulletin)</option>
                                <option value="rejetee">Rejeter la candidature</option>
                                <option value="en_attente">Maintenir en attente</option>
                                <option value="retiree">Marquer comme retirée</option>
                            </select>
                        </div>

                        <div class="col-12" id="motifRejetGroup">
                            <label class="form-label">Motif de rejet (obligatoire en cas de rejet) :</label>
                            <textarea name="motif_rejet" id="reviewMotifRejet" class="form-control" rows="2" placeholder="Ex: Âge insuffisant, ancienneté insuffisante, droit de candidature non payé..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Enregistrer la décision du bureau</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const reviewModal = document.getElementById('reviewModal');
    if (reviewModal) {
        reviewModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('reviewId').value = btn.getAttribute('data-id');
            document.getElementById('reviewNom').textContent = btn.getAttribute('data-nom');
            document.getElementById('reviewTelephone').textContent = 'Tél : ' + (btn.getAttribute('data-telephone') || '—');
            document.getElementById('reviewDateNaissance').textContent = btn.getAttribute('data-date-naissance');
            document.getElementById('reviewAge').textContent = btn.getAttribute('data-age');
            document.getElementById('reviewAnciennete').textContent = btn.getAttribute('data-anciennete');
            document.getElementById('reviewMotifCandidature').textContent = btn.getAttribute('data-motif-candidature');
            document.getElementById('reviewElection').textContent = btn.getAttribute('data-election');
            document.getElementById('reviewStatut').value = btn.getAttribute('data-statut');
            document.getElementById('reviewDroitPaye').value = btn.getAttribute('data-droit-paye') || '0';
            document.getElementById('reviewMotifRejet').value = btn.getAttribute('data-motif-rejet') || '';

            const conforme = btn.getAttribute('data-conforme') === '1';
            const motifsRaw = btn.getAttribute('data-motifs-non-conf') || '';
            const alertBox = document.getElementById('reviewConformiteAlert');

            if (conforme) {
                alertBox.className = 'alert alert-success py-2 small mb-3';
                alertBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> <strong>Dossier conforme aux conditions configurées</strong> (âge, ancienneté et paiement du droit de candidature respectés).';
            } else {
                alertBox.className = 'alert alert-warning py-2 small mb-3';
                const lines = motifsRaw.split('\n').filter(Boolean);
                let html = '<i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>Conditions non respectées :</strong><ul class="mb-0 mt-1">';
                lines.forEach(function (l) {
                    const li = document.createElement('li');
                    li.textContent = l;
                    html += li.outerHTML;
                });
                html += '</ul>';
                alertBox.innerHTML = html;

                // Pré-remplir le motif de rejet s'il est vide
                if (!document.getElementById('reviewMotifRejet').value && lines.length > 0) {
                    document.getElementById('reviewMotifRejet').value = lines.join(' ');
                }
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
