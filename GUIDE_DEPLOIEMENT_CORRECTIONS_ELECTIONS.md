# Guide de déploiement — corrections électorales

Cette procédure vise à déployer les corrections de cycle électoral documentées dans [le rapport technique](./RAPPORT_TECHNIQUE_ELECTIONS.md). Elle ne remplace pas la procédure générale d'installation ou d'hébergement du projet.

## 1. Préparer l'environnement

1. Déployer d'abord sur une préproduction reproduisant la version PHP, MySQL/MariaDB et le schéma de la production.
2. Créer une sauvegarde vérifiée de la base de données et conserver une copie des fichiers applicatifs actuellement en service.
3. Vérifier que la sauvegarde et la préproduction ne contiennent pas de données de production exposées aux tests.
4. Examiner `config/database.php` sur la cible sans écraser sa configuration locale. Ne pas copier de secrets dans le dépôt, les commandes partagées ou les journaux.
5. Vérifier que les tables multi-tours et décisions du bureau attendues par l'application sont présentes. Ce lot ne fournit pas de migration SQL.

## 2. Déployer le code

1. Publier les fichiers PHP, vues et documentation listés dans le rapport technique.
2. Préserver la configuration propre à l'environnement, les médias téléversés et les autres données persistantes.
3. Ne pas importer `database/schema.sql` par-dessus une base existante. Si le schéma cible est ancien, arrêter le déploiement et préparer une migration dédiée avec sauvegarde et validation.
4. Vider les caches applicatifs éventuels si l'hébergement en utilise.

## 3. Vérifications avant ouverture

Exécuter le lint PHP sur les fichiers modifiés dans l'environnement cible, puis contrôler le journal PHP/Apache et la connexion à la base sans afficher de mots de passe ni d'informations sensibles.

Sur une copie isolée de la base, exécuter les tests d'intégration concernés :

```powershell
php tests/test_prompt7_scenarios.php
php tests/test_multi_tours_scenarios.php
```

Ces scripts font du nettoyage de données de test. Ne jamais les lancer sur la base de production, ni sur une base contenant des données à conserver. En cas d'échec ou d'impossibilité de test, ne pas annoncer le déploiement comme validé.

## 4. Recette fonctionnelle sur la préproduction

- Clôturer un tour sans certifier : vérifier que les résultats ne sont pas publiés au public.
- Certifier un résultat : vérifier qu'il devient consultable publiquement.
- Clôturer une égalité persistante : vérifier son affichage public sans vainqueur ni certification.
- Préparer un tour manuel après clôture : vérifier l'ordre des tours, les dates, les candidats qualifiés obligatoires et le rejet d'une exception sans décision de type « Autre » correctement motivée.
- Ouvrir le tour préparé et vérifier que le vote n'est accepté que pendant sa période.
- Vérifier que les tours précédents, leurs votes et leur historique restent consultables après la préparation du tour suivant.
- Tester les rôles et protections CSRF sur les nouvelles actions administratives.

## 5. Mise en production et retour arrière

1. N'effectuer la mise en production qu'après réussite des tests isolés et de la recette.
2. Déployer le même ensemble de fichiers validé en préproduction en préservant la configuration et les médias.
3. Vérifier les pages administratives et publiques, les journaux, et la disponibilité de la base sans lancer de tests destructifs.
4. En cas d'anomalie, remettre les fichiers applicatifs sauvegardés. Restaurer la base uniquement si une modification de données est survenue et selon la procédure de reprise validée; ce lot ne requiert pas de changement de schéma.

## État de validation de ce lot

Le lint PHP et `git diff --check` ont réussi en local. Les tests d'intégration MySQL et la recette de préproduction restent à exécuter; l'accès à la base locale n'étant pas disponible lors des corrections, cette livraison ne peut pas être qualifiée de validée en production.
