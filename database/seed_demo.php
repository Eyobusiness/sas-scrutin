<?php
/**
 * Script d'initialisation de données de démonstration pour tests
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();

echo "Initialisation des données de test SAS Élection...\n";

// 1. Super Admin
$passwordHash = password_hash('Admin@2026', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO administrateurs (role_id, nom, prenoms, telephone, email, mot_de_passe, statut)
    VALUES (1, 'KOUADIO', 'Ferdinand', '0700000001', 'superadmin@sas-eglise.org', :pass, 'actif')
    ON DUPLICATE KEY UPDATE mot_de_passe = VALUES(mot_de_passe)");
$stmt->execute([':pass' => $passwordHash]);

// 2. Administrateur standard
$stmt = $pdo->prepare("INSERT INTO administrateurs (role_id, nom, prenoms, telephone, email, mot_de_passe, statut)
    VALUES (2, 'TOURE', 'Awa', '0700000002', 'admin@sas-eglise.org', :pass, 'actif')
    ON DUPLICATE KEY UPDATE mot_de_passe = VALUES(mot_de_passe)");
$stmt->execute([':pass' => $passwordHash]);

// 3. Élection
$dateOuvIns = date('Y-m-d H:i:s', strtotime('-14 days'));
$dateFermIns = date('Y-m-d H:i:s', strtotime('-1 day'));
$dateOuvCand = date('Y-m-d H:i:s', strtotime('-10 days'));
$dateFermCand = date('Y-m-d H:i:s', strtotime('-3 days'));
$dateDebCamp = date('Y-m-d H:i:s', strtotime('-3 days'));
$dateFinCamp = date('Y-m-d H:i:s', strtotime('-1 day'));
$dateDebut = date('Y-m-d H:i:s', strtotime('-1 day'));
$dateFin = date('Y-m-d H:i:s', strtotime('+6 days'));
$stmt = $pdo->prepare("INSERT INTO elections (
        id, titre, description,
        date_ouverture_inscriptions, date_fermeture_inscriptions,
        date_ouverture_candidatures, date_fermeture_candidatures,
        date_debut_campagne, date_fin_campagne,
        date_debut_vote, date_fin_vote,
        date_debut, date_fin, statut, cree_par, cree_le
    )
    VALUES (
        1, 'Élection du Président du SAS 2026-2028',
        'Scrutin officiel pour l\'élection du nouveau président du SAS (« Tous unis au service du Christ »). Vote personnel et secret.',
        :ouv_ins, :ferm_ins, :ouv_cand, :ferm_cand, :deb_camp, :fin_camp, :debut, :fin, :debut, :fin, 'ouverte', 1, NOW()
    )
    ON DUPLICATE KEY UPDATE
        statut = 'ouverte',
        date_ouverture_inscriptions = VALUES(date_ouverture_inscriptions),
        date_fermeture_inscriptions = VALUES(date_fermeture_inscriptions),
        date_ouverture_candidatures = VALUES(date_ouverture_candidatures),
        date_fermeture_candidatures = VALUES(date_fermeture_candidatures),
        date_debut_campagne = VALUES(date_debut_campagne),
        date_fin_campagne = VALUES(date_fin_campagne),
        date_debut_vote = VALUES(date_debut_vote),
        date_fin_vote = VALUES(date_fin_vote),
        date_debut = VALUES(date_debut),
        date_fin = VALUES(date_fin)");
$stmt->execute([
    ':ouv_ins'   => $dateOuvIns,
    ':ferm_ins'  => $dateFermIns,
    ':ouv_cand'  => $dateOuvCand,
    ':ferm_cand' => $dateFermCand,
    ':deb_camp'  => $dateDebCamp,
    ':fin_camp'  => $dateFinCamp,
    ':debut'     => $dateDebut,
    ':fin'       => $dateFin,
]);

// 4. Candidats (avec date_naissance, anciennete et droit_candidature_paye)
$candidats = [
    [1, 1, 'KOUAME', 'Yao Serge', '0711111111', '1988-05-14', 5, 1, 'Unir tous les membres du SAS dans l\'amour du Christ et dynamiser l\'impact social.', 'validee'],
    [2, 1, 'KOFFI', 'Amenan Grace', '0722222222', '1991-09-20', 4, 1, 'Renforcer la communion fraternelle, les temps de prière et le rayonnement spirituel.', 'validee'],
    [3, 1, 'DIABATE', 'Eric Patrick', '0733333333', '1990-02-10', 3, 1, 'Moderniser la gestion du SAS et promouvoir des projets concrets pour la jeunesse.', 'validee']
];

foreach ($candidats as $c) {
    $stmt = $pdo->prepare("INSERT INTO candidats (id, election_id, nom, prenoms, telephone, date_naissance, anciennete, droit_candidature_paye, motif_candidature, statut, date_depot)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            date_naissance = VALUES(date_naissance),
            anciennete = VALUES(anciennete),
            droit_candidature_paye = VALUES(droit_candidature_paye),
            statut = VALUES(statut),
            motif_candidature = VALUES(motif_candidature)");
    $stmt->execute($c);
}

// 5. Membres (avec date_naissance et anciennete)
$membres = [
    ['KOUASSI', 'Marc Antoine', '0701010101', '1995-04-12', 4, 'Enseignant', 'Chorale', 'actif', 'oui', null],
    ['YAO', 'Patricia', '0702020202', '1998-09-24', 3, 'Comptable', 'Accueil', 'actif', 'oui', null],
    ['N\'GUESSAN', 'David', '0703030303', '1992-01-15', 5, 'Informaticien', 'Logistique', 'actif', 'oui', null],
    ['KONE', 'Aminata', '0704040404', '2001-06-30', 1, 'Étudiante', 'Membre', 'actif', 'non', 'Ancienneté déclarée : 1 an. Ancienneté minimum requise : 2 ans.'],
    ['KOUAME', 'Emmanuel', '0705050505', '1989-11-03', 6, 'Médecin', 'Santé & Social', 'actif', 'oui', null],
    ['BLESSING', 'Marie', '0706060606', '2003-08-19', 2, 'Graphiste', 'Communication', 'inactif', 'non', 'Compte temporairement inactif']
];

foreach ($membres as $m) {
    $stmt = $pdo->prepare("INSERT INTO membres (nom, prenoms, telephone, date_naissance, anciennete, fonction_pro, responsabilite_sas, statut, eligibilite_a_voter, motif_non_eligibilite, date_enregistrement, date_validation_eligibilite, valide_par)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
        ON DUPLICATE KEY UPDATE 
            date_naissance = VALUES(date_naissance),
            anciennete = VALUES(anciennete),
            eligibilite_a_voter = VALUES(eligibilite_a_voter),
            motif_non_eligibilite = VALUES(motif_non_eligibilite)");
    $stmt->execute($m);
}

echo "Données de test insérées avec succès !\n";
echo "Comptes disponibles :\n";
echo " - Super Admin : Tél 0700000001 / Mot de passe : Admin@2026\n";
echo " - Admin : Tél 0700000002 / Mot de passe : Admin@2026\n";
echo " - Membres éligibles pour voter : 0701010101 (Né le 1995-04-12), 0702020202 (Née le 1998-09-24), 0703030303 (Né le 1992-01-15), 0705050505 (Né le 1989-11-03)\n";
echo " - Membre non-éligible pour tester le refus : 0704040404\n";
