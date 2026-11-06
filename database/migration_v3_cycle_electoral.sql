-- ============================================================
-- SAS ÉLECTION - Migration V3 : Cycle électoral complet & Dernier scrutin
-- Fuseau horaire : Africa/Abidjan (UTC+00:00)
-- ============================================================

USE `sas_election`;

-- 1. Ajout des colonnes de gestion des périodes électorales dans la table `elections`
ALTER TABLE `elections`
    ADD COLUMN IF NOT EXISTS `date_ouverture_inscriptions` DATETIME NULL DEFAULT NULL AFTER `description`,
    ADD COLUMN IF NOT EXISTS `date_fermeture_inscriptions` DATETIME NULL DEFAULT NULL AFTER `date_ouverture_inscriptions`,
    ADD COLUMN IF NOT EXISTS `date_ouverture_candidatures` DATETIME NULL DEFAULT NULL AFTER `date_fermeture_inscriptions`,
    ADD COLUMN IF NOT EXISTS `date_fermeture_candidatures` DATETIME NULL DEFAULT NULL AFTER `date_ouverture_candidatures`,
    ADD COLUMN IF NOT EXISTS `date_debut_campagne` DATETIME NULL DEFAULT NULL AFTER `date_fermeture_candidatures`,
    ADD COLUMN IF NOT EXISTS `date_fin_campagne` DATETIME NULL DEFAULT NULL AFTER `date_debut_campagne`,
    ADD COLUMN IF NOT EXISTS `date_debut_vote` DATETIME NULL DEFAULT NULL AFTER `date_fin_campagne`,
    ADD COLUMN IF NOT EXISTS `date_fin_vote` DATETIME NULL DEFAULT NULL AFTER `date_debut_vote`,
    ADD COLUMN IF NOT EXISTS `date_cloture_effective` DATETIME NULL DEFAULT NULL AFTER `date_fin`,
    ADD COLUMN IF NOT EXISTS `resultats_certifies` TINYINT(1) NOT NULL DEFAULT 0 AFTER `statut`,
    ADD COLUMN IF NOT EXISTS `date_certification` DATETIME NULL DEFAULT NULL AFTER `resultats_certifies`;

-- 2. Synchronisation initiale des élections existantes (rétrocompatibilité avec date_debut / date_fin)
UPDATE `elections`
SET
    `date_debut_vote` = COALESCE(`date_debut_vote`, `date_debut`),
    `date_fin_vote` = COALESCE(`date_fin_vote`, `date_fin`),
    `date_ouverture_inscriptions` = COALESCE(`date_ouverture_inscriptions`, DATE_SUB(`date_debut`, INTERVAL 14 DAY)),
    `date_fermeture_inscriptions` = COALESCE(`date_fermeture_inscriptions`, DATE_SUB(`date_debut`, INTERVAL 1 HOUR)),
    `date_ouverture_candidatures` = COALESCE(`date_ouverture_candidatures`, DATE_SUB(`date_debut`, INTERVAL 10 DAY)),
    `date_fermeture_candidatures` = COALESCE(`date_fermeture_candidatures`, DATE_SUB(`date_debut`, INTERVAL 3 DAY)),
    `date_debut_campagne` = COALESCE(`date_debut_campagne`, DATE_SUB(`date_debut`, INTERVAL 3 DAY)),
    `date_fin_campagne` = COALESCE(`date_fin_campagne`, `date_debut`),
    `date_cloture_effective` = CASE
        WHEN `statut` = 'cloturee' AND `date_cloture_effective` IS NULL THEN `date_fin`
        ELSE `date_cloture_effective`
    END;

-- 3. Index d'optimisation pour la détermination du dernier scrutin et les contrôles de doublons
CREATE INDEX IF NOT EXISTS `idx_elections_dernier` ON `elections` (`id`, `cree_le`);
CREATE INDEX IF NOT EXISTS `idx_candidats_election_tel` ON `candidats` (`election_id`, `telephone`);
CREATE INDEX IF NOT EXISTS `idx_candidats_election_identite` ON `candidats` (`election_id`, `nom`, `prenoms`);

