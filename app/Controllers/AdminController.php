<?php
/**
 * Contrôleur de gestion des administrateurs et des paramètres
 */

declare(strict_types=1);

require_once __DIR__ . '/../Helpers/AuthHelper.php';
require_once __DIR__ . '/../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Helpers/SecurityHelper.php';
require_once __DIR__ . '/../Models/Administrateur.php';
require_once __DIR__ . '/../Models/Parametre.php';
require_once __DIR__ . '/../Services/ParametreService.php';

class AdminController
{
    private Administrateur $adminModel;
    private Parametre $parametreModel;
    private ParametreService $parametreService;

    public function __construct()
    {
        AuthHelper::requireLogin();
        $this->adminModel = new Administrateur();
        $this->parametreModel = new Parametre();
        $this->parametreService = new ParametreService();
    }

    /**
     * Liste des comptes administrateurs (Réservé au Super Administrateur)
     */
    public function index(): void
    {
        AuthHelper::requireSuperAdmin();
        $admins = $this->adminModel->getAll();
        $roles = $this->adminModel->getRoles();
        require __DIR__ . '/../../views/admins/index.php';
    }

    /**
     * Création d'un nouvel administrateur (Réservé au Super Administrateur)
     */
    public function store(): void
    {
        AuthHelper::requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=admins');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        $nom = trim($_POST['nom'] ?? '');
        $prenoms = trim($_POST['prenoms'] ?? '');
        $telephone = SecurityHelper::cleanPhone($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $roleId = (int)($_POST['role_id'] ?? 2);
        $statut = in_array($_POST['statut'] ?? '', ['actif', 'inactif'], true) ? $_POST['statut'] : 'actif';

        if (empty($nom) || empty($prenoms) || empty($telephone) || empty($motDePasse)) {
            SessionHelper::setFlash('warning', 'Le nom, le prénom, le téléphone et le mot de passe sont obligatoires.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        if (strlen($motDePasse) < 6) {
            SessionHelper::setFlash('warning', 'Le mot de passe doit comporter au moins 6 caractères.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        if ($this->adminModel->findByTelephone($telephone)) {
            SessionHelper::setFlash('danger', 'Un administrateur avec ce numéro de téléphone existe déjà.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        $this->adminModel->create([
            'role_id'      => $roleId,
            'nom'          => $nom,
            'prenoms'      => $prenoms,
            'telephone'    => $telephone,
            'email'        => !empty($email) ? $email : null,
            'mot_de_passe' => $motDePasse,
            'statut'       => $statut
        ]);

        SessionHelper::setFlash('success', "Le compte administrateur pour {$prenoms} {$nom} a été créé avec succès.");
        SecurityHelper::redirect('index.php?page=admins');
    }

    /**
     * Modification d'un administrateur (Réservé au Super Administrateur)
     */
    public function update(): void
    {
        AuthHelper::requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=admins');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        $id = (int)($_POST['id'] ?? 0);
        $admin = $this->adminModel->findById($id);
        if (!$admin) {
            SessionHelper::setFlash('danger', 'Administrateur introuvable.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        $nom = trim($_POST['nom'] ?? '');
        $prenoms = trim($_POST['prenoms'] ?? '');
        $telephone = SecurityHelper::cleanPhone($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? $admin['role_id']);
        $statut = in_array($_POST['statut'] ?? '', ['actif', 'inactif'], true) ? $_POST['statut'] : $admin['statut'];

        $current = AuthHelper::user();
        if ($current['id'] === $id && $statut === 'inactif') {
            SessionHelper::setFlash('danger', 'Vous ne pouvez pas désactiver votre propre compte Super Administrateur.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        $existing = $this->adminModel->findByTelephone($telephone);
        if ($existing && (int)$existing['id'] !== $id) {
            SessionHelper::setFlash('danger', 'Ce numéro de téléphone est déjà utilisé par un autre administrateur.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        $this->adminModel->update($id, [
            'role_id'   => $roleId,
            'nom'       => $nom,
            'prenoms'   => $prenoms,
            'telephone' => $telephone,
            'email'     => !empty($email) ? $email : null,
            'statut'    => $statut
        ]);

        if (!empty($_POST['mot_de_passe'])) {
            if (strlen($_POST['mot_de_passe']) >= 6) {
                $this->adminModel->updatePassword($id, $_POST['mot_de_passe']);
            } else {
                SessionHelper::setFlash('warning', 'Le mot de passe n\'a pas été modifié (au moins 6 caractères requis).');
            }
        }

        SessionHelper::setFlash('success', "Le compte administrateur a été mis à jour.");
        SecurityHelper::redirect('index.php?page=admins');
    }

    /**
     * Bascule du statut actif / inactif (Réservé au Super Administrateur)
     */
    public function toggleStatut(): void
    {
        AuthHelper::requireSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=admins');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        $id = (int)($_POST['id'] ?? 0);
        $current = AuthHelper::user();

        if ($current['id'] === $id) {
            SessionHelper::setFlash('danger', 'Impossible de désactiver votre propre compte.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        $admin = $this->adminModel->findById($id);
        if (!$admin) {
            SessionHelper::setFlash('danger', 'Administrateur introuvable.');
            SecurityHelper::redirect('index.php?page=admins');
        }

        $nouveauStatut = ($admin['statut'] === 'actif') ? 'inactif' : 'actif';
        $this->adminModel->toggleStatut($id, $nouveauStatut);

        SessionHelper::setFlash('info', "Le compte de {$admin['prenoms']} {$admin['nom']} est désormais {$nouveauStatut}.");
        SecurityHelper::redirect('index.php?page=admins');
    }

    /**
     * Gestion des paramètres (Généraux & Conditions d'éligibilité)
     */
    public function settings(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
                SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
                SecurityHelper::redirect('index.php?page=settings');
            }

            $formType = $_POST['form_type'] ?? 'general';
            $redirectPage = ($_POST['redirect_page'] ?? 'settings') === 'settings_eligibilite' ? 'settings_eligibilite' : 'settings';

            if ($formType === 'eligibilite') {
                [$success, $message] = $this->parametreService->saveEligibilitySettings($_POST);
                SessionHelper::setFlash($success ? 'success' : 'danger', $message);
                SecurityHelper::redirect('index.php?page=' . $redirectPage);
            } else {
                [$success, $message] = $this->parametreService->saveGeneralSettings($_POST);
                SessionHelper::setFlash($success ? 'success' : 'danger', $message);
                SecurityHelper::redirect('index.php?page=' . $redirectPage);
            }
        }

        $settings = $this->parametreService->getAll();
        $electorConditions = $this->parametreService->getElectorConditions();
        $candidatureConditions = $this->parametreService->getCandidatureConditions();
        $activeTab = ($_GET['page'] ?? 'settings') === 'settings_eligibilite' || ($_GET['section'] ?? '') === 'eligibilite'
            ? 'eligibilite'
            : 'general';

        require __DIR__ . '/../../views/admins/settings.php';
    }
}
