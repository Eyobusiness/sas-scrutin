-- ============================================================
-- SAS ÉLECTION - Base de données officielle (Version Multi-Tours)
-- Groupe : "Tous unis au service du Christ"
-- Encodage : UTF-8 (utf8mb4) | Moteur : InnoDB
-- Fuseau horaire : Africa/Abidjan
-- ============================================================

CREATE DATABASE IF NOT EXISTS `sas_election` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `sas_election`;

-- 1. Table des rôles
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `libelle` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table des administrateurs
CREATE TABLE IF NOT EXISTS `administrateurs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `prenoms` VARCHAR(150) NOT NULL,
    `telephone` VARCHAR(25) NOT NULL UNIQUE,
    `email` VARCHAR(150) NULL,
    `mot_de_passe` VARCHAR(255) NOT NULL,
    `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
    `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `mis_a_jour_le` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_admin_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table des membres
CREATE TABLE IF NOT EXISTS `membres` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nom` VARCHAR(100) NOT NULL,
    `prenoms` VARCHAR(150) NOT NULL,
    `telephone` VARCHAR(25) NOT NULL UNIQUE,
    `date_naissance` DATE NULL,
    `anciennete` INT UNSIGNED NULL DEFAULT NULL,
    `fonction_pro` VARCHAR(150) NULL,
    `responsabilite_sas` VARCHAR(150) NULL,
    `statut` ENUM('actif', 'inactif') NOT NULL DEFAULT 'actif',
    `eligibilite_a_voter` ENUM('oui', 'non') NOT NULL DEFAULT 'non',
    `motif_non_eligibilite` TEXT NULL,
    `date_enregistrement` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_validation_eligibilite` DATETIME NULL,
    `valide_par` INT NULL,
    CONSTRAINT `fk_membre_valideur` FOREIGN KEY (`valide_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table des élections
CREATE TABLE IF NOT EXISTS `elections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `titre` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `date_ouverture_inscriptions` DATETIME NULL DEFAULT NULL,
    `date_fermeture_inscriptions` DATETIME NULL DEFAULT NULL,
    `date_ouverture_candidatures` DATETIME NULL DEFAULT NULL,
    `date_fermeture_candidatures` DATETIME NULL DEFAULT NULL,
    `date_debut_campagne` DATETIME NULL DEFAULT NULL,
    `date_fin_campagne` DATETIME NULL DEFAULT NULL,
    `date_debut_vote` DATETIME NULL DEFAULT NULL,
    `date_fin_vote` DATETIME NULL DEFAULT NULL,
    `date_debut` DATETIME NOT NULL,
    `date_fin` DATETIME NOT NULL,
    `date_cloture_effective` DATETIME NULL DEFAULT NULL,
    `statut` ENUM('preparation', 'ouverte', 'cloturee') NOT NULL DEFAULT 'preparation',
    `nombre_max_tours` INT UNSIGNED NOT NULL DEFAULT 3,
    `tour_courant` INT UNSIGNED NOT NULL DEFAULT 1,
    `etat_egalite_persistante` TINYINT(1) NOT NULL DEFAULT 0,
    `vainqueur_candidat_id` INT NULL DEFAULT NULL,
    `resultats_certifies` TINYINT(1) NOT NULL DEFAULT 0,
    `date_certification` DATETIME NULL DEFAULT NULL,
    `cree_par` INT NULL,
    `cree_le` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `mis_a_jour_le` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_election_createur` FOREIGN KEY (`cree_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Table des candidatures
CREATE TABLE IF NOT EXISTS `candidats` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `prenoms` VARCHAR(150) NOT NULL,
    `telephone` VARCHAR(25) NULL,
    `date_naissance` DATE NULL DEFAULT NULL,
    `anciennete` INT UNSIGNED NULL DEFAULT NULL,
    `droit_candidature_paye` TINYINT(1) NOT NULL DEFAULT 0,
    `photo` VARCHAR(255) NULL,
    `motif_candidature` TEXT NOT NULL,
    `statut` ENUM('en_attente', 'validee', 'rejetee', 'retiree') NOT NULL DEFAULT 'en_attente',
    `motif_rejet` TEXT NULL,
    `date_depot` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_traitement` DATETIME NULL,
    `traite_par` INT NULL,
    CONSTRAINT `fk_candidat_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_candidat_traiteur` FOREIGN KEY (`traite_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Table des tours de scrutin (`tours_election`)
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

-- 7. Table des candidats autorisés par tour (`candidats_tour`)
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

-- 8. Table de la liste de référence des électeurs d'une élection (`electeurs_election`)
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

-- 9. Table de la liste des électeurs autorisés par tour (`electeurs_tour`)
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

-- 10. Journal d'audit des modifications exceptionnelles d'électeurs (`journal_modifications_electeurs`)
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

-- 11. Table des décisions du bureau (`decisions_bureau`)
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

-- 12. Table des participations (Émargement nominatif par tour)
CREATE TABLE IF NOT EXISTS `participations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `tour_id` INT NULL DEFAULT NULL,
    `membre_id` INT NOT NULL,
    `statut` ENUM('non_vote', 'a_vote') NOT NULL DEFAULT 'a_vote',
    `date_vote` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_participation_election` (`election_id`),
    CONSTRAINT `fk_participation_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_participation_membre` FOREIGN KEY (`membre_id`) REFERENCES `membres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `unique_tour_membre` UNIQUE (`tour_id`, `membre_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Table des votes (Bulletins secrets déconnectés du membre, rattachés au tour)
CREATE TABLE IF NOT EXISTS `votes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `election_id` INT NOT NULL,
    `tour_id` INT NULL DEFAULT NULL,
    `candidat_id` INT NOT NULL,
    `date_vote` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_vote_tour` (`tour_id`),
    CONSTRAINT `fk_vote_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_vote_candidat` FOREIGN KEY (`candidat_id`) REFERENCES `candidats` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Table des paramètres de l'application
CREATE TABLE IF NOT EXISTS `parametres` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cle` VARCHAR(50) NOT NULL UNIQUE,
    `valeur` TEXT NOT NULL,
    `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des rôles par défaut
INSERT INTO `roles` (`id`, `code`, `libelle`, `description`) VALUES
(1, 'super_admin', 'Super Administrateur', 'Accès total à toutes les fonctionnalités et à la gestion des administrateurs'),
(2, 'admin', 'Administrateur', 'Accès aux fonctions de gestion du scrutin (membres, candidatures, élections, participations, résultats)')
ON DUPLICATE KEY UPDATE `libelle` = VALUES(`libelle`);

-- Insertion des paramètres initiaux
INSERT INTO `parametres` (`cle`, `valeur`, `description`) VALUES
('app_name', 'SAS Élection', 'Nom de l\'application'),
('app_subtitle', 'Tous unis au service du Christ', 'Devise du groupe'),
('contact_email', 'contact@sas-eglise.org', 'Email de contact du bureau'),
('contact_phone', '+225 07 00 00 00 00', 'Téléphone d\'assistance du bureau'),
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
ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`);
