<?php
/**
 * Read-only HTTP checks for the shared registers. Uses an existing administrator,
 * isolated PHP sessions and a loopback server whose PDO session rejects writes.
 * Run: php scripts/smoke_http_registers.php [--preview-seconds=120]
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set(APP_TIMEZONE);
$pdo = getDB();
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$root = realpath(__DIR__ . '/..');
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'amm-registers-' . bin2hex(random_bytes(8));
$temporaryFiles = []; $process = null; $checks = 0; $exitCode = 0; $summary = [];
$baseUrl = ''; $sessionId = bin2hex(random_bytes(16));

function registerAssert(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function registerGet(string $path): array {
    global $baseUrl, $sessionId;
    $headers = [];
    $curl = curl_init($baseUrl . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>30,
        CURLOPT_COOKIE=>'PHPSESSID=' . $sessionId,
        CURLOPT_HEADERFUNCTION=>static function ($curl, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            return strlen($line);
        },
    ]);
    $body = curl_exec($curl); $code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl); curl_close($curl);
    if ($body === false) throw new RuntimeException('HTTP ' . $path . ': ' . $error);
    registerAssert($code===200, 'HTTP ' . $code . ' sur ' . $path);
    registerAssert(!preg_match('/(?:Fatal error|Uncaught (?:Error|Exception|PDOException)|Warning:|Notice:|Deprecated:)/i', $body), 'Erreur PHP dans la réponse de ' . $path);
    return ['body'=>$body, 'headers'=>$headers];
}

function registerDom(string $html): array {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    return [$dom, new DOMXPath($dom)];
}

/** Counts regular data rows, while preserving empty placeholders and detail rows. */
function registerRows(DOMXPath $xpath, DOMElement $table, string $path): int {
    $head = $xpath->query('./thead/tr[last()]/*', $table);
    registerAssert($head->length>0, 'En-tête absent sur ' . $path);
    registerAssert($xpath->query('./tbody', $table)->length===1, 'Corps du tableau absent sur ' . $path);
    $columns = 0;
    foreach ($head as $cell) $columns += max(1, (int)$cell->getAttribute('colspan'));
    $rows = 0;
    foreach ($xpath->query('./tbody/tr', $table) as $row) {
        $cells = $xpath->query('./td|./th', $row);
        if ($cells->length===1 && (int)$cells->item(0)->getAttribute('colspan')===$columns) continue;
        $width = 0;
        foreach ($cells as $cell) $width += max(1, (int)$cell->getAttribute('colspan'));
        registerAssert($width===$columns, 'Nombre de cellules incohérent sur ' . $path . ' (' . $width . '/' . $columns . ')');
        $rows++;
    }
    return $rows;
}

function registerInspect(string $path, ?string $targetId=null, bool $tableRequired=true): array {
    $response = registerGet($path);
    [$dom, $xpath] = registerDom($response['body']);
    $tables = $xpath->query('//table[@data-register-table or @id="tableCourriers"]');
    if ($tableRequired) registerAssert($tables->length>0, 'Registre absent sur ' . $path);
    foreach ($tables as $table) registerRows($xpath, $table, $path);
    foreach ($xpath->query('//form[@data-register-filters or @id="filtresCourriers"]') as $form) {
        $card = $xpath->query('ancestor::*[contains(concat(" ",normalize-space(@class)," ")," card ") or contains(concat(" ",normalize-space(@class)," ")," section-card ")][1]', $form)->item(0);
        registerAssert($card!==null, 'Filtres hors carte de registre sur ' . $path);
        if ($tables->length>0) registerAssert($xpath->query('.//table[@data-register-table or @id="tableCourriers"]', $card)->length>0, 'Bloc de filtres séparé du tableau sur ' . $path);
    }
    $table = $targetId!==null ? $xpath->query('//table[@id="' . $targetId . '"]')->item(0) : $tables->item(0);
    if ($tableRequired) registerAssert($table!==null, 'Tableau principal absent sur ' . $path);
    return ['body'=>$response['body'], 'dom'=>$dom, 'xpath'=>$xpath, 'table'=>$table];
}

function registerCount(string $sql): int {
    global $pdo;
    return (int)$pdo->query($sql)->fetchColumn();
}

function registerTableExists(string $table): bool {
    global $pdo;
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn()>0;
}

function registerSnapshot(): array {
    $snapshot=[];
    foreach (['users','services','audit_logs','notifications','treasury_fund_requests','treasury_request_reminders'] as $table) $snapshot[$table]=registerCount('SELECT COUNT(*) FROM ' . $table);
    return $snapshot;
}

try {
    registerAssert(extension_loaded('curl') && extension_loaded('dom') && function_exists('proc_open'), 'cURL, DOM et proc_open requis.');
    $admin = $pdo->query("SELECT id,nom,role,service,service_id FROM users WHERE actif=1 AND role='admin' ORDER BY id LIMIT 1")->fetch();
    registerAssert((bool)$admin, 'Un compte administrateur actif existant est nécessaire.');
    registerAssert(registerCount("SELECT COUNT(*) FROM treasury_fund_requests WHERE status='en_suspens' AND reminder_date<=CURDATE()")===0, 'Rappels réels arrivés à échéance : différer ce test pour préserver les notifications.');
    $before = registerSnapshot();
    if (!mkdir($tempDir,0700,true) && !is_dir($tempDir)) throw new RuntimeException('Répertoire temporaire inaccessible.');
    $sessionDir = $tempDir . DIRECTORY_SEPARATOR . 'sessions';
    mkdir($sessionDir,0700); ini_set('session.save_path',$sessionDir);
    session_id($sessionId); session_start();
    $_SESSION = ['user_id'=>(int)$admin['id'], 'user_nom'=>$admin['nom'], 'user_role'=>$admin['role'], 'user_service'=>$admin['service'], 'user_service_id'=>$admin['service_id'], 'csrf_token'=>bin2hex(random_bytes(32))];
    session_write_close();

    $socket = stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if (!$socket) throw new RuntimeException('Port local inaccessible : ' . $error);
    $address=stream_socket_get_name($socket,false); fclose($socket);
    $port=(int)substr(strrchr($address,':'),1); $baseUrl='http://127.0.0.1:'.$port;
    // Exercise the real project view with in-memory data even when its schema is absent.
    $projectFixture=$tempDir.DIRECTORY_SEPARATOR.'project-fixture.php'; $temporaryFiles[]=$projectFixture;
    $fixtureSource= <<<'PHP'
<?php
session_start();
date_default_timezone_set('Africa/Lubumbashi');
require_once $root.'/includes/functions.php';
require_once $root.'/models/Courrier.php';
require_once $root.'/models/Contrat.php';
require_once $root.'/models/Treasury.php';
$today=date('Y-m-d'); $next=date('Y-m-d',strtotime('+7 days'));
$projet=['id'=>0,'titre'=>'Smoke register project','description'=>'Read-only in-memory interface fixture','budget'=>1000,'devise'=>'USD','date_debut'=>$today,'date_fin'=>$next,'statut'=>'en_cours','responsable_nom'=>'Smoke administrator'];
$phases=[];
for ($i=1;$i<=2;$i++) $phases[]=['id'=>$i,'nom'=>'Phase '.$i,'ordre_phase'=>$i,'date_debut'=>$today,'date_fin'=>$next,'statut'=>'en_cours','progress_pct'=>25];
$users=[['id'=>(int)$_SESSION['user_id'],'nom'=>'Smoke administrator'],['id'=>999999,'nom'=>'Smoke team member']];
$taches=[];
for ($i=1;$i<=32;$i++) $taches[]=['id'=>$i,'titre'=>'Smoke task '.str_pad((string)$i,2,'0',STR_PAD_LEFT),'phase_id'=>$i%2+1,'phase_nom'=>'Phase '.($i%2+1),'assigned_to'=>$i%2?(int)$_SESSION['user_id']:999999,'assigne_nom'=>$i%2?'Smoke administrator':'Smoke team member','deadline'=>$i%3===0?date('Y-m-d',strtotime('-1 day')):$next,'priorite'=>'moyenne','statut'=>$i%4===0?'termine':'en_cours'];
$jalons=[]; $jalonTaskLinks=[];
for ($i=1;$i<=18;$i++) {
    $jalons[]=['id'=>$i,'titre'=>'Smoke milestone '.str_pad((string)$i,2,'0',STR_PAD_LEFT),'phase_id'=>$i%2+1,'phase_nom'=>'Phase '.($i%2+1),'date_butoir'=>$next,'statut'=>$i%4===0?'atteint':'non_demarre','description'=>'Read-only milestone'];
    $jalonTaskLinks[$i]=['total'=>1,'pending'=>1,'task_ids'=>[$i]];
}
$stats=['progress_global_pct'=>25,'progress_phases_pct'=>25,'total_taches'=>32,'taches_terminees'=>8,'taches_en_retard'=>8,'total_jalons'=>18,'jalons_atteints'=>4,'jalons_en_retard'=>0];
$liens=[];
require $root.'/views/projets/voir.php';
PHP;
    file_put_contents($projectFixture,$fixtureSource);
    $router=$tempDir.DIRECTORY_SEPARATOR.'router.php'; $temporaryFiles[]=$router;
    $routerSource='<?php $root='.var_export($root,true).'; $path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH); if (($_SERVER["REQUEST_METHOD"]??"GET")!=="GET") { http_response_code(405); exit("Read-only smoke server"); } $physical=$root.$path; if ($path!=="/" && is_file($physical) && strtolower(pathinfo($physical,PATHINFO_EXTENSION))!=="php") return false; require_once $root."/config/database.php"; getDB()->exec("SET SESSION TRANSACTION READ ONLY"); $_GET["url"]=ltrim($path,"/"); $_SERVER["SCRIPT_NAME"]="/index.php"; if ($path==="/__smoke/project-registers") { require '.var_export($projectFixture,true).'; return; } if ($path!=="/index.php" && is_file($physical)) { require $physical; return; } require $root."/index.php";';
    file_put_contents($router,$routerSource);
    $log=$tempDir.DIRECTORY_SEPARATOR.'server.log'; $temporaryFiles[]=$log;
    $environment=getenv(); $environment['APP_URL']=$baseUrl;
    $process=proc_open([PHP_BINARY,'-d','session.save_path='.$sessionDir,'-d','display_errors=1','-d','error_reporting=32767','-S','127.0.0.1:'.$port,'-t',$root,$router],
        [0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root,$environment,['bypass_shell'=>true]);
    if (!is_resource($process)) throw new RuntimeException('Démarrage du serveur PHP impossible.');
    fclose($pipes[0]); $ready=false;
    for ($attempt=0;$attempt<50;$attempt++) {
        $connection=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,0.1);
        if ($connection) { fclose($connection); $ready=true; break; }
        usleep(100000);
    }
    registerAssert($ready,'Serveur HTTP non disponible.');

    $serverCases = [
        ['/documents/liste', 'SELECT COUNT(*) FROM documents WHERE parent_id IS NULL'],
        ['/contrats/liste', 'SELECT COUNT(*) FROM contrats'],
        ['/projets/liste', 'SELECT COUNT(*) FROM projects'],
        ['/finance/transactions?annee=0&mois=0', 'SELECT COUNT(*) FROM financial_transactions t JOIN financial_categories c ON c.id=t.categorie_id JOIN financial_accounts a ON a.id=t.compte_id'],
        ['/finance/factures?annee=0&mois=0', 'SELECT COUNT(*) FROM invoices'],
        ['/admin-logs?periode=tout', 'SELECT COUNT(*) FROM audit_logs'],
    ];
    foreach ($serverCases as [$path,$sql]) {
        if (str_starts_with($path,'/projets/') && !registerTableExists('projects')) {
            $summary[$path]=['skipped'=>'Module projets non migré : table projects absente.'];
            continue;
        }
        $total=registerCount($sql);
        foreach ([null,25,50,100,500,0] as $size) {
            $expectedSize=in_array($size,[25,50,100],true) ? $size : 15;
            $url=$path.($size===null ? '' : (str_contains($path,'?')?'&':'?').'per_page='.$size);
            $page=registerInspect($url); $table=$page['table'];
            registerAssert($table->getAttribute('data-register-mode')==='server','Mode serveur absent sur '.$url);
            registerAssert((int)$table->getAttribute('data-register-total')===$total,'Compteur SQL incohérent sur '.$url);
            registerAssert(registerRows($page['xpath'],$table,$url)===min($total,$expectedSize),'Taille de page incohérente sur '.$url);
            if ($table->hasAttribute('data-register-page-size')) registerAssert((int)$table->getAttribute('data-register-page-size')===$expectedSize,'Taille déclarée incohérente sur '.$url);
        }
        if ($total>15) {
            $page=registerInspect($path.(str_contains($path,'?')?'&':'?').'page=2');
            registerAssert(registerRows($page['xpath'],$page['table'],$path)===min(15,$total-15),'Deuxième page incohérente sur '.$path);
        }
        $summary[$path]=['total'=>$total,'pagination'=>'15 / 25 / 50 / 100, valeurs hors liste rejetées'];
    }

    $courrier=registerInspect('/courrier/liste','tableCourriers');
    registerAssert(str_contains($courrier['body'],'pageLength: 15'),'Courrier : taille par défaut différente de 15.');
    $courrierTotal=registerCount('SELECT COUNT(*) FROM courriers');
    foreach ([15,25,100,500] as $size) {
        $json=registerGet('/courrier/liste?draw=7&start=0&length='.$size);
        $data=json_decode($json['body'],true,512,JSON_THROW_ON_ERROR);
        registerAssert($data['draw']===7 && $data['recordsTotal']===$courrierTotal && $data['recordsFiltered']===$courrierTotal,'Courrier : réponse JSON ou compteurs incohérents.');
        [$dom,$xpath]=registerDom('<table><thead><tr>'.str_repeat('<th></th>',10).'</tr></thead><tbody>'.$data['html'].'</tbody></table>');
        $rows=registerRows($xpath,$xpath->query('//table')->item(0),'courrier JSON');
        registerAssert($rows===min($courrierTotal,$size===500?15:$size),'Courrier : taille JSON incohérente.');
    }
    $empty=registerGet('/courrier/liste?draw=9&recherche='.rawurlencode('SMOKE-NO-MATCH-'.bin2hex(random_bytes(16))));
    $data=json_decode($empty['body'],true,512,JSON_THROW_ON_ERROR);
    registerAssert($data['draw']===9 && $data['recordsFiltered']===0 && trim($data['html'])==='','Courrier : recherche sans résultat incohérente.');
    $summary['/courrier/liste']=['total'=>$courrierTotal,'json'=>'draw / tailles / compteurs / recherche vide'];

    foreach ([
        ['/admin-departements',null,true], ['/users/liste','tableUsers',true],
        ['/courrier/mes-courriers','tblMesCourriers',true],
        ['/treasury/demandes','tableDemandesFonds',registerCount('SELECT COUNT(*) FROM treasury_fund_requests')>0],
        ['/treasury/ordres','tableOrdresPaiement',registerCount('SELECT COUNT(*) FROM treasury_payment_orders')>0],
        ['/treasury/encaissements','tableEncaissements',registerCount('SELECT COUNT(*) FROM treasury_receipts')>0],
        ['/treasury/banque',null,false], ['/treasury/caisse',null,false],
        ['/treasury/rapports',null,false], ['/contrats/alerte',null,false],
        ['/projets/calendrier',null,false], ['/admin-logs',null,true],
        ['/finance/transactions',null,true], ['/finance/factures',null,true],
    ] as [$path,$tableId,$required]) {
        if (str_starts_with($path,'/projets/') && !registerTableExists('projects')) {
            $summary[$path]=['skipped'=>'Module projets non migré : table projects absente.'];
            continue;
        }
        registerInspect($path,$tableId,$required); $summary[$path]=$summary[$path]??['render'=>'ok'];
    }
    // Funds requests: business-state filters, independent state cells and paid rows.
    $requestFilterLabels = [
        ''=>'Toutes', 'a_controler'=>'À contrôler', 'soumise'=>'À valider',
        'en_suspens'=>'En suspens', 'validee_comptable'=>'Direction',
        'validee_direction'=>'Prêtes pour ordre', 'payee'=>'Payées',
        'brouillon'=>'Brouillons', 'ordre_emis'=>'Ordres émis',
        'rejetee'=>'Rejetées', 'annulee'=>'Annulées',
    ];
    $requestCellLabels = array_merge($requestFilterLabels, [
        'brouillon'=>'Brouillon', 'validee_direction'=>'Prête pour ordre',
        'ordre_emis'=>'Ordre émis', 'payee'=>'Payée',
        'rejetee'=>'Rejetée', 'annulee'=>'Annulée',
    ]);
    $requestStates = [];
    foreach ($pdo->query('SELECT id,status,urgency FROM treasury_fund_requests')->fetchAll() as $row) $requestStates[(int)$row['id']] = $row;
    $inspectRequestStates = static function (string $path, string $status='', string $urgency='') use ($pdo, $requestStates, $requestFilterLabels, $requestCellLabels): int {
        $page = registerGet($path);
        [$dom, $xpath] = registerDom($page['body']);
        $select = $xpath->query('//select[@id="requestStateFilter" and @name="status"]')->item(0);
        registerAssert($select!==null, 'Demandes : filtre des états absent sur '.$path);
        registerAssert($xpath->query('ancestor::*[@data-register-advanced]', $select)->length>0, 'Demandes : état non marqué comme filtre avancé.');
        $options = [];
        foreach ($xpath->query('./option', $select) as $option) $options[$option->getAttribute('value')] = trim($option->textContent);
        registerAssert($options===$requestFilterLabels, 'Demandes : options métier des états incohérentes.');
        if ($status!=='') {
            $selected = $xpath->query('./option[@selected]', $select);
            registerAssert($selected->length===1 && $selected->item(0)->getAttribute('value')===$status, 'Demandes : état sélectionné non conservé.');
        }
        if ($urgency!=='') {
            $selected = $xpath->query('//select[@name="urgency"]/option[@selected]');
            registerAssert($selected->length===1 && $selected->item(0)->getAttribute('value')===$urgency, 'Demandes : urgence sélectionnée non conservée.');
        }
        registerAssert($xpath->query('//*[@id="requestQuickViews"]')->length===0, 'Demandes : anciens onglets des états encore présents.');
        $where = [];
        if ($status!=='') $where[] = 'status='.$pdo->quote($status);
        if ($urgency!=='') $where[] = 'urgency='.$pdo->quote($urgency);
        $expected = min(5000, registerCount('SELECT COUNT(*) FROM treasury_fund_requests'.($where ? ' WHERE '.implode(' AND ', $where) : '')));
        $table = $xpath->query('//table[@id="tableDemandesFonds"]')->item(0);
        if ($table===null) {
            registerAssert($expected===0 && str_contains($page['body'], 'Aucune demande trouvée'), 'Demandes : tableau manquant malgré des résultats SQL.');
        } else {
            registerAssert($xpath->query('./thead/tr/th', $table)->length===9, 'Demandes : neuf colonnes attendues.');
            registerAssert(trim($xpath->query('./thead/tr/th[8]', $table)->item(0)->textContent)==='État', 'Demandes : colonne État mal positionnée.');
            registerAssert(registerRows($xpath, $table, $path)===$expected, 'Demandes : nombre de lignes différent du filtre SQL.');
            foreach ($xpath->query('./tbody/tr', $table) as $row) {
                $cells = $xpath->query('./td', $row);
                registerAssert($cells->length===9, 'Demandes : neuf cellules attendues par ligne.');
                $id = (int)$cells->item(0)->getAttribute('data-order');
                registerAssert(isset($requestStates[$id]), 'Demandes : identifiant de ligne absent des données SQL.');
                $record = $requestStates[$id]; $state = $record['status'];
                registerAssert($row->getAttribute('data-request-state')===$state, 'Demandes : état de ligne différent du statut SQL.');
                registerAssert(($status==='' || $state===$status) && ($urgency==='' || $record['urgency']===$urgency), 'Demandes : ligne ne respectant pas les filtres métier.');
                $classes = preg_split('/\s+/', trim($cells->item(7)->getAttribute('class')));
                registerAssert(in_array('request-state-cell', $classes, true) && in_array('request-state-'.$state, $classes, true), 'Demandes : classe de couleur absente de la cellule État.');
                registerAssert(trim($cells->item(7)->textContent)===($requestCellLabels[$state] ?? ''), 'Demandes : libellé de cellule État incohérent.');
                $rowClasses = preg_split('/\s+/', trim($row->getAttribute('class')));
                registerAssert(in_array('request-row-paid', $rowClasses, true)===($state==='payee'), 'Demandes : coloration de ligne réservée aux demandes payées.');
            }
        }
        $exports = $xpath->query('//a[contains(@href,"/treasury/demandes-kpi-pdf?") or contains(@href,"/treasury/demandes-kpi-excel?")]');
        registerAssert($exports->length===2, 'Demandes : liens des exports du registre absents.');
        foreach ($exports as $export) {
            parse_str((string)parse_url($export->getAttribute('href'), PHP_URL_QUERY), $query);
            registerAssert(($query['metric'] ?? '')==='registre' && ($query['status'] ?? '')===$status && ($query['urgency'] ?? '')===$urgency, 'Demandes : filtres métier perdus dans un lien d’export.');
        }
        return $expected;
    };
    $allRequestCount = $inspectRequestStates('/treasury/demandes');
    $paidRequestCount = $inspectRequestStates('/treasury/demandes?status=payee', 'payee');
    $requestUrgency = $pdo->query("SELECT urgency FROM treasury_fund_requests WHERE status='soumise' AND urgency IN ('basse','moyenne','haute') ORDER BY id LIMIT 1")->fetchColumn() ?: 'haute';
    $submittedRequestCount = $inspectRequestStates('/treasury/demandes?status=soumise&urgency='.rawurlencode($requestUrgency), 'soumise', $requestUrgency);
    $summary['/treasury/demandes'] = ['total'=>$allRequestCount, 'paid'=>$paidRequestCount, 'submitted_urgency'=>$requestUrgency, 'submitted'=>$submittedRequestCount, 'states'=>'Advanced selector, nine columns, state-cell and paid-row classes, SQL filters and export links'];

    // Orders and receipts: independent state columns, advanced SQL filters and completed rows.
    foreach ([
        [
            'path'=>'/treasury/ordres', 'id'=>'tableOrdresPaiement', 'columns'=>8, 'state_index'=>6,
            'quick_views'=>'orderQuickViews', 'empty'=>'Aucun ordre de paiement trouvé', 'complete'=>'execute',
            'options'=>[''=>'Tous les états', 'emis'=>'À exécuter', 'execute'=>'Exécutés', 'annule'=>'Annulés'],
            'select'=>'o.id,o.status', 'from'=>'treasury_payment_orders o JOIN treasury_fund_requests r ON r.id=o.request_id JOIN financial_accounts a ON a.id=o.account_id LEFT JOIN treasury_payments p ON p.payment_order_id=o.id',
            'status_column'=>'o.status',
        ],
        [
            'path'=>'/treasury/encaissements', 'id'=>'tableEncaissements', 'columns'=>7, 'state_index'=>5,
            'quick_views'=>'receiptQuickViews', 'empty'=>'Aucun encaissement trouvé', 'complete'=>'valide',
            'options'=>[''=>'Tous les états', 'brouillon'=>'À valider', 'valide'=>'Validés', 'annule'=>'Annulés'],
            'select'=>'r.id,r.status', 'from'=>'treasury_receipts r JOIN financial_accounts a ON a.id=r.account_id',
            'status_column'=>'r.status',
        ],
    ] as $case) {
        $states = [];
        foreach ($pdo->query('SELECT '.$case['select'].' FROM '.$case['from'])->fetchAll() as $record) $states[(int)$record['id']]=$record['status'];
        $counts = [];
        foreach (array_keys($case['options']) as $status) {
            $path = $case['path'].($status!=='' ? '?status='.rawurlencode($status) : '');
            $page = registerGet($path);
            [$dom,$xpath] = registerDom($page['body']);
            $select = $xpath->query('//form[@data-register-filters]//select[@name="status"]')->item(0);
            registerAssert($select!==null, 'Filtre des états absent sur '.$path);
            registerAssert($xpath->query('ancestor::*[@data-register-advanced]', $select)->length>0, 'État hors des filtres avancés sur '.$path);
            $options = [];
            foreach ($xpath->query('./option',$select) as $option) $options[$option->getAttribute('value')]=trim($option->textContent);
            // The all-states label is allowed to retain the register-specific wording.
            registerAssert(array_keys($options)===array_keys($case['options']), 'Valeurs des états métier incohérentes sur '.$path);
            foreach ($case['options'] as $value=>$label) {
                if ($value!=='') registerAssert(($options[$value]??'')===$label, 'Libellé métier incorrect pour '.$value.' sur '.$path);
            }
            if ($status!=='') {
                $selected = $xpath->query('./option[@selected]',$select);
                registerAssert($selected->length===1 && $selected->item(0)->getAttribute('value')===$status, 'État sélectionné non conservé sur '.$path);
            }
            registerAssert($xpath->query('//*[@id="'.$case['quick_views'].'"]')->length===0, 'Anciens onglets de statut encore présents sur '.$path);
            $expected = min(5000,registerCount('SELECT COUNT(*) FROM '.$case['from'].($status!==''?' WHERE '.$case['status_column'].'='.$pdo->quote($status):'')));
            $table = $xpath->query('//table[@id="'.$case['id'].'"]')->item(0);
            if ($table===null) {
                registerAssert($expected===0 && str_contains($page['body'],$case['empty']), 'Registre absent malgré des résultats SQL sur '.$path);
            } else {
                registerAssert($xpath->query('./thead/tr/th',$table)->length===$case['columns'], 'Nombre de colonnes incorrect sur '.$path);
                $heading = $xpath->query('./thead/tr/th['.($case['state_index']+1).']',$table)->item(0);
                registerAssert($heading!==null && trim($heading->textContent)==='État', 'Colonne État absente ou mal placée sur '.$path);
                registerAssert(registerRows($xpath,$table,$path)===$expected, 'Nombre de lignes différent du filtre SQL sur '.$path);
                foreach ($xpath->query('./tbody/tr',$table) as $row) {
                    $cells = $xpath->query('./td',$row);
                    $id = (int)$cells->item(0)->getAttribute('data-order');
                    registerAssert(isset($states[$id]), 'Identifiant de ligne absent des données SQL sur '.$path);
                    $state = $states[$id]; $cell = $cells->item($case['state_index']);
                    registerAssert($cell->getAttribute('data-register-state')===$state && trim($cell->textContent)!=='', 'Cellule État incohérente avec le statut SQL sur '.$path);
                    registerAssert($status==='' || $state===$status, 'Ligne ne respectant pas le filtre métier sur '.$path);
                    registerAssert(($row->getAttribute('data-register-complete')==='true')===($state===$case['complete']), 'Marquage des lignes terminées incorrect sur '.$path);
                }
            }
            $counts[$status===''?'all':$status] = $expected;
        }
        // A valid status plus an impossible text search must produce the controlled empty state.
        $status = array_keys($case['options'])[1];
        $empty = registerGet($case['path'].'?status='.rawurlencode($status).'&recherche='.rawurlencode('SMOKE-NO-MATCH-'.bin2hex(random_bytes(16))));
        [$dom,$xpath] = registerDom($empty['body']);
        registerAssert(str_contains($empty['body'],$case['empty']), 'État vide contrôlé absent sur '.$case['path']);
        registerAssert($xpath->query('//table[@id="'.$case['id'].'"]/tbody/tr')->length===0, 'Lignes présentes malgré une recherche sans résultat sur '.$case['path']);
        $summary[$case['path']] = ['counts'=>$counts, 'states'=>'Advanced business selector, separate state column, completed-row attributes, exact SQL filters and controlled empty state'];
    }

    foreach (['/documents/liste','/contrats/liste','/projets/liste'] as $path) {
        if (str_starts_with($path,'/projets/') && !registerTableExists('projects')) continue;
        $url=$path.'?recherche='.rawurlencode('SMOKE-NO-MATCH-'.bin2hex(random_bytes(16)));
        $empty=registerInspect($url);
        registerAssert(registerRows($empty['xpath'],$empty['table'],$url)===0,'Recherche sans résultat : lignes présentes sur '.$path);
        registerAssert((int)$empty['table']->getAttribute('data-register-total')===0,'Recherche sans résultat : compteur incorrect sur '.$path);
    }
    $project=registerInspect('/__smoke/project-registers','tableProjectTasks');
    foreach (['tableProjectTasks'=>32,'tableProjectMilestones'=>18,'tableProjectMilestoneMatrix'=>18,'tableProjectPhases'=>2] as $id=>$count) {
        $table=$project['xpath']->query('//table[@id="'.$id.'"]')->item(0);
        registerAssert($table!==null && registerRows($project['xpath'],$table,'project fixture')===$count,'Projet : lignes de fixture incohérentes pour '.$id);
    }
    registerAssert($project['xpath']->query('//template[starts-with(@id,"projectMilestoneLinks")]')->length===18,'Projet : formulaires de liaison absents.');
    $summary['/__smoke/project-registers']=['render'=>'Real view with 32 tasks / 18 milestones / 2 phases, in-memory only'];
    registerAssert(registerSnapshot()===$before,'Le test a modifié le nombre de lignes des données suivies.');
    $logBody=(string)file_get_contents($log);
    registerAssert(!preg_match('/PHP (?:Warning|Fatal error|Notice|Deprecated)|Uncaught|Erreur rappel demande|SQLSTATE/', $logBody),'Erreur technique dans le journal du serveur isolé.');
    echo json_encode(['status'=>'ok','checks'=>$checks,'read_only'=>true,'routes'=>$summary],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL;

    foreach ($argv as $argument) {
        if (!preg_match('/^--preview-seconds=(\d+)$/',$argument,$match)) continue;
        $seconds=min(300,(int)$match[1]); if ($seconds<1) break;
        $manifest=$tempDir.DIRECTORY_SEPARATOR.'browser-manifest.json'; $temporaryFiles[]=$manifest;
        file_put_contents($manifest,json_encode(['base_url'=>$baseUrl,'session_id'=>$sessionId,'sessions'=>['admin'=>['id'=>$sessionId]],'read_only'=>true],JSON_PRETTY_PRINT));
        echo 'PREVIEW_MANIFEST '.$manifest.PHP_EOL; flush();
        $deadline=microtime(true)+$seconds;
        while (microtime(true)<$deadline) usleep(200000);
        registerAssert(registerSnapshot()===$before,'La prévisualisation a modifié le nombre de lignes des données suivies.');
        break;
    }
} catch (Throwable $e) {
    fwrite(STDERR,'REGISTER_SMOKE_ERROR: '.$e->getMessage().PHP_EOL); $exitCode=1;
    if (isset($log) && is_file($log)) fwrite(STDERR,substr((string)file_get_contents($log),-3500).PHP_EOL);
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if (isset($sessionDir)) {
        $sessionPath=$sessionDir.DIRECTORY_SEPARATOR.'sess_'.$sessionId;
        if (is_file($sessionPath)) unlink($sessionPath);
    }
    foreach ($temporaryFiles as $file) if (is_file($file)) unlink($file);
    if (isset($sessionDir) && is_dir($sessionDir)) rmdir($sessionDir);
    if (is_dir($tempDir)) rmdir($tempDir);
}
exit($exitCode);
