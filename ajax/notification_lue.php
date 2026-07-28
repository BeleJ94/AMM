<?php
/**
 * AJAX - Marquer une ou toutes les notifications comme lues
 * AMM-ERP
 */
session_start();
date_default_timezone_set('Africa/Lubumbashi');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Notification.php';

if (empty($_SESSION['user_id'])) {
    jsonReponse(['success' => false, 'message' => 'Non authentifié'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonReponse(['success' => false], 405);
}

$notifModel = new Notification();
$userId     = (int)$_SESSION['user_id'];

// Marquer tout comme lu
if (!empty($_POST['tout'])) {
    $ok = $notifModel->marquerToutesLues($userId);
    jsonReponse(['success' => $ok]);
}

// Marquer une notification spécifique
$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $ok = $notifModel->marquerLue($id, $userId);
    jsonReponse(['success' => $ok]);
}

jsonReponse(['success' => false, 'message' => 'Paramètre manquant'], 400);
