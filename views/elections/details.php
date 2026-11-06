<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Helpers/AuthHelper.php';
require_once __DIR__ . '/../../app/Models/Election.php';
require_once __DIR__ . '/../../app/Services/ElectionService.php';

SessionHelper::start();
AuthHelper::requireLogin();

/** @var array $election */
$election = $election ?? ((new ElectionService())->getDerniereElection() ?? ['id' => 0, 'titre' => '', 'statut' => 'preparation', 'date_debut' => date('Y-m-d H:i:s'), 'date_fin' => date('Y-m-d H:i:s')]);
/** @var array $candidats */
$candidats = $candidats ?? [];
/** @var int $nbVotants */
$nbVotants = $nbVotants ?? 0;
/** @var int $nbEligibles */
$nbEligibles = $nbEligibles ?? (int)($election['nb_eligibles'] ?? 0);
/** @var bool $isDerniereElection */
$isDerniereElection = $isDerniereElection ?? false;
/** @var array $tours */
$tours = $tours ?? [];
/** @var array|null $activeTour */
$activeTour = $activeTour ?? null;
/** @var int $selectedTourId */
$selectedTourId = $selectedTourId ?? ($activeTour ? (int)$activeTour['id'] : 0);
/** @var array $electeursTour */
$electeursTour = $electeursTour ?? [];
/** @var array $journalElecteurs */
$journalElecteurs = $journalElecteurs ?? [];
/** @var array $decisionsBureau */
$decisionsBureau = $decisionsBureau ?? [];
/** @var bool $isTourVoterLocked */
$isTourVoterLocked = $isTourVoterLocked ?? false;
/** @var array $tousMembres */
$tousMembres = $tousMembres ?? [];

$pageTitle = 'Détails & Chronologie : ' . ($election['titre'] ?? '');
$pageSubtitle = 'Suivi du cycle électoral';
$currentPage = 'elections';

$phaseInfo = $phaseInfo ?? ($election['phase_info'] ?? (new ElectionService())->getCurrentPhase($election));
$timeline = $phaseInfo['timeline'] ?? [];
$locks = $phaseInfo['locks'] ?? [];
$nextPhase = $phaseInfo['next_phase'] ?? null;

include __DIR__ . '/../layout/head.php';
?>

<main class="dashboard-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <?= SessionHelper::renderFlashes() ?>

<div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <a href="index.php?page=elections" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Retour aux élections
    </a>

    <div class="d-flex flex-wrap gap-2">
        <?php if (!empty($isDerniereElection)): ?>
            <span class="badge bg-primary px-3 py-2">
                <i class="bi bi-star-fill me-1"></i> Dernier scrutin de référence
            </span>
        <?php endif; ?>
        <span class="badge bg-dark px-3 py-2">
            <i class="bi bi-clock me-1"></i> Fuseau : Africa/Abidjan (<?= date('d/m/Y H:i:s', strtotime($phaseInfo['now'])) ?>)
        </span>
    </div>
</div>

<!-- En-tête de l'élection + Statistiques clés -->
<div class="card border-0 shadow-sm mb-4 p-4 bg-white">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="badge bg-<?= SecurityHelper::e($phaseInfo['badge']) ?> px-3 py-2 fs-6">
                    Phase actuelle : <?= SecurityHelper::e($phaseInfo['label']) ?>
                </span>
                <?php if ($nextPhase): ?>
                    <span class="badge bg-light text-dark border px-3 py-2">
                        Phase suivante : <strong><?= SecurityHelper::e($nextPhase['libelle']) ?></strong>
                    </span>
                <?php endif; ?>
            </div>
            <h2 class="h4 fw-bold mb-1"><?= SecurityHelper::e($election['titre']) ?></h2>
            <p class="text-muted mb-2"><?= nl2br(SecurityHelper::e($election['description'] ?? '')) ?></p>
            <div class="small text-muted">
                <strong>Vote :</strong> Du <?= date('d/m/Y à H:i', strtotime($election['date_debut_vote'] ?? $election['date_debut'])) ?>
                au <?= date('d/m/Y à H:i', strtotime($election['date_fin_vote'] ?? $election['date_fin'])) ?>
                <?php if (!empty($election['date_cloture_effective'])): ?>
                    &bull; <strong>Clôture effective :</strong> <?= date('d/m/Y à H:i:s', strtotime($election['date_cloture_effective'])) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-end d-flex flex-wrap gap-2">
            <a href="index.php?page=resultats_admin&election_id=<?= SecurityHelper::encryptId($election['id']) ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-bar-chart-line me-1"></i> Voir les résultats
            </a>
            <?php if (($election['statut'] ?? '') === 'cloturee'): ?>
                <form method="post" action="index.php?page=election_certify" class="d-inline">
                    <?= SessionHelper::csrfField() ?>
                    <input type="hidden" name="id" value="<?= $election['id'] ?>">
                    <input type="hidden" name="certified" value="<?= !empty($election['resultats_certifies']) ? '0' : '1' ?>">
                    <button type="submit" class="btn btn-sm <?= !empty($election['resultats_certifies']) ? 'btn-outline-secondary' : 'btn-success' ?>">
                        <i class="bi bi-patch-check-fill me-1"></i>
                        <?= !empty($election['resultats_certifies']) ? 'Retirer la certification' : 'Certifier officiellement les résultats' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Compteurs (Membres éligibles, Candidats validés, Votes enregistrés) -->
    <div class="row g-3 mt-3 pt-3 border-top text-center">
        <div class="col-12 col-sm-4">
            <div class="p-2 rounded bg-light">
                <span class="text-muted small d-block">Membres éligibles au vote</span>
                <span class="fs-4 fw-bold text-success"><?= (int)($nbEligibles ?? ($election['nb_eligibles'] ?? 0)) ?></span>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="p-2 rounded bg-light">
                <span class="text-muted small d-block">Candidats validés (sur le bulletin)</span>
                <span class="fs-4 fw-bold text-primary"><?= (int)($election['nb_candidats_valides'] ?? 0) ?> <small class="text-muted fw-normal">/ <?= count($candidats) ?></small></span>
            </div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="p-2 rounded bg-light">
                <span class="text-muted small d-block">Votes enregistrés</span>
                <span class="fs-4 fw-bold text-dark"><?= (int)$nbVotants ?></span>
            </div>
        </div>
    </div>
</div>

<!-- État des verrouillages actifs (Section 13) -->
<div class="card border-0 shadow-sm mb-4 bg-white">
    <div class="card-header bg-white py-3">
        <h3 class="h6 fw-bold mb-0"><i class="bi bi-shield-lock text-primary me-2"></i>État des verrouillages automatiques</h3>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 rounded border text-center h-100 <?= !empty($locks['inscriptions_cloturees']) ? 'bg-danger-subtle border-danger-subtle text-danger' : 'bg-success-subtle border-success-subtle text-success' ?>">
                    <i class="bi <?= !empty($locks['inscriptions_cloturees']) ? 'bi-lock-fill' : 'bi-unlock-fill' ?> fs-4 d-block mb-1"></i>
                    <span class="small fw-bold d-block">Inscriptions clôturées</span>
                    <span class="badge <?= !empty($locks['inscriptions_cloturees']) ? 'bg-danger' : 'bg-success' ?> mt-1">
                        <?= !empty($locks['inscriptions_cloturees']) ? 'OUI (Verrouillé)' : 'NON (Ouvertes)' ?>
                    </span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 rounded border text-center h-100 <?= !empty($locks['candidatures_cloturees']) ? 'bg-danger-subtle border-danger-subtle text-danger' : 'bg-success-subtle border-success-subtle text-success' ?>">
                    <i class="bi <?= !empty($locks['candidatures_cloturees']) ? 'bi-lock-fill' : 'bi-unlock-fill' ?> fs-4 d-block mb-1"></i>
                    <span class="small fw-bold d-block">Candidatures clôturées</span>
                    <span class="badge <?= !empty($locks['candidatures_cloturees']) ? 'bg-danger' : 'bg-success' ?> mt-1">
                        <?= !empty($locks['candidatures_cloturees']) ? 'OUI (Verrouillé)' : 'NON (Ouvertes)' ?>
                    </span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 rounded border text-center h-100 <?= !empty($locks['campagne_terminee']) ? 'bg-secondary-subtle border-secondary-subtle text-secondary' : 'bg-warning-subtle border-warning-subtle text-dark' ?>">
                    <i class="bi bi-megaphone-fill fs-4 d-block mb-1"></i>
                    <span class="small fw-bold d-block">Campagne terminée</span>
                    <span class="badge <?= !empty($locks['campagne_terminee']) ? 'bg-secondary' : 'bg-warning text-dark' ?> mt-1">
                        <?= !empty($locks['campagne_terminee']) ? 'OUI' : 'NON' ?>
                    </span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 rounded border text-center h-100 <?= !empty($locks['scrutin_ouvert']) ? 'bg-success-subtle border-success-subtle text-success' : 'bg-light text-muted' ?>">
                    <i class="bi bi-fingerprint fs-4 d-block mb-1"></i>
                    <span class="small fw-bold d-block">Scrutin ouvert</span>
                    <span class="badge <?= !empty($locks['scrutin_ouvert']) ? 'bg-success' : 'bg-secondary' ?> mt-1">
                        <?= !empty($locks['scrutin_ouvert']) ? 'OUI (En cours)' : 'NON' ?>
                    </span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 rounded border text-center h-100 <?= !empty($locks['vote_cloture']) ? 'bg-dark text-white' : 'bg-light text-muted' ?>">
                    <i class="bi bi-lock-fill fs-4 d-block mb-1"></i>
                    <span class="small fw-bold d-block">Vote clôturé</span>
                    <span class="badge <?= !empty($locks['vote_cloture']) ? 'bg-warning text-dark' : 'bg-secondary' ?> mt-1">
                        <?= !empty($locks['vote_cloture']) ? 'OUI (Verrouillé)' : 'NON' ?>
                    </span>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <div class="p-3 rounded border text-center h-100 <?= !empty($locks['resultats_publics']) ? 'bg-primary-subtle border-primary-subtle text-primary' : 'bg-light text-muted' ?>">
                    <i class="bi bi-trophy-fill fs-4 d-block mb-1"></i>
                    <span class="small fw-bold d-block">Résultats accessibles au public</span>
                    <span class="badge <?= !empty($locks['resultats_publics']) ? 'bg-primary' : 'bg-secondary' ?> mt-1">
                        <?= !empty($locks['resultats_publics']) ? 'OUI (Publics)' : 'NON (Masqués)' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chronologie visuelle des 7 phases électorales (Section 13) -->
<div class="card border-0 shadow-sm mb-4 bg-white">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 fw-bold mb-0"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Chronologie visuelle du cycle électoral</h3>
        <span class="text-muted small">7 étapes statutaires</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">#</th>
                        <th>Phase électorale</th>
                        <th>Date de début</th>
                        <th>Date de fin</th>
                        <th class="text-end pe-4">État de la phase</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($timeline as $step): ?>
                        <?php
                        $etatBadge = match ($step['etat']) {
                            'en_cours'    => '<span class="badge bg-primary"><i class="bi bi-play-circle-fill me-1"></i>En cours</span>',
                            'terminee'    => '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i>Terminée</span>',
                            'a_venir'     => '<span class="badge bg-warning-subtle text-dark border border-warning-subtle"><i class="bi bi-clock me-1"></i>À venir</span>',
                            default       => '<span class="badge bg-light text-muted border">Non définie</span>',
                        };
                        ?>
                        <tr class="<?= $step['etat'] === 'en_cours' ? 'table-primary' : '' ?>">
                            <td class="ps-4 fw-bold"><?= (int)$step['numero'] ?></td>
                            <td>
                                <i class="bi <?= SecurityHelper::e($step['icon']) ?> me-2 text-primary"></i>
                                <strong><?= SecurityHelper::e($step['libelle']) ?></strong>
                            </td>
                            <td class="small">
                                <?= !empty($step['date_debut']) ? date('d/m/Y à H:i', strtotime($step['date_debut'])) : '<span class="text-muted">&mdash;</span>' ?>
                            </td>
                            <td class="small">
                                <?= !empty($step['date_fin']) ? date('d/m/Y à H:i', strtotime($step['date_fin'])) : '<span class="text-muted">&mdash;</span>' ?>
                            </td>
                            <td class="text-end pe-4"><?= $etatBadge ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Liste des candidatures de ce scrutin -->
<div class="card border-0 shadow-sm bg-white mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 fw-bold mb-0">Candidatures enregistrées pour ce scrutin (<?= count($candidats) ?>)</h3>
        <a href="index.php?page=candidats&election_id=<?= SecurityHelper::encryptId($election['id']) ?>" class="btn btn-sm btn-outline-primary">
            Gérer les candidatures
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Candidat</th>
                        <th>Âge / Ancienneté</th>
                        <th>Droit payé</th>
                        <th>Conformité</th>
                        <th>Statut</th>
                        <th>Voix obtenues (Total)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($candidats)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Aucun candidat pour cette élection.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($candidats as $c): ?>
                            <tr>
                                <td class="ps-4 fw-semibold"><?= SecurityHelper::e($c['nom'] . ' ' . $c['prenoms']) ?></td>
                                <td class="small">
                                    <?= $c['age_calcule'] !== null ? (int)$c['age_calcule'] . ' ans' : '—' ?> /
                                    <?= $c['anciennete'] !== null ? (int)$c['anciennete'] . ' an(s)' : '—' ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['droit_candidature_paye'])): ?>
                                        <span class="badge bg-success-subtle text-success">Oui</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger">Non</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($c['dossier_conforme'])): ?>
                                        <span class="badge bg-success">Conforme</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Non conforme</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($c['statut'] === 'validee'): ?>
                                        <span class="badge bg-success">Validée (sur bulletin)</span>
                                    <?php elseif ($c['statut'] === 'en_attente'): ?>
                                        <span class="badge bg-warning text-dark">En attente</span>
                                    <?php elseif ($c['statut'] === 'rejetee'): ?>
                                        <span class="badge bg-danger">Rejetée</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Retirée</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold"><?= (int)$c['nb_voix'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- SECTION 3 & 4 : GESTION DES TOURS DE SCRUTIN & QUALIFICATION AUTOMATIQUE -->
<!-- ======================================================================= -->
<div class="card border-0 shadow-sm bg-white mb-4" id="section-tours">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="h6 fw-bold mb-0">
                <i class="bi bi-layers-fill text-primary me-2"></i>Gestion des Tours de Scrutin &amp; Qualification Automatique
            </h3>
            <small class="text-muted">
                Nombre maximal de tours configuré : <strong><?= (int)($election['nombre_max_tours'] ?? 3) ?> tours</strong>
                &bull; Tour actif : <strong>Tour <?= (int)($election['tour_courant'] ?? 1) ?></strong>
            </small>
        </div>
        <span class="badge bg-primary-subtle text-primary"><?= count($tours) ?> tour(s) créé(s)</span>
    </div>
    <div class="card-body">
        <?php if (!empty($election['etat_egalite_persistante'])): ?>
            <div class="alert alert-warning border-warning d-flex align-items-center gap-3 mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
                <div>
                    <h4 class="h6 fw-bold mb-1">État : « Égalité persistante » après le nombre maximal de tours (<?= (int)($election['nombre_max_tours'] ?? 3) ?>)</h4>
                    <p class="small mb-0">
                        Aucun vainqueur n'a été désigné automatiquement. Le bureau doit enregistrer une <strong>décision officielle motivée</strong> ci-dessous (les résultats historiques des tours précédents sont intégralement conservés).
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>N° Tour</th>
                        <th>Libellé</th>
                        <th>Ouverture du vote</th>
                        <th>Fermeture du vote</th>
                        <th>Statut</th>
                        <th>Clôture effective</th>
                        <th class="text-end">Actions du bureau</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tours as $t): ?>
                        <tr>
                            <td class="fw-bold">#<?= (int)$t['numero_tour'] ?></td>
                            <td>
                                <strong><?= SecurityHelper::e($t['libelle'] ?? ('Tour ' . $t['numero_tour'])) ?></strong>
                                <?php if (!empty($t['est_egalite'])): ?>
                                    <span class="badge bg-warning text-dark ms-1">Égalité en tête</span>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?= date('d/m/Y à H:i', strtotime($t['date_debut_vote'])) ?></td>
                            <td class="small"><?= date('d/m/Y à H:i', strtotime($t['date_fin_vote'])) ?></td>
                            <td>
                                <?php if ($t['statut'] === 'ouverte'): ?>
                                    <span class="badge bg-success"><i class="bi bi-broadcast me-1"></i>Ouvert au vote</span>
                                <?php elseif ($t['statut'] === 'cloturee'): ?>
                                    <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i>Clôturé</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-gear-fill me-1"></i>En préparation</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?= !empty($t['date_cloture_effective']) ? date('d/m/Y H:i:s', strtotime($t['date_cloture_effective'])) : '<span class="text-muted">&mdash;</span>' ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                    <a href="index.php?page=resultats_admin&election_id=<?= SecurityHelper::encryptId($election['id']) ?>&tour_id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-bar-chart-line"></i> Résultats
                                    </a>
                                    <?php if ($t['statut'] !== 'cloturee'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalUpdateTour<?= (int)$t['id'] ?>">
                                            <i class="bi bi-calendar-check"></i> Dates / Ouvrir
                                        </button>
                                        <form method="post" action="index.php?page=election_cloturer_tour" class="d-inline" onsubmit="return confirm('Confirmez-vous la clôture de ce tour et le calcul automatique des qualifications ?');">
                                            <?= SessionHelper::csrfField() ?>
                                            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
                                            <input type="hidden" name="tour_id" value="<?= (int)$t['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-stop-circle"></i> Clôturer &amp; Qualifier
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal configuration/ouverture du tour -->
                        <div class="modal fade" id="modalUpdateTour<?= (int)$t['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <form method="post" action="index.php?page=election_update_tour" class="modal-content">
                                    <?= SessionHelper::csrfField() ?>
                                    <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
                                    <input type="hidden" name="tour_id" value="<?= (int)$t['id'] ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Configurer / Ouvrir le <?= SecurityHelper::e($t['libelle'] ?? ('Tour ' . $t['numero_tour'])) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Date et heure d'ouverture du vote <span class="text-danger">*</span></label>
                                            <input type="datetime-local" name="date_debut_vote" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($t['date_debut_vote'])) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Date et heure de fermeture du vote <span class="text-danger">*</span></label>
                                            <input type="datetime-local" name="date_fin_vote" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($t['date_fin_vote'])) ?>" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small fw-bold">Statut du tour</label>
                                            <select name="statut" class="form-select">
                                                <option value="preparation" <?= $t['statut'] === 'preparation' ? 'selected' : '' ?>>En préparation (liste électorale modifiable)</option>
                                                <option value="ouverte" <?= $t['statut'] === 'ouverte' ? 'selected' : '' ?>>Ouvert au vote (verrouille la liste électorale du tour)</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================================================================= -->
<!-- SECTION 4 & 14 : DÉCISION OFFICIELLE DU BUREAU (ÉGALITÉ PERSISTANTE)    -->
<!-- ======================================================================= -->
<div class="card border-0 shadow-sm bg-white mb-4" id="section-decisions-bureau">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h3 class="h6 fw-bold mb-0">
            <i class="bi bi-journal-check text-primary me-2"></i>Décisions Officielles du Bureau Électoral
        </h3>
        <span class="badge bg-secondary-subtle text-secondary"><?= count($decisionsBureau) ?> décision(s) enregistrée(s)</span>
    </div>
    <div class="card-body">
        <form method="post" action="index.php?page=election_decision_bureau" class="bg-light p-3 rounded border mb-3">
            <?= SessionHelper::csrfField() ?>
            <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
            <input type="hidden" name="tour_id" value="<?= (int)$selectedTourId ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Type de décision</label>
                    <select name="type_decision" class="form-select form-select-sm">
                        <option value="egalite_persistante">Résolution d'égalité persistante</option>
                        <option value="proclamation_vainqueur">Proclamation officielle du vainqueur</option>
                        <option value="autre">Autre décision statutaire du bureau</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Candidat retenu / désigné (optionnel)</label>
                    <select name="candidat_retenu_id" class="form-select form-select-sm">
                        <option value="">-- Aucun / Décision générale --</option>
                        <?php foreach ($candidats as $c): ?>
                            <?php if ($c['statut'] === 'validee'): ?>
                                <option value="<?= (int)$c['id'] ?>"><?= SecurityHelper::e($c['prenoms'] . ' ' . $c['nom']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Décision du bureau <span class="text-danger">*</span></label>
                    <input type="text" name="decision" class="form-control form-control-sm" placeholder="Ex: Départage par le comité ad hoc..." required>
                </div>
                <div class="col-md-9">
                    <label class="form-label small fw-bold">Motif officiel de la décision <span class="text-danger">*</span></label>
                    <input type="text" name="motif" class="form-control form-control-sm" placeholder="Ex: Application de l'article statutaire suite à l'égalité persistante au dernier tour..." required>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                        <i class="bi bi-save me-1"></i> Enregistrer la décision
                    </button>
                </div>
            </div>
        </form>

        <?php if (!empty($decisionsBureau)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Tour</th>
                            <th>Décision</th>
                            <th>Motif</th>
                            <th>Candidat retenu</th>
                            <th>Administrateur</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($decisionsBureau as $dec): ?>
                            <tr>
                                <td class="small"><?= date('d/m/Y H:i', strtotime($dec['date_decision'])) ?></td>
                                <td class="small"><?= SecurityHelper::e($dec['tour_libelle'] ?? ('Tour ' . ($dec['numero_tour'] ?? '-'))) ?></td>
                                <td class="fw-semibold small"><?= SecurityHelper::e($dec['decision']) ?></td>
                                <td class="small text-muted"><?= SecurityHelper::e($dec['motif']) ?></td>
                                <td class="small">
                                    <?php if (!empty($dec['candidat_nom'])): ?>
                                        <span class="badge bg-success"><?= SecurityHelper::e($dec['candidat_prenoms'] . ' ' . $dec['candidat_nom']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= SecurityHelper::e(($dec['admin_prenoms'] ?? '') . ' ' . ($dec['admin_nom'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ======================================================================= -->
<!-- SECTION 5 : GESTION EXCEPTIONNELLE DES ÉLECTEURS PAR TOUR & TRAÇABILITÉ -->
<!-- ======================================================================= -->
<div class="card border-0 shadow-sm bg-white mb-4" id="section-electeurs-tour">
    <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="h6 fw-bold mb-0">
                <i class="bi bi-person-Vcard-fill text-primary me-2"></i>Liste Électorale par Tour &amp; Gestion Exceptionnelle du Bureau
            </h3>
            <small class="text-muted">
                Toute modification exceptionnelle (ajout, retrait, correction) exige un motif et n'est autorisée qu'<strong>avant l'ouverture du tour</strong>.
            </small>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php foreach ($tours as $tItem): ?>
                <a href="index.php?page=election_details&id=<?= SecurityHelper::encryptId($election['id']) ?>&tour_id=<?= (int)$tItem['id'] ?>#section-electeurs-tour"
                   class="btn btn-sm <?= ($selectedTourId === (int)$tItem['id']) ? 'btn-primary fw-bold' : 'btn-outline-primary' ?>">
                    <?= SecurityHelper::e($tItem['libelle'] ?? ('Tour ' . $tItem['numero_tour'])) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($isTourVoterLocked): ?>
                <span class="badge bg-danger px-3 py-2">
                    <i class="bi bi-lock-fill me-1"></i> Liste électorale verrouillée (tour ouvert ou clôturé)
                </span>
            <?php else: ?>
                <span class="badge bg-success px-3 py-2">
                    <i class="bi bi-unlock-fill me-1"></i> Modifications exceptionnelles autorisées avant ouverture
                </span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$isTourVoterLocked && $selectedTourId > 0): ?>
            <form method="post" action="index.php?page=election_electeur_tour" class="bg-light p-3 rounded border mb-4">
                <?= SessionHelper::csrfField() ?>
                <input type="hidden" name="election_id" value="<?= (int)$election['id'] ?>">
                <input type="hidden" name="tour_id" value="<?= (int)$selectedTourId ?>">
                <h6 class="fw-bold small text-uppercase text-primary mb-2">
                    <i class="bi bi-shield-exclamation me-1"></i> Intervention exceptionnelle du bureau sur la liste électorale de ce tour
                </h6>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Membre concerné <span class="text-danger">*</span></label>
                        <select name="membre_id" class="form-select form-select-sm" required>
                            <option value="">-- Sélectionner un membre --</option>
                            <?php foreach ($tousMembres as $m): ?>
                                <option value="<?= (int)$m['id'] ?>">
                                    <?= SecurityHelper::e($m['nom'] . ' ' . $m['prenoms'] . ' (' . $m['telephone'] . ') — Éligible : ' . strtoupper($m['eligibilite_a_voter'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Type d'intervention <span class="text-danger">*</span></label>
                        <select name="type_modification" class="form-select form-select-sm" required>
                            <option value="ajout">Ajouter exceptionnellement (Autoriser)</option>
                            <option value="retrait">Retirer exceptionnellement (Interdire)</option>
                            <option value="correction">Corriger les données / Réautoriser</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Motif obligatoire <span class="text-danger">*</span></label>
                        <input type="text" name="motif" class="form-control form-control-sm" placeholder="Motif précis de la décision du bureau..." required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i> Appliquer
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-12 col-lg-7">
                <h6 class="fw-bold small text-uppercase text-muted mb-2">
                    Électeurs enregistrés sur ce tour (<?= count($electeursTour) ?>)
                </h6>
                <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Membre</th>
                                <th>Téléphone</th>
                                <th>Autorisation</th>
                                <th>Source / Motif</th>
                                <th>Vote sur ce tour</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($electeursTour)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Aucun électeur synchronisé pour ce tour.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($electeursTour as $et): ?>
                                    <tr>
                                        <td class="fw-semibold small"><?= SecurityHelper::e($et['nom'] . ' ' . $et['prenoms']) ?></td>
                                        <td class="small"><?= SecurityHelper::e($et['telephone']) ?></td>
                                        <td>
                                            <?php if (!empty($et['autorise_a_voter'])): ?>
                                                <span class="badge bg-success-subtle text-success">Autorisé</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger">Retiré / Non autorisé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <span class="badge bg-light text-dark border"><?= SecurityHelper::e($et['source_autorisation']) ?></span>
                                            <?php if (!empty($et['motif_modification'])): ?>
                                                <small class="d-block text-muted"><?= SecurityHelper::e($et['motif_modification']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($et['a_vote_ce_tour'])): ?>
                                                <span class="badge bg-primary"><i class="bi bi-check2 me-1"></i>A voté</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Non voté</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-12 col-lg-5">
                <h6 class="fw-bold small text-uppercase text-muted mb-2">
                    Journal d'audit des modifications d'électeurs (<?= count($journalElecteurs) ?>)
                </h6>
                <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Membre</th>
                                <th>Motif &amp; Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($journalElecteurs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3 small">Aucune modification exceptionnelle enregistrée.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($journalElecteurs as $j): ?>
                                    <tr>
                                        <td class="small"><?= date('d/m/Y H:i', strtotime($j['date_modification'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $j['type_modification'] === 'ajout' ? 'success' : ($j['type_modification'] === 'retrait' ? 'danger' : 'warning text-dark') ?>">
                                                <?= SecurityHelper::e(ucfirst($j['type_modification'])) ?>
                                            </span>
                                        </td>
                                        <td class="small fw-semibold"><?= SecurityHelper::e(($j['membre_nom'] ?? '') . ' ' . ($j['membre_prenoms'] ?? '')) ?></td>
                                        <td class="small">
                                            <div><?= SecurityHelper::e($j['motif']) ?></div>
                                            <small class="text-muted">Par <?= SecurityHelper::e(($j['admin_prenoms'] ?? '') . ' ' . ($j['admin_nom'] ?? 'Admin #' . $j['admin_id'])) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

    </div>
</main>

<?php include __DIR__ . '/../layout/footer.php'; ?>
