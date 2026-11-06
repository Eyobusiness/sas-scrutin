<?php
/**
 * Script de création sécurisée du premier compte Super Administrateur
 * Compatible CLI (ligne de commande) et navigateur Web
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/Helpers/SecurityHelper.php';

$pdo = Database::getConnection();

// Vérifier si un super admin existe déjà
$stmt = $pdo->query("SELECT a.id, a.nom, a.prenoms, a.telephone 
                     FROM administrateurs a 
                     JOIN roles r ON a.role_id = r.id 
                     WHERE r.code = 'super_admin'");
$existingSuperAdmins = $stmt->fetchAll();

$isCli = (php_sapi_name() === 'cli');

if ($isCli) {
    echo "====================================================\n";
    echo "  SAS ÉLECTION - Création du Super Administrateur  \n";
    echo "  Groupe : « Tous unis au service du Christ »      \n";
    echo "====================================================\n\n";

    if (!empty($existingSuperAdmins)) {
        echo "[ATTENTION] Un ou plusieurs comptes Super Admin existent déjà :\n";
        foreach ($existingSuperAdmins as $admin) {
            echo " - {$admin['prenoms']} {$admin['nom']} (Tél: {$admin['telephone']})\n";
        }
        echo "\nVoulez-vous quand même créer un autre compte Super Admin ? (o/n) : ";
        $reponse = trim(fgets(STDIN));
        if (strtolower($reponse) !== 'o' && strtolower($reponse) !== 'oui') {
            echo "Opération annulée.\n";
            exit(0);
        }
    }

    echo "Nom de famille : ";
    $nom = trim(fgets(STDIN));

    echo "Prénoms : ";
    $prenoms = trim(fgets(STDIN));

    echo "Numéro de téléphone (servira d'identifiant de connexion) : ";
    $telephone = SecurityHelper::cleanPhone(trim(fgets(STDIN)));

    echo "Email (facultatif) : ";
    $email = trim(fgets(STDIN));

    echo "Mot de passe (minimum 6 caractères) : ";
    $motDePasse = trim(fgets(STDIN));

    if (empty($nom) || empty($prenoms) || empty($telephone) || empty($motDePasse)) {
        echo "\n[ERREUR] Tous les champs obligatoires (nom, prénoms, téléphone, mot de passe) doivent être renseignés.\n";
        exit(1);
    }

    if (strlen($motDePasse) < 6) {
        echo "\n[ERREUR] Le mot de passe doit comporter au moins 6 caractères.\n";
        exit(1);
    }

    // Récupérer l'ID du rôle super_admin
    $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE code = 'super_admin' LIMIT 1");
    $stmtRole->execute();
    $roleId = $stmtRole->fetchColumn();

    if (!$roleId) {
        echo "\n[ERREUR] Le rôle 'super_admin' n'existe pas en base de données. Veuillez exécuter le schéma database/schema.sql.\n";
        exit(1);
    }

    // Insérer ou mettre à jour
    $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
    $stmtInsert = $pdo->prepare("INSERT INTO administrateurs 
        (role_id, nom, prenoms, telephone, email, mot_de_passe, statut, cree_le)
        VALUES (:role_id, :nom, :prenoms, :telephone, :email, :mot_de_passe, 'actif', NOW())
        ON DUPLICATE KEY UPDATE 
            nom = VALUES(nom), 
            prenoms = VALUES(prenoms),
            mot_de_passe = VALUES(mot_de_passe),
            role_id = VALUES(role_id),
            statut = 'actif'");

    $stmtInsert->execute([
        ':role_id'      => $roleId,
        ':nom'          => $nom,
        ':prenoms'      => $prenoms,
        ':telephone'    => $telephone,
        ':email'        => !empty($email) ? $email : null,
        ':mot_de_passe' => $hash
    ]);

    echo "\n[SUCCÈS] Le compte Super Administrateur a été configuré avec succès !\n";
    echo "Identifiant (Téléphone) : {$telephone}\n";
    echo "Vous pouvez maintenant vous connecter à l'adresse : http://localhost/sas-scrutin/index.php?page=login\n\n";
    exit(0);
}

// Mode Web
$message = null;
$messageType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenoms = trim($_POST['prenoms'] ?? '');
    $telephone = SecurityHelper::cleanPhone($_POST['telephone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $motDePasse = $_POST['mot_de_passe'] ?? '';

    if (empty($nom) || empty($prenoms) || empty($telephone) || empty($motDePasse)) {
        $message = "Tous les champs obligatoires doivent être renseignés.";
        $messageType = "danger";
    } elseif (strlen($motDePasse) < 6) {
        $message = "Le mot de passe doit comporter au moins 6 caractères.";
        $messageType = "warning";
    } else {
        $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE code = 'super_admin' LIMIT 1");
        $stmtRole->execute();
        $roleId = $stmtRole->fetchColumn();

        if (!$roleId) {
            $message = "Le rôle 'super_admin' n'existe pas en base. Exécutez database/schema.sql.";
            $messageType = "danger";
        } else {
            $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
            $stmtInsert = $pdo->prepare("INSERT INTO administrateurs 
                (role_id, nom, prenoms, telephone, email, mot_de_passe, statut, cree_le)
                VALUES (:role_id, :nom, :prenoms, :telephone, :email, :mot_de_passe, 'actif', NOW())
                ON DUPLICATE KEY UPDATE 
                    nom = VALUES(nom), 
                    prenoms = VALUES(prenoms),
                    mot_de_passe = VALUES(mot_de_passe),
                    role_id = VALUES(role_id),
                    statut = 'actif'");

            $stmtInsert->execute([
                ':role_id'      => $roleId,
                ':nom'          => $nom,
                ':prenoms'      => $prenoms,
                ':telephone'    => $telephone,
                ':email'        => !empty($email) ? $email : null,
                ':mot_de_passe' => $hash
            ]);

            $message = "Le compte Super Administrateur a été configuré avec succès ! Vous pouvez maintenant vous connecter.";
            $messageType = "success";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Initialisation Super Admin | SAS Élection</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/vendors/bootstrap-icons/bootstrap-icons.css">
</head>
<body class="bg-light py-5">
    <div class="container" style="max-width: 580px;">
        <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
            <div class="text-center mb-4">
                <i class="bi bi-shield-lock-fill text-primary display-4"></i>
                <h1 class="h4 fw-bold mt-2">Création du Super Administrateur</h1>
                <p class="text-muted small">Ce script initialise le compte ayant les droits suprêmes de gestion.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nom de famille <span class="text-danger">*</span></label>
                    <input type="text" name="nom" class="form-control" required placeholder="Ex: KOUASSI">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Prénoms <span class="text-danger">*</span></label>
                    <input type="text" name="prenoms" class="form-control" required placeholder="Ex: Jean-Marc">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Numéro de téléphone <span class="text-danger">*</span></label>
                    <input type="tel" name="telephone" class="form-control" required placeholder="Ex: 0700000000">
                    <small class="text-muted">Servira d'identifiant de connexion à l'administration.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email (facultatif)</label>
                    <input type="email" name="email" class="form-control" placeholder="superadmin@sas-eglise.org">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Mot de passe <span class="text-danger">*</span></label>
                    <input type="password" name="mot_de_passe" class="form-control" required minlength="6" placeholder="Minimum 6 caractères">
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                    <i class="bi bi-check-circle me-1"></i> Créer le Super Admin
                </button>
            </form>

            <div class="text-center mt-3 pt-2">
                <a href="../index.php?page=login" class="text-decoration-none text-muted small">
                    Aller vers la page de connexion &rarr;
                </a>
            </div>
        </div>
    </div>
</body>
</html>
