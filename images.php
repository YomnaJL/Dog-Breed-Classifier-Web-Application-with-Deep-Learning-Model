<?php
require_once 'class.php';

$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'classification',
    'username' => 'root',
    'password' => ''
];

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Vérifier si le répertoire est bien sélectionné
        if (empty($_POST['directory'])) {
            throw new Exception("Le chemin du répertoire n'est pas défini ou est vide.");
        }

        // Récupérer le chemin relatif du formulaire
        $relativePath = trim($_POST['directory']);

        // Compléter le chemin pour obtenir le chemin absolu
        $basePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'dogsVScats-classifier';
        $absolutePath = realpath($basePath . DIRECTORY_SEPARATOR . $relativePath);

        // Vérifier si le répertoire existe
        if ($absolutePath === false || !is_dir($absolutePath)) {
            throw new Exception("Le répertoire spécifié n'existe pas : $relativePath");
        }

        // Connexion à la base de données
        $db = new Database($dbConfig['host'], $dbConfig['dbname'], $dbConfig['username'], $dbConfig['password']);

        // Validation des hyperparamètres
        $hyperparameters = new Hyperparameters($_POST);
        $hyperparameters->validate();

        // Sauvegarder la configuration dans la base de données avec le chemin absolu
        $data = array_merge($hyperparameters->getParams(), ['directory' => $absolutePath]);
        $lastId = $db->saveConfiguration($data);

        // Affichage du résultat
        $resultDisplay = new ResultDisplay();

        // Ajouter un bouton pour afficher les graphiques
 
        echo '<form action="affichage_graphique.php" method="get">';
        echo '<input type="hidden" name="id" value="' . $lastId . '">';
        echo '<div class="button-container">';
        echo '<button type="submit">Afficher les graphiques</button>';
        echo '</form>';

        // Utiliser la classe ImageManager pour récupérer et afficher les images
        $imageManager = new ImageManager($absolutePath, $relativePath);
        $imageManager->displayImages();
    }
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>
<style>
/* Bouton positionné en haut à droite avec style */
.button-container {

    top: 20px; /* Distance depuis le haut */
    right: 20px; /* Distance depuis la droite */
}

/* Style du bouton */
.button-container button {
    background-color: #6c4f2d;
    border: none;
    color: white;
    padding: 10px 20px;
    font-size: 16px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

/* Effet au survol */
.button-container button:hover {
    background-color:#6c4f2d;
}
</style>
