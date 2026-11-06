<?php
/**
 * Contrôleur de gestion des membres
 */

declare(strict_types=1);

require_once __DIR__ . '/../Helpers/AuthHelper.php';
require_once __DIR__ . '/../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Helpers/SecurityHelper.php';
require_once __DIR__ . '/../Models/Membre.php';
require_once __DIR__ . '/../Services/ParametreService.php';
require_once __DIR__ . '/../Services/EligibilityService.php';
require_once __DIR__ . '/../Services/ElectionService.php';

class MembreController
{
    private Membre $membreModel;
    private ParametreService $parametreService;
    private EligibilityService $eligibilityService;
    private ElectionService $electionService;

    public function __construct()
    {
        AuthHelper::requireLogin();
        $this->membreModel = new Membre();
        $this->parametreService = new ParametreService();
        $this->eligibilityService = new EligibilityService(null, $this->parametreService);
        $this->electionService = new ElectionService();
    }

    /**
     * Liste des membres avec recherche, filtres et calcul de l'âge
     */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');
        $statut = trim($_GET['statut'] ?? '');
        $eligibilite = trim($_GET['eligibilite'] ?? '');

        $filters = [];
        if ($search !== '') $filters['search'] = $search;
        if ($statut !== '') $filters['statut'] = $statut;
        if ($eligibilite !== '') $filters['eligibilite'] = $eligibilite;

        $membres = $this->membreModel->getAll($filters);
        foreach ($membres as &$m) {
            $m['age_calcule'] = $this->eligibilityService->calculateAge($m['date_naissance'] ?? null);
        }
        unset($m);

        $stats = $this->membreModel->getStats();
        $electorConditions = $this->parametreService->getElectorConditions();
        $derniereElection = $this->electionService->getDerniereElection();
        $eligLockCheck = $this->electionService->canModifyMemberEligibility($derniereElection);
        $inscriptionsLocked = !$eligLockCheck['allowed'];
        $lockMessage = $eligLockCheck['message'];

        require __DIR__ . '/../../views/membres/index.php';
    }

    /**
     * Enregistrement d'un membre par l'administrateur
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=membres');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        // Verrouillage automatique côté serveur dès l'ouverture du vote ou clôture des inscriptions
        $eligLockCheck = $this->electionService->canModifyMemberEligibility();
        if (!$eligLockCheck['allowed']) {
            SessionHelper::setFlash('danger', ElectionService::MSG_INSCRIPTIONS_CLOTUREES);
            SecurityHelper::redirect('index.php?page=membres');
        }

        $nom = trim($_POST['nom'] ?? '');
        $prenoms = trim($_POST['prenoms'] ?? '');
        $telephone = SecurityHelper::cleanPhone($_POST['telephone'] ?? '');
        $dateNaissanceRaw = trim($_POST['date_naissance'] ?? '');
        $ancienneteRaw = $_POST['anciennete'] ?? null;
        $fonctionPro = trim($_POST['fonction_pro'] ?? '');
        $responsabiliteSas = trim($_POST['responsabilite_sas'] ?? '');
        $statut = in_array($_POST['statut'] ?? '', ['actif', 'inactif'], true) ? $_POST['statut'] : 'actif';
        $modeEligibilite = $_POST['eligibilite_a_voter'] ?? 'auto';
        $motifManuel = trim($_POST['motif_non_eligibilite'] ?? '');

        if ($nom === '' || $prenoms === '' || $telephone === '') {
            SessionHelper::setFlash('warning', 'Le nom, les prénoms et le numéro de téléphone sont obligatoires.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        if (!SecurityHelper::isValidPhone($telephone)) {
            SessionHelper::setFlash('warning', 'Le numéro de téléphone saisi est invalide.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        $dateNaissance = null;
        if ($dateNaissanceRaw !== '') {
            $birthValidation = $this->eligibilityService->validateBirthDate($dateNaissanceRaw);
            if (!$birthValidation['valid']) {
                SessionHelper::setFlash('warning', $birthValidation['error']);
                SecurityHelper::redirect('index.php?page=membres');
            }
            $dateNaissance = $birthValidation['date'];
        }

        $anciennete = null;
        if ($ancienneteRaw !== null && trim((string)$ancienneteRaw) !== '') {
            $ancValidation = $this->eligibilityService->validateAnciennete($ancienneteRaw);
            if (!$ancValidation['valid']) {
                SessionHelper::setFlash('warning', $ancValidation['error']);
                SecurityHelper::redirect('index.php?page=membres');
            }
            $anciennete = $ancValidation['anciennete'];
        }

        // Vérification de l'unicité du téléphone
        if ($this->membreModel->findByTelephone($telephone)) {
            SessionHelper::setFlash('danger', 'Ce numéro de téléphone est déjà attribué à un autre membre.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        // Détermination de l'éligibilité (automatique selon les paramètres ou forcée par l'admin)
        if ($modeEligibilite === 'auto') {
            $eval = $this->eligibilityService->evaluateMemberEligibility($dateNaissance, $anciennete);
            $eligibilite = $eval['eligibilite_a_voter'];
            $motif = $eval['motif_db'];
        } else {
            $eligibilite = ($modeEligibilite === 'oui') ? 'oui' : 'non';
            $motif = ($eligibilite === 'non') ? ($motifManuel !== '' ? $motifManuel : null) : null;
        }

        $admin = AuthHelper::user();
        $this->membreModel->create([
            'nom'                   => $nom,
            'prenoms'               => $prenoms,
            'telephone'             => $telephone,
            'date_naissance'        => $dateNaissance,
            'anciennete'            => $anciennete,
            'fonction_pro'          => $fonctionPro,
            'responsabilite_sas'    => $responsabiliteSas,
            'statut'                => $statut,
            'eligibilite_a_voter'   => $eligibilite,
            'motif_non_eligibilite' => $motif,
            'valide_par'            => ($eligibilite === 'oui') ? $admin['id'] : null
        ]);

        SessionHelper::setFlash('success', "Le membre {$prenoms} {$nom} a été enregistré avec succès.");
        SecurityHelper::redirect('index.php?page=membres');
    }

    /**
     * Modification d'un membre
     */
    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=membres');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        $id = (int)($_POST['id'] ?? 0);
        $membre = $this->membreModel->findById($id);
        if (!$membre) {
            SessionHelper::setFlash('danger', 'Membre introuvable.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        $nom = trim($_POST['nom'] ?? '');
        $prenoms = trim($_POST['prenoms'] ?? '');
        $telephone = SecurityHelper::cleanPhone($_POST['telephone'] ?? '');
        $dateNaissanceRaw = trim($_POST['date_naissance'] ?? '');
        $ancienneteRaw = $_POST['anciennete'] ?? null;
        $fonctionPro = trim($_POST['fonction_pro'] ?? '');
        $responsabiliteSas = trim($_POST['responsabilite_sas'] ?? '');
        $statut = in_array($_POST['statut'] ?? '', ['actif', 'inactif'], true) ? $_POST['statut'] : 'actif';

        if ($nom === '' || $prenoms === '' || $telephone === '') {
            SessionHelper::setFlash('warning', 'Le nom, les prénoms et le numéro de téléphone sont requis.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        if (!SecurityHelper::isValidPhone($telephone)) {
            SessionHelper::setFlash('warning', 'Le numéro de téléphone saisi est invalide.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        $dateNaissance = null;
        if ($dateNaissanceRaw !== '') {
            $birthValidation = $this->eligibilityService->validateBirthDate($dateNaissanceRaw);
            if (!$birthValidation['valid']) {
                SessionHelper::setFlash('warning', $birthValidation['error']);
                SecurityHelper::redirect('index.php?page=membres');
            }
            $dateNaissance = $birthValidation['date'];
        }

        $anciennete = null;
        if ($ancienneteRaw !== null && trim((string)$ancienneteRaw) !== '') {
            $ancValidation = $this->eligibilityService->validateAnciennete($ancienneteRaw);
            if (!$ancValidation['valid']) {
                SessionHelper::setFlash('warning', $ancValidation['error']);
                SecurityHelper::redirect('index.php?page=membres');
            }
            $anciennete = $ancValidation['anciennete'];
        }

        // Vérification de collision de téléphone
        $existing = $this->membreModel->findByTelephone($telephone);
        if ($existing && (int)$existing['id'] !== $id) {
            SessionHelper::setFlash('danger', 'Ce numéro de téléphone est déjà utilisé par un autre membre.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        $this->membreModel->update($id, [
            'nom'                => $nom,
            'prenoms'            => $prenoms,
            'telephone'          => $telephone,
            'date_naissance'     => $dateNaissance,
            'anciennete'         => $anciennete,
            'fonction_pro'       => $fonctionPro,
            'responsabilite_sas' => $responsabiliteSas,
            'statut'             => $statut
        ]);

        // Optionnel : réévaluer automatiquement l'éligibilité si demandé par l'administrateur (et non verrouillé)
        if (!empty($_POST['recalculer_eligibilite'])) {
            $eligLockCheck = $this->electionService->canModifyMemberEligibility();
            if (!$eligLockCheck['allowed']) {
                SessionHelper::setFlash('warning', ElectionService::MSG_INSCRIPTIONS_CLOTUREES . ' L\'éligibilité du membre n\'a pas pu être modifiée.');
                SecurityHelper::redirect('index.php?page=membres');
            }
            $eval = $this->eligibilityService->evaluateMemberEligibility($dateNaissance, $anciennete);
            $admin = AuthHelper::user();
            $this->membreModel->updateEligibilite(
                $id,
                $eval['eligibilite_a_voter'],
                $eval['motif_db'],
                $eval['eligible'] ? $admin['id'] : null
            );
        }

        SessionHelper::setFlash('success', "Les informations du membre ont été mises à jour.");
        SecurityHelper::redirect('index.php?page=membres');
    }

    /**
     * Validation ou invalidation manuelle de l'éligibilité au vote par le bureau
     */
    public function toggleEligibilite(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=membres');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        // Verrouillage automatique côté serveur dès le début du vote ou la fermeture des inscriptions
        $eligLockCheck = $this->electionService->canModifyMemberEligibility();
        if (!$eligLockCheck['allowed']) {
            SessionHelper::setFlash('danger', ElectionService::MSG_INSCRIPTIONS_CLOTUREES);
            SecurityHelper::redirect('index.php?page=membres');
        }

        $id = (int)($_POST['id'] ?? 0);
        $membre = $this->membreModel->findById($id);
        if (!$membre) {
            SessionHelper::setFlash('danger', 'Membre introuvable.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        $eligibilite = ($_POST['eligibilite'] ?? '') === 'oui' ? 'oui' : 'non';
        $motif = ($eligibilite === 'non') ? trim($_POST['motif_non_eligibilite'] ?? '') : null;
        $admin = AuthHelper::user();

        $this->membreModel->updateEligibilite($id, $eligibilite, $motif, $admin['id']);

        $message = ($eligibilite === 'oui') 
            ? "L'éligibilité au vote de {$membre['prenoms']} {$membre['nom']} a été validée avec succès."
            : "L'éligibilité au vote de {$membre['prenoms']} {$membre['nom']} a été révoquée.";

        SessionHelper::setFlash('success', $message);
        SecurityHelper::redirect('index.php?page=membres');
    }

    /**
     * Bascule du statut actif / inactif (désactivation privilégiée à la suppression)
     */
    public function toggleStatut(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=membres');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        $id = (int)($_POST['id'] ?? 0);
        $membre = $this->membreModel->findById($id);
        if (!$membre) {
            SessionHelper::setFlash('danger', 'Membre introuvable.');
            SecurityHelper::redirect('index.php?page=membres');
        }

        $nouveauStatut = ($membre['statut'] === 'actif') ? 'inactif' : 'actif';
        $this->membreModel->toggleStatut($id, $nouveauStatut);

        SessionHelper::setFlash('info', "Le compte du membre a été marqué comme {$nouveauStatut}.");
        SecurityHelper::redirect('index.php?page=membres');
    }
}
