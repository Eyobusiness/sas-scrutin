<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../../app/Helpers/AuthHelper.php';
require_once __DIR__ . '/../../app/Models/Administrateur.php';

SessionHelper::start();
AuthHelper::requireSuperAdmin();

$currentAdmin = AuthHelper::user() ?? [];
$currentAdminId = (int)($currentAdmin['id'] ?? 0);

if (!isset($admins) || !is_array($admins)) {
    $adminModel = new Administrateur();
    $admins = $adminModel->getAll();
}
if (!isset($roles) || !is_array($roles)) {
    $adminModel = new Administrateur();
    $roles = $adminModel->getRoles();
}

$pageTitle = 'Gestion des administrateurs';
include __DIR__ . '/../layout/head.php';
?>

<main class="dashboard-content">
    <div class="container-fluid px-3 px-lg-4 py-4">

        <div class="page-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="page-heading-copy">
                <span class="page-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
                <div>
                    <h1 class="h3 mb-1">Comptes Administrateurs</h1>
                    <p class="text-muted mb-0">Gestion des accès au bureau électoral et attribution des privilèges (Réservé au Super Admin).</p>
                </div>
            </div>
            <div class="heading-actions">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="bi bi-person-plus me-1"></i> Nouvel administrateur
                </button>
            </div>
        </div>

        <?= SessionHelper::renderFlashes() ?>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0 fs-6 fw-bold">
                    <i class="bi bi-shield-check me-2 text-primary"></i> Administrateurs enregistrés (<?= count($admins) ?>)
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>N°</th>
                            <th>Nom & Prénoms</th>
                            <th>Téléphone</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th>Date de création</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($admins)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    Aucun compte administrateur enregistré.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($admins as $index => $adm): ?>
                                <tr>
                                    <td class="text-muted small"><?= $index + 1 ?></td>
                                    <td>
                                        <strong><?= SecurityHelper::e(($adm['nom'] ?? '') . ' ' . ($adm['prenoms'] ?? '')) ?></strong>
                                        <?php if ((int)($adm['id'] ?? 0) === $currentAdminId): ?>
                                            <span class="badge bg-primary-subtle text-primary ms-1 small">Vous</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= SecurityHelper::e($adm['telephone'] ?? '') ?></code></td>
                                    <td><?= SecurityHelper::e($adm['email'] ?? '—') ?></td>
                                    <td>
                                        <?php if (($adm['role_code'] ?? '') === 'super_admin'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                                <i class="bi bi-shield-shaded me-1"></i> Super Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                                <i class="bi bi-shield me-1"></i> Administrateur
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($adm['statut'] ?? '') === 'actif'): ?>
                                            <span class="badge bg-success-subtle text-success px-2 py-1">Actif</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary px-2 py-1">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= !empty($adm['cree_le']) ? date('d/m/Y', strtotime($adm['cree_le'])) : '—' ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}'>
                                                Options
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li>
                                                    <button type="button" class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editAdminModal"
                                                            data-id="<?= $adm['id'] ?>"
                                                            data-nom="<?= SecurityHelper::e($adm['nom'] ?? '') ?>"
                                                            data-prenoms="<?= SecurityHelper::e($adm['prenoms'] ?? '') ?>"
                                                            data-telephone="<?= SecurityHelper::e($adm['telephone'] ?? '') ?>"
                                                            data-email="<?= SecurityHelper::e($adm['email'] ?? '') ?>"
                                                            data-role-id="<?= $adm['role_id'] ?? 2 ?>"
                                                            data-statut="<?= $adm['statut'] ?? 'actif' ?>">
                                                        <i class="bi bi-pencil me-2 text-secondary"></i> Modifier
                                                    </button>
                                                </li>
                                                <?php if ((int)($adm['id'] ?? 0) !== $currentAdminId): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="post" action="index.php?page=admin_toggle_statut" onsubmit="return confirm('Confirmez-vous le changement de statut de cet administrateur ?');">
                                                            <?= SessionHelper::csrfField() ?>
                                                            <input type="hidden" name="id" value="<?= $adm['id'] ?>">
                                                            <button type="submit" class="dropdown-item <?= (($adm['statut'] ?? '') === 'actif') ? 'text-danger' : 'text-success' ?>">
                                                                <i class="bi bi-power me-2"></i> <?= (($adm['statut'] ?? '') === 'actif') ? 'Désactiver le compte' : 'Activer le compte' ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
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

<!-- Modal Nouvel Administrateur -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="index.php?page=admin_store">
                <?= SessionHelper::csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2 text-primary"></i> Créer un administrateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="nom" class="form-control" required placeholder="Ex: KOUASSI">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prénoms <span class="text-danger">*</span></label>
                        <input type="text" name="prenoms" class="form-control" required placeholder="Ex: Jean">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Téléphone (identifiant de connexion) <span class="text-danger">*</span></label>
                        <input type="tel" name="telephone" class="form-control" required placeholder="Ex: 0700000000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email (facultatif)</label>
                        <input type="email" name="email" class="form-control" placeholder="admin@sas-eglise.org">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mot de passe provisoire <span class="text-danger">*</span></label>
                        <input type="password" name="mot_de_passe" class="form-control" required minlength="6" placeholder="Minimum 6 caractères">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rôle d'administration <span class="text-danger">*</span></label>
                        <select name="role_id" class="form-select" required>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= (($r['code'] ?? '') === 'admin') ? 'selected' : '' ?>>
                                    <?= SecurityHelper::e($r['libelle']) ?> &mdash; <?= SecurityHelper::e($r['description'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Statut</label>
                        <select name="statut" class="form-select">
                            <option value="actif" selected>Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Créer l'administrateur</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Modifier Administrateur -->
<div class="modal fade" id="editAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="index.php?page=admin_update">
                <?= SessionHelper::csrfField() ?>
                <input type="hidden" name="id" id="editAdmId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2 text-primary"></i> Modifier l'administrateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="nom" id="editAdmNom" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Prénoms <span class="text-danger">*</span></label>
                        <input type="text" name="prenoms" id="editAdmPrenoms" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                        <input type="tel" name="telephone" id="editAdmTelephone" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="editAdmEmail" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
                        <input type="password" name="mot_de_passe" class="form-control" placeholder="Laisser vide pour conserver l'actuel">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rôle</label>
                        <select name="role_id" id="editAdmRoleId" class="form-select">
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= SecurityHelper::e($r['libelle']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Statut</label>
                        <select name="statut" id="editAdmStatut" class="form-select">
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editAdminModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('editAdmId').value = btn.getAttribute('data-id');
            document.getElementById('editAdmNom').value = btn.getAttribute('data-nom');
            document.getElementById('editAdmPrenoms').value = btn.getAttribute('data-prenoms');
            document.getElementById('editAdmTelephone').value = btn.getAttribute('data-telephone');
            document.getElementById('editAdmEmail').value = btn.getAttribute('data-email');
            document.getElementById('editAdmRoleId').value = btn.getAttribute('data-role-id');
            document.getElementById('editAdmStatut').value = btn.getAttribute('data-statut');
        });
    }
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
