<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un administrateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php"); // Rediriger si non autorisé
    exit; // Important d'arrêter le script après une redirection
}

// Informations de connexion à la base de données
$host = 'localhost';
$dbname = 'gamstore';
$user = 'root';
$password = ''; 

try {
    // Connexion à la base de données avec PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    // Définir le mode d'erreur PDO sur Exception (très recommandé)
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Optionnel : Désactiver les émulations de requêtes préparées pour plus de sécurité
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // En production : logguer l'erreur et afficher un message générique
    error_log("Erreur de connexion BDD: " . $e->getMessage());
    die("Une erreur est survenue lors de la connexion à la base de données. Veuillez réessayer plus tard.");
}

try {
    // Récupérer la liste des clients
    $stmtClients = $pdo->prepare("SELECT idUser, prenom, nom, mail, telephone, addresse, dateInscription, actif FROM utilisateur ORDER BY nom, prenom");
    $stmtClients->execute();
    $clients = $stmtClients->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les demandes de contact (avec tri comme suggéré précédemment)
    // Assurez-vous que tous les noms de colonnes ici correspondent EXACTEMENT à votre table 'contact_submissions'
    $sqlContacts = "SELECT
                        id, etat, nom, email, raison, id_user, sujet, message,
                        numero_commande, raison_specifique_commande,
                        raison_specifique_produit, nom_produit, raison_specifique_retour,
                        date_soumission
                    FROM contact_submissions
                    ORDER BY
                        CASE etat
                            WHEN 'a_traiter' THEN 1
                            WHEN 'en_attente' THEN 2
                            WHEN 'en_charge' THEN 3
                            WHEN 'termine' THEN 4
                            ELSE 5
                        END, -- Trier par état (non traité en premier)
                        date_soumission DESC"; // Puis par date (plus récent en premier)
    $stmtContacts = $pdo->prepare($sqlContacts);
    $stmtContacts->execute();
    // La variable $contacts est correctement remplie ici avec les données des contacts
    $contacts = $stmtContacts->fetchAll(PDO::FETCH_ASSOC);

    $stmtProduits = $pdo->prepare("SELECT idProduit, nom, prix, actif FROM produit ORDER BY nom");
    $stmtProduits->execute();
    $produits = $stmtProduits->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Gérer les erreurs lors de l'exécution des requêtes
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    // Initialiser les variables pour éviter des erreurs plus loin dans le HTML
    $clients = $contacts = $produits = [];
    // Afficher un message d'erreur sur la page (pour l'admin)
    echo "<p style='color:red; border: 1px solid red; padding: 10px;'>Erreur lors de la récupération des données depuis la base de données. Vérifiez les logs pour plus de détails.</p>";
    // Vous pourriez vouloir arrêter le script ici ou afficher le reste de la page sans données
    // die(); // Décommentez pour arrêter complètement
}


// --- La fonction getStatusClass est correcte et peut rester ici ---
// Fonction pour obtenir une classe CSS basée sur l'état
function getStatusClass($status) {
    switch ($status) {
        case 'a_traiter': return 'status-todo';
        case 'en_attente': return 'status-pending';
        case 'en_charge': return 'status-progress';
        case 'termine': return 'status-done';
        default: return '';
    }
}
// --- Récupération des Avis ---
try {
    // La requête JOIN avec utilisateur et produit
    $sqlAvis = "SELECT
                    a.idAvis, a.note, a.commentaire, a.dateAvis, a.idUser, a.idProduit, -- Champs de la table avis (préfixés par 'a.')
                    u.prenom, u.nom AS nom_user,  -- Prénom et Nom (avec alias pour 'nom') depuis la table utilisateur (préfixés par 'u.')
                    p.nom AS nom_produit          -- Nom du produit (avec alias) depuis la table produit (préfixés par 'p.')
                FROM
                    avis a -- Alias 'a' pour la table avis
                LEFT JOIN utilisateur u ON a.idUser = u.idUser -- JOINTURE GAUCHE avec utilisateur (alias 'u')
                LEFT JOIN produit p ON a.idProduit = p.idProduit -- JOINTURE GAUCHE avec produit (alias 'p')
                ORDER BY a.dateAvis DESC"; // Optionnel: trier par date pour voir les plus récents en premier

$stmtAvis = $pdo->prepare($sqlAvis);
$stmtAvis->execute();
$avis = $stmtAvis->fetchAll(PDO::FETCH_ASSOC); // Récupère les avis avec les infos jointes

} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des avis: " . $e->getMessage());
    $avis = [];
    echo "<p style='color:red; border: 1px solid red; padding: 10px;'>Erreur lors de la récupération des avis depuis la base de données.</p>";
}

// Fonction pour générer des étoiles (peut être adaptée pour utiliser vos classes CSS si besoin)
function displayStars($rating) {
    $rating = max(0, min(5, intval($rating)));
    $fullStars = floor($rating);
    $emptyStars = 5 - $fullStars;
    // Utilisation des caractères Unicode pour simplicité
    $starsHtml = str_repeat('<span class="etoile-admin pleine">★</span>', $fullStars);
    $starsHtml .= str_repeat('<span class="etoile-admin vide">☆</span>', $emptyStars);
    return $starsHtml;
}
$commandesDetails = [];
try {
    // 1. Récupérer les commandes principales SANS les produits et SANS GROUP BY
    $sqlCommandesPrincipales = "SELECT
                                    c.idCommande, c.dateCommande, c.statut, c.prix AS montantTotal,
                                    c.nomClient, c.prenomClient, c.mailClient, c.telephoneClient,
                                    c.adresseClient, c.completAdresse, c.villeClient, c.cpClient, c.paysClient, c.delivery_method,
                                    c.numeroPointRelay, c.adresseDifferente,
                                    c.nomDesti, c.prenomDesti, c.mailDesti, c.telDesti,
                                    c.adresseDesti, c.completAdresseDesti, c.villeDesti, c.cpDesti, c.paysDesti,
                                    c.idUser
                                FROM
                                    commande c
                                ORDER BY
                                    CASE c.statut
                                        WHEN 'En cours' THEN 1
                                        WHEN 'Préparation' THEN 2
                                        WHEN 'Expédiée' THEN 3
                                        WHEN 'Livrée' THEN 4
                                        WHEN 'Annulée' THEN 5
                                        ELSE 6
                                    END,
                                    c.dateCommande DESC";

    $stmtCommandesPrincipales = $pdo->prepare($sqlCommandesPrincipales);
    $stmtCommandesPrincipales->execute();
    $commandesDetails = $stmtCommandesPrincipales->fetchAll(PDO::FETCH_ASSOC);

    // 2. Préparer une requête pour récupérer les produits d'UNE commande
    $sqlProduitsCommande = "SELECT
                                cp.quantite, cp.prixUnitaire,
                                p.idProduit, p.nom AS nomProduit
                            FROM
                                commande_produit cp
                            JOIN
                                produit p ON cp.idProduit = p.idProduit
                            WHERE
                                cp.idCommande = :idCommande";
    $stmtProduitsCommande = $pdo->prepare($sqlProduitsCommande);

     foreach ($commandesDetails as $key => $commande) {
        $stmtProduitsCommande->bindParam(':idCommande', $commande['idCommande'], PDO::PARAM_INT);
        $stmtProduitsCommande->execute();
        $produits = $stmtProduitsCommande->fetchAll(PDO::FETCH_ASSOC);

        // Ajouter le tableau des produits à la commande
        $commandesDetails[$key]['produits'] = $produits; // Stocker directement le tableau des produits

        // Calculer la quantité totale d'articles pour cette commande
        $totalQuantite = 0;
        foreach ($produits as $prod) {
            $totalQuantite += $prod['quantite'];
        }
        $commandesDetails[$key]['totalQuantiteProduits'] = $totalQuantite;
    }
    

} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des commandes: " . $e->getMessage());
    $commandesDetails = [];
    echo "<p style='color:red; border: 1px solid red; padding: 10px;'>Erreur lors de la récupération des commandes depuis la base de données.</p>";
    // --- POUR LE DEBUG UNIQUEMENT ---
     echo "<p style='color:red;'>Erreur SQL détaillée: " . htmlspecialchars($e->getMessage()) . "</p>";
    // --------------------------------
}
// Fonction pour la classe CSS du statut de commande
function getCommandeStatusClass($status) {
    switch (strtolower(str_replace(' ', '-', $status ?? ''))) { // Normalise le statut pour la classe CSS
        case 'en-cours': return 'status-encours';
        case 'préparation': return 'status-preparation';
        case 'expédiée': return 'status-expediee';
        case 'livrée': return 'status-livree';
        case 'annulée': return 'status-annulee';
        default: return 'status-inconnu';
    }
}

// Fonction pour déterminer le mode de livraison (simplifié)
function getModeLivraison($commande) {
    if (!empty($commande['numeroPointRelay'])) {
        return "Point Relay Mondial Relay";
    } 
    if ($commande['delivery_method'] == "mondial_relay_domicile") {
        return "Livraison à domicile Mondial Relay";
    } 
    if ($commande['delivery_method']  == "colissimo_domicile") {
        return "Livraison à domicile Colissimo";
    }
    return "Mode de livraison inconnu"; // Ajout d'un retour par défaut pour éviter les erreurs
}

// Initialiser les variables pour éviter les erreurs si les requêtes échouent
$statsCommandes = [
    'total_24h' => 0,
    'a_traiter' => 0, // Adaptez ces statuts à ceux que vous utilisez réellement
    'preparation' => 0,
    'expediee' => 0,
    'livree' => 0,
    // Ajoutez d'autres statuts si nécessaire
];
$produitsPlusCommandes = [];
$derniersAvis = [];
$statsAvis = [];
$statsUtilisateurs = [
    'total' => 0,
    'derniere_semaine' => 0,
];
$labelsProduits = [];
$dataProduits = [];
$labelsNotes = [];
$dataNotes = [];

try {
    // --- Statistiques Commandes ---
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN dateCommande >= NOW() - INTERVAL 1 DAY THEN 1 ELSE 0 END) as total_24h,
            SUM(CASE WHEN statut = 'En cours' OR statut = 'A traiter' THEN 1 ELSE 0 END) as a_traiter, -- Adaptez les noms des statuts
            SUM(CASE WHEN statut = 'Préparation' THEN 1 ELSE 0 END) as preparation,
            SUM(CASE WHEN statut = 'Expédiée' THEN 1 ELSE 0 END) as expediee,
            SUM(CASE WHEN statut = 'Livrée' THEN 1 ELSE 0 END) as livree
        FROM commande
    ");
    $stmt->execute();
    // Utiliser fetch plutôt que fetchAll car on attend une seule ligne de résultats
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        // Assigner les valeurs en vérifiant si elles sont null (au cas où il n'y aurait aucune commande)
        $statsCommandes['total_24h'] = $result['total_24h'] ?? 0;
        $statsCommandes['a_traiter'] = $result['a_traiter'] ?? 0;
        $statsCommandes['preparation'] = $result['preparation'] ?? 0;
        $statsCommandes['expediee'] = $result['expediee'] ?? 0;
        $statsCommandes['livree'] = $result['livree'] ?? 0;
    }


    // --- Produits les plus commandés (dernier mois) ---
    $stmt = $pdo->prepare("
        SELECT p.nom, SUM(cp.quantite) as total_quantite
        FROM commande_produit cp
        JOIN produit p ON cp.idProduit = p.idProduit
        JOIN commande c ON cp.idCommande = c.idCommande
        WHERE c.dateCommande >= NOW() - INTERVAL 1 MONTH
        GROUP BY cp.idProduit, p.nom
        ORDER BY total_quantite DESC
        LIMIT 5 -- Limite pour le graphique
    ");
    $stmt->execute();
    $produitsPlusCommandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Préparer les données pour Chart.js
    foreach ($produitsPlusCommandes as $prod) {
        $labelsProduits[] = $prod['nom'];
        $dataProduits[] = $prod['total_quantite'];
    }


    // --- 5 Derniers Avis ---
     // Assurez-vous d'avoir les colonnes nécessaires dans votre table avis et joignez si besoin
    $stmt = $pdo->prepare("
        SELECT a.idAvis, a.note, a.commentaire, a.dateAvis, u.prenom, u.nom AS nom_user, p.nom AS nom_produit
        FROM avis a
        LEFT JOIN utilisateur u ON a.idUser = u.idUser
        LEFT JOIN produit p ON a.idProduit = p.idProduit -- Joindre pour le nom du produit si l'avis est lié à un produit
        ORDER BY a.dateAvis DESC
        LIMIT 5
    ");
    $stmt->execute();
    $derniersAvis = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // --- Statistiques des Avis (Répartition par note) ---
    $stmt = $pdo->prepare("
        SELECT note, COUNT(*) as count_note
        FROM avis
        GROUP BY note
        ORDER BY note ASC
    ");
    $stmt->execute();
    $statsAvis = $stmt->fetchAll(PDO::FETCH_ASSOC);
     // Préparer les données pour Chart.js
    foreach ($statsAvis as $stat) {
        $labelsNotes[] = $stat['note'] . ' étoile' . ($stat['note'] > 1 ? 's' : ''); // Ex: "5 étoiles"
        $dataNotes[] = $stat['count_note'];
    }


    // --- Statistiques Utilisateurs ---
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM utilisateur) as total,
            (SELECT COUNT(*) FROM utilisateur WHERE dateInscription >= NOW() - INTERVAL 7 DAY) as derniere_semaine
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
     if ($result) {
        $statsUtilisateurs['total'] = $result['total'] ?? 0;
        $statsUtilisateurs['derniere_semaine'] = $result['derniere_semaine'] ?? 0;
    }

} catch (PDOException $e) {
    // Gérer l'erreur (log, message, etc.)
    error_log("Erreur BDD Dashboard Admin: " . $e->getMessage());
    // Afficher un message d'erreur sur la page si nécessaire
     echo "<p style='color:red; border: 1px solid red; padding: 10px;'>Erreur lors de la récupération des données du tableau de bord.</p>";
     // Il est important que les variables aient été initialisées à des valeurs par défaut
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Gamstore</title>
    <link rel="stylesheet" href="adminCss.css">
    <link rel="stylesheet" href="principal.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="adminJs.js"></script>
</head>
<body>
    <?php include 'header.php';?>
    <div class="admin-container">
        <nav class="admin-nav">
            <ul>
                <li data-section="dashboard" class="active">Tableau de Bord</li>
                <li data-section="gestion-produits">Gestion des Produits</li>
                <li data-section="gestion-commandes">Gestion des Commandes</li>
                <li data-section="gestion-clients" >Gestion des Clients</li>
                <li data-section="gestion-contacts">Gestion des Contacts</li>
                <li data-section="gestion-promotions">Gestion des Promotions</li>
                <li data-section="gestion-avis">Gestion des Avis Clients</li>
                <li data-section="gestion-site">Gestion du Site</li>
                <li><a href="admin_inventaire">gestion de stock</a></li>
            </ul>
        </nav>

        <main class="admin-content">
            <section id="dashboard" class="admin-section active ">
                <h2>Tableau de Bord</h2>

    <main class="dashboard-container">

        <!-- Section Récap Commandes -->
        <section class="dashboard-card commandes-recap">
            <h2><i class="fas fa-shopping-cart"></i> Récapitulatif des Commandes</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-value"><?php echo htmlspecialchars($statsCommandes['total_24h']); ?></span>
                    <span class="stat-label">Commandes (24h)</span>
                </div>
                <div class="stat-item status-a-traiter">
                    <span class="stat-value"><?php echo htmlspecialchars($statsCommandes['a_traiter']); ?></span>
                    <span class="stat-label">À traiter</span>
                </div>
                <div class="stat-item status-preparation">
                    <span class="stat-value"><?php echo htmlspecialchars($statsCommandes['preparation']); ?></span>
                    <span class="stat-label">En préparation</span>
                </div>
                <div class="stat-item status-expediee">
                    <span class="stat-value"><?php echo htmlspecialchars($statsCommandes['expediee']); ?></span>
                    <span class="stat-label">Expédiées</span>
                </div>
                 <div class="stat-item status-livree">
                    <span class="stat-value"><?php echo htmlspecialchars($statsCommandes['livree']); ?></span>
                    <span class="stat-label">Livrées</span>
                </div>
                <!-- Ajoutez d'autres statuts si nécessaire -->
            </div>
        </section>

        <!-- Section Produits populaires -->
        <section class="dashboard-card produits-populaires">
            <h2><i class="fas fa-chart-pie"></i> Produits Populaires (Mois)</h2>
            <?php if (!empty($labelsProduits)): ?>
                <div class="chart-container">
                    <canvas id="produitsChart"></canvas>
                </div>
             <?php else: ?>
                <p>Pas assez de données pour afficher le graphique des produits.</p>
            <?php endif; ?>
        </section>

        <!-- Section Derniers Avis -->
        <section class="dashboard-card derniers-avis">
             <h2><i class="fas fa-comments"></i> Derniers Avis Clients</h2>
             <?php if (!empty($derniersAvis)): ?>
                <ul>
                    <?php foreach ($derniersAvis as $avis): ?>
                        <li>
                            <div class="avis-header">
                                <span class="avis-note"><?php echo str_repeat('★', $avis['note']) . str_repeat('☆', 5 - $avis['note']); ?></span>
                                <span class="avis-meta">
                                     Par <?php echo htmlspecialchars($avis['prenom'] ?? 'Utilisateur inconnu'); ?>
                                     <?php if (!empty($avis['nom_produit'])): ?>
                                         sur <?php echo htmlspecialchars($avis['nom_produit']); ?>
                                     <?php endif; ?>
                                     le <?php echo htmlspecialchars(date('d/m/Y', strtotime($avis['dateAvis']))); ?>
                                </span>
                            </div>
                            <p class="avis-commentaire"><?php echo nl2br(htmlspecialchars(substr($avis['commentaire'], 0, 100))); ?>...</p>
                            <!-- Lien vers l'avis complet si nécessaire -->
                        </li>
                    <?php endforeach; ?>
                </ul>
                <!-- Lien vers la page de gestion des avis -->
                <a href="admin_avis.php" class="voir-plus-link">Voir tous les avis <i class="fas fa-arrow-right"></i></a>
             <?php else: ?>
                <p>Aucun avis récent trouvé.</p>
            <?php endif; ?>
        </section>


         <!-- Section Stats Avis -->
        <section class="dashboard-card stats-avis">
            <h2><i class="fas fa-star-half-alt"></i> Répartition des Notes</h2>
             <?php if (!empty($labelsNotes)): ?>
                 <div class="chart-container">
                    <canvas id="avisChart"></canvas>
                 </div>
            <?php else: ?>
                 <p>Pas assez de données pour afficher le graphique des avis.</p>
            <?php endif; ?>
        </section>

         <!-- Section Utilisateurs -->
        <section class="dashboard-card utilisateurs-recap">
            <h2><i class="fas fa-users"></i> Utilisateurs</h2>
             <div class="stats-grid user-stats">
                <div class="stat-item">
                    <span class="stat-value"><?php echo htmlspecialchars($statsUtilisateurs['total']); ?></span>
                    <span class="stat-label">Total Inscrits</span>
                </div>
                 <div class="stat-item">
                    <span class="stat-value"><?php echo htmlspecialchars($statsUtilisateurs['derniere_semaine']); ?></span>
                    <span class="stat-label">Inscrits (7 jours)</span>
                </div>
            </div>
             <!-- Lien vers la page de gestion des utilisateurs -->
             <a href="admin_users.php" class="voir-plus-link">Gérer les utilisateurs <i class="fas fa-arrow-right"></i></a>
        </section>

    </main>
            </section>

        <section id="gestion-commandes" class="admin-section">
                <h2>Gestion des Commandes</h2>

                <!-- <?php if (is_array($commandesDetails) && count($commandesDetails) > 0): ?> -->
                    <table id="commandes-table">
                        <thead>
                            <tr>
                                <th>ID Commande</th>
                                <th>Client</th>
                                <th>Nb. Articles</th> <!-- Quantité totale -->
                                <th>Montant Total</th>
                                <th>Mode Livraison</th>
                                <th>Statut</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commandesDetails as $cmd): ?>
                                <?php
                                    // --- CE BLOC DOIT ÊTRE ICI ---
                                    // Préparer le JSON des produits pour CETTE commande ($cmd)
                                    $produitsData = isset($cmd['produits']) && is_array($cmd['produits']) ? $cmd['produits'] : [];
                                    $produitsJson = htmlspecialchars(json_encode($produitsData), ENT_QUOTES, 'UTF-8');
                                    // Maintenant, $produitsJson existe et contient le JSON pour la commande actuelle
                                ?>
                                <tr class="commande-row <?php echo getCommandeStatusClass($cmd['statut']); ?>"
                                    title="Cliquez pour voir les détails"
                                    data-commande-id="<?php echo htmlspecialchars($cmd['idCommande']); ?>"
                                    data-date="<?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($cmd['dateCommande']))); ?>"
                                    data-statut="<?php echo htmlspecialchars($cmd['statut']); ?>"
                                    data-montant="<?php echo htmlspecialchars(number_format($cmd['montantTotal'] ?? 0, 2, ',', ' ')); ?>"
                                    data-id-user="<?php echo htmlspecialchars($cmd['idUser'] ?? ''); ?>"
                                    data-nom-client="<?php echo htmlspecialchars($cmd['nomClient'] ?? ''); ?>"
                                    data-prenom-client="<?php echo htmlspecialchars($cmd['prenomClient'] ?? ''); ?>"
                                    data-mail-client="<?php echo htmlspecialchars($cmd['mailClient'] ?? ''); ?>"
                                    data-tel-client="<?php echo htmlspecialchars($cmd['telephoneClient'] ?? ''); ?>"
                                    data-adresse-client="<?php echo htmlspecialchars($cmd['adresseClient'] ?? ''); ?>"
                                    data-complement-client="<?php echo htmlspecialchars($cmd['completAdresse'] ?? ''); ?>"
                                    data-ville-client="<?php echo htmlspecialchars($cmd['villeClient'] ?? ''); ?>"
                                    data-cp-client="<?php echo htmlspecialchars($cmd['cpClient'] ?? ''); ?>"
                                    data-pays-client="<?php echo htmlspecialchars($cmd['paysClient'] ?? ''); ?>"
                                    data-point-relay="<?php echo htmlspecialchars($cmd['numeroPointRelay'] ?? ''); ?>"
                                    data-adresse-diff="<?php echo htmlspecialchars($cmd['adresseDifferente'] ?? '0'); ?>"
                                    data-nom-desti="<?php echo htmlspecialchars($cmd['nomDesti'] ?? ''); ?>"
                                    data-prenom-desti="<?php echo htmlspecialchars($cmd['prenomDesti'] ?? ''); ?>"
                                    data-mail-desti="<?php echo htmlspecialchars($cmd['mailDesti'] ?? ''); ?>"
                                    data-tel-desti="<?php echo htmlspecialchars($cmd['telDesti'] ?? ''); ?>"
                                    data-adresse-desti="<?php echo htmlspecialchars($cmd['adresseDesti'] ?? ''); ?>"
                                    data-complement-desti="<?php echo htmlspecialchars($cmd['completAdresseDesti'] ?? ''); ?>"
                                    data-ville-desti="<?php echo htmlspecialchars($cmd['villeDesti'] ?? ''); ?>"
                                    data-cp-desti="<?php echo htmlspecialchars($cmd['cpDesti'] ?? ''); ?>"
                                    data-pays-desti="<?php echo htmlspecialchars($cmd['paysDesti'] ?? ''); ?>"
                                     
                                    data-produits="<?= $produitsJson ?>";
                                >   <!-- Fin de la balise <tr> ouvrante -->

                                    <!-- Cellules <td> -->
                                    <td>#<?php echo htmlspecialchars($cmd['idCommande']); ?></td>
                                    <td><?php echo htmlspecialchars($cmd['prenomClient'] . ' ' . $cmd['nomClient']); ?></td>
                                    <td style="text-align:center;"><?php echo htmlspecialchars($cmd['totalQuantiteProduits'] ?? 0); ?></td>
                                    <td style="text-align:right;"><?php echo htmlspecialchars(number_format($cmd['montantTotal'] ?? 0, 2, ',', ' ')); ?> €</td>
                                    <td><?php echo getModeLivraison($cmd); ?></td>
                                    <td class="status-cell">
                                         <span class="status-badge"><?php echo htmlspecialchars($cmd['statut'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($cmd['dateCommande']))); ?></td>
                                    <!-- Fin des cellules <td> -->

                                </tr> <!-- Balise <tr> fermante -->
                            <?php endforeach; ?>

                            <!-- Message si aucune commande -->
                            <?php if (empty($commandesDetails)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">Aucune commande trouvée.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <!-- <?php else: ?>-->
                    <!-- <p>Aucune commande trouvée.</p> -->
                    <!-- <?php if (!is_array($commandesDetails) && isset($e)) echo "<p style='color:red;'>Erreur lors de la récupération des commandes.</p>"; ?> -->
                <?php endif; ?> 
                
                <!-- Structure pour l'affichage détaillé des commandes (initialement cachée) -->
                <div id="commande-detail-overlay" class="commande-overlay"> <!-- Nom différent de contact-overlay -->
                    <div id="commande-detail-content" class="commande-detail-box">
                        <button id="close-commande-detail-btn" class="close-button">×</button>
                        <h3>Détails de la Commande #<span id="detail-commande-id"></span></h3>
                        <div class="commande-detail-grid">
                            <!-- Infos Générales -->
                            <div class="detail-section">
                                <h4>Informations Générales</h4>
                                <p><strong>Date :</strong> <span id="detail-commande-date"></span></p>
                                <p><strong>Statut Actuel :</strong> <span id="detail-commande-statut-text"></span></p>
                                <p><strong>Montant Total :</strong> <span id="detail-commande-montant"></span> €</p>
                                <p><strong>ID Utilisateur :</strong> <span id="detail-commande-iduser"></span></p>
                            </div>
                
                            <!-- Infos Client -->
                            <div class="detail-section">
                                <h4>Client (Facturation)</h4>
                                <p><strong>Nom :</strong> <span id="detail-commande-nom-client"></span></p>
                                <p><strong>Email :</strong> <span id="detail-commande-mail-client"></span></p>
                                <p><strong>Téléphone :</strong> <span id="detail-commande-tel-client"></span></p>
                                <p><strong>Adresse :</strong> <span id="detail-commande-adresse-client"></span></p>
                                <p><strong>Code postal :</strong><span id="detail-commande-cp-client"></span> </p>
                                <p><strong>Ville :</strong><span id="detail-commande-ville-client"></span></p>
                                <p><strong>Complément d'addresse : </strong><span id="detail-commande-complement-client"></span></p>
                                <p><strong>Pays :</strong><span id="detail-commande-pays-client"></span></p>
                            </div>
                
                             <!-- Infos Livraison -->
                            <div class="detail-section full-width" id="section-livraison">
                                <h4>Livraison</h4>
                                <div id="livraison-point-relay">
                                    <p><strong>Mode :</strong> Point Relay</p>
                                    <p><strong>N° / Nom Point Relay :</strong> <span id="detail-commande-point-relay"></span></p>
                                </div>
                                <div id="livraison-domicile">
                                     <p><strong>Mode :</strong> Livraison Standard / Domicile</p>
                                     <p><strong>Destinataire :</strong> <span id="detail-commande-nom-desti"></span></p>
                                     <p><strong>Email Dest. :</strong> <span id="detail-commande-mail-desti"></span></p>
                                     <p><strong>Tél Dest. :</strong> <span id="detail-commande-tel-desti"></span></p>
                                     <p><strong>Adresse Livraison :</strong> <span id="detail-commande-adresse-desti"></span></p>
                                     <p><span id="detail-commande-cp-desti"></span> <span id="detail-commande-ville-desti"></span></p>
                                     <p><span id="detail-commande-pays-desti"></span></p>
                                     <p><span id="detail-commande-complement-desti"></span></p>
                                </div>
                                 <p id="adresse-identique-msg" style="display:none;"><i>(Adresse de livraison identique à l'adresse de facturation)</i></p>
                            </div>
                
                             <!-- Produits Commandés -->
                            <div class="detail-section full-width">
                                 <h4>Produits Commandés</h4>
                                 <table id="detail-produits-table">
                                     <thead>
                                         <tr>
                                             <th>Produit</th>
                                             <th>Quantité</th>
                                             <th>Prix Unitaire</th>
                                             <th>Total Ligne</th>
                                         </tr>
                                     </thead>
                                     <tbody>
                                        
                                     </tbody>
                                 </table>
                            </div>
                
                            <!-- Gestion de l'état -->
                            <div class="detail-section full-width status-management">
                                 <h4>Modifier le Statut</h4><br>
                                 <label for="change-commande-status">Nouveau Statut :</label>
                                 <select id="change-commande-status" name="statut">
                                     <!-- Remplir avec les statuts possibles de votre système -->
                                     <option value="En cours">En cours</option>
                                     <option value="Préparation">Préparation</option>
                                     <option value="Expédiée">Expédiée</option>
                                     <option value="Livrée">Livrée</option>
                                     <option value="Annulée">Annulée</option>
                                     <!-- Ajoutez d'autres statuts si nécessaire -->
                                 </select>
                                 <button id="save-commande-status-btn">Enregistrer Statut</button>
                                 <span id="commande-status-save-feedback"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        <section id="gestion-clients" class="admin-section">
                <h2>Gestion des Clients</h2>

                <h3>Liste des Clients</h3>
                <?php if (count($clients) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Prénom</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Adresse</th>
                                <th>Date Inscription</th>
                                <th>Actif</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['idUser']); ?></td>
                                    <td><?php echo htmlspecialchars($client['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($client['nom']); ?></td>
                                    <td><?php echo htmlspecialchars($client['mail']); ?></td>
                                    <td><?php echo htmlspecialchars($client['telephone']); ?></td>
                                    <td><?php echo htmlspecialchars($client['addresse']); ?></td>
                                    <td><?php echo htmlspecialchars($client['dateInscription']); ?></td>
                                    <td><?php echo htmlspecialchars($client['actif'] ? 'Oui' : 'Non'); ?></td>
                                    <td>
                                        <form method="post" action="desactiver_client.php">
                                            <input type="hidden" name="idClient" value="<?php echo htmlspecialchars($client['idUser']); ?>">
                                            <button type="submit"><?php echo htmlspecialchars($client['actif'] ? 'Désactiver' : 'Activer'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun client enregistré.</p>
                <?php endif; ?>

  
            </section>
            <section id="gestion-contacts" class="admin-section">
                <h3>Demandes de Contact</h3>
                <?php if (is_array($contacts) && count($contacts) > 0): ?>
                    <table id="contact-table">
                        <!-- ... thead ... -->
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Raison Principale</th>
                                <th>Date</th>
                                <th>État</th>
                            </tr>
                        </thead>
                         <!-- ... tbody avec la boucle foreach et les data-* ... -->
                         <tbody>
                                <?php foreach ($avis as $item): ?>
                                    <?php
                                        // Pré-traitement avec les BONNES CLÉS et sécurités (??)
                                        $idAvis = $item['idAvis'] ?? 'N/A';
                                        $note = $item['note'] ?? 0;
                                        $commentaire = $item['commentaire'] ?? '';
                                        $dateAvisStr = $item['dateAvis'] ?? null;
                                
                                        // Auteur - Utilise 'prenom' et 'nom_user' (comme vu dans le var_dump)
                                        $prenomAuteur = $item['prenom'] ?? '';
                                        $nomAuteur = $item['nom_user'] ?? ''; // <= CORRIGÉ ICI
                                        $auteurComplet = trim(htmlspecialchars($prenomAuteur) . ' ' . htmlspecialchars($nomAuteur));
                                        $auteurAffichage = $auteurComplet ?: 'Auteur inconnu'; // Fallback
                                
                                        // Produit - Utilise 'nom_produit' (comme vu dans le var_dump)
                                        $nomProduit = $item['nom_produit'] ?? 'Produit inconnu'; // <= CORRIGÉ ICI
                                
                                        // Formatage sécurisé de la date
                                        $dateFormatee = 'Date invalide';
                                        if ($dateAvisStr) {
                                            $timestamp = strtotime($dateAvisStr);
                                            if ($timestamp !== false) {
                                                $dateFormatee = date('d/m/Y H:i', $timestamp);
                                            }
                                        }
                                    ?>
                                    <tr class="avis-row" data-avis-id="<?php echo htmlspecialchars($idAvis); ?>">
                                        <td><?php echo htmlspecialchars($idAvis); ?></td>
                                        <td>
                                            <?php echo $auteurAffichage; // Affiche le nom formaté ou le fallback ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($nomProduit); // Affiche le nom du produit ?>
                                        </td>
                                        <td class="avis-note">
                                            <?php
                                                if (function_exists('displayStars')) {
                                                     echo displayStars($note);
                                                } else {
                                                    echo str_repeat('★', $note) . str_repeat('☆', 5 - $note);
                                                }
                                             ?>
                                            (<?php echo htmlspecialchars($note); ?>/5)
                                        </td>
                                        <td title="<?php echo htmlspecialchars($commentaire); ?>">
                                            <?php
                                                $commentaireSecurise = htmlspecialchars($commentaire);
                                                $extrait = mb_substr($commentaireSecurise, 0, 80);
                                                echo $extrait;
                                                if (mb_strlen($commentaireSecurise) > 80) {
                                                    echo '...';
                                                }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($dateFormatee); ?></td>
                                        <td class="actions-cell">
                                            <?php if ($idAvis !== 'N/A'): ?>
                                                <button class="action-btn delete-btn" data-id="<?php echo htmlspecialchars($idAvis); ?>">Supprimer</button>
                                            <?php else: ?>
                                                Action impossible
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                    </table>
                 <?php else: ?>
                    <p>Aucune demande de contact pour le moment.</p>
                    <?php if (!is_array($contacts) && isset($e)) echo "<p style='color:red;'>Erreur lors de la récupération des contacts.</p>"; // Message si erreur PDO ?>
                 <?php endif; ?>
                        <!-- Structure pour l'affichage détaillé (initialement cachée) -->
                    <div id="contact-detail-overlay" class="contact-overlay">
                        <div id="contact-detail-content" class="contact-detail-box">
                            <button id="close-detail-btn" class="close-button">×</button>
                            <h3>Détails de la demande <span id="detail-id"></span></h3>
                            <div class="detail-grid">
                                <p><strong>Nom :</strong> <span id="detail-nom"></span></p>
                                <p><strong>Email :</strong> <a id="detail-email-link" href="#"><span id="detail-email"></span></a></p>
                                <p><strong>Date :</strong> <span id="detail-datesoumission"></span></p>
                                <p><strong>ID Utilisateur :</strong> <span id="detail-iduser"></span></p>
                                <p><strong>Raison Principale :</strong> <span id="detail-raison"></span></p>
                                <p><strong>Sujet :</strong> <span id="detail-sujet"></span></p>
                                <p class="detail-full-width"><strong>Message :</strong></p>
                                <p id="detail-message" class="detail-full-width message-box"></p>

                                <!-- Champs Conditionnels -->
                                <div id="detail-commande-section" class="conditional-section detail-full-width">
                                    <!-- *** CORRECTION ICI *** -->
                                    <h4>Infos Commande</h4>
                                    <p><strong>N° Commande :</strong> <span id="detail-numcommande"></span></p>
                                    <p><strong>Raison Spécifique :</strong> <span id="detail-raisoncommande"></span></p>
                                    <!-- ********************** -->
                                </div>
                                 <div id="detail-produit-section" class="conditional-section detail-full-width">
                                     <!-- *** CORRECTION ICI *** -->
                                     <h4>Infos Produit</h4>
                                     <p><strong>Nom Produit :</strong> <span id="detail-nomproduit"></span></p>
                                     <p><strong>Raison Spécifique :</strong> <span id="detail-raisonproduit"></span></p>
                                     <!-- ********************** -->
                                 </div>
                                 <div id="detail-retour-section" class="conditional-section detail-full-width">
                                     <!-- *** CORRECTION ICI *** -->
                                     <h4>Infos Retour</h4>
                                     <p><strong>N° Commande :</strong> <span id="detail-numcommande-retour"></span></p>
                                     <p><strong>Nom Produit :</strong> <span id="detail-nomproduit-retour"></span></p>
                                     <p><strong>Raison Spécifique :</strong> <span id="detail-raisonretour"></span></p>
                                     <!-- ********************** -->
                                 </div>

                                 <!-- Gestion de l'état -->
                                <div class="detail-full-width status-management">
                                      <p><strong>État Actuel :</strong> <span id="detail-etat-text"></span></p>
                                     <label for="change-status">Changer l'état :</label>
                                     <select id="change-status" name="etat">
                                         <option value="a_traiter">À traiter</option>
                                         <option value="en_attente">En attente</option>
                                         <option value="en_charge">En charge</option>
                                         <option value="termine">Terminé</option>
                                     </select>
                                     <button id="save-status-btn">Enregistrer État</button>
                                     <span id="status-save-feedback"></span>
                                </div>
                            </div>
                        </div>
                    </div>
            </section>
           

            <section id="gestion-promotions" class="admin-section">
                <h2>Gestion des Promotions</h2>
                <!-- ... -->
            </section>

            <section id="gestion-avis" class="admin-section">
                <h2>Gestion des Avis Clients</h2>

                <?php if (is_array($avis) && count($avis) > 0): ?>
                    <table id="avis-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Auteur</th>
                                <th>Produit</th>
                                <th>Note</th>
                                <th>Commentaire (Extrait)</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($avis as $item): ?>
                                <tr class="avis-row " data-avis-id="<?php echo htmlspecialchars($item['idAvis']); ?>">
                                    <td><?php echo htmlspecialchars($item['idAvis']); ?></td>
                                    <td>
                                        <?php
                                            // Afficher Prénom Nom si disponible, sinon ID User, sinon 'Visiteur' (ajuster si vous avez d'autres infos)
                                            $auteur = trim(htmlspecialchars($item['auteur_prenom'] ?? '') . ' ' . htmlspecialchars($item['auteur_nom'] ?? ''));
                                            echo $auteur ?: ($item['idUser']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($item['idProduit']); ?>
                                    </td>
                                    <td class="avis-note">
                                        <?php echo displayStars($item['note']); ?>
                                        (<?php echo htmlspecialchars($item['note']); ?>/5)
                                    </td>
                                    <td title="<?php echo htmlspecialchars($item['commentaire']); ?>">
                                        <?php
                                            $commentaireCourt = mb_substr(htmlspecialchars($item['commentaire']), 0, 80);
                                            echo $commentaireCourt;
                                            if (mb_strlen(htmlspecialchars($item['commentaire'])) > 80) {
                                                echo '...';
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($item['dateAvis']))); // Utilisation de dateAvis ?></td>
                                    <td class="actions-cell">
                                    <button class="action-btn delete-btn" data-id="<?php echo htmlspecialchars($item['idAvis']); ?>">Supprimer</button> <!-- Remplace data-id par l'ID réel -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Aucun avis client trouvé pour le moment.</p>
                    <?php if (!is_array($avis) && isset($e)) echo "<p style='color:red;'>Erreur lors de la récupération des avis.</p>"; ?>
                <?php endif; ?>
            </section>

            <section id="gestion-site" class="admin-section">
                <h2>Gestion du Site</h2>
                <!-- ... -->
            </section>
        </main>
    </div>
    <script>
           // Passer les données PHP aux variables JavaScript
           const labelsProduits = <?php echo json_encode($labelsProduits); ?>;
        const dataProduits = <?php echo json_encode($dataProduits); ?>;
        const labelsNotes = <?php echo json_encode($labelsNotes); ?>;
        const dataNotes = <?php echo json_encode($dataNotes); ?>;

        // Configuration des couleurs (vous pouvez les personnaliser)
        const chartColors = [
            '#4CAF50', '#2196F3', '#FFC107', '#FF5722', '#9C27B0',
            '#E91E63', '#00BCD4', '#8BC34A', '#CDDC39', '#FF9800'
        ];

        // Graphique Produits Populaires
        const ctxProduits = document.getElementById('produitsChart')?.getContext('2d');
        if (ctxProduits && labelsProduits.length > 0) {
            new Chart(ctxProduits, {
                type: 'doughnut', // ou 'pie'
                data: {
                    labels: labelsProduits,
                    datasets: [{
                        label: 'Quantité commandée',
                        data: dataProduits,
                        backgroundColor: chartColors,
                        hoverOffset: 4
                    }]
                },
                 options: {
                    responsive: true,
                    maintainAspectRatio: false, // Permet au conteneur de définir la taille
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Top 5 Produits les plus commandés (Mois)'
                        }
                    }
                }
            });
        }

        // Graphique Répartition des Notes
        const ctxAvis = document.getElementById('avisChart')?.getContext('2d');
         if (ctxAvis && labelsNotes.length > 0) {
            new Chart(ctxAvis, {
                type: 'pie', // ou 'doughnut'
                data: {
                    labels: labelsNotes,
                    datasets: [{
                        label: 'Nombre d\'avis',
                        data: dataNotes,
                        backgroundColor: chartColors.slice().reverse(), // Utiliser des couleurs différentes ou inversées
                        hoverOffset: 4
                    }]
                },
                 options: {
                    responsive: true,
                    maintainAspectRatio: false,
                     plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Répartition des Notes des Avis'
                        }
                    }
                }
            });
        }
        </script>
    <script>
document.querySelectorAll('.delete-btn').forEach(button => {
    button.addEventListener('click', function() {
        const rowId = this.getAttribute('data-id'); // Récupère l'ID

        console.log("ID récupéré :", rowId); // Vérifie si l'ID est bien affiché dans la console

        if (!rowId) {
            alert("Erreur : ID non trouvé !");
            return;
        }

        if (confirm("Voulez-vous vraiment supprimer cette ligne ?")) {
            fetch('deleteAvis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ idAvis: rowId }) // Envoie bien en JSON
            })
            .then(response => response.json())
            .then(data => {
                console.log("Réponse du serveur :", data);
                if (data.success) {
                    alert("Ligne supprimée avec succès !");
                    location.reload(); // Recharge la page après suppression
                } else {
                    alert("Erreur : " + data.message);
                }
            })
            .catch(error => console.error('Erreur lors de la requête :', error));
        }
    });
});

</script>
    <script src="adminJs.js"></script>
</body>
</html>