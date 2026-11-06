<?php
/**
 * Contrôleur d'authentification
 */

declare(strict_types=1);

require_once __DIR__ . '/../Models/Administrateur.php';
require_once __DIR__ . '/../Helpers/AuthHelper.php';
require_once __DIR__ . '/../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Helpers/SecurityHelper.php';

class AuthController
{
    private Administrateur $adminModel;

    public function __construct()
    {
        $this->adminModel = new Administrateur();
    }

    /**
     * Affiche la page de connexion
     */
    public function showLogin(): void
    {
        if (AuthHelper::isLoggedIn()) {
            SecurityHelper::redirect('index.php?page=dashboard');
        }

        require __DIR__ . '/../../views/auth/login.php';
    }

    /**
     * Traite la soumission du formulaire de connexion
     */
    public function processLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=login');
        }

        $csrf = $_POST['csrf_token'] ?? '';
        if (!SessionHelper::verifyCsrf($csrf)) {
            SessionHelper::setFlash('danger', 'Jeton de sécurité CSRF expiré ou invalide. Veuillez réessayer.');
            SecurityHelper::redirect('index.php?page=login');
        }

        $telephone = SecurityHelper::cleanPhone($_POST['telephone'] ?? '');
        $password = $_POST['mot_de_passe'] ?? '';

        if (empty($telephone) || empty($password)) {
            SessionHelper::setFlash('warning', 'Veuillez renseigner votre numéro de téléphone et votre mot de passe.');
            SecurityHelper::redirect('index.php?page=login');
        }

        $admin = $this->adminModel->findByTelephone($telephone);

        if (!$admin || !password_verify($password, $admin['mot_de_passe'])) {
            SessionHelper::setFlash('danger', 'Identifiants incorrects. Veuillez vérifier votre numéro et mot de passe.');
            SecurityHelper::redirect('index.php?page=login');
        }

        if ($admin['statut'] !== 'actif') {
            SessionHelper::setFlash('danger', 'Votre compte est inactif. Veuillez contacter le super administrateur.');
            SecurityHelper::redirect('index.php?page=login');
        }

        // Connexion réussie
        AuthHelper::login($admin);
        SessionHelper::setFlash('success', 'Bienvenue dans l\'espace d\'administration SAS Élection, ' . $admin['prenoms'] . ' !');
        SecurityHelper::redirect('index.php?page=dashboard');
    }

    /**
     * Déconnecte l'administrateur
     */
    public function logout(): void
    {
        AuthHelper::logout();
        SessionHelper::start();
        SessionHelper::setFlash('info', 'Vous avez été déconnecté avec succès.');
        SecurityHelper::redirect('index.php?page=login');
    }
}
