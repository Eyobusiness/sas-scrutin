<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Helpers/AuthHelper.php';
require_once __DIR__ . '/../../app/Models/Election.php';
require_once __DIR__ . '/../../app/Services/ElectionService.php';

SessionHelper::start();
AuthHelper::requireLogin();

if (!isset($elections) || !is_array($elections)) {
    $elections = (new ElectionService())->getAllElectionsEnriched();
}
if (!isset($derniereElection)) {
    $derniereElection = (new ElectionService())->getDerniereElection();
}
if (!isset($stats) || !is_array($stats)) {
    $stats = (new Election())->getStats();
}

$pageTitle = 'Gestion des Élections';
$pageSubtitle = 'Planification des scrutins';
$currentPage = 'elections';

include __DIR__ . '/../layout/head.php';
?>

<main class="dashboard-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="page-heading-copy">
                <span class="page-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
                <div>
                    <h1 class="h3 mb-1"><?= SecurityHelper::e($pageTitle) ?></h1>
                    <p class="text-muted mb-0"><?= SecurityHelper::e($pageSubtitle) ?></p>
                </div>
            </div>
            <!-- <div class="heading-actions">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddElection">
                    <i class="bi bi-plus-circle me-1"></i> Créer une élection
                </button>
            </div> -->
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <!-- Bandeau du scrutin de référence -->
        <?php if (!empty($derniereElection)): ?>
            <?php $refPhase = $derniereElection['phase_info'] ?? null; ?>
            <div class="card border-0 shadow-sm mb-4 bg-white border-start border-primary border-4 p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i> Dernier scrutin de référence (Public)</span>
                            <?php if ($refPhase): ?>
                                <span class="badge bg-<?= SecurityHelper::e($refPhase['badge']) ?>">
                                    Phase actuelle : <?= SecurityHelper::e($refPhase['label']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h2 class="h5 fw-bold mb-1"><?= SecurityHelper::e($derniereElection['titre']) ?></h2>
                        <div class="text-muted small">
                            <span><strong>Membres éligibles :</strong> <?= (int)($derniereElection['nb_eligibles'] ?? 0) ?></span> &bull;
                            <span><strong>Candidats validés :</strong> <?= (int)($derniereElection['nb_candidats_valides'] ?? 0) ?> / <?= (int)($derniereElection['nb_candidats'] ?? 0) ?></span> &bull;
                            <span><strong>Votes enregistrés :</strong> <?= (int)($derniereElection['nb_votants'] ?? 0) ?></span>
                        </div>
                    </div>
                    <div>
                        <a href="index.php?page=election_details&id=<?= SecurityHelper::encryptId($derniereElection['id']) ?>" class="btn btn-outline-primary btn-sm fw-semibold">
                            <i class="bi bi-diagram-3 me-1"></i> Chronologie &amp; Détails
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Cartes statistiques -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 text-center">
                    <span class="text-muted small">Total Élections</span>
                    <h3 class="fw-bold mb-0"><?= (int)($stats['total'] ?? 0) ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 text-center">
                    <span class="text-muted small text-success">Ouvertes</span>
                    <h3 class="fw-bold text-success mb-0"><?= (int)($stats['ouvertes'] ?? 0) ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 text-center">
                    <span class="text-muted small text-warning">En préparation</span>
                    <h3 class="fw-bold text-warning mb-0"><?= (int)($stats['preparations'] ?? 0) ?></h3>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm p-3 text-center">
                    <span class="text-muted small text-secondary">Clôturées</span>
                    <h3 class="fw-bold text-secondary mb-0"><?= (int)($stats['cloturees'] ?? 0) ?></h3>
                </div>
            </div>
        </div>

        <!-- Barre d'action -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <div>
                <span class="text-muted small">Fuseau horaire officiel du système : <strong>Africa/Abidjan (UTC+00:00)</strong></span>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddElection">
                <i class="bi bi-plus-circle me-1"></i> Créer une élection
            </button>
        </div>

        <!-- Liste des élections -->
        <div class="row g-4">
            <?php if (empty($elections)): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm p-5 text-center text-muted">
                        <i class="bi bi-calendar2-x fs-1 mb-2"></i>
                        <p class="mb-0">Aucune élection n'a encore été créée.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($elections as $e): ?>
                    <?php
                    $pInfo = $e['phase_info'] ?? null;
                    $isReference = !empty($derniereElection) && (int)$derniereElection['id'] === (int)$e['id'];
                    ?>
                    <div class="col-12 col-lg-6">
                        <div class="card border-0 shadow-sm h-100 <?= $isReference ? 'border-top border-primary border-3' : '' ?>">
                            <div class="card-body d-flex flex-column justify-content-between">
                                <div>
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php if ($isReference): ?>
                                                <span class="badge bg-primary"><i class="bi bi-star-fill me-1"></i>Scrutin de référence</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Scrutin archivé</span>
                                            <?php endif; ?>

                                            <?php if ($pInfo): ?>
                                                <span class="badge bg-<?= SecurityHelper::e($pInfo['badge']) ?>">
                                                    Phase : <?= SecurityHelper::e($pInfo['label']) ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="badge bg-dark">
                                                <i class="bi bi-layers-fill me-1"></i> Tour <?= (int)($e['tour_courant'] ?? 1) ?> / Max <?= (int)($e['nombre_max_tours'] ?? 3) ?>
                                            </span>
                                            <?php if (!empty($e['etat_egalite_persistante'])): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Égalité persistante
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div>
                                            <?php if ($e['statut'] === 'ouverte'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                    <i class="bi bi-broadcast me-1"></i> Ouverte
                                                </span>
                                            <?php elseif ($e['statut'] === 'preparation'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                                                    <i class="bi bi-gear me-1"></i> En préparation
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                                    <i class="bi bi-lock me-1"></i> Clôturée
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <h3 class="h5 fw-bold mb-2"><?= SecurityHelper::e($e['titre']) ?></h3>
                                    <p class="text-muted small mb-3">
                                        <?= !empty($e['description']) ? nl2br(SecurityHelper::e($e['description'])) : '<em>Aucune description fournie.</em>' ?>
                                    </p>

                                    <div class="bg-light rounded p-3 mb-3 small">
                                        <div class="row g-2">
                                            <?php if (!empty($e['date_ouverture_inscriptions']) && !empty($e['date_fermeture_inscriptions'])): ?>
                                                <div class="col-12">
                                                    <i class="bi bi-person-plus text-success me-1"></i>
                                                    <strong>Inscriptions :</strong>
                                                    <?= date('d/m/Y H:i', strtotime($e['date_ouverture_inscriptions'])) ?> &rarr; <?= date('d/m/Y H:i', strtotime($e['date_fermeture_inscriptions'])) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($e['date_ouverture_candidatures']) && !empty($e['date_fermeture_candidatures'])): ?>
                                                <div class="col-12">
                                                    <i class="bi bi-person-badge text-primary me-1"></i>
                                                    <strong>Candidatures :</strong>
                                                    <?= date('d/m/Y H:i', strtotime($e['date_ouverture_candidatures'])) ?> &rarr; <?= date('d/m/Y H:i', strtotime($e['date_fermeture_candidatures'])) ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($e['date_debut_campagne']) && !empty($e['date_fin_campagne'])): ?>
                                                <div class="col-12">
                                                    <i class="bi bi-megaphone text-warning me-1"></i>
                                                    <strong>Campagne :</strong>
                                                    <?= date('d/m/Y H:i', strtotime($e['date_debut_campagne'])) ?> &rarr; <?= date('d/m/Y H:i', strtotime($e['date_fin_campagne'])) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="col-12">
                                                <i class="bi bi-fingerprint text-danger me-1"></i>
                                                <strong>Vote :</strong>
                                                <?= date('d/m/Y H:i', strtotime($e['date_debut_vote'] ?? $e['date_debut'])) ?> &rarr; <?= date('d/m/Y H:i', strtotime($e['date_fin_vote'] ?? $e['date_fin'])) ?>
                                            </div>
                                            <div class="col-4 mt-2 pt-2 border-top">
                                                <i class="bi bi-person-check me-1 text-success"></i>
                                                <strong>Éligibles :</strong> <?= (int)($e['nb_eligibles'] ?? 0) ?>
                                            </div>
                                            <div class="col-4 mt-2 pt-2 border-top">
                                                <i class="bi bi-people me-1 text-info"></i>
                                                <strong>Candidats validés :</strong> <?= (int)($e['nb_candidats_valides'] ?? 0) ?>/<?= (int)($e['nb_candidats'] ?? 0) ?>
                                            </div>
                                            <div class="col-4 mt-2 pt-2 border-top">
                                                <i class="bi bi-check2-square me-1 text-primary"></i>
                                                <strong>Votes :</strong> <?= (int)($e['nb_votants'] ?? 0) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-2 border-top">
                                    <div class="d-flex gap-1">
                                        <a href="index.php?page=election_details&id=<?= SecurityHelper::encryptId($e['id']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> Détails &amp; Phases
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#modalEditElection<?= $e['id'] ?>">
                                            <i class="bi bi-pencil me-1"></i> Modifier
                                        </button>
                                        <a href="index.php?page=resultats_admin&election_id=<?= SecurityHelper::encryptId($e['id']) ?>" class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-bar-chart-line me-1"></i> Résultats
                                        </a>
                                    </div>

                                    <!-- Boutons rapides de changement de statut -->
                                    <div class="d-flex gap-1">
                                        <?php if ($e['statut'] === 'preparation'): ?>
                                            <form method="post" action="index.php?page=election_change_statut" class="d-inline" data-confirm="Voulez-vous ouvrir officiellement ce scrutin au vote ?">
                                                <?= SessionHelper::csrfField() ?>
                                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                                <input type="hidden" name="statut" value="ouverte">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="bi bi-play-fill"></i> Ouvrir le vote
                                                </button>
                                            </form>
                                        <?php elseif ($e['statut'] === 'ouverte'): ?>
                                            <form method="post" action="index.php?page=election_change_statut" class="d-inline" data-confirm="Attention : Clôturer ce scrutin rendra les résultats publics et empêchera tout nouveau vote. Continuer ?">
                                                <?= SessionHelper::csrfField() ?>
                                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                                <input type="hidden" name="statut" value="cloturee">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-stop-fill"></i> Clôturer
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Modification Élection -->
                    <div class="modal fade" id="modalEditElection<?= $e['id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <form method="post" action="index.php?page=election_update" class="modal-content">
                                <?= SessionHelper::csrfField() ?>
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">Modifier l'élection et ses périodes électorales</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Titre de l'élection <span class="text-danger">*</span></label>
                                        <input type="text" name="titre" class="form-control" value="<?= SecurityHelper::e($e['titre']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description / Instructions</label>
                                        <textarea name="description" class="form-control" rows="2"><?= SecurityHelper::e($e['description'] ?? '') ?></textarea>
                                    </div>

                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">1. Période d'inscriptions des membres</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Ouverture des inscriptions</label>
                                            <input type="datetime-local" name="date_ouverture_inscriptions" class="form-control"
                                                value="<?= !empty($e['date_ouverture_inscriptions']) ? date('Y-m-d\TH:i', strtotime($e['date_ouverture_inscriptions'])) : '' ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Fermeture des inscriptions</label>
                                            <input type="datetime-local" name="date_fermeture_inscriptions" class="form-control"
                                                value="<?= !empty($e['date_fermeture_inscriptions']) ? date('Y-m-d\TH:i', strtotime($e['date_fermeture_inscriptions'])) : '' ?>">
                                        </div>
                                    </div>

                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">2. Période de dépôt des candidatures</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Ouverture des candidatures</label>
                                            <input type="datetime-local" name="date_ouverture_candidatures" class="form-control"
                                                value="<?= !empty($e['date_ouverture_candidatures']) ? date('Y-m-d\TH:i', strtotime($e['date_ouverture_candidatures'])) : '' ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Fermeture des candidatures</label>
                                            <input type="datetime-local" name="date_fermeture_candidatures" class="form-control"
                                                value="<?= !empty($e['date_fermeture_candidatures']) ? date('Y-m-d\TH:i', strtotime($e['date_fermeture_candidatures'])) : '' ?>">
                                        </div>
                                    </div>

                                    <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">3. Période de campagne électorale</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Début de la campagne</label>
                                            <input type="datetime-local" name="date_debut_campagne" class="form-control"
                                                value="<?= !empty($e['date_debut_campagne']) ? date('Y-m-d\TH:i', strtotime($e['date_debut_campagne'])) : '' ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Fin de la campagne</label>
                                            <input type="datetime-local" name="date_fin_campagne" class="form-control"
                                                value="<?= !empty($e['date_fin_campagne']) ? date('Y-m-d\TH:i', strtotime($e['date_fin_campagne'])) : '' ?>">
                                        </div>
                                    </div>

                                    <h6 class="fw-bold text-danger border-bottom pb-2 mb-3">4. Période officielle de vote <span class="text-danger">*</span></h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Début du vote <span class="text-danger">*</span></label>
                                            <input type="datetime-local" name="date_debut_vote" class="form-control"
                                                value="<?= date('Y-m-d\TH:i', strtotime($e['date_debut_vote'] ?? $e['date_debut'])) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold">Fin du vote <span class="text-danger">*</span></label>
                                            <input type="datetime-local" name="date_fin_vote" class="form-control"
                                                value="<?= date('Y-m-d\TH:i', strtotime($e['date_fin_vote'] ?? $e['date_fin'])) ?>" required>
                                        </div>
                                    </div>

                                    <div class="row g-3 mb-2">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Statut du scrutin</label>
                                            <select name="statut" class="form-select">
                                                <option value="preparation" <?= $e['statut'] === 'preparation' ? 'selected' : '' ?>>En préparation</option>
                                                <option value="ouverte" <?= $e['statut'] === 'ouverte' ? 'selected' : '' ?>>Ouverte au vote</option>
                                                <option value="cloturee" <?= $e['statut'] === 'cloturee' ? 'selected' : '' ?>>Clôturée</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold">Nombre maximal de tours autorisés</label>
                                            <input type="number" name="nombre_max_tours" class="form-control" min="1" max="10" value="<?= (int)($e['nombre_max_tours'] ?? 3) ?>" required>
                                            <div class="form-text small">Si l'égalité persiste après ce nombre de tours, une décision du bureau sera requise.</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Modal Création Élection -->
        <div class="modal fade" id="modalAddElection" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="post" action="index.php?page=election_store" class="modal-content">
                    <?= SessionHelper::csrfField() ?>
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">Créer un nouveau scrutin</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 small">
                            <i class="bi bi-info-circle me-1"></i> Toute nouvelle élection créée devient automatiquement le <strong>scrutin de référence</strong> sur les pages publiques.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Titre de l'élection <span class="text-danger">*</span></label>
                            <input type="text" name="titre" class="form-control" placeholder="Ex: Élection du Président du SAS 2026-2028" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description / Instructions aux électeurs</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Règles du scrutin, informations générales..."></textarea>
                        </div>

                        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">1. Période d'inscriptions des membres</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Ouverture des inscriptions</label>
                                <input type="datetime-local" name="date_ouverture_inscriptions" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Fermeture des inscriptions</label>
                                <input type="datetime-local" name="date_fermeture_inscriptions" class="form-control">
                            </div>
                        </div>

                        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">2. Période de dépôt des candidatures</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Ouverture des candidatures</label>
                                <input type="datetime-local" name="date_ouverture_candidatures" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Fermeture des candidatures</label>
                                <input type="datetime-local" name="date_fermeture_candidatures" class="form-control">
                            </div>
                        </div>

                        <h6 class="fw-bold text-primary border-bottom pb-2 mb-3">3. Période de campagne électorale</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Début de la campagne</label>
                                <input type="datetime-local" name="date_debut_campagne" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Fin de la campagne</label>
                                <input type="datetime-local" name="date_fin_campagne" class="form-control">
                            </div>
                        </div>

                        <h6 class="fw-bold text-danger border-bottom pb-2 mb-3">4. Période officielle de vote <span class="text-danger">*</span></h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Début du vote <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="date_debut_vote" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Fin du vote <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="date_fin_vote" class="form-control" required>
                            </div>
                        </div>

                        <div class="row g-3 mb-2">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Statut initial</label>
                                <select name="statut" class="form-select">
                                    <option value="preparation" selected>En préparation (recommandé)</option>
                                    <option value="ouverte">Ouverte immédiatement</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nombre maximal de tours autorisés</label>
                                <input type="number" name="nombre_max_tours" class="form-control" min="1" max="10" value="3" required>
                                <div class="form-text small">Par défaut : 3 tours max. Configurable par le bureau.</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer l'élection</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</main>

<?php include __DIR__ . '/../layout/footer.php'; ?>