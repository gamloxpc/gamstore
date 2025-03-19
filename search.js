const produits = [
    { name: "sweet", url: "sweet.html", img: "casquette1.png" },
    { name: "casquette", url: "casquette.html", img: "casquette1.png" },
    { name: "polo", url: "polo.html", img: "casquette1.png" },
    { name: "t-shirt", url: "T-shirt.html", img: "casquette1.png" },
    { name: "veste", url: "veste.html", img: "casquette1.png" },
    { name: "chaussures", url: "chaussures.html", img: "casquette1.png" },
    { name: "jean", url: "jean.html", img: "casquette1.png" },
    { name: "pull", url: "pull.html", img: "casquette1.png" },
    { name: "bonnet", url: "bonnet.html", img: "casquette1.png" },
    { name: "gants", url: "gants.html", img: "casquette1.png" }
  ];

  const searchInput = document.getElementById('search-bar');
  const suggestionsContainer = document.getElementById('suggestions');

  searchInput.addEventListener('input', () => {
      const query = searchInput.value.toLowerCase();
      suggestionsContainer.innerHTML = '';

      if (query) {
          const filteredProducts = produits.filter(produit => produit.name.toLowerCase().includes(query));

          filteredProducts.forEach(produit => {
              const suggestionItem = document.createElement('div');
              suggestionItem.innerHTML = `<span>${produit.name}</span><img src="${produit.img}" alt="${produit.name}">`;
              suggestionItem.addEventListener('click', () => {
                  window.location.href = produit.url;
              });
              suggestionsContainer.appendChild(suggestionItem);
          });
          suggestionsContainer.style.display = 'block';
      } else {
          suggestionsContainer.style.display = 'none';
      }
  });

  document.addEventListener('click', (e) => {
      if (!suggestionsContainer.contains(e.target) && e.target !== searchInput) {
          suggestionsContainer.innerHTML = '';
          suggestionsContainer.style.display = 'none';
      }
  });