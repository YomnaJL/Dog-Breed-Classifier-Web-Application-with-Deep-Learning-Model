<?php
header('Content-Type: application/json');

// Paramètres de connexion à la base de données
$host = 'localhost';
$dbname = 'classification';
$username = 'root';
$password = '';

// Connexion à la base de données
$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Connexion à la base de données échouée.']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login = mysqli_real_escape_string($conn, $_POST['login']);
    $motdepasse = mysqli_real_escape_string($conn, $_POST['motdepasse']);

    if (empty($login) || empty($motdepasse)) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs sont obligatoires.']);
        exit;
    }

    $sql = "SELECT * FROM utilisateurs WHERE login = '$login'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);

        if (password_verify($motdepasse, $row['motdepasse'])) {
            echo json_encode(['success' => true, 'message' => 'Connexion réussie !']);
        } else {
            echo json_encode(['success' => false, 'errors' => ['motdepasse' => 'Mot de passe incorrect.']]);
        }
    } else {
        echo json_encode(['success' => false, 'errors' => ['login' => 'Login incorrect ou utilisateur inexistant.']]);
    }
}

mysqli_close($conn);
?>
