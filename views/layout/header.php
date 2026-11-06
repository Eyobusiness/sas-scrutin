<?php
$currentAdmin = AuthHelper::user();
?>
<nav class="navbar admin-navbar navbar-expand bg-white">
    <div class="container-fluid px-3 px-lg-4">
        <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-controls="adminSidebar" aria-expanded="true" aria-label="Afficher/Masquer le menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="d-none d-md-flex ms-3 align-items-center">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                <i class="bi bi-shield-check me-1"></i> Espace d'administration sécurisé
            </span>
        </div>

        <div class="navbar-actions ms-auto d-flex align-items-center gap-2">
            <!-- Lien vers le portail public -->
            <a href="index.php?page=home" target="_blank" class="btn btn-outline-secondary btn-sm" title="Ouvrir le portail public dans un nouvel onglet">
                <i class="bi bi-box-arrow-up-right me-1"></i> <span class="d-none d-sm-inline">Portail public</span>
            </a>

            <!-- Profil de l'administrateur -->
            <div class="dropdown">
                <button class="profile-button dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 36px; height: 36px;">
                        <?= strtoupper(substr($currentAdmin['prenoms'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="text-start d-none d-sm-block">
                        <span class="profile-name fw-semibold d-block lh-1 text-dark"><?= SecurityHelper::e(($currentAdmin['prenoms'] ?? '') . ' ' . ($currentAdmin['nom'] ?? '')) ?></span>
                        <small class="text-muted" style="font-size: 0.75rem;"><?= SecurityHelper::e($currentAdmin['role_nom'] ?? 'Admin') ?></small>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li class="dropdown-header">
                        <strong><?= SecurityHelper::e(($currentAdmin['prenoms'] ?? '') . ' ' . ($currentAdmin['nom'] ?? '')) ?></strong>
                        <div class="small text-muted"><?= SecurityHelper::e($currentAdmin['telephone'] ?? '') ?></div>
                        <span class="badge bg-secondary-subtle text-secondary mt-1"><?= SecurityHelper::e($currentAdmin['role_nom'] ?? '') ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (AuthHelper::isSuperAdmin()): ?>
                        <li><a class="dropdown-item" href="index.php?page=admins"><i class="bi bi-people me-2 text-primary"></i> Gérer les admins</a></li>
                        <li><a class="dropdown-item" href="index.php?page=settings"><i class="bi bi-gear me-2 text-secondary"></i> Paramètres</a></li>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li>
                        <a class="dropdown-item text-danger" href="index.php?page=logout">
                            <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
