// Variable globale pour le panier, chargée au démarrage
let cart = [];
const CART_STORAGE_KEY = 'shoppingCart'; // Clé unique pour localStorage

/**
 * Sauvegarde le contenu actuel du tableau 'cart' dans le localStorage.
 */
function SaveCart() {
    try {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(cart));
        // console.log("Panier sauvegardé:", cart); // Optionnel pour debug
    } catch (error) {
        console.error("SaveCart: Erreur lors de la sauvegarde dans localStorage:", error);
    }
}

/**
 * Charge le panier depuis le localStorage au chargement de la page.
 * Réinitialise le panier si les données sont corrompues.
 */
function LoadCart() {
    const storedCart = localStorage.getItem(CART_STORAGE_KEY);
    if (storedCart) {
        try {
            // Vérification simple si ça ressemble à un tableau JSON
            if (storedCart.startsWith('[') && storedCart.endsWith(']')) {
                cart = JSON.parse(storedCart);
                // console.log("Panier chargé:", cart); // Optionnel pour debug
            } else {
                console.warn("LoadCart: Données localStorage corrompues (pas un tableau JSON), panier réinitialisé.");
                cart = [];
                localStorage.removeItem(CART_STORAGE_KEY); // Nettoyer localStorage
            }
        } catch (error) {
            console.error("LoadCart: Erreur lors du parsing du JSON depuis localStorage:", error);
            cart = [];
            localStorage.removeItem(CART_STORAGE_KEY); // Nettoyer localStorage
        }
    } else {
        cart = []; // Pas de panier sauvegardé
    }
    // Mettre à jour l'affichage après le chargement
    updateCart();
}

/**
 * Ajoute un produit au panier ou met à jour sa quantité.
 * @param {number} productIdNum - L'ID numérique unique du produit (depuis la BDD).
 * @param {string} productName - Le nom du produit.
 * @param {number} productPrice - Le prix unitaire du produit.
 * @param {string} taille - La taille sélectionnée.
 * @param {string} color - La couleur sélectionnée.
 * @param {number} productPoid - Le poids unitaire du produit.
 * @param {number} quantity - La quantité à ajouter.
 */
function addToCart(productIdNum, productName, productPrice, taille, color, productPoid, quantity) {
    // --- Validation Robuste des Paramètres ---
    const numProductId = parseInt(productIdNum, 10);
    const numPrice = parseFloat(productPrice);
    const numPoid = parseFloat(productPoid);
    const numQuantity = parseInt(quantity, 10);

    if (isNaN(numProductId) || numProductId <= 0) {
        alert("Erreur : ID Produit invalide."); return;
    }
    if (!productName || typeof productName !== 'string' || productName.trim() === '') {
        alert("Erreur : Nom du produit invalide."); return;
    }
    if (isNaN(numPrice) || numPrice < 0) { // Permettre prix 0 ? A adapter si besoin.
        alert("Erreur : Prix du produit invalide."); return;
    }
     if (!taille || typeof taille !== 'string' || taille.trim() === '') {
        alert("Erreur : Taille invalide ou non sélectionnée."); return;
    }
     if (!color || typeof color !== 'string' || color.trim() === '') {
        alert("Erreur : Couleur invalide ou non sélectionnée."); return;
    }
     if (isNaN(numPoid) || numPoid < 0) {
        alert("Erreur : Poids du produit invalide."); return;
    }
    if (isNaN(numQuantity) || numQuantity <= 0) {
        alert("Erreur : Quantité invalide."); return;
    }

    // Créer un ID unique pour cette *combinaison* produit/couleur/taille dans le panier JS
    const cartItemId = `${numProductId}-${color}-${taille}`;

    // Chercher si cette combinaison existe déjà dans le panier
    const existingProductIndex = cart.findIndex(item => item.cartId === cartItemId);

    if (existingProductIndex > -1) {
        // Le produit/variation existe, on met à jour la quantité
        const currentQuantity = parseInt(cart[existingProductIndex].quantity, 10) || 0;
        cart[existingProductIndex].quantity = currentQuantity + numQuantity;
        console.log(`Quantité mise à jour pour ${productName} (${color}/${taille}). Nouvelle quantité: ${cart[existingProductIndex].quantity}`);
    } else {
        // Nouveau produit/variation, on l'ajoute au panier
        cart.push({
            cartId: cartItemId,         // ID interne pour la gestion du panier JS
            productId: numProductId,    // <<< ID NUMÉRIQUE DE LA BDD (essentiel pour le serveur)
            name: productName,
            price: numPrice,            // Prix au moment de l'ajout
            quantity: numQuantity,
            color: color,
            taille: taille,
            poid: numPoid              // Poids au moment de l'ajout
        });
        console.log(`Produit ajouté: ${productName} (${color}/${taille}), Qte: ${numQuantity}`);
    }

    updateCart(); // Mettre à jour l'affichage (compteur, liste, total)
    SaveCart();   // Sauvegarder le panier mis à jour dans localStorage
    alert(`${productName} (${color}/${taille}) ajouté au panier !`); // Feedback utilisateur
}


/**
 * Met à jour l'affichage complet du panier (compteur, liste, total, poids).
 */
function updateCart() {
    const cartCountElement = document.getElementById('cart-count');
    const cartItemsElement = document.getElementById('cart-items');
    const cartTotalElement = document.getElementById('cart-total');
    const cartPoidElement = document.getElementById('cart-poid');
    const cartSection = document.getElementById('cart');

    if (!cartItemsElement) {
        // console.warn("updateCart: Élément #cart-items non trouvé. Affichage de la liste impossible.");
        // return; // Sortir si la liste n'existe pas (ex: sur page commande)
    }

    let totalItems = 0;
    let totalPrice = 0;
    let totalPoids = 0;

    if(cartItemsElement) cartItemsElement.innerHTML = ''; // Vider la liste actuelle

    cart.forEach((item, index) => {
        // Assurer que les données sont numériques
        const itemQuantity = parseInt(item.quantity, 10) || 0;
        const itemPrice = parseFloat(item.price) || 0;
        const itemPoid = parseFloat(item.poid) || 0;

        totalItems += itemQuantity;
        totalPrice += itemPrice * itemQuantity;
        totalPoids += itemPoid * itemQuantity;

        // Mettre à jour la liste HTML uniquement si l'élément existe
        if (cartItemsElement) {
             const li = document.createElement('li');
             // Utiliser item.productId pour référence si besoin, mais afficher name, color, taille
             li.innerHTML = `
                 ${item.name || 'Nom inconnu'} (${item.color || 'N/A'} / ${item.taille || 'N/A'})
                  x ${itemQuantity} - ${(itemPrice * itemQuantity).toFixed(2)}€
                 <button class="remove-btn" data-index="${index}" title="Supprimer cet article"> Supprimer </button>
             `;
             cartItemsElement.appendChild(li);
        }
    });

    // Mettre à jour le compteur total d'articles
    if (cartCountElement) {
        cartCountElement.textContent = totalItems;
    }

    // Mettre à jour le prix total
    if (cartTotalElement) {
        cartTotalElement.textContent = totalPrice.toFixed(2);
    }

    // Mettre à jour le poids total
    if (cartPoidElement) {
        cartPoidElement.textContent = totalPoids.toFixed(3); // 3 décimales pour kg? Adaptez si poids en g.
    }

     // Attacher les écouteurs aux nouveaux boutons supprimer (si la liste existe)
     if (cartItemsElement) {
        const removeButtons = cartItemsElement.querySelectorAll('.remove-btn');
        removeButtons.forEach(button => {
            button.removeEventListener('click', handleRemoveItem); // Enlever ancien listener pour éviter doublons
            button.addEventListener('click', handleRemoveItem);
        });
     }


}


/**
 * Gère le clic sur un bouton "Supprimer" dans la liste du panier.
 * @param {Event} e L'événement de clic.
 */
function handleRemoveItem(e) {
    const button = e.currentTarget; // Utiliser currentTarget est plus sûr
    const index = parseInt(button.dataset.index, 10);
    if (!isNaN(index)) {
        removeFromCart(index);
    } else {
        console.error("handleRemoveItem: Index invalide depuis data-index", button.dataset.index);
    }
}

/**
 * Supprime un article du panier basé sur son index.
 * @param {number} index L'index de l'article à supprimer dans le tableau `cart`.
 */
function removeFromCart(index) {
    if (index >= 0 && index < cart.length) {
        console.log(`Suppression de l'article à l'index ${index}:`, cart[index]);
        cart.splice(index, 1); // Supprimer l'élément du tableau
        updateCart();
        SaveCart();
    } else {
        console.error(`removeFromCart: Index ${index} hors limites.`);
    }
}


/**
 * Met à jour la taille sélectionnée visuellement et dans le champ caché.
 * DOIT être appelée par onclick="selectSize(this)" sur les div.size-option.
 * @param {HTMLElement} selectedElement L'élément div.size-option cliqué.
 */
function selectSize(selectedElement) {
    if (!selectedElement) return;

    // Trouver le conteneur parent le plus proche pour cibler les options de ce produit uniquement
    const productContainer = selectedElement.closest('.product-options, .product'); // Adapter le sélecteur parent
    if (!productContainer) {
        console.error("selectSize: Impossible de trouver le conteneur parent du produit.");
        return;
    }

    // Retirer la classe active des options de taille DANS CE CONTENEUR
    productContainer.querySelectorAll('.size-option').forEach(option => {
        option.classList.remove('active');
    });

    // Ajouter la classe active sur l'élément cliqué
    selectedElement.classList.add('active');

    // Mettre à jour la valeur du champ caché DANS CE CONTENEUR
    const hiddenInput = productContainer.querySelector('.selected-size-input');
    if (hiddenInput) {
        hiddenInput.value = selectedElement.dataset.value || selectedElement.textContent.trim(); // Utiliser data-value si défini
        // console.log("Taille sélectionnée mise à jour :", hiddenInput.value); // Debug
    } else {
         console.error("selectSize: Input caché .selected-size-input non trouvé dans le conteneur produit.");
    }
}


// --- Code exécuté au chargement du DOM ---
document.addEventListener('DOMContentLoaded', () => {
    // Charger le panier depuis localStorage dès que possible
    LoadCart();

    // Sélectionner les éléments une seule fois
    const cartLink = document.getElementById('cart-link');
    const shopLink = document.getElementById('shop-link'); // Si vous avez ce lien
    const TshopSection = document.getElementById('Tshop');   // Si vous avez cette section
    const cartSection = document.getElementById('cart');
    const addToCartButtons = document.querySelectorAll('.add-to-cart-button');

    // --- Gestion Clic Boutons "Ajouter au Panier" ---
    addToCartButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const productElement = e.target.closest('.product'); // Trouver le conteneur parent .product
            if (!productElement) {
                console.error("Impossible de trouver l'élément parent '.product' du bouton.");
                return;
            }

            // Récupérer les données depuis les attributs data-* du conteneur .product
            const productIdNum = productElement.dataset.productId; // <<< ID NUMÉRIQUE
            const productName = productElement.dataset.name;
            const productPrice = productElement.dataset.price;
            const productPoid = productElement.dataset.poid;

            // Récupérer les options DANS le conteneur .product
            const quantityInput = productElement.querySelector('.quantity-input');
            const colorSelect = productElement.querySelector('.color-select');
            const hiddenSizeInput = productElement.querySelector('.selected-size-input'); // Champ caché MAJ par selectSize

            // Vérifier que tous les éléments nécessaires existent
            if (!productIdNum || !productName || !productPrice || productPoid === undefined) {
                 alert("Erreur : Données produit manquantes (vérifiez les attributs data-* sur .product)."); return;
            }
            if (!quantityInput) { alert("Erreur : Champ quantité manquant."); return; }
            if (!colorSelect) { alert("Erreur : Sélecteur de couleur manquant."); return; }
            if (!hiddenSizeInput) { alert("Erreur : Champ taille cachée manquant."); return; }

            // Récupérer les valeurs sélectionnées
            const quantity = parseInt(quantityInput.value, 10);
            const color = colorSelect.value;
            const taille = hiddenSizeInput.value; // Lire la valeur du champ caché

            // Valider les valeurs récupérées
            if (isNaN(quantity) || quantity <= 0) {
                alert("Veuillez entrer une quantité valide (supérieure à 0)."); return;
            }
             if (!color) {
                alert("Veuillez sélectionner une couleur."); return;
            }
            if (!taille) {
                alert("Veuillez sélectionner une taille."); return;
            }

            // Appeler addToCart avec toutes les infos, y compris l'ID numérique et la quantité
            addToCart(productIdNum, productName, productPrice, taille, color, productPoid, quantity);
        });
    });


}); // Fin DOMContentLoaded 