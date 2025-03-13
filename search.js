document.addEventListener("DOMContentLoaded", function () {
    const searchBox = document.getElementById("search-bar");
    const searchResults = document.getElementById("search-results");

    // Liste des produits (à compléter dynamiquement si besoin)
    const products = [
        { name: "T-shirt", link: "T-shirt.html"  },
        { name: "Sweet", link: "sweet.html" },
        { name: "Casquette", link: "casquette.html" },
        { name: "Polo", link: "polo.html"  },
        { name: "Bonnet", link: "bonnet.html" },
        { name: "Jogging", link: "jogging.html" },
        { name: "Tasse", link: "tasse.html" },
        { name: "Veste", link: "veste.html" }
    ];

    searchBox.addEventListener("input", function () {
        const query = searchBox.value.toLowerCase().trim();
        searchResults.innerHTML = "";

        if (query === "") {
            searchResults.style.display = "none";
            return;
        }

        const filteredProducts = products.filter(product => product.name.toLowerCase().includes(query));

        if (filteredProducts.length > 0) {
            searchResults.style.display = "block";
            filteredProducts.forEach(product => {
                const item = document.createElement("div");
                item.classList.add("search-item");
                item.textContent = product.name;
                item.addEventListener("click", () => {
                    window.location.href = product.link;
                });
                searchResults.appendChild(item);
            });
        } else {
            searchResults.style.display = "none";
        }
    });

    // Cacher les résultats quand on clique ailleurs
    document.addEventListener("click", function (e) {
        if (!searchBox.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = "none";
        }
    });
});
