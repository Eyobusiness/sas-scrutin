<?php
/**
 * Modèle Candidat
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

class Candidat extends BaseModel
{
    /**
     * Recherche un candidat par son identifiant
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT c.*, e.titre AS election_titre, e.statut AS election_statut,
                       a.nom AS traiteur_nom, a.prenoms AS traiteur_prenoms
                FROM candidats c
                JOIN elections e ON c.election_id = e.id
                LEFT JOIN administrateurs a ON c.traite_par = a.id
                WHERE c.id = :id
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Liste toutes les candidatures avec filtres éventuels
     */
    public function getAll(array $filters = []): array
    {
        $sql = "SELECT c.*, e.titre AS election_titre, e.statut AS election_statut,
                       a.nom AS traiteur_nom, a.prenoms AS traiteur_prenoms,
                       (SELECT COUNT(*) FROM votes v WHERE v.candidat_id = c.id) AS nb_voix
                FROM candidats c
                JOIN elections e ON c.election_id = e.id
                LEFT JOIN administrateurs a ON c.traite_par = a.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['election_id'])) {
            $sql .= " AND c.election_id = :election_id";
            $params[':election_id'] = (int)$filters['election_id'];
        }

        if (!empty($filters['statut'])) {
            $sql .= " AND c.statut = :statut";
            $params[':statut'] = $filters['statut'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (c.nom LIKE :search OR c.prenoms LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        $sql .= " ORDER BY e.id DESC, c.nom ASC, c.prenoms ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Récupère uniquement les candidats VALIDÉS d'une élection (pour le bulletin de vote)
     * Règle métier : Seules les candidatures validées apparaissent sur le bulletin de vote !
     */
    public function getValidatedByElection(int $electionId): array
    {
        $sql = "SELECT * FROM candidats 
                WHERE election_id = :election_id AND statut = 'validee'
                ORDER BY nom ASC, prenoms ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':election_id' => $electionId]);
        return $stmt->fetchAll();
    }

    /**
     * Vérifie si une candidature existe déjà pour ce candidat (même téléphone ou même nom+prénoms)
     * sur la même élection (hors candidature retirée)
     */
    public function hasExistingCandidature(int $electionId, string $nom, string $prenoms, ?string $telephone = null, ?int $excludeId = null): bool
    {
        $tel = trim((string)($telephone ?? ''));
        $nomClean = mb_strtolower(trim($nom));
        $prenomsClean = mb_strtolower(trim($prenoms));

        $sql = "SELECT id FROM candidats
                WHERE election_id = :election_id
                  AND statut != 'retiree'
                  AND (
                      (LOWER(TRIM(nom)) = :nom AND LOWER(TRIM(prenoms)) = :prenoms)"
                . ($tel !== '' ? " OR telephone = :tel" : "")
                . ")"
                . ($excludeId !== null ? " AND id != :exclude_id" : "")
                . " LIMIT 1";

        $params = [
            ':election_id' => $electionId,
            ':nom'         => $nomClean,
            ':prenoms'     => $prenomsClean,
        ];
        if ($tel !== '') {
            $params[':tel'] = $tel;
        }
        if ($excludeId !== null) {
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Dépôt d'une candidature
     */
    public function create(array $data): int
    {
        $anciennete = (isset($data['anciennete']) && $data['anciennete'] !== '' && $data['anciennete'] !== null)
            ? max(0, (int)$data['anciennete'])
            : null;
        $droitPaye = !empty($data['droit_candidature_paye']) ? 1 : 0;

        $sql = "INSERT INTO candidats (
                    election_id, nom, prenoms, telephone, date_naissance, anciennete,
                    droit_candidature_paye, photo, motif_candidature, statut, motif_rejet, date_depot
                ) VALUES (
                    :election_id, :nom, :prenoms, :telephone, :date_naissance, :anciennete,
                    :droit_candidature_paye, :photo, :motif_candidature, :statut, :motif_rejet, NOW()
                )";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':election_id'            => (int)$data['election_id'],
            ':nom'                    => trim($data['nom']),
            ':prenoms'                => trim($data['prenoms']),
            ':telephone'              => !empty($data['telephone']) ? trim($data['telephone']) : null,
            ':date_naissance'         => !empty($data['date_naissance']) ? $data['date_naissance'] : null,
            ':anciennete'             => $anciennete,
            ':droit_candidature_paye' => $droitPaye,
            ':photo'                  => !empty($data['photo']) ? trim($data['photo']) : null,
            ':motif_candidature'      => trim($data['motif_candidature']),
            ':statut'                 => $data['statut'] ?? 'en_attente',
            ':motif_rejet'            => !empty($data['motif_rejet']) ? trim($data['motif_rejet']) : null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Traitement par le bureau : validation, rejet ou retrait (et mise à jour éventuelle du paiement)
     */
    public function updateStatut(int $id, string $statut, ?string $motifRejet, ?int $traiteParAdminId, ?int $droitPaye = null): bool
    {
        if (!in_array($statut, ['en_attente', 'validee', 'rejetee', 'retiree'], true)) {
            return false;
        }

        $sql = "UPDATE candidats SET 
                    statut = :statut,
                    motif_rejet = :motif_rejet,
                    date_traitement = NOW(),
                    traite_par = :traite_par"
                . ($droitPaye !== null ? ", droit_candidature_paye = :droit_paye" : "")
                . " WHERE id = :id";

        $params = [
            ':id'          => $id,
            ':statut'      => $statut,
            ':motif_rejet' => ($statut === 'rejetee') ? trim((string)$motifRejet) : null,
            ':traite_par'  => $traiteParAdminId
        ];

        if ($droitPaye !== null) {
            $params[':droit_paye'] = $droitPaye ? 1 : 0;
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Modification des informations d'un candidat
     */
    public function update(int $id, array $data): bool
    {
        $anciennete = (isset($data['anciennete']) && $data['anciennete'] !== '' && $data['anciennete'] !== null)
            ? max(0, (int)$data['anciennete'])
            : null;
        $droitPaye = !empty($data['droit_candidature_paye']) ? 1 : 0;

        $sql = "UPDATE candidats SET 
                    nom = :nom,
                    prenoms = :prenoms,
                    telephone = :telephone,
                    date_naissance = :date_naissance,
                    anciennete = :anciennete,
                    droit_candidature_paye = :droit_candidature_paye,
                    motif_candidature = :motif_candidature" . 
                    (!empty($data['photo']) ? ", photo = :photo" : "") .
                " WHERE id = :id";

        $params = [
            ':id'                     => $id,
            ':nom'                    => trim($data['nom']),
            ':prenoms'                => trim($data['prenoms']),
            ':telephone'              => !empty($data['telephone']) ? trim($data['telephone']) : null,
            ':date_naissance'         => !empty($data['date_naissance']) ? $data['date_naissance'] : null,
            ':anciennete'             => $anciennete,
            ':droit_candidature_paye' => $droitPaye,
            ':motif_candidature'      => trim($data['motif_candidature'])
        ];

        if (!empty($data['photo'])) {
            $params[':photo'] = trim($data['photo']);
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Statistiques globales des candidatures
     */
    public function getStats(?int $electionId = null): array
    {
        $sql = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN statut = 'validee' THEN 1 ELSE 0 END) AS validees,
                    SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS en_attente,
                    SUM(CASE WHEN statut = 'rejetee' THEN 1 ELSE 0 END) AS rejetees,
                    SUM(CASE WHEN statut = 'retiree' THEN 1 ELSE 0 END) AS retirees
                FROM candidats";
        if ($electionId !== null) {
            $sql .= " WHERE election_id = " . (int)$electionId;
        }

        $row = $this->pdo->query($sql)->fetch();
        return [
            'total'      => (int)($row['total'] ?? 0),
            'validees'   => (int)($row['validees'] ?? 0),
            'en_attente' => (int)($row['en_attente'] ?? 0),
            'rejetees'   => (int)($row['rejetees'] ?? 0),
            'retirees'   => (int)($row['retirees'] ?? 0)
        ];
    }
}
