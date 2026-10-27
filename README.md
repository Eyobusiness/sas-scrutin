# SAS Élection — « Tous unis au service du Christ »

Application web complète et sécurisée d'organisation et de gestion du scrutin pour l'élection du président du groupe **SAS** (« Tous unis au service du Christ »).

Ce projet est développé en **PHP 8 orienté objet (POO)**, **PDO**, **MySQL / MariaDB**, et réutilise le template d'administration **Bootstrap 5.3.8 (adminHMD)** avec son thème personnalisé, son dark/light mode et ses composants visuels.

---

## 1. Fonctionnalités Principales

### A. Espace Public & Scrutin Électronique
- **Portail d'accueil (`index.php?page=home`)** : Présentation du scrutin, élection active, directives électorales et accès rapides.
- **Auto-inscription des membres (`index.php?page=inscription`)** : Enregistrement des membres avec numéro de téléphone unique. Statut d'éligibilité mis par défaut en attente de validation par le bureau.
- **Dépôt de candidature (`index.php?page=candidater`)** : Dépôt public de candidature avec profession de foi et téléversement sécurisé de photo (formats acceptés : JPG, JPEG, PNG, WEBP avec vérification stricte MIME). Statut initial en attente.
- **Vote électronique sécurisé (`index.php?page=voter`)** :
  - **Étape 1** : Saisie du numéro de téléphone. Vérification que l'élection est ouverte et en cours de validité horaire. Vérification de l'existence du membre et de son éligibilité (`eligibilite_a_voter = 'oui'`). Vérification d'absence de vote préalable.
  - **Étape 2** : Bulletin de vote affichant uniquement les candidats validés (photos, nom, prénoms, profession de foi). Sélection interactive et confirmation solennelle.
  - **Enregistrement par Transaction SQL Atomique** : Enregistrement du suffrage dans la table `votes` (totalement anonymisée, sans référence au votant) et marquage simultané de l'émargement dans `participations` (`statut = 'a_vote'`).
- **Résultats officiels certifiés (`index.php?page=resultats`)** : Contrôle strict côté serveur. **Aucun résultat n'est diffusé publiquement avant la clôture officielle de l'élection**. Proclamation du vainqueur, calcul des pourcentages, taux de participation et gestion des égalités (ex æquo).

### B. Espace Administration Privé
- **Authentification & Sessions (`index.php?page=login`)** : Connexion par numéro de téléphone et mot de passe hashé (`password_hash` / `password_verify`). Régénération sécurisée de l'ID de session, cookies protégés (`HttpOnly`, `SameSite=Lax`). Protection CSRF sur l'ensemble des formulaires.
- **Tableau de bord (`index.php?page=dashboard`)** : KPI en temps réel (inscrits, éligibles, votants, taux de participation, alertes scrutin en cours).
- **Gestion des Membres (`index.php?page=membres`)** :
  - Recherche instantanée et filtres combinés (nom, prénom, téléphone, éligibilité, statut actif/inactif).
  - Validation manuelle de l'éligibilité au vote par le bureau avec enregistrement du motif et de l'administrateur validateur.
  - Règle métier stricte : **Seul le champ `eligibilite_a_voter = 'oui'` autorise le vote** (l'âge ou le statut ne bloquent pas un membre déclaré éligible).
  - Privilégie la désactivation à la suppression définitive pour l'historique électoral.
- **Gestion des Élections (`index.php?page=elections`)** :
  - Création, modification et suivi des scrutins.
  - Cycle de vie : `preparation` &rarr; `ouverte` &rarr; `cloturee`.
  - Possibilité de clôture anticipée avec confirmation solennelle.
- **Gestion des Candidatures (`index.php?page=candidats`)** :
  - Examen des dossiers de candidature.
  - Statuts : `en_attente`, `validee`, `rejetee` (avec motif obligatoire), `retiree`.
  - Seules les candidatures validées sont intégrées aux bulletins de vote.
- **Registre des Participations (`index.php?page=participations`)** :
  - Consultation de la liste nominative des émargements (nom, prénom, téléphone, date et heure du vote).
  - Garantie de confidentialité : dissociation totale de l'identité du votant et du candidat choisi.
- **Dépouillement en Direct (`index.php?page=resultats_admin`)** :
  - Résultats en temps réel réservés aux administrateurs autorisés pendant le scrutin.
  - Graphiques de progression et pourcentages automatisés.
- **Gestion des Administrateurs (`index.php?page=admins`)** :
  - Réservée au rôle `super_admin`.
  - Création, modification, attribution de rôle (`super_admin`, `admin`) et activation/désactivation de comptes.
- **Paramètres de l'Application (`index.php?page=settings`)** :
  - Personnalisation du nom de l'application, devise du groupe, téléphone et email d'assistance.

---

## 2. Arborescence du Projet

```text
sas-scrutin/
├── app/
│   ├── Controllers/
│   │   ├── AdminController.php
│   │   ├── AuthController.php
│   │   ├── CandidatController.php
│   │   ├── DashboardController.php
│   │   ├── ElectionController.php
│   │   ├── MembreController.php
│   │   ├── PublicController.php
│   │   └── VoteAdminController.php
│   ├── Helpers/
│   │   ├── AuthHelper.php
│   │   ├── SecurityHelper.php
│   │   └── SessionHelper.php
│   ├── Models/
│   │   ├── Administrateur.php
│   │   ├── BaseModel.php
│   │   ├── Candidat.php
│   │   ├── Election.php
│   │   ├── Membre.php
│   │   ├── Parametre.php
│   │   ├── Participation.php
│   │   └── Vote.php
│   └── Services/
│       └── ResultatService.php
├── assets/
│   ├── css/
│   │   ├── bootstrap.min.css (v5.3.8)
│   │   └── style.css (Thème adminHMD)
│   ├── js/
│   │   ├── bootstrap.bundle.min.js
│   │   └── main.js
│   └── vendors/
│       └── bootstrap-icons/
├── config/
│   └── database.php (Connexion PDO Singleton)
├── database/
│   ├── schema.sql (Structure officielle InnoDB utf8mb4)
│   ├── create_super_admin.php (Script CLI/Web sécurisé)
│   └── seed_demo.php (Données de test complètes)
├── tests/
│   └── test_flow.php (Suite de tests automatisés PHP)
├── uploads/
│   └── candidats/ (Photos téléversées sécurisées)
├── views/
│   ├── admins/ (Gestion admins et paramètres)
│   ├── auth/ (Page de connexion par téléphone)
│   ├── candidats/ (Gestion et examen des candidatures)
│   ├── dashboard/ (Tableau de bord et KPI)
│   ├── elections/ (Gestion et détails des élections)
│   ├── layout/ (En-tête, sidebar et pied de page réutilisables)
│   ├── membres/ (Registre électoral et validation d'éligibilité)
│   ├── participations/ (Registre nominatif d'émargement)
│   ├── public/ (Portail de vote, inscription, candidature, résultats)
│   └── resultats/ (Dépouillement direct administration)
├── index.php (Routeur central sécurisé)
└── README.md
```

---

## 3. Installation et Lancement sous XAMPP (Windows)

### Prérequis
- **XAMPP** pour Windows avec **Apache**, **PHP 8.2+** et **MySQL / MariaDB**.
- L'application doit être placée dans : `C:\xampp\htdocs\sas-scrutin\`.

### Étape 1 : Démarrer Apache et MySQL
1. Ouvrez le **Panneau de contrôle XAMPP**.
2. Démarrez les modules **Apache** et **MySQL**.

### Étape 2 : Importer la Base de Données
Dans un terminal PowerShell ou Invite de commandes Windows :
```powershell
cmd.exe /c "C:\xampp\mysql\bin\mysql.exe -u root < c:\xampp\htdocs\sas-scrutin\database\schema.sql"
```
*Ou directement via phpMyAdmin (`http://localhost/phpmyadmin/`) : importer le fichier `database/schema.sql`.*

### Étape 3 : Créer le Premier Compte Super Administrateur
Vous pouvez au choix :
- **En ligne de commande** :
  ```powershell
  cd c:\xampp\htdocs\sas-scrutin
  php database/create_super_admin.php
  ```
  Le script interactif vous demandera votre nom, prénom, numéro de téléphone et mot de passe.
- **Via le navigateur** :
  Ouvrez l'URL : `http://localhost/sas-scrutin/database/create_super_admin.php` et remplissez le formulaire.

### Étape 4 (Optionnelle) : Charger les données de démonstration
Pour tester immédiatement l'ensemble des modules :
```powershell
php database/seed_demo.php
```

### Étape 5 : Accéder à l'application
- **Portail public (Vote & Inscription)** :
  `http://localhost/sas-scrutin/index.php?page=home`
- **Espace Administration** :
  `http://localhost/sas-scrutin/index.php?page=login`

---

## 4. Identifiants de Démonstration (Suite au Seeder)

> [!WARNING]
> Ces comptes de démonstration ont été générés pour vous permettre de tester immédiatement la plateforme. Il est impératif de changer les mots de passe avant toute utilisation en production.

| Rôle | Nom & Prénoms | Téléphone (Identifiant) | Mot de passe |
| :--- | :--- | :--- | :--- |
| **Super Admin** | Administrateur Principal | `0173832620` | `Admin2026@` |

### Numéros de test pour le vote électronique :
- **Membres Éligibles (`eligibilite = oui`)** :
  - `0701010101` (KOUASSI Marc Antoine)
  - `0702020202` (YAO Patricia)
  - `0703030303` (N'GUESSAN David)
  - `0705050505` (KOUAME Emmanuel)
- **Membre Non Éligible pour tester le blocage (`eligibilite = non`)** :
  - `0704040404` (KONE Aminata) &rarr; message immédiat : *« Votre droit de vote n'a pas été validé par le bureau »*.

---

## 5. Exécution des Tests Automatisés

Le banc de test automatisé valide l'intégralité du workflow :
```powershell
php tests/test_flow.php
```
**Résultat des tests :** 16 / 16 assertions validées avec succès :
- Vérification de l'authentification et du hash bcrypt.
- Vérification temporelle et statut de l'élection (`checkCanVote`).
- Vérification du filtre des candidats validés.
- Rejet systématique du vote pour un membre non éligible.
- Enregistrement atomique du vote valide par transaction SQL.
- Enregistrement de l'émargement dans `participations`.
- Blocage strict de la double soumission (unicité élection/membre).
- Dépouillement, calcul des pourcentages et détermination du vainqueur.

---

## 6. Sécurité Implémentée

1. **Requêtes Préparées PDO** : Zéro concaténation SQL, protection contre les injections SQL.
2. **Échappement XSS** : Toutes les sorties dynamiques utilisent `SecurityHelper::e()` (`htmlspecialchars` avec encodage `UTF-8`).
3. **Protection CSRF** : Jeton cryptographique vérifié avec `hash_equals` sur tous les formulaires d'action.
4. **Anonymat du Vote** : Séparation stricte entre `votes` (bulletins sans identifiant membre) et `participations` (registre d'émargement avec contrainte d'unicité `(election_id, membre_id)`).
5. **Transactions SQL ACID** : Les opérations de vote et d'émargement sont exécutées dans un bloc `beginTransaction` / `commit` avec verrou `FOR UPDATE` et rollback automatique en cas d'erreur.
6. **Sécurité des Téléversements** : Vérification de la taille (max 3 Mo), de l'extension et du type MIME réel via `finfo` avec renommage aléatoire sécurisé.
7. **Contrôle des Accès** : Vérification systématique des rôles (`super_admin` vs `admin`) et de l'état actif du compte sur chaque route.
