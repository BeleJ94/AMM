<?php
/**
 * AJAX - Récupérer les notifications de l'utilisateur connecté
 * AMM-ERP
 */
session_start();
date_default_timezone_set('Africa/Lubumbashi');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier la session
if (empty($_SESSION['user_id'])) {
    jsonReponse(['erreur' => 'Non authentifié'], 401);
}

$pdo = getDB();
// Les rappels arrivent aussi lorsque l'utilisateur reste sur un autre module.
require_once __DIR__ . '/../models/Treasury.php';
try {
    (new Treasury())->envoyerRappelsDemandes();
} catch (Throwable $e) {
    error_log('Rappels demandes de fonds : ' . $e->getMessage());
}
$stmt = $pdo->prepare(
    "SELECT id, type_notif, message, lien, est_lu, DATE_FORMAT(created_at, '%d/%m/%Y %H:%i') AS created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 20"
);
$stmt->execute([$_SESSION['user_id']]);
$notifs = $stmt->fetchAll();

jsonReponse($notifs);
