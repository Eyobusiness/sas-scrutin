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
    public function updateTour(int $tourId, string $dateDebutVote, string $dateFinVote, string $statut): bool
    {
        $tour = $this->getTourById($tourId);
        if (!$tour) {
            return false;
        }

        $clotureEff = ($statut === 'cloturee')
            ? ($tour['date_cloture_effective'] ?? date('Y-m-d H:i:s'))
            : null;

        $stmt = $this->pdo->prepare("
            UPDATE tours_election
            SET date_debut_vote = :debut,
                date_fin_vote = :fin,
                statut = :statut,
                date_cloture_effective = :cloture
            WHERE id = :id
        ");
        $ok = $stmt->execute([
            ':debut'   => $dateDebutVote,
            ':fin'     => $dateFinVote,
            ':statut'  => $statut,
            ':cloture' => $clotureEff,
            ':id'      => $tourId,
        ]);

        // Synchroniser l'élection si ce tour est le dernier / tour courant
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
                    date_cloture_effective = :cloture
                WHERE id = :eid
            ");
            $updEl->execute([
                ':num'     => (int)$tour['numero_tour'],
                ':debut_v' => $dateDebutVote,
                ':fin_v'   => $dateFinVote,
                ':debut_d' => $dateDebutVote,
                ':fin_d'   => $dateFinVote,
                ':statut'  => $statut,
                ':cloture' => $clotureEff,
                ':eid'     => $electionId,
            ]);
        }

        return $ok;
    }
}
