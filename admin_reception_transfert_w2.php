<?php
// Début de session, vérification admin, connexion PDO ($pdo)
session_start();
// if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit; }
require_once 'config/database.php';
require_once 'includes/helpers.php'; // Pour getStatusClass

// --- Récupérer l'ID du Transfert depuis l'URL ---
$idTransfert = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // 'id' correspond au lien généré

if (!$idTransfert) {
    $_SESSION['error_message'] = "ID de transfert invalide.";
    header('Location: admin_inventaire.php?tab=transferts');
    exit;
}

// --- Récupérer les informations ---
$transfert = null;
$uniqueItems = [];
$statsItemsTransfert = ['total_transfert' => 0, 'en_transit' => 0, 'deja_recu' => 0];
$error_message = null;

try {
    // Infos de l'en-tête du transfert
    $stmtTransfert = $pdo->prepare("
        SELECT t.*, es.nom as nomSource, ed.nom as nomDest
        FROM transferts_stock t
        JOIN entrepot es ON t.idEntrepotSource = es.idEntrepot
        JOIN entrepot ed ON t.idEntrepotDestination = ed.idEntrepot
        WHERE t.idTransfert = :idTransfert
    ");
    $stmtTransfert->bindParam(':idTransfert', $idTransfert, PDO::PARAM_INT);
    $stmtTransfert->execute();
    $transfert = $stmtTransfert->fetch(PDO::FETCH_ASSOC);

    if (!$transfert) {
        $_SESSION['error_message'] = "Transfert non trouvé.";
        header('Location: admin_inventaire.php?tab=transferts');
        exit;
    }

    // Items uniques associés à ce transfert
    // Assurez-vous que la colonne 'idTransfertActuel' existe dans 'stock_items_uniques'
    // et qu'elle est mise à jour dans 'actions/finalize_fabrication_transfer.php'
     $stmtUniqueItems = $pdo->prepare("
         SELECT siu.*, p.nom as nomProduit, p.reference_sku
         FROM stock_items_uniques siu
         JOIN produit p ON siu.idProduit = p.idProduit
         WHERE siu.idTransfertActuel = :idTransfert -- Filtrer par l'ID du transfert lié
           AND siu.statut IN ('en_transit', 'disponible') -- Montrer ceux en transit ou déjà reçus/dispo pour ce transfert
         ORDER BY FIELD(siu.statut, 'en_transit') DESC, p.nom, siu.code_barre_interne -- Afficher ceux en transit en premier
     ");

    $stmtUniqueItems->bindParam(':idTransfert', $idTransfert, PDO::PARAM_INT);
    $stmtUniqueItems->execute();
    $uniqueItems = $stmtUniqueItems->fetchAll(PDO::FETCH_ASSOC);

    // Calculer des stats rapides pour l'affichage
    $statsItemsTransfert['total_transfert'] = count($uniqueItems);
    foreach ($uniqueItems as $item) {
        if ($item['statut'] === 'en_transit') $statsItemsTransfert['en_transit']++;
        // Vérifier l'entrepôt destination pour 'deja_recu'
        elseif ($item['statut'] === 'disponible' && $item['idEntrepotActuel'] == $transfert['idEntrepotDestination']) $statsItemsTransfert['deja_recu']++;
    }

} catch (PDOException $e) {
    $error_message = "Erreur de base de données : " . $e->getMessage();
    // Les variables sont déjà initialisées
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réception Transfert TR-<?php echo htmlspecialchars($idTransfert); ?> (W2)</title>
    <link rel="stylesheet" href="adminInventaire.css"> <!-- Assurez-vous que le nom est correct -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Styles spécifiques (comme avant) */
        .transfert-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #dee2e6;}
        .transfert-details p { margin: 0.3rem 0; font-size: 0.95rem; }
        .transfert-details strong { color: #495057; min-width: 150px; display: inline-block;}
        .item-stats { display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap; }
        .stat-box { background-color: #f8f9fa; padding: 0.8rem 1rem; border-radius: 5px; border: 1px solid #e9ecef; text-align: center; }
        .stat-box .value { font-size: 1.5rem; font-weight: bold; display: block; }
        .stat-box .label { font-size: 0.9rem; color: #6c757d; }
        .scan-activation-area { margin-bottom: 1.5rem; padding: 1rem; background-color: #eef; border-radius: 5px; border: 1px solid #dde;}
        .scan-result-activation { margin-top: 1rem; padding: 1rem; background-color: #fff; border: 1px solid #dee2e6; border-radius: 5px; min-height: 60px; }
        .scan-result-activation .item-actions button { margin-top: 10px;}
        .items-list-table th, .items-list-table td { font-size: 0.9rem; }
        .items-list-table .item-code { font-family: monospace; }
        .status-row-en_transit { background-color: #fffadc; }
        .status-row-disponible { background-color: #e6ffed; text-decoration: line-through; color: #555; }
        .message { padding: 1rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: 0.25rem; display: flex; align-items: center; gap: 10px; }
        .message.success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
        .message.error { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
        .error-box { /* Style pour $error_message PHP */ }
        .no-data { text-align: center; color: #6c757d; padding: 1rem; }
    </style>
</head>
<body>
<div class="admin-layout">
<div class="main-content">
     <header class="main-header">
        <h1><i class="fas fa-pallet"></i> Réception Transfert #TR-<?= htmlspecialchars($idTransfert) ?> vers W2</h1>
         <div class="header-actions">
             <a href="admin_inventaire.php?tab=transferts" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour Liste Transferts</a>
         </div>
    </header>

    <div class="content-body" style="padding: 1.5rem;">
         <!-- Affichage des messages session -->
         <?php if (isset($_SESSION['success_message'])): ?> <div class="message success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div><?php endif; ?>
         <?php if (isset($_SESSION['error_message'])): ?> <div class="message error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div><?php endif; ?>
         <?php if ($error_message): ?> <p class="error-box"><?= htmlspecialchars($error_message); ?></p><?php endif; ?>

          <?php if ($transfert): ?>
             <!-- Détails du transfert -->
             <div class="transfert-details card"><div class="card-body">...</div></div>

            <!-- Stats Items -->
             <div class="item-stats">...</div>

            <!-- Zone de Scan W2 -->
              <div class="scan-activation-area card">
                 <div class="card-body">
                     <label for="scan-item-activation-input"><strong>Scanner le QR Code de l'article reçu à W2 :</strong></label>
                     <div class="input-group">
                         <span class="input-group-icon"><i class="fas fa-qrcode"></i></span>
                         <input type="text" id="scan-item-activation-input" placeholder="Scanner l'étiquette de l'item...">
                         <button id="lookup-item-activation-btn"><i class="fas fa-search"></i></button>
                     </div>
                     <div id="scan-result-activation" class="scan-result-activation">
                         <p class="placeholder-text">En attente de scan...</p>
                     </div>
                 </div>
             </div>

            <!-- Liste des Items du Transfert -->
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-tags"></i> Items Inclus</h3></div>
                 <div class="card-body">
                     <?php if (!empty($uniqueItems)): ?>
                        <div class="table-responsive">
                            <table class="data-table items-list-table">
                                <thead>
                                    <tr>
                                        <th>Code Interne</th>
                                        <th>Produit</th>
                                        <th>SKU</th>
                                        <th>Variante</th>
                                        <th>Statut Item</th>
                                        <th>Action Rapide</th>
                                    </tr>
                                </thead>
                                <tbody id="activation-item-list-body">
                                    <?php foreach ($uniqueItems as $item): ?>
                                    <tr id="item-row-<?= $item['idItemUnique'] ?>" class="status-row-<?= $item['statut'] ?>">
                                        <td class="item-code"><?= htmlspecialchars($item['code_barre_interne']); ?></td>
                                        <td><?= htmlspecialchars($item['nomProduit']); ?></td>
                                        <td><?= htmlspecialchars($item['reference_sku'] ?: 'N/A'); ?></td>
                                        <td>
                                             <?php
                                                $variants = [];
                                                if (!empty($item['taille'])) $variants[] = "T: " . htmlspecialchars($item['taille']);
                                                if (!empty($item['couleur'])) $variants[] = "C: " . htmlspecialchars($item['couleur']);
                                                echo !empty($variants) ? implode(' / ', $variants) : 'Standard';
                                            ?>
                                        </td>
                                        <td><span class="status-badge item-status-badge <?= getStatusClass($item['statut']) ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $item['statut']))); ?></span></td>
                                        <td>
                                            <?php if ($item['statut'] === 'en_transit'): ?>
                                                <button class="btn btn-success btn-sm activate-stock-btn" data-item-id="<?= $item['idItemUnique'] ?>" title="Confirmer Réception & Activer Stock">
                                                    <i class="fas fa-check-circle"></i> Activer
                                                </button>
                                            <?php elseif ($item['statut'] === 'disponible'): ?>
                                                 <span class="text-success"><i class="fas fa-check-circle"></i> Activé</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                         </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                     <?php else: ?>
                         <p class="no-data">Aucun item unique associé à ce transfert ou erreur.</p>
                     <?php endif; ?>
                 </div>
             </div>

             <?php if ($transfert && isset($transfert['idTransfert'])): ?>
                 <a href="actions/generate_labels.php?transfert_id=<?php echo htmlspecialchars($transfert['idTransfert']); ?>" class="btn btn-primary btn-sm" title="Imprimer Étiquettes pour ce Transfert" target="_blank">
                     <i class="fas fa-print"></i> Imprimer Étiquettes (TR-<?= htmlspecialchars($transfert['idTransfert']) ?>)
                 </a>
             <?php endif; // Fin du if pour le lien impression ?>

             <!-- Bouton Marquer comme Reçu -->
             <div class="actions-footer">
                <a href="admin_inventaire.php?tab=transferts" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
                <?php
                // Condition pour afficher le bouton/formulaire "Marquer comme Reçu"
                if($transfert['statut'] !== 'recu' && $statsItemsTransfert['en_transit'] == 0 && $statsItemsTransfert['total_transfert'] > 0 ):
                ?>
                     <form action="actions/mark_transfer_received.php" method="POST" style="display: inline;" onsubmit="return confirm('Marquer ce transfert comme entièrement reçu ?');">
                         <input type="hidden" name="idTransfert" value="<?= htmlspecialchars($idTransfert) ?>">
                         <button type="submit" class="btn btn-primary"><i class="fas fa-clipboard-check"></i> Marquer comme Reçu</button>
                     </form>
                <?php
                endif; // <<< AJOUTER CE ENDIF pour fermer le if du bouton
                ?>
                <!-- Ajouter ici d'autres messages si le transfert n'est pas prêt ou déjà reçu si besoin -->
                <?php if($transfert['statut'] === 'recu'): ?>
                     <span class="status-info-text text-success"><i class="fas fa-check-double"></i> Transfert déjà marqué comme reçu.</span>
                <?php elseif ($statsItemsTransfert['en_transit'] > 0): ?>
                     <span class="status-info-text text-warning"><i class="fas fa-info-circle"></i> Encore <?= $statsItemsTransfert['en_transit'] ?> item(s) à activer.</span>
                 <?php endif; ?>
             </div>

        <?php // Le else correspondant au if ($transfert) du début est ici ?>
        <?php else: ?>
            <p class="error-box">Impossible de charger les informations du transfert.</p>
        <?php endif; // Fin du if ($transfert) principal ?>

    </div> <!-- /.content-body -->
</div> <!-- /.main-content -->
</div> <!-- /.admin-layout -->

            

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Sélection Éléments W2 ---
    const scanInputW2 = document.getElementById('scan-item-activation-input');
    const lookupBtnW2 = document.getElementById('lookup-item-activation-btn');
    const scanResultDisplayW2 = document.getElementById('scan-result-activation');
    const activationItemListBody = document.getElementById('activation-item-list-body');
    const eventDelegateTargetW2 = document.querySelector('.content-body') || document.body;
    const qrScanInputW2 = document.getElementById('scan-item-activation-input');

    // --- Fonctions Utilitaires ---
    function escapeHtml(unsafe) {
        if (unsafe === null || typeof unsafe === 'undefined') return '';
        return unsafe.toString().replace(/&/g, "&").replace(/</g, "<").replace(/>/g, ">").replace(/"/g, "\"").replace(/'/g, "'");
    }
    function ucfirst(str) { if (!str) return ''; return str.charAt(0).toUpperCase() + str.slice(1); }

    function getStatusClass(status) {
        if (!status) return 'status-secondary';
        status = status.toLowerCase();
        switch (status) {
            case 'attente_reception': return 'status-warning';
            case 'partiellement_recu': return 'status-info';
            case 'recu_complet': case 'recu_w1_attente_transfert': return 'status-success';
            case 'en_fabrication': return 'status-info';
            case 'attente_transfert': return 'status-ready';
            case 'en_transit': return 'status-info';
            case 'attente_reception_dest': return 'status-warning';
            case 'disponible': case 'recu': case 'termine': return 'status-done';
            case 'annule': case 'perdu': case 'defectueux': case 'perdu_fabrication': return 'status-danger';
            case 'reserve': return 'status-pending';
            default: return 'status-secondary';
        }
    }

    // --- Fonction AJAX pour Activer Item ---
    function activateItemStock(itemId, buttonElement) {
        console.log(`DEBUG JS: Appel activateItemStock - Item: ${itemId}`);
        // Cibler les boutons DANS la portée de l'écouteur principal ou un parent fiable
        const relatedButtons = eventDelegateTargetW2.querySelectorAll(`button[data-item-id="${itemId}"]`);
        relatedButtons.forEach(btn => { btn.disabled = true; });
        let originalButtonHtml = null;
        if (buttonElement) {
            originalButtonHtml = buttonElement.innerHTML;
            buttonElement.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Activation...`;
            buttonElement.dataset.originalHtml = originalButtonHtml;
        }

        // Zone de résultat spécifique à l'activation W2
        const scanResultItemActionsW2 = scanResultDisplayW2 ? scanResultDisplayW2.querySelector(`.item-details[data-item-id="${itemId}"] .item-actions`) : null;
        if (scanResultItemActionsW2) {
             scanResultItemActionsW2.innerHTML = `<p class="loading"><i class="fas fa-spinner fa-spin"></i> Activation...</p>`;
        }

        fetch('actions/activate_item.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'idItemUnique=' + encodeURIComponent(itemId)
        })
        .then(response => response.ok ? response.json() : response.text().then(text => { throw new Error(text || `Erreur HTTP ${response.status}`); }))
        .then(data => {
             console.log("DEBUG JS: Réponse AJAX activateItemStock:", data);
             if (data.success) {
                 const newStatusText = 'Disponible';
                 const newStatusClass = getStatusClass('disponible');

                 // Mettre à jour la zone de résultat du scan
                 if (scanResultItemActionsW2) {
                     scanResultItemActionsW2.innerHTML = `<p class="success"><i class="fas fa-check-circle"></i> Item ${escapeHtml(data.code_interne || itemId)} activé !</p>`;
                 }
                 // Mettre à jour la ligne du tableau
                 const tableRow = activationItemListBody ? activationItemListBody.querySelector(`#item-row-${itemId}`) : null;
                 if(tableRow){
                     tableRow.className = 'status-row-disponible';
                     const statusBadge = tableRow.querySelector('.item-status-badge');
                     if(statusBadge){ statusBadge.textContent = newStatusText; statusBadge.className = `status-badge item-status-badge ${newStatusClass}`; }
                     const actionCell = tableRow.querySelector('td:last-child');
                     if(actionCell){ actionCell.innerHTML = `<span class="text-success"><i class="fas fa-check-circle"></i> Activé</span>`; }
                 }
                 // Mettre à jour les statistiques affichées sur la page
                 updateTransferStatsDisplay();

                 // Essayer de vider le champ scan en le re-sélectionnant
                 // **** CORRECTION: Déplacer cette logique à l'intérieur du bloc success ****
                 
                 const inputFieldW2 = document.getElementById('scan-item-activation-input');
                 if (inputFieldW2) {
                     inputFieldW2.value = '';
                     console.log("DEBUG JS: Champ scan W2 vidé (via re-sélection).");
                     // Optionnel : Remettre le focus après un court délai
                     // setTimeout(() => inputFieldW2.focus(), 50);
                 } else {
                      console.warn("DEBUG JS: Impossible de trouver #qr-scan-input-w2 pour le vider après succès.");
                 }

            } else { // Si data.success est false (erreur logique serveur)
                 alert(`Erreur activation: ${data.error || 'Inconnue'}`);
                 // Réactiver les boutons
                 relatedButtons.forEach(btn => { btn.disabled = false; if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;});
                 // Afficher échec dans la zone de scan si elle existe pour cet item
                 if (scanResultItemActionsW2){scanResultItemActionsW2.innerHTML = `<p class="error">Échec Activation</p>`;}
             }
        })
        .catch(error => { // Erreur réseau ou parsing JSON
            console.error('Erreur Fetch activate item:', error);
            alert(`Erreur activation: ${error.message || 'Inconnue'}`);
            // Réactiver les boutons
            relatedButtons.forEach(btn => { btn.disabled = false; if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;});
            // Afficher échec dans la zone de scan si elle existe pour cet item
            if (scanResultItemActionsW2){ scanResultItemActionsW2.innerHTML = `<p class="error">Échec Communication</p>`;}
        });
    } // Fin activateItemStock
     // --- Fonction Affichage Détails Item Scan W2 ---
     function displayActivationItemDetails(item, productInfo) {
         if (!scanResultDisplayW2 || !item) return; // Sécurités
         let html = `<div class="item-details" data-item-id="${item.idItemUnique}"><h4>${escapeHtml(productInfo?.nom || 'Produit inconnu')}</h4>`;
         html += `<p><strong>Code Interne:</strong> <span class="item-code">${escapeHtml(item.code_barre_interne || 'Code Manquant')}</span></p>`;
         html += `<p><strong>Taille :</strong> ${escapeHtml(item.taille || 'N/A')}</p>`;
         html += `<p><strong>Couleur :</strong> ${escapeHtml(item.couleur || 'N/A')}</p>`;
         html += `<p><strong>Statut Actuel:</strong> <span class="status-badge item-status-badge ${getStatusClass(item.statut)}">${escapeHtml(ucfirst((item.statut || 'inconnu').replace(/_/g, ' ')))}</span></p>`;
         if(item.nomEntrepotActuel) { html += `<p><strong>Entrepôt Actuel:</strong> ${escapeHtml(item.nomEntrepotActuel)}</p>`; }
         html += `<div class="item-actions">`;
         const canActivate = (item.statut === 'en_transit'); // Statut exact attendu
         if (canActivate) { html += `<button class="btn btn-success activate-stock-btn" data-item-id="${item.idItemUnique}"><i class="fas fa-check-circle"></i> Confirmer & Activer</button>`; }
         else if (item.statut === 'disponible') { html += `<p class="info"><i class="fas fa-check-circle"></i> Déjà activé.</p>`; }
         else { html += `<p class="warning"><i class="fas fa-exclamation-triangle"></i> Non en attente de réception (Statut: ${escapeHtml(item.statut || 'inconnu')}).</p>`; }
         html += `</div></div>`; scanResultDisplayW2.innerHTML = html;
     }

     // --- Fonction Mise à Jour Stats Transfert (Exemple Simple) ---
     function updateTransferStatsDisplay(){
         console.log("Appel updateTransferStatsDisplay");
         // Recalculer depuis le tableau (méthode simple mais peut être imprécise avec bcp d'items/pagination)
          let enTransitCount = 0;
          let dejaRecuCount = 0;
          if(activationItemListBody){
              activationItemListBody.querySelectorAll('tr').forEach(row => {
                 if(row.classList.contains('status-row-en_transit')) enTransitCount++;
                 else if(row.classList.contains('status-row-disponible')) dejaRecuCount++;
              });
          }
          const spanTransit = document.getElementById('stat-en-transit');
          const spanRecu = document.getElementById('stat-deja-recu');
          if(spanTransit) spanTransit.textContent = enTransitCount;
          if(spanRecu) spanRecu.textContent = dejaRecuCount;

          // Cacher/Montrer le bouton "Marquer comme Reçu" si enTransitCount == 0
          const finalBtnForm = document.querySelector('.actions-footer form[action="actions/mark_transfer_received.php"]');
           if(finalBtnForm) {
               const totalItemsSpan = document.querySelector('.item-stats .stat-box:first-child .value');
               const totalItems = totalItemsSpan ? parseInt(totalItemsSpan.textContent, 10) : 0;
               if (enTransitCount === 0 && totalItems > 0) {
                   finalBtnForm.style.display = 'inline';
               } else {
                   finalBtnForm.style.display = 'none';
               }
           }

         // Idéalement, appeler un script PHP pour avoir les vrais comptes BDD
     }

    // --- Scan/Recherche Item sur page Réception W2 ---
    if (scanInputW2) {
        const handleActivationItemLookup = () => {
             const barcode = scanInputW2.value.trim();
             if (!barcode) { if(scanResultDisplayW2) scanResultDisplayW2.innerHTML = '<p class="error">Scan vide.</p>'; return; }
             if(scanResultDisplayW2) scanResultDisplayW2.innerHTML = '<p class="loading">Recherche...</p>';
             fetch('actions/inventory_lookup.php', { method: 'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: 'barcode='+encodeURIComponent(barcode) })
             .then(response => response.ok ? response.json() : Promise.reject(`Erreur HTTP ${response.status}`))
             .then(data => {
                 if(!scanResultDisplayW2) return;
                 if(data.error){ scanResultDisplayW2.innerHTML = `<p class="error">${data.error}</p>`; }
                 else if(data.uniqueItem){ displayActivationItemDetails(data.uniqueItem, data.product); scanInputW2.value=''; scanInputW2.focus(); }
                 else { scanResultDisplayW2.innerHTML = `<p class="error">Item non trouvé.</p>`; }
             }).catch(error => { console.error('Erreur lookup W2:', error); if(scanResultDisplayW2) scanResultDisplayW2.innerHTML = `<p class="error">Erreur recherche. (${error})</p>`; });
        };
        scanInputW2.addEventListener('keypress', (e) => { if(e.key === 'Enter'){ e.preventDefault(); handleActivationItemLookup();} });
        if(lookupBtnW2) lookupBtnW2.addEventListener('click', handleActivationItemLookup);
    }

    // --- Délégation Clic sur Bouton Activer ---
     if (eventDelegateTargetW2) {
         eventDelegateTargetW2.addEventListener('click', function(event) {
             const button = event.target.closest('button.activate-stock-btn');
             if (button) {
                 const itemId = button.dataset.itemId;
                 if(itemId) { console.log("DEBUG JS: Clic sur Activer pour item", itemId); activateItemStock(itemId, button); }
             }
         });
         console.log("DEBUG JS: Écouteur clic pour activation attaché.");
     } else { console.error("ERREUR JS: eventDelegateTargetW2 non trouvé !"); }

      // --- Initialisation ---
      if (qrScanInputW2) { setTimeout(() => qrScanInputW2.focus(), 100); 
     // Mettre à jour l'affichage initial des stats et du bouton final
     updateTransferStatsDisplay();
} 
});
</script>
</script>

</body>
</html>