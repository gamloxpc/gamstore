// Initialiser un tableau pour le panier
let cart = [];

// Sélectionner les éléments du DOM
const cartCountElement = document.getElementById('cart-count');
const cartItemsElement = document.getElementById('cart-items');
const cartTotalElement = document.getElementById('cart-total');
const cartSection = document.getElementById('cart');
const cartLink = document.getElementById('cart-link'); // Logo du panier

const shopLink = document.getElementById('shop-link'); // Lien pour afficher #Tshop
const TshopSection = document.getElementById('Tshop'); // Section #Tshop

function SaveCart() {
    localStorage.setItem('cartItems', JSON.stringify(cart));
}

// Fonction pour charger le panier depuis le localStorage
function LoadCart() {
    const storedCart = localStorage.getItem('cartItems');
    if (storedCart) {
        cart = JSON.parse(storedCart);
    }
    updateCart(); // Mettre à jour l'affichage du panier après le chargement
}

LoadCart(); // Charger le panier au démarrage

// Ajouter un produit au panier (MODIFIÉE)
function addToCart(productName, productPrice, quantity, size, color) {
    // Vérifier que la quantité est un nombre entier valide
    if (isNaN(quantity) || quantity <= 0) {
        alert("Veuillez entrer une quantité valide.");
        return;
    }

    // Vérifier si le produit est déjà dans le panier (en tenant compte de la taille et de la couleur)
    const existingProduct = cart.find(item => item.name === productName && item.size === size && item.color === color);

    if (existingProduct) {
        existingProduct.quantity += quantity; // Augmenter la quantité
    } else {
        cart.push({ name: productName, price: parseFloat(productPrice), quantity: quantity, size: size, color: color });
    }

    updateCart(); // Mettre à jour l'affichage du panier
    SaveCart(); // Sauvegarder le panier dans le local storage
}

// Mettre à jour l'affichage du panier (MODIFIÉE)
function updateCart() {
    console.log("updateCart est appelée");

    // Mettre à jour le compteur
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
    cartCountElement.textContent = totalItems;

    // Mettre à jour les détails du panier
    cartItemsElement.innerHTML = '';
    let totalPrice = 0;

    cart.forEach((item, index) => {
        totalPrice += item.price * item.quantity;

        // Créer un élément de liste pour chaque produit
        const li = document.createElement('li');
        li.innerHTML = `
            ${item.name} (Taille: ${item.size}, Couleur: ${item.color}) x${item.quantity} - ${(item.price * item.quantity).toFixed(2)}€
            <button class="remove-btn" data-index="${index}">Supprimer</button>
        `;
        cartItemsElement.appendChild(li);
    });

    // Mettre à jour le total
    cartTotalElement.textContent = totalPrice.toFixed(2);

    // Ajouter des écouteurs d'événements pour les boutons "Supprimer" **DÉPLACÉ ICI**
    const removeButtons = document.querySelectorAll('.remove-btn');
    removeButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const index = parseInt(e.target.dataset.index);
            removeFromCart(index);
        });
    });

    // Afficher ou masquer la section du panier
    cartSection.style.display = cart.length > 0 ? 'block' : 'none';
}

// Supprimer un produit du panier
function removeFromCart(index) {
    console.log("removeFromCart appelé avec l'index : " + index);

    if (cart[index].quantity > 1) {
        cart[index].quantity -= 1;
    } else {
        cart.splice(index, 1);
    }
    console.log("Contenu du tableau cart après la suppression :", cart);

    updateCart(); // Mettre à jour l'affichage du panier
    SaveCart(); // Sauvegarder le panier dans le local storage
}

// Fonction pour changer la quantité d'un produit dans le panier
function changeQuantity(index, change) {
    if (cart[index].quantity + change > 0) { // Vérifier que la quantité ne devient pas négative
        cart[index].quantity += change;
    } else {
        removeFromCart(index); // Supprimer si la quantité devient 0
    }
    updateCart();
    SaveCart();
}

// Gérer les clics sur les boutons "Ajouter au panier" (MODIFIÉE)
document.querySelectorAll('.product button').forEach(button => {
    button.addEventListener('click', (e) => {
        const productElement = e.target.closest('.product');

        // Vérification de l'existence de l'élément .product
        if (!productElement) {
            console.error("L'élément .product n'a pas été trouvé !");
            return;
        }

        const productName = productElement.dataset.name;
        const productPrice = productElement.dataset.price;
        const quantityInput = productElement.querySelector('.quantity input[type="number"]');
        const sizeSelect = productElement.querySelector('.product-options #size');
        const colorSelect = productElement.querySelector('.product-options #color');

        // Vérification de l'existence des éléments
        if (!quantityInput) {
            console.error("L'élément .quantity input[type='number'] n'a pas été trouvé dans .product !");
            return;
        }
        if (!sizeSelect) {
            console.error("L'élément .product-options #size n'a pas été trouvé dans .product !");
            return;
        }
        if (!colorSelect) {
            console.error("L'élément .product-options #color n'a pas été trouvé dans .product !");
            return;
        }

        let quantity = parseInt(quantityInput.value, 10);
        const size = sizeSelect.value;
        const color = colorSelect.value;

        // Validation de la quantité AVANT l'appel à addToCart
        if (isNaN(quantity) || quantity <= 0) {
            alert("Veuillez entrer une quantité valide.");
            return;
        }

        addToCart(productName, productPrice, quantity, size, color); // Appel à addToCart avec les nouvelles options
    });
});
  
// Gérer le clic sur le logo du panier
cartLink.addEventListener('click', (e) => {
    e.preventDefault(); // Empêcher le comportement par défaut du lien
    cartSection.classList.toggle('visible'); // Bascule l'affichage
    cartSection.style.display = cartSection.classList.contains('visible') ? 'block' : 'none';
});
// Gérer le clic sur le lien "shop"
shopLink.addEventListener('click', (e) => {
    e.preventDefault(); // Empêcher le comportement par défaut du lien
    TshopSection.classList.toggle('visible'); // Bascule l'affichage
    TshopSection.style.display = TshopSection.classList.contains('visible') ? 'block' : 'none';
});
// Charger le panier au chargement de la page
window.addEventListener('load', LoadCart);

// Ajouter un gestionnaire d'événement sur chaque miniature
const thumbnails = document.querySelectorAll('.thumbnail');
const mainImage = document.getElementById('main-image');
thumbnails.forEach(thumbnail => {
    thumbnail.addEventListener('click', () => {
        mainImage.src = thumbnail.src;
    });
});