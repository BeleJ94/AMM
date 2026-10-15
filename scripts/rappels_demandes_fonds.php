<?php
/** À exécuter chaque jour via le planificateur du serveur ; également déclenché par les notifications. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
date_default_timezone_set('Africa/Lubumbashi');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Treasury.php';
try {
    $nombre = (new Treasury())->envoyerRappelsDemandes();
    echo $nombre . " rappel(s) de demandes de fonds envoyé(s).\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Échec des rappels : " . $e->getMessage() . "\n");
    exit(1);
}
