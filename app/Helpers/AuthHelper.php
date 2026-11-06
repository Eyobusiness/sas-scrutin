<?php
/**
 * AuthHelper - Authentification et contrôle des permissions des administrateurs
 */

declare(strict_types=1);

require_once __DIR__ . '/SessionHelper.php';
require_once __DIR__ . '/SecurityHelper.php';

class AuthHelper
{
    /**
     * Vérifie si un administrateur est connecté
     */
    public static function isLoggedIn(): bool
    {
        SessionHelper::start();
        return !empty($_SESSION['admin_user']) && isset($_SESSION['admin_user']['id']);
    }

    /**
     * Récupère l'administrateur connecté
     */
    public static function user(): ?array
    {
        SessionHelper::start();
        return $_SESSION['admin_user'] ?? null;
    }

    /**
     * Vérifie si l'administrateur connecté a le rôle super_admin
     */
    public static function isSuperAdmin(): bool
    {
        $user = self::user();
        return $user !== null && ($user['role_code'] ?? '') === 'super_admin';
    }

    /**
     * Vérifie si l'administrateur est connecté et actif
     */
    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            SessionHelper::setFlash('warning', 'Veuillez vous connecter pour accéder à cet espace.');
            SecurityHelper::redirect('index.php?page=login');
        }

        // Vérification de l'activité du compte
        $user = self::user();
        if (($user['statut'] ?? '') !== 'actif') {
            self::logout();
            SessionHelper::setFlash('danger', 'Votre compte administrateur a été désactivé. Contactez le super administrateur.');
            SecurityHelper::redirect('index.php?page=login');
        }
    }

    /**
     * Exige le rôle super_admin
     */
    public static function requireSuperAdmin(): void
    {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            SessionHelper::setFlash('danger', 'Accès refusé. Cette action nécessite les privilèges de Super Administrateur.');
            SecurityHelper::redirect('index.php?page=dashboard');
        }
    }

    /**
     * Connecte un administrateur et régénère la session
     */
    public static function login(array $adminData): void
    {
        SessionHelper::regenerate();
        $_SESSION['admin_user'] = [
            'id'        => (int)$adminData['id'],
            'nom'       => $adminData['nom'],
            'prenoms'   => $adminData['prenoms'],
            'telephone' => $adminData['telephone'],
            'email'     => $adminData['email'] ?? '',
            'role_id'   => (int)$adminData['role_id'],
            'role_code' => $adminData['role_code'] ?? 'admin',
            'role_nom'  => $adminData['role_libelle'] ?? 'Administrateur',
            'statut'    => $adminData['statut']
        ];
    }

    /**
     * Déconnecte l'administrateur
     */
    public static function logout(): void
    {
        SessionHelper::start();
        unset($_SESSION['admin_user']);
        session_destroy();
    }
}
