<?php
/**
 * Contrôleur de gestion des candidatures
 */

declare(strict_types=1);

require_once __DIR__ . '/../Helpers/AuthHelper.php';
require_once __DIR__ . '/../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Helpers/SecurityHelper.php';
require_once __DIR__ . '/../Models/Candidat.php';
require_once __DIR__ . '/../Models/Election.php';
require_once __DIR__ . '/../Services/CandidatureService.php';
require_once __DIR__ . '/../Services/ElectionService.php';

class CandidatController
{
    private Candidat $candidatModel;
    private Election $electionModel;
    private CandidatureService $candidatureService;
    private ElectionService $electionService;

    public function __construct()
    {
        AuthHelper::requireLogin();
        $this->candidatModel = new Candidat();
        $this->electionModel = new Election();
        $this->candidatureService = new CandidatureService();
        $this->electionService = $this->candidatureService->getElectionService();
    }

    /**
     * Liste des candidatures avec calcul de l'âge, conformité aux conditions configurées et état de verrouillage
     */
    public function index(): void
    {
        $electionId = !empty($_GET['election_id']) ? SecurityHelper::decryptId($_GET['election_id']) : null;
        $statut = trim($_GET['statut'] ?? '');
        $search = trim($_GET['search'] ?? '');

        $filters = [];
        if ($electionId) $filters['election_id'] = $electionId;
        if ($statut !== '') $filters['statut'] = $statut;
        if ($search !== '') $filters['search'] = $search;

        $rawCandidats = $this->candidatModel->getAll($filters);
        $candidats = [];
        foreach ($rawCandidats as $c) {
            $candidats[] = $this->candidatureService->enrichCandidat($c);
        }

        $elections = $this->electionService->getAllElectionsEnriched();
        $derniereElection = $this->electionService->getDerniereElection();
        $candidatureLockCheck = $this->electionService->canSubmitCandidature($derniereElection);
        $candidaturesLocked = !$candidatureLockCheck['allowed'];
        $candidatureLockMessage = $candidatureLockCheck['message'];

        $stats = $this->candidatModel->getStats($electionId);
        $candidatureConditions = $this->candidatureService->getParametreService()->getCandidatureConditions();

        require __DIR__ . '/../../views/candidats/index.php';
    }

    /**
     * Enregistrement d'un candidat par l'administrateur (soumis au verrouillage de la période de candidature)
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=candidats');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=candidats');
        }

        $electionId = (int)($_POST['election_id'] ?? 0);
        $nom = trim($_POST['nom'] ?? '');
        $prenoms = trim($_POST['prenoms'] ?? '');
        $telephone = SecurityHelper::cleanPhone($_POST['telephone'] ?? '');
        $dateNaissanceRaw = trim($_POST['date_naissance'] ?? '');
        $ancienneteRaw = $_POST['anciennete'] ?? null;
        $droitPaye = $this->candidatureService->normalizeDroitPaye($_POST['droit_candidature_paye'] ?? 0);
        $motifCandidature = trim($_POST['motif_candidature'] ?? '');
        $statut = in_array($_POST['statut'] ?? '', ['en_attente', 'validee', 'rejetee', 'retiree'], true) ? $_POST['statut'] : 'en_attente';

        $targetElection = $electionId > 0
            ? $this->electionService->getElectionById($electionId)
            : $this->electionService->getDerniereElection();

        // Vérifier que l'élection existe et que sa période de candidature est ouverte
        $periodCheck = $this->electionService->canSubmitCandidature($targetElection);
        if (!$periodCheck['allowed']) {
            SessionHelper::setFlash('danger', $periodCheck['message']);
            SecurityHelper::redirect('index.php?page=candidats');
        }

        $electionId = (int)$targetElection['id'];

        if ($nom === '' || $prenoms === '' || $motifCandidature === '') {
            SessionHelper::setFlash('warning', 'Veuillez saisir le nom, le prénom et la profession de foi.');
            SecurityHelper::redirect('index.php?page=candidats');
        }

        // Vérification anti-doublon sur cette élection
        if ($this->candidatModel->hasExistingCandidature($electionId, $nom, $prenoms, $telephone)) {
            SessionHelper::setFlash('warning', 'Ce candidat possède déjà un dossier de candidature pour cette élection.');
            SecurityHelper::redirect('index.php?page=candidats');
        }

        $eligService = $this->candidatureService->getEligibilityService();

        $dateNaissance = null;
        if ($dateNaissanceRaw !== '') {
            $birthValidation = $eligService->validateBirthDate($dateNaissanceRaw);
            if (!$birthValidation['valid']) {
                SessionHelper::setFlash('warning', $birthValidation['error']);
                SecurityHelper::redirect('index.php?page=candidats');
            }
            $dateNaissance = $birthValidation['date'];
        }

        $anciennete = null;
        if ($ancienneteRaw !== null && trim((string)$ancienneteRaw) !== '') {
            $ancValidation = $eligService->validateAnciennete($ancienneteRaw);
            if (!$ancValidation['valid']) {
                SessionHelper::setFlash('warning', $ancValidation['error']);
                SecurityHelper::redirect('index.php?page=candidats');
            }
            $anciennete = $ancValidation['anciennete'];
        }

        // Gestion de la photo facultative
        $photoName = null;
        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $destDir = __DIR__ . '/../../uploads/candidats';
            [$success, $result] = SecurityHelper::uploadPhoto($_FILES['photo'], $destDir);
            if (!$success) {
                SessionHelper::setFlash('danger', $result);
                SecurityHelper::redirect('index.php?page=candidats');
            }
            $photoName = $result;
        }

        $this->candidatModel->create([
            'election_id'            => $electionId,
            'nom'                    => $nom,
            'prenoms'                => $prenoms,
            'telephone'              => $telephone,
            'date_naissance'         => $dateNaissance,
            'anciennete'             => $anciennete,
            'droit_candidature_paye' => $droitPaye ? 1 : 0,
            'photo'                  => $photoName,
            'motif_candidature'      => $motifCandidature,
            'statut'                 => $statut
        ]);

        SessionHelper::setFlash('success', "La candidature de {$prenoms} {$nom} a été ajoutée avec succès.");
        SecurityHelper::redirect('index.php?page=candidats');
    }

    /**
     * Traitement de la candidature par le bureau (Validation / Rejet / Retrait + suivi du paiement)
     * Autorisé tant que le scrutin n'est pas clôturé ni en cours de vote.
     */
    public function review(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=candidats');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=candidats');
        }

        $id = (int)($_POST['id'] ?? 0);
        $statut = $_POST['statut'] ?? '';
        $motifRejet = trim($_POST['motif_rejet'] ?? '');
        $droitPaye = isset($_POST['droit_candidature_paye'])
            ? ($this->candidatureService->normalizeDroitPaye($_POST['droit_candidature_paye']) ? 1 : 0)
            : null;

        $candidat = $this->candidatModel->findById($id);
        if (!$candidat) {
            SessionHelper::setFlash('danger', 'Candidature introuvable.');
            SecurityHelper::redirect('index.php?page=candidats');
        }

        $election = $this->electionService->getElectionById((int)$candidat['election_id']);
        if ($election && (!empty($election['phase_info']['is_closed']) || !empty($election['phase_info']['vote_open']))) {
            SessionHelper::setFlash('danger', 'Le bulletin de vote est verrouillé : impossible de modifier le statut d\'un candidat pendant ou après le scrutin.');
            SecurityHelper::redirect('index.php?page=candidats');
        }

        if (!in_array($statut, ['en_attente', 'validee', 'rejetee', 'retiree'], true)) {
            SessionHelper::setFlash('danger', 'Statut de traitement non reconnu.');
            SecurityHelper::redirect('index.php?page=candidats');
        }

        if ($statut === 'rejetee' && $motifRejet === '') {
            SessionHelper::setFlash('warning', 'Veuillez indiquer un motif de rejet pour rejeter la candidature.');
            SecurityHelper::redirect('index.php?page=candidats');
        }

        $admin = AuthHelper::user();
        $this->candidatModel->updateStatut($id, $statut, $motifRejet, $admin['id'], $droitPaye);

        $messages = [
            'validee'    => "La candidature de {$candidat['prenoms']} {$candidat['nom']} a été validée par le bureau. Elle apparaîtra sur le bulletin de vote.",
            'rejetee'    => "La candidature de {$candidat['prenoms']} {$candidat['nom']} a été rejetée.",
            'retiree'    => "La candidature de {$candidat['prenoms']} {$candidat['nom']} a été marquée comme retirée.",
            'en_attente' => "La candidature de {$candidat['prenoms']} {$candidat['nom']} a été maintenue en attente."
        ];

        SessionHelper::setFlash('success', $messages[$statut]);
        SecurityHelper::redirect('index.php?page=candidats');
    }
}
