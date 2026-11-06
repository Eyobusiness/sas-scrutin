<?php

/**
 * Configuration de la base de données pour SAS Élection
 * Compatible XAMPP Windows / MySQL / MariaDB
 */

declare(strict_types=1);

// Fuseau horaire officiel de référence : Africa/Abidjan (UTC+00:00)
date_default_timezone_set('Africa/Abidjan');

// if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
// if (!defined('DB_PORT')) define('DB_PORT', '3306');
// if (!defined('DB_NAME')) define('DB_NAME', 'sas_election');
// if (!defined('DB_USER')) define('DB_USER', 'root');
// if (!defined('DB_PASS')) define('DB_PASS', '');

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'amicijeu_electionsas');
if (!defined('DB_USER')) define('DB_USER', 'amicijeu_l2v');
if (!defined('DB_PASS')) define('DB_PASS', '98044322@Root');

class Database
{
    private static ?PDO $instance = null;

    /**
     * Retourne l'instance unique PDO (Singleton)
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                self::$instance->exec("SET time_zone = '+00:00'");
            } catch (PDOException $e) {
                // Journaliser et afficher un message sécurisé sans divulguer d'identifiants
                error_log('Erreur de connexion PDO : ' . $e->getMessage());
                die('Impossible de se connecter à la base de données. Veuillez vérifier la configuration dans config/database.php.');
            }
        }

        return self::$instance;
    }
}
