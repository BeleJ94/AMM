<?php
/**
 * AJAX DataTables - Source de données courriers (server-side processing)
 * AMM-ERP
 */
session_start();
date_default_timezone_set('Africa/Lubumbashi');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['user_id'])) {
    jsonReponse(['error' => 'Non authentifié'], 401);
}

$pdo = getDB();

// Paramètres DataTables
$draw   = (int)($_POST['draw'] ?? 1);
$start  = (int)($_POST['start'] ?? 0);
$length = (int)($_POST['length'] ?? 20);
$search = trim($_POST['search']['value'] ?? '');

// Compter le total
$totalStmt = $pdo->query("SELECT COUNT(*) FROM courriers");
$total     = (int)$totalStmt->fetchColumn();

// Requête principale avec recherche
$params = [];
$where  = "WHERE 1=1";

if (!empty($search)) {
    $where   .= " AND (c.numero_unique LIKE ? OR c.expediteur LIKE ? OR c.objet LIKE ?)";
    $term    = '%' . $search . '%';
    $params  = [$term, $term, $term];
}

// Compter les filtrés
$stmtFiltre = $pdo->prepare("SELECT COUNT(*) FROM courriers c $where");
$stmtFiltre->execute($params);
$totalFiltre = (int)$stmtFiltre->fetchColumn();

// Récupérer les données
$params[] = $length;
$params[] = $start;

$stmt = $pdo->prepare(
    "SELECT c.id, c.numero_unique, c.expediteur, c.objet, c.statut, c.urgence,
            c.date_reception, c.deadline, c.service_destinataire
     FROM courriers c
     $where
     ORDER BY c.created_at DESC
     LIMIT ? OFFSET ?"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Formater les données pour DataTables
$data = [];
foreach ($rows as $c) {
    $urgenceBadge = '<span class="badge bg-' . badgeUrgence($c['urgence']) . '">' . e($c['urgence']) . '</span>';
    $statutBadge  = '<span class="badge bg-' . badgeStatut($c['statut']) . '">' . e($c['statut']) . '</span>';
    $actions = '<a href="' . APP_URL . '/courrier/voir/' . $c['id'] . '" class="btn btn-xs btn-outline-primary"><i class="bi bi-eye"></i></a>';

    $data[] = [
        'numero_unique'       => '<a href="' . APP_URL . '/courrier/voir/' . $c['id'] . '">' . e($c['numero_unique']) . '</a>',
        'expediteur'          => e($c['expediteur']),
        'objet'               => '<span title="' . e($c['objet']) . '">' . e(substr($c['objet'], 0, 60)) . '...</span>',
        'statut'              => $statutBadge,
        'urgence'             => $urgenceBadge,
        'date_reception'      => formatDate($c['date_reception']),
        'deadline'            => formatDate($c['deadline']),
        'service_destinataire'=> e($c['service_destinataire'] ?? '—'),
        'actions'             => $actions,
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => $total,
    'recordsFiltered' => $totalFiltre,
    'data'            => $data,
], JSON_UNESCAPED_UNICODE);
