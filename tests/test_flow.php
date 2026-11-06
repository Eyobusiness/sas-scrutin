<?php
/**
 * Test fonctionnel automatisé de la logique métier SAS Élection
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../app/Models/Administrateur.php';
require_once __DIR__ . '/../app/Models/Membre.php';
require_once __DIR__ . '/../app/Models/Election.php';
require_once __DIR__ . '/../app/Models/Candidat.php';
require_once __DIR__ . '/../app/Models/Participation.php';
require_once __DIR__ . '/../app/Models/Vote.php';
require_once __DIR__ . '/../app/Services/ResultatService.php';

$pdo = Database::getConnection();

echo "\n--- DÉBUT DES TESTS FONCTIONNELS AUTOMATISÉS ---\n\n";

$testsTotal = 0;
$testsPassed = 0;

function assertCondition(bool $condition, string $testName) {
    global $testsTotal, $testsPassed;
    $testsTotal++;
    if ($condition) {
        $testsPassed++;
        echo " [OK] " . $testName . "\n";
    } else {
        echo " [ECHEC] " . $testName . "\n";
    }
}

// 1. Test Authentification
$adminModel = new Administrateur($pdo);
$superAdmin = $adminModel->findByTelephone('0700000001');
assertCondition($superAdmin !== null, "Super Administrateur trouvé par téléphone");
assertCondition($superAdmin['role_code'] === 'super_admin', "Rôle super_admin vérifié");
assertCondition(password_verify('Admin@2026', $superAdmin['mot_de_passe']), "Vérification password_verify() avec hash bcrypt");

// 2. Test Élection
$electionModel = new Election($pdo);
$election = $electionModel->findById(1);
assertCondition($election !== null, "Élection #1 trouvée");
assertCondition($election['statut'] === 'ouverte', "Élection #1 a le statut 'ouverte'");

$canVoteCheck = $electionModel->checkCanVote($election);
assertCondition($canVoteCheck['can_vote'] === true, "checkCanVote() confirme l'ouverture du scrutin");

// 3. Test Membres et Règle d'éligibilité
$membreModel = new Membre($pdo);
$membreEligible = $membreModel->findByTelephone('0701010101');
assertCondition($membreEligible !== null && $membreEligible['eligibilite_a_voter'] === 'oui', "Membre éligible identifié (0701010101)");

$membreNonEligible = $membreModel->findByTelephone('0704040404');
assertCondition($membreNonEligible !== null && $membreNonEligible['eligibilite_a_voter'] === 'non', "Membre non-éligible identifié (0704040404)");

// 4. Test Candidats
$candidatModel = new Candidat($pdo);
$candidats = $candidatModel->getValidatedByElection(1);
assertCondition(count($candidats) === 3, "3 candidats validés sur le bulletin de vote");

// 5. Test Transaction de vote
$voteModel = new Vote($pdo);
$participationModel = new Participation($pdo);

// Nettoyer les votes précédents sur ce membre de test
$pdo->exec("DELETE FROM participations WHERE election_id = 1 AND membre_id = " . (int)$membreEligible['id']);

// A. Test vote d'un membre non éligible (doit être refusé)
[$successNonEligible, $msgNonEligible] = $voteModel->castVote(1, (int)$candidats[0]['id'], (int)$membreNonEligible['id']);
assertCondition($successNonEligible === false, "Refus du vote pour un membre non éligible (Attendu: FALSE)");

// B. Test premier vote valide
[$success1, $msg1] = $voteModel->castVote(1, (int)$candidats[0]['id'], (int)$membreEligible['id']);
assertCondition($success1 === true, "Premier vote valide enregistré avec succès : {$msg1}");

// Vérification de l'émargement
$hasVoted = $participationModel->hasVoted(1, (int)$membreEligible['id']);
assertCondition($hasVoted === true, "Participation / émargement correctement enregistré");

// C. Test tentative de double vote (doit être rejeté)
[$successDouble, $msgDouble] = $voteModel->castVote(1, (int)$candidats[1]['id'], (int)$membreEligible['id']);
assertCondition($successDouble === false, "Tentative de double vote bloquée : {$msgDouble}");

// 6. Test Dépouillement et Résultats
$resultatService = new ResultatService($pdo);
$res = $resultatService->getResultats(1);
assertCondition($res !== null, "Calcul des résultats exécuté");
assertCondition($res['total_bulletins'] >= 1, "Au moins 1 bulletin comptabilisé dans les résultats");
assertCondition($res['candidats'][0]['id'] === $candidats[0]['id'], "Le candidat ayant reçu le vote est en tête");

echo "\n--- RÉSULTATS DES TESTS : {$testsPassed} / {$testsTotal} RÉUSSIS ---\n\n";

if ($testsPassed === $testsTotal) {
    exit(0);
} else {
    exit(1);
}
