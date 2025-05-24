<?php
//Gérer les répertoires contenant des images et récupérer leur 
require_once 'jpgraph/src/jpgraph.php';
require_once 'jpgraph/src/jpgraph_bar.php';
require_once 'jpgraph/src/jpgraph_pie.php';

class ImageDirectory {
    private $directoryPath;

    public function __construct($directoryPath) {
        $this->directoryPath = $directoryPath;
    }

    public function getImages() {
        if (is_dir($this->directoryPath)) {
            return array_diff(scandir($this->directoryPath), array('.', '..'));
        }
        throw new Exception("Répertoire non valide.");
    }
}

// Gérer les hyperparamètres d'un modèle de machine learning et les valider.
class Hyperparameters {
    private $params; // tableau associatif contenant les hyperparamètres.

    public function __construct($params) {
        $this->params = $params;
    }

    public function validate() {
        $requiredFields = ['epochs', 'batch_size', 'patience', 'model_name'];
        foreach ($requiredFields as $field) {
            if (empty($this->params[$field])) {
                // Lance une exception si un champ est manquant.
                throw new Exception("Le paramètre $field est requis.");
            }
        }
    }

    public function getParams() {
        // Retourne le tableau des hyperparamètres.
        return $this->params;
    }
}

// Gérer les interactions avec une base de données (connexion, insertion, et récupération des configurations).
class Database {
    private $conn; // instance de la connexion mysqli à la base de données.

    public function __construct($host, $dbname, $username, $password) {
        $this->conn = new mysqli($host, $username, $password, $dbname);

        if ($this->conn->connect_error) {
            throw new Exception("Erreur de connexion à la base de données : " . $this->conn->connect_error);
        }
    }

    public function saveConfiguration($data) {
        $sql = "INSERT INTO classification (epochs, batch_size, patience, Monitor, optimizer, model_name, activation_function, validation_split, test_split, directory)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Erreur lors de la préparation de la requête : " . $this->conn->error);
        }

        $stmt->bind_param(
            "iissssssss",
            $data['epochs'],
            $data['batch_size'],
            $data['patience'],
            $data['Monitor'],
            $data['optimizer'],
            $data['model_name'],
            $data['activation_function'],
            $data['validation_split'],
            $data['test_split'],
            $data['directory']
        );

        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }

        throw new Exception("Erreur lors de l'enregistrement des données : " . $stmt->error);
    }

    // Récupère toutes les configurations stockées dans la table classification.
    // Retourne les données sous forme de tableau associatif.
    public function getConfigurations() {
        $sql = "SELECT * FROM parametres";
        $result = $this->conn->query($sql);

        if (!$result) {
            throw new Exception("Erreur lors de la récupération des configurations : " . $this->conn->error);
        }

        return $result->fetch_all(MYSQLI_ASSOC);
    }
}

    class ResultDisplay {
        public function display($message) {
            echo "<div class='result'>{$message}</div>";
        }
    }

    

class ImageAnalyzer {
    private $directory;

    public function __construct($directory) {
        $this->directory = $directory;
    }

    // Récupère le nombre d'images par classe
    public function getImagesByClass() {
        $classes = array_diff(scandir($this->directory), array('.', '..'));
        $imageCounts = [];
        foreach ($classes as $class) {
            $classPath = $this->directory . DIRECTORY_SEPARATOR . $class;
            if (is_dir($classPath)) {
                $images = array_diff(scandir($classPath), array('.', '..'));
                $imageCounts[$class] = count($images);
            }
        }
        return $imageCounts;
    }

    // Génère un barplot avec JpGraph et l'enregistre dans un fichier
    public function generateBarPlot($imageCounts, $filePath) {
        $classes = array_keys($imageCounts);
        $counts = array_values($imageCounts);

        // Crée un graphique en barres
        $graph = new Graph(800, 600);
        $graph->SetScale('textlin');

        // Ajouter des labels et des titres
        $graph->xaxis->SetTickLabels($classes);
        $graph->xaxis->SetTitle('Classes', 'center');
        $graph->yaxis->SetTitle('Nombre d\'images', 'center');
        $graph->title->Set('Répartition des images par classe');

        // Ajouter des barres
        $barPlot = new BarPlot($counts);
        $barPlot->SetFillColor('blue');
        $barPlot->value->Show();

        // Ajouter le plot au graphique
        $graph->Add($barPlot);

        // Enregistrer l'image dans un fichier
        $graph->Stroke($filePath);
    }

    // Génère un histogramme des tailles d'image avec JpGraph et l'enregistre dans un fichier
    public function generateSizeHistogram($filePath) {
        $classes = array_diff(scandir($this->directory), array('.', '..'));
        $imageSizes = [];
        foreach ($classes as $class) {
            $classPath = $this->directory . DIRECTORY_SEPARATOR . $class;
            if (is_dir($classPath)) {
                $images = array_diff(scandir($classPath), array('.', '..'));
                foreach ($images as $image) {
                    $imagePath = $classPath . DIRECTORY_SEPARATOR . $image;
                    $size = filesize($imagePath);
                    $imageSizes[$class][] = $size;
                }
            }
        }

        // Calculer les tailles d'image moyennes par classe
        $averageSizes = [];
        foreach ($imageSizes as $class => $sizes) {
            $averageSizes[$class] = array_sum($sizes) / count($sizes);
        }

        // Préparer les données pour le graphique
        $data = array_values($averageSizes);
        $labels = array_keys($averageSizes);

        // Crée un graphique en barres pour les tailles d'image
        $graph = new Graph(800, 600);
        $graph->SetScale('textlin');
        $graph->SetShadow();
        $graph->title->Set("Taille moyenne des images par classe");
        $graph->title->SetFont(FF_FONT1, FS_BOLD);
        $graph->xaxis->SetTickLabels($labels);
        $graph->xaxis->SetTitle('Classes', 'center');
        $graph->yaxis->SetTitle('Taille moyenne des images (octets)', 'center');

        // Ajouter un graphique en barres
        $barPlot = new BarPlot($data);
        $barPlot->SetFillColor('green');
        $barPlot->value->Show();

        // Ajouter le graphique au graph
        $graph->Add($barPlot);

        // Enregistrer l'image dans un fichier
        $graph->Stroke($filePath);
    }

    // Génère un graphique à secteurs avec JpGraph et l'enregistre dans un fichier
    public function generatePieChart($imageCounts, $filePath) {
        $classes = array_keys($imageCounts);
        $counts = array_values($imageCounts);

        // Crée un graphique à secteurs
        $graph = new PieGraph(800, 600);
        $graph->title->Set("Répartition des images par classe");

        // Ajouter un plot
        $piePlot = new PiePlot($counts);
        $piePlot->SetLegends($classes);
        $piePlot->SetCenter(0.5, 0.5);

        // Ajouter le plot au graphique
        $graph->Add($piePlot);

        // Enregistrer l'image dans un fichier
        $graph->Stroke($filePath);
    }
}
class ImageManager {
        private $directoryPath;
        private $relativePath;
    
        public function __construct($absolutePath, $relativePath) {
            $this->directoryPath = $absolutePath;
            $this->relativePath = $relativePath;
        }

        // Récupérer les classes (répertoires uniquement)
        public function getClasses() {
            $imageDir = new ImageDirectory($this->directoryPath);
            $allItems = $imageDir->getImages();
            
            return array_filter($allItems, function($item) {
                return is_dir($this->directoryPath . DIRECTORY_SEPARATOR . $item); // Filtrer uniquement les répertoires
            });
   
        }
    
        // Préparer les images par classe
        public function prepareImages() {
            $imagesByClass = [];
            $classes = $this->getClasses();
    
            foreach ($classes as $class) {
                $classPath = $this->directoryPath . DIRECTORY_SEPARATOR . $class;
                $images = array_diff(scandir($classPath), ['.', '..']);
                if (!empty($images)) {
                    // Construction du chemin de l'image correctement, en utilisant la structure relative
                    $imagesByClass[] = [
                        'class' => $class,
                        'image' => $this->relativePath . DIRECTORY_SEPARATOR . $class . DIRECTORY_SEPARATOR . reset($images) 
                    ];
                }
                if (count($imagesByClass) >= 10) break; // Limiter à 10 classes
            }
    
            return $imagesByClass;
        }
    
        // Afficher les images
        public function displayImages() {
            $imagesByClass = $this->prepareImages();
            
            $resultDisplay = new ResultDisplay();
            $resultDisplay->display("<div class='container'>
            <h1>Images par classe</h1>");
            
            echo "<div class='class-section-container'>";
            
            foreach ($imagesByClass as $index => $image) {
                $resultDisplay->display("
                    <div class='class-section'>
                        <h2>Classe : {$image['class']}</h2>
                        <div class='image-container'>
                            <img src='{$image['image']}' alt='Image'>
                        </div>
                    </div>
                ");
                
                // Vérifier si l'index est impair pour passer à une nouvelle ligne
                if (($index + 1) % 2 == 0) {
                    echo "</div><div class='class-section-container'>";
                }
            }
        
            echo "</div>";
            $resultDisplay->display("</div>");
        }
    }
    
    
    ?>
    <style>
/* Conteneur principal */
.container {
    max-width: 1200px;
    margin: 20px auto;
    padding: 10px;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* En-tête général */
.container h1 {
    text-align: center;
    margin-bottom: 20px;
    color: #6c4f2d;
    
}

/* Conteneur principal pour les classes avec alignement */
.class-section-container {
    display: grid;
    grid-template-columns: repeat(2, 1fr); /* 2 classes par ligne */
    gap: 20px;
    margin-bottom: 30px;
}

/* Conteneur pour chaque classe avec son image */
.class-section {
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
    text-align: center;
}

/* Titre de chaque classe */
.class-section h2 {
    color: #df9d0e;
    font-size: 1.2em;
    margin-bottom: 10px;
    font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
}

/* Grille contenant les images */
.image-container {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

/* Style pour les images avec taille agrandie */
.image-container img {
    width: 200px; /* Taille ajustée */
    height: 200px; /* Taille ajustée */
    object-fit: cover;
    border-radius: 8px;
    transition: transform 0.2s ease-in-out;
}

/* Effet de zoom sur les images au survol */
.image-container img:hover {
    transform: scale(1.1);
}
</style>
