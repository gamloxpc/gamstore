<?php
session_start(); // Toujours démarrer la session en haut de chaque page

// Informations de connexion à la base de données
$host = 'localhost';
$dbname = 'gamstore';
$user = 'root';
$password = '';

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// Récupérer les informations de l'utilisateur
$userId = $_SESSION['user_id'] ?? null;
if (!is_null($userId)) {
    $stmt = $pdo->prepare("SELECT prenom, nom, mail, addresse, telephone, dateNaissance, ville, cp, complementAdresse, Pays, sexe FROM utilisateur WHERE idUser = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $user = null; // Aucun utilisateur connecté
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande</title>
    <link rel="stylesheet" href="principal.css">
    <link rel="shortcut icon" type="image/png" href="icon.webp"/>
    <link rel="stylesheet" href="commandeCss.css">
     <!-- mondiale relay JQuery required (>1.6.4) -->
     <script src="//ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
    
    <!-- Leaflet dependency is not required since it is loaded by the plugin -->
    <script src="//unpkg.com/leaflet/dist/leaflet.js"></script>
    <link rel="stylesheet" type="text/css" href="//unpkg.com/leaflet/dist/leaflet.css" />
    <script type="text/javascript" src="https://widget.mondialrelay.com/parcelshop-picker/v4_0_0/parcelshop-picker.js"></script>

    <!-- JS plugin for the Parcelshop Picker Widget MR using JQuery -->
    <script src="//widget.mondialrelay.com/parcelshop-picker/jquery.plugin.mondialrelay.parcelshoppicker.min.js"></script>
</head>
<body>
<?php include 'headerPanier.php';?>
<script src="search.js"></script>

<form action="traitement_commande.php" method="POST" id="commandeForm">

<h2>Votre Commande</h2>
<div class="commande-page">
    <div class="colonne-gauche">
        <?php if (isset($_SESSION['user_id']) && $user): // $user doit exister aussi ?>
            <section class="info-client">
                <h2>Information client</h2>
                <input type="text" name="Nom" maxlength="50" id="Nom" placeholder="Nom *" value="<?php echo htmlspecialchars($user['nom'] ?? '');?>">
                <p id="errorMessageNom" class="messageErreur" style="color: red; display: none;">Veuillez rentré votre nom.</p>

                <input type="text" name="Prenom" maxlength="50" id="Prenom" placeholder="Prénom *" value="<?php echo htmlspecialchars($user['prenom'] ?? ''); ?>">
                <p id="errorMessagePrenom" class="messageErreur"  style="color: red; display: none;">Veuillez rentré votre prénom.</p>

                <!-- CORRIGÉ: Ajout name="Email" -->
                <input type="email" name="Email" id="Email" maxlength="100" placeholder="Mail *" value="<?php echo htmlspecialchars($user['mail'] ?? ''); ?>">
                <p id="errorMessageEmail" class="messageErreur"  style="color: red; display: none;">Veuillez rentré votre email.</p>

                <!-- CORRIGÉ: Ajout name="Telephone" -->
                <input type="tel" name="Telephone" id="Telephone" maxlength="50"  placeholder="Numéro de Téléphone *" value="<?php echo htmlspecialchars($user['telephone'] ?? ''); ?>">
                <p id="errorMessageTel" class="messageErreur"  style="color: red; display: none;">Veuillez rentré votre numéro de téléphone.</p>

                <!-- CORRIGÉ: Ajout name="Adresse" -->
                <input type="text" name="Adresse" id="Adresse" maxlength="200" placeholder="Adresse *" value="<?php echo htmlspecialchars($user['addresse'] ?? ''); // Attention: 'addresse' avec 2 'd' ? Vérifiez BDD ?>">
                <p id="errorMessageAdresse" class="messageErreur"  style="color: red; display: none;">Veuillez rentré votre adresse.</p>

                <!-- CORRIGÉ: Ajout name="ComplementAdresse" -->
                <input type="text" name="ComplementAdresse" id="ComplementAdresse" maxlength="200" placeholder="Complément D'adresse"  value="<?php echo htmlspecialchars($user['complementAdresse'] ?? ''); ?>">

                <!-- CORRIGÉ: Ajout name="Ville" -->
                <input type="text" name="Ville" id="Ville" maxlength="100" placeholder="Ville *" value="<?php echo htmlspecialchars($user['ville'] ?? ''); ?>">
                <p id="errorMessageVille" class="messageErreur"  style="color: red; display: none;">Veuillez rentré votre ville.</p>

                <!-- CORRIGÉ: Ajout name="CodePostal" -->
                <input type="text" name="CodePostal" id="CodePostal" maxlength="50" placeholder="Code Postal *" value="<?php echo htmlspecialchars($user['cp'] ?? '');?>">
                <p id="errorMessageCodePostal" class="messageErreur"  style="color: red; display: none;">Veuillez rentré votre code postal.</p>

                <div class="choisisPays">
                    <select id="pays" name="pays" required> <!-- Ajout required -->
                         <option value="" disabled <?php echo empty($user['Pays']) ? 'selected' : ''; ?>>Sélectionner un pays *</option>
                         <option value="france" <?php echo (($user['Pays'] ?? '') === 'france') ? 'selected' : ''; ?>>France</option>
                         <option value="belgique" <?php echo (($user['Pays'] ?? '') === 'belgique') ? 'selected' : ''; ?>>Belgique</option>
                         <option value="suisse" <?php echo (($user['Pays'] ?? '') === 'suisse') ? 'selected' : ''; ?>>Suisse</option>
                         <option value="luxembourg" <?php echo (($user['Pays'] ?? '') === 'luxembourg') ? 'selected' : ''; ?>>Luxembourg</option>
                         <option value="allemagne" <?php echo (($user['Pays'] ?? '') === 'allemagne') ? 'selected' : ''; ?>>Allemagne</option>
                         <option value="espagne" <?php echo (($user['Pays'] ?? '') === 'espagne') ? 'selected' : ''; ?>>Espagne</option>
                         <option value="italie" <?php echo (($user['Pays'] ?? '') === 'italie') ? 'selected' : ''; ?>>Italie</option>
                     </select>
                </div>
                <p id="errorMessagePays" class="messageErreur"  style="color: red; display: none;">Veuillez sélectionner votre pays.</p>

                <div class="newsletter-group">
                     <input type="checkbox" id="addresseDifferente" name="addresseDifferente" value="1"> <!-- Value 1 -->
                     <label for="addresseDifferente">Adresse de livraison différente de celle de facturation</label>
                </div>
                <div id="addresseDifferenteDetails" style="display: none;">
                     <h3>Adresse de Livraison</h3>
                     <!-- CORRIGÉ: name="Nom_destinataire" etc. -->
                     <input type="text" name="Nom_destinataire" maxlength="50" id="Nom_destinataire" placeholder="Nom du destinataire *">
                     <p id="errorMessageNom_destinataire" ...></p>
                     <input type="text" name="Prenom_destinataire" id="Prenom_destinataire" placeholder="Prénom du destinataire *">
                     <p id="errorMessagePrenom_destinataire" ...></p>
                     <input type="email" name="Email_destinataire" id="Email_destinataire" placeholder="Email du destinataire *">
                     <p id="errorMessageEmail_destinataire" ...></p>
                     <input type="tel" name="Telephone_destinataire" id="Telephone_destinataire" placeholder="Téléphone du destinataire *">
                     <p id="errorMessageTel_destinataire" ...></p>
                     <input type="text" name="Adresse_destinataire" id="Adresse_destinataire" placeholder="Adresse du destinataire *">
                     <p id="errorMessageAdresse_destinataire" ...></p>
                     <input type="text" name="ComplementAdresse_destinataire" id="ComplementAdresse_destinataire" placeholder="Complément d'adresse du destinataire">
                     <!-- Message erreur pas indispensable ici -->
                     <input type="text" name="Ville_destinataire" id="Ville_destinataire" placeholder="Ville du destinataire *">
                     <p id="errorMessageVille_destinataire" ...></p>
                     <input type="text" name="CodePostal_destinataire" id="CodePostal_destinataire" placeholder="Code Postal du destinataire *">
                     <p id="errorMessageCodePostal_destinataire" ...></p>
                     <div class="choisisPays">
                         <label for="pays_destinataire">Pays du destinataire *:</label>
                         <select id="pays_destinataire" name="pays_destinataire">
                             <option value="" disabled selected>Sélectionner un pays</option>
                             <option value="france">France</option>
                             <!-- ... autres pays ... -->
                         </select>
                     </div>
                     <p id="errorMessagePays_destinataire" ...></p>
                </div>
                <div class="newsletter-group">
                     <input type="checkbox" id="newsletter" name="newsletter" value="1"> <!-- Value 1 -->
                     <label for="newsletter">J'accepte de recevoir par mails des dernière nouveauté du group © Gamlox Studio</label>
                </div>
            </section> <!-- Fin info-client connecté -->
        <?php else: ?>
            <section class="info-client">
                <h2>Information client</h2>
                <p>Connectez-vous ou continuez en tant qu'invité.</p>
                <!-- CORRIGÉ: Ajout des name="..." et required -->
                <input type="text" name="Nom" maxlength="50" id="Nom" placeholder="Nom *" required>
                <p id="errorMessageNom" ...></p>
                <input type="text" name="Prenom" id="Prenom" placeholder="Prénom *" required>
                <p id="errorMessagePrenom" ...></p>
                <input type="email" name="Email" id="Email" placeholder="Email *" required>
                <p id="errorMessageEmail" ...></p>
                <input type="tel" name="Telephone" id="Telephone" placeholder="Téléphone *" required>
                <p id="errorMessageTel" ...></p>
                <input type="text" name="Adresse" id="Adresse" placeholder="Adresse *" required>
                <p id="errorMessageAdresse" ...></p>
                <input type="text" name="ComplementAdresse" id="ComplementAdresse" placeholder="Complément d'adresse">
                <input type="text" name="Ville" id="Ville" placeholder="Ville *" required>
                <p id="errorMessageVille" ...></p>
                <input type="text" name="CodePostal" id="CodePostal" placeholder="Code Postal *" required>
                <p id="errorMessageCodePostal" ...></p>
                <div class="choisisPays">
                    <select id="pays" name="pays" required>
                        <option value="" disabled selected>Sélectionner un pays *</option>
                        <option value="france">France</option>
                        <!-- ... autres pays ... -->
                    </select>
                </div>
                 <p id="errorMessagePays" ...></p>
                <!-- Checkbox adresse différente et détails (avec les name corrects comme ci-dessus) -->
                 <div class="newsletter-group">
                     <input type="checkbox" id="addresseDifferente" name="addresseDifferente" value="1">
                     <label for="addresseDifferente">Adresse de livraison différente</label>
                 </div>
                 <div id="addresseDifferenteDetails" style="display: none;">
                      <h3>Adresse de Livraison</h3>
                      <input type="text" name="Nom_destinataire" ...>
                      <input type="text" name="Prenom_destinataire" ...>
                      <!-- ... autres champs desti avec name ... -->
                 </div>
                 <div class="newsletter-group">
                     <input type="checkbox" id="newsletter" name="newsletter" value="1">
                     <label for="newsletter">J'accepte de recevoir par mails...</label>
                 </div>
                </section>
                <?php endif; ?>  
                
                <section class="paiement-securise">
                    <h2>Paiement sécurisé</h2>
                    <!-- Contenu : Options de paiement, logos de sécurité, et -->
                </section>
            </div>
        
            <div class="colonne-droite">
            <section class="recap-commande">
    <div class="recap-box">
        <h3>Récapitulatif de la commande</h3>
        (<span id="cart-count">0</span>) <!-- N'oublie pas que cart-count est rempli dynamiquement -->
        <div class="recap-line">
            <span>Sous-total</span>
            <span id="total-articles">0.00€</span> 
        </div>
        <div class="recap-line">
            <span>Estimations des frais d'expédition et de manutention</span>
            <span id="frais-expedition">0.00€</span> <!-- Affiche les frais d'expédition -->
        </div>
        <div class="recap-line total">
            <span>Total (TVA incluse)</span>
            <span id="total-paiement">0.00€</span> <!-- Affiche le total à payer -->
        </div>
    </div>
</section>
              
                </section>       <!-- Code promo -->
                <div class="code-promo">
                    <p>Code Promo :</p>
                    <input type="text" placeholder="Saisir le code">
                    <button>Appliquer</button>
                </div><br>
                <section class="info-livraison">
                    <div class="form-group">
                        <label>Mode de livraison :</label>
                        <div class="delivery-option">
                            <input type="radio" id="mondial_relay_point" name="delivery_method" value="mondial_relay_point">
                            <label for="mondial_relay_point">Point Relais Mondial Relay</label>            <input type="hidden" name="cartData" id="cartDataInput">
                    <div id="mondial_relay_details" style="display: none;">
                    <button id="openModal" type="button">Choisir son point relais</button>
                    <div id="confirmationMessage" style="display: none;"></div>
                        <!-- Fenêtre modale -->
                        <div id="modal" class="modal">
                            <div class="modal-content">
                            <button type="button" id="validateRelayBtn">Validée</button>
                                
                                <h2>Choisissez votre point relais</h2>
                                <p>votre numéro de point relay est
                                <input type="text" id="Target_Widget" disabled="disabled" name="Target_Widget" readonly /> <!-- AJOUT DE name="Target_Widget" --></p>
                               <div id="Zone_Widget"></div> <!-- Widget Mondial Relay --> 
                            </div>
                        </div>
                    </div>
                    
                        </div>

                        <div class="delivery-option">
                            <input type="radio" id="mondial_relay_domicile" name="delivery_method" value="mondial_relay_domicile">
                            <label for="mondial_relay_domicile">Domicile Mondial Relay</label>
                        </div>

                        <div class="delivery-option">
                            <input type="radio" id="colissimo_domicile" name="delivery_method" value="colissimo_domicile">
                            <label for="colissimo_domicile">Domicile Colissimo</label>
                        </div>
                    </div>
            </section>

                    <div id="colissimo_details" style="display: none;">
                        <!-- Champs pour la livraison Colissimo -->
                    </div>


                <section class="partie-legal">
                    <div class="terms-container">
                        <label>
                            <input type="checkbox" id="termsCheckbox">
                            J'accepte les <a href="#" target="_blank">Conditions Générales de Vente</a>, 
                            la <a href="#" target="_blank">Politique de Confidentialité</a> et la 
                            <a href="#" target="_blank">Politique de Retours</a>.
                        </label>
                    </div>
                    
                    
                    <button type="submit" class="btn-commander" id="payButton">Commander et Payer</button>
                
                </section>
            </form>
            </div>
        </div><section id="cart" style="display: none;">
            <h2>Votre Panier</h2>
            <ul id="cart-items"></ul>
            <p> poid total :<span id="cart-poid">0</span>g</p>
            <p>Total : <span id="cart-total">0</span>€</p>
            <a  href="panier.php"><button class="BtnPanier">Valider le panier</button></a>
        </section>

                <?php include 'bas.php'?>

<script src="calculPrix.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const targetWidget = document.getElementById("Target_Widget");
    const openModalBtn = document.getElementById("openModal");
    const confirmationMessage = document.getElementById("confirmationMessage");
    const validateRelayBtn = document.getElementById("validateRelayBtn");
    const modal = document.getElementById("modal");

    validateRelayBtn.addEventListener("click", function () {
        const relayNumber = targetWidget.value.trim();

        if (relayNumber !== "") {
            // Cacher le bouton "Choisir son point relais"
            openModalBtn.style.display = "none";

            // Afficher le message de confirmation
            confirmationMessage.style.display = "block";
            confirmationMessage.className = "confirmation-success";
            confirmationMessage.innerHTML = `✅ Point relais sélectionné : <strong>${relayNumber}</strong>`;

            // Fermer la modale
            modal.style.display = "none";
        } else {
            alert("Veuillez sélectionner un point relais avant de valider.");
        }
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const termsCheckbox = document.getElementById('termsCheckbox');
    const payButton = document.getElementById('payButton');
    const errorMessage = document.getElementById('errorMessageBtn');

    const errorMessagePrenom = document.getElementById('errorMessagePrenom');
    const errorMessageNom = document.getElementById('errorMessageNom');
    const errorMessageEmail = document.getElementById('errorMessageEmail');
    const errorMessageTel = document.getElementById('errorMessageTel');
    const errorMessageAdresse = document.getElementById('errorMessageAdresse');
    const errorMessageVille = document.getElementById('errorMessageVille');
    const errorMessageCodePostal = document.getElementById('errorMessageCodePostal');
    const errorMessagePays = document.getElementById('errorMessagePays');

    const errorMessageNom_destinataire = document.getElementById('errorMessageNom_destinataire');
    const errorMessagePrenom_destinataire = document.getElementById('errorMessagePrenom_destinataire');
    const errorMessageEmail_destinataire = document.getElementById('errorMessageEmail_destinataire');
    const errorMessageTel_destinataire = document.getElementById('errorMessageTel_destinataire');
    const errorMessageAdresse_destinataire = document.getElementById('errorMessageAdresse_destinataire');
    const errorMessageVille_destinataire = document.getElementById('errorMessageVille_destinataire');
    const errorMessageCodePostal_destinataire = document.getElementById('errorMessageCodePostal_destinataire');
    const errorMessagePays_destinataire = document.getElementById('errorMessagePays_destinataire');

    const addresseDifferenteCheckbox = document.getElementById('addresseDifferente');
    const addresseDifferenteDetails = document.getElementById('addresseDifferenteDetails');

    // Fonction pour valider les champs du destinataire
    function validerDestinataire() {
        let isValid = true;

        if (document.getElementById('Nom_destinataire').value === "") {
            errorMessageNom_destinataire.style.display = 'block';
            isValid = false;
        } else {
            errorMessageNom_destinataire.style.display = 'none';
        }
        if (document.getElementById('Prenom_destinataire').value === "") {
            errorMessagePrenom_destinataire.style.display = 'block';
            isValid = false;
        } else {
            errorMessagePrenom_destinataire.style.display = 'none';
        }
        if (document.getElementById('Email_destinataire').value === "") {
            errorMessageEmail_destinataire.style.display = 'block';
            isValid = false;
        } else {
            errorMessageEmail_destinataire.style.display = 'none';
        }
        if (document.getElementById('Telephone_destinataire').value === "") {
            errorMessageTel_destinataire.style.display = 'block';
            isValid = false;
        } else {
            errorMessageTel_destinataire.style.display = 'none';
        }
        if (document.getElementById('Adresse_destinataire').value === "") {
            errorMessageAdresse_destinataire.style.display = 'block';
            isValid = false;
        } else {
            errorMessageAdresse_destinataire.style.display = 'none';
        }
        if (document.getElementById('Ville_destinataire').value === "") {
            errorMessageVille_destinataire.style.display = 'block';
            isValid = false;
        } else {
            errorMessageVille_destinataire.style.display = 'none';
        }
        if (document.getElementById('CodePostal_destinataire').value === "") {
            errorMessageCodePostal_destinataire.style.display = 'block';
            isValid = false;
        } else {
            errorMessageCodePostal_destinataire.style.display = 'none';
        }
        if (document.getElementById('pays_destinataire').value === "") {
            errorMessagePays_destinataire.style.display = 'block';
            isValid = false;
        } else {
            errorMessagePays_destinataire.style.display = 'none';
        }

        return isValid;
    }

    // Afficher/masquer les détails du destinataire
    addresseDifferenteCheckbox.addEventListener('change', function() {
        if (this.checked) {
            addresseDifferenteDetails.style.display = 'block';
        } else {
            addresseDifferenteDetails.style.display = 'none';
        }
    });

    // Validation du formulaire principal et du destinataire
    payButton.addEventListener('click', (e) => {
        let isFormValid = true;

        // Validation des termes et conditions
        if (!termsCheckbox.checked) {
            e.preventDefault();
            errorMessage.style.display = 'block';
            isFormValid = false;
        } else {
            errorMessage.style.display = 'none';
        }

        // Validation des champs principaux
        if (document.getElementById('Nom').value === "") {
            errorMessagePrenom.style.display = 'block';
            isFormValid = false;
        } else {
            errorMessagePrenom.style.display = 'none';
        }
        if (document.getElementById('Prenom').value === "") {
            errorMessageNom.style.display = 'block';
            isFormValid = false;
        } else {
            errorMessageNom.style.display = 'none';
        }
        if (document.getElementById('Email').value === "") {
            errorMessageEmail.style.display = 'block';
            isFormValid = false;
        } else {
            errorMessageEmail.style.display = 'none';
        }
        if (document.getElementById('Telephone').value === "") {
            errorMessageTel.style.display = 'block';
            isFormValid = false;
        } else {
            errorMessageTel.style.display = 'none';
        }
        if (document.getElementById('Adresse').value === "") {
            errorMessageAdresse.style.display = 'block';
            isFormValid = false;
        } else {
            errorMessageAdresse.style.display = 'none';
        }
        if (document.getElementById('Ville').value === "") {
            errorMessageVille.style.display = 'block';
            isFormValid = false;
        } else {
            errorMessageVille.style.display = 'none';
        }
        if (document.getElementById('CodePostal').value === "") {
            errorMessageCodePostal.style.display = 'block';
            isFormValid = false;
        } else {
            errorMessageCodePostal.style.display = 'none';
        }
        if (document.getElementById('pays').value === "") {
            errorMessagePays.style.display = 'block';
            isFormValid = false;
        } else {
            errorMessagePays.style.display = 'none';
        }

        // Validation des champs du destinataire si la checkbox est cochée
        if (addresseDifferenteCheckbox.checked) {
            if (!validerDestinataire()) {
                isFormValid = false;
                e.preventDefault();
            }
        }

        // Si le formulaire est valide, afficher le message de confirmation
        if (isFormValid) {
            alert("Votre commande est en cours de traitement !");
        } else {
            e.preventDefault();
        }
    });
});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const mondialRelayPointRadio = document.getElementById('mondial_relay_point');
    const mondialRelayDomicileRadio = document.getElementById('mondial_relay_domicile');
    const colissimoDomicileRadio = document.getElementById('colissimo_domicile');
    const cpNumberInput = document.getElementById('Code Postal');

    const mondialRelayDetails = document.getElementById('mondial_relay_details');
    const colissimoDetails = document.getElementById('colissimo_details');

    // Fonction pour masquer toutes les sections de détails
    function hideAllDetails() {
        mondialRelayDetails.style.display = 'none';
        colissimoDetails.style.display = 'none';
    }



    // Ajouter des écouteurs d'événements
    mondialRelayPointRadio.addEventListener('change', function() {
        if (this.checked) {
            hideAllDetails();
            mondialRelayDetails.style.display = 'block';
        }
    });

    mondialRelayDomicileRadio.addEventListener('change', function() {
        if (this.checked) {
            hideAllDetails();
            mondialRelayDetails.style.display = 'none';
            // Vous pouvez ajouter ici du code pour récupérer les frais de port
            // Mondial Relay à domicile en fonction de l'adresse de l'utilisateur.
        }
    });

    colissimoDomicileRadio.addEventListener('change', function() {
        if (this.checked) {
            hideAllDetails();
            colissimoDetails.style.display = 'none';
        }
    });
});
document.getElementById("openModal").addEventListener("click", function() {
    document.getElementById("modal").style.display = "block";
});


window.addEventListener("click", function(event) {
    if (event.target === document.getElementById("modal")) {
        document.getElementById("modal").style.display = "none";
    }
});

// Init the widget on ready state
$(document).ready(function() {
  // Loading the Parcelshop picker widget into the <div> with id "Zone_Widget" with such settings:
  $("#Zone_Widget").MR_ParcelShopPicker({
    //
    // Settings relating to the HTML.
    //
    // JQuery selector of the HTML element receiving the Parcelshop Number (ex: here, input type text, but should be input hidden)
    Target: "#Target_Widget",
    //
    // Settings for Parcelshop data access
    //
    // Code given by Mondial Relay, 8 characters (padding right with spaces)
    // BDTEST is used for development only => a warning appears
    Brand: "CC23B73M",
    // Default Country (2 letters) used for search at loading
    Country: "FR",
    // Default postal Code used for search at loading
    PostCode: "59000",
    // Delivery mode (Standard [24R], XL [24L], XXL [24X], Drive [DRI])
    ColLivMod: "24R",
    // Number of parcelshops requested (must be less than 20)
    NbResults: "7",
    //
    // Display settings
    //
    // Enable Responsive (nb: non responsive corresponds to the Widget used in older versions=
    Responsive: true,
		// Show the results on Leaflet map usng OpenStreetMap. 
    ShowResultsOnMap: true
  });

});
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    // ... (votre code existant : variables, event listeners, fonctions...)

    const payButton = document.getElementById('payButton');
    const commandeForm = document.getElementById('commandeForm'); // Récupérer le formulaire par ID
    const cartDataInput = document.getElementById('cartDataInput'); // Récupérer l'input caché

    payButton.addEventListener('click', (e) => {
        let isFormValid = true;

        // --- Votre validation existante des champs client, termes, etc. ---
        // ... (validation du nom, prenom, email, checkbox termes...) ...

        // --- Validation du panier (ne pas commander si vide) ---
        if (cart.length === 0) {
             alert("Votre panier est vide. Impossible de passer commande.");
             e.preventDefault(); // Empêcher la soumission
             isFormValid = false;
             return; // Sortir tôt
        }


        // --- Si toutes les validations JS passent ---
        if (isFormValid) {
            // 1. Récupérer le panier depuis la variable globale 'cart' (qui vient du localStorage)
            const cartDataForServer = cart; // Ou vous pouvez relire explicitement localStorage.getItem('shoppingCart')

            // 2. Convertir en JSON
            const cartJsonString = JSON.stringify(cartDataForServer);

            // 3. Mettre le JSON dans le champ caché
            if (cartDataInput) {
                cartDataInput.value = cartJsonString;
            } else {
                console.error("L'input caché #cartDataInput est manquant !");
                e.preventDefault(); // Empêcher la soumission si l'input manque
                return;
            }

             // Afficher l'alerte de confirmation (si vous la gardez)
             alert("Votre commande est en cours de traitement !");

             // Laisser le formulaire se soumettre normalement (ne plus faire e.preventDefault() ici)
             // ou utiliser commandeForm.submit(); si nécessaire après une action asynchrone (non le cas ici)

        } else {
             e.preventDefault(); // Empêcher la soumission si la validation a échoué
        }
    });

});
</script>
        <script src="script.js"></script>
    </body>
    </html> 