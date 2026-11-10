<?php
/**
 * Contrôleur d'administration des votes, des participations et des résultats par tour
 */

declare(strict_types=1);

require_once __DIR__ . '/../Helpers/AuthHelper.php';
require_once __DIR__ . '/../Helpers/SessionHelper.php';
require_once __DIR__ . '/../Helpers/SecurityHelper.php';
require_once __DIR__ . '/../Models/Election.php';
require_once __DIR__ . '/../Models/Participation.php';
require_once __DIR__ . '/../Services/ResultatService.php';
require_once __DIR__ . '/../Services/ElectionService.php';
require_once __DIR__ . '/../Services/TourElectionService.php';

class VoteAdminController
{
    private Election $electionModel;
    private Participation $participationModel;
    private ResultatService $resultatService;
    private ElectionService $electionService;
    private TourElectionService $tourService;

    public function __construct()
    {
        AuthHelper::requireLogin();
        $this->electionModel = new Election();
        $this->participationModel = new Participation();
        $this->resultatService = new ResultatService();
        $this->electionService = new ElectionService();
        $this->tourService = new TourElectionService();
    }

    /**
     * Liste des émargements / participations nominatives (filtrable par élection et par tour)
     */
    public function participations(): void
    {
        $elections = $this->electionService->getAllElectionsEnriched();
        $selectedElectionId = !empty($_GET['election_id']) ? SecurityHelper::decryptId($_GET['election_id']) : null;

        if (!$selectedElectionId && !empty($elections)) {
            $selectedElectionId = (int)$elections[0]['id'];
        }

        $participations = [];
        $currentElection = null;
        $tours = [];
        $selectedTourId = !empty($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;

        if ($selectedElectionId) {
            $currentElection = $this->electionService->getElectionById($selectedElectionId);
            $tours = $this->tourService->getToursByElection($selectedElectionId);
            $search = trim($_GET['search'] ?? '');
            $filters = ['search' => $search];
            if ($selectedTourId) {
                $filters['tour_id'] = $selectedTourId;
            }
            $participations = $this->participationModel->getByElection($selectedElectionId, $filters);
        }

        require __DIR__ . '/../../views/participations/index.php';
    }

    /**
     * Résultats en direct et par tour pour les administrateurs
     */
    public function resultats(): void
    {
        $elections = $this->electionService->getAllElectionsEnriched();
        $selectedElectionId = !empty($_GET['election_id']) ? SecurityHelper::decryptId($_GET['election_id']) : null;

        if (!$selectedElectionId && !empty($elections)) {
            $selectedElectionId = (int)$elections[0]['id'];
        }

        $selectedTourId = !empty($_GET['tour_id']) ? (int)$_GET['tour_id'] : null;
        $donnees = null;
        if ($selectedElectionId) {
            $donnees = $this->resultatService->getResultats($selectedElectionId, $selectedTourId);
        }

        require __DIR__ . '/../../views/resultats/admin.php';
    }
}
