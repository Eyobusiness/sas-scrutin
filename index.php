<?php

/**
 * SAS Élection - Routeur Principal
 * Groupe : « Tous unis au service du Christ »
 * Architecture MVC légère sans framework externe
 */

declare(strict_types=1);

// Démarrage sécurisé de la session
require_once __DIR__ . '/app/Helpers/SessionHelper.php';
require_once __DIR__ . '/app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/app/Helpers/AuthHelper.php';

SessionHelper::start();

// Chargement des contrôleurs
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/DashboardController.php';
require_once __DIR__ . '/app/Controllers/MembreController.php';
require_once __DIR__ . '/app/Controllers/ElectionController.php';
require_once __DIR__ . '/app/Controllers/CandidatController.php';
require_once __DIR__ . '/app/Controllers/VoteAdminController.php';
require_once __DIR__ . '/app/Controllers/AdminController.php';
require_once __DIR__ . '/app/Controllers/PublicController.php';

// Détermination de la page demandée
$page = $_GET['page'] ?? 'home';

// Table de routage
switch ($page) {
    // --- ESPACE PUBLIC ---
    case 'home':
        (new PublicController())->home();
        break;

    case 'inscription':
        (new PublicController())->inscription();
        break;

    case 'candidater':
        (new PublicController())->candidater();
        break;

    case 'voter':
        (new PublicController())->voter();
        break;

    case 'vote_succes':
        (new PublicController())->voteSucces();
        break;

    case 'resultats':
        (new PublicController())->resultats();
        break;

    // --- AUTHENTIFICATION ---
    case 'login':
        (new AuthController())->showLogin();
        break;

    case 'login_process':
        (new AuthController())->processLogin();
        break;

    case 'logout':
        (new AuthController())->logout();
        break;

    // --- ESPACE ADMINISTRATION ---
    case 'dashboard':
        (new DashboardController())->index();
        break;

    // Membres
    case 'membres':
        (new MembreController())->index();
        break;

    case 'membre_store':
        (new MembreController())->store();
        break;

    case 'membre_update':
        (new MembreController())->update();
        break;

    case 'membre_toggle_eligibilite':
        (new MembreController())->toggleEligibilite();
        break;

    case 'membre_toggle_statut':
        (new MembreController())->toggleStatut();
        break;

    // Élections
    case 'elections':
        (new ElectionController())->index();
        break;

    case 'election_store':
        (new ElectionController())->store();
        break;

    case 'election_update':
        (new ElectionController())->update();
        break;

    case 'election_change_statut':
        (new ElectionController())->changeStatut();
        break;

    case 'election_certify':
        (new ElectionController())->certify();
        break;

    case 'election_cloturer_tour':
        (new ElectionController())->cloturerTour();
        break;

    case 'election_update_tour':
        (new ElectionController())->updateTour();
        break;

    case 'election_electeur_tour':
        (new ElectionController())->modifierElecteurTour();
        break;

    case 'election_decision_bureau':
        (new ElectionController())->enregistrerDecisionBureau();
        break;

    case 'election_details':
        (new ElectionController())->details();
        break;

    // Candidats
    case 'candidats':
        (new CandidatController())->index();
        break;

    case 'candidat_store':
        (new CandidatController())->store();
        break;

    case 'candidat_review':
        (new CandidatController())->review();
        break;

    // Participations & Résultats
    case 'participations':
        (new VoteAdminController())->participations();
        break;

    case 'resultats_admin':
        (new VoteAdminController())->resultats();
        break;

    // --- SUPER ADMINISTRATION ---
    case 'admins':
        (new AdminController())->index();
        break;

    case 'admin_store':
        (new AdminController())->store();
        break;

    case 'admin_update':
        (new AdminController())->update();
        break;

    case 'admin_toggle_statut':
        (new AdminController())->toggleStatut();
        break;

    case 'settings':
    case 'settings_eligibilite':
        (new AdminController())->settings();
        break;

    default:
        // Page 404
        http_response_code(404);
        echo '<!DOCTYPE html>
        <html lang="fr">
        <head>
        <meta charset="UTF-8">
        <title>Page non trouvée</title>
        <link rel="stylesheet" href="assets/css/bootstrap.min.css">
        </head>
        <body class="bg-light py-5 text-center">
        <div class="container" style="max-width: 500px;">
        <h1 class="display-1 text-primary fw-bold">404</h1>
        <h2 class="h4 mb-3">Page non trouvée</h2>
        <p class="text-muted mb-4">La page demandée n\'existe pas ou a été déplacée.</p>
        <a href="index.php?page=home" class="btn btn-primary">Retour à l\'accueil</a>
        </div>
        </body>
        </html>';
        break;
}
