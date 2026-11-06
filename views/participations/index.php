<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Helpers/AuthHelper.php';
require_once __DIR__ . '/../../app/Models/Election.php';
require_once __DIR__ . '/../../app/Models/Participation.php';

SessionHelper::start();
AuthHelper::requireLogin();

if (!isset($elections) || !is_array($elections)) {
    $electionModel = new Election();
    $elections = $electionModel->getAll();
}
$selectedElectionId = $selectedElectionId ?? (!empty($elections) ? (int)$elections[0]['id'] : null);
$currentElection = $currentElection ?? ($selectedElectionId ? (new Election())->findById($selectedElectionId) : null);
$participations = $participations ?? ($selectedElectionId ? (new Participation())->getByElection($selectedElectionId) : []);

$pageTitle = 'Registre des participations (Émargement)';
include __DIR__ . '/../layout/head.php';
?>

<main class="dashboard-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- Heading -->
        <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="page-heading-copy">
                <span class="page-icon"><i class="bi bi-card-checklist" aria-hidden="true"></i></span>
                <div>
                    <h1 class="h3 mb-1">Registre de Participations</h1>
                    <p class="text-muted mb-0">Consultation nominative des membres ayant exercé leur droit de vote.</p>
                </div>
            </div>
            <?php if ($currentElection): ?>
                <div class="heading-actions">
                    <a href="index.php?page=resultats_admin&election_id=<?= SecurityHelper::encryptId($currentElection['id']) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-bar-chart me-1"></i> Voir les résultats
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <!-- Alerte de confidentialité -->
        <div class="alert alert-info d-flex align-items-center mb-4">
            <i class="bi bi-shield-lock-fill me-3 fs-4 text-info"></i>
            <div>
                <strong>Confidentialité du vote garantie :</strong> Ce registre atteste uniquement de l'émargement (le fait qu'un membre a participé au vote). Conformément au protocole de vote secret, le choix du candidat n'est pas associé à l'identité du votant dans la base de données.
            </div>
        </div>

        <!-- Filtre élection et recherche -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="get" action="index.php" class="row g-2 align-items-end">
                    <input type="hidden" name="page" value="participations">

                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1">Sélectionnez l'élection</label>
                        <select name="election_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($elections as $el): ?>
                                <option value="<?= SecurityHelper::encryptId($el['id']) ?>" <?= ($selectedElectionId == $el['id']) ? 'selected' : '' ?>>
                                    <?= SecurityHelper::e($el['titre']) ?> (<?= $el['statut'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted mb-1">Tour de scrutin</label>
                        <select name="tour_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Tous les tours</option>
                            <?php foreach (($tours ?? []) as $tItem): ?>
                                <option value="<?= (int)$tItem['id'] ?>" <?= (!empty($selectedTourId) && (int)$selectedTourId === (int)$tItem['id']) ? 'selected' : '' ?>>
                                    <?= SecurityHelper::e($tItem['libelle'] ?? ('Tour ' . $tItem['numero_tour'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted mb-1">Recherche par nom, prénom ou téléphone</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Rechercher un votant..." value="<?= SecurityHelper::e($_GET['search'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-funnel"></i> Filtrer
                        </button>
                        <a href="index.php?page=participations&election_id=<?= SecurityHelper::encryptId($selectedElectionId) ?>" class="btn btn-outline-secondary btn-sm" title="Réinitialiser">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tableau des émargements -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-people-fill me-2 text-primary"></i> Liste des votants ayant émargé (<?= count($participations) ?>)
                </h5>
                <?php if ($currentElection): ?>
                    <span class="badge bg-primary px-3 py-2">
                        Élection : <?= SecurityHelper::e($currentElection['titre']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>N°</th>
                            <th>Nom & Prénoms</th>
                            <th>Numéro de téléphone</th>
                            <th>Tour de scrutin</th>
                            <th>Responsabilité SAS</th>
                            <th>Statut émargement</th>
                            <th>Date et heure de vote</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($participations)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    Aucun vote enregistré pour cette élection pour le moment.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($participations as $index => $p): ?>
                                <tr>
                                    <td class="text-muted small"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= SecurityHelper::e($p['membre_nom'] . ' ' . $p['membre_prenoms']) ?></div>
                                    </td>
                                    <td>
                                        <code><?= SecurityHelper::e($p['membre_telephone']) ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary">
                                            <?= SecurityHelper::e($p['tour_libelle'] ?? ('Tour ' . ($p['numero_tour'] ?? 1))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= SecurityHelper::e($p['responsabilite_sas'] ?? 'Membre') ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-success text-white px-2 py-1">
                                            <i class="bi bi-check2-circle me-1"></i> A voté
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <i class="bi bi-clock me-1 text-primary"></i> <?= date('d/m/Y à H:i:s', strtotime($p['date_vote'])) ?>
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

<?php include __DIR__ . '/../layout/footer.php'; ?>
