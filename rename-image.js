const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Récupérer les arguments : ancien nom et nouveau nom
const oldName = process.argv[2];
const newName = process.argv[3];

// Vérification des arguments
if (!oldName || !newName) {
    console.error('Usage: node rename-image.js <ancien_nom> <nouveau_nom>');
    process.exit(1);
}

// Fonction pour rechercher les fichiers HTML dans le projet
function findHtmlFiles(dir) {
    const files = fs.readdirSync(dir);
    let htmlFiles = [];

    files.forEach(file => {
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);

        if (stat.isDirectory()) {
            htmlFiles = htmlFiles.concat(findHtmlFiles(filePath));
        } else if (path.extname(file) === '.html') {
            htmlFiles.push(filePath);
        }
    });

    return htmlFiles;
}

// Fonction pour remplacer le nom de l'image dans un fichier
function replaceImageName(filePath, oldName, newName) {
    try {
        let content = fs.readFileSync(filePath, 'utf8');
        const newContent = content.replace(new RegExp(escapeRegExp(oldName), 'g'), newName);

        if (content !== newContent) {
            fs.writeFileSync(filePath, newContent, 'utf8');
            return true;
        }
        return false;
    } catch (error) {
        console.error(`Error processing file ${filePath}: ${error.message}`);
        return false;
    }
}

// Fonction pour échapper les caractères spéciaux pour la RegExp
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

// Trouver tous les fichiers HTML dans le projet
const htmlFiles = findHtmlFiles('.'); // Commence à partir du répertoire courant

// Remplacer le nom de l'image dans chaque fichier
let modifiedFiles = [];
htmlFiles.forEach(file => {
    if (replaceImageName(file, oldName, newName)) {
        modifiedFiles.push(file);
    }
});

// Afficher la liste des fichiers modifiés
if (modifiedFiles.length > 0) {
    console.log('Modified files:');
    modifiedFiles.forEach(file => console.log(file));
} else {
    console.log('No files modified.');
}

// Ajouter les fichiers modifiés à Git
if (modifiedFiles.length > 0) {
    try {
        execSync(`git add ${modifiedFiles.join(' ')}`);
        console.log('Files added to Git.');
    } catch (error) {
        console.error('Error adding files to Git:', error.message);
    }
}