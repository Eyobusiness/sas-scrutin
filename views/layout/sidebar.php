<?php
$currentPage = $_GET['page'] ?? 'dashboard';
$isSuperAdmin = AuthHelper::isSuperAdmin();
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Menu principal">
    <div class="sidebar-header">
        <a class="brand-mark" href="index.php?page=dashboard" aria-label="SAS Élection">
            <span class="brand-icon bg-white p-1 border shadow-sm">
                <img src="assets/images/logo/logosas.png" alt="Logo SAS" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px;">
            </span>
            <span class="brand-copy">
                <span class="brand-title">SAS ÉLECTION</span>
                <span class="brand-subtitle">Au service du Christ</span>
            </span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <!-- 1. Tableau de bord -->
        <a class="nav-link <?= ($currentPage === 'dashboard') ? 'active' : '' ?>" href="index.php?page=dashboard">
            <span class="nav-icon"><i class="bi bi-speedometer2" aria-hidden="true"></i></span>
            <span class="nav-text">Tableau de bord</span>
        </a>

        <!-- 2. Membres -->
        <a class="nav-link <?= ($currentPage === 'membres') ? 'active' : '' ?>" href="index.php?page=membres">
            <span class="nav-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            <span class="nav-text">Membres</span>
        </a>

        <!-- 3. Candidatures -->
        <a class="nav-link <?= ($currentPage === 'candidats') ? 'active' : '' ?>" href="index.php?page=candidats">
            <span class="nav-icon"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
            <span class="nav-text">Candidatures</span>
        </a>

        <!-- 4. Élections -->
        <a class="nav-link <?= (in_array($currentPage, ['elections', 'election_details'])) ? 'active' : '' ?>" href="index.php?page=elections">
            <span class="nav-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
            <span class="nav-text">Élections</span>
        </a>

        <!-- 5. Participations -->
        <a class="nav-link <?= ($currentPage === 'participations') ? 'active' : '' ?>" href="index.php?page=participations">
            <span class="nav-icon"><i class="bi bi-card-checklist" aria-hidden="true"></i></span>
            <span class="nav-text">Participations</span>
        </a>

        <!-- 6. Résultats -->
        <a class="nav-link <?= ($currentPage === 'resultats_admin') ? 'active' : '' ?>" href="index.php?page=resultats_admin">
            <span class="nav-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span>
            <span class="nav-text">Résultats</span>
        </a>

        <!-- Section Paramètres & Administration -->
        <hr class="my-2 mx-1 opacity-25">

        <!-- Paramètres -> Conditions d'éligibilité -->
        <a class="nav-link <?= ($currentPage === 'settings_eligibilite') ? 'active' : '' ?>" href="index.php?page=settings_eligibilite">
            <span class="nav-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
            <span class="nav-text">Conditions d'éligibilité</span>
        </a>

        <!-- Paramètres généraux -->
        <!-- <a class="nav-link <?= ($currentPage === 'settings') ? 'active' : '' ?>" href="index.php?page=settings">
            <span class="nav-icon"><i class="bi bi-sliders" aria-hidden="true"></i></span>
            <span class="nav-text">Paramètres</span>
        </a> -->

        <?php if ($isSuperAdmin): ?>
            <!-- Administrateurs (Super Admin uniquement) -->
            <a class="nav-link <?= ($currentPage === 'admins') ? 'active' : '' ?>" href="index.php?page=admins">
                <span class="nav-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
                <span class="nav-text">Administrateurs</span>
            </a>
        <?php endif; ?>

        <!-- Séparateur -->
        <hr class="my-2 mx-1 opacity-25">

        <!-- Accès portail public -->
        <a class="nav-link" href="index.php?page=home" target="_blank">
            <span class="nav-icon"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></span>
            <span class="nav-text">Portail de vote</span>
        </a>

        <!-- Déconnexion -->
        <a class="nav-link text-danger" href="index.php?page=logout">
            <span class="nav-icon"><i class="bi bi-box-arrow-right text-danger" aria-hidden="true"></i></span>
            <span class="nav-text text-danger">Déconnexion</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <span class="status-dot"></span>
        <span class="sidebar-footer-text">Scrutin SAS sécurisé</span>
    </div>
</aside>