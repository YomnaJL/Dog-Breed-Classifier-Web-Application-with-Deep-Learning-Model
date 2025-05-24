<?php
require_once 'class.php';
require_once 'jpgraph/src/jpgraph.php';
require_once 'jpgraph/src/jpgraph_bar.php';
require_once 'jpgraph/src/jpgraph_pie.php';

function getDirectoryById($db, $id) {
    $sql = "SELECT directory FROM classification WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['directory'];
    } else {
        throw new Exception("Aucun enregistrement trouvé pour l'ID : $id");
    }
}

try {
    // Connexion à la base de données
    $dbConfig = [
        'host' => 'localhost',
        'dbname' => 'classification',
        'username' => 'root',
        'password' => ''
    ];
    $db = new mysqli($dbConfig['host'], $dbConfig['username'], $dbConfig['password'], $dbConfig['dbname']);
    if ($db->connect_error) {
        throw new Exception("Erreur de connexion à la base de données : " . $db->connect_error);
    }

    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $directory = getDirectoryById($db, $id);
        $absolutePath = realpath($directory);

        if ($absolutePath === false || !is_dir($absolutePath)) {
            throw new Exception("Le répertoire spécifié n'existe pas : $directory");
        }

        $analyzer = new ImageAnalyzer($absolutePath);

        // Génération des graphiques
        // Barplot des images par classe
        $imageCounts = $analyzer->getImagesByClass();
        $barPlotImagePath = "temp/temp_barplot_$id.png";
        $analyzer->generateBarPlot($imageCounts, $barPlotImagePath);

        // Histogramme des tailles d'images
        $sizeHistogramPath = "temp/temp_histogram_$id.png";
        $analyzer->generateSizeHistogram($sizeHistogramPath);

        // Graphique à secteurs des images par classe
        
        $pieChartPath = "temp/temp_piechart_$id.png";
        $analyzer->generatePieChart($imageCounts, $pieChartPath);
    } else {
        throw new Exception("ID non fourni.");
    }
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Affichage des Graphiques</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            overflow-y: auto;
            height: 100vh;
        }
        .chart {
            margin-bottom: 40px;
            text-align: center;
        }
        .chart img {
            max-width: 100%;
            height: auto;
        }
        .chart h2 {
            margin-bottom: 10px;
            font-size: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 style="text-align: center;">Affichage des Graphiques</h1>

        <?php if (isset($_GET['id'])): ?>
            <div class="chart">
                <h2>Barplot des Images par Classe</h2>
                <img src="<?php echo $barPlotImagePath; ?>" alt="Barplot des Images par Classe">
            </div>

            <div class="chart">
                <h2>Histogramme des Tailles d'Images</h2>
                <img src="<?php echo $sizeHistogramPath; ?>" alt="Histogramme des Tailles d'Images">
            </div>

            <div class="chart">
                <h2>Graphique à Secteurs des Images par Classe</h2>
                <img src="<?php echo $pieChartPath; ?>" alt="Graphique à Secteurs des Images par Classe">
            </div>

            <h3 style="text-align: center;">Dernier ID Enregistré : <?php echo (int)$_GET['id']; ?></h3>
        <?php endif; ?>
    </div>
</body>
</html>
