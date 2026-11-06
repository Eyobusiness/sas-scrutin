<?php
/**
 * Service d'authentification des électeurs (double vérification Téléphone + Date de naissance),
 * de contrôle d'autorisation par tour et de sécurisation du parcours de vote (Sections 6 & 7)
 */

declare(strict_types=1);

require_once __DIR__ . '/../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Helpers/SecurityHelper.php';
require_once __DIR__ . '/../Models/Membre.php';
require_once __DIR__ . '/../Models/Election.php';
require_once __DIR__ . '/../Models/Participation.php';
require_once __DIR__ . '/../Models/Vote.php';
require_once __DIR__ . '/ElectionService.php';
require_once __DIR__ . '/TourElectionService.php';
require_once __DIR__ . '/ElecteurTourService.php';

class VoteService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_SECONDS = 300; // 5 minutes
    private const SESSION_TTL_SECONDS = 900; // 15 minutes max pour voter après authentification

    private Membre $membreModel;
    private Election $electionModel;
    private Participation $participationModel;
    private Vote $voteModel;
    private ElectionService $electionService;
    private TourElectionService $tourService;
    private ElecteurTourService $electeurTourService;

    public function __construct(?PDO $pdo = null)
    {
        $this->membreModel = new Membre($pdo);
        $this->electionModel = new Election($pdo);
        $this->participationModel = new Participation($pdo);
        $this->voteModel = new Vote($pdo);
        $this->electionService = new ElectionService($pdo);
        $this->tourService = new TourElectionService($pdo);
        $this->electeurTourService = new ElecteurTourService($pdo);
    }

    /**
     * Réinitialise l'état de vérification en session
     */
    public function resetVoterSession(): void
    {
        SessionHelper::start();
        unset(
            $_SESSION['vote_auth'],
            $_SESSION['vote_step'],
            $_SESSION['vote_membre'],
            $_SESSION['vote_election_id']
        );
    }

    /**
     * Réinitialise le compteur de tentatives d'identification
     */
    public function resetRateLimit(): void
    {
        SessionHelper::start();
        unset($_SESSION['vote_rate_limit']);
    }

    /**
     * Vérifie si l'utilisateur n'a pas dépassé la limite de tentatives d'identification
     */
    public function checkRateLimit(): array
    {
        SessionHelper::start();
        $rl = $_SESSION['vote_rate_limit'] ?? ['attempts' => 0, 'locked_until' => 0];

        if (!empty($rl['locked_until']) && time() < $rl['locked_until']) {
            $remaining = (int)ceil(($rl['locked_until'] - time()) / 60);
            return [
                'allowed' => false,
                'message' => "Trop de tentatives d'identification échouées. Veuillez patienter {$remaining} minute(s) avant de réessayer."
            ];
        }

        if (!empty($rl['locked_until']) && time() >= $rl['locked_until']) {
            $this->resetRateLimit();
        }

        return ['allowed' => true, 'message' => null];
    }

    /**
     * Enregistre un échec d'identification
     */
    private function recordFailedAttempt(): void
    {
        SessionHelper::start();
        if (!isset($_SESSION['vote_rate_limit'])) {
            $_SESSION['vote_rate_limit'] = ['attempts' => 0, 'locked_until' => 0];
        }

        $_SESSION['vote_rate_limit']['attempts']++;
        if ($_SESSION['vote_rate_limit']['attempts'] >= self::MAX_ATTEMPTS) {
            $_SESSION['vote_rate_limit']['locked_until'] = time() + self::LOCKOUT_SECONDS;
        }
    }

    /**
     * Détermine l'étape actuelle du parcours de vote (1, 2 ou 3) pour l'élection et son tour actif
     */
    public function getCurrentStep(int $electionId): int
    {
        SessionHelper::start();
        if (empty($_SESSION['vote_auth']) || !is_array($_SESSION['vote_auth'])) {
            return 1;
        }

        $auth = $_SESSION['vote_auth'];

        if (($auth['election_id'] ?? 0) !== $electionId) {
            $this->resetVoterSession();
            return 1;
        }

        $activeTour = $this->tourService->getActiveOrLatestTour($electionId);
        if ($activeTour && !empty($auth['tour_id']) && (int)$auth['tour_id'] !== (int)$activeTour['id']) {
            $this->resetVoterSession();
            return 1;
        }

        if (empty($auth['started_at']) || (time() - (int)$auth['started_at']) > self::SESSION_TTL_SECONDS) {
            $this->resetVoterSession();
            return 1;
        }

        $step = (int)($auth['step'] ?? 1);
        if ($step === 3 && empty($auth['dob_verified'])) {
            return 2;
        }

        return in_array($step, [1, 2, 3], true) ? $step : 1;
    }

    /**
     * Retourne uniquement les informations publiques autorisées à l'étape 3 (après vérification complète)
     * Ne retourne JAMAIS la date de naissance enregistrée !
     */
    public function getVerifiedVoterSummary(int $electionId): ?array
    {
        if ($this->getCurrentStep($electionId) !== 3) {
            return null;
        }

        $membreId = (int)($_SESSION['vote_auth']['membre_id'] ?? 0);
        if ($membreId <= 0) {
            return null;
        }

        $membre = $this->membreModel->findById($membreId);
        if (!$membre) {
            return null;
        }

        return [
            'id'        => (int)$membre['id'],
            'nom'       => $membre['nom'],
            'prenoms'   => $membre['prenoms'],
            'telephone' => $membre['telephone'],
        ];
    }

    /**
     * ÉTAPE 1 : Vérification du numéro de téléphone et de l'autorisation sur le tour actif
     */
    public function verifyPhoneStep(array $election, string $rawPhone): array
    {
        $rateCheck = $this->checkRateLimit();
        if (!$rateCheck['allowed']) {
            return [
                'success' => false,
                'type'    => 'danger',
                'message' => $rateCheck['message'],
                'step'    => 1
            ];
        }

        $canVote = $this->electionService->canVote($election);
        if (!$canVote['can_vote']) {
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'warning',
                'message' => $canVote['message'],
                'step'    => 1
            ];
        }

        $telephone = SecurityHelper::cleanPhone($rawPhone);
        if ($telephone === '') {
            return [
                'success' => false,
                'type'    => 'warning',
                'message' => 'Veuillez saisir votre numéro de téléphone.',
                'step'    => 1
            ];
        }

        $membre = $this->membreModel->findByTelephone($telephone);

        // 1. Numéro introuvable
        if (!$membre) {
            $this->recordFailedAttempt();
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'danger',
                'message' => 'Numéro de téléphone incorrect ou introuvable dans la liste des électeurs. Veuillez vérifier vos informations.',
                'step'    => 1
            ];
        }

        $electionId = (int)$election['id'];
        $activeTour = $this->tourService->getActiveOrLatestTour($electionId);
        $activeTourId = $activeTour ? (int)$activeTour['id'] : 0;

        // 2. Vérification de l'autorisation à voter avec motif précis de la base de données (Section 7)
        $authCheck = $this->electeurTourService->checkMemberAuthorizationForTour($electionId, $activeTourId, $membre);
        if (!$authCheck['authorized']) {
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'warning',
                'message' => $authCheck['message'],
                'step'    => 1
            ];
        }

        // 3. Membre ayant déjà voté pour ce tour de scrutin (Section 6)
        if ($this->participationModel->hasVoted($electionId, (int)$membre['id'], $activeTourId > 0 ? $activeTourId : null)) {
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'info',
                'message' => 'Vous avez déjà participé à cette élection. Merci de patienter pour les résultats.',
                'step'    => 1
            ];
        }

        // Succès de l'étape 1 : stocker uniquement l'ID du membre et du tour en session temporaire
        SessionHelper::regenerate();
        $_SESSION['vote_auth'] = [
            'step'         => 2,
            'membre_id'    => (int)$membre['id'],
            'election_id'  => $electionId,
            'tour_id'      => $activeTourId,
            'dob_verified' => false,
            'started_at'   => time()
        ];

        return [
            'success' => true,
            'type'    => 'info',
            'message' => 'Veuillez maintenant saisir votre date de naissance pour confirmer votre identité.',
            'step'    => 2
        ];
    }

    /**
     * ÉTAPE 2 : Vérification de la date de naissance
     */
    public function verifyBirthDateStep(array $election, string $inputDateNaissance): array
    {
        $electionId = (int)$election['id'];

        if ($this->getCurrentStep($electionId) < 2 || empty($_SESSION['vote_auth']['membre_id'])) {
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'danger',
                'message' => 'Veuillez d\'abord saisir votre numéro de téléphone.',
                'step'    => 1
            ];
        }

        $rateCheck = $this->checkRateLimit();
        if (!$rateCheck['allowed']) {
            return [
                'success' => false,
                'type'    => 'danger',
                'message' => $rateCheck['message'],
                'step'    => 2
            ];
        }

        $canVote = $this->electionService->canVote($election);
        if (!$canVote['can_vote']) {
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'warning',
                'message' => $canVote['message'],
                'step'    => 1
            ];
        }

        $membreId = (int)$_SESSION['vote_auth']['membre_id'];
        $activeTour = $this->tourService->getActiveOrLatestTour($electionId);
        $activeTourId = $activeTour ? (int)$activeTour['id'] : 0;

        $membre = $this->membreModel->findById($membreId);
        if (!$membre) {
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'warning',
                'message' => "Vous n'êtes pas autorisé(e) à participer à ce scrutin parce que : Membre introuvable.",
                'step'    => 1
            ];
        }

        $authCheck = $this->electeurTourService->checkMemberAuthorizationForTour($electionId, $activeTourId, $membre);
        if (!$authCheck['authorized']) {
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'warning',
                'message' => $authCheck['message'],
                'step'    => 1
            ];
        }

        if ($this->participationModel->hasVoted($electionId, $membreId, $activeTourId > 0 ? $activeTourId : null)) {
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'info',
                'message' => 'Vous avez déjà participé à cette élection. Merci de patienter pour les résultats.',
                'step'    => 1
            ];
        }

        $saisie = trim($inputDateNaissance);
        $dateEnregistree = !empty($membre['date_naissance']) ? trim((string)$membre['date_naissance']) : '';

        if ($saisie === '' || $dateEnregistree === '' || !hash_equals($dateEnregistree, $saisie)) {
            $this->recordFailedAttempt();
            return [
                'success' => false,
                'type'    => 'danger',
                'message' => 'Les informations fournies ne correspondent pas à celles enregistrées dans le système. Veuillez vérifier votre numéro de téléphone et votre date de naissance.',
                'step'    => 2
            ];
        }

        $this->resetRateLimit();
        SessionHelper::regenerate();
        $_SESSION['vote_auth']['step'] = 3;
        $_SESSION['vote_auth']['dob_verified'] = true;
        $_SESSION['vote_auth']['verified_at'] = time();

        return [
            'success' => true,
            'type'    => 'success',
            'message' => 'Identité vérifiée avec succès. Vous pouvez maintenant procéder au vote.',
            'step'    => 3
        ];
    }

    /**
     * ÉTAPE 3 : Enregistrement du vote après double vérification sur le tour actif
     */
    public function castAuthenticatedVote(array $election, int $candidatId): array
    {
        $electionId = (int)$election['id'];

        if (
            $this->getCurrentStep($electionId) !== 3
            || empty($_SESSION['vote_auth']['dob_verified'])
            || empty($_SESSION['vote_auth']['membre_id'])
        ) {
            $this->resetVoterSession();
            return [
                'success' => false,
                'type'    => 'danger',
                'message' => 'Accès non autorisé au bulletin de vote. Veuillez valider votre numéro de téléphone et votre date de naissance.'
            ];
        }

        if ($candidatId <= 0) {
            return [
                'success' => false,
                'type'    => 'warning',
                'message' => 'Veuillez sélectionner un candidat sur le bulletin avant de valider votre vote.'
            ];
        }

        $membreId = (int)$_SESSION['vote_auth']['membre_id'];
        $tourId = !empty($_SESSION['vote_auth']['tour_id']) ? (int)$_SESSION['vote_auth']['tour_id'] : null;

        [$success, $message] = $this->voteModel->castVote($electionId, $candidatId, $membreId, $tourId);

        $this->resetVoterSession();

        return [
            'success' => $success,
            'type'    => $success ? 'success' : 'danger',
            'message' => $message
        ];
    }
}
