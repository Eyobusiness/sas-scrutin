<?php
declare(strict_types=1);

/** @var array|null $activeElection */
$activeElection = $activeElection ?? null;
/** @var array|null $activeElectionStats */
$activeElectionStats = $activeElectionStats ?? null;
/** @var array $membreStats */
$membreStats = $membreStats ?? ['total' => 0, 'eligibles' => 0, 'non_eligibles' => 0, 'actifs' => 0, 'inactifs' => 0];
/** @var array $candidatStats */
$candidatStats = $candidatStats ?? ['total' => 0, 'validees' => 0, 'en_attente' => 0, 'rejetees' => 0];
/** @var array $electionStats */
$electionStats = $electionStats ?? ['total' => 0, 'ouvertes' => 0, 'cloturees' => 0, 'brouillons' => 0];
/** @var array $recentMembres */
$recentMembres = $recentMembres ?? [];
/** @var array $recentCandidats */
$recentCandidats = $recentCandidats ?? [];

$pageTitle = 'Tableau de bord';
include __DIR__ . '/../layout/head.php';
?>

<main class="dashboard-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- En-tête de la page -->
        <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="page-heading-copy">
                <span class="page-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
                <div>
                    <h1 class="h3 mb-1">Tableau de bord</h1>
                    <p class="text-muted mb-0">Vue d'ensemble du scrutin et des statistiques électorales du SAS.</p>
                </div>
            </div>
            <div class="heading-actions d-flex gap-2">
                <a href="index.php?page=membres" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-people me-1"></i> Gérer les membres
                </a>
                <a href="index.php?page=elections" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Nouvelle élection
                </a>
            </div>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <!-- Alerte Élection en cours si active -->
        <?php if ($activeElection): ?>
            <div class="card border-0 shadow-sm mb-4 bg-primary text-white overflow-hidden">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <span class="badge bg-white text-primary mb-2 px-3 py-1 text-uppercase fw-bold">
                                <i class="bi bi-broadcast me-1"></i> Scrutin actuellement ouvert
                            </span>
                            <h2 class="h4 mb-1 text-white"><?= SecurityHelper::e($activeElection['titre']) ?></h2>
                            <p class="mb-0 text-white-50">
                                Clôture prévue le <?= date('d/m/Y à H:i', strtotime($activeElection['date_fin'])) ?>
                            </p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="index.php?page=resultats_admin&election_id=<?= $activeElection['id'] ?>" class="btn btn-light fw-semibold">
                                <i class="bi bi-bar-chart me-1"></i> Résultats en direct
                            </a>
                            <a href="index.php?page=participations&election_id=<?= $activeElection['id'] ?>" class="btn btn-outline-light">
                                <i class="bi bi-card-checklist me-1"></i> Émargement
                            </a>
                        </div>
                    </div>

                    <?php if ($activeElectionStats): ?>
                        <div class="mt-4 pt-3 border-top border-white-50 d-flex flex-wrap gap-4">
                            <div>
                                <small class="text-white-50 d-block">Votants enregistrés</small>
                                <span class="fs-4 fw-bold text-white"><?= $activeElectionStats['nb_votants'] ?></span>
                            </div>
                            <div>
                                <small class="text-white-50 d-block">Membres éligibles</small>
                                <span class="fs-4 fw-bold text-white"><?= $activeElectionStats['nb_eligibles'] ?></span>
                            </div>
                            <div>
                                <small class="text-white-50 d-block">Taux de participation</small>
                                <span class="fs-4 fw-bold text-white"><?= $activeElectionStats['taux'] ?>%</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Cartes statistiques -->
        <div class="row g-3 mb-4">
            <!-- 1. Total Membres -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">Total Membres</span>
                            <span class="badge bg-primary-subtle text-primary p-2 rounded-3"><i class="bi bi-people fs-6"></i></span>
                        </div>
                        <h3 class="fw-bold mb-1"><?= $membreStats['total'] ?></h3>
                        <small class="text-muted">
                            <span class="text-success fw-bold"><?= $membreStats['actifs'] ?></span> actifs •
                            <span class="text-secondary"><?= $membreStats['inactifs'] ?></span> inactifs
                        </small>
                    </div>
                </div>
            </div>

            <!-- 2. Éligibles au vote -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">Éligibles au vote</span>
                            <span class="badge bg-success-subtle text-success p-2 rounded-3"><i class="bi bi-check2-circle fs-6"></i></span>
                        </div>
                        <h3 class="fw-bold mb-1 text-success"><?= $membreStats['eligibles'] ?></h3>
                        <small class="text-muted">
                            <span class="text-danger fw-bold"><?= $membreStats['non_eligibles'] ?></span> non éligibles
                        </small>
                    </div>
                </div>
            </div>

            <!-- 3. Candidatures -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">Candidatures</span>
                            <span class="badge bg-info-subtle text-info p-2 rounded-3"><i class="bi bi-person-badge fs-6"></i></span>
                        </div>
                        <h3 class="fw-bold mb-1"><?= $candidatStats['total'] ?></h3>
                        <small class="text-muted">
                            <span class="text-success fw-bold"><?= $candidatStats['validees'] ?></span> validées •
                            <span class="text-warning fw-bold"><?= $candidatStats['en_attente'] ?></span> en attente
                        </small>
                    </div>
                </div>
            </div>

            <!-- 4. Élections organisées -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small fw-semibold text-uppercase">Élections</span>
                            <span class="badge bg-warning-subtle text-warning p-2 rounded-3"><i class="bi bi-archive fs-6"></i></span>
                        </div>
                        <h3 class="fw-bold mb-1"><?= $electionStats['total'] ?></h3>
                        <small class="text-muted">
                            <span class="text-success fw-bold"><?= $electionStats['ouvertes'] ?></span> ouverte(s) •
                            <span class="text-secondary"><?= $electionStats['cloturees'] ?></span> clôturée(s)
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section des élections récentes -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-calendar-check me-2 text-primary"></i> Élections récentes
                </h5>
                <a href="index.php?page=elections" class="btn btn-sm btn-link text-decoration-none">Voir toutes</a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Titre de l'élection</th>
                            <th>Période du scrutin</th>
                            <th>Statut</th>
                            <th>Candidats</th>
                            <th>Votants</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentElections)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    Aucune élection enregistrée pour le moment.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentElections as $el): ?>
                                <tr>
                                    <td>
                                        <strong><?= SecurityHelper::e($el['titre']) ?></strong>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('d/m/Y H:i', strtotime($el['date_debut'])) ?> &rarr; <?= date('d/m/Y H:i', strtotime($el['date_fin'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($el['statut'] === 'ouverte'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> Ouverte
                                            </span>
                                        <?php elseif ($el['statut'] === 'preparation'): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">En préparation</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Clôturée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= $el['nb_candidats'] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= $el['nb_votants'] ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="index.php?page=resultats_admin&election_id=<?= SecurityHelper::encryptId($el['id']) ?>" class="btn btn-outline-secondary" title="Résultats">
                                                <i class="bi bi-bar-chart"></i>
                                            </a>
                                            <a href="index.php?page=participations&election_id=<?= SecurityHelper::encryptId($el['id']) ?>" class="btn btn-outline-secondary" title="Émargement">
                                                <i class="bi bi-card-checklist"></i>
                                            </a>
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

<?php include __DIR__ . '/../layout/footer.php'; ?>
