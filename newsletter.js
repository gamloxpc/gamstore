Content-Type: application/json;
Accept: application/json
document.addEventListener('DOMContentLoaded', function() { // Attendre que le DOM soit chargé

    const form = document.getElementById('https://connect.mailerlite.com/api'); // Remplacez par l'ID de votre formulaire MailerLite
    if (form) { // Vérifier si le formulaire existe
        form.addEventListener('submit', function(event) {
            let isValid = true;

            // Validation de l'email
            const emailInput = form.querySelector('input[type="email"]');
            if (emailInput) {
                const emailValue = emailInput.value.trim();
                if (emailValue === '' || !isValidEmail(emailValue)) {
                    alert('Veuillez entrer une adresse email valide.');
                    event.preventDefault(); // Empêcher la soumission du formulaire
                    isValid = false;
                }
            }

            // Ajoutez ici d'autres validations pour les champs obligatoires

            if (isValid === false) {
              return; // Empêcher la poursuite si la validation échoue
            }

            // Si tout est valide, le formulaire se soumettra normalement à MailerLite
        });
    }
});

function isValidEmail(email) {
    // Une expression régulière simple pour valider le format d'un email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}