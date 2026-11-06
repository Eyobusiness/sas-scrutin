-- ============================================================================
-- SAS ÉLECTION - MIGRATION V4 : TOURS DE SCRUTIN, QUALIFICATION AUTOMATIQUE,
-- GESTION EXCEPTIONNELLE DES ÉLECTEURS, DÉCISIONS DU BUREAU & HISTORIQUE
-- Fuseau horaire applicatif : Africa/Abidjan
-- ============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

USE `sas_election`;

-- 1. Évolution de la table `elections`
ALTER TABLE `elections`
    ADD COLUMN IF NOT EXISTS `nombre_max_tours` INT UNSIGNED NOT NULL DEFAULT 3 AFTER `statut`,
    ADD COLUMN IF NOT EXISTS `tour_courant` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `nombre_max_tours`,
    ADD COLUMN IF NOT EXISTS `etat_egalite_persistante` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tour_courant`,
    ADD COLUMN IF NOT EXISTS `vainqueur_candidat_id` INT NULL DEFAULT NULL AFTER `etat_egalite_persistante`;

-- 2. Table `tours_election` (Cycles de vote à l'intérieur d'une même élection)
CREATE TABLE IF NOT EXISTS `tours_election` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `numero_tour` INT UNSIGNED NOT NULL DEFAULT 1,
    `libelle` VARCHAR(120) NULL DEFAULT NULL,
    `date_debut_vote` DATETIME NOT NULL,
    `date_fin_vote` DATETIME NOT NULL,
    `statut` ENUM('preparation', 'ouverte', 'cloturee') NOT NULL DEFAULT 'preparation',
    `est_egalite` TINYINT(1) NOT NULL DEFAULT 0,
    `date_cloture_effective` DATETIME NULL DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tour_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `unique_election_numero_tour` UNIQUE (`election_id`, `numero_tour`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table `candidats_tour` (Candidats autorisés / qualifiés pour chaque tour)
CREATE TABLE IF NOT EXISTS `candidats_tour` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tour_id` INT NOT NULL,
    `election_id` INT NOT NULL,
    `candidat_id` INT NOT NULL,
    `statut_qualification` ENUM('participant', 'qualifie', 'elimine', 'vainqueur') NOT NULL DEFAULT 'participant',
    `nb_voix_obtenues` INT UNSIGNED NOT NULL DEFAULT 0,
    `pourcentage_obtenu` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ct_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours_election` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ct_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ct_candidat` FOREIGN KEY (`candidat_id`) REFERENCES `candidats` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `unique_tour_candidat` UNIQUE (`tour_id`, `candidat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table `electeurs_election` (Liste de référence des électeurs de l'élection)
CREATE TABLE IF NOT EXISTS `electeurs_election` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `membre_id` INT NOT NULL,
    `autorise` TINYINT(1) NOT NULL DEFAULT 1,
    `motif` TEXT NULL DEFAULT NULL,
    `ajoute_par` INT NULL DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `mis_a_jour_le` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ee_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ee_membre` FOREIGN KEY (`membre_id`) REFERENCES `membres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ee_admin` FOREIGN KEY (`ajoute_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `unique_electeur_election` UNIQUE (`election_id`, `membre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table `electeurs_tour` (Liste des électeurs autorisés pour chaque tour)
CREATE TABLE IF NOT EXISTS `electeurs_tour` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tour_id` INT NOT NULL,
    `election_id` INT NOT NULL,
    `membre_id` INT NOT NULL,
    `autorise` TINYINT(1) NOT NULL DEFAULT 1,
    `motif` TEXT NULL DEFAULT NULL,
    `modifie_par` INT NULL DEFAULT NULL,
    `date_creation` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `mis_a_jour_le` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_et_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours_election` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_et_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_et_membre` FOREIGN KEY (`membre_id`) REFERENCES `membres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_et_admin` FOREIGN KEY (`modifie_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `unique_electeur_tour` UNIQUE (`tour_id`, `membre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table `journal_modifications_electeurs` (Journal d'audit obligatoire des ajouts/retraits/corrections)
CREATE TABLE IF NOT EXISTS `journal_modifications_electeurs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `tour_id` INT NULL DEFAULT NULL,
    `membre_id` INT NOT NULL,
    `admin_id` INT NULL DEFAULT NULL,
    `type_modification` ENUM('ajout', 'retrait', 'correction', 'initialisation') NOT NULL,
    `motif` TEXT NOT NULL,
    `date_modification` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_jme_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_jme_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours_election` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_jme_membre` FOREIGN KEY (`membre_id`) REFERENCES `membres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_jme_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Table `decisions_bureau` (Décisions du bureau en cas d'égalité persistante ou validation finale)
CREATE TABLE IF NOT EXISTS `decisions_bureau` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `tour_id` INT NULL DEFAULT NULL,
    `type_decision` ENUM('egalite_persistante', 'proclamation_vainqueur', 'autre') NOT NULL DEFAULT 'egalite_persistante',
    `candidat_retenu_id` INT NULL DEFAULT NULL,
    `decision` TEXT NOT NULL,
    `motif` TEXT NOT NULL,
    `admin_id` INT NULL DEFAULT NULL,
    `date_decision` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_db_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_db_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours_election` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_db_candidat` FOREIGN KEY (`candidat_retenu_id`) REFERENCES `candidats` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_db_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Évolution de la table `participations` (Ajout de `tour_id` et unicité par `(tour_id, membre_id)`)
ALTER TABLE `participations`
    ADD COLUMN IF NOT EXISTS `tour_id` INT NULL DEFAULT NULL AFTER `election_id`;

CREATE INDEX IF NOT EXISTS `idx_participation_election` ON `participations` (`election_id`);
ALTER TABLE `participations` DROP INDEX IF EXISTS `unique_election_membre`;
ALTER TABLE `participations` ADD UNIQUE INDEX IF NOT EXISTS `unique_tour_membre` (`tour_id`, `membre_id`);

-- 9. Évolution de la table `votes` (Ajout de `tour_id` — toujours sans `membre_id` pour le secret du vote)
ALTER TABLE `votes`
    ADD COLUMN IF NOT EXISTS `tour_id` INT NULL DEFAULT NULL AFTER `election_id`;

CREATE INDEX IF NOT EXISTS `idx_vote_tour` ON `votes` (`tour_id`);

-- 10. Création automatique du Tour 1 pour toutes les élections existantes qui n'en ont pas encore
INSERT IGNORE INTO `tours_election` (`election_id`, `numero_tour`, `libelle`, `date_debut_vote`, `date_fin_vote`, `statut`, `date_cloture_effective`, `date_creation`)
SELECT
    e.`id`,
    1,
    '1er Tour',
    COALESCE(e.`date_debut_vote`, e.`date_debut`),
    COALESCE(e.`date_fin_vote`, e.`date_fin`),
    e.`statut`,
    e.`date_cloture_effective`,
    e.`cree_le`
FROM `elections` e;

-- 11. Rattachement des participations et votes existants au Tour 1 de leur élection respective
UPDATE `participations` p
JOIN `tours_election` t ON t.`election_id` = p.`election_id` AND t.`numero_tour` = 1
SET p.`tour_id` = t.`id`
WHERE p.`tour_id` IS NULL;

UPDATE `votes` v
JOIN `tours_election` t ON t.`election_id` = v.`election_id` AND t.`numero_tour` = 1
SET v.`tour_id` = t.`id`
WHERE v.`tour_id` IS NULL;

-- 12. Initialisation des candidats validés dans `candidats_tour` pour le Tour 1
INSERT IGNORE INTO `candidats_tour` (`tour_id`, `election_id`, `candidat_id`, `statut_qualification`)
SELECT t.`id`, c.`election_id`, c.`id`, 'participant'
FROM `candidats` c
JOIN `tours_election` t ON t.`election_id` = c.`election_id` AND t.`numero_tour` = 1
WHERE c.`statut` = 'validee';

-- 13. Initialisation de `electeurs_election` et `electeurs_tour` à partir des membres éligibles actifs
INSERT IGNORE INTO `electeurs_election` (`election_id`, `membre_id`, `autorise`, `motif`)
SELECT e.`id`, m.`id`, 1, 'Membre éligible sur la liste électorale générale'
FROM `elections` e
CROSS JOIN `membres` m
WHERE m.`eligibilite_a_voter` = 'oui' AND m.`statut` = 'actif';

INSERT IGNORE INTO `electeurs_tour` (`tour_id`, `election_id`, `membre_id`, `autorise`, `motif`)
SELECT t.`id`, t.`election_id`, m.`id`, 1, 'Membre éligible autorisé au tour'
FROM `tours_election` t
CROSS JOIN `membres` m
WHERE m.`eligibilite_a_voter` = 'oui' AND m.`statut` = 'actif';

-- Normalisation UTF-8 des motifs par défaut
UPDATE `electeurs_tour` SET `motif` = 'Membre éligible autorisé au tour' WHERE `modifie_par` IS NULL AND `autorise` = 1;
UPDATE `electeurs_election` SET `motif` = 'Membre éligible sur la liste électorale générale' WHERE `ajoute_par` IS NULL AND `autorise` = 1;
