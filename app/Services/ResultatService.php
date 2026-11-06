<?php
/**
 * ResultatService - Calcul et restitution des résultats d'un scrutin et de ses tours
 * (Sections 8, 9 & 10)
 */

declare(strict_types=1);

require_once __DIR__ . '/../Models/Election.php';
require_once __DIR__ . '/../Models/Candidat.php';
require_once __DIR__ . '/../Models/Participation.php';
require_once __DIR__ . '/../Models/Membre.php';
require_once __DIR__ . '/ElectionService.php';
require_once __DIR__ . '/TourElectionService.php';
require_once __DIR__ . '/ElecteurTourService.php';
require_once __DIR__ . '/QualificationService.php';

class ResultatService
{
    public const MSG_EGALITE_COMITE = 'ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !';

    private PDO $pdo;
    private ElectionService $electionService;
    private TourElectionService $tourService;
    private ElecteurTourService $electeurTourService;
    private QualificationService $qualificationService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->electionService = new ElectionService($this->pdo);
        $this->tourService = new TourElectionService($this->pdo);
        $this->electeurTourService = new ElecteurTourService($this->pdo);
        $this->qualificationService = new QualificationService($this->pdo);
    }

    /**
     * Calcule les résultats détaillés d'un tour spécifique d'une élection
     */
    public function computeTourResultats(array $election, array $tour): array
    {
        $electionId = (int)$election['id'];
        $tourId = (int)$tour['id'];
        $numeroTour = (int)$tour['numero_tour'];
        $isT1 = ($numeroTour === 1) ? 1 : 0;

        // S'assurer que le Tour 1 a bien ses candidats validés associés
        if ($numeroTour === 1) {
            $this->tourService->syncTour1ValidatedCandidates($electionId, $tourId);
        }

        // Nombre d'électeurs autorisés pour ce tour
        $nbEligibles = $this->electeurTourService->countAuthorizedForTour($electionId, $tourId);

        // Nombre de votants (émargements) pour ce tour
        $stmtVotants = $this->pdo->prepare("
            SELECT COUNT(*) FROM participations
            WHERE election_id = :election_id
              AND statut = 'a_vote'
              AND (tour_id = :tid OR (:is_t1 = 1 AND tour_id IS NULL))
        ");
        $stmtVotants->execute([
            ':election_id' => $electionId,
            ':tid'         => $tourId,
            ':is_t1'       => $isT1,
        ]);
        $nbVotants = (int)$stmtVotants->fetchColumn();

        // Nombre de bulletins dépouillés pour ce tour
        $stmtVotes = $this->pdo->prepare("
            SELECT COUNT(*) FROM votes
            WHERE election_id = :election_id
              AND (tour_id = :tid OR (:is_t1 = 1 AND tour_id IS NULL))
        ");
        $stmtVotes->execute([
            ':election_id' => $electionId,
            ':tid'         => $tourId,
            ':is_t1'       => $isT1,
        ]);
        $totalBulletins = (int)$stmtVotes->fetchColumn();

        $tauxParticipation = ($nbEligibles > 0) ? round(($nbVotants / $nbEligibles) * 100, 1) : 0.0;

        // Récupérer les scores des candidats participant à ce tour
        $sql = "SELECT c.id, c.nom, c.prenoms, c.photo, c.motif_candidature, c.statut,
                       COALESCE(ct.statut_qualification, 'participant') AS statut_qualification,
                       COUNT(v.id) AS nb_voix
                FROM candidats_tour ct
                JOIN candidats c ON c.id = ct.candidat_id
                LEFT JOIN votes v ON v.candidat_id = c.id
                    AND v.election_id = c.election_id
                    AND (v.tour_id = :tid OR (:is_t1 = 1 AND v.tour_id IS NULL))
                WHERE ct.tour_id = :tid2 AND c.statut = 'validee'
                GROUP BY c.id, ct.statut_qualification
                ORDER BY nb_voix DESC, c.nom ASC, c.prenoms ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tid'   => $tourId,
            ':is_t1' => $isT1,
            ':tid2'  => $tourId,
        ]);
        $candidatsScores = $stmt->fetchAll();

        // Fallback si candidats_tour était vide au Tour 1
        if (empty($candidatsScores) && $numeroTour === 1) {
            $sqlFallback = "SELECT c.id, c.nom, c.prenoms, c.photo, c.motif_candidature, c.statut,
                                   'participant' AS statut_qualification,
                                   COUNT(v.id) AS nb_voix
                            FROM candidats c
                            LEFT JOIN votes v ON v.candidat_id = c.id AND v.election_id = c.election_id
                            WHERE c.election_id = :election_id AND c.statut = 'validee'
                            GROUP BY c.id
                            ORDER BY nb_voix DESC, c.nom ASC, c.prenoms ASC";
            $stmtFb = $this->pdo->prepare($sqlFallback);
            $stmtFb->execute([':election_id' => $electionId]);
            $candidatsScores = $stmtFb->fetchAll();
        }

        $maxVoix = 0;
        $sumCandidatVoix = 0;
        $scoresMap = [];
        foreach ($candidatsScores as $c) {
            $voix = (int)$c['nb_voix'];
            $sumCandidatVoix += $voix;
            if ($voix > $maxVoix) {
                $maxVoix = $voix;
            }
            $scoresMap[] = $voix;
        }

        if ($sumCandidatVoix > $totalBulletins) {
            $totalBulletins = $sumCandidatVoix;
        }

        $voixCounts = array_count_values($scoresMap);

        $leaders = [];
        $qualifies = [];
        $candidatsFormat = [];
        $prevVoix = null;
        $prevRang = 1;

        foreach ($candidatsScores as $index => $c) {
            $voix = (int)$c['nb_voix'];
            $pourcentage = ($totalBulletins > 0) ? round(($voix / $totalBulletins) * 100, 1) : 0.0;
            $isLeader = ($voix === $maxVoix && $maxVoix > 0);

            if ($prevVoix !== null && $voix === $prevVoix) {
                $rang = $prevRang;
            } else {
                $rang = $index + 1;
                $prevRang = $rang;
            }
            $prevVoix = $voix;

            $isExAequo = (($voixCounts[$voix] ?? 0) > 1);
            $rangLabel = '#' . $rang;
            if ($isExAequo) {
                $rangLabel .= ' (ex æquo)';
            }

            $statutQualif = $c['statut_qualification'] ?? 'participant';
            if ($isLeader && count($candidatsScores) > 1 && ($voixCounts[$maxVoix] ?? 0) > 1) {
                $statutQualif = 'qualifie';
            } elseif ($isLeader && ($voixCounts[$maxVoix] ?? 0) === 1 && ($tour['statut'] ?? '') === 'cloturee') {
                $statutQualif = 'vainqueur';
            }

            $candidatData = [
                'id'                   => (int)$c['id'],
                'nom'                  => $c['nom'],
                'prenoms'              => $c['prenoms'],
                'photo'                => $c['photo'],
                'motif_candidature'    => $c['motif_candidature'],
                'nb_voix'              => $voix,
                'pourcentage'          => $pourcentage,
                'is_leader'            => $isLeader,
                'rang'                 => $rang,
                'rang_label'           => $rangLabel,
                'is_ex_aequo'          => $isExAequo,
                'statut_qualification' => $statutQualif,
            ];

            if ($isLeader) {
                $leaders[] = $candidatData;
            }
            if ($statutQualif === 'qualifie' || ($isLeader && ($voixCounts[$maxVoix] ?? 0) > 1)) {
                $qualifies[] = $candidatData;
            }

            $candidatsFormat[] = $candidatData;
        }

        $isEgalite = (count($leaders) > 1);
        $vainqueur = (count($leaders) === 1) ? $leaders[0] : null;

        // Si le bureau a pris une décision officielle désignant un vainqueur sur le dernier tour
        if ($isEgalite && !empty($election['vainqueur_candidat_id']) && empty($election['etat_egalite_persistante'])) {
            foreach ($candidatsFormat as $cf) {
                if ($cf['id'] === (int)$election['vainqueur_candidat_id']) {
                    $vainqueur = $cf;
                    $isEgalite = false;
                    break;
                }
            }
        }

        $podium = array_slice($candidatsFormat, 0, 3);
        $autresCandidats = array_slice($candidatsFormat, 3);

        // Message officiel de victoire selon le modèle exact de la Section 9
        $messageVictoire = null;
        if (!$isEgalite && $vainqueur !== null) {
            $nomScrutin = $election['titre'];
            $nomCandidat = trim($vainqueur['prenoms'] . ' ' . $vainqueur['nom']);
            $nbVoixVainqueur = (int)$vainqueur['nb_voix'];
            $pctVainqueur = number_format((float)$vainqueur['pourcentage'], 1, ',', ' ');
            $messageVictoire = "Les votes du scrutin \"{$nomScrutin}\" : vainqueur {$nomCandidat} avec {$nbVoixVainqueur} voix ({$pctVainqueur} %).";
        }

        return [
            'tour'                 => $tour,
            'numero_tour'          => $numeroTour,
            'tour_libelle'         => $tour['libelle'] ?? $this->tourService->formatTourLabel($numeroTour),
            'nb_eligibles'         => $nbEligibles,
            'nb_votants'           => $nbVotants,
            'total_bulletins'      => $totalBulletins,
            'taux_participation'   => $tauxParticipation,
            'candidats'            => $candidatsFormat,
            'podium'               => $podium,
            'autres_candidats'     => $autresCandidats,
            'qualifies'            => $qualifies,
            'max_voix'             => $maxVoix,
            'is_egalite'           => $isEgalite,
            'leaders'              => $leaders,
            'vainqueur'            => $vainqueur,
            'message_victoire'     => $messageVictoire,
            'message_egalite'      => self::MSG_EGALITE_COMITE,
            'message_officiel'     => $isEgalite ? self::MSG_EGALITE_COMITE : $messageVictoire,
            'derniere_mise_a_jour' => date('d/m/Y à H:i'),
        ];
    }

    /**
     * Calcule l'ensemble des statistiques et résultats d'une élection (et de tous ses tours)
     *
     * @param int $electionId Identifiant de l'élection
     * @param int|null $tourId Identifiant d'un tour spécifique (optionnel : par défaut le tour actif/dernier)
     * @return array|null Données consolidées des résultats
     */
    public function getResultats(int $electionId, ?int $tourId = null): ?array
    {
        $election = $this->electionService->getElectionById($electionId);
        if (!$election) {
            return null;
        }

        $allTours = $this->tourService->getToursByElection($electionId);
        $selectedTour = null;

        if ($tourId !== null && $tourId > 0) {
            foreach ($allTours as $t) {
                if ((int)$t['id'] === $tourId) {
                    $selectedTour = $t;
                    break;
                }
            }
        }

        if (!$selectedTour) {
            $selectedTour = $this->tourService->getActiveOrLatestTour($electionId);
        }

        if (!$selectedTour) {
            return null;
        }

        // Calculer les résultats pour chaque tour afin de conserver l'historique complet (Section 10)
        $toursHistory = [];
        $currentTourData = null;

        foreach ($allTours as $t) {
            $resTour = $this->computeTourResultats($election, $t);
            $toursHistory[] = $resTour;
            if ((int)$t['id'] === (int)$selectedTour['id']) {
                $currentTourData = $resTour;
            }
        }

        if (!$currentTourData) {
            $currentTourData = $this->computeTourResultats($election, $selectedTour);
        }

        $decisions = $this->qualificationService->getDecisionsByElection($electionId);

        return array_merge($currentTourData, [
            'election'                 => $election,
            'tours'                    => $allTours,
            'tours_history'            => $toursHistory,
            'selected_tour_id'         => (int)$selectedTour['id'],
            'etat_egalite_persistante' => !empty($election['etat_egalite_persistante']),
            'decisions_bureau'         => $decisions,
        ]);
    }
}
