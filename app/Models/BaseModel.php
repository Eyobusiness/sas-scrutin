<?php
/**
 * BaseModel - Classe mère pour les modèles avec accès PDO
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

abstract class BaseModel
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }
}
