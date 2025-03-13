document.addEventListener('DOMContentLoaded', function() {
    const raisonSelect = document.getElementById('raison');
    const commandeDetails = document.getElementById('commandeDetails');
    const produitDetails = document.getElementById('produitDetails');
    const retour = document.getElementById('retourDetails');

    raisonSelect.addEventListener('change', function() {
        const selectedValue = this.value;

        // Cacher toutes les sections
        commandeDetails.style.display = 'none';
        produitDetails.style.display = 'none';
        retour.style.display = 'none';

        // Afficher les sections en fonction de la sélection
        if (selectedValue === 'infoCommande') {
            commandeDetails.style.display = 'block';
        } else if (selectedValue === 'inforProduit') {
            produitDetails.style.display = 'block';
        }
        else if (selectedValue === 'retour') {
            retour.style.display = 'block';
        }
        // Vous pouvez ajouter d'autres conditions "else if" pour les autres options
    });
});