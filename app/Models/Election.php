<?php
/**
 * Modèle Election — Gestion des élections, des périodes électorales et du nombre maximal de tours
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

class Election extends BaseModel
{
    /**
     * Normalise un enregistrement d'élection pour garantir la présence de date_debut_vote, date_fin_vote et des champs multi-tours
     */
    public function normalizeRow(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        if (empty($row['date_debut_vote']) && !empty($row['date_debut'])) {
            $row['date_debut_vote'] = $row['date_debut'];
        }
        if (empty($row['date_fin_vote']) && !empty($row['date_fin'])) {
            $row['date_fin_vote'] = $row['date_fin'];
        }
        if (empty($row['date_debut']) && !empty($row['date_debut_vote'])) {
            $row['date_debut'] = $row['date_debut_vote'];
        }
        if (empty($row['date_fin']) && !empty($row['date_fin_vote'])) {
            $row['date_fin'] = $row['date_fin_vote'];
        }
        $row['resultats_certifies'] = (int)($row['resultats_certifies'] ?? 0);
        $row['nombre_max_tours'] = max(1, (int)($row['nombre_max_tours'] ?? 3));
        $row['tour_courant'] = max(1, (int)($row['tour_courant'] ?? 1));
        $row['etat_egalite_persistante'] = (int)($row['etat_egalite_persistante'] ?? 0);
        $row['vainqueur_candidat_id'] = !empty($row['vainqueur_candidat_id']) ? (int)$row['vainqueur_candidat_id'] : null;
        return $row;
    }

    /**
     * Recherche une élection par son identifiant
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT e.*, a.nom AS createur_nom, a.prenoms AS createur_prenoms,
                       (SELECT COUNT(*) FROM candidats c WHERE c.election_id = e.id) AS nb_candidats,
                       (SELECT COUNT(*) FROM candidats c WHERE c.election_id = e.id AND c.statut = 'validee') AS nb_candidats_valides,
                       (SELECT COUNT(*) FROM participations p WHERE p.election_id = e.id AND p.statut = 'a_vote') AS nb_votants,
                       (SELECT COUNT(*) FROM membres m WHERE m.eligibilite_a_voter = 'oui' AND m.statut = 'actif') AS nb_eligibles,
                       (SELECT COUNT(*) FROM tours_election t WHERE t.election_id = e.id) AS nb_tours
                FROM elections e
                LEFT JOIN administrateurs a ON e.cree_par = a.id
                WHERE e.id = :id
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $this->normalizeRow($row ?: null);
    }

    /**
     * Récupère le dernier scrutin enregistré (scrutin de référence)
     * Trié par id DESC, cree_le DESC LIMIT 1
     */
    public function getDerniereElection(): ?array
    {
        $sql = "SELECT e.*, a.nom AS createur_nom, a.prenoms AS createur_prenoms,
                       (SELECT COUNT(*) FROM candidats c WHERE c.election_id = e.id) AS nb_candidats,
                       (SELECT COUNT(*) FROM candidats c WHERE c.election_id = e.id AND c.statut = 'validee') AS nb_candidats_valides,
                       (SELECT COUNT(*) FROM participations p WHERE p.election_id = e.id AND p.statut = 'a_vote') AS nb_votants,
                       (SELECT COUNT(*) FROM membres m WHERE m.eligibilite_a_voter = 'oui' AND m.statut = 'actif') AS nb_eligibles,
                       (SELECT COUNT(*) FROM tours_election t WHERE t.election_id = e.id) AS nb_tours
                FROM elections e
                LEFT JOIN administrateurs a ON e.cree_par = a.id
                ORDER BY e.id DESC, e.cree_le DESC
                LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch();
        return $this->normalizeRow($row ?: null);
    }

    /**
     * Liste toutes les élections (triées du scrutin le plus récent au plus ancien)
     */
    public function getAll(): array
    {
        $sql = "SELECT e.*, a.nom AS createur_nom, a.prenoms AS createur_prenoms,
                       (SELECT COUNT(*) FROM candidats c WHERE c.election_id = e.id) AS nb_candidats,
                       (SELECT COUNT(*) FROM candidats c WHERE c.election_id = e.id AND c.statut = 'validee') AS nb_candidats_valides,
                       (SELECT COUNT(*) FROM participations p WHERE p.election_id = e.id AND p.statut = 'a_vote') AS nb_votants,
                       (SELECT COUNT(*) FROM membres m WHERE m.eligibilite_a_voter = 'oui' AND m.statut = 'actif') AS nb_eligibles,
                       (SELECT COUNT(*) FROM tours_election t WHERE t.election_id = e.id) AS nb_tours
                FROM elections e
                LEFT JOIN administrateurs a ON e.cree_par = a.id
                ORDER BY e.id DESC, e.cree_le DESC";
        $rows = $this->pdo->query($sql)->fetchAll();
        return array_map(fn(array $r) => $this->normalizeRow($r), $rows);
    }

    /**
     * Récupère l'élection actuellement ouverte au vote (ou la plus récente ouverte)
     */
    public function getActiveElection(): ?array
    {
        $sql = "SELECT * FROM elections 
                WHERE statut = 'ouverte'
                ORDER BY id DESC, cree_le DESC LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch();
        return $this->normalizeRow($row ?: null);
    }

    /**
     * Crée une élection avec l'ensemble de ses dates de phases électorales et son Tour 1 initial
     */
    public function create(array $data): int
    {
        $dateDebutVote  = !empty($data['date_debut_vote']) ? $data['date_debut_vote'] : ($data['date_debut'] ?? date('Y-m-d H:i:s'));
        $dateFinVote    = !empty($data['date_fin_vote']) ? $data['date_fin_vote'] : ($data['date_fin'] ?? date('Y-m-d H:i:s', strtotime('+1 day')));
        $statut         = $data['statut'] ?? 'preparation';
        $nombreMaxTours = isset($data['nombre_max_tours']) ? max(1, (int)$data['nombre_max_tours']) : 3;
        $clotureEff     = !empty($data['date_cloture_effective'])
            ? $data['date_cloture_effective']
            : ($statut === 'cloturee' ? $dateFinVote : null);

        $sql = "INSERT INTO elections (
                    titre, description,
                    date_ouverture_inscriptions, date_fermeture_inscriptions,
                    date_ouverture_candidatures, date_fermeture_candidatures,
                    date_debut_campagne, date_fin_campagne,
                    date_debut_vote, date_fin_vote,
                    date_debut, date_fin,
                    date_cloture_effective,
                    statut, nombre_max_tours, tour_courant,
                    resultats_certifies, date_certification,
                    cree_par, cree_le
                ) VALUES (
                    :titre, :description,
                    :date_ouverture_inscriptions, :date_fermeture_inscriptions,
                    :date_ouverture_candidatures, :date_fermeture_candidatures,
                    :date_debut_campagne, :date_fin_campagne,
                    :date_debut_vote, :date_fin_vote,
                    :date_debut, :date_fin,
                    :date_cloture_effective,
                    :statut, :nombre_max_tours, 1,
                    :resultats_certifies, :date_certification,
                    :cree_par, NOW()
                )";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':titre'                       => trim($data['titre']),
            ':description'                 => !empty($data['description']) ? trim($data['description']) : null,
            ':date_ouverture_inscriptions' => !empty($data['date_ouverture_inscriptions']) ? $data['date_ouverture_inscriptions'] : null,
            ':date_fermeture_inscriptions' => !empty($data['date_fermeture_inscriptions']) ? $data['date_fermeture_inscriptions'] : null,
            ':date_ouverture_candidatures' => !empty($data['date_ouverture_candidatures']) ? $data['date_ouverture_candidatures'] : null,
            ':date_fermeture_candidatures' => !empty($data['date_fermeture_candidatures']) ? $data['date_fermeture_candidatures'] : null,
            ':date_debut_campagne'         => !empty($data['date_debut_campagne']) ? $data['date_debut_campagne'] : null,
            ':date_fin_campagne'           => !empty($data['date_fin_campagne']) ? $data['date_fin_campagne'] : null,
            ':date_debut_vote'             => $dateDebutVote,
            ':date_fin_vote'               => $dateFinVote,
            ':date_debut'                  => $dateDebutVote,
            ':date_fin'                    => $dateFinVote,
            ':date_cloture_effective'      => $clotureEff,
            ':statut'                      => $statut,
            ':nombre_max_tours'            => $nombreMaxTours,
            ':resultats_certifies'         => !empty($data['resultats_certifies']) ? 1 : 0,
            ':date_certification'          => !empty($data['date_certification']) ? $data['date_certification'] : null,
            ':cree_par'                    => $data['cree_par'] ?? null
        ]);

        $electionId = (int)$this->pdo->lastInsertId();

        // Créer automatiquement le Tour 1 de cette élection
        $stmtTour = $this->pdo->prepare("
            INSERT IGNORE INTO tours_election (election_id, numero_tour, libelle, date_debut_vote, date_fin_vote, statut, date_cloture_effective, date_creation)
            VALUES (:eid, 1, '1er Tour', :debut, :fin, :statut, :cloture, NOW())
        ");
        $stmtTour->execute([
            ':eid'     => $electionId,
            ':debut'   => $dateDebutVote,
            ':fin'     => $dateFinVote,
            ':statut'  => $statut,
            ':cloture' => $clotureEff,
        ]);

        return $electionId;
    }

    /**
     * Met à jour une élection et synchronise son tour courant
     */
    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);
        if (
            !$existing
            || (!empty($existing['resultats_certifies']) && ($data['statut'] ?? $existing['statut']) !== 'cloturee')
        ) {
            return false;
        }
        $dateDebutVote  = !empty($data['date_debut_vote']) ? $data['date_debut_vote'] : ($data['date_debut'] ?? null);
        $dateFinVote    = !empty($data['date_fin_vote']) ? $data['date_fin_vote'] : ($data['date_fin'] ?? null);
        $statut         = $data['statut'] ?? ($existing['statut'] ?? 'preparation');
        $nombreMaxTours = isset($data['nombre_max_tours'])
            ? max(1, (int)$data['nombre_max_tours'])
            : (int)($existing['nombre_max_tours'] ?? 3);

        $clotureEff = $data['date_cloture_effective'] ?? null;
        if ($statut === 'cloturee' && empty($clotureEff)) {
            $clotureEff = date('Y-m-d H:i:s');
        } elseif ($statut !== 'cloturee') {
            $clotureEff = null;
        }

        $sql = "UPDATE elections SET 
                    titre = :titre,
                    description = :description,
                    date_ouverture_inscriptions = :date_ouverture_inscriptions,
                    date_fermeture_inscriptions = :date_fermeture_inscriptions,
                    date_ouverture_candidatures = :date_ouverture_candidatures,
                    date_fermeture_candidatures = :date_fermeture_candidatures,
                    date_debut_campagne = :date_debut_campagne,
                    date_fin_campagne = :date_fin_campagne,
                    date_debut_vote = :date_debut_vote,
                    date_fin_vote = :date_fin_vote,
                    date_debut = :date_debut,
                    date_fin = :date_fin,
                    date_cloture_effective = :date_cloture_effective,
                    statut = :statut,
                    nombre_max_tours = :nombre_max_tours
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':id'                          => $id,
            ':titre'                       => trim($data['titre']),
            ':description'                 => !empty($data['description']) ? trim($data['description']) : null,
            ':date_ouverture_inscriptions' => !empty($data['date_ouverture_inscriptions']) ? $data['date_ouverture_inscriptions'] : null,
            ':date_fermeture_inscriptions' => !empty($data['date_fermeture_inscriptions']) ? $data['date_fermeture_inscriptions'] : null,
            ':date_ouverture_candidatures' => !empty($data['date_ouverture_candidatures']) ? $data['date_ouverture_candidatures'] : null,
            ':date_fermeture_candidatures' => !empty($data['date_fermeture_candidatures']) ? $data['date_fermeture_candidatures'] : null,
            ':date_debut_campagne'         => !empty($data['date_debut_campagne']) ? $data['date_debut_campagne'] : null,
            ':date_fin_campagne'           => !empty($data['date_fin_campagne']) ? $data['date_fin_campagne'] : null,
            ':date_debut_vote'             => $dateDebutVote,
            ':date_fin_vote'               => $dateFinVote,
            ':date_debut'                  => $dateDebutVote,
            ':date_fin'                    => $dateFinVote,
            ':date_cloture_effective'      => $clotureEff,
            ':statut'                      => $statut,
            ':nombre_max_tours'            => $nombreMaxTours
        ]);

        // Synchroniser le tour courant dans tours_election
        $tourCourant = max(1, (int)($existing['tour_courant'] ?? 1));
        if ($dateDebutVote && $dateFinVote) {
            $updTour = $this->pdo->prepare("
                UPDATE tours_election
                SET date_debut_vote = :debut,
                    date_fin_vote = :fin,
                    statut = :statut,
                    date_cloture_effective = :cloture
                WHERE election_id = :eid AND numero_tour = :num
            ");
            $updTour->execute([
                ':debut'   => $dateDebutVote,
                ':fin'     => $dateFinVote,
                ':statut'  => $statut,
                ':cloture' => $clotureEff,
                ':eid'     => $id,
                ':num'     => $tourCourant,
            ]);
        }

        return $ok;
    }

    /**
     * Change le statut de l'élection et de son tour courant (preparation, ouverte, cloturee)
     */
    public function setStatut(int $id, string $statut, ?string $clotureEffective = null): bool
    {
        if (!in_array($statut, ['preparation', 'ouverte', 'cloturee'], true)) {
            return false;
        }

        $existing = $this->findById($id);
        if (!$existing || (!empty($existing['resultats_certifies']) && $statut !== 'cloturee')) {
            return false;
        }
        $tourCourant = max(1, (int)($existing['tour_courant'] ?? 1));

        if ($statut === 'cloturee') {
            $clotureDate = $clotureEffective ?? date('Y-m-d H:i:s');
            $sql = "UPDATE elections SET statut = 'cloturee', date_cloture_effective = COALESCE(date_cloture_effective, :cloture_date) WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([':id' => $id, ':cloture_date' => $clotureDate]);

            $updTour = $this->pdo->prepare("
                UPDATE tours_election
                SET statut = 'cloturee', date_cloture_effective = COALESCE(date_cloture_effective, :cloture_date)
                WHERE election_id = :eid AND numero_tour = :num
            ");
            $updTour->execute([':eid' => $id, ':num' => $tourCourant, ':cloture_date' => $clotureDate]);
            return $ok;
        }

        $sql = "UPDATE elections SET statut = :statut, date_cloture_effective = NULL, resultats_certifies = 0, date_certification = NULL WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([':id' => $id, ':statut' => $statut]);

        $updTour = $this->pdo->prepare("
            UPDATE tours_election
            SET statut = :statut, date_cloture_effective = NULL
            WHERE election_id = :eid AND numero_tour = :num
        ");
        $updTour->execute([':eid' => $id, ':num' => $tourCourant, ':statut' => $statut]);

        return $ok;
    }

    /**
     * Marque automatiquement une élection et son tour courant comme clôturés lorsque date_fin_vote est atteinte
     */
    public function markAutoClosed(int $id, string $clotureDate): bool
    {
        $sql = "UPDATE elections 
                SET statut = 'cloturee', 
                    date_cloture_effective = COALESCE(date_cloture_effective, :cloture_date)
                WHERE id = :id AND statut != 'cloturee'";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            ':id'           => $id,
            ':cloture_date' => $clotureDate
        ]);

        $updTour = $this->pdo->prepare("
            UPDATE tours_election
            SET statut = 'cloturee',
                date_cloture_effective = COALESCE(date_cloture_effective, :cloture_date)
            WHERE election_id = :eid AND statut != 'cloturee' AND date_fin_vote <= :cloture_date2
        ");
        $updTour->execute([
            ':eid'           => $id,
            ':cloture_date'  => $clotureDate,
            ':cloture_date2' => $clotureDate,
        ]);

        return $ok;
    }

    /**
     * Certifie ou annule la certification officielle des résultats après clôture
     */
    public function certifyResults(int $id, bool $certified = true): bool
    {
        $sql = "UPDATE elections 
                SET resultats_certifies = :cert,
                    date_certification = :date_cert
                WHERE id = :id AND statut = 'cloturee'";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'        => $id,
            ':cert'      => $certified ? 1 : 0,
            ':date_cert' => $certified ? date('Y-m-d H:i:s') : null
        ]);
    }

    /**
     * Vérifie si le vote est actuellement autorisé pour cette élection (et son tour actif).
     */
    public function checkCanVote(array $election, ?string $now = null): array
    {
        $nowStr = $now ?? date('Y-m-d H:i:s');
        $dateDebutVote = $election['date_debut_vote'] ?? ($election['date_debut'] ?? null);
        $dateFinVote   = $election['date_fin_vote'] ?? ($election['date_fin'] ?? null);

        if (($election['statut'] ?? '') === 'cloturee') {
            return [
                'can_vote' => false,
                'message'  => 'Le scrutin pour cette élection est définitivement clôturé.'
            ];
        }

        if (!empty($dateFinVote) && $nowStr >= $dateFinVote) {
            if (!empty($election['id']) && !$this->pdo->inTransaction()) {
                $this->markAutoClosed((int)$election['id'], $dateFinVote);
            }
            return [
                'can_vote' => false,
                'message'  => 'L\'heure de clôture du vote est atteinte (fin le ' . date('d/m/Y à H:i', strtotime($dateFinVote)) . '). Le scrutin est désormais clôturé.'
            ];
        }

        if (($election['statut'] ?? '') === 'preparation') {
            return [
                'can_vote' => false,
                'message'  => 'Cette élection est actuellement en cours de préparation. Le vote n\'est pas encore ouvert.'
            ];
        }

        if (!empty($dateDebutVote) && $nowStr < $dateDebutVote) {
            return [
                'can_vote' => false,
                'message'  => 'Le vote n\'a pas encore démarré (début prévu le ' . date('d/m/Y à H:i', strtotime($dateDebutVote)) . ').'
            ];
        }

        return [
            'can_vote' => true,
            'message'  => 'Le scrutin est ouvert.'
        ];
    }

    /**
     * Statistiques globales des élections
     */
    public function getStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN statut = 'ouverte' THEN 1 ELSE 0 END) AS ouvertes,
                    SUM(CASE WHEN statut = 'preparation' THEN 1 ELSE 0 END) AS preparations,
                    SUM(CASE WHEN statut = 'cloturee' THEN 1 ELSE 0 END) AS cloturees
                FROM elections";
        $row = $this->pdo->query($sql)->fetch();
        return [
            'total'        => (int)($row['total'] ?? 0),
            'ouvertes'     => (int)($row['ouvertes'] ?? 0),
            'preparations' => (int)($row['preparations'] ?? 0),
            'cloturees'    => (int)($row['cloturees'] ?? 0)
        ];
    }
}
