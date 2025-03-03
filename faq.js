document.addEventListener('DOMContentLoaded', function() {
    const faqQuestions = document.querySelectorAll('.faq-question');

    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            // Sélectionner la réponse correspondante
            const answer = this.nextElementSibling;

            // Basculer la classe "active" sur la question
            this.classList.toggle('active');

            // Si la réponse est déjà visible, on la masque
            if (answer.classList.contains('show')) {
                answer.classList.remove('show');
                answer.style.maxHeight = null; // Réinitialiser la hauteur maximale pour l'animation de fermeture
            } else {
                answer.classList.add('show');
                answer.style.maxHeight = answer.scrollHeight + "px"; // Définir la hauteur maximale pour l'animation d'ouverture
            }
        });
    });
});