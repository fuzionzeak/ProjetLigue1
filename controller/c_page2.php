<?php

require_once __DIR__ . '/../model/GestionBDD.php';
require_once __DIR__ . '/../model/GestionClub.php';

use Model\GestionBDD;
use Model\GestionClub;

// Connexion à la base de données
$gestionBDD = new GestionBDD('ligue1');
$cnx = $gestionBDD->connect();

// Création de l'objet GestionClub pour manipuler les clubs
$gestionClub = new GestionClub($cnx);

// Récupération des clubs pour les afficher en boutons
$clubs = $gestionClub->getAllClubs();

// ID_CLUB par défaut (doit correspondre à un club existant)
$idClubParDefaut = 1; // Changez ce numéro par un ID valide existant dans votre base

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération des données du formulaire
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $sexe = $_POST['sexe'] ?? '';
    $password = password_hash($_POST['password'] ?? '', PASSWORD_BCRYPT); // Hash du mot de passe pour la sécurité
    $clubsChoisis = $_POST['clubs'] ?? [];
    $image = $_FILES['image'] ?? null;

    // Utiliser le premier club sélectionné ou un club par défaut s'il n'y a rien
    $idClub = !empty($clubsChoisis) ? (int)$clubsChoisis[0] : $idClubParDefaut;

    // Vérification que l'ID_CLUB existe dans la table CLUB
    $stmtCheckClub = $cnx->prepare("SELECT COUNT(*) FROM CLUB WHERE ID_CLUB = :id_club");
    $stmtCheckClub->bindParam(':id_club', $idClub, PDO::PARAM_INT);
    $stmtCheckClub->execute();
    $clubExists = $stmtCheckClub->fetchColumn() > 0;

    if (!$clubExists) {
        // Si l'ID_CLUB n'est pas valide, utilisez l'ID par défaut
        $idClub = $idClubParDefaut;
    }

    // Traitement de l'image de profil (sauvegarde dans un dossier, etc.)
    $imagePath = '';
    if ($image && $image['error'] === 0) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // Crée le dossier s'il n'existe pas
        }
        $imagePath = $uploadDir . basename($image['name']);
        move_uploaded_file($image['tmp_name'], $imagePath);
    }

    // Insertion de l'utilisateur dans la base de données
    $stmt = $cnx->prepare("INSERT INTO UTILISATEUR (ID_CLUB, NOM_UTI, PRENOM_UTI, SEXE_UTI, PASSWORD_UTI, IMAGE_UTI, DATE_INSCRIPTION) 
                           VALUES (:id_club, :nom, :prenom, :sexe, :password, :image, NOW())");

    $stmt->bindParam(':id_club', $idClub, PDO::PARAM_INT);
    $stmt->bindParam(':nom', $nom);
    $stmt->bindParam(':prenom', $prenom);
    $stmt->bindParam(':sexe', $sexe);
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':image', $imagePath);
    $stmt->execute();

    // Récupération de l'ID de l'utilisateur inséré
    $userId = $cnx->lastInsertId();

    // Insertion des abonnements aux clubs sélectionnés
    if (!empty($clubsChoisis)) {
        $stmtAbonnement = $cnx->prepare("INSERT INTO S_ABONNER (ID_UTI, ID_CLUB) VALUES (:id_uti, :id_club)");
        foreach ($clubsChoisis as $clubId) {
            // Vérifier l'existence de chaque club avant d'insérer
            $stmtCheckClub->bindParam(':id_club', $clubId, PDO::PARAM_INT);
            $stmtCheckClub->execute();
            $clubExists = $stmtCheckClub->fetchColumn() > 0;

            if ($clubExists) {
                $stmtAbonnement->bindParam(':id_uti', $userId);
                $stmtAbonnement->bindParam(':id_club', $clubId, PDO::PARAM_INT);
                $stmtAbonnement->execute();
            }
        }
    }

    echo "Inscription réussie !"; // Message de succès
}
require_once __DIR__ . '/../views/page2_view.php';  // Appelle la vue

?>