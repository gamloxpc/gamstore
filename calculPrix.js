document.addEventListener('DOMContentLoaded', function () {
    const radioButtons = document.querySelectorAll('input[name="delivery_method"]');
    radioButtons.forEach(radio => {
        radio.addEventListener('change', calculerFraisLivraison);
    });

    function calculerFraisLivraison() {

       let  nombreArticles = parseInt(document.getElementById('cart-count').textContent) || 0;
        let  poidsProduits = parseFloat(document.getElementById('cart-poid').textContent) || 0; // En grammes
        let  prixTotal = parseFloat(document.getElementById('cart-total').textContent) || 0;
        let  fraisExpeditionElement = document.getElementById('frais-expedition');
        let  totalArticlesElement = document.getElementById('total-articles');
        let  totalPaiementElement = document.getElementById('total-paiement');
        let  pointRelayElement = document.getElementById('relayNumber');

        console.log("Nombre d'articles :", nombreArticles);
        console.log("Poids total des articles (g) :", poidsProduits);
        console.log("Prix total du panier :", prixTotal);


        // Déterminer le poids de l'emballage (en grammes)
        let poidsEmballage = 0;
        if (nombreArticles <= 2) {
            poidsEmballage = 20; // 20g
        } else if (nombreArticles <= 5) {
            poidsEmballage = 100; // 100g
        } else {
            poidsEmballage = 150; // 150g
        }

        console.log("Poids de l'emballage (g) :", poidsEmballage);

        // Calculer le poids total en grammes
        const poidsTotalLivraison = poidsProduits + poidsEmballage;
        console.log("Poids total (articles + emballage) (g) :", poidsTotalLivraison);

        // Déterminer le mode de livraison sélectionné
        let modeLivraison = document.querySelector('input[name="delivery_method"]:checked');
        if (!modeLivraison) {
            console.log("Aucun mode de livraison sélectionné.");
            //Remettre les valeurs a zero car rien n'est selectionné
            totalArticlesElement.textContent = prixTotal.toFixed(2) + "€";
            fraisExpeditionElement.textContent = "0.00€";
            totalPaiementElement.textContent = prixTotal.toFixed(2) + "€";
            return;
        }
        console.log("Mode de livraison sélectionné :", modeLivraison.value);

        let fraisLivraison = 0;

        // Tarifs Mondial Relay (Point Relais)
        const tarifsMondialRelayPoint = [
            { poidsMax: 250, prix: 4.19 },
            { poidsMax: 500, prix: 4.30 },
            { poidsMax: 1000, prix: 5.39 },
            { poidsMax: 2000, prix: 6.59 },
            { poidsMax: 3000, prix: 7.40 },
            { poidsMax: 4000, prix: 8.90 },
            { poidsMax: 5000, prix: 12.40 },
            { poidsMax: 7000, prix: 14.40 },
            { poidsMax: 10000, prix: 14.40 }
        ];

        // Tarifs Mondial Relay (Domicile)
        const tarifsMondialRelayDomicile = [
            { poidsMax: 250, prix: 7.20 },
            { poidsMax: 500, prix: 7.79 },
            { poidsMax: 1000, prix: 8.75 },
            { poidsMax: 2000, prix: 10.20 },
            { poidsMax: 3000, prix: 11.99 },
            { poidsMax: 5000, prix: 14.39 },
            { poidsMax: 7000, prix: 15.59 },
            { poidsMax: 10000, prix: 19.19 },
            { poidsMax: 15000, prix: 23.99 },
            { poidsMax: 20000, prix: 39.59 },
            { poidsMax: 25000, prix: 39.59 },
            { poidsMax: 30000, prix: 39.59 }
        ];

        // Tarifs Colissimo
        const tarifsColissimo = [
            { poidsMax: 250, prix: 5.25 },
            { poidsMax: 500, prix: 7.35 },
            { poidsMax: 750, prix: 8.65 },
            { poidsMax: 1000, prix: 9.40 },
            { poidsMax: 2000, prix: 10.70 },
            { poidsMax: 5000, prix: 16.60 },
            { poidsMax: 10000, prix: 24.20 },
            { poidsMax: 15000, prix: 30.55 },
            { poidsMax: 30000, prix: 37.85 }
        ];

        // Fonction pour obtenir le tarif en fonction du poids
        function getTarif(tarifs, poids) {
            for (let tarif of tarifs) {
                if (poids <= tarif.poidsMax) {
                    return tarif.prix;
                }
            }
            return tarifs[tarifs.length - 1].prix; // Retourne le dernier prix si dépassement
        }

        // Sélection du tarif en fonction du mode de livraison
        switch (modeLivraison.value) {
            case 'mondial_relay_point':
                fraisLivraison = getTarif(tarifsMondialRelayPoint, poidsTotalLivraison);
                break;
            case 'mondial_relay_domicile':
                fraisLivraison = getTarif(tarifsMondialRelayDomicile, poidsTotalLivraison);
                break;
            case 'colissimo_domicile':
                fraisLivraison = getTarif(tarifsColissimo, poidsTotalLivraison);
                break;
            default:
                console.log("Mode de livraison non reconnu.");
                fraisLivraison = 0;
                break;
        }

        console.log("Frais de livraison calculés :", fraisLivraison);

        // Livraison gratuite au-delà de 60€
        if (prixTotal >= 60) {
            console.log("Livraison gratuite appliquée (panier >= 60€)");
            fraisLivraison = 0;
        }

        // Calcul du total à payer
        const totalAPayer = prixTotal + fraisLivraison;

        // Mettre à jour l'affichage
        totalArticlesElement.textContent = prixTotal.toFixed(2) + "€";
        fraisExpeditionElement.textContent = fraisLivraison.toFixed(2) + "€";
        totalPaiementElement.textContent = totalAPayer.toFixed(2) + "€";

        // Affichage en console
        console.log("Prix total des articles :", prixTotal + "€");
        console.log("Frais de livraison :", fraisLivraison + "€");
        console.log("Prix total à payer :", totalAPayer + "€");
        console.log("Numéro de pois relay :", poidsTotalLivraison);
    }

    // Exécuter au chargement de la page
    calculerFraisLivraison();

    const cartCountElement = document.getElementById('cart-count');
    const cartPoidElement = document.getElementById('cart-poid');
    const cartTotalElement = document.getElementById('cart-total');

    if (cartCountElement && cartPoidElement && cartTotalElement ) {
        calculerFraisLivraison();
    }
});