<?php
/**
 * Modèle Membre
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

class Membre extends BaseModel
{
    /**
     * Recherche un membre par son identifiant
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT m.*, a.nom AS valideur_nom, a.prenoms AS valideur_prenoms
                FROM membres m
                LEFT JOIN administrateurs a ON m.valide_par = a.id
                WHERE m.id = :id
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Recherche un membre par son numéro de téléphone
     */
    public function findByTelephone(string $telephone): ?array
    {
        $sql = "SELECT * FROM membres WHERE telephone = :telephone LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':telephone' => $telephone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Inscription d'un nouveau membre (publique ou par l'admin)
     */
    public function create(array $data): int
    {
        $anciennete = (isset($data['anciennete']) && $data['anciennete'] !== '' && $data['anciennete'] !== null)
            ? max(0, (int)$data['anciennete'])
            : null;

        $sql = "INSERT INTO membres (
                    nom, prenoms, telephone, date_naissance, anciennete, fonction_pro, 
                    responsabilite_sas, statut, eligibilite_a_voter, motif_non_eligibilite,
                    date_enregistrement, date_validation_eligibilite, valide_par
                ) VALUES (
                    :nom, :prenoms, :telephone, :date_naissance, :anciennete, :fonction_pro,
                    :responsabilite_sas, :statut, :eligibilite_a_voter, :motif_non_eligibilite,
                    NOW(), :date_validation_eligibilite, :valide_par
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nom'                         => trim($data['nom']),
            ':prenoms'                     => trim($data['prenoms']),
            ':telephone'                   => trim($data['telephone']),
            ':date_naissance'              => !empty($data['date_naissance']) ? $data['date_naissance'] : null,
            ':anciennete'                  => $anciennete,
            ':fonction_pro'                => !empty($data['fonction_pro']) ? trim($data['fonction_pro']) : null,
            ':responsabilite_sas'          => !empty($data['responsabilite_sas']) ? trim($data['responsabilite_sas']) : null,
            ':statut'                      => $data['statut'] ?? 'actif',
            ':eligibilite_a_voter'         => $data['eligibilite_a_voter'] ?? 'non',
            ':motif_non_eligibilite'       => !empty($data['motif_non_eligibilite']) ? trim($data['motif_non_eligibilite']) : null,
            ':date_validation_eligibilite' => (!empty($data['eligibilite_a_voter']) && $data['eligibilite_a_voter'] === 'oui') ? date('Y-m-d H:i:s') : null,
            ':valide_par'                  => $data['valide_par'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Mise à jour des informations d'un membre
     */
    public function update(int $id, array $data): bool
    {
        $anciennete = (isset($data['anciennete']) && $data['anciennete'] !== '' && $data['anciennete'] !== null)
            ? max(0, (int)$data['anciennete'])
            : null;

        $sql = "UPDATE membres SET 
                    nom = :nom,
                    prenoms = :prenoms,
                    telephone = :telephone,
                    date_naissance = :date_naissance,
                    anciennete = :anciennete,
                    fonction_pro = :fonction_pro,
                    responsabilite_sas = :responsabilite_sas,
                    statut = :statut
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'                 => $id,
            ':nom'                => trim($data['nom']),
            ':prenoms'            => trim($data['prenoms']),
            ':telephone'          => trim($data['telephone']),
            ':date_naissance'     => !empty($data['date_naissance']) ? $data['date_naissance'] : null,
            ':anciennete'         => $anciennete,
            ':fonction_pro'       => !empty($data['fonction_pro']) ? trim($data['fonction_pro']) : null,
            ':responsabilite_sas' => !empty($data['responsabilite_sas']) ? trim($data['responsabilite_sas']) : null,
            ':statut'             => in_array($data['statut'] ?? 'actif', ['actif', 'inactif'], true) ? $data['statut'] : 'actif'
        ]);
    }

    /**
     * Modification manuelle de l'éligibilité au vote par le bureau
     * Règle métier : Seul eligibilite_a_voter = 'oui' autorise le vote !
     */
    public function updateEligibilite(int $id, string $eligibilite, ?string $motif, ?int $valideParAdminId): bool
    {
        $isEligible = ($eligibilite === 'oui');
        $sql = "UPDATE membres SET 
                    eligibilite_a_voter = :eligibilite,
                    motif_non_eligibilite = :motif,
                    date_validation_eligibilite = :date_validation,
                    valide_par = :valide_par
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'              => $id,
            ':eligibilite'     => $isEligible ? 'oui' : 'non',
            ':motif'           => $isEligible ? null : (!empty($motif) ? trim($motif) : null),
            ':date_validation' => $isEligible ? date('Y-m-d H:i:s') : null,
            ':valide_par'      => $valideParAdminId
        ]);
    }

    /**
     * Bascule du statut actif / inactif (privilégier la désactivation à la suppression)
     */
    public function toggleStatut(int $id, string $nouveauStatut): bool
    {
        $sql = "UPDATE membres SET statut = :statut WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'     => $id,
            ':statut' => $nouveauStatut === 'actif' ? 'actif' : 'inactif'
        ]);
    }

    /**
     * Récupère la liste des membres avec recherche et filtres
     */
    public function getAll(array $filters = []): array
    {
        $sql = "SELECT m.*, a.nom AS valideur_nom, a.prenoms AS valideur_prenoms
                FROM membres m
                LEFT JOIN administrateurs a ON m.valide_par = a.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (m.nom LIKE :search OR m.prenoms LIKE :search OR m.telephone LIKE :search)";
            $params[':search'] = '%' . trim($filters['search']) . '%';
        }

        if (!empty($filters['statut']) && in_array($filters['statut'], ['actif', 'inactif'], true)) {
            $sql .= " AND m.statut = :statut";
            $params[':statut'] = $filters['statut'];
        }

        if (!empty($filters['eligibilite']) && in_array($filters['eligibilite'], ['oui', 'non'], true)) {
            $sql .= " AND m.eligibilite_a_voter = :eligibilite";
            $params[':eligibilite'] = $filters['eligibilite'];
        }

        $sql .= " ORDER BY m.nom ASC, m.prenoms ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Récupère les statistiques globales sur les membres
     */
    public function getStats(): array
    {
        $sql = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) AS actifs,
                    SUM(CASE WHEN statut = 'inactif' THEN 1 ELSE 0 END) AS inactifs,
                    SUM(CASE WHEN eligibilite_a_voter = 'oui' THEN 1 ELSE 0 END) AS eligibles,
                    SUM(CASE WHEN eligibilite_a_voter = 'non' THEN 1 ELSE 0 END) AS non_eligibles
                FROM membres";
        $row = $this->pdo->query($sql)->fetch();
        return [
            'total'         => (int)($row['total'] ?? 0),
            'actifs'        => (int)($row['actifs'] ?? 0),
            'inactifs'      => (int)($row['inactifs'] ?? 0),
            'eligibles'     => (int)($row['eligibles'] ?? 0),
            'non_eligibles' => (int)($row['non_eligibles'] ?? 0)
        ];
    }
}
