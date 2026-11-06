-- ============================================================
-- SAS ÉLECTION - Migration SQL v2 (Prompt 6)
-- Évolution des conditions d'éligibilité, des candidatures
-- et de l'authentification des électeurs
-- ============================================================
-- Ce script est idempotent : il préserve toutes les données
-- existantes, ne supprime aucune colonne et gère les valeurs NULL
-- pour les anciens enregistrements sans les rendre artificiellement éligibles.
-- ============================================================

USE `sas_election`;

-- 1. Table de configuration des paramètres (si elle n'existe pas déjà)
CREATE TABLE IF NOT EXISTS `parametres` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cle` VARCHAR(50) NOT NULL UNIQUE,
    `valeur` TEXT NOT NULL,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Mise à jour de la table `membres`
-- Ajout du champ `anciennete` (INT UNSIGNED NULL pour préserver les anciens membres sans inventer d'ancienneté)
SET @dbname = DATABASE();
SET @tablename = 'membres';
SET @columnname = 'anciennete';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `membres` ADD COLUMN `anciennete` INT UNSIGNED NULL DEFAULT NULL AFTER `date_naissance`;'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. Mise à jour de la table `candidats`
-- 3.a Ajout du champ `date_naissance` (DATE NULL)
SET @tablename = 'candidats';
SET @columnname = 'date_naissance';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `candidats` ADD COLUMN `date_naissance` DATE NULL DEFAULT NULL AFTER `telephone`;'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3.b Ajout du champ `anciennete` (INT UNSIGNED NULL)
SET @columnname = 'anciennete';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `candidats` ADD COLUMN `anciennete` INT UNSIGNED NULL DEFAULT NULL AFTER `date_naissance`;'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3.c Ajout du champ `droit_candidature_paye` (TINYINT(1) NOT NULL DEFAULT 0)
SET @columnname = 'droit_candidature_paye';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE `candidats` ADD COLUMN `droit_candidature_paye` TINYINT(1) NOT NULL DEFAULT 0 AFTER `anciennete`;'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4. Insertion des paramètres configurables d'éligibilité et de candidature (sans écraser les valeurs déjà configurées)
INSERT INTO `parametres` (`cle`, `valeur`, `description`) VALUES
('age_min_actif', '1', 'Activer la condition d\'âge minimum pour les électeurs (1=Oui, 0=Non)'),
('age_min_valeur', '18', 'Âge minimum requis pour être électeur (en années)'),
('anciennete_min_actif', '1', 'Activer la condition d\'ancienneté minimum pour les électeurs (1=Oui, 0=Non)'),
('anciennete_min_valeur', '2', 'Ancienneté minimum requise pour être électeur (en années)'),
('candidature_conditions_communes', '1', 'Utiliser les mêmes conditions d\'âge et d\'ancienneté pour les candidats (1=Oui, 0=Non)'),
('candidature_age_min_actif', '1', 'Activer la condition d\'âge minimum pour les candidats (1=Oui, 0=Non)'),
('candidature_age_min_valeur', '18', 'Âge minimum autorisé pour déposer une candidature (en années)'),
('candidature_anciennete_min_actif', '1', 'Activer la condition d\'ancienneté minimum pour les candidats (1=Oui, 0=Non)'),
('candidature_anciennete_min_valeur', '2', 'Ancienneté minimum requise pour déposer une candidature (en années)'),
('candidature_paiement_requis', '1', 'Exiger le paiement du droit de candidature (1=Oui, 0=Non)')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

