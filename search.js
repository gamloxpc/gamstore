// Données de test (REMPLACEZ CECI par vos propres données)
const data = [
    { name: "casquette", url: "casquette.html" },
    { name: "Banana", url: "https://en.wikipedia.org/wiki/Banana" },
    { name: "Cherry", url: "https://en.wikipedia.org/wiki/Cherry" },
    { name: "Date", url: "https://en.wikipedia.org/wiki/Date_(fruit)" },
    { name: "Fig", url: "https://en.wikipedia.org/wiki/Fig" },
    { name: "Grape", url: "https://en.wikipedia.org/wiki/Grape" },
    { name: "Lemon", url: "https://en.wikipedia.org/wiki/Lemon" },
    { name: "Mango", url: "https://en.wikipedia.org/wiki/Mango" }
];

const searchInput = document.getElementById("searchInput");
const searchResults = document.getElementById("searchResults");

searchInput.addEventListener("input", function() {
    const searchTerm = searchInput.value.toLowerCase();

    if (searchTerm === "") {
        searchResults.style.display = "none";
        searchResults.innerHTML = "";
        return;
    }

    const results = data.filter(item => item.name.toLowerCase().includes(searchTerm)); // Modifié pour utiliser item.name
    displayResults(results);
});

function displayResults(results) {
    searchResults.innerHTML = "";

    if (results.length === 0) {
        searchResults.innerHTML = "<p>No results found.</p>";
    } else {
        const list = document.createElement("ul");
        results.forEach(result => {
            const item = document.createElement("li");
            item.textContent = result.name; // Modifié pour afficher item.name

            // Ajout d'un gestionnaire d'événement de clic pour la redirection
            item.addEventListener("click", function() {
                window.location.href = result.url; // Redirection vers l'URL
            });

            list.appendChild(item);
        });
        searchResults.appendChild(list);
    }

    searchResults.style.display = "block";
}