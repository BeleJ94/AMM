<?php
/**
 * AJAX - Changer le statut d'un courrier
 * AMM-ERP
 */
session_start();
date_default_timezone_set('Africa/Lubumbashi');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Courrier.php';

if (empty($_SESSION['user_id'])) {
    jsonReponse(['success' => false], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonReponse(['success' => false], 405);
}

$courrierId = (int)($_POST['courrier_id'] ?? 0);
$statut     = trim($_POST['statut'] ?? '');

$statutsValides = ['Recu','Assigne','Accuse','En_cours','Termine','Clos','Archive','Overdue'];
if ($courrierId <= 0 || !in_array($statut, $statutsValides, true)) {
    jsonReponse(['success' => false, 'message' => 'Paramètres invalides'], 400);
}

$model = new Courrier();
$ok    = $model->changerStatut($courrierId, $statut);
logAudit("Changement statut AJAX courrier #$courrierId → $statut", 'courriers', $courrierId);

jsonReponse(['success' => $ok, 'new_status' => $statut]);
