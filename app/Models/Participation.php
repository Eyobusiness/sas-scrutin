<?php
/**
 * Modèle Participation (Émargement nominatif par tour de scrutin)
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/../Services/TourElectionService.php';

class Participation extends BaseModel
{
    /**
     * Vérifie si un membre a déjà voté pour le tour donné (ou le tour actif de l'élection)
     * Le fait d'avoir voté au 1er tour n'empêche pas de voter au 2e tour (Section 6).
     */
    public function hasVoted(int $electionId, int $membreId, ?int $tourId = null): bool
    {
        $tourService = new TourElectionService($this->pdo);
        $tour = $tourId ? $tourService->getTourById($tourId) : $tourService->getActiveOrLatestTour($electionId);

        if (!$tour) {
            $sql = "SELECT COUNT(*) FROM participations 
                    WHERE election_id = :election_id AND membre_id = :membre_id AND statut = 'a_vote'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':election_id' => $electionId,
                ':membre_id'   => $membreId
            ]);
            return (int)$stmt->fetchColumn() > 0;
        }

        $tid = (int)$tour['id'];
        $isT1 = ((int)$tour['numero_tour'] === 1) ? 1 : 0;

        $sql = "SELECT COUNT(*) FROM participations 
                WHERE election_id = :election_id
                  AND membre_id = :membre_id
                  AND statut = 'a_vote'
                  AND (tour_id = :tid OR (:is_t1 = 1 AND tour_id IS NULL))";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':election_id' => $electionId,
            ':membre_id'   => $membreId,
            ':tid'         => $tid,
            ':is_t1'       => $isT1,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Liste des émargements / participations pour une élection (et optionnellement un tour)
     */
    public function getByElection(int $electionId, array $filters = []): array
    {
        $sql = "SELECT p.*, m.nom AS membre_nom, m.prenoms AS membre_prenoms, 
                       m.telephone AS membre_telephone, m.responsabilite_sas,
                       e.titre AS election_titre,
                       COALESCE(t.numero_tour, 1) AS numero_tour,
                       COALESCE(t.libelle, '1er Tour') AS tour_libelle
                FROM participations p
                JOIN membres m ON p.membre_id = m.id
                JOIN elections e ON p.election_id = e.id
                LEFT JOIN tours_election t ON p.tour_id = t.id
                WHERE p.election_id = :election_id";
        $params = [':election_id' => $electionId];

        if (!empty($filters['tour_id'])) {
            $sql .= " AND p.tour_id = :tour_id";
            $params[':tour_id'] = (int)$filters['tour_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (m.nom LIKE :search OR m.prenoms LIKE :search OR m.telephone LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $sql .= " ORDER BY p.date_vote DESC, p.id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Compte le nombre de votants pour une élection (ou un tour spécifique si fourni)
     */
    public function countByElection(int $electionId, ?int $tourId = null): int
    {
        if ($tourId !== null && $tourId > 0) {
            $sql = "SELECT COUNT(*) FROM participations 
                    WHERE election_id = :election_id AND tour_id = :tid AND statut = 'a_vote'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':election_id' => $electionId, ':tid' => $tourId]);
            return (int)$stmt->fetchColumn();
        }

        $sql = "SELECT COUNT(*) FROM participations 
                WHERE election_id = :election_id AND statut = 'a_vote'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':election_id' => $electionId]);
        return (int)$stmt->fetchColumn();
    }
}
