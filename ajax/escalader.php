<?php
/**
 * AJAX - Escalader un courrier au chef de service
 * AMM-ERP
 */
session_start();
date_default_timezone_set('Africa/Lubumbashi');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Courrier.php';
require_once __DIR__ . '/../models/Notification.php';

if (empty($_SESSION['user_id'])) {
    jsonReponse(['success' => false], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonReponse(['success' => false], 405);
}

$courrierId = (int)($_POST['courrier_id'] ?? 0);
if ($courrierId <= 0) {
    jsonReponse(['success' => false, 'message' => 'ID invalide'], 400);
}

$courrierModel = new Courrier();
$courrier      = $courrierModel->trouverParId($courrierId);

if (!$courrier) {
    jsonReponse(['success' => false, 'message' => 'Courrier introuvable'], 404);
}

// Trouver tous les chefs de service et direction
$pdo  = getDB();
$stmt = $pdo->prepare(
    "SELECT id FROM users WHERE role IN ('chef_service','direction') AND actif = 1"
);
$stmt->execute();
$chefs = $stmt->fetchAll(PDO::FETCH_COLUMN);

$notifModel = new Notification();
foreach ($chefs as $chefId) {
    $notifModel->creer(
        (int)$chefId,
        'escalation',
        "Escalade : le courrier {$courrier['numero_unique']} nécessite votre attention urgente.",
        APP_URL . '/courrier/voir/' . $courrierId
    );
}

logAudit("Escalade AJAX courrier #$courrierId", 'courriers', $courrierId);

jsonReponse(['success' => true, 'escalade_niveau' => 'chef_service', 'notifies' => count($chefs)]);
