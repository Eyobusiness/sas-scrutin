<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Helpers/AuthHelper.php';
require_once __DIR__ . '/../../app/Models/Election.php';
require_once __DIR__ . '/../../app/Services/ResultatService.php';

SessionHelper::start();
AuthHelper::requireLogin();

// Si accédé directement sans passer par le contrôleur MVC
if (!isset($elections)) {
    $electionId = !empty($_GET['election_id']) ? (SecurityHelper::decryptId($_GET['election_id']) ?? (int)$_GET['election_id']) : null;
    $query = $electionId ? '&election_id=' . SecurityHelper::encryptId($electionId) : '';
    SecurityHelper::redirect('index.php?page=resultats_admin' . $query);
}

/** @var array $elections */
$elections = $elections ?? [];
/** @var int|null $selectedElectionId */
$selectedElectionId = $selectedElectionId ?? (!empty($elections) ? (int)$elections[0]['id'] : null);
/** @var array|null $donnees */
$donnees = $donnees ?? null;

if (!function_exists('getInitials')) {
    function getInitials(string $nom, string $prenoms): string {
        $n = mb_substr(trim($nom), 0, 1, 'UTF-8');
        $p = mb_substr(trim($prenoms), 0, 1, 'UTF-8');
        return strtoupper($n . $p);
    }
}

$pageTitle = 'Résultats du scrutin (Administration)';
$customCss = 'assets/css/admin-results.css';
include __DIR__ . '/../layout/head.php';
?>

<main class="dashboard-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <!-- En-tête de la page -->
        <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="page-heading-copy">
                <span class="page-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
                <div>
                    <h1 class="h3 mb-1">Dépouillement &amp; Résultats</h1>
                    <p class="text-muted mb-0">Suivi en direct des suffrages exprimés et statistiques.</p>
                </div>
            </div>
            <?php if (!empty($donnees) && isset($donnees['election']) && $donnees['election']['statut'] === 'ouverte'): ?>
                <div class="heading-actions">
                    <form method="post" action="index.php?page=election_change_statut" onsubmit="return confirm('Confirmez-vous la CLÔTURE définitive de cette élection ? Les résultats seront alors rendus publics.');">
                        <?= SessionHelper::csrfField() ?>
                        <input type="hidden" name="id" value="<?= $donnees['election']['id'] ?>">
                        <input type="hidden" name="statut" value="cloturee">
                        <button type="submit" class="btn btn-danger btn-sm">
                            <i class="bi bi-stop-circle me-1"></i> Clôturer l'élection
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <!-- Sélecteur d'élection discret et stylisé -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="get" action="index.php" class="row g-2 align-items-center justify-content-between">
                    <input type="hidden" name="page" value="resultats_admin">
                    <div class="col-12 col-md-auto d-flex align-items-center gap-2">
                        <label class="form-label small text-muted mb-0 fw-bold">
                            <i class="bi bi-calendar2-check text-primary me-1"></i> Choisir un scrutin :
                        </label>
                    </div>
                    <div class="col-12 col-md-7 col-lg-6">
                        <select name="election_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <?php foreach ($elections as $el): ?>
                                <option value="<?= SecurityHelper::encryptId($el['id']) ?>" <?= ($selectedElectionId == $el['id']) ? 'selected' : '' ?>>
                                    <?= SecurityHelper::e($el['titre']) ?> &mdash; (<?= ($el['statut'] === 'cloturee') ? 'Clôturée' : ($el['statut'] === 'ouverte' ? 'Ouverte' : 'En préparation') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto ms-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-repeat me-1"></i> Actualiser
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!$donnees): ?>
            <div class="card border-0 shadow-sm p-4 text-center my-3">
                <i class="bi bi-inbox fs-1 text-muted mb-2"></i>
                <h3 class="h5 fw-bold">Aucune élection sélectionnée</h3>
                <p class="text-muted mb-3">Aucune élection n'a encore été sélectionnée ou créée pour ce dépouillement.</p>
                <div>
                    <a href="index.php?page=elections" class="btn btn-primary btn-sm">
                        <i class="bi bi-calendar-event me-1"></i> Gérer les élections
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php 
                $el = $donnees['election'];
                $isOuverte = ($el['statut'] === 'ouverte');
                $isCloturee = ($el['statut'] === 'cloturee');
            ?>

            <!-- Bannière d'état officielle -->
            <div class="card border-0 shadow-sm mb-4 <?= $isOuverte ? 'border-start border-primary border-4' : 'border-start border-success border-4' ?>">
                <div class="card-body p-3 p-md-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h2 class="h4 mb-0 fw-bold"><?= SecurityHelper::e($el['titre']) ?></h2>
                            <?php if (!empty($donnees['tour'])): ?>
                                <span class="badge bg-dark px-2 py-1 rounded-pill">
                                    <i class="bi bi-flag-fill me-1"></i><?= SecurityHelper::e($donnees['tour']['libelle'] ?? ('Tour ' . $donnees['tour']['numero_tour'])) ?>
                                    (Max : <?= (int)($el['nombre_max_tours'] ?? 3) ?> tours)
                                </span>
                            <?php endif; ?>
                            <?php if ($isOuverte): ?>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
                                    <i class="bi bi-broadcast me-1"></i> Scrutin ouvert &bull; Dépouillement direct
                                </span>
                            <?php elseif ($isCloturee): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                    <i class="bi bi-check-circle-fill me-1"></i> Résultats définitifs certifiés
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 rounded-pill">
                                    En préparation
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted mb-0 small">
                            Période du tour : Du <?= date('d/m/Y à H:i', strtotime($donnees['tour']['date_debut_vote'] ?? $el['date_debut'])) ?> au <?= date('d/m/Y à H:i', strtotime($donnees['tour']['date_fin_vote'] ?? $el['date_fin'])) ?> &bull; Dernière actualisation : <?= SecurityHelper::e($donnees['derniere_mise_a_jour']) ?>
                        </p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="index.php?page=election_details&id=<?= SecurityHelper::encryptId($el['id']) ?>" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-sliders me-1"></i> Gérer tours &amp; électeurs
                        </a>
                        <a href="index.php?page=participations&election_id=<?= SecurityHelper::encryptId($el['id']) ?><?= !empty($donnees['tour']['id']) ? '&tour_id=' . (int)$donnees['tour']['id'] : '' ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-card-checklist me-1"></i> Registre d'émargement
                        </a>
                        <?php if ($isCloturee): ?>
                            <a href="index.php?page=resultats&tour_id=<?= (int)($donnees['tour']['id'] ?? 0) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Page publique des résultats
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sélecteur de tour de scrutin (Section 3 & 10) -->
            <?php if (!empty($donnees['tours']) && count($donnees['tours']) > 1): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="small fw-bold text-muted">
                            <i class="bi bi-layers-fill text-primary me-1"></i> Sélectionner le tour à inspecter :
                        </span>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($donnees['tours'] as $tItem): ?>
                                <?php
                                $isCurrentTab = (!empty($donnees['tour']) && (int)$donnees['tour']['id'] === (int)$tItem['id']);
                                $tStatutTxt = ($tItem['statut'] === 'cloturee') ? 'Clôturé' : (($tItem['statut'] === 'ouverte') ? 'Ouvert' : 'Préparation');
                                ?>
                                <a href="index.php?page=resultats_admin&election_id=<?= SecurityHelper::encryptId($el['id']) ?>&tour_id=<?= (int)$tItem['id'] ?>"
                                   class="btn btn-sm <?= $isCurrentTab ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                                    <?= SecurityHelper::e($tItem['libelle'] ?? ('Tour ' . $tItem['numero_tour'])) ?>
                                    <span class="badge <?= $isCurrentTab ? 'bg-white text-primary' : 'bg-secondary-subtle text-secondary' ?> ms-1">
                                        <?= $tStatutTxt ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Cartes KPI de participation -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small text-uppercase fw-semibold">Éligibles au vote</span>
                            <span class="badge bg-primary-subtle text-primary rounded-circle p-2"><i class="bi bi-people-fill"></i></span>
                        </div>
                        <h3 class="fw-bold my-1"><?= $donnees['nb_eligibles'] ?></h3>
                        <small class="text-muted">Membres autorisés sur ce tour</small>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small text-uppercase fw-semibold">Votants (Émargés)</span>
                            <span class="badge bg-info-subtle text-info rounded-circle p-2"><i class="bi bi-person-check-fill"></i></span>
                        </div>
                        <h3 class="fw-bold my-1 text-primary"><?= $donnees['nb_votants'] ?></h3>
                        <small class="text-muted">Participations enregistrées</small>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small text-uppercase fw-semibold">Bulletins dépouillés</span>
                            <span class="badge bg-warning-subtle text-warning rounded-circle p-2"><i class="bi bi-box2-heart-fill"></i></span>
                        </div>
                        <h3 class="fw-bold my-1 text-warning"><?= $donnees['total_bulletins'] ?></h3>
                        <small class="text-muted">Suffrages exprimés</small>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card border-0 shadow-sm p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted small text-uppercase fw-semibold">Participation</span>
                            <span class="badge bg-success-subtle text-success rounded-circle p-2"><i class="bi bi-pie-chart-fill"></i></span>
                        </div>
                        <h3 class="fw-bold my-1 text-success"><?= number_format($donnees['taux_participation'], 1, ',', ' ') ?> %</h3>
                        <div class="progress mt-2" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= min(100, $donnees['taux_participation']) ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bandeau élégant du candidat en tête ou d'égalité (Section 8 : Orange / Section 9 : Vert) -->
            <?php if ($donnees['total_bulletins'] > 0): ?>
                <?php if ($donnees['is_egalite']): ?>
                    <!-- SECTION 8 : Situation d'égalité (Ex æquo - Orange, aucune coupe, message Comité Ad Hoc) -->
                    <div class="admin-equality-card mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center p-3" style="width: 54px; height: 54px; flex-shrink: 0;">
                                    <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                                </div>
                                <div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                        <span class="badge bg-warning text-dark fw-bold text-uppercase px-2 py-1">
                                            <?= !empty($donnees['etat_egalite_persistante']) ? 'Égalité persistante (Max tours atteint)' : 'Situation d\'égalité (Ex æquo en tête)' ?>
                                        </span>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 small">
                                            Aucun vainqueur désigné automatiquement
                                        </span>
                                    </div>
                                    <h3 class="h5 fw-bold mb-1" style="color: #9a3412;">
                                        Plusieurs candidats sont à égalité au 1er rang (<?= $donnees['max_voix'] ?> voix)
                                    </h3>
                                    <p class="small mb-2" style="color: #7c2d12;">
                                        Candidats ex æquo en tête :
                                        <strong><?= implode(', ', array_map(fn($l) => SecurityHelper::e($l['prenoms'] . ' ' . $l['nom']), $donnees['leaders'])) ?></strong>
                                    </p>
                                    <div class="admin-comite-instruction">
                                        <i class="bi bi-megaphone-fill me-2"></i>ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-2">
                                <a href="index.php?page=election_details&id=<?= SecurityHelper::encryptId($el['id']) ?>" class="btn btn-warning btn-sm fw-bold text-dark">
                                    <i class="bi bi-gear-fill me-1"></i> Gérer la qualification / Décision du bureau
                                </a>
                            </div>
                        </div>
                    </div>
                <?php elseif (!empty($donnees['vainqueur'])): ?>
                    <?php $v = $donnees['vainqueur']; ?>
                    <!-- SECTION 9 : Bandeau du candidat en tête / vainqueur (Vert en cas de victoire + Coupe + Phrase officielle) -->
                    <div class="admin-leader-card <?= $isCloturee ? 'is-winner-green' : '' ?> mb-4">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="admin-leader-avatar-wrap">
                                    <?php if (!empty($v['photo']) && file_exists(__DIR__ . '/../../uploads/candidats/' . $v['photo'])): ?>
                                        <img src="uploads/candidats/<?= SecurityHelper::e($v['photo']) ?>" alt="<?= SecurityHelper::e($v['nom']) ?>" class="admin-leader-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="admin-leader-avatar-initials" style="display:none;"><?= getInitials($v['nom'], $v['prenoms']) ?></div>
                                    <?php else: ?>
                                        <div class="admin-leader-avatar-initials"><?= getInitials($v['nom'], $v['prenoms']) ?></div>
                                    <?php endif; ?>
                                    <span class="admin-leader-crown-badge" title="Rang #1">
                                        <i class="bi bi-trophy-fill"></i>
                                    </span>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge <?= $isCloturee ? 'bg-success text-white' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' ?> fw-bold text-uppercase px-2 py-1" style="font-size: 0.72rem; letter-spacing: 0.04em;">
                                            <i class="bi bi-trophy-fill me-1"></i><?= $isCloturee ? 'Vainqueur Officiel Proclamé' : 'En tête du scrutin' ?>
                                        </span>
                                        <span class="badge bg-light text-muted border px-2 py-1 small">
                                            Rang #1
                                        </span>
                                    </div>
                                    <h3 class="admin-leader-name"><?= SecurityHelper::e($v['prenoms'] . ' ' . $v['nom']) ?></h3>
                                    <div class="small fw-semibold <?= $isCloturee ? 'text-success' : 'text-primary' ?> mb-1">
                                        Les votes du scrutin "<?= SecurityHelper::e($el['titre']) ?>" : vainqueur <?= SecurityHelper::e($v['prenoms'] . ' ' . $v['nom']) ?> avec <?= (int)($v['nb_voix'] ?? 0) ?> voix (<?= number_format((float)($v['pourcentage'] ?? 0.0), 1, ',', ' ') ?> %).
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="text-muted small">Score :</span>
                                        <span class="fw-bold text-primary"><?= (int)($v['nb_voix'] ?? 0) ?> voix</span>
                                        <span class="text-muted small">&bull;</span>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">
                                            <?= number_format((float)($v['pourcentage'] ?? 0.0), 1, ',', ' ') ?> %
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-none d-sm-flex align-items-center gap-3">
                                <div class="text-end">
                                    <div class="admin-leader-score"><?= (int)($v['nb_voix'] ?? 0) ?> <span class="fs-6 fw-normal text-muted">voix</span></div>
                                    <small class="text-muted"><?= number_format((float)($v['pourcentage'] ?? 0.0), 1, ',', ' ') ?> % des suffrages</small>
                                </div>
                                <div class="rounded-circle d-flex align-items-center justify-content-center p-3" style="width: 52px; height: 52px; background: <?= $isCloturee ? 'rgba(22, 163, 74, 0.14)' : 'rgba(245, 158, 11, 0.12)' ?>; color: <?= $isCloturee ? '#16a34a' : '#f59e0b' ?>;">
                                    <i class="bi bi-trophy-fill fs-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Tableau et Cartes des résultats par candidat -->
            <div class="admin-results-table-card mb-4">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="bi bi-trophy me-2 text-warning"></i> Répartition des voix par candidat
                    </h5>
                    <span class="badge bg-secondary-subtle text-secondary small">
                        <?= count($donnees['candidats'] ?? []) ?> candidat(s) enregistré(s)
                    </span>
                </div>

                <?php if (empty($donnees['candidats'])): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-people fs-2 d-block mb-2"></i>
                        Aucun candidat validé dans cette élection.
                    </div>
                <?php else: ?>
                    <!-- Vue Tableau classique (Desktop : >= md) -->
                    <div class="d-none d-md-block">
                        <table class="admin-results-table">
                            <thead>
                                <tr>
                                    <th style="width: 10%;">Rang</th>
                                    <th style="width: 32%;">Candidat</th>
                                    <th style="width: 28%;">Progression</th>
                                    <th style="width: 12%; text-align: right;">Nombre de voix</th>
                                    <th style="width: 10%; text-align: right;">Pourcentage</th>
                                    <th style="width: 8%; text-align: center;">Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donnees['candidats'] as $c): ?>
                                    <?php 
                                        $rangClass = 'rank-neutral';
                                        $progClass = 'prog-neutral';
                                        if ($c['rang'] === 1 && $c['nb_voix'] > 0) {
                                            if ($donnees['is_egalite']) {
                                                $rangClass = 'rank-gold rank-equality-orange';
                                                $progClass = 'prog-gold prog-equality-orange';
                                            } else {
                                                $rangClass = 'rank-gold';
                                                $progClass = 'prog-gold';
                                            }
                                        } elseif ($c['rang'] === 2 && $c['nb_voix'] > 0) {
                                            $rangClass = 'rank-silver';
                                            $progClass = 'prog-silver';
                                        } elseif ($c['rang'] === 3 && $c['nb_voix'] > 0) {
                                            $rangClass = 'rank-bronze';
                                            $progClass = 'prog-bronze';
                                        }
                                    ?>
                                    <tr>
                                        <!-- Rang -->
                                        <td>
                                            <span class="admin-rank-pill <?= $rangClass ?>">
                                                #<?= $c['rang'] ?>
                                            </span>
                                            <?php if (!empty($c['is_ex_aequo'])): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle ms-1 small" title="Ex æquo">
                                                    Ex æquo
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Candidat (Photo + Nom) -->
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($c['photo']) && file_exists(__DIR__ . '/../../uploads/candidats/' . $c['photo'])): ?>
                                                    <img src="uploads/candidats/<?= SecurityHelper::e($c['photo']) ?>" alt="<?= SecurityHelper::e($c['nom']) ?>" class="admin-table-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                    <div class="admin-table-avatar-initials" style="display:none;"><?= getInitials($c['nom'], $c['prenoms']) ?></div>
                                                <?php else: ?>
                                                    <div class="admin-table-avatar-initials"><?= getInitials($c['nom'], $c['prenoms']) ?></div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-bold">
                                                        <?= SecurityHelper::e($c['nom'] . ' ' . $c['prenoms']) ?>
                                                        <?php if ($c['is_leader'] && $c['nb_voix'] > 0 && !$donnees['is_egalite']): ?>
                                                            <i class="bi bi-trophy-fill text-success ms-1" title="Vainqueur"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($c['motif_candidature'])): ?>
                                                        <small class="text-muted d-block text-truncate" style="max-width: 250px; font-size: 0.75rem;">
                                                            <?= SecurityHelper::e($c['motif_candidature']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>

                        <!-- Progression -->
                                        <td>
                                            <div class="admin-prog-track">
                                                <div class="admin-prog-fill <?= $progClass ?>" style="width: <?= number_format(min(100.0, max(0.0, (float)$c['pourcentage'])), 1, '.', '') ?>%;"></div>
                                            </div>
                                        </td>

                                        <!-- Voix -->
                                        <td style="text-align: right;">
                                            <span class="fw-bold fs-6"><?= $c['nb_voix'] ?></span>
                                            <span class="text-muted small">voix</span>
                                        </td>

                                        <!-- Pourcentage -->
                                        <td style="text-align: right;">
                                            <span class="fw-bold"><?= number_format($c['pourcentage'], 1, ',', ' ') ?> %</span>
                                        </td>

                                        <!-- Statut -->
                                        <td style="text-align: center;">
                                            <?php if ($c['is_leader'] && $c['nb_voix'] > 0 && !$donnees['is_egalite']): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.72rem;">
                                                    <i class="bi bi-trophy-fill me-1"></i> <?= $isCloturee ? 'Vainqueur' : 'En tête' ?>
                                                </span>
                                            <?php elseif (!empty($c['is_ex_aequo'])): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1" style="font-size: 0.72rem;">
                                                    Ex æquo
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-2 py-1" style="font-size: 0.72rem;">
                                                    Candidat
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Vue Cartes compactes (Mobile : < md) -->
                    <div class="d-md-none p-3">
                        <?php foreach ($donnees['candidats'] as $c): ?>
                            <?php 
                                $isLeader = ($c['is_leader'] && $c['nb_voix'] > 0);
                                $rangClass = 'rank-neutral';
                                $progClass = 'prog-neutral';
                                if ($c['rang'] === 1 && $c['nb_voix'] > 0) {
                                    if ($donnees['is_egalite']) {
                                        $rangClass = 'rank-gold rank-equality-orange';
                                        $progClass = 'prog-gold prog-equality-orange';
                                    } else {
                                        $rangClass = 'rank-gold';
                                        $progClass = 'prog-gold';
                                    }
                                } elseif ($c['rang'] === 2 && $c['nb_voix'] > 0) {
                                    $rangClass = 'rank-silver';
                                    $progClass = 'prog-silver';
                                } elseif ($c['rang'] === 3 && $c['nb_voix'] > 0) {
                                    $rangClass = 'rank-bronze';
                                    $progClass = 'prog-bronze';
                                }
                            ?>
                            <div class="admin-mobile-card <?= ($isLeader && !$donnees['is_egalite']) ? 'is-leader-card' : (($isLeader && $donnees['is_egalite']) ? 'is-equality-card' : '') ?>">
                                <div class="admin-mobile-card-top">
                                    <div class="d-flex align-items-center gap-1">
                                        <span class="admin-rank-pill <?= $rangClass ?>">
                                            #<?= $c['rang'] ?>
                                        </span>
                                        <?php if (!empty($c['is_ex_aequo'])): ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle small">
                                                Ex æquo
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($c['is_leader'] && $c['nb_voix'] > 0 && !$donnees['is_egalite']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small">
                                                <i class="bi bi-trophy-fill me-1"></i> <?= $isCloturee ? 'Vainqueur' : 'En tête' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border px-2 py-1 small">Candidat</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="admin-mobile-card-user">
                                    <?php if (!empty($c['photo']) && file_exists(__DIR__ . '/../../uploads/candidats/' . $c['photo'])): ?>
                                        <img src="uploads/candidats/<?= SecurityHelper::e($c['photo']) ?>" alt="<?= SecurityHelper::e($c['nom']) ?>" class="admin-table-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="admin-table-avatar-initials" style="display:none;"><?= getInitials($c['nom'], $c['prenoms']) ?></div>
                                    <?php else: ?>
                                        <div class="admin-table-avatar-initials"><?= getInitials($c['nom'], $c['prenoms']) ?></div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1" style="min-width: 0;">
                                        <div class="fw-bold text-truncate"><?= SecurityHelper::e($c['nom'] . ' ' . $c['prenoms']) ?></div>
                                    </div>
                                </div>

                                <div class="admin-mobile-card-stats">
                                    <span class="small text-muted">
                                        <strong class="text-dark fs-6"><?= $c['nb_voix'] ?></strong> voix
                                    </span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">
                                        <?= number_format($c['pourcentage'], 1, ',', ' ') ?> %
                                    </span>
                                </div>

                                <div class="admin-prog-track">
                                    <div class="admin-prog-fill <?= $progClass ?>" style="width: <?= number_format(min(100.0, max(0.0, (float)$c['pourcentage'])), 1, '.', '') ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Récapitulatif historique de tous les tours de l'élection (Section 10) -->
            <?php if (!empty($donnees['tours_history']) && count($donnees['tours_history']) > 1): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fs-6 fw-bold">
                            <i class="bi bi-clock-history text-primary me-2"></i> Historique Complet des Tours de Scrutin
                        </h5>
                        <span class="badge bg-primary-subtle text-primary"><?= count($donnees['tours_history']) ?> tours enregistrés</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tour</th>
                                    <th>Période de vote</th>
                                    <th>Statut</th>
                                    <th>Électeurs / Votants</th>
                                    <th>Participation</th>
                                    <th>Résultat / Candidats qualifiés</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donnees['tours_history'] as $th): ?>
                                    <?php $tr = $th['resultats'] ?? $th; $tInfo = $th['tour']; ?>
                                    <tr>
                                        <td class="fw-bold"><?= SecurityHelper::e($tInfo['libelle'] ?? ('Tour ' . $tInfo['numero_tour'])) ?></td>
                                        <td class="small">
                                            Du <?= date('d/m/Y H:i', strtotime($tInfo['date_debut_vote'])) ?><br>
                                            au <?= date('d/m/Y H:i', strtotime($tInfo['date_fin_vote'])) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $tInfo['statut'] === 'cloturee' ? 'success' : ($tInfo['statut'] === 'ouverte' ? 'primary' : 'warning text-dark') ?>">
                                                <?= $tInfo['statut'] === 'cloturee' ? 'Clôturé' : ($tInfo['statut'] === 'ouverte' ? 'Ouvert' : 'En préparation') ?>
                                            </span>
                                        </td>
                                        <td class="small"><?= $tr['nb_votants'] ?> votants / <?= $tr['nb_eligibles'] ?> autorisés</td>
                                        <td><span class="badge bg-info-subtle text-info"><?= number_format($tr['taux_participation'], 1, ',', ' ') ?> %</span></td>
                                        <td>
                                            <?php if ($tr['is_egalite']): ?>
                                                <span class="badge bg-warning text-dark">Égalité (<?= $tr['max_voix'] ?> voix)</span>
                                                <small class="d-block text-muted mt-1">
                                                    Qualifiés : <?= implode(', ', array_map(fn($q) => SecurityHelper::e($q['prenoms'] . ' ' . $q['nom']), $tr['qualifies'])) ?>
                                                </small>
                                            <?php elseif (!empty($tr['vainqueur'])): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-trophy-fill me-1"></i><?= SecurityHelper::e($tr['vainqueur']['prenoms'] . ' ' . $tr['vainqueur']['nom']) ?> (<?= $tr['vainqueur']['nb_voix'] ?> voix)
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">Aucun suffrage exprimé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="index.php?page=resultats_admin&election_id=<?= SecurityHelper::encryptId($el['id']) ?>&tour_id=<?= (int)$tInfo['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                Inspecter ce tour
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/../layout/footer.php'; ?>
