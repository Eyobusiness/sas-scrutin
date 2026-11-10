<?php
/**
 * Service de gestion des tours de scrutin au sein d'une même élection
 * (Table `tours_election` et `candidats_tour`)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

class TourElectionService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Formate le libellé d'un tour à partir de son numéro (ex: "1er Tour", "2e Tour")
     */
    public function formatTourLabel(int $numeroTour): string
    {
        return $numeroTour === 1 ? '1er Tour' : ($numeroTour . 'e Tour');
    }

    /**
     * Garantit que le Tour 1 existe pour une élection donnée et que les candidats validés y sont associés
     */
    public function ensureInitialTour(int $electionId): ?array
    {
        if ($electionId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM elections WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $electionId]);
        $election = $stmt->fetch();
        if (!$election) {
            return null;
        }

        $stmtTour = $this->pdo->prepare("SELECT * FROM tours_election WHERE election_id = :eid AND numero_tour = 1 LIMIT 1");
        $stmtTour->execute([':eid' => $electionId]);
        $tour1 = $stmtTour->fetch();

        $dateDebut = $election['date_debut_vote'] ?? ($election['date_debut'] ?? date('Y-m-d H:i:s'));
        $dateFin   = $election['date_fin_vote'] ?? ($election['date_fin'] ?? date('Y-m-d H:i:s', strtotime('+1 day')));
        $statut    = $election['statut'] ?? 'preparation';
        $cloture   = $election['date_cloture_effective'] ?? null;

        if (!$tour1) {
            $ins = $this->pdo->prepare("
                INSERT INTO tours_election (election_id, numero_tour, libelle, date_debut_vote, date_fin_vote, statut, date_cloture_effective, date_creation)
                VALUES (:eid, 1, '1er Tour', :debut, :fin, :statut, :cloture, NOW())
            ");
            $ins->execute([
                ':eid'     => $electionId,
                ':debut'   => $dateDebut,
                ':fin'     => $dateFin,
                ':statut'  => $statut,
                ':cloture' => $cloture,
            ]);
            $tour1Id = (int)$this->pdo->lastInsertId();
            $stmtTour->execute([':eid' => $electionId]);
            $tour1 = $stmtTour->fetch();
        } else {
            $tour1Id = (int)$tour1['id'];
        }

        // Synchroniser les candidats validés dans candidats_tour pour le 1er tour
        $this->syncTour1ValidatedCandidates($electionId, $tour1Id);

        // Rattacher les votes et participations orphelins de tour_id au Tour 1
        $updVotes = $this->pdo->prepare("UPDATE votes SET tour_id = :tid WHERE election_id = :eid AND tour_id IS NULL");
        $updVotes->execute([':tid' => $tour1Id, ':eid' => $electionId]);

        $updPart = $this->pdo->prepare("UPDATE participations SET tour_id = :tid WHERE election_id = :eid AND tour_id IS NULL");
        $updPart->execute([':tid' => $tour1Id, ':eid' => $electionId]);

        return $tour1 ?: null;
    }

    /**
     * Synchronise les candidats ayant le statut 'validee' sur le 1er tour d'une élection
     */
    public function syncTour1ValidatedCandidates(int $electionId, int $tour1Id): void
    {
        $sql = "INSERT IGNORE INTO candidats_tour (tour_id, election_id, candidat_id, statut_qualification)
                SELECT :tid, c.election_id, c.id, 'participant'
                FROM candidats c
                WHERE c.election_id = :eid AND c.statut = 'validee'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tid' => $tour1Id,
            ':eid' => $electionId,
        ]);

        // Retirer du Tour 1 (s'il n'est pas clôturé) les candidats qui ne sont plus 'validee'
        $del = $this->pdo->prepare("
            DELETE ct FROM candidats_tour ct
            JOIN tours_election t ON t.id = ct.tour_id
            JOIN candidats c ON c.id = ct.candidat_id
            WHERE ct.tour_id = :tid
              AND t.numero_tour = 1
              AND t.statut != 'cloturee'
              AND c.statut != 'validee'
        ");
        $del->execute([':tid' => $tour1Id]);
    }

    /**
     * Récupère tous les tours d'une élection par ordre chronologique (numero_tour ASC)
     */
    public function getToursByElection(int $electionId): array
    {
        $this->ensureInitialTour($electionId);

        $sql = "SELECT t.*,
                       (SELECT COUNT(*) FROM candidats_tour ct WHERE ct.tour_id = t.id) AS nb_candidats_tour,
                       (SELECT COUNT(*) FROM electeurs_tour et WHERE et.tour_id = t.id AND et.autorise = 1) AS nb_electeurs_tour,
                       (SELECT COUNT(*) FROM participations p WHERE p.tour_id = t.id AND p.statut = 'a_vote') AS nb_votants_tour,
                       (SELECT COUNT(*) FROM votes v WHERE v.tour_id = t.id) AS nb_bulletins_tour
                FROM tours_election t
                WHERE t.election_id = :eid
                ORDER BY t.numero_tour ASC, t.id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':eid' => $electionId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère un tour par son ID
     */
    public function getTourById(int $tourId): ?array
    {
        if ($tourId <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("SELECT * FROM tours_election WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $tourId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Récupère un tour par élection et numéro de tour
     */
    public function getTourByNumero(int $electionId, int $numeroTour): ?array
    {
        $this->ensureInitialTour($electionId);
        $stmt = $this->pdo->prepare("SELECT * FROM tours_election WHERE election_id = :eid AND numero_tour = :num LIMIT 1");
        $stmt->execute([
            ':eid' => $electionId,
            ':num' => $numeroTour,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Récupère le tour courant ou le dernier tour d'une élection
     */
    public function getActiveOrLatestTour(int $electionId): ?array
    {
        $this->ensureInitialTour($electionId);

        // 1. Tour actuellement ouvert
        $stmt = $this->pdo->prepare("
            SELECT * FROM tours_election
            WHERE election_id = :eid AND statut = 'ouverte'
            ORDER BY numero_tour DESC LIMIT 1
        ");
        $stmt->execute([':eid' => $electionId]);
        $openTour = $stmt->fetch();
        if ($openTour) {
            return $openTour;
        }

        // 2. Tour correspondant à tour_courant de l'élection
        $stmtEl = $this->pdo->prepare("SELECT tour_courant FROM elections WHERE id = :eid LIMIT 1");
        $stmtEl->execute([':eid' => $electionId]);
        $tourCourantNum = (int)($stmtEl->fetchColumn() ?: 1);

        $tourCourant = $this->getTourByNumero($electionId, $tourCourantNum);
        if ($tourCourant) {
            return $tourCourant;
        }

        // 3. Dernier tour enregistré
        $stmtLast = $this->pdo->prepare("
            SELECT * FROM tours_election
            WHERE election_id = :eid
            ORDER BY numero_tour DESC, id DESC LIMIT 1
        ");
        $stmtLast->execute([':eid' => $electionId]);
        $last = $stmtLast->fetch();
        return $last ?: null;
    }

    /**
     * Récupère les candidats autorisés à participer à un tour donné (pour le bulletin de vote ou les résultats)
     */
    public function getCandidatesForTour(int $electionId, ?int $tourId = null): array
    {
        $tour = $tourId ? $this->getTourById($tourId) : $this->getActiveOrLatestTour($electionId);
        if (!$tour) {
            return [];
        }

        $tid = (int)$tour['id'];
        $num = (int)$tour['numero_tour'];

        if ($num === 1) {
            $this->syncTour1ValidatedCandidates($electionId, $tid);
        }

        $sql = "SELECT c.*, ct.statut_qualification, ct.nb_voix_obtenues, ct.pourcentage_obtenu, ct.tour_id
                FROM candidats_tour ct
                JOIN candidats c ON c.id = ct.candidat_id
                WHERE ct.tour_id = :tid AND c.statut = 'validee'
                ORDER BY c.nom ASC, c.prenoms ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tid' => $tid]);
        $rows = $stmt->fetchAll();

        // Fallback de sécurité pour le Tour 1 si candidats_tour était vide
        if (empty($rows) && $num === 1) {
            $stmtFallback = $this->pdo->prepare("
                SELECT * FROM candidats
                WHERE election_id = :eid AND statut = 'validee'
                ORDER BY nom ASC, prenoms ASC
            ");
            $stmtFallback->execute([':eid' => $electionId]);
            return $stmtFallback->fetchAll();
        }

        return $rows;
    }

    /**
     * Candidats validés pouvant être sélectionnés pour un tour manuel suivant.
     * Une décision « autre » liée au tour précédent autorise une exception motivée.
     */
    public function getManualTourCandidateOptions(int $electionId, int $previousTourId): array
    {
        $previousTour = $this->getTourById($previousTourId);
        if (!$previousTour || (int)$previousTour['election_id'] !== $electionId) {
            return [];
        }

        $isFirstTour = (int)$previousTour['numero_tour'] === 1;
        $sql = "
            SELECT c.id, c.nom, c.prenoms,
                   COALESCE(ct.statut_qualification, '') AS statut_qualification,
                   COALESCE(scores.nb_voix, 0) AS nb_voix,
                   CASE WHEN EXISTS (
                       SELECT 1
                       FROM decisions_bureau d
                       WHERE d.election_id = c.election_id
                         AND d.tour_id = :decision_tour_id
                         AND d.candidat_retenu_id = c.id
                         AND d.type_decision = 'autre'
                   ) THEN 1 ELSE 0 END AS decision_exception
            FROM candidats c
            LEFT JOIN candidats_tour ct ON ct.candidat_id = c.id AND ct.tour_id = :candidate_tour_id
            LEFT JOIN (
                SELECT candidat_id, COUNT(*) AS nb_voix
                FROM votes
                WHERE election_id = :score_election_id
                  AND (tour_id = :vote_tour_id OR (:is_first_tour = 1 AND tour_id IS NULL))
                GROUP BY candidat_id
            ) scores ON scores.candidat_id = c.id
            WHERE c.election_id = :candidate_election_id
              AND c.statut = 'validee'
            ORDER BY c.nom ASC, c.prenoms ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':decision_tour_id' => $previousTourId,
            ':candidate_tour_id' => $previousTourId,
            ':vote_tour_id' => $previousTourId,
            ':is_first_tour' => $isFirstTour ? 1 : 0,
            ':score_election_id' => $electionId,
            ':candidate_election_id' => $electionId,
        ]);
        $candidates = $stmt->fetchAll();

        $previousParticipants = array_values(array_filter(
            $candidates,
            static fn(array $candidate): bool => $candidate['statut_qualification'] !== ''
        ));
        $hasProcessedQualifications = false;
        $maxVoix = 0;
        $leaders = [];
        foreach ($previousParticipants as $candidate) {
            if ($candidate['statut_qualification'] !== 'participant') {
                $hasProcessedQualifications = true;
            }
            $voix = (int)$candidate['nb_voix'];
            if ($voix > $maxVoix) {
                $maxVoix = $voix;
                $leaders = [$candidate['id']];
            } elseif ($voix === $maxVoix) {
                $leaders[] = $candidate['id'];
            }
        }
        $unprocessedTieLeaders = !$hasProcessedQualifications && count($leaders) > 1
            ? array_fill_keys(array_map('intval', $leaders), true)
            : [];

        foreach ($candidates as &$candidate) {
            $candidate['est_qualifie'] = (int)(
                $candidate['statut_qualification'] === 'qualifie'
                || (
                    !$hasProcessedQualifications
                    && isset($unprocessedTieLeaders[(int)$candidate['id']])
                )
            );
            $candidate['decision_exception'] = (int)$candidate['decision_exception'];
        }
        unset($candidate);

        return $candidates;
    }

    /**
     * Vérifie qu'un candidat est bien autorisé à participer au tour donné
     */
    public function isCandidateInTour(int $tourId, int $candidatId): bool
    {
        $tour = $this->getTourById($tourId);
        if (!$tour) {
            return false;
        }
        if ((int)$tour['numero_tour'] === 1) {
            $this->syncTour1ValidatedCandidates((int)$tour['election_id'], $tourId);
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM candidats_tour ct
            JOIN candidats c ON c.id = ct.candidat_id
            WHERE ct.tour_id = :tid AND ct.candidat_id = :cid AND c.statut = 'validee'
        ");
        $stmt->execute([':tid' => $tourId, ':cid' => $candidatId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Met à jour les dates et le statut d'un tour et synchronise l'élection parente si c'est le tour courant
     */
    public function updateTour(
        int $tourId,
        string $dateDebutVote,
        string $dateFinVote,
        string $statut,
        ?array $candidatIds = null
    ): bool
    {
        $tour = $this->getTourById($tourId);
        if (
            !$tour
            || $tour['statut'] !== 'preparation'
            || !in_array($statut, ['preparation', 'ouverte'], true)
            || $dateFinVote <= $dateDebutVote
        ) {
            return false;
        }

        $candidateIds = null;
        if ($candidatIds !== null && (int)$tour['numero_tour'] > 1) {
            $previousTour = $this->getTourByNumero(
                (int)$tour['election_id'],
                (int)$tour['numero_tour'] - 1
            );
            if (!$previousTour || $previousTour['statut'] !== 'cloturee') {
                return false;
            }

            $candidateIds = array_values(array_unique(array_filter(
                array_map('intval', $candidatIds),
                static fn(int $id): bool => $id > 0
            )));
            if (empty($candidateIds)) {
                return false;
            }
            $optionsById = [];
            foreach ($this->getManualTourCandidateOptions(
                (int)$tour['election_id'],
                (int)$previousTour['id']
            ) as $option) {
                $optionsById[(int)$option['id']] = $option;
            }
            $selectedIds = array_fill_keys($candidateIds, true);
            foreach ($optionsById as $optionId => $option) {
                if (!empty($option['est_qualifie']) && !isset($selectedIds[$optionId])) {
                    return false;
                }
            }
            foreach ($candidateIds as $candidateId) {
                $option = $optionsById[$candidateId] ?? null;
                if (
                    !$option
                    || (empty($option['est_qualifie']) && empty($option['decision_exception']))
                ) {
                    return false;
                }
            }
        } elseif ($candidatIds !== null) {
            return false;
        }

        $ownTx = !$this->pdo->inTransaction();
        if ($ownTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $lockTour = $this->pdo->prepare('SELECT statut FROM tours_election WHERE id = :id LIMIT 1 FOR UPDATE');
            $lockTour->execute([':id' => $tourId]);
            if ($lockTour->fetchColumn() !== 'preparation') {
                if ($ownTx) {
                    $this->pdo->rollBack();
                }
                return false;
            }

            if ($candidateIds !== null) {
                $stmtVotes = $this->pdo->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM votes WHERE tour_id = :vote_tour_id) +
                        (SELECT COUNT(*) FROM participations WHERE tour_id = :participation_tour_id AND statut = 'a_vote')
                ");
                $stmtVotes->execute([
                    ':vote_tour_id' => $tourId,
                    ':participation_tour_id' => $tourId,
                ]);
                if ((int)$stmtVotes->fetchColumn() > 0) {
                    if ($ownTx) {
                        $this->pdo->rollBack();
                    }
                    return false;
                }
            }

            if ($statut === 'ouverte') {
                $candidateCount = $this->pdo->prepare("
                    SELECT COUNT(*)
                    FROM candidats_tour ct
                    JOIN candidats c ON c.id = ct.candidat_id
                    WHERE ct.tour_id = :tour_id AND c.statut = 'validee'
                ");
                $candidateCount->execute([':tour_id' => $tourId]);
                if ((int)$candidateCount->fetchColumn() === 0) {
                    if ($ownTx) {
                        $this->pdo->rollBack();
                    }
                    return false;
                }

                require_once __DIR__ . '/ElecteurTourService.php';
                $electorService = new ElecteurTourService($this->pdo);
                $electorService->syncElecteursForTour((int)$tour['election_id'], $tourId);
                if ($electorService->countAuthorizedForTour((int)$tour['election_id'], $tourId) === 0) {
                    if ($ownTx) {
                        $this->pdo->rollBack();
                    }
                    return false;
                }
            }

            $stmt = $this->pdo->prepare("
                UPDATE tours_election
                SET date_debut_vote = :debut,
                    date_fin_vote = :fin,
                    statut = :statut,
                    date_cloture_effective = NULL
                WHERE id = :id
            ");
            $ok = $stmt->execute([
                ':debut'   => $dateDebutVote,
                ':fin'     => $dateFinVote,
                ':statut'  => $statut,
                ':id'      => $tourId,
            ]);

            if ($candidateIds !== null) {
                $deleteCandidates = $this->pdo->prepare('DELETE FROM candidats_tour WHERE tour_id = :tour_id');
                $deleteCandidates->execute([':tour_id' => $tourId]);
                $insertCandidate = $this->pdo->prepare("
                    INSERT INTO candidats_tour (tour_id, election_id, candidat_id, statut_qualification)
                    VALUES (:tour_id, :election_id, :candidate_id, 'participant')
                ");
                foreach ($candidateIds as $candidateId) {
                    $insertCandidate->execute([
                        ':tour_id' => $tourId,
                        ':election_id' => (int)$tour['election_id'],
                        ':candidate_id' => $candidateId,
                    ]);
                }
            }

            // Synchroniser l'élection si ce tour est le dernier / tour courant.
            $electionId = (int)$tour['election_id'];
            $latest = $this->getActiveOrLatestTour($electionId);
            if ($latest && (int)$latest['id'] === $tourId) {
                $updEl = $this->pdo->prepare("
                    UPDATE elections
                    SET tour_courant = :num,
                        date_debut_vote = :debut_v,
                        date_fin_vote = :fin_v,
                        date_debut = :debut_d,
                        date_fin = :fin_d,
                        statut = :statut,
                        date_cloture_effective = NULL
                    WHERE id = :eid
                ");
                $updEl->execute([
                    ':num'     => (int)$tour['numero_tour'],
                    ':debut_v' => $dateDebutVote,
                    ':fin_v'   => $dateFinVote,
                    ':debut_d' => $dateDebutVote,
                    ':fin_d'   => $dateFinVote,
                    ':statut'  => $statut,
                    ':eid'     => $electionId,
                ]);
            }

            if ($ownTx) {
                $this->pdo->commit();
            }
            return $ok;
        } catch (Throwable $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur updateTour : ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crée un tour manuel après clôture du précédent, sans modifier ses résultats.
     */
    public function createManualTour(
        int $electionId,
        int $numeroTour,
        string $dateDebutVote,
        string $dateFinVote,
        array $candidatIds
    ): array {
        $candidateIds = array_values(array_unique(array_filter(
            array_map('intval', $candidatIds),
            static fn(int $id): bool => $id > 0
        )));

        if ($electionId <= 0 || $numeroTour <= 1 || empty($candidateIds)) {
            return ['success' => false, 'message' => 'Élection, numéro du tour et candidats valides sont obligatoires.'];
        }
        $timezone = new DateTimeZone('Africa/Abidjan');
        $dateDebut = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dateDebutVote, $timezone);
        $dateFin = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dateFinVote, $timezone);
        if (
            !$dateDebut
            || !$dateFin
            || $dateDebut->format('Y-m-d H:i:s') !== $dateDebutVote
            || $dateFin->format('Y-m-d H:i:s') !== $dateFinVote
        ) {
            return ['success' => false, 'message' => 'Veuillez saisir des dates valides pour l’ouverture et la clôture du tour.'];
        }
        if ($dateFin <= $dateDebut) {
            return ['success' => false, 'message' => 'La date et l’heure de clôture doivent être postérieures à celles de l’ouverture.'];
        }
        if ($dateDebut <= new DateTimeImmutable('now', $timezone)) {
            return ['success' => false, 'message' => 'La date et l’heure d’ouverture du nouveau tour doivent être dans le futur.'];
        }

        $ownTx = !$this->pdo->inTransaction();
        if ($ownTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmtElection = $this->pdo->prepare('SELECT * FROM elections WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmtElection->execute([':id' => $electionId]);
            $election = $stmtElection->fetch();
            if (!$election) {
                if ($ownTx) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'message' => 'Élection introuvable.'];
            }

            $maxTours = max(1, (int)($election['nombre_max_tours'] ?? 3));
            if ($numeroTour > $maxTours) {
                if ($ownTx) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'message' => "Le nombre maximal de tours configuré ({$maxTours}) serait dépassé."];
            }

            $stmtPrevious = $this->pdo->prepare("
                SELECT * FROM tours_election
                WHERE election_id = :eid AND numero_tour = :numero
                LIMIT 1 FOR UPDATE
            ");
            $stmtPrevious->execute([':eid' => $electionId, ':numero' => $numeroTour - 1]);
            $previousTour = $stmtPrevious->fetch();
            if (!$previousTour || $previousTour['statut'] !== 'cloturee') {
                if ($ownTx) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'message' => 'Le tour précédent doit exister et être clôturé avant de créer le suivant.'];
            }

            $stmtLastTour = $this->pdo->prepare('SELECT MAX(numero_tour) FROM tours_election WHERE election_id = :eid');
            $stmtLastTour->execute([':eid' => $electionId]);
            if ((int)$stmtLastTour->fetchColumn() !== $numeroTour - 1) {
                if ($ownTx) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'message' => 'Les tours doivent être créés dans l’ordre, immédiatement après le dernier tour existant.'];
            }

            if (
                ($election['statut'] ?? '') === 'cloturee'
                && empty($election['etat_egalite_persistante'])
                && !empty($election['vainqueur_candidat_id'])
            ) {
                if ($ownTx) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'message' => 'Un scrutin clôturé avec un vainqueur ne peut pas recevoir de tour supplémentaire.'];
            }
            if (!empty($election['resultats_certifies'])) {
                if ($ownTx) {
                    $this->pdo->rollBack();
                }
                return ['success' => false, 'message' => 'Un scrutin certifié ne peut pas recevoir de tour supplémentaire.'];
            }

            $optionsById = [];
            foreach ($this->getManualTourCandidateOptions($electionId, (int)$previousTour['id']) as $option) {
                $optionsById[(int)$option['id']] = $option;
            }
            $selectedIds = array_fill_keys($candidateIds, true);
            foreach ($optionsById as $optionId => $option) {
                if (!empty($option['est_qualifie']) && !isset($selectedIds[$optionId])) {
                    if ($ownTx) {
                        $this->pdo->rollBack();
                    }
                    return [
                        'success' => false,
                        'message' => 'Tous les candidats qualifiés automatiquement doivent être inclus dans le tour suivant.',
                    ];
                }
            }
            foreach ($candidateIds as $candidateId) {
                $candidate = $optionsById[$candidateId] ?? null;
                if (!$candidate) {
                    if ($ownTx) {
                        $this->pdo->rollBack();
                    }
                    return ['success' => false, 'message' => 'Un candidat sélectionné est introuvable, non validé ou rattaché à une autre élection.'];
                }
                if (
                    empty($candidate['est_qualifie'])
                    && empty($candidate['decision_exception'])
                ) {
                    if ($ownTx) {
                        $this->pdo->rollBack();
                    }
                    return [
                        'success' => false,
                        'message' => 'Un candidat non qualifié doit faire l’objet d’une décision « Autre décision statutaire » motivée, liée au tour précédent et le désignant.',
                    ];
                }
            }

            $libelle = $this->formatTourLabel($numeroTour);
            $stmtInsert = $this->pdo->prepare("
                INSERT INTO tours_election
                    (election_id, numero_tour, libelle, date_debut_vote, date_fin_vote, statut, date_creation)
                VALUES (:eid, :numero, :libelle, :debut, :fin, 'preparation', NOW())
            ");
            $stmtInsert->execute([
                ':eid' => $electionId,
                ':numero' => $numeroTour,
                ':libelle' => $libelle,
                ':debut' => $dateDebutVote,
                ':fin' => $dateFinVote,
            ]);
            $tourId = (int)$this->pdo->lastInsertId();

            $stmtInsertCandidate = $this->pdo->prepare("
                INSERT INTO candidats_tour (tour_id, election_id, candidat_id, statut_qualification)
                VALUES (:tour_id, :election_id, :candidate_id, 'participant')
            ");
            foreach ($candidateIds as $candidateId) {
                $stmtInsertCandidate->execute([
                    ':tour_id' => $tourId,
                    ':election_id' => $electionId,
                    ':candidate_id' => $candidateId,
                ]);
            }

            require_once __DIR__ . '/ElecteurTourService.php';
            (new ElecteurTourService($this->pdo))->syncElecteursForTour($electionId, $tourId);

            $stmtUpdateElection = $this->pdo->prepare("
                UPDATE elections
                SET tour_courant = :numero,
                    date_debut_vote = :debut_vote,
                    date_fin_vote = :fin_vote,
                    date_debut = :debut_election,
                    date_fin = :fin_election,
                    statut = 'preparation',
                    date_cloture_effective = NULL,
                    etat_egalite_persistante = 0,
                    vainqueur_candidat_id = NULL,
                    resultats_certifies = 0,
                    date_certification = NULL
                WHERE id = :eid
            ");
            $stmtUpdateElection->execute([
                ':numero' => $numeroTour,
                ':debut_vote' => $dateDebutVote,
                ':fin_vote' => $dateFinVote,
                ':debut_election' => $dateDebutVote,
                ':fin_election' => $dateFinVote,
                ':eid' => $electionId,
            ]);

            if ($ownTx) {
                $this->pdo->commit();
            }

            return [
                'success' => true,
                'tour_id' => $tourId,
                'message' => "Le {$libelle} a été créé en préparation. Les résultats des tours précédents sont conservés.",
            ];
        } catch (PDOException $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur createManualTour : ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur lors de la création du tour manuel.'];
        } catch (Throwable $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur createManualTour : ' . $e->getMessage());
            return ['success' => false, 'message' => 'Une erreur inattendue a empêché la création du tour.'];
        }
    }
}
