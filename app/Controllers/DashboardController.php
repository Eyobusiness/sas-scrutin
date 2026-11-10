<?php
/**
 * Contrôleur du Tableau de bord (Dashboard)
 */

declare(strict_types=1);

require_once __DIR__ . '/../Helpers/AuthHelper.php';
require_once __DIR__ . '/../Models/Membre.php';
require_once __DIR__ . '/../Models/Election.php';
require_once __DIR__ . '/../Models/Candidat.php';
require_once __DIR__ . '/../Models/Participation.php';
require_once __DIR__ . '/../Models/Vote.php';
require_once __DIR__ . '/../Services/ElectionService.php';

class DashboardController
{
    private Membre $membreModel;
    private Election $electionModel;
    private Candidat $candidatModel;
    private Participation $participationModel;
    private Vote $voteModel;
    private ElectionService $electionService;

    public function __construct()
    {
        AuthHelper::requireLogin();
        $this->membreModel = new Membre();
        $this->electionModel = new Election();
        $this->candidatModel = new Candidat();
        $this->participationModel = new Participation();
        $this->voteModel = new Vote();
        $this->electionService = new ElectionService();
    }

    public function index(): void
    {
        $recentElections = $this->electionService->getAllElectionsEnriched();
        $derniereElection = $recentElections[0] ?? null;
        $membreStats = $this->membreModel->getStats();
        $electionStats = $this->electionModel->getStats();
        $candidatStats = $this->candidatModel->getStats();
        $activeElection = $this->electionModel->getActiveElection();

        $activeElectionStats = null;
        if ($activeElection) {
            $nbVotants = $this->participationModel->countByElection((int)$activeElection['id']);
            $nbEligibles = $membreStats['eligibles'];
            $taux = ($nbEligibles > 0) ? round(($nbVotants / $nbEligibles) * 100, 1) : 0;
            $activeElectionStats = [
                'nb_votants'   => $nbVotants,
                'nb_eligibles' => $nbEligibles,
                'taux'         => $taux
            ];
        }

        $recentElections = array_slice($recentElections, 0, 5);

        require __DIR__ . '/../../views/dashboard/index.php';
    }
}
