<?php
/**
 * Service de gestion des listes d'électeurs par élection (`electeurs_election`),
 * par tour (`electeurs_tour`), de la gestion exceptionnelle par le bureau avec
 * traçabilité obligatoire (`journal_modifications_electeurs`) et du diagnostic
 * précis des motifs de non-autorisation au vote (Sections 5 & 7).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/TourElectionService.php';

class ElecteurTourService
{
    private PDO $pdo;
    private TourElectionService $tourService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->tourService = new TourElectionService($this->pdo);
    }

    /**
     * Vérifie si la liste des électeurs d'un tour est verrouillée.
     * Règle Section 5 :
     * - Les modifications sont autorisées uniquement avant l'ouverture du tour concerné.
     * - Dès que le tour commence (statut = 'ouverte' et now >= date_debut_vote) ou est clôturé, sa liste est verrouillée.
     * - Ne jamais modifier rétroactivement la liste des électeurs d'un tour clôturé.
     */
    public function isTourVoterListLocked(array|int|null $tour, ?string $now = null): bool
    {
        if (is_int($tour)) {
            $tour = $this->tourService->getTourById($tour);
        }
        if (empty($tour) || !is_array($tour)) {
            return true;
        }

        $statut = $tour['statut'] ?? 'preparation';

        if ($statut === 'cloturee' || $statut === 'ouverte') {
            return true;
        }

        return false;
    }

    /**
     * Initialise ou synchronise la liste des électeurs de l'élection et du tour
     * à partir des membres actifs et éligibles (sans écraser les décisions manuelles du bureau)
     */
    public function syncElecteursForTour(int $electionId, int $tourId): void
    {
        if ($electionId <= 0 || $tourId <= 0) {
            return;
        }

        // 1. Alimenter electeurs_election pour les membres éligibles non encore présents
        $sqlEE = "INSERT IGNORE INTO electeurs_election (election_id, membre_id, autorise, motif)
                  SELECT :eid, m.id, 1, 'Membre éligible sur la liste électorale générale'
                  FROM membres m
                  WHERE m.eligibilite_a_voter = 'oui' AND m.statut = 'actif'";
        $stmtEE = $this->pdo->prepare($sqlEE);
        $stmtEE->execute([':eid' => $electionId]);

        // 2. Alimenter electeurs_tour à partir de electeurs_election (sans écraser les lignes déjà présentes sur ce tour)
        $sqlET = "INSERT IGNORE INTO electeurs_tour (tour_id, election_id, membre_id, autorise, motif, modifie_par)
                  SELECT :tid, ee.election_id, ee.membre_id, ee.autorise, ee.motif, ee.ajoute_par
                  FROM electeurs_election ee
                  JOIN membres m ON m.id = ee.membre_id
                  WHERE ee.election_id = :eid AND m.statut = 'actif'";
        $stmtET = $this->pdo->prepare($sqlET);
        $stmtET->execute([
            ':tid' => $tourId,
            ':eid' => $electionId,
        ]);
    }

    /**
     * Compte le nombre d'électeurs autorisés pour un tour donné
     */
    public function countAuthorizedForTour(int $electionId, int $tourId): int
    {
        $this->syncElecteursForTour($electionId, $tourId);

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM electeurs_tour et
            JOIN membres m ON m.id = et.membre_id
            WHERE et.tour_id = :tid AND et.autorise = 1 AND m.statut = 'actif'
        ");
        $stmt->execute([':tid' => $tourId]);
        $count = (int)$stmt->fetchColumn();

        if ($count === 0) {
            $stmtFallback = $this->pdo->query("SELECT COUNT(*) FROM membres WHERE eligibilite_a_voter = 'oui' AND statut = 'actif'");
            return (int)$stmtFallback->fetchColumn();
        }

        return $count;
    }

    /**
     * Gestion exceptionnelle d'un électeur par le bureau (ajout, retrait, correction)
     * Enregistre obligatoirement l'administrateur, la date/heure, le type, le motif, l'élection et le tour.
     */
    public function modifyElecteurTour(
        int $electionId,
        int $tourId,
        int $membreId,
        string $typeModification,
        bool $autorise,
        string $motif,
        int $adminId,
        ?string $now = null
    ): array {
        if (!in_array($typeModification, ['ajout', 'retrait', 'correction'], true)) {
            return [
                'success' => false,
                'message' => 'Type de modification d\'électeur invalide.',
            ];
        }

        $motifClean = trim($motif);
        if ($motifClean === '') {
            return [
                'success' => false,
                'message' => 'Le motif de la modification exceptionnelle est obligatoire.',
            ];
        }

        $tour = $this->tourService->getTourById($tourId);
        if (!$tour || (int)$tour['election_id'] !== $electionId) {
            return [
                'success' => false,
                'message' => 'Tour de scrutin introuvable pour cette élection.',
            ];
        }

        if ($this->isTourVoterListLocked($tour, $now)) {
            return [
                'success' => false,
                'message' => 'La liste des électeurs de ce tour est verrouillée (le tour a déjà commencé ou est clôturé). Aucune modification rétroactive n\'est autorisée.',
            ];
        }

        $stmtMembre = $this->pdo->prepare("SELECT * FROM membres WHERE id = :id LIMIT 1");
        $stmtMembre->execute([':id' => $membreId]);
        $membre = $stmtMembre->fetch();
        if (!$membre) {
            return [
                'success' => false,
                'message' => 'Membre introuvable.',
            ];
        }

        $autoriseInt = ($typeModification === 'retrait') ? 0 : (($typeModification === 'ajout') ? 1 : ($autorise ? 1 : 0));
        $nowStr = $now ?? date('Y-m-d H:i:s');

        $ownTx = !$this->pdo->inTransaction();
        if ($ownTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Mise à jour / insertion dans electeurs_election (liste de référence de l'élection)
            $sqlEE = "INSERT INTO electeurs_election (election_id, membre_id, autorise, motif, ajoute_par, date_creation)
                      VALUES (:eid, :mid, :aut, :motif, :aid, :dt)
                      ON DUPLICATE KEY UPDATE
                          autorise = VALUES(autorise),
                          motif = VALUES(motif),
                          ajoute_par = VALUES(ajoute_par),
                          mis_a_jour_le = VALUES(date_creation)";
            $stmtEE = $this->pdo->prepare($sqlEE);
            $stmtEE->execute([
                ':eid'   => $electionId,
                ':mid'   => $membreId,
                ':aut'   => $autoriseInt,
                ':motif' => $motifClean,
                ':aid'   => $adminId > 0 ? $adminId : null,
                ':dt'    => $nowStr,
            ]);

            // 2. Mise à jour / insertion dans electeurs_tour (liste du tour concerné)
            $sqlET = "INSERT INTO electeurs_tour (tour_id, election_id, membre_id, autorise, motif, modifie_par, date_creation)
                      VALUES (:tid, :eid, :mid, :aut, :motif, :aid, :dt)
                      ON DUPLICATE KEY UPDATE
                          autorise = VALUES(autorise),
                          motif = VALUES(motif),
                          modifie_par = VALUES(modifie_par),
                          mis_a_jour_le = VALUES(date_creation)";
            $stmtET = $this->pdo->prepare($sqlET);
            $stmtET->execute([
                ':tid'   => $tourId,
                ':eid'   => $electionId,
                ':mid'   => $membreId,
                ':aut'   => $autoriseInt,
                ':motif' => $motifClean,
                ':aid'   => $adminId > 0 ? $adminId : null,
                ':dt'    => $nowStr,
            ]);

            // 3. Enregistrement obligatoire dans le journal d'audit
            $sqlLog = "INSERT INTO journal_modifications_electeurs (
                           election_id, tour_id, membre_id, admin_id, type_modification, motif, date_modification
                       ) VALUES (
                           :eid, :tid, :mid, :aid, :type, :motif, :dt
                       )";
            $stmtLog = $this->pdo->prepare($sqlLog);
            $stmtLog->execute([
                ':eid'   => $electionId,
                ':tid'   => $tourId,
                ':mid'   => $membreId,
                ':aid'   => $adminId > 0 ? $adminId : null,
                ':type'  => $typeModification,
                ':motif' => $motifClean,
                ':dt'    => $nowStr,
            ]);

            if ($ownTx) {
                $this->pdo->commit();
            }

            $actionLabel = match ($typeModification) {
                'ajout'      => 'ajouté à la liste des électeurs du tour',
                'retrait'    => 'retiré de la liste des électeurs du tour',
                'correction' => ($autoriseInt === 1 ? 'autorisé sur la liste du tour (correction)' : 'suspendu de la liste du tour (correction)'),
            };

            return [
                'success' => true,
                'message' => "L'électeur {$membre['prenoms']} {$membre['nom']} a été {$actionLabel}.",
            ];
        } catch (Throwable $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur modifyElecteurTour : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la liste des électeurs du tour.',
            ];
        }
    }

    /**
     * Formate proprement un ou plusieurs motifs enregistrés en base de données
     * pour le message de non-autorisation au vote (Section 7).
     */
    public function formatNonEligibilityMessage(array $motifsList): string
    {
        $cleaned = [];
        foreach ($motifsList as $m) {
            $parts = explode('|', (string)$m);
            foreach ($parts as $p) {
                $p = trim($p);
                $p = rtrim($p, '.');
                if ($p !== '' && !in_array($p, $cleaned, true)) {
                    $cleaned[] = $p;
                }
            }
        }

        if (empty($cleaned)) {
            $cleaned[] = 'Membre non éligible';
        }

        $joined = implode(' ; ', $cleaned);
        return "Vous n'êtes pas autorisé(e) à participer à ce scrutin parce que : {$joined}.";
    }

    /**
     * Vérifie si un membre est autorisé à voter pour le tour donné et retourne,
     * en cas de refus, le message explicatif exact basé sur les motifs enregistrés en base (Section 7).
     */
    public function checkMemberAuthorizationForTour(int $electionId, int $tourId, array $membre): array
    {
        $membreId = (int)($membre['id'] ?? 0);
        $statutMembre = $membre['statut'] ?? 'actif';
        $eligibiliteGenerale = $membre['eligibilite_a_voter'] ?? 'non';
        $motifDbMembre = trim((string)($membre['motif_non_eligibilite'] ?? ''));

        // 1. Vérifier d'abord si le membre figure dans electeurs_tour pour ce tour
        $stmtET = $this->pdo->prepare("SELECT * FROM electeurs_tour WHERE tour_id = :tid AND membre_id = :mid LIMIT 1");
        $stmtET->execute([
            ':tid' => $tourId,
            ':mid' => $membreId,
        ]);
        $etRow = $stmtET->fetch();

        // Cas A : Une décision explicite de retrait (autorise = 0) existe sur electeurs_tour
        if ($etRow && (int)$etRow['autorise'] === 0) {
            $motifTour = trim((string)($etRow['motif'] ?? ''));
            if ($motifTour === '') {
                $motifTour = 'Autorisation retirée par le bureau';
            }
            $motifs = [$motifTour];
            if ($eligibiliteGenerale !== 'oui' && $motifDbMembre !== '' && $motifDbMembre !== $motifTour) {
                $motifs[] = $motifDbMembre;
            }
            return [
                'authorized' => false,
                'motifs'     => $motifs,
                'message'    => $this->formatNonEligibilityMessage($motifs),
            ];
        }

        // Cas B : Compte membre désactivé
        if ($statutMembre !== 'actif') {
            $motifs = ['Compte membre inactif'];
            if ($motifDbMembre !== '') {
                $motifs[] = $motifDbMembre;
            }
            return [
                'authorized' => false,
                'motifs'     => $motifs,
                'message'    => $this->formatNonEligibilityMessage($motifs),
            ];
        }

        // Cas C : Le membre a été explicitement autorisé sur ce tour par le bureau (autorise = 1 dans electeurs_tour)
        // ET soit il est éligible, soit il a fait l'objet d'un ajout/correction exceptionnel par un administrateur
        if ($etRow && (int)$etRow['autorise'] === 1) {
            if ($eligibiliteGenerale === 'oui' || !empty($etRow['modifie_par'])) {
                return [
                    'authorized' => true,
                    'motifs'     => [],
                    'message'    => 'Autorisé à voter pour ce tour.',
                ];
            }
        }

        // Cas D : Le membre n'est pas éligible (eligibilite_a_voter = 'non')
        if ($eligibiliteGenerale !== 'oui') {
            $motifs = [];
            if ($motifDbMembre !== '') {
                $motifs[] = $motifDbMembre;
            } else {
                $motifs[] = 'Membre non éligible';
            }
            return [
                'authorized' => false,
                'motifs'     => $motifs,
                'message'    => $this->formatNonEligibilityMessage($motifs),
            ];
        }

        // Cas E : Le membre est éligible et actif -> s'assurer qu'il est rattaché à electeurs_tour
        if (!$etRow) {
            $ins = $this->pdo->prepare("
                INSERT IGNORE INTO electeurs_tour (tour_id, election_id, membre_id, autorise, motif)
                VALUES (:tid, :eid, :mid, 1, 'Membre éligible autorisé au tour')
            ");
            $ins->execute([
                ':tid' => $tourId,
                ':eid' => $electionId,
                ':mid' => $membreId,
            ]);
        }

        return [
            'authorized' => true,
            'motifs'     => [],
            'message'    => 'Autorisé à voter pour ce tour.',
        ];
    }

    /**
     * Alias simplifié pour modifier un électeur sur un tour (déduit $autorise depuis $typeModification)
     */
    public function modifierElecteurTour(
        int $electionId,
        int $tourId,
        int $membreId,
        string $typeModification,
        string $motif,
        int $adminId,
        ?bool $autoriseOverride = null,
        ?string $now = null
    ): array {
        $autorise = $autoriseOverride ?? ($typeModification !== 'retrait');
        return $this->modifyElecteurTour($electionId, $tourId, $membreId, $typeModification, $autorise, $motif, $adminId, $now);
    }

    /**
     * Liste complète des électeurs d'un tour (avec informations du membre et état de vote au tour)
     */
    public function getElecteursByTour(int $electionId, int $tourId): array
    {
        $this->syncElecteursForTour($electionId, $tourId);

        $sql = "SELECT et.*,
                       et.autorise AS autorise_a_voter,
                       CASE WHEN et.modifie_par IS NOT NULL THEN 'Bureau (exceptionnel)' ELSE 'Liste électorale' END AS source_autorisation,
                       et.motif AS motif_modification,
                       m.nom, m.prenoms, m.telephone, m.date_naissance, m.anciennete,
                       m.eligibilite_a_voter, m.motif_non_eligibilite, m.statut AS membre_statut,
                       a.nom AS admin_nom, a.prenoms AS admin_prenoms,
                       (SELECT COUNT(*) FROM participations p WHERE p.tour_id = et.tour_id AND p.membre_id = et.membre_id AND p.statut = 'a_vote') AS a_vote_tour,
                       (SELECT COUNT(*) FROM participations p WHERE p.tour_id = et.tour_id AND p.membre_id = et.membre_id AND p.statut = 'a_vote') AS a_vote_ce_tour
                FROM electeurs_tour et
                JOIN membres m ON m.id = et.membre_id
                LEFT JOIN administrateurs a ON a.id = et.modifie_par
                WHERE et.tour_id = :tid
                ORDER BY et.autorise DESC, m.nom ASC, m.prenoms ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tid' => $tourId]);
        return $stmt->fetchAll();
    }

    /**
     * Alias de getJournalByElection
     */
    public function getJournalModifications(int $electionId, ?int $tourId = null): array
    {
        return $this->getJournalByElection($electionId, $tourId);
    }

    /**
     * Journal complet des modifications exceptionnelles d'électeurs pour une élection (ou un tour)
     */
    public function getJournalByElection(int $electionId, ?int $tourId = null): array
    {
        $sql = "SELECT j.*,
                       m.nom AS membre_nom, m.prenoms AS membre_prenoms, m.telephone AS membre_telephone,
                       a.nom AS admin_nom, a.prenoms AS admin_prenoms,
                       t.numero_tour, t.libelle AS tour_libelle
                FROM journal_modifications_electeurs j
                JOIN membres m ON m.id = j.membre_id
                LEFT JOIN administrateurs a ON a.id = j.admin_id
                LEFT JOIN tours_election t ON t.id = j.tour_id
                WHERE j.election_id = :eid"
                . ($tourId !== null ? " AND j.tour_id = :tid" : "")
                . " ORDER BY j.date_modification DESC, j.id DESC";
        $params = [':eid' => $electionId];
        if ($tourId !== null) {
            $params[':tid'] = $tourId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
