<?php
/**
 * Service de qualification automatique des candidats entre les tours de scrutin,
 * de gestion des égalités, de préparation des tours suivants et d'enregistrement
 * des décisions du bureau en cas d'égalité persistante (Section 4 & 12).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/TourElectionService.php';
require_once __DIR__ . '/ElecteurTourService.php';

class QualificationService
{
    private PDO $pdo;
    private TourElectionService $tourService;
    private ElecteurTourService $electeurTourService;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->tourService = new TourElectionService($this->pdo);
        $this->electeurTourService = new ElecteurTourService($this->pdo);
    }

    /**
     * Calcule les scores d'un tour, clôture le tour et applique les règles de qualification (Section 4) :
     * 1. Calculer le nombre de voix obtenu par chaque candidat du tour.
     * 2. Identifier le meilleur score.
     * 3. Si un seul candidat est en tête (maxVoix > 0) : il est vainqueur, l'élection est clôturée (pas de nouveau tour).
     * 4. S'il y a égalité en tête (au moins 2 candidats au meilleur score) :
     *    - Si numero_tour < nombre_max_tours : qualifier automatiquement TOUS les candidats ayant ce meilleur score,
     *      écarter les candidats ayant un score inférieur, et préparer le tour suivant avec les candidats qualifiés.
     *    - Si numero_tour >= nombre_max_tours : ne pas désigner automatiquement un vainqueur, afficher l'état
     *      « Égalité persistante » et exiger une décision du bureau.
     */
    public function cloturerTourEtQualifier(
        int $electionId,
        ?int $tourId = null,
        ?string $nextTourDebut = null,
        ?string $nextTourFin = null,
        ?string $clotureDate = null
    ): array {
        $this->tourService->ensureInitialTour($electionId);
        $tour = $tourId
            ? $this->tourService->getTourById($tourId)
            : $this->tourService->getActiveOrLatestTour($electionId);

        if (!$tour || (int)$tour['election_id'] !== $electionId) {
            return [
                'success' => false,
                'status'  => 'error',
                'message' => 'Tour de scrutin introuvable pour cette élection.',
            ];
        }

        $tid = (int)$tour['id'];
        $numeroTour = (int)$tour['numero_tour'];

        $stmtEl = $this->pdo->prepare("SELECT * FROM elections WHERE id = :id LIMIT 1");
        $stmtEl->execute([':id' => $electionId]);
        $election = $stmtEl->fetch();
        if (!$election) {
            return [
                'success' => false,
                'status'  => 'error',
                'message' => 'Élection introuvable.',
            ];
        }

        $nombreMaxTours = max(1, (int)($election['nombre_max_tours'] ?? 3));
        $nowStr = $clotureDate ?? date('Y-m-d H:i:s');

        // S'assurer que candidats_tour est initialisé pour le Tour 1
        if ($numeroTour === 1) {
            $this->tourService->syncTour1ValidatedCandidates($electionId, $tid);
        }

        $ownTx = !$this->pdo->inTransaction();
        if ($ownTx) {
            $this->pdo->beginTransaction();
        }

        try {
            // 1. Récupérer les candidats participants à ce tour et leur nombre de voix sur ce tour
            $sqlScores = "
                SELECT c.id, c.nom, c.prenoms, c.photo,
                       COUNT(v.id) AS nb_voix
                FROM candidats_tour ct
                JOIN candidats c ON c.id = ct.candidat_id
                LEFT JOIN votes v ON v.candidat_id = c.id
                    AND v.election_id = :eid
                    AND (v.tour_id = :tid OR (:is_t1 = 1 AND v.tour_id IS NULL))
                WHERE ct.tour_id = :tid2 AND c.statut = 'validee'
                GROUP BY c.id
                ORDER BY nb_voix DESC, c.nom ASC, c.prenoms ASC
            ";
            $stmtScores = $this->pdo->prepare($sqlScores);
            $stmtScores->execute([
                ':eid'   => $electionId,
                ':tid'   => $tid,
                ':is_t1' => ($numeroTour === 1) ? 1 : 0,
                ':tid2'  => $tid,
            ]);
            $scores = $stmtScores->fetchAll();

            $totalBulletins = 0;
            $maxVoix = 0;
            foreach ($scores as $s) {
                $v = (int)$s['nb_voix'];
                $totalBulletins += $v;
                if ($v > $maxVoix) {
                    $maxVoix = $v;
                }
            }

            // Identifier les candidats ayant obtenu le meilleur score
            $leaders = [];
            $elimines = [];
            foreach ($scores as $s) {
                $v = (int)$s['nb_voix'];
                if ($v === $maxVoix && count($scores) > 0) {
                    $leaders[] = $s;
                } else {
                    $elimines[] = $s;
                }
            }

            $isSingleWinner = (count($leaders) === 1 && $maxVoix > 0) || (count($scores) === 1);
            $isEgalite = !$isSingleWinner && count($leaders) >= 2;

            // 2. Mettre à jour les scores figés dans candidats_tour pour ce tour
            $updCt = $this->pdo->prepare("
                UPDATE candidats_tour
                SET statut_qualification = :statut_qualif,
                    nb_voix_obtenues = :voix,
                    pourcentage_obtenu = :pct
                WHERE tour_id = :tid AND candidat_id = :cid
            ");

            foreach ($scores as $s) {
                $cid = (int)$s['id'];
                $voix = (int)$s['nb_voix'];
                $pct = ($totalBulletins > 0) ? round(($voix / $totalBulletins) * 100, 2) : 0.00;

                if ($isSingleWinner && $cid === (int)$leaders[0]['id']) {
                    $statutQualif = 'vainqueur';
                } elseif ($isEgalite && $voix === $maxVoix) {
                    $statutQualif = 'qualifie';
                } else {
                    $statutQualif = 'elimine';
                }

                $updCt->execute([
                    ':statut_qualif' => $statutQualif,
                    ':voix'          => $voix,
                    ':pct'           => $pct,
                    ':tid'           => $tid,
                    ':cid'           => $cid,
                ]);
            }

            // 3. Clôturer le tour courant
            $updTour = $this->pdo->prepare("
                UPDATE tours_election
                SET statut = 'cloturee',
                    est_egalite = :egalite,
                    date_cloture_effective = COALESCE(date_cloture_effective, :cloture)
                WHERE id = :tid
            ");
            $updTour->execute([
                ':egalite' => $isEgalite ? 1 : 0,
                ':cloture' => $nowStr,
                ':tid'     => $tid,
            ]);

            // CAS 1 : Un seul candidat arrive en tête -> Victoire, pas de nouveau tour
            if ($isSingleWinner) {
                $winner = $leaders[0];
                $updEl = $this->pdo->prepare("
                    UPDATE elections
                    SET statut = 'cloturee',
                        date_cloture_effective = COALESCE(date_cloture_effective, :cloture),
                        etat_egalite_persistante = 0,
                        vainqueur_candidat_id = :vid
                    WHERE id = :eid
                ");
                $updEl->execute([
                    ':cloture' => $nowStr,
                    ':vid'     => (int)$winner['id'],
                    ':eid'     => $electionId,
                ]);

                if ($ownTx) {
                    $this->pdo->commit();
                }

                return [
                    'success'         => true,
                    'status'          => 'vainqueur_unique',
                    'tour_cloture_id' => $tid,
                    'numero_tour'     => $numeroTour,
                    'vainqueur'       => $winner,
                    'qualifies'       => [$winner],
                    'elimines'        => $elimines,
                    'next_tour_id'    => null,
                    'message'         => "Le {$this->tourService->formatTourLabel($numeroTour)} est clôturé. Un seul candidat ({$winner['prenoms']} {$winner['nom']}) est en tête avec {$winner['nb_voix']} voix : il n'est pas nécessaire d'organiser un nouveau tour.",
                ];
            }

            // CAS 2 : Égalité en tête et le nombre maximal de tours n'est pas encore atteint -> Qualification pour le tour suivant
            if ($isEgalite && $numeroTour < $nombreMaxTours) {
                $nextNum = $numeroTour + 1;
                $nextLabel = $this->tourService->formatTourLabel($nextNum);

                $debutNext = $nextTourDebut ?: date('Y-m-d H:i:s', strtotime('+1 hour'));
                $finNext   = $nextTourFin ?: date('Y-m-d H:i:s', strtotime('+25 hours'));

                // Vérifier si le tour suivant existe déjà
                $stmtCheckNext = $this->pdo->prepare("SELECT * FROM tours_election WHERE election_id = :eid AND numero_tour = :num LIMIT 1");
                $stmtCheckNext->execute([':eid' => $electionId, ':num' => $nextNum]);
                $existingNext = $stmtCheckNext->fetch();

                if ($existingNext) {
                    $nextTourId = (int)$existingNext['id'];
                } else {
                    $insNext = $this->pdo->prepare("
                        INSERT INTO tours_election (election_id, numero_tour, libelle, date_debut_vote, date_fin_vote, statut, est_egalite, date_creation)
                        VALUES (:eid, :num, :lib, :debut, :fin, 'preparation', 0, NOW())
                    ");
                    $insNext->execute([
                        ':eid'   => $electionId,
                        ':num'   => $nextNum,
                        ':lib'   => $nextLabel,
                        ':debut' => $debutNext,
                        ':fin'   => $finNext,
                    ]);
                    $nextTourId = (int)$this->pdo->lastInsertId();
                }

                // Inscrire uniquement les candidats qualifiés dans candidats_tour pour le nouveau tour
                $delNextCt = $this->pdo->prepare("DELETE FROM candidats_tour WHERE tour_id = :ntid");
                $delNextCt->execute([':ntid' => $nextTourId]);

                $insNextCt = $this->pdo->prepare("
                    INSERT INTO candidats_tour (tour_id, election_id, candidat_id, statut_qualification)
                    VALUES (:ntid, :eid, :cid, 'participant')
                ");
                foreach ($leaders as $q) {
                    $insNextCt->execute([
                        ':ntid' => $nextTourId,
                        ':eid'  => $electionId,
                        ':cid'  => (int)$q['id'],
                    ]);
                }

                // Préparer la liste des électeurs pour le nouveau tour à partir de la liste de référence de l'élection
                $this->electeurTourService->syncElecteursForTour($electionId, $nextTourId);

                // Mettre l'élection sur le nouveau tour (en préparation afin que le bureau puisse ajuster les dates ou ouvrir le vote)
                $updEl = $this->pdo->prepare("
                    UPDATE elections
                    SET tour_courant = :num,
                        date_debut_vote = :debut_v,
                        date_fin_vote = :fin_v,
                        date_debut = :debut_d,
                        date_fin = :fin_d,
                        statut = 'preparation',
                        date_cloture_effective = NULL,
                        etat_egalite_persistante = 0,
                        vainqueur_candidat_id = NULL,
                        resultats_certifies = 0,
                        date_certification = NULL
                    WHERE id = :eid
                ");
                $updEl->execute([
                    ':num'     => $nextNum,
                    ':debut_v' => $debutNext,
                    ':fin_v'   => $finNext,
                    ':debut_d' => $debutNext,
                    ':fin_d'   => $finNext,
                    ':eid'     => $electionId,
                ]);

                if ($ownTx) {
                    $this->pdo->commit();
                }

                $nomsQualifies = implode(', ', array_map(fn($l) => $l['prenoms'] . ' ' . $l['nom'], $leaders));
                return [
                    'success'         => true,
                    'status'          => 'second_tour_prepare',
                    'tour_cloture_id' => $tid,
                    'numero_tour'     => $numeroTour,
                    'vainqueur'       => null,
                    'qualifies'       => $leaders,
                    'elimines'        => $elimines,
                    'next_tour_id'    => $nextTourId,
                    'next_numero'     => $nextNum,
                    'message'         => "Égalité en tête au {$this->tourService->formatTourLabel($numeroTour)} ({$maxVoix} voix). Les candidats qualifiés ({$nomsQualifies}) ont été automatiquement retenus pour le {$nextLabel}.",
                ];
            }

            // CAS 3 : Égalité persistante après le nombre maximal de tours -> Décision du bureau requise
            $updEl = $this->pdo->prepare("
                UPDATE elections
                SET statut = 'cloturee',
                    date_cloture_effective = COALESCE(date_cloture_effective, :cloture),
                    etat_egalite_persistante = 1,
                    vainqueur_candidat_id = NULL
                WHERE id = :eid
            ");
            $updEl->execute([
                ':cloture' => $nowStr,
                ':eid'     => $electionId,
            ]);

            if ($ownTx) {
                $this->pdo->commit();
            }

            return [
                'success'         => true,
                'status'          => 'egalite_persistante',
                'tour_cloture_id' => $tid,
                'numero_tour'     => $numeroTour,
                'vainqueur'       => null,
                'qualifies'       => $leaders,
                'elimines'        => $elimines,
                'next_tour_id'    => null,
                'message'         => "Égalité persistante après le nombre maximal de tours ({$nombreMaxTours} tours). Aucun vainqueur n'est désigné automatiquement : une décision officielle du bureau est requise.",
            ];
        } catch (Throwable $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur cloturerTourEtQualifier : ' . $e->getMessage());
            return [
                'success' => false,
                'status'  => 'error',
                'message' => 'Erreur lors de la clôture du tour et du calcul des qualifications : ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Enregistre une décision officielle du bureau (notamment en cas d'égalité persistante)
     * Sans jamais effacer les résultats historiques des tours précédents (Section 4 & 14).
     */
    public function enregistrerDecisionBureau(
        int $electionId,
        ?int $tourId,
        string $decision,
        string $motif,
        int $adminId,
        ?int $candidatRetenuId = null,
        string $typeDecision = 'egalite_persistante'
    ): array {
        $decisionClean = trim($decision);
        $motifClean = trim($motif);

        if ($decisionClean === '' || $motifClean === '') {
            return [
                'success' => false,
                'message' => 'La décision du bureau et son motif sont tous deux obligatoires.',
            ];
        }

        if ($adminId <= 0) {
            return [
                'success' => false,
                'message' => 'Administrateur responsable non identifié.',
            ];
        }

        $ownTx = !$this->pdo->inTransaction();
        if ($ownTx) {
            $this->pdo->beginTransaction();
        }

        try {
            $sql = "INSERT INTO decisions_bureau (
                        election_id, tour_id, type_decision, candidat_retenu_id, decision, motif, admin_id, date_decision
                    ) VALUES (
                        :eid, :tid, :type, :cid, :decision, :motif, :aid, NOW()
                    )";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':eid'      => $electionId,
                ':tid'      => $tourId,
                ':type'     => in_array($typeDecision, ['egalite_persistante', 'proclamation_vainqueur', 'autre'], true) ? $typeDecision : 'egalite_persistante',
                ':cid'      => ($candidatRetenuId && $candidatRetenuId > 0) ? $candidatRetenuId : null,
                ':decision' => $decisionClean,
                ':motif'    => $motifClean,
                ':aid'      => $adminId,
            ]);

            if ($candidatRetenuId && $candidatRetenuId > 0) {
                $updEl = $this->pdo->prepare("
                    UPDATE elections
                    SET vainqueur_candidat_id = :cid,
                        etat_egalite_persistante = 0
                    WHERE id = :eid
                ");
                $updEl->execute([
                    ':cid' => $candidatRetenuId,
                    ':eid' => $electionId,
                ]);
            }

            if ($ownTx) {
                $this->pdo->commit();
            }

            return [
                'success' => true,
                'message' => 'La décision officielle du bureau a été enregistrée et horodatée avec succès.',
            ];
        } catch (Throwable $e) {
            if ($ownTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Erreur enregistrerDecisionBureau : ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de la décision du bureau.',
            ];
        }
    }

    /**
     * Récupère l'historique des décisions du bureau pour une élection
     */
    public function getDecisionsByElection(int $electionId): array
    {
        $sql = "SELECT d.*,
                       a.nom AS admin_nom, a.prenoms AS admin_prenoms,
                       c.nom AS candidat_nom, c.prenoms AS candidat_prenoms,
                       t.numero_tour, t.libelle AS tour_libelle
                FROM decisions_bureau d
                LEFT JOIN administrateurs a ON a.id = d.admin_id
                LEFT JOIN candidats c ON c.id = d.candidat_retenu_id
                LEFT JOIN tours_election t ON t.id = d.tour_id
                WHERE d.election_id = :eid
                ORDER BY d.date_decision DESC, d.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':eid' => $electionId]);
        return $stmt->fetchAll();
    }
}
