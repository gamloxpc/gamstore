document.addEventListener('DOMContentLoaded', function() {
    const closeBanner = document.querySelector('.top-banner .close-banner');
    const topBanner = document.querySelector('.top-banner');

    if (closeBanner && topBanner) {
        // Vérifier si le bandeau a déjà été masqué
        if (localStorage.getItem('topBannerMasque') === 'true') {
            topBanner.style.display = 'none';
        }

        closeBanner.addEventListener('click', function(event) {
            event.preventDefault(); // Empêcher le lien de naviguer
            topBanner.style.display = 'none'; // Cacher le bandeau

            // Enregistrer la préférence dans le localStorage
            localStorage.setItem('topBannerMasque', 'true');
        });
    }
});