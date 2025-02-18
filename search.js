function searchProducts() {
    let input = document.getElementById("search-bar").value.toLowerCase();
    let searchResults = document.getElementById("search-results");

    searchResults.innerHTML = "";

    // Structure de données améliorée avec des URLs
    let products = [
        { name: "produit", url: "produit.html" },
        { name: "casquette", url: "casquette.html" },
        { name: "T-shirt", url: "t-shirt.html" }
    ];

    let filteredProducts = products.filter(product => product.name.toLowerCase().includes(input));

    if (input.trim() !== "" && filteredProducts.length > 0) {
        searchResults.style.display = "block";

        filteredProducts.forEach(product => {
            // Crée un élément div pour chaque résultat
            let div = document.createElement("div");
            div.classList.add("search-item");
            
            // Crée un lien <a>
            let a = document.createElement("a");
            a.href = product.url;
            a.textContent = product.name; // Met le nom du produit comme texte du lien
            
            // Ajoute le lien <a> dans le div
            div.appendChild(a);

            // Ajoute le div dans les résultats
            searchResults.appendChild(div);
        });
    } else {
        searchResults.style.display = "none";
    }
}

document.getElementById("search-bar").addEventListener("input", searchProducts);

document.addEventListener("click", function (e) {
    let searchContainer = document.querySelector(".search-container");
    let searchResults = document.getElementById("search-results");

    if (!searchContainer.contains(e.target)) {
        searchResults.style.display = "none";
    }
});