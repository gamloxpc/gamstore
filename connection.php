<?php
session_start();
include_once('config/dataclient.php'); 
// Forcer l'affichage des erreurs PENDANT LE DEBUG (à retirer en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$recaptchaSecret = '6LdNmh8rAAAAAMSviZxv3AJEIGB9cnKCbOSmE3Tl'; // IMPORTANT: Ta clé secrète reCAPTCHA

define('MAX_IP_LOGIN_ATTEMPTS', 10);
define('IP_LOCKOUT_DURATION', '30 minutes');

// --- Initialisations ---
$login_error = null;
$pdo = null;
$is_ip_blocked = false;
$recaptcha_passed = false; // Flag pour le statut du reCAPTCHA

// --- Fonction IP Client ---
function get_client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}
$client_ip = get_client_ip();

// --- Vérification Préalable du Blocage IP (si DB OK et IP connue) ---
if ($pdo && $client_ip !== 'UNKNOWN') {
    try {
        $stmt_ip = $pdo->prepare("SELECT failed_attempts, last_attempt_timestamp FROM ip_login_attempts WHERE ip_address = ?");
        $stmt_ip->execute([$client_ip]);
        $ip_data = $stmt_ip->fetch(PDO::FETCH_ASSOC);

        if ($ip_data) {
            $lockout_time = strtotime('-' . IP_LOCKOUT_DURATION);
            $last_attempt_time = $ip_data['last_attempt_timestamp'] ? strtotime($ip_data['last_attempt_timestamp']) : null;

            if ($ip_data['failed_attempts'] >= MAX_IP_LOGIN_ATTEMPTS && $last_attempt_time && $last_attempt_time > $lockout_time) {
                $is_ip_blocked = true;
                $remaining_time = ceil(($last_attempt_time + strtotime(IP_LOCKOUT_DURATION, 0) - time()) / 60);
                if ($remaining_time < 1) $remaining_time = 1;
                $login_error = "Trop de tentatives de connexion depuis votre adresse IP. Veuillez réessayer dans environ " . $remaining_time . " minute(s).";
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du blocage IP pour $client_ip: " . $e->getMessage());
        // Ne pas bloquer si la vérif échoue, mais logger
    }
}

// --- Traitement du Formulaire (Méthode POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
   // ******** DEBUT MODIFICATION POUR TEST ********
   $recaptcha_passed = true; // ON FORCE LA VALIDATION RECAPTCHA À TRUE
   $login_error = null; // On s'assure qu'il n'y a pas d'erreur initiale
   /*  // --- Étape 1: Vérifier reCAPTCHA (SEULEMENT si pas déjà d'erreur et l'IP n'est pas bloquée) ---
    if ($login_error === null && !$is_ip_blocked) {
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? null;

        if (empty($recaptchaResponse)) {
            $login_error = "Veuillez valider le captcha.";
        } else {
            // --- Début Vérification reCAPTCHA ---
            $verifyURL = 'https://www.google.com/recaptcha/api/siteverify';
            $postData = http_build_query([
                'secret'   => $recaptchaSecret,
                'response' => $recaptchaResponse,
                'remoteip' => $client_ip
            ]);
            $options = ['http' => ['header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => $postData, 'ignore_errors' => true]];
            $context  = stream_context_create($options);
            $verifyResponse = file_get_contents($verifyURL, false, $context);

            if ($verifyResponse === FALSE) {
                $login_error = "Erreur lors de la communication avec le service reCAPTCHA. Veuillez réessayer.";
                error_log("reCAPTCHA verification failed: Could not contact Google API for IP $client_ip. file_get_contents returned FALSE.");
            } else {
               $responseData = json_decode($verifyResponse);
               // error_log("Decoded Response Data: " . print_r($responseData, true)); // Garder commenté sauf pour debug

               if ($responseData && isset($responseData->success) && $responseData->success === true) {
                   $recaptcha_passed = true;
                   // error_log("reCAPTCHA validation SUCCESSFUL for IP $client_ip"); // Garder commenté sauf pour debug
               } else {
                   $login_error = "Échec de la validation du captcha. Veuillez réessayer.";
                   $errorCodes = isset($responseData->{'error-codes'}) ? implode(', ', $responseData->{'error-codes'}) : 'N/A';
                   error_log("reCAPTCHA validation FAILED for IP $client_ip. Success flag missing, false, or invalid JSON. Response: " . $verifyResponse . " | Error codes: " . $errorCodes);
               }
            }
            // --- Fin Vérification reCAPTCHA ---
        }
    } */
    // --- Fin Étape 1 ---


    // --- Étape 2: Tenter la connexion et gérer les tentatives ---
    // **** TOUTE LA LOGIQUE DE CONNEXION EST MAINTENANT DANS CE BLOC ****
    if ($pdo && $recaptcha_passed && $login_error === null && !$is_ip_blocked) {

        // Récupérer les données du formulaire
        $mail = $_POST['mail'] ?? null;
        $mdp = $_POST['mdp'] ?? null;
        $login_success = false; // Initialisation OBLIGATOIRE ici
        var_dump($user); // Voir si l'utilisateur est trouvé
        if ($user) {
            var_dump($mdp); // Voir le mot de passe entré
            var_dump($user['mdp']); // Voir le hash de la BDD
            var_dump(password_verify($mdp, $user['mdp'])); // Voir le résultat de la vérification
        }
         //exit;
        // Valider que les champs ne sont pas vides
        if (empty($mail) || empty($mdp)) {
            $login_error = "L'adresse e-mail et le mot de passe sont requis.";
            // $login_success reste false, c'est correct
        } else {
            // Essayer de connecter l'utilisateur
            try {
                $stmt_user = $pdo->prepare("SELECT idUser, prenom, role, mdp FROM utilisateur WHERE mail = ?");
                $stmt_user->execute([$mail]);
                $user = $stmt_user->fetch(PDO::FETCH_ASSOC);

                // Vérifier l'utilisateur et le mot de passe
                if ($user && password_verify($mdp, $user['mdp'])) {
                    // ===== CONNEXION REUSSIE =====
                    $login_success = true;

                    // Réinitialiser les tentatives IP pour cette IP
                    try {
                        $stmt_ip_reset = $pdo->prepare("DELETE FROM ip_login_attempts WHERE ip_address = ?");
                        $stmt_ip_reset->execute([$client_ip]);
                    } catch (PDOException $e_reset) {
                        // Loguer l'erreur mais continuer, la connexion a réussi
                        error_log("Erreur lors de la réinitialisation des tentatives IP pour $client_ip après succès: " . $e_reset->getMessage());
                    }

                    // Stocker les informations utilisateur en session
                    $_SESSION['user_id'] = $user['idUser'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user_prenom'] = $user['prenom'];

                    // Rediriger vers la page de profil
                    header("Location: profil.php");
                    exit; // Arrêter le script après redirection

                } else {
                    // ===== ECHEC CONNEXION (Mauvais email/mdp) =====
                    // $login_success reste false (sa valeur par défaut)
                    $login_error = "Adresse e-mail ou mot de passe incorrect.";
                }

            } catch (PDOException $e_login) {
                 // ===== ECHEC CONNEXION (Erreur BDD) =====
                error_log("Erreur DB lors de la tentative de connexion pour $mail depuis $client_ip: " . $e_login->getMessage());
                $login_error = "Une erreur technique est survenue lors de la connexion.";
                // $login_success reste false (sa valeur par défaut)
            }
        } // Fin du else (si email et mdp n'étaient pas vides)


        // --- Mettre à jour les tentatives IP en cas d'échec de connexion ---
        // S'exécute seulement si la connexion n'a pas réussi ($login_success est false)
        // ET si un email a été fourni (pour éviter de compter les soumissions vides)
        if (!$login_success && !empty($mail)) {
            try {
                $sql_update_ip = "
                    INSERT INTO ip_login_attempts (ip_address, failed_attempts, last_attempt_timestamp)
                    VALUES (?, 1, NOW())
                    ON DUPLICATE KEY UPDATE
                    failed_attempts = failed_attempts + 1,
                    last_attempt_timestamp = NOW()";
                $stmt_ip_update = $pdo->prepare($sql_update_ip);
                $stmt_ip_update->execute([$client_ip]);

                // Vérifier si cet échec déclenche un blocage IP
                $stmt_check_again = $pdo->prepare("SELECT failed_attempts FROM ip_login_attempts WHERE ip_address = ?");
                $stmt_check_again->execute([$client_ip]);
                $updated_ip_data = $stmt_check_again->fetch(PDO::FETCH_ASSOC);

                // Si le nombre max est atteint OU dépassé
                if ($updated_ip_data && $updated_ip_data['failed_attempts'] >= MAX_IP_LOGIN_ATTEMPTS) {
                     $lockout_msg_time = ceil(strtotime(IP_LOCKOUT_DURATION, 0) / 60);
                     // Modifier le message d'erreur pour indiquer le blocage
                     $login_error = "Trop de tentatives de connexion depuis votre adresse IP. Veuillez réessayer dans environ " . $lockout_msg_time . " minute(s).";
                     $is_ip_blocked = true; // Marquer comme bloqué pour désactiver le formulaire sur la page suivante
                }

            } catch (PDOException $e_ip) {
                // Loguer l'erreur mais ne pas afficher d'erreur supplémentaire à l'utilisateur
                error_log("Erreur lors de la mise à jour des tentatives IP pour $client_ip: " . $e_ip->getMessage());
            }
        } // Fin de la mise à jour des tentatives IP

    } // --- Fin Étape 2 (Bloc principal de tentative de connexion) ---
    // Note: Il n'y a plus besoin du bloc elseif car tous les cas POST sont gérés soit par une erreur initiale, soit dans le bloc ci-dessus.

} // --- Fin du bloc if ($_SERVER["REQUEST_METHOD"] == "POST") ---

// Le reste du code (HTML) est généré après le traitement PHP
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Se connecter à votre compte | GamStore</title>
    <link rel="shortcut icon" type="image/png" href="im/logo.png"/>
    <link rel="stylesheet" href="registerCss.css">
    <!-- Styles pour le message d'erreur et le blocage (optionnel) -->
    <style>
        .error-message {
            color: #D8000C; /* Rouge */
            background-color: #FFD2D2; /* Fond rouge clair */
            border: 1px solid #D8000C;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            text-align: center;
        }
        .ip-blocked input[type="email"],
        .ip-blocked input[type="password"],
        .ip-blocked input[type="submit"],
        .ip-blocked .g-recaptcha {
            /* Optionnel: griser ou cacher si l'IP est bloquée */
            opacity: 0.6;
            pointer-events: none; /* Empêche l'interaction */
        }
    </style>
    <!-- Script Google reCAPTCHA (à laisser dans le <head> ou avant la fermeture de <body>) -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>
<main class="main-container">
    <section class="inscription-container" <?php if ($is_ip_blocked) echo 'ip-blocked'; // Ajoute la classe si bloqué ?>">
        <h2>Se Connecter</h2>

        <?php
        // Afficher le message d'erreur s'il y en a un
        if (!empty($login_error)) {
            // Utiliser htmlspecialchars pour éviter les failles XSS si un message contenait du HTML
            echo '<div class="error-message">' . htmlspecialchars($login_error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        ?>

<form class="formConnection form-group" action="connection.php" method="POST">
            <label for="mail">Adresse Email :</label>
            <input type="email" id="mail" name="mail" required <?php if ($is_ip_blocked) echo 'disabled'; ?>>

            <label for="mdp">Mot de passe :</label>
            <div class="password-container">
            <input type="password" id="mdp" name="mdp" required <?php if ($is_ip_blocked) echo 'disabled'; ?>>
            <!-- Ajout de l'icône (utilisant un emoji pour la simplicité, tu peux le remplacer) -->
            <span class="toggle-password-icon" id="togglePassword">👁️</span>
        </div>
                <div class="login-link">
                <a href="inscription.php">Créer un compte</a> |
                <a href="motDePasseOublie.php">Mot de passe oublié ?</a>
            </div><br>

            <!-- Widget reCAPTCHA v2 Checkbox -->
            <div class="g-recaptcha" data-sitekey="6LdNmh8rAAAAAC88X6fg2T5qh_ucNvJ_HUm_FM5G"></div>
            <br/> <!-- Tu peux ajuster ou supprimer ce <br/> selon ton design -->

            <!-- Bouton de soumission -->
            <input type="submit" class="submit-button" value="Se connecter" <?php if ($is_ip_blocked) echo 'disabled'; // Désactive si bloqué ?>>

        </form>
    </section>
    </main>
    <script>
    // Fonction pour réinitialiser le reCAPTCHA
    function resetRecaptcha() {
        // Vérifie si l'API grecaptcha est chargée et s'il y a un widget à réinitialiser
        if (typeof grecaptcha !== 'undefined' && grecaptcha && typeof grecaptcha.reset === 'function') {
             // Tu peux cibler un widget spécifique par ID si tu en as plusieurs,
             // sinon, sans argument, ça devrait réinitialiser le premier trouvé.
             grecaptcha.reset();
        }
    }

    // Appeler la fonction de réinitialisation au chargement de la page
    // ou après un certain délai si l'API n'est pas immédiatement prête
    window.onload = function() {
       // Un petit délai peut parfois aider si l'API met du temps à s'initialiser
       setTimeout(resetRecaptcha, 500);
    };

    // Optionnel : Tu peux aussi appeler resetRecaptcha() après l'affichage
    // d'un message d'erreur via PHP/JS si tu fais une validation Ajax.
</script>
    <script>
        // Attend que le contenu de la page soit chargé
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('mdp');
            const togglePasswordIcon = document.getElementById('togglePassword');

            // Vérifie si les éléments existent avant d'ajouter l'écouteur
            if (passwordInput && togglePasswordIcon) {
                togglePasswordIcon.addEventListener('click', function () {
                    // Récupère le type actuel de l'input ('password' ou 'text')
                    const currentType = passwordInput.getAttribute('type');

                    // Inverse le type
                    if (currentType === 'password') {
                        passwordInput.setAttribute('type', 'text');
                        // Change l'icône (ou le texte) pour indiquer "masquer" (emoji singe qui cache les yeux)
                        togglePasswordIcon.textContent = '🙈';
                    } else {
                        passwordInput.setAttribute('type', 'password');
                        // Remet l'icône "œil" pour indiquer "afficher"
                        togglePasswordIcon.textContent = '👁️';
                    }
                });
            }
        });
    </script>
</body>
</html>