<!DOCTYPE html>
<html lang="fr" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administration | SAS Élection</title>

    <link rel="icon" type="image/png" href="assets/images/logo/logosas.png">
    <link rel="apple-touch-icon" href="assets/images/logo/logosas.png">

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        .login-logo-box {
            width: 88px;
            height: 88px;
            border-radius: 20px;
            background: #ffffff;
            padding: 8px;
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.16), 0 0 0 1px rgba(226, 232, 240, 0.9);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .login-logo-box:hover {
            transform: scale(1.04);
            box-shadow: 0 16px 36px rgba(37, 99, 235, 0.22);
        }
        .login-logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 12px;
        }
        .login-card {
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 1.25rem;
            background: #ffffff;
            box-shadow: 0 20px 45px -15px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(226, 232, 240, 0.5);
        }
    </style>
</head>


<body class="auth-body">
    <main class="auth-page">
        <section class="auth-card login-card">
            <!-- Logo & En-tête -->
            <div class="text-center mb-4">
                <a class="text-decoration-none d-inline-block mb-3" href="index.php?page=home" title="Retour à l'accueil">
                    <div class="login-logo-box">
                        <img src="assets/images/logo/logosas.png" alt="Logo SAS" class="login-logo-img">
                    </div>
                </a>
                <div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 mb-2 rounded-pill fw-semibold">
                        <i class="bi bi-shield-lock me-1"></i> Espace d'Administration
                    </span>
                    <h1 class="h4 fw-bold text-dark mb-1">Connexion</h1>
                    <p class="text-muted small mb-0">Identifiez-vous pour superviser et gérer les scrutins du SAS.</p>
                </div>
            </div>

            <!-- Messages flash -->
            <div class="mb-3">
                <?= SessionHelper::renderFlashes() ?>
            </div>

            <!-- Formulaire de connexion -->
            <form method="post" action="index.php?page=login_process" class="needs-validation" novalidate>
                <?= SessionHelper::csrfField() ?>

                <!-- Numéro de téléphone -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="loginPhone">Numéro de téléphone</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0">
                            <i class="bi bi-telephone"></i>
                        </span>
                        <input class="form-control border-start-0" id="loginPhone" name="telephone" type="tel" placeholder="Numéro de téléphone" required autofocus>
                    </div>
                    <div class="invalid-feedback">Veuillez renseigner un numéro de téléphone valide.</div>
                </div>

                <!-- Mot de passe -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label fw-semibold mb-0" for="loginPassword">Mot de passe</label>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-light text-muted border-end-0">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input class="form-control border-start-0 border-end-0" id="loginPassword" name="mot_de_passe" type="password" placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary border-start-0" type="button" id="btnTogglePassword" title="Afficher/Masquer le mot de passe" tabindex="-1">
                            <i class="bi bi-eye" id="iconTogglePassword"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback">Le mot de passe est obligatoire.</div>
                </div>

                <!-- Bouton de soumission -->
                <button class="btn btn-primary btn-lg w-100 fw-bold shadow-sm" type="submit">
                    <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Se connecter
                </button>
            </form>

            <!-- Reassurance & Retour -->
            <div class="text-center mt-4 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-center gap-2 text-muted small mb-3">
                    <i class="bi bi-shield-check text-success"></i> Accès sécurisé réservé aux comité ad hoc
                </div>
                <a href="index.php?page=home" class="text-decoration-none text-muted small fw-semibold">
                    <i class="bi bi-arrow-left me-1"></i> Retour au portail public de vote
                </a>
            </div>
        </section>
    </main>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var btnToggle = document.getElementById('btnTogglePassword');
            var inputPassword = document.getElementById('loginPassword');
            var iconToggle = document.getElementById('iconTogglePassword');

            if (btnToggle && inputPassword && iconToggle) {
                btnToggle.addEventListener('click', function() {
                    if (inputPassword.type === 'password') {
                        inputPassword.type = 'text';
                        iconToggle.classList.remove('bi-eye');
                        iconToggle.classList.add('bi-eye-slash');
                    } else {
                        inputPassword.type = 'password';
                        iconToggle.classList.remove('bi-eye-slash');
                        iconToggle.classList.add('bi-eye');
                    }
                });
            }
        });
    </script>
</body>
</html>
