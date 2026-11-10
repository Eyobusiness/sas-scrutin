<?php
/**
 * Modèle Vote (Bulletins secrets & Transaction atomique de vote par tour)
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';
require_once __DIR__ . '/Election.php';
require_once __DIR__ . '/Candidat.php';
require_once __DIR__ . '/Membre.php';
require_once __DIR__ . '/../Services/TourElectionService.php';
require_once __DIR__ . '/../Services/ElecteurTourService.php';

class Vote extends BaseModel
{
    /**
     * Enregistre un vote de manière strictement transactionnelle et anonyme pour le tour actif
     *
     * @param int $electionId ID de l'élection
     * @param int $candidatId ID du candidat choisi
     * @param int $membreId ID du membre votant (servira uniquement pour l'émargement dans `participations`)
     * @param int|null $tourId ID du tour (optionnel, déterminé automatiquement si omis)
     * @return array [bool $success, string $message]
     */
    public function castVote(int $electionId, int $candidatId, int $membreId, ?int $tourId = null): array
    {
        $tourService = new TourElectionService($this->pdo);
        $electeurTourService = new ElecteurTourService($this->pdo);

        // S'assurer que le Tour 1 existe avant d'ouvrir la transaction
        $tourService->ensureInitialTour($electionId);

        // 1. Démarrer la transaction SQL
        $this->pdo->beginTransaction();

        try {
            // 2. Vérification de l'élection (avec verrou pour cohérence)
            $stmt = $this->pdo->prepare("SELECT * FROM elections WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $electionId]);
            $election = $stmt->fetch();

            if (!$election) {
                $this->pdo->rollBack();
                return [false, "Élection introuvable."];
            }

            $electionModel = new Election($this->pdo);
            $election = $electionModel->normalizeRow($election);
            $now = (new DateTimeImmutable('now', new DateTimeZone('Africa/Abidjan')))->format('Y-m-d H:i:s');
            $dateFinVote = $election['date_fin_vote'] ?? ($election['date_fin'] ?? null);

            if (($election['statut'] ?? '') !== 'cloturee' && !empty($dateFinVote) && $now >= $dateFinVote) {
                $electionModel->markAutoClosed((int)$election['id'], $dateFinVote);
                $this->pdo->commit();
                return [false, 'L\'heure de clôture du vote est atteinte (fin le ' . date('d/m/Y à H:i', strtotime($dateFinVote)) . '). Le scrutin est désormais clôturé.'];
            }

            $canVoteStatus = $electionModel->checkCanVote($election, $now);
            if (!$canVoteStatus['can_vote']) {
                $this->pdo->rollBack();
                return [false, $canVoteStatus['message']];
            }

            // Déterminer le tour actif de l'élection
            $tour = $tourId ? $tourService->getTourById($tourId) : $tourService->getActiveOrLatestTour($electionId);
            if (!$tour || (int)$tour['election_id'] !== $electionId) {
                $this->pdo->rollBack();
                return [false, "Tour de scrutin introuvable pour cette élection."];
            }
            $activeTourId = (int)$tour['id'];
            $numeroTour = (int)$tour['numero_tour'];
            $now = (new DateTimeImmutable('now', new DateTimeZone('Africa/Abidjan')))->format('Y-m-d H:i:s');
            $tourStart = $tour['date_debut_vote'] ?? null;
            $tourEnd = $tour['date_fin_vote'] ?? null;
            if (
                ($tour['statut'] ?? '') !== 'ouverte'
                || empty($tourStart)
                || empty($tourEnd)
                || $now < $tourStart
                || $now >= $tourEnd
            ) {
                $this->pdo->rollBack();
                return [false, 'Ce tour n’est pas actuellement ouvert au vote.'];
            }

            // 3. Vérification du candidat : doit exister, appartenir à cette élection, avoir le statut 'validee'
            // ET être qualifié/participant pour ce tour !
            $stmt = $this->pdo->prepare("SELECT * FROM candidats WHERE id = :id AND election_id = :election_id LIMIT 1");
            $stmt->execute([
                ':id'          => $candidatId,
                ':election_id' => $electionId
            ]);
            $candidat = $stmt->fetch();

            if (!$candidat) {
                $this->pdo->rollBack();
                return [false, "Candidat non trouvé pour cette élection."];
            }

            if ($candidat['statut'] !== 'validee') {
                $this->pdo->rollBack();
                return [false, "Ce candidat n'est pas validé pour ce scrutin."];
            }

            if ($numeroTour > 1 && !$tourService->isCandidateInTour($activeTourId, $candidatId)) {
                $this->pdo->rollBack();
                return [false, "Ce candidat n'est pas qualifié pour ce tour de scrutin."];
            }

            // 4. Vérification du membre et de son autorisation sur ce tour
            $stmt = $this->pdo->prepare("SELECT * FROM membres WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $membreId]);
            $membre = $stmt->fetch();

            if (!$membre) {
                $this->pdo->rollBack();
                return [false, "Membre introuvable. Veuillez contacter le bureau."];
            }

            $authCheck = $electeurTourService->checkMemberAuthorizationForTour($electionId, $activeTourId, $membre);
            if (!$authCheck['authorized']) {
                $this->pdo->rollBack();
                return [false, $authCheck['message']];
            }

            // 5. Vérification des doublons de vote sur CE tour uniquement (Participation préalable au tour)
            $isT1 = ($numeroTour === 1) ? 1 : 0;
            $stmt = $this->pdo->prepare("
                SELECT id FROM participations
                WHERE election_id = :election_id
                  AND membre_id = :membre_id
                  AND (tour_id = :tid OR (:is_t1 = 1 AND tour_id IS NULL))
                FOR UPDATE
            ");
            $stmt->execute([
                ':election_id' => $electionId,
                ':membre_id'   => $membreId,
                ':tid'         => $activeTourId,
                ':is_t1'       => $isT1,
            ]);
            if ($stmt->fetch()) {
                $this->pdo->rollBack();
                return [false, "Vous avez déjà voté. Merci de patienter pour les résultats."];
            }

            $currentDatetime = $now;

            // 6. Insérer dans la table votes (ANONYME : AUCUN identifiant du membre n'est stocké ici)
            $stmt = $this->pdo->prepare("
                INSERT INTO votes (election_id, tour_id, candidat_id, date_vote)
                VALUES (:election_id, :tour_id, :candidat_id, :date_vote)
            ");
            $stmt->execute([
                ':election_id' => $electionId,
                ':tour_id'     => $activeTourId,
                ':candidat_id' => $candidatId,
                ':date_vote'   => $currentDatetime
            ]);

            // 7. Insérer dans la table participations (Émargement nominatif par tour pour empêcher le double vote)
            $stmt = $this->pdo->prepare("
                INSERT INTO participations (election_id, tour_id, membre_id, statut, date_vote)
                VALUES (:election_id, :tour_id, :membre_id, 'a_vote', :date_vote)
            ");
            $stmt->execute([
                ':election_id' => $electionId,
                ':tour_id'     => $activeTourId,
                ':membre_id'   => $membreId,
                ':date_vote'   => $currentDatetime
            ]);

            // 8. Valider la transaction
            $this->pdo->commit();

            return [true, "Félicitations, votre voix a bien été prise en compte !"];

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            // Gestion de violation de contrainte d'unicité (code 23000)
            if ($e->getCode() === '23000') {
                return [false, "Vous avez déjà voté. Merci de patienter pour les résultats."];
            }

            error_log("Erreur critique lors de l'enregistrement du vote : " . $e->getMessage());
            return [false, "Une erreur interne est survenue lors de l'enregistrement de votre vote. Veuillez réessayer."];
        }
    }

    /**
     * Compte le nombre total de votes enregistrés pour une élection (ou un tour spécifique)
     */
    public function countByElection(int $electionId, ?int $tourId = null): int
    {
        if ($tourId !== null && $tourId > 0) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM votes WHERE election_id = :election_id AND tour_id = :tid");
            $stmt->execute([':election_id' => $electionId, ':tid' => $tourId]);
            return (int)$stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM votes WHERE election_id = :election_id");
        $stmt->execute([':election_id' => $electionId]);
        return (int)$stmt->fetchColumn();
    }
}
