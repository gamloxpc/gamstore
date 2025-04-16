<?php
session_start();
require_once 'includes/send_email.php'; // Inclure la fonction d'envoi
require_once 'vendor/autoload.php'; // Nécessaire si vous utilisez Dotenv (voir étape 5)
$idUser = $_SESSION['user_id']; // On récupère l'ID utilisateur

// 2. Connexion à la base de données (PDO)
$host = 'localhost';
$dbname = 'gamstore';
$user_db = 'root';       // Utilisateur BDD (nom différent de la variable $user)
$password_db = '';     // Mot de passe BDD
// Charger les variables d'environnement (voir étape 5)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); // Ajuster le chemin si besoin
$dotenv->safeLoad(); // safeLoad() ne provoque pas d'erreur si .env n'existe pas

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Activer les erreurs PDO
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);      // Meilleure sécurité
} catch (PDOException $e) {
    error_log("Erreur Connexion BDD: " . $e->getMessage()); // Log l'erreur serveur
    die("Impossible de se connecter à la base de données. Veuillez réessayer plus tard."); // Message pour l'utilisateur
}

// 3. Vérifier si le formulaire a été soumis via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 4. Récupération et Validation des Données du Formulaire Client/Livraison
    $errors = []; // Tableau pour collecter les erreurs de validation

    // Fonction simple pour récupérer et valider un champ POST obligatoire
    function getRequiredPost($key, &$errors, $fieldName) {
        $value = trim($_POST[$key] ?? '');
        if (empty($value)) {
            $errors[] = "$fieldName est obligatoire.";
            return null; // Retourner null si vide
        }
        return $value;
    }

    // Fonction pour valider un email
    function getValidEmailPost($key, &$errors, $fieldName) {
        $value = trim($_POST[$key] ?? '');
        if (empty($value)) {
            $errors[] = "$fieldName est obligatoire.";
            return null;
        } elseif (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
             $errors[] = "$fieldName n'est pas une adresse email valide.";
            return null;
        }
        return $value;
    }

     // Fonction pour nettoyer un téléphone
     function getCleanPhonePost($key) {
         return preg_replace('/[^0-9+]/', '', $_POST[$key] ?? '');
     }

    // --- Informations Client ---
    $nomClient = getRequiredPost('Nom', $errors, 'Nom client');
    $prenomClient = getRequiredPost('Prenom', $errors, 'Prénom client');
    $mailClient = getValidEmailPost('Email', $errors, 'Email client');
    $telephoneClient = getCleanPhonePost('Telephone');
    if (empty($telephoneClient)) $errors[] = "Téléphone client est obligatoire."; // Valider après nettoyage
    $adresseClient = getRequiredPost('Adresse', $errors, 'Adresse client');
    $complementAdresse = trim($_POST['ComplementAdresse'] ?? ''); // Optionnel
    $villeClient = getRequiredPost('Ville', $errors, 'Ville client');
    $cpClient = getRequiredPost('CodePostal', $errors, 'Code postal client');
    $paysClient = getRequiredPost('pays', $errors, 'Pays client');

    // --- Options & Méthode de livraison ---
    $newsletter = isset($_POST['newsletter']) ? 1 : 0; // 1 si coché, 0 sinon
    $delivery_method = trim($_POST['delivery_method'] ?? '');
  // Initialiser le numéro de point relais
$numeroPointRelay = null; // Utiliser NULL par défaut pour la base de données si non applicable

// Si la méthode est Point Relay, récupérer la valeur de l'input 'Target_Widget'
if ($delivery_method === 'mondial_relay_point') {
    // --- CORRECTION ICI ---
    // Utiliser 'Target_Widget' qui correspond au name="" de l'input HTML
    $numeroPointRelay = trim($_POST['Target_Widget'] ?? '');
    // --------------------

    // Vérifier si le numéro a bien été récupéré (le widget a dû remplir le champ)
    if (empty($numeroPointRelay)) {
        // L'utilisateur a choisi Point Relay mais le champ est vide
        $errors[] = "Veuillez sélectionner un Point Relay valide en cliquant sur le bouton 'Choisir son point relais'.";
        // Optionnel: Logguer cette erreur pour investigation si ça arrive souvent
        error_log("Validation Commande: Mode Point Relay choisi mais Target_Widget vide.");
    }
}

    // --- Adresse de Livraison (si différente) ---
    $adresseDifferente = isset($_POST['addresseDifferente']) ? 1 : 0; // 1 si coché
    $nomDesti = $nomClient; $prenomDesti = $prenomClient; $mailDesti = $mailClient;
    $telDesti = $telephoneClient; $adresseDesti = $adresseClient; $complementAdresseDesti = $complementAdresse;
    $villeDesti = $villeClient; $cpDesti = $cpClient; $paysDesti = $paysClient;

    if ($adresseDifferente) {
        $nomDesti = getRequiredPost('Nom_destinataire', $errors, 'Nom destinataire');
        $prenomDesti = getRequiredPost('Prenom_destinataire', $errors, 'Prénom destinataire');
        $mailDesti = getValidEmailPost('Email_destinataire', $errors, 'Email destinataire'); // Optionnel ? A vérifier
        $telDesti = getCleanPhonePost('Telephone_destinataire'); // Optionnel ? A vérifier
        $adresseDesti = getRequiredPost('Adresse_destinataire', $errors, 'Adresse destinataire');
        $complementAdresseDesti = trim($_POST['ComplementAdresse_destinataire'] ?? '');
        $villeDesti = getRequiredPost('Ville_destinataire', $errors, 'Ville destinataire');
        $cpDesti = getRequiredPost('CodePostal_destinataire', $errors, 'Code postal destinataire');
        $paysDesti = getRequiredPost('pays_destinataire', $errors, 'Pays destinataire');
    }

    // --- Vérifier s'il y a eu des erreurs de validation ---
    if (!empty($errors)) {
        echo "<h1>Erreurs dans le formulaire :</h1><ul>";
        foreach ($errors as $error) { echo "<li>" . htmlspecialchars($error) . "</li>"; }
        echo "</ul><p><a href='javascript:history.back()'>Retour au formulaire</a></p>";
        // Afficher les données POST reçues pour aider au debug
        echo '<h2>Données POST reçues :</h2><pre>'; var_dump($_POST); echo '</pre>';
        die(); // Arrêter le script
    }

    // 5. Récupération et Traitement du Panier (depuis JSON)
    $cartJson = $_POST['cartData'] ?? null;
    if ($cartJson === null) { die("Erreur : Données du panier manquantes."); }
    $cartItems = json_decode($cartJson, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($cartItems) || !is_array($cartItems)) {
        error_log("Erreur décodage JSON panier ou panier vide. JSON reçu: " . $cartJson);
        die("Erreur : Impossible de traiter le contenu de votre panier.");
    }

    // 6. Vérification des Produits et Calcul du Total
    $prixTotalCommande = 0;            // Total final (sans frais/réduc ici)
    $produitsPourCommande = [];      // Pour stocker les produits validés
    $sqlGetProductInfo = "SELECT prix FROM produit WHERE idProduit = ?"; // On a juste besoin du prix actuel
    try {
        $stmtGetProductInfo = $pdo->prepare($sqlGetProductInfo);
    } catch (PDOException $e) {
         error_log("Erreur Préparation Requete Produit: " . $e->getMessage());
         die("Erreur serveur lors de la préparation des informations produit.");
    }

    foreach ($cartItems as $item) {
        $idProduit = isset($item['productId']) ? (int)$item['productId'] : 0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;

        if ($idProduit <= 0 || $quantity <= 0) {
            error_log("Article panier ignoré (ID ou Qté invalide): " . json_encode($item));
            continue; // Passer à l'article suivant
        }

        try {
            $stmtGetProductInfo->execute([$idProduit]);
            $productInfo = $stmtGetProductInfo->fetch(PDO::FETCH_ASSOC);

            if ($productInfo) {
                $prixActuel = (float)$productInfo['prix'];
                $prixTotalLigne = $prixActuel * $quantity;
                $prixTotalCommande += $prixTotalLigne; // Ajouter au total de la commande

                // Ajouter le produit validé au tableau pour insertion BDD
                $produitsPourCommande[] = [
                    'idProduit'      => $idProduit,
                    'quantite'       => $quantity,
                    'prixUnitaire'   => $prixActuel,
                    'prixTotalLigne' => $prixTotalLigne
                ];
            } else {
                error_log("Produit ID {$idProduit} du panier non trouvé en BDD.");
                // Faut-il arrêter la commande ou juste ignorer l'article ?
                // Pour l'instant, on ignore. Prévenir l'utilisateur serait mieux.
            }
        } catch (PDOException $e) {
            error_log("Erreur recherche produit ID {$idProduit} en BDD: " . $e->getMessage());
            die("Une erreur est survenue lors de la vérification des produits.");
        }
    }

    // Vérifier si au moins un produit valide a été trouvé
    if (empty($produitsPourCommande)) {
        die("Erreur : Aucun produit valide trouvé pour cette commande. Votre panier est peut-être vide ou contient des articles indisponibles.");
    }


    // 7. Enregistrement dans la Base de Données (Transaction)
    try {
        $pdo->beginTransaction();

        // Insertion dans la table 'commande'
        // Assurez-vous que ces noms de colonnes correspondent EXACTEMENT à votre table commande
        // Notamment 'complementAdresse' vs 'completAdresse' et 'complementAdresseDesti' vs 'compleAdresseDesti'
        $sqlInsertCommande = "INSERT INTO commande (
                                idUser, dateCommande, statut, prix, numeroPointRelay, nomClient, prenomClient, mailClient,
                                telephoneClient, adresseClient, completAdresse, villeClient, cpClient, paysClient,
                                adresseDifferente, nomDesti, prenomDesti, mailDesti, telDesti, adresseDesti,
                                completAdresseDesti, villeDesti, cpDesti, paysDesti, delivery_method, newsletter
                            ) VALUES (
                                ?, NOW(), 'En cours', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                            )";
        $stmtCommande = $pdo->prepare($sqlInsertCommande);
        $paramsCommande = [
            $idUser,
            $prixTotalCommande, // Utiliser le total calculé
            $numeroPointRelay,
            $nomClient, $prenomClient, $mailClient, $telephoneClient, $adresseClient, $complementAdresse, // Utilisez complementAdresse ici
            $villeClient, $cpClient, $paysClient, $adresseDifferente,
            $nomDesti, $prenomDesti, $mailDesti, $telDesti, $adresseDesti,
            $complementAdresseDesti, // Et ici, mais vérifiez le nom exact dans la TABLE commande (probablement compleAdresseDesti)
            $villeDesti, $cpDesti, $paysDesti,
            $delivery_method, $newsletter
        ];
        $stmtCommande->execute($paramsCommande);
        $idCommande = $pdo->lastInsertId();

        if (!$idCommande) { throw new Exception("Impossible de créer la commande."); }

        // Insertion dans la table 'commande_produit'
        // Assurez-vous que les colonnes sont idCommande, idProduit, quantite, prixUnitaire, PrixProduit
        $sqlInsertProduit = "INSERT INTO commande_produit (idCommande, idProduit, quantite, prixUnitaire, PrixProduit) VALUES (?, ?, ?, ?, ?)";
        $stmtCommandeProduit = $pdo->prepare($sqlInsertProduit);
        foreach ($produitsPourCommande as $produit) {
            $stmtCommandeProduit->execute([
                $idCommande,
                $produit['idProduit'],
                $produit['quantite'],
                $produit['prixUnitaire'],
                $produit['prixTotalLigne']
            ]);
        }

        // Valider la transaction
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        // 8. Succès : Préparer pour vider le panier et rediriger
        $_SESSION['clearCart'] = true; // Indicateur pour vider localStorage sur la page de confirmation
        header("Location: confirmation.php?idCommande=" . $idCommande);
        exit;

    } catch (Exception $e) { // Attrape les erreurs PDO et autres Exceptions
        // Annuler la transaction en cas d'erreur
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERREUR TRANSACTION Commande pour user ID {$idUser}: " . $e->getMessage());
        // Afficher l'erreur détaillée pour le debug (à commenter en production)
         echo "<p style='color:red; border: 1px solid red; padding: 10px;'><strong>ERREUR CRITIQUE BDD LORS DE L'INSERTION/TRANSACTION:</strong><br>"
              . htmlspecialchars($e->getMessage()) . "<br><br><strong>Fichier:</strong> " . $e->getFile()
              . "<br><strong>Ligne:</strong> " . $e->getLine() . "</p>";
        die("Une erreur critique est survenue lors de l'enregistrement de votre commande. Code: TDBERR");
    }

// --- Préparer le contenu de l'email ---
$sujet = "Confirmation de votre commande GamStore #" . $idCommande . "NE PAS REPONDRE";

// Corps HTML (exemple simple)
$htmlBody = "<h1>Merci pour votre commande Sur GamStore !</h1>";
$htmlBody .= "<p>Bonjour " . htmlspecialchars($nomClient) . ",</p>";
$htmlBody .= "<p>Votre commande numéro <strong>#" . $idCommande . "</strong> a bien été enregistrée.</p>";
$htmlBody .= "<p>Voici un récapitulatif :</p>";
$htmlBody .= "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>
                <thead>
                    <tr><th>Produit</th><th>Quantité</th><th>Prix Total</th></tr>
                </thead>
                <tbody>";
foreach ($produitsCommandes as $produit) {
    $htmlBody .= "<tr>
                    <td>" . htmlspecialchars($produit['nom']) . "</td>
                    <td style='text-align:center;'>" . htmlspecialchars($produit['quantite']) . "</td>
                    <td style='text-align:right;'>" . number_format($produit['prix'], 2, ',', ' ') . " €</td>
                  </tr>";
}
$htmlBody .= "</tbody>
                <tfoot>
                    <tr><td colspan='2' style='text-align:right;'><strong>Total :</strong></td><td style='text-align:right;'><strong>" . number_format($prixTotalCommande, 2, ',', ' ') . " €</strong></td></tr>
                </tfoot>
              </table>";
$htmlBody .= "<p>Vous recevrez un autre email lorsque votre commande sera expédiée.</p>";
$htmlBody .= "<p>Cordialement,<br>L'équipe GamStore</p>";

// Corps Texte Brut (simplifié)
$altBody = "Merci pour votre commande chez GamStore !\n\n";
$altBody .= "Bonjour " . $nomClient . ",\n\n";
$altBody .= "Votre commande numéro #" . $idCommande . " a bien été enregistrée.\n\n";
$altBody .= "Récapitulatif :\n";
foreach ($produitsCommandes as $produit) {
    $altBody .= "- " . $produit['nom'] . " (Qté: " . $produit['quantite'] . ") - " . number_format($produit['prix'], 2, ',', ' ') . " €\n";
}
$altBody .= "\nTotal : " . number_format($prixTotalCommande, 2, ',', ' ') . " €\n\n";
$altBody .= "Vous recevrez un autre email lorsque votre commande sera expédiée.\n\n";
$altBody .= "Cordialement,\nL'équipe GamStore";


// --- Envoyer l'email ---
$emailSent = sendConfirmationEmail($mailClient, $sujet, $htmlBody, $altBody);

} else {
    // Rediriger si accès direct non POST
    header("Location: commande.php");
    exit;
}

// Fermeture implicite de la connexion PDO à la fin du script
?>