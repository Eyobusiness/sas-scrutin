-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sas_election
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `administrateurs`
--

DROP TABLE IF EXISTS `administrateurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administrateurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `prenoms` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telephone` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'actif',
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `mis_a_jour_le` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `telephone` (`telephone`),
  KEY `fk_admin_role` (`role_id`),
  CONSTRAINT `fk_admin_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administrateurs`
--

LOCK TABLES `administrateurs` WRITE;
/*!40000 ALTER TABLE `administrateurs` DISABLE KEYS */;
INSERT INTO `administrateurs` VALUES (1,1,'Kouadio','Ferdinand','0173832620','admin@sas-election.ci','$2y$10$tSnm4QB5iTLEg6XalNJaI.lGMcPvTim7knSIhvp6yqGV71i5lYpKO','actif','2026-10-27 14:07:11','2026-10-27 14:22:59'),(2,1,'KOUADIO','Ferdinand','0700000001','superadmin@sas-eglise.org','$2y$10$8Q1caMUWPkSRa7VWGi2hquGs3cCHzRw/qsc8W.z0xOErFI87kqF9W','actif','2026-11-01 16:56:13',NULL),(3,2,'TOURE','Awa','0700000002','admin@sas-eglise.org','$2y$10$8Q1caMUWPkSRa7VWGi2hquGs3cCHzRw/qsc8W.z0xOErFI87kqF9W','actif','2026-11-01 16:56:13',NULL),(4,1,'M.','ANEDJO','0707136615',NULL,'$2y$10$c/2/obGVfgz/nDBE0nNhsOdvhRPou6ECxPSEAYZSDgAeL278uD9R2','actif','2026-11-02 19:57:41',NULL);
/*!40000 ALTER TABLE `administrateurs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candidats`
--

DROP TABLE IF EXISTS `candidats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `prenoms` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telephone` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `anciennete` int(10) unsigned DEFAULT NULL,
  `droit_candidature_paye` tinyint(1) NOT NULL DEFAULT 0,
  `photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `motif_candidature` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `statut` enum('en_attente','validee','rejetee','retiree') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'en_attente',
  `motif_rejet` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_depot` datetime NOT NULL DEFAULT current_timestamp(),
  `date_traitement` datetime DEFAULT NULL,
  `traite_par` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_candidat_traiteur` (`traite_par`),
  KEY `idx_candidats_election_tel` (`election_id`,`telephone`),
  KEY `idx_candidats_election_identite` (`election_id`,`nom`,`prenoms`),
  CONSTRAINT `fk_candidat_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_candidat_traiteur` FOREIGN KEY (`traite_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candidats`
--

LOCK TABLES `candidats` WRITE;
/*!40000 ALTER TABLE `candidats` DISABLE KEYS */;
/*!40000 ALTER TABLE `candidats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `candidats_tour`
--

DROP TABLE IF EXISTS `candidats_tour`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `candidats_tour` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tour_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `candidat_id` int(11) NOT NULL,
  `statut_qualification` enum('participant','qualifie','elimine','vainqueur') NOT NULL DEFAULT 'participant',
  `nb_voix_obtenues` int(10) unsigned NOT NULL DEFAULT 0,
  `pourcentage_obtenu` decimal(5,2) NOT NULL DEFAULT 0.00,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tour_candidat` (`tour_id`,`candidat_id`),
  KEY `fk_ct_election` (`election_id`),
  KEY `fk_ct_candidat` (`candidat_id`),
  CONSTRAINT `fk_ct_candidat` FOREIGN KEY (`candidat_id`) REFERENCES `candidats` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ct_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ct_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours_election` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `candidats_tour`
--

LOCK TABLES `candidats_tour` WRITE;
/*!40000 ALTER TABLE `candidats_tour` DISABLE KEYS */;
/*!40000 ALTER TABLE `candidats_tour` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `decisions_bureau`
--

DROP TABLE IF EXISTS `decisions_bureau`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `decisions_bureau` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `tour_id` int(11) DEFAULT NULL,
  `type_decision` enum('egalite_persistante','proclamation_vainqueur','autre') NOT NULL DEFAULT 'egalite_persistante',
  `candidat_retenu_id` int(11) DEFAULT NULL,
  `decision` text NOT NULL,
  `motif` text NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `date_decision` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_db_election` (`election_id`),
  KEY `fk_db_tour` (`tour_id`),
  KEY `fk_db_candidat` (`candidat_retenu_id`),
  KEY `fk_db_admin` (`admin_id`),
  CONSTRAINT `fk_db_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_db_candidat` FOREIGN KEY (`candidat_retenu_id`) REFERENCES `candidats` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_db_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_db_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours_election` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `decisions_bureau`
--

LOCK TABLES `decisions_bureau` WRITE;
/*!40000 ALTER TABLE `decisions_bureau` DISABLE KEYS */;
/*!40000 ALTER TABLE `decisions_bureau` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `electeurs_election`
--

DROP TABLE IF EXISTS `electeurs_election`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `electeurs_election` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `membre_id` int(11) NOT NULL,
  `autorise` tinyint(1) NOT NULL DEFAULT 1,
  `motif` text DEFAULT NULL,
  `ajoute_par` int(11) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `mis_a_jour_le` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_electeur_election` (`election_id`,`membre_id`),
  KEY `fk_ee_membre` (`membre_id`),
  KEY `fk_ee_admin` (`ajoute_par`),
  CONSTRAINT `fk_ee_admin` FOREIGN KEY (`ajoute_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ee_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ee_membre` FOREIGN KEY (`membre_id`) REFERENCES `membres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `electeurs_election`
--

LOCK TABLES `electeurs_election` WRITE;
/*!40000 ALTER TABLE `electeurs_election` DISABLE KEYS */;
/*!40000 ALTER TABLE `electeurs_election` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `electeurs_tour`
--

DROP TABLE IF EXISTS `electeurs_tour`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `electeurs_tour` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tour_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `membre_id` int(11) NOT NULL,
  `autorise` tinyint(1) NOT NULL DEFAULT 1,
  `motif` text DEFAULT NULL,
  `modifie_par` int(11) DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  `mis_a_jour_le` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_electeur_tour` (`tour_id`,`membre_id`),
  KEY `fk_et_election` (`election_id`),
  KEY `fk_et_membre` (`membre_id`),
  KEY `fk_et_admin` (`modifie_par`),
  CONSTRAINT `fk_et_admin` FOREIGN KEY (`modifie_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_et_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_et_membre` FOREIGN KEY (`membre_id`) REFERENCES `membres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_et_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours_election` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `electeurs_tour`
--

LOCK TABLES `electeurs_tour` WRITE;
/*!40000 ALTER TABLE `electeurs_tour` DISABLE KEYS */;
/*!40000 ALTER TABLE `electeurs_tour` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `elections`
--

DROP TABLE IF EXISTS `elections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `elections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titre` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_ouverture_inscriptions` datetime DEFAULT NULL,
  `date_fermeture_inscriptions` datetime DEFAULT NULL,
  `date_ouverture_candidatures` datetime DEFAULT NULL,
  `date_fermeture_candidatures` datetime DEFAULT NULL,
  `date_debut_campagne` datetime DEFAULT NULL,
  `date_fin_campagne` datetime DEFAULT NULL,
  `date_debut_vote` datetime DEFAULT NULL,
  `date_fin_vote` datetime DEFAULT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `date_cloture_effective` datetime DEFAULT NULL,
  `statut` enum('preparation','ouverte','cloturee') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'preparation',
  `nombre_max_tours` int(10) unsigned NOT NULL DEFAULT 3,
  `tour_courant` int(10) unsigned NOT NULL DEFAULT 1,
  `etat_egalite_persistante` tinyint(1) NOT NULL DEFAULT 0,
  `vainqueur_candidat_id` int(11) DEFAULT NULL,
  `resultats_certifies` tinyint(1) NOT NULL DEFAULT 0,
  `date_certification` datetime DEFAULT NULL,
  `cree_par` int(11) DEFAULT NULL,
  `cree_le` datetime NOT NULL DEFAULT current_timestamp(),
  `mis_a_jour_le` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_election_createur` (`cree_par`),
  KEY `idx_elections_dernier` (`id`,`cree_le`),
  CONSTRAINT `fk_election_createur` FOREIGN KEY (`cree_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `elections`
--

LOCK TABLES `elections` WRITE;
/*!40000 ALTER TABLE `elections` DISABLE KEYS */;
/*!40000 ALTER TABLE `elections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_modifications_electeurs`
--

DROP TABLE IF EXISTS `journal_modifications_electeurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_modifications_electeurs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `tour_id` int(11) DEFAULT NULL,
  `membre_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `type_modification` enum('ajout','retrait','correction','initialisation') NOT NULL,
  `motif` text NOT NULL,
  `date_modification` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_jme_election` (`election_id`),
  KEY `fk_jme_tour` (`tour_id`),
  KEY `fk_jme_membre` (`membre_id`),
  KEY `fk_jme_admin` (`admin_id`),
  CONSTRAINT `fk_jme_admin` FOREIGN KEY (`admin_id`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_jme_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jme_membre` FOREIGN KEY (`membre_id`) REFERENCES `membres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_jme_tour` FOREIGN KEY (`tour_id`) REFERENCES `tours_election` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_modifications_electeurs`
--

LOCK TABLES `journal_modifications_electeurs` WRITE;
/*!40000 ALTER TABLE `journal_modifications_electeurs` DISABLE KEYS */;
/*!40000 ALTER TABLE `journal_modifications_electeurs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `membres`
--

DROP TABLE IF EXISTS `membres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `membres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `prenoms` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telephone` varchar(25) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date_naissance` date DEFAULT NULL,
  `anciennete` int(10) unsigned DEFAULT NULL,
  `fonction_pro` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `responsabilite_sas` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `statut` enum('actif','inactif') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'actif',
  `eligibilite_a_voter` enum('oui','non') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'non',
  `motif_non_eligibilite` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date_enregistrement` datetime NOT NULL DEFAULT current_timestamp(),
  `date_validation_eligibilite` datetime DEFAULT NULL,
  `valide_par` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telephone` (`telephone`),
  KEY `fk_membre_valideur` (`valide_par`),
  CONSTRAINT `fk_membre_valideur` FOREIGN KEY (`valide_par`) REFERENCES `administrateurs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `membres`
--

LOCK TABLES `membres` WRITE;
/*!40000 ALTER TABLE `membres` DISABLE KEYS */;
/*!40000 ALTER TABLE `membres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parametres`
--

DROP TABLE IF EXISTS `parametres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `parametres` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cle` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `valeur` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB AUTO_INCREMENT=229 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parametres`
--

LOCK TABLES `parametres` WRITE;
/*!40000 ALTER TABLE `parametres` DISABLE KEYS */;
INSERT INTO `parametres` VALUES (1,'app_name','SAS élection','Nom de l\'application'),(2,'app_subtitle','Tous unis au service du Christ','Devise du groupe'),(3,'contact_email','contact@sas-eglise.org','Email de contact du bureau'),(4,'contact_phone','+225 07 00 00 00 00','T├®l├®phone d\'assistance du bureau'),(9,'age_min_actif','1','Activer la condition d\'??ge minimum pour les ??lecteurs (1=Oui, 0=Non)'),(10,'age_min_valeur','18','??ge minimum requis pour ??tre ??lecteur (en ann??es)'),(11,'anciennete_min_actif','1','Activer la condition d\'anciennet?? minimum pour les ??lecteurs (1=Oui, 0=Non)'),(12,'anciennete_min_valeur','2','Anciennet?? minimum requise pour ??tre ??lecteur (en ann??es)'),(13,'candidature_conditions_communes','1','Utiliser les m??mes conditions d\'??ge et d\'anciennet?? pour les candidats (1=Oui, 0=Non)'),(14,'candidature_age_min_actif','1','Activer la condition d\'??ge minimum pour les candidats (1=Oui, 0=Non)'),(15,'candidature_age_min_valeur','18','??ge minimum autoris?? pour d??poser une candidature (en ann??es)'),(16,'candidature_anciennete_min_actif','1','Activer la condition d\'anciennet?? minimum pour les candidats (1=Oui, 0=Non)'),(17,'candidature_anciennete_min_valeur','2','Anciennet?? minimum requise pour d??poser une candidature (en ann??es)'),(18,'candidature_paiement_requis','1','Exiger le paiement du droit de candidature (1=Oui, 0=Non)');
/*!40000 ALTER TABLE `parametres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `participations`
--

DROP TABLE IF EXISTS `participations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `participations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `tour_id` int(11) DEFAULT NULL,
  `membre_id` int(11) NOT NULL,
  `statut` enum('non_vote','a_vote') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'a_vote',
  `date_vote` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tour_membre` (`tour_id`,`membre_id`),
  KEY `fk_participation_membre` (`membre_id`),
  KEY `idx_participation_election` (`election_id`),
  CONSTRAINT `fk_participation_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_participation_membre` FOREIGN KEY (`membre_id`) REFERENCES `membres` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `participations`
--

LOCK TABLES `participations` WRITE;
/*!40000 ALTER TABLE `participations` DISABLE KEYS */;
/*!40000 ALTER TABLE `participations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `libelle` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super_admin','Super Administrateur','Accès total à toutes les fonctionnalités et à la gestion des administrateurs'),(2,'admin','Administrateur','Accès aux fonctions de gestion du scrutin (membres, candidatures, élections, participations, résultats)');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tours_election`
--

DROP TABLE IF EXISTS `tours_election`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tours_election` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `numero_tour` int(10) unsigned NOT NULL DEFAULT 1,
  `libelle` varchar(120) DEFAULT NULL,
  `date_debut_vote` datetime NOT NULL,
  `date_fin_vote` datetime NOT NULL,
  `statut` enum('preparation','ouverte','cloturee') NOT NULL DEFAULT 'preparation',
  `est_egalite` tinyint(1) NOT NULL DEFAULT 0,
  `date_cloture_effective` datetime DEFAULT NULL,
  `date_creation` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_election_numero_tour` (`election_id`,`numero_tour`),
  CONSTRAINT `fk_tour_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tours_election`
--

LOCK TABLES `tours_election` WRITE;
/*!40000 ALTER TABLE `tours_election` DISABLE KEYS */;
/*!40000 ALTER TABLE `tours_election` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `votes`
--

DROP TABLE IF EXISTS `votes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `election_id` int(11) NOT NULL,
  `tour_id` int(11) DEFAULT NULL,
  `candidat_id` int(11) NOT NULL,
  `date_vote` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_vote_election` (`election_id`),
  KEY `fk_vote_candidat` (`candidat_id`),
  KEY `idx_vote_tour` (`tour_id`),
  CONSTRAINT `fk_vote_candidat` FOREIGN KEY (`candidat_id`) REFERENCES `candidats` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_vote_election` FOREIGN KEY (`election_id`) REFERENCES `elections` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `votes`
--

LOCK TABLES `votes` WRITE;
/*!40000 ALTER TABLE `votes` DISABLE KEYS */;
/*!40000 ALTER TABLE `votes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'sas_election'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 15:05:12
