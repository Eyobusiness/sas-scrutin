<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Models/Election.php';
require_once __DIR__ . '/../../app/Models/Candidat.php';

SessionHelper::start();

// Si accédé directement sans passer par le contrôleur MVC
if (!isset($election)) {
    SecurityHelper::redirect('index.php?page=voter');
}

/** @var array $election */
$election = $election ?? [];
/** @var int $step */
$step = isset($step) ? (int)$step : 1;
/** @var array|null $stepMembre */
$stepMembre = ($step === 3) ? ($stepMembre ?? null) : null;
/** @var array $candidats */
$candidats = ($step === 3) ? ($candidats ?? []) : [];
$maxDate = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote Électronique | SAS Élection</title>

    <link rel="icon" type="image/png" href="assets/images/logo/logosas.png">
    <link rel="apple-touch-icon" href="assets/images/logo/logosas.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        .candidate-select-card {
            border: 2px solid #e2e8f0;
            border-radius: 1rem;
            transition: all 0.2s ease-in-out;
            cursor: pointer;
            background: #ffffff;
        }

        .candidate-select-card:hover {
            border-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.12);
        }

        .candidate-select-card.selected {
            border-color: #2563eb;
            background-color: #eff6ff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        .candidate-photo-ballot {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 50%;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .step-pill {
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            background: #e2e8f0;
            color: #64748b;
        }

        .step-pill.active {
            background: #2563eb;
            color: #ffffff;
        }

        .step-pill.done {
            background: #dcfce7;
            color: #15803d;
        }
    </style>
</head>

<body class="bg-light py-4">

    <div class="container" style="max-width: 860px;">

        <!-- Header -->
        <div class="text-center mb-3">
            <a href="index.php?page=home" class="d-inline-flex align-items-center gap-2 text-decoration-none text-primary mb-2">
                <img src="assets/images/logo/logosas.png" alt="Logo SAS" height="38" class="rounded shadow-sm">
                <span class="fs-4 fw-bold">SAS ÉLECTION</span>
            </a>
            <h1 class="h3 fw-bold mb-1"><?= SecurityHelper::e($election['titre']) ?></h1>
            <p class="text-muted small mb-0">
                <?php if (!empty($activeTour)): ?>
                    <span class="badge bg-primary me-1">
                        <i class="bi bi-flag-fill me-1"></i><?= SecurityHelper::e($activeTour['libelle'] ?? ('Tour ' . $activeTour['numero_tour'])) ?>
                    </span>
                <?php endif; ?>
                &bull; Scrutin officiel en ligne &bull; Clôture le <?= date('d/m/Y à H:i', strtotime($activeTour['date_fin_vote'] ?? ($election['date_fin_vote'] ?? $election['date_fin']))) ?>
            </p>
        </div>

        <!-- Indicateur des 3 étapes -->
        <div class="step-indicator flex-wrap">
            <span class="step-pill <?= $step === 1 ? 'active' : 'done' ?>">
                <i class="bi <?= $step > 1 ? 'bi-check-circle-fill' : 'bi-1-circle' ?> me-1"></i> 1. Numéro de téléphone
            </span>
            <span class="step-pill <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">
                <i class="bi <?= $step > 2 ? 'bi-check-circle-fill' : 'bi-2-circle' ?> me-1"></i> 2. Date de naissance
            </span>
            <span class="step-pill <?= $step === 3 ? 'active' : '' ?>">
                <i class="bi bi-3-circle me-1"></i> 3. Bulletin de vote
            </span>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <?php if ($step === 1): ?>
            <!-- ========================================== -->
            <!-- ÉTAPE 1 : Saisie du numéro de téléphone    -->
            <!-- ========================================== -->
            <div class="card border-0 shadow-sm p-3 p-sm-4 p-md-5 bg-white mx-auto" style="max-width: 580px;">
                <div class="text-center mb-4">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-telephone-check fs-2"></i>
                    </div>
                    <h2 class="h5 fw-bold mb-1">Étape 1 : Saisie du numéro de téléphone</h2>
                    <p class="text-muted small">Saisissez votre numéro de téléphone pour vérifier votre inscription sur la liste des électeurs.</p>
                </div>

                <div class="alert alert-light border small text-muted mb-4">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    L'accès au vote nécessite une double vérification (numéro de téléphone puis date de naissance) sans mot de passe.
                </div>

                <form method="post" action="index.php?page=voter">
                    <?= SessionHelper::csrfField() ?>
                    <input type="hidden" name="action" value="verifier_telephone">

                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="inputPhone">Numéro de téléphone</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="tel" class="form-control" id="inputPhone" name="telephone" placeholder="Votre numéro de téléphone" required autofocus>
                        </div>
                        <small class="text-muted">Numéro enregistré lors de votre inscription au SAS.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3">
                        <i class="bi bi-arrow-right-circle me-1"></i> Continuer vers l'étape 2
                    </button>
                </form>

                <div class="text-center mt-4 pt-3 border-top">
                    <small class="text-muted">
                        Pas encore inscrit ? <a href="index.php?page=inscription" class="fw-semibold text-decoration-none">Inscrivez-vous ici</a>
                    </small>
                </div>
            </div>

        <?php elseif ($step === 2): ?>
            <!-- ========================================== -->
            <!-- ÉTAPE 2 : Vérification date de naissance   -->
            <!-- ========================================== -->
            <div class="card border-0 shadow-sm p-3 p-sm-4 p-md-5 bg-white mx-auto" style="max-width: 580px;">
                <div class="text-center mb-4">
                    <div class="rounded-circle bg-primary-subtle text-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-calendar-check fs-2"></i>
                    </div>
                    <h2 class="h5 fw-bold mb-1">Étape 2 : Vérification de la date de naissance</h2>
                    <p class="text-muted small">
                        Votre numéro est reconnu sur la liste électorale. Veuillez confirmer votre identité en saisissant votre date de naissance.
                    </p>
                </div>

                <form method="post" action="index.php?page=voter">
                    <?= SessionHelper::csrfField() ?>
                    <input type="hidden" name="action" value="verifier_date_naissance">

                    <div class="mb-4">
                        <label class="form-label fw-semibold" for="inputDob">Date de naissance</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-calendar-event"></i></span>
                            <input type="date" class="form-control" id="inputDob" name="date_naissance" max="<?= $maxDate ?>" required autofocus>
                        </div>
                        <small class="text-muted">Saisissez la date de naissance exacte renseignée lors de votre inscription.</small>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold py-3 mb-3">
                        <i class="bi bi-shield-check me-1"></i> Vérifier et accéder au bulletin de vote
                    </button>
                </form>

                <div class="text-center pt-2 border-top">
                    <a href="index.php?page=voter&reset=1" class="btn btn-link btn-sm text-decoration-none text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Changer de numéro de téléphone
                    </a>
                </div>
            </div>

        <?php elseif ($step === 3 && !empty($stepMembre)): ?>
            <!-- ========================================== -->
            <!-- ÉTAPE 3 : Accès au bulletin de vote        -->
            <!-- ========================================== -->
            <div class="card border-0 shadow-sm p-3 p-sm-4 p-md-5 bg-white">
                <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 mb-4 border-bottom gap-2">
                    <div>
                        <span class="badge bg-success-subtle text-success px-2 py-1 mb-1">
                            <i class="bi bi-check-circle-fill me-1"></i> Identité et éligibilité vérifiées
                        </span>
                        <h2 class="h5 fw-bold mb-0">Étape 3 : Bulletin de vote électronique</h2>
                        <small class="text-muted">
                            Électeur : <strong><?= SecurityHelper::e($stepMembre['prenoms'] . ' ' . $stepMembre['nom']) ?></strong> (<?= SecurityHelper::e($stepMembre['telephone']) ?>)
                        </small>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark border px-3 py-2">
                            <i class="bi bi-shield-shaded text-primary me-1"></i> Vote secret garanti
                        </span>
                    </div>
                </div>

                <?php if (empty($candidats)): ?>
                    <div class="alert alert-warning text-center py-4">
                        <i class="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
                        Aucun candidat validé n'est actuellement inscrit sur le bulletin de ce scrutin.
                    </div>
                <?php else: ?>
                    <form method="post" action="index.php?page=voter" id="ballotForm" novalidate>
                        <?= SessionHelper::csrfField() ?>
                        <input type="hidden" name="action" value="enregistrer_vote">

                        <p class="fw-semibold text-dark mb-3">
                            Veuillez choisir un seul candidat en cliquant sur sa carte :
                        </p>

                        <div class="row g-3 mb-4">
                            <?php foreach ($candidats as $c): ?>
                                <div class="col-12 col-md-6">
                                    <label class="candidate-select-card p-3 d-flex align-items-center gap-3 w-100" for="candidat_<?= $c['id'] ?>">
                                        <input class="form-check-input mt-0 fs-5 flex-shrink-0" type="radio" name="candidat_id" id="candidat_<?= $c['id'] ?>" value="<?= $c['id'] ?>" data-candidate-name="<?= SecurityHelper::e($c['nom'] . ' ' . $c['prenoms']) ?>" data-candidate-photo="<?= !empty($c['photo']) ? 'uploads/candidats/' . SecurityHelper::e($c['photo']) : '' ?>" required>

                                        <?php if (!empty($c['photo'])): ?>
                                            <img src="uploads/candidats/<?= SecurityHelper::e($c['photo']) ?>" alt="Photo" class="candidate-photo-ballot shadow-sm">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 80px; height: 80px;">
                                                <i class="bi bi-person fs-1"></i>
                                            </div>
                                        <?php endif; ?>

                                        <div class="flex-grow-1">
                                            <h3 class="h6 fw-bold mb-1 text-dark"><?= SecurityHelper::e($c['nom'] . ' ' . $c['prenoms']) ?></h3>
                                            <p class="text-muted small mb-0" style="font-size: 0.8rem; line-height: 1.3;">
                                                <?= nl2br(SecurityHelper::e($c['motif_candidature'])) ?>
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Confirmation solennelle -->
                        <div class="card bg-light border-0 p-3 mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmVote" required>
                                <label class="form-check-label small fw-semibold text-dark" for="confirmVote">
                                    Je confirme mon choix en mon âme et conscience pour cette élection SAS. Je reconnais que mon vote est unique, définitif et confidentiel.
                                </label>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-sm-row gap-2 justify-content-between align-items-stretch align-items-sm-center">
                            <a href="index.php?page=voter&reset=1" class="btn btn-outline-secondary order-2 order-sm-1">
                                <i class="bi bi-arrow-left me-1"></i> Annuler et recommencer
                            </a>
                            <button type="submit" class="btn btn-success btn-lg px-4 fw-bold shadow-sm order-1 order-sm-2" id="btnSubmitVote">
                                <i class="bi bi-check-lg me-1"></i> Confirmer et enregistrer mon vote
                            </button>
                        </div>
                    </form>

                    <!-- Popup style SweetAlert : Alerte de saisie manquante -->
                    <div class="modal fade sas-swal-modal" id="voteAlertModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
                            <div class="modal-content p-4 text-center">
                                <div class="modal-body p-2">
                                    <div class="sas-swal-icon is-warning">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                    </div>
                                    <h3 class="h5 fw-bold text-dark mb-2" id="voteAlertTitle">Attention</h3>
                                    <p class="text-muted mb-4" id="voteAlertMessage">Veuillez sélectionner un candidat.</p>
                                    <button type="button" class="btn btn-primary px-4 py-2 fw-bold w-100 rounded-3" data-bs-dismiss="modal">
                                        J'ai compris
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Popup style SweetAlert : Confirmation définitive du vote -->
                    <div class="modal fade sas-swal-modal" id="voteConfirmModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered" style="max-width: 460px;">
                            <div class="modal-content p-4 text-center">
                                <div class="modal-body p-2">
                                    <div class="sas-swal-icon is-confirm">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <h3 class="h4 fw-bold text-dark mb-2">Confirmez-vous votre vote ?</h3>
                                    <p class="text-muted small mb-3">
                                        Vous êtes sur le point d'enregistrer définitivement votre voix pour :
                                    </p>

                                    <!-- Aperçu du candidat sélectionné -->
                                    <div class="sas-swal-candidate-box d-flex align-items-center gap-3 text-start mb-3">
                                        <div id="swalCandidatePhotoWrap">
                                            <div class="sas-swal-candidate-placeholder">
                                                <i class="bi bi-person-check-fill"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1">Votre choix</span>
                                            <div class="fw-bold text-dark fs-6" id="swalCandidateName">&mdash;</div>
                                        </div>
                                    </div>

                                    <div class="alert alert-warning border-warning-subtle small text-start py-2 px-3 mb-4">
                                        <i class="bi bi-info-circle-fill me-1"></i>
                                        <strong>Action irréversible :</strong> une fois validé, votre bulletin est déposé dans l'urne de façon anonyme et ne pourra plus être modifié.
                                    </div>

                                    <div class="d-flex flex-column flex-sm-row gap-2">
                                        <button type="button" class="btn btn-light border fw-semibold flex-fill py-2" data-bs-dismiss="modal">
                                            Non, modifier
                                        </button>
                                        <button type="button" class="btn btn-success fw-bold flex-fill py-2 shadow-sm" id="btnFinalConfirmVote">
                                            <i class="bi bi-check2-circle me-1"></i> Oui, je confirme
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const radios = document.querySelectorAll('input[name="candidat_id"]');
            radios.forEach(radio => {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.candidate-select-card').forEach(card => card.classList.remove('selected'));
                    if (this.checked) {
                        this.closest('.candidate-select-card').classList.add('selected');
                    }
                });
            });

            const form = document.getElementById('ballotForm');
            if (form) {
                let voteConfirmedByModal = false;
                const alertModalEl = document.getElementById('voteAlertModal');
                const confirmModalEl = document.getElementById('voteConfirmModal');
                const alertTitleEl = document.getElementById('voteAlertTitle');
                const alertMsgEl = document.getElementById('voteAlertMessage');
                const swalNameEl = document.getElementById('swalCandidateName');
                const swalPhotoWrap = document.getElementById('swalCandidatePhotoWrap');
                const btnFinalConfirm = document.getElementById('btnFinalConfirmVote');

                function showAlertPopup(title, message) {
                    if (alertModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        if (alertTitleEl) alertTitleEl.textContent = title;
                        if (alertMsgEl) alertMsgEl.textContent = message;
                        bootstrap.Modal.getOrCreateInstance(alertModalEl).show();
                    } else {
                        alert(message);
                    }
                }

                form.addEventListener('submit', function(e) {
                    if (voteConfirmedByModal) {
                        return;
                    }

                    const selected = document.querySelector('input[name="candidat_id"]:checked');
                    if (!selected) {
                        e.preventDefault();
                        showAlertPopup(
                            'Sélection d\'un candidat requise',
                            'Veuillez sélectionner un candidat en cliquant sur sa carte avant de continuer.'
                        );
                        return;
                    }

                    const confirmBox = document.getElementById('confirmVote');
                    if (!confirmBox.checked) {
                        e.preventDefault();
                        showAlertPopup(
                            'Engagement requis',
                            'Veuillez cocher la case de confirmation solennelle avant de valider votre vote.'
                        );
                        return;
                    }

                    if (confirmModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        e.preventDefault();
                        const candName = selected.getAttribute('data-candidate-name') || 'Candidat sélectionné';
                        const candPhoto = selected.getAttribute('data-candidate-photo') || '';

                        if (swalNameEl) {
                            swalNameEl.textContent = candName;
                        }
                        if (swalPhotoWrap) {
                            if (candPhoto) {
                                swalPhotoWrap.innerHTML = '';
                                const img = document.createElement('img');
                                img.src = candPhoto;
                                img.alt = candName;
                                img.className = 'sas-swal-candidate-photo';
                                swalPhotoWrap.appendChild(img);
                            } else {
                                swalPhotoWrap.innerHTML = '<div class="sas-swal-candidate-placeholder"><i class="bi bi-person-check-fill"></i></div>';
                            }
                        }

                        bootstrap.Modal.getOrCreateInstance(confirmModalEl).show();
                    } else if (!confirm('Confirmez-vous définitivement votre vote ? Cette action est irréversible.')) {
                        e.preventDefault();
                    }
                });

                if (btnFinalConfirm) {
                    btnFinalConfirm.addEventListener('click', function() {
                        voteConfirmedByModal = true;
                        btnFinalConfirm.classList.add('is-loading');
                        btnFinalConfirm.innerHTML = '<span class="sas-btn-spinner" aria-hidden="true"></span> Enregistrement...';
                        form.submit();
                    });
                }
            }
        });
    </script>
</body>

</html>
