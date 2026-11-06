<?php
/**
 * Contrôleur de l'espace public (Inscription, Candidature, Vote électronique, Résultats)
 * Utilise automatiquement le dernier scrutin enregistré comme scrutin de référence.
 */

declare(strict_types=1);

require_once __DIR__ . '/../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Helpers/SecurityHelper.php';
require_once __DIR__ . '/../Models/Membre.php';
require_once __DIR__ . '/../Models/Election.php';
require_once __DIR__ . '/../Models/Candidat.php';
require_once __DIR__ . '/../Models/Participation.php';
require_once __DIR__ . '/../Models/Vote.php';
require_once __DIR__ . '/../Models/Parametre.php';
require_once __DIR__ . '/../Services/ElectionService.php';
require_once __DIR__ . '/../Services/ResultatService.php';
require_once __DIR__ . '/../Services/ParametreService.php';
require_once __DIR__ . '/../Services/EligibilityService.php';
require_once __DIR__ . '/../Services/CandidatureService.php';
require_once __DIR__ . '/../Services/VoteService.php';
require_once __DIR__ . '/../Services/TourElectionService.php';

class PublicController
{
    private Membre $membreModel;
    private Election $electionModel;
    private Candidat $candidatModel;
    private Participation $participationModel;
    private Vote $voteModel;
    private Parametre $parametreModel;
    private ElectionService $electionService;
    private ResultatService $resultatService;
    private ParametreService $parametreService;
    private EligibilityService $eligibilityService;
    private CandidatureService $candidatureService;
    private VoteService $voteService;
    private TourElectionService $tourService;

    public function __construct()
    {
        $this->membreModel = new Membre();
        $this->electionModel = new Election();
        $this->candidatModel = new Candidat();
        $this->participationModel = new Participation();
        $this->voteModel = new Vote();
        $this->parametreModel = new Parametre();
        $this->electionService = new ElectionService();
        $this->resultatService = new ResultatService();
        $this->parametreService = new ParametreService();
        $this->eligibilityService = new EligibilityService(null, $this->parametreService);
        $this->candidatureService = new CandidatureService();
        $this->voteService = new VoteService();
        $this->tourService = new TourElectionService();
    }

    /**
     * Page d'accueil publique / Portail du scrutin
     * Affiche automatiquement les informations du dernier scrutin enregistré.
     */
    public function home(): void
    {
        $derniereElection = $this->electionService->getDerniereElection();
        $phaseInfo = $derniereElection['phase_info'] ?? null;
        $activeElection = ($derniereElection && !empty($phaseInfo['vote_open'])) ? $derniereElection : null;
        $appParams = $this->parametreModel->getAll();

        require __DIR__ . '/../../views/public/home.php';
    }

    /**
     * Inscription publique d'un nouveau membre avec vérification automatique d'éligibilité
     * et verrouillage automatique dès la fermeture des inscriptions ou le début du vote.
     */
    public function inscription(): void
    {
        $derniereElection = $this->electionService->getDerniereElection();
        $registrationStatus = $this->electionService->canRegisterMember($derniereElection);
        $inscriptionsOuvertes = $registrationStatus['allowed'];
        $messageVerrouillage = $registrationStatus['message'];
        $electorConditions = $this->parametreService->getElectorConditions();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
                SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
                SecurityHelper::redirect('index.php?page=inscription');
            }

            // Contrôle strict côté serveur : refuser toute inscription hors période ou après le début du vote
            if (!$inscriptionsOuvertes) {
                SessionHelper::setFlash('danger', ElectionService::MSG_INSCRIPTIONS_CLOTUREES);
                SecurityHelper::redirect('index.php?page=inscription');
            }

            $nom = trim($_POST['nom'] ?? '');
            $prenoms = trim($_POST['prenoms'] ?? '');
            $telephone = SecurityHelper::cleanPhone($_POST['telephone'] ?? '');
            $dateNaissanceRaw = trim($_POST['date_naissance'] ?? '');
            $fonctionPro = trim($_POST['fonction_pro'] ?? '');
            $responsabiliteSas = trim($_POST['responsabilite_sas'] ?? '');
            $ancienneteRaw = $_POST['anciennete'] ?? ($_POST['ancienete'] ?? null);

            if ($nom === '' || $prenoms === '' || $telephone === '') {
                SessionHelper::setFlash('warning', 'Le nom, les prénoms et le numéro de téléphone sont obligatoires.');
                SecurityHelper::redirect('index.php?page=inscription');
            }

            if (!SecurityHelper::isValidPhone($telephone)) {
                SessionHelper::setFlash('warning', 'Le numéro de téléphone n\'est pas au bon format.');
                SecurityHelper::redirect('index.php?page=inscription');
            }

            // Validation de la date de naissance côté serveur (date valide et non future)
            $birthValidation = $this->eligibilityService->validateBirthDate($dateNaissanceRaw);
            if (!$birthValidation['valid']) {
                SessionHelper::setFlash('warning', $birthValidation['error']);
                SecurityHelper::redirect('index.php?page=inscription');
            }

            // Validation de l'ancienneté saisie manuellement (entier >= 0, jamais calculée)
            $ancValidation = $this->eligibilityService->validateAnciennete($ancienneteRaw);
            if (!$ancValidation['valid']) {
                SessionHelper::setFlash('warning', $ancValidation['error']);
                SecurityHelper::redirect('index.php?page=inscription');
            }

            // Vérifier si le numéro est déjà inscrit
            if ($this->membreModel->findByTelephone($telephone)) {
                SessionHelper::setFlash('info', 'Ce numéro de téléphone est déjà enregistré dans la liste des membres. Veuillez contacter le bureau du SAS si nécessaire.');
                SecurityHelper::redirect('index.php?page=inscription');
            }

            // Vérification automatique des conditions d'éligibilité configurées
            $eval = $this->eligibilityService->evaluateMemberEligibility(
                $birthValidation['date'],
                $ancValidation['anciennete']
            );

            // Enregistrement du membre avec son éligibilité calculée ('oui' ou 'non')
            $membreId = $this->membreModel->create([
                'nom'                   => $nom,
                'prenoms'               => $prenoms,
                'telephone'             => $telephone,
                'date_naissance'        => $birthValidation['date'],
                'anciennete'            => $ancValidation['anciennete'],
                'fonction_pro'          => $fonctionPro,
                'responsabilite_sas'    => $responsabiliteSas,
                'statut'                => 'actif',
                'eligibilite_a_voter'   => $eval['eligibilite_a_voter'],
                'motif_non_eligibilite' => $eval['motif_db']
            ]);

            // Récupérer les données exactement telles qu'elles sont sauvegardées en base
            $savedMembre = $this->membreModel->findById($membreId);

            $_SESSION['inscription_result'] = [
                'eligible'       => $eval['eligible'],
                'nom'            => $savedMembre['nom'] ?? $nom,
                'prenoms'        => $savedMembre['prenoms'] ?? $prenoms,
                'telephone'      => $savedMembre['telephone'] ?? $telephone,
                'date_naissance' => $savedMembre['date_naissance'] ?? $birthValidation['date'],
                'anciennete'     => $savedMembre['anciennete'] ?? $ancValidation['anciennete'],
                'age'            => $eval['age'],
                'motifs'         => $eval['motifs'],
                'explications'   => $eval['explications']
            ];

            SecurityHelper::redirect('index.php?page=inscription');
        }

        $inscriptionResult = $_SESSION['inscription_result'] ?? null;
        unset($_SESSION['inscription_result']);

        require __DIR__ . '/../../views/public/inscription.php';
    }

    /**
     * Dépôt public d'une candidature associé automatiquement au dernier scrutin enregistré.
     * Ne fait jamais confiance à un election_id envoyé par le navigateur.
     * Règle impérative : toute candidature reste en statut 'en_attente' jusqu'à décision du bureau.
     */
    public function candidater(): void
    {
        $derniereElection = $this->electionService->getDerniereElection();
        $candidatureStatus = $this->electionService->canSubmitCandidature($derniereElection);
        $candidaturesOuvertes = $candidatureStatus['allowed'];
        $candidatureBlockReason = $candidatureStatus['reason'];
        $messageVerrouillageCandidature = $candidatureStatus['message'];
        $candidatureConditions = $this->parametreService->getCandidatureConditions();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
                SessionHelper::setFlash('danger', 'Jeton CSRF invalide.');
                SecurityHelper::redirect('index.php?page=candidater');
            }

            // 1. Contrôle côté serveur de l'existence d'une élection et de l'ouverture de la période de candidature
            if (!$candidaturesOuvertes || !$derniereElection) {
                SessionHelper::setFlash('danger', $messageVerrouillageCandidature);
                SecurityHelper::redirect('index.php?page=candidater');
            }

            // Utiliser exclusivement l'identifiant du dernier scrutin déterminé côté serveur
            $electionId = (int)$derniereElection['id'];

            $nom = trim($_POST['nom'] ?? '');
            $prenoms = trim($_POST['prenoms'] ?? '');
            $telephone = SecurityHelper::cleanPhone($_POST['telephone'] ?? '');
            $dateNaissanceRaw = trim($_POST['date_naissance'] ?? '');
            $ancienneteRaw = $_POST['anciennete'] ?? ($_POST['ancienete'] ?? null);
            $droitPayeRaw = $_POST['droit_candidature_paye'] ?? null;
            $motifCandidature = trim($_POST['motif_candidature'] ?? '');

            if ($nom === '' || $prenoms === '' || $motifCandidature === '') {
                SessionHelper::setFlash('warning', 'Tous les champs obligatoires doivent être renseignés.');
                SecurityHelper::redirect('index.php?page=candidater');
            }

            if ($telephone === '' || !SecurityHelper::isValidPhone($telephone)) {
                SessionHelper::setFlash('warning', 'Le numéro de téléphone saisi n\'est pas au bon format.');
                SecurityHelper::redirect('index.php?page=candidater');
            }

            // 2. Vérifier l'absence de candidature doublon ou incompatible pour ce candidat sur ce scrutin
            $submitCheck = $this->candidatureService->checkCanSubmitForElection($derniereElection, $nom, $prenoms, $telephone);
            if (!$submitCheck['allowed']) {
                SessionHelper::setFlash('warning', $submitCheck['message']);
                SecurityHelper::redirect('index.php?page=candidater');
            }

            // 3. Validation de la date de naissance
            $birthValidation = $this->eligibilityService->validateBirthDate($dateNaissanceRaw);
            if (!$birthValidation['valid']) {
                SessionHelper::setFlash('warning', $birthValidation['error']);
                SecurityHelper::redirect('index.php?page=candidater');
            }

            // 4. Validation de l'ancienneté déclarée manuellement
            $ancValidation = $this->eligibilityService->validateAnciennete($ancienneteRaw);
            if (!$ancValidation['valid']) {
                SessionHelper::setFlash('warning', $ancValidation['error']);
                SecurityHelper::redirect('index.php?page=candidater');
            }

            $droitPaye = $this->candidatureService->normalizeDroitPaye($droitPayeRaw);

            // Photo du candidat (si fournie)
            $photoName = null;
            if (!empty($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $destDir = __DIR__ . '/../../uploads/candidats';
                [$success, $result] = SecurityHelper::uploadPhoto($_FILES['photo'], $destDir);
                if (!$success) {
                    SessionHelper::setFlash('danger', $result);
                    SecurityHelper::redirect('index.php?page=candidater');
                }
                $photoName = $result;
            }

            // Évaluation automatique des conditions de candidature
            $eval = $this->candidatureService->evaluateCandidature(
                $birthValidation['date'],
                $ancValidation['anciennete'],
                $droitPaye
            );

            // Enregistrement de la candidature (toujours avec le statut 'en_attente')
            $this->candidatModel->create([
                'election_id'            => $electionId,
                'nom'                    => $nom,
                'prenoms'                => $prenoms,
                'telephone'              => $telephone,
                'date_naissance'         => $birthValidation['date'],
                'anciennete'             => $ancValidation['anciennete'],
                'droit_candidature_paye' => $droitPaye ? 1 : 0,
                'photo'                  => $photoName,
                'motif_candidature'      => $motifCandidature,
                'statut'                 => 'en_attente'
            ]);

            $_SESSION['candidature_result'] = [
                'conforme'       => $eval['conforme'],
                'nom'            => $nom,
                'prenoms'        => $prenoms,
                'age'            => $eval['age'],
                'anciennete'     => $ancValidation['anciennete'],
                'droit_paye'     => $droitPaye,
                'motifs'         => $eval['motifs'],
                'election_titre' => $derniereElection['titre']
            ];

            SecurityHelper::redirect('index.php?page=candidater');
        }

        $candidatureResult = $_SESSION['candidature_result'] ?? null;
        unset($_SESSION['candidature_result']);

        require __DIR__ . '/../../views/public/candidater.php';
    }

    /**
     * Parcours de vote électronique en 3 étapes sur le dernier scrutin enregistré :
     * Étape 1 : Numéro de téléphone
     * Étape 2 : Date de naissance
     * Étape 3 : Bulletin de vote
     */
    public function voter(): void
    {
        // Utiliser exclusivement le dernier scrutin enregistré comme scrutin de référence
        $election = $this->electionService->getDerniereElection();

        // Si aucune élection n'est trouvée
        if (!$election) {
            $this->voteService->resetVoterSession();
            $messageFermeture = 'Aucune élection n\'est actuellement ouverte au vote.';
            require __DIR__ . '/../../views/public/voter_ferme.php';
            return;
        }

        // Vérification stricte de l'ouverture et de la période du scrutin [date_debut_vote, date_fin_vote)
        $canVoteCheck = $this->electionService->canVote($election);
        if (!$canVoteCheck['can_vote']) {
            $this->voteService->resetVoterSession();
            $messageFermeture = $canVoteCheck['message'];
            require __DIR__ . '/../../views/public/voter_ferme.php';
            return;
        }

        // Réinitialisation volontaire du parcours (bouton "Changer de numéro / Recommencer")
        if (!empty($_GET['reset'])) {
            $this->voteService->resetVoterSession();
            SecurityHelper::redirect('index.php?page=voter');
        }

        // Protection contre toute tentative d'accès direct forcé par paramètre GET (ex: ?step=3)
        if (isset($_GET['step']) && (int)$_GET['step'] > $this->voteService->getCurrentStep((int)$election['id'])) {
            $this->voteService->resetVoterSession();
            SessionHelper::setFlash('danger', 'Veuillez vous identifier avec votre numéro de téléphone et votre date de naissance avant d\'accéder au bulletin de vote.');
            SecurityHelper::redirect('index.php?page=voter');
        }

        // Traitement POST des 3 étapes
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'] ?? '';

            if (!SessionHelper::verifyCsrf($_POST['csrf_token'] ?? '')) {
                SessionHelper::setFlash('danger', 'Jeton de sécurité expiré. Veuillez recommencer votre identification.');
                $this->voteService->resetVoterSession();
                SecurityHelper::redirect('index.php?page=voter');
            }

            // Action 0 : Recommencer l'identification
            if ($action === 'recommencer') {
                $this->voteService->resetVoterSession();
                SecurityHelper::redirect('index.php?page=voter');
            }

            // Étape 1 : Vérification du numéro de téléphone
            if ($action === 'verifier_telephone') {
                $res = $this->voteService->verifyPhoneStep($election, $_POST['telephone'] ?? '');
                if (!$res['success']) {
                    SessionHelper::setFlash($res['type'], $res['message']);
                }
                SecurityHelper::redirect('index.php?page=voter');
            }

            // Étape 2 : Vérification de la date de naissance
            if ($action === 'verifier_date_naissance') {
                $res = $this->voteService->verifyBirthDateStep($election, $_POST['date_naissance'] ?? '');
                if (!$res['success']) {
                    SessionHelper::setFlash($res['type'], $res['message']);
                }
                SecurityHelper::redirect('index.php?page=voter');
            }

            // Étape 3 : Enregistrement du vote
            if ($action === 'enregistrer_vote') {
                $candidatId = (int)($_POST['candidat_id'] ?? 0);
                $res = $this->voteService->castAuthenticatedVote($election, $candidatId);

                if ($res['success']) {
                    $_SESSION['vote_success_message'] = $res['message'];
                    SecurityHelper::redirect('index.php?page=vote_succes');
                } else {
                    SessionHelper::setFlash($res['type'], $res['message']);
                    SecurityHelper::redirect('index.php?page=voter');
                }
            }
        }

        $step = $this->voteService->getCurrentStep((int)$election['id']);
        $activeTour = $this->tourService->getActiveOrLatestTour((int)$election['id']);
        $stepMembre = ($step === 3) ? $this->voteService->getVerifiedVoterSummary((int)$election['id']) : null;
        $candidats = ($step === 3)
            ? $this->tourService->getCandidatesForTour((int)$election['id'], $activeTour ? (int)$activeTour['id'] : null)
            : [];

        require __DIR__ . '/../../views/public/voter.php';
    }

    /**
     * Page de confirmation de succès après un vote
     */
    public function voteSucces(): void
    {
        $message = $_SESSION['vote_success_message'] ?? 'Félicitations, votre voix a bien été prise en compte !';
        unset($_SESSION['vote_success_message']);
        require __DIR__ . '/../../views/public/vote_succes.php';
    }

    /**
     * Page publique des résultats :
     * Utilise exclusivement le dernier scrutin enregistré (ignore tout paramètre election_id de l'URL).
     * Gère les 5 cas : Préparation, Inscriptions/Candidatures, Campagne, Vote en cours, et Scrutin clôturé.
     */
    public function resultats(): void
    {
        $derniereElection = $this->electionService->getDerniereElection();
        $currentElection = $derniereElection;
        $phaseInfo = $derniereElection['phase_info'] ?? null;
        $isCloturee = $phaseInfo ? (bool)$phaseInfo['is_closed'] : false;
        $donnees = null;

        if ($derniereElection && $isCloturee) {
            $requestedTourId = !empty($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;
            $donnees = $this->resultatService->getResultats((int)$derniereElection['id'], $requestedTourId);
        }

        require __DIR__ . '/../../views/public/resultats.php';
    }
}
