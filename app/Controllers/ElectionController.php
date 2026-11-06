<?php
/**
 * Contrôleur de gestion des élections, des tours de scrutin, de la qualification,
 * de la gestion exceptionnelle des électeurs et des décisions du bureau
 */

declare(strict_types=1);

require_once __DIR__ . '/../Helpers/AuthHelper.php';
require_once __DIR__ . '/../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Helpers/SecurityHelper.php';
require_once __DIR__ . '/../Models/Election.php';
require_once __DIR__ . '/../Models/Candidat.php';
require_once __DIR__ . '/../Models/Participation.php';
require_once __DIR__ . '/../Models/Membre.php';
require_once __DIR__ . '/../Services/ElectionService.php';
require_once __DIR__ . '/../Services/CandidatureService.php';
require_once __DIR__ . '/../Services/TourElectionService.php';
require_once __DIR__ . '/../Services/QualificationService.php';
require_once __DIR__ . '/../Services/ElecteurTourService.php';

class ElectionController
{
    private Election $electionModel;
    private Candidat $candidatModel;
    private Participation $participationModel;
    private Membre $membreModel;
    private ElectionService $electionService;
    private CandidatureService $candidatureService;
    private TourElectionService $tourService;
    private QualificationService $qualificationService;
    private ElecteurTourService $electeurTourService;

    public function __construct()
    {
        AuthHelper::requireLogin();
        $this->electionModel = new Election();
        $this->candidatModel = new Candidat();
        $this->participationModel = new Participation();
        $this->membreModel = new Membre();
        $this->electionService = new ElectionService();
        $this->candidatureService = new CandidatureService();
        $this->tourService = new TourElectionService();
        $this->qualificationService = new QualificationService();
        $this->electeurTourService = new ElecteurTourService();
    }

    /**
     * Liste des élections (actuelle et passées) avec leurs phases, tours et verrouillages
     */
    public function index(): void
    {
        $elections = $this->electionService->getAllElectionsEnriched();
        $derniereElection = $this->electionService->getDerniereElection();
        $stats = $this->electionModel->getStats();
        require __DIR__ . '/../../views/elections/index.php';
    }

    /**
     * Enregistrement d'une nouvelle élection avec validation de cohérence des dates et nombre_max_tours
     */
    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $statut = in_array($_POST['statut'] ?? '', ['preparation', 'ouverte', 'cloturee'], true) ? $_POST['statut'] : 'preparation';
        $nombreMaxTours = max(1, min(10, (int)($_POST['nombre_max_tours'] ?? 3)));

        if ($titre === '') {
            SessionHelper::setFlash('warning', 'Le titre de l\'élection est obligatoire.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $dateValidation = $this->electionService->validateElectionDates($_POST);
        if (!$dateValidation['valid']) {
            SessionHelper::setFlash('warning', implode(' ', $dateValidation['errors']));
            SecurityHelper::redirect('index.php?page=elections');
        }

        $admin = AuthHelper::user();
        $payload = array_merge($dateValidation['dates'], [
            'titre'            => $titre,
            'description'      => $description,
            'statut'           => $statut,
            'nombre_max_tours' => $nombreMaxTours,
            'cree_par'         => $admin['id']
        ]);

        $electionId = $this->electionModel->create($payload);
        $tour1 = $this->tourService->ensureInitialTour($electionId);
        if ($tour1) {
            $this->electeurTourService->syncElecteursForTour($electionId, (int)$tour1['id']);
        }

        SessionHelper::setFlash('success', "L'élection « {$titre} » a été créée avec succès (max {$nombreMaxTours} tour(s)) et définie comme scrutin de référence.");
        SecurityHelper::redirect('index.php?page=elections');
    }

    /**
     * Modification d'une élection et de ses périodes électorales
     */
    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $id = (int)($_POST['id'] ?? 0);
        $election = $this->electionModel->findById($id);
        if (!$election) {
            SessionHelper::setFlash('danger', 'Élection introuvable.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $statut = in_array($_POST['statut'] ?? '', ['preparation', 'ouverte', 'cloturee'], true) ? $_POST['statut'] : $election['statut'];
        $nombreMaxTours = isset($_POST['nombre_max_tours'])
            ? max(1, min(10, (int)$_POST['nombre_max_tours']))
            : (int)($election['nombre_max_tours'] ?? 3);

        if ($titre === '') {
            SessionHelper::setFlash('warning', 'Le titre de l\'élection est obligatoire.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $dateValidation = $this->electionService->validateElectionDates($_POST);
        if (!$dateValidation['valid']) {
            SessionHelper::setFlash('warning', implode(' ', $dateValidation['errors']));
            SecurityHelper::redirect('index.php?page=elections');
        }

        $payload = array_merge($dateValidation['dates'], [
            'titre'                  => $titre,
            'description'            => $description,
            'statut'                 => $statut,
            'nombre_max_tours'       => $nombreMaxTours,
            'date_cloture_effective' => $election['date_cloture_effective'] ?? null
        ]);

        $this->electionModel->update($id, $payload);

        SessionHelper::setFlash('success', "Le calendrier et les paramètres de l'élection « {$titre} » ont été mis à jour.");
        SecurityHelper::redirect('index.php?page=elections');
    }

    /**
     * Changement direct de statut (Ouverture / Clôture avec qualification automatique entre les tours)
     */
    public function changeStatut(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $id = (int)($_POST['id'] ?? 0);
        $statut = $_POST['statut'] ?? '';
        $election = $this->electionModel->findById($id);

        if (!$election) {
            SessionHelper::setFlash('danger', 'Élection introuvable.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (!in_array($statut, ['preparation', 'ouverte', 'cloturee'], true)) {
            SessionHelper::setFlash('danger', 'Statut non reconnu.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        // Si on clôture le scrutin, appliquer les règles de qualification du tour courant (Section 4)
        if ($statut === 'cloturee') {
            $qualifRes = $this->qualificationService->cloturerTourEtQualifier($id);
            if ($qualifRes['success']) {
                $flashType = ($qualifRes['status'] === 'vainqueur_unique') ? 'success' : 'warning';
                SessionHelper::setFlash($flashType, $qualifRes['message']);
            } else {
                $this->electionModel->setStatut($id, 'cloturee');
                SessionHelper::setFlash('info', "L'élection « {$election['titre']} » a été clôturée.");
            }
        } else {
            $this->electionModel->setStatut($id, $statut);
            $labels = [
                'preparation' => 'remise en préparation',
                'ouverte'     => 'officiellement ouverte au scrutin',
            ];
            SessionHelper::setFlash('success', "L'élection « {$election['titre']} » est désormais " . ($labels[$statut] ?? $statut) . ".");
        }

        $redirect = !empty($_POST['redirect_details'])
            ? 'index.php?page=election_details&id=' . SecurityHelper::encryptId($id)
            : 'index.php?page=elections';
        SecurityHelper::redirect($redirect);
    }

    /**
     * Clôture d'un tour spécifique avec calcul automatique des qualifiés ou du vainqueur (Section 4)
     */
    public function cloturerTour(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $electionId = (int)($_POST['election_id'] ?? 0);
        $tourId = !empty($_POST['tour_id']) ? (int)$_POST['tour_id'] : null;
        $nextDebut = $this->electionService->normalizeDateTime($_POST['next_date_debut_vote'] ?? null);
        $nextFin   = $this->electionService->normalizeDateTime($_POST['next_date_fin_vote'] ?? null);

        $res = $this->qualificationService->cloturerTourEtQualifier($electionId, $tourId, $nextDebut, $nextFin);
        $type = $res['success'] ? ($res['status'] === 'vainqueur_unique' ? 'success' : 'warning') : 'danger';
        SessionHelper::setFlash($type, $res['message']);

        SecurityHelper::redirect('index.php?page=election_details&id=' . SecurityHelper::encryptId($electionId));
    }

    /**
     * Programmation / ouverture d'un tour de scrutin (ex: 2e tour préparé)
     */
    public function updateTour(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $electionId = (int)($_POST['election_id'] ?? 0);
        $tourId = (int)($_POST['tour_id'] ?? 0);
        $statut = in_array($_POST['statut'] ?? '', ['preparation', 'ouverte', 'cloturee'], true) ? $_POST['statut'] : 'preparation';
        $dateDebut = $this->electionService->normalizeDateTime($_POST['date_debut_vote'] ?? null);
        $dateFin   = $this->electionService->normalizeDateTime($_POST['date_fin_vote'] ?? null);

        if (!$dateDebut || !$dateFin || $dateFin <= $dateDebut) {
            SessionHelper::setFlash('warning', 'Les dates de début et de fin du tour doivent être valides et la fin doit être postérieure au début.');
            SecurityHelper::redirect('index.php?page=election_details&id=' . SecurityHelper::encryptId($electionId));
        }

        if ($statut === 'cloturee') {
            $res = $this->qualificationService->cloturerTourEtQualifier($electionId, $tourId);
            SessionHelper::setFlash($res['success'] ? 'info' : 'danger', $res['message']);
        } else {
            $this->tourService->updateTour($tourId, $dateDebut, $dateFin, $statut);
            SessionHelper::setFlash('success', 'Les paramètres et horaires du tour ont été mis à jour.');
        }

        SecurityHelper::redirect('index.php?page=election_details&id=' . SecurityHelper::encryptId($electionId));
    }

    /**
     * Gestion exceptionnelle des électeurs d'un tour par le bureau (ajout, retrait, correction)
     * Autorisé uniquement avant l'ouverture du tour, avec motif et traçabilité obligatoires (Section 5)
     */
    public function modifierElecteurTour(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $electionId = (int)($_POST['election_id'] ?? 0);
        $tourId = (int)($_POST['tour_id'] ?? 0);
        $membreId = (int)($_POST['membre_id'] ?? 0);
        $typeModif = trim($_POST['type_modification'] ?? 'correction');
        $autorise = ($_POST['autorise'] ?? '1') === '1';
        $motif = trim($_POST['motif'] ?? '');
        $admin = AuthHelper::user();

        $res = $this->electeurTourService->modifyElecteurTour(
            $electionId,
            $tourId,
            $membreId,
            $typeModif,
            $autorise,
            $motif,
            (int)($admin['id'] ?? 0)
        );

        SessionHelper::setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        SecurityHelper::redirect('index.php?page=election_details&id=' . SecurityHelper::encryptId($electionId) . '&tour_id=' . $tourId . '#section-electeurs-tour');
    }

    /**
     * Enregistrement d'une décision officielle du bureau en cas d'égalité persistante (Section 4)
     */
    public function enregistrerDecisionBureau(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $electionId = (int)($_POST['election_id'] ?? 0);
        $tourId = !empty($_POST['tour_id']) ? (int)$_POST['tour_id'] : null;
        $candidatRetenuId = !empty($_POST['candidat_retenu_id']) ? (int)$_POST['candidat_retenu_id'] : null;
        $decision = trim($_POST['decision'] ?? '');
        $motif = trim($_POST['motif'] ?? '');
        $typeDecision = trim($_POST['type_decision'] ?? 'egalite_persistante');
        $admin = AuthHelper::user();

        $res = $this->qualificationService->enregistrerDecisionBureau(
            $electionId,
            $tourId,
            $decision,
            $motif,
            (int)($admin['id'] ?? 0),
            $candidatRetenuId,
            $typeDecision
        );

        SessionHelper::setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        $redirect = !empty($_POST['redirect_resultats'])
            ? 'index.php?page=resultats_admin&election_id=' . SecurityHelper::encryptId($electionId)
            : 'index.php?page=election_details&id=' . SecurityHelper::encryptId($electionId);
        SecurityHelper::redirect($redirect);
    }

    /**
     * Certification officielle des résultats par le bureau après la clôture du scrutin
     */
    public function certify(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
            SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $id = (int)($_POST['id'] ?? 0);
        $election = $this->electionService->getElectionById($id);

        if (!$election) {
            SessionHelper::setFlash('danger', 'Élection introuvable.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        if (($election['statut'] ?? '') !== 'cloturee') {
            SessionHelper::setFlash('warning', 'Les résultats ne peuvent être certifiés qu\'après la clôture du scrutin.');
            SecurityHelper::redirect('index.php?page=election_details&id=' . SecurityHelper::encryptId($id));
        }

        if (!empty($election['etat_egalite_persistante'])) {
            SessionHelper::setFlash('warning', 'Une égalité persistante est en cours : veuillez d\'abord enregistrer la décision officielle du bureau avant de certifier les résultats.');
            SecurityHelper::redirect('index.php?page=election_details&id=' . SecurityHelper::encryptId($id));
        }

        $certified = ($_POST['certified'] ?? '1') === '1';
        $this->electionModel->certifyResults($id, $certified);

        if ($certified) {
            SessionHelper::setFlash('success', "Les résultats de l'élection « {$election['titre']} » ont été officiellement certifiés par le bureau.");
        } else {
            SessionHelper::setFlash('info', "La certification des résultats a été retirée.");
        }

        $redirect = !empty($_POST['redirect_resultats'])
            ? 'index.php?page=resultats_admin&election_id=' . SecurityHelper::encryptId($id)
            : 'index.php?page=election_details&id=' . SecurityHelper::encryptId($id);
        SecurityHelper::redirect($redirect);
    }

    /**
     * Consultation des détails d'une élection, de ses tours, de sa liste d'électeurs par tour et des décisions du bureau
     */
    public function details(): void
    {
        $id = SecurityHelper::decryptId((string)($_GET['id'] ?? '')) ?? (int)($_GET['id'] ?? 0);
        $election = $this->electionService->getElectionById($id);

        if (!$election) {
            SessionHelper::setFlash('danger', 'Élection introuvable.');
            SecurityHelper::redirect('index.php?page=elections');
        }

        $phaseInfo = $election['phase_info'];
        $derniereElection = $this->electionService->getDerniereElection();
        $isDerniereElection = $derniereElection && (int)$derniereElection['id'] === (int)$election['id'];

        $rawCandidats = $this->candidatModel->getAll(['election_id' => $id]);
        $candidats = [];
        foreach ($rawCandidats as $c) {
            $candidats[] = $this->candidatureService->enrichCandidat($c);
        }

        $tours = $this->tourService->getToursByElection($id);
        $selectedTourId = !empty($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;
        $activeTour = null;
        if ($selectedTourId) {
            foreach ($tours as $t) {
                if ((int)$t['id'] === $selectedTourId) {
                    $activeTour = $t;
                    break;
                }
            }
        }
        if (!$activeTour) {
            $activeTour = $this->tourService->getActiveOrLatestTour($id);
        }

        $activeTourId = $activeTour ? (int)$activeTour['id'] : 0;
        $isTourVoterLocked = $activeTour ? $this->electeurTourService->isTourVoterListLocked($activeTour, $phaseInfo['now']) : true;
        $electeursTour = $activeTourId > 0 ? $this->electeurTourService->getElecteursByTour($id, $activeTourId) : [];
        $journalElecteurs = $this->electeurTourService->getJournalByElection($id);
        $decisionsBureau = $this->qualificationService->getDecisionsByElection($id);
        $tousLesMembres = $this->membreModel->getAll(['statut' => 'actif']);

        $nbVotants = $this->participationModel->countByElection($id, $activeTourId > 0 ? $activeTourId : null);
        $nbEligibles = $activeTourId > 0
            ? $this->electeurTourService->countAuthorizedForTour($id, $activeTourId)
            : (int)($election['nb_eligibles'] ?? 0);

        require __DIR__ . '/../../views/elections/details.php';
    }
}
