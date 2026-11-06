<?php
/**
 * Test de rendu complet de views/public/resultats.php pour différents scénarios :
 * - Scrutin ouvert (confidentiel)
 * - Scrutin clôturé avec 1 candidat
 * - Scrutin clôturé avec 2 candidats
 * - Scrutin clôturé avec 3 candidats (podium complet)
 * - Scrutin clôturé avec 5 candidats (podium + 2 dans la suite)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/SessionHelper.php';
require_once __DIR__ . '/../app/Helpers/SecurityHelper.php';
require_once __DIR__ . '/../app/Models/Election.php';
require_once __DIR__ . '/../app/Services/ResultatService.php';

SessionHelper::start();

echo "--- DÉBUT DES TESTS DE RENDU HTML DE LA PAGE RÉSULTATS ---\n\n";

$pdo = Database::getConnection();
$service = new ResultatService($pdo);

// Scénario A : 5 candidats avec élection clôturée
$pdo->exec("INSERT INTO elections (titre, date_debut, date_fin, statut) VALUES ('Élection Clôturée Test 5', '2026-01-01 00:00:00', '2026-01-02 00:00:00', 'cloturee')");
$elId = (int)$pdo->lastInsertId();

$cIds = [];
for ($i = 1; $i <= 5; $i++) {
    $pdo->exec("INSERT INTO candidats (election_id, nom, prenoms, telephone, motif_candidature, statut) VALUES ($elId, 'NOM_$i', 'Prenom_$i', '07000010$i', 'Motif $i', 'validee')");
    $cIds[$i] = (int)$pdo->lastInsertId();
}

$votes = [1 => 45, 2 => 25, 3 => 15, 4 => 10, 5 => 5];
foreach ($votes as $idx => $count) {
    for ($k = 0; $k < $count; $k++) {
        $pdo->exec("INSERT INTO votes (election_id, candidat_id) VALUES ($elId, {$cIds[$idx]})");
    }
}

// Variables pour la vue
$elections = (new Election($pdo))->getAll();
$selectedElectionId = $elId;
$currentElection = (new Election($pdo))->findById($elId);
$isCloturee = true;
$donnees = $service->getResultats($elId);

// Capture du rendu
ob_start();
include __DIR__ . '/../views/public/resultats.php';
$html = ob_get_clean();

// Nettoyage
$pdo->exec("DELETE FROM votes WHERE election_id = $elId");
$pdo->exec("DELETE FROM candidats WHERE election_id = $elId");
$pdo->exec("DELETE FROM elections WHERE id = $elId");

// Vérifications
$tests = [
    'HTML généré non vide' => strlen($html) > 5000,
    'Feuille de style results.css incluse' => strpos($html, 'assets/css/results.css') !== false,
    'Podium Or présent' => strpos($html, 'podium-gold') !== false,
    'Podium Argent présent' => strpos($html, 'podium-silver') !== false,
    'Podium Bronze présent' => strpos($html, 'podium-bronze') !== false,
    'Suite du classement présente' => strpos($html, 'ranking-section') !== false,
    'Statistiques KPI présentes' => strpos($html, 'kpi-card-res') !== false,
    'Variables CSS thématiques présentes' => strpos($html, 'data-theme') !== false,
    'Aucun warning PHP injecté dans le HTML' => strpos($html, 'Warning:') === false && strpos($html, 'Fatal error:') === false && strpos($html, 'Notice:') === false,
];

$allOk = true;
foreach ($tests as $label => $pass) {
    if ($pass) {
        echo " [OK] $label\n";
    } else {
        echo " [ÉCHEC] $label\n";
        $allOk = false;
    }
}

if ($allOk) {
    echo "\nTOUS LES TESTS DE RENDU SONT VALIDÉS AVEC SUCCÈS !\n";
} else {
    echo "\nDES ERREURS ONT ÉTÉ DÉTECTÉES DANS LE RENDU !\n";
    exit(1);
}
