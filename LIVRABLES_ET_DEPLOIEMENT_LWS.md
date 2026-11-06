# LIVRABLES, DOCUMENTATION FONCTIONNELLE, RAPPORT DE TESTS & PROCÉDURE DE DÉPLOIEMENT LWS

Ce document récapitule l'ensemble des évolutions réalisées en local sur **SAS Élection** conformément au cahier des charges d'évolution et de finalisation de la plateforme (gestion multi-tours, qualification automatique, gestion exceptionnelle des électeurs, affichage des égalités en orange et des victoires en vert, historique complet).

---

## 1. Liste complète des fichiers créés et modifiés

### A. Scripts Base de Données (`database/`)
1. `database/migration_v4_multi_tours.sql` *(Nouveau — à exécuter sur phpMyAdmin LWS pour faire évoluer une base existante sans perte de données)*
2. `database/export_sas_election_complet.sql` *(Nouveau — export SQL complet de la base locale mise à jour avec ses 14 tables)*
3. `database/schema.sql` *(Modifié — schéma de référence complet incluant les 14 tables)*

### B. Services Métier (`app/Services/`)
4. `app/Services/TourElectionService.php` *(Nouveau — gestion des tours `tours_election` et des candidats par tour `candidats_tour`)*
5. `app/Services/ElecteurTourService.php` *(Nouveau — gestion des listes `electeurs_election` et `electeurs_tour`, interventions exceptionnelles du bureau avant ouverture, verrouillage après ouverture, traçabilité dans `journal_modifications_electeurs`, et génération du message détaillé de non-éligibilité)*
6. `app/Services/QualificationService.php` *(Nouveau — clôture d'un tour, calcul des scores, qualification automatique des ex æquo en tête vers le tour suivant, détection de victoire unique, gestion de l'« Égalité persistante » après `nombre_max_tours`, et enregistrement des décisions officielles du bureau dans `decisions_bureau`)*
7. `app/Services/VoteService.php` *(Modifié — authentification en 2 étapes liée au `tour_id` actif, autorisation d'un électeur ayant voté au Tour 1 de voter au Tour 2, et affichage des motifs exacts de non-autorisation)*
8. `app/Services/ResultatService.php` *(Modifié — calcul des résultats par tour, conservation de l'historique complet `tours_history`, message d'égalité `"ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !"` et phrase exacte de proclamation du vainqueur)*

### C. Modèles (`app/Models/`)
9. `app/Models/Election.php` *(Modifié — prise en charge de `nombre_max_tours`, `tour_courant`, `etat_egalite_persistante`, `vainqueur_candidat_id` et synchronisation automatique du Tour 1)*
10. `app/Models/Participation.php` *(Modifié — gestion de `tour_id` et vérification `hasVoted()` par tour)*
11. `app/Models/Vote.php` *(Modifié — enregistrement anonyme du bulletin avec `tour_id`, vérification de la qualification du candidat sur le tour actif et de l'autorisation de l'électeur sur le tour)*

### D. Contrôleurs & Routeur (`index.php` & `app/Controllers/`)
12. `index.php` *(Modifié — ajout des routes administratives `election_cloturer_tour`, `election_update_tour`, `election_electeur_tour`, `election_decision_bureau`)*
13. `app/Controllers/ElectionController.php` *(Modifié — méthodes `cloturerTour()`, `updateTour()`, `modifierElecteurTour()`, `enregistrerDecisionBureau()`, et enrichissement de `details()`)*
14. `app/Controllers/PublicController.php` *(Modifié — `voter()` charge le tour actif et ses candidats qualifiés ; `resultats()` permet de consulter les résultats par tour)*
15. `app/Controllers/VoteAdminController.php` *(Modifié — `participations()` et `resultats()` filtrent et affichent les données par tour)*

### E. Vues & Feuilles de style (`views/` & `assets/css/`)
16. `views/public/resultats.php` *(Modifié — sélecteur de tour, bandeau d'égalité orange sans coupe avec `"ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !"`, bandeau de victoire vert avec coupe et phrase officielle exacte, historique complet des tours et décisions du bureau)*
17. `views/resultats/admin.php` *(Modifié — sélecteur de tour, bandeau d'égalité orange avec `"ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !"`, bandeau de victoire vert avec phrase exacte, barres de progression oranges pour les ex æquo, historique complet des tours)*
18. `views/elections/index.php` *(Modifié — configuration de `nombre_max_tours` à la création/modification et affichage du badge `Tour X / Max Y`)*
19. `views/elections/details.php` *(Modifié — gestion des tours et qualification automatique, formulaire de décision du bureau en cas d'égalité persistante, gestion exceptionnelle de la liste électorale par tour avant ouverture et journal d'audit)*
20. `views/public/voter.php` *(Modifié — affichage du tour actif sur l'en-tête du vote + popup moderne de confirmation du vote)*
21. `views/participations/index.php` *(Modifié — filtre et colonne par tour de scrutin)*
22. `assets/css/results.css` *(Modifié — styles oranges pour les égalités et styles verts pour les victoires)*
23. `assets/css/admin-results.css` *(Modifié — styles oranges pour les égalités et styles verts pour les victoires dans l'administration)*
24. `assets/css/animations.css` & `assets/js/main.js` *(Animations premium et interactions fluides)*

---

## 2. Documentation des nouvelles fonctionnalités

1. **Gestion de plusieurs tours au sein d'une même élection (`tours_election`, `candidats_tour`, `nombre_max_tours`)** :
   - Chaque élection possède un paramètre configurable `nombre_max_tours` (par défaut `3`).
   - Le **1er Tour** est créé automatiquement dès la création de l'élection avec tous les candidats validés.
2. **Qualification automatique entre les tours (`QualificationService`)** :
   - À la clôture d'un tour :
     - Si **un seul candidat** obtient le meilleur score (`max_voix > 0`), il est proclamé vainqueur et l'élection est clôturée (aucun nouveau tour n'est créé).
     - Si **au moins 2 candidats** sont à égalité au 1er rang et que `numero_tour < nombre_max_tours` : tous les candidats ayant obtenu ce meilleur score sont automatiquement qualifiés, les candidats ayant un score inférieur sont écartés, et le tour suivant (`numero_tour + 1`) est préparé automatiquement avec uniquement les candidats qualifiés.
     - Si l'égalité persiste alors que `numero_tour >= nombre_max_tours` : aucun vainqueur n'est désigné automatiquement, l'élection passe en état **« Égalité persistante »**, et le bureau peut enregistrer une décision officielle motivée (`decisions_bureau`) sans jamais effacer l'historique des tours.
3. **Gestion exceptionnelle des électeurs par tour (`electeurs_election`, `electeurs_tour`, `journal_modifications_electeurs`)** :
   - Avant l'ouverture d'un tour (`statut = 'preparation'`), le bureau peut exceptionnellement **ajouter**, **retirer** ou **corriger** un électeur sur la liste du tour.
   - Chaque intervention exige un **motif obligatoire** et enregistre automatiquement dans `journal_modifications_electeurs` l'administrateur responsable, la date/heure, le type de modification, le motif, l'élection et le tour.
   - Dès que le tour est ouvert (`ouverte`) ou clôturé (`cloturee`), sa liste électorale est automatiquement **verrouillée**.
4. **Participation par tour & Secret absolu du vote** :
   - Un électeur ayant voté au 1er Tour peut voter au 2e Tour (unicité sur `(tour_id, membre_id)` dans `participations`).
   - La table `votes` contient `tour_id` mais ne contient jamais `membre_id`, garantissant l'anonymat complet des suffrages.
5. **Message détaillé de non-éligibilité** :
   - Format exact : `« Vous n'êtes pas autorisé(e) à participer à ce scrutin parce que : [motif enregistré dans la base de données]. »`
6. **Affichage des résultats — Cas d'égalité (Orange) & Cas de victoire (Vert)** :
   - **Égalité** : couleur **orange** appliquée aux ex æquo en tête, aucune coupe attribuée, histogrammes de même dimension, et message obligatoire **`« ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC ! »`**.
   - **Victoire** : couleur **verte** appliquée au vainqueur, coupe affichée sur sa carte (#1), et phrase exacte :
     **`« Les votes du scrutin "[NOM DU SCRUTIN]" : vainqueur [NOM DU CANDIDAT] avec [NOMBRE DE VOIX] voix ([POURCENTAGE] %). »`**

---

## 3. Rapport de tests automatisés exécutés en local

Tous les scripts de tests ont été exécutés en local sur PHP 8 / MySQL :
- `tests/test_multi_tours_scenarios.php` : **26 / 26 tests validés** (création multi-tours, égalité 5-5-3 au Tour 1 -> qualification automatique de A et B au Tour 2 et élimination de C, vote au Tour 2 par les votants du Tour 1, gestion exceptionnelle d'électeurs avant ouverture + verrouillage après ouverture + journal d'audit, messages détaillés de non-éligibilité, rendu orange sans coupe avec `"ATTENDRE LES INSTRUCTIONS DU COMITÉ AD HOC !"`, rendu vert avec coupe et phrase exacte de victoire, égalité persistante après `nombre_max_tours` + décision du bureau).
- `tests/test_prompt6_scenarios.php` : **25 / 25 tests validés**.
- `tests/test_prompt7_scenarios.php` : **22 / 22 tests validés**.
- `tests/test_resultats_scenarios.php` : **23 / 23 tests validés**.
- `tests/test_admin_results_render.php` : **11 / 11 tests validés**.
- `tests/test_closed_render.php` : **9 / 9 tests validés**.
- **Total global : 116 / 116 assertions automatisées réussies (0 erreur, 0 warning PHP).**

---

## 4. Instructions de déploiement sur LWS et précautions de sécurité

### Étape 1 : Sauvegarde préalable obligatoire sur LWS (Sécurité Production)
1. Connectez-vous à votre espace **phpMyAdmin LWS**.
2. Sélectionnez votre base de données de production et cliquez sur l'onglet **Exporter** -> **Exécuter** pour télécharger une sauvegarde `.sql` complète de la base de production actuelle avant toute intervention.

### Étape 2 : Mise à jour de la base de données sur LWS (Sans perte de données)
- **Si votre base LWS contient déjà des données (membres, administrateurs, élections)** :
  1. Dans phpMyAdmin LWS, sélectionnez votre base de données.
  2. Cliquez sur l'onglet **Importer** (ou **SQL**).
  3. Importez le fichier **`database/migration_v4_multi_tours.sql`**. Ce script est idempotent : il ajoute les nouvelles colonnes (`nombre_max_tours`, `tour_courant`, `etat_egalite_persistante`, `vainqueur_candidat_id`, `tour_id`), crée les nouvelles tables (`tours_election`, `candidats_tour`, `electeurs_election`, `electeurs_tour`, `journal_modifications_electeurs`, `decisions_bureau`) et initialise automatiquement le `1er Tour` pour les élections existantes sans effacer aucune donnée.
- **Si vous installez sur une base LWS vierge** :
  - Importez directement **`database/export_sas_election_complet.sql`** (ou `database/schema.sql`).

### Étape 3 : Envoi des fichiers PHP / CSS / JS par FTP (FileZilla ou Gestionnaire LWS)
Transférez sur le serveur LWS (en respectant l'arborescence) les fichiers suivants :
- `index.php`
- `app/Models/Election.php`
- `app/Models/Participation.php`
- `app/Models/Vote.php`
- `app/Services/TourElectionService.php`
- `app/Services/ElecteurTourService.php`
- `app/Services/QualificationService.php`
- `app/Services/VoteService.php`
- `app/Services/ResultatService.php`
- `app/Controllers/ElectionController.php`
- `app/Controllers/PublicController.php`
- `app/Controllers/VoteAdminController.php`
- `views/public/resultats.php`
- `views/public/voter.php`
- `views/resultats/admin.php`
- `views/elections/index.php`
- `views/elections/details.php`
- `views/participations/index.php`
- `assets/css/results.css`
- `assets/css/admin-results.css`
- `assets/css/animations.css`
- `assets/js/main.js`

> **Attention :** Ne remplacez **pas** votre fichier `config/database.php` de production sur LWS afin de conserver vos identifiants MySQL LWS intacts.

