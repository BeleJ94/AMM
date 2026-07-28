<?php
/**
 * AJAX - Accuser réception d'un courrier
 * AMM-ERP
 */
session_start();
date_default_timezone_set('Africa/Lubumbashi');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Courrier.php';

if (empty($_SESSION['user_id'])) {
    jsonReponse(['success' => false, 'message' => 'Non authentifié'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonReponse(['success' => false], 405);
}

$courrierId = (int)($_POST['courrier_id'] ?? 0);
if ($courrierId <= 0) {
    jsonReponse(['success' => false, 'message' => 'ID invalide'], 400);
}

$model = new Courrier();
$ok    = $model->accuserReception($courrierId, (int)$_SESSION['user_id']);
logAudit("Accusé réception AJAX courrier #$courrierId", 'courriers', $courrierId);

jsonReponse(['success' => $ok, 'message' => $ok ? 'Réception accusée.' : 'Erreur ou déjà accusé.']);
