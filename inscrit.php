<?php
// Paramètres de connexion à la base de données
$host = 'localhost'; // Adresse du serveur
$dbname = 'classification'; // Nom de la base de données
$username = 'root'; // Nom d'utilisateur
$password = ''; // Mot de passe

// Connexion à la base de données
$conn = mysqli_connect($host, $username, $password, $dbname);

// Vérification de la connexion
if (!$conn) {
    die("Connexion échouée : " . mysqli_connect_error());
}

// Vérifier si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer les données du formulaire
    $login = mysqli_real_escape_string($conn, $_POST['Login']);
    $prenom = mysqli_real_escape_string($conn, $_POST['prenom']);
    $nom = mysqli_real_escape_string($conn, $_POST['nom']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $motdepasse = mysqli_real_escape_string($conn, $_POST['motdepasse']);

    // Hachage du mot de passe pour plus de sécurité
    $motdepasse_hash = password_hash($motdepasse, PASSWORD_DEFAULT);

       // Vérifier si l'email existe déjà
       $checkEmailQuery = "SELECT * FROM utilisateurs WHERE email = '$email'";
       $result = mysqli_query($conn, $checkEmailQuery);
       if (mysqli_num_rows($result) > 0) {
        echo json_encode(['success' => false, 'errors' => ['email' => "Cet email est déjà utilisé."]]);
    } else {
        $sql = "INSERT INTO utilisateurs (login, prenom, nom, email, motdepasse) VALUES ('$login', '$prenom', '$nom', '$email', '$motdepasse_hash')";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(['success' => true, 'message' => "Enregistrement réussi !"]);
        } else {
            echo json_encode(['success' => false, 'message' => "Erreur lors de l'enregistrement."]);
        }
    }
}


// Fermer la connexion
mysqli_close($conn);
?>
