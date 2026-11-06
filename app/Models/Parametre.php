<?php
/**
 * Modèle Parametre
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseModel.php';

class Parametre extends BaseModel
{
    /**
     * Récupère la valeur d'un paramètre
     */
    public function get(string $cle, string $default = ''): string
    {
        $stmt = $this->pdo->prepare("SELECT valeur FROM parametres WHERE cle = :cle LIMIT 1");
        $stmt->execute([':cle' => $cle]);
        $valeur = $stmt->fetchColumn();
        return ($valeur !== false) ? (string)$valeur : $default;
    }

    /**
     * Définit ou met à jour un paramètre
     */
    public function set(string $cle, string $valeur, ?string $description = null): bool
    {
        $sql = "INSERT INTO parametres (cle, valeur, description) 
                VALUES (:cle, :valeur, :description)
                ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':cle'         => $cle,
            ':valeur'      => $valeur,
            ':description' => $description
        ]);
    }

    /**
     * Récupère tous les paramètres sous forme de tableau clé => valeur
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT cle, valeur FROM parametres");
        $rows = $stmt->fetchAll();
        $params = [];
        foreach ($rows as $r) {
            $params[$r['cle']] = $r['valeur'];
        }
        return $params;
    }
}
