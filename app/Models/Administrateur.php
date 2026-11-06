<?php
/**
 * Modèle Administrateur
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

class Administrateur extends BaseModel
{
    /**
     * Recherche un administrateur par son numéro de téléphone
     */
    public function findByTelephone(string $telephone): ?array
    {
        $sql = "SELECT a.*, r.code AS role_code, r.libelle AS role_libelle
                FROM administrateurs a
                JOIN roles r ON a.role_id = r.id
                WHERE a.telephone = :telephone
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':telephone' => $telephone]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Recherche par ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT a.*, r.code AS role_code, r.libelle AS role_libelle
                FROM administrateurs a
                JOIN roles r ON a.role_id = r.id
                WHERE a.id = :id
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Liste tous les administrateurs
     */
    public function getAll(): array
    {
        $sql = "SELECT a.id, a.nom, a.prenoms, a.telephone, a.email, a.statut, a.cree_le,
                       r.id AS role_id, r.code AS role_code, r.libelle AS role_libelle
                FROM administrateurs a
                JOIN roles r ON a.role_id = r.id
                ORDER BY a.nom ASC, a.prenoms ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Liste tous les rôles disponibles
     */
    public function getRoles(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM roles ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    /**
     * Crée un nouvel administrateur
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO administrateurs (role_id, nom, prenoms, telephone, email, mot_de_passe, statut)
                VALUES (:role_id, :nom, :prenoms, :telephone, :email, :mot_de_passe, :statut)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':role_id'      => $data['role_id'],
            ':nom'          => $data['nom'],
            ':prenoms'      => $data['prenoms'],
            ':telephone'    => $data['telephone'],
            ':email'        => $data['email'] ?? null,
            ':mot_de_passe' => password_hash($data['mot_de_passe'], PASSWORD_DEFAULT),
            ':statut'       => $data['statut'] ?? 'actif'
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Met à jour les informations d'un administrateur
     */
    public function update(int $id, array $data): bool
    {
        $sql = "UPDATE administrateurs 
                SET role_id = :role_id, nom = :nom, prenoms = :prenoms, 
                    telephone = :telephone, email = :email, statut = :statut
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'        => $id,
            ':role_id'   => $data['role_id'],
            ':nom'       => $data['nom'],
            ':prenoms'   => $data['prenoms'],
            ':telephone' => $data['telephone'],
            ':email'     => $data['email'] ?? null,
            ':statut'    => $data['statut']
        ]);
    }

    /**
     * Modifie le mot de passe
     */
    public function updatePassword(int $id, string $plainPassword): bool
    {
        $sql = "UPDATE administrateurs SET mot_de_passe = :mot_de_passe WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'           => $id,
            ':mot_de_passe' => password_hash($plainPassword, PASSWORD_DEFAULT)
        ]);
    }

    /**
     * Bascule le statut (actif / inactif)
     */
    public function toggleStatut(int $id, string $nouveauStatut): bool
    {
        $sql = "UPDATE administrateurs SET statut = :statut WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'     => $id,
            ':statut' => $nouveauStatut === 'actif' ? 'actif' : 'inactif'
        ]);
    }

    /**
     * Compte le nombre total d'administrateurs
     */
    public function countTotal(): int
    {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM administrateurs")->fetchColumn();
    }
}
