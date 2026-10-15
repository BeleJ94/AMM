<?php
/**
 * HTTP integration checks against an isolated local PHP server and temporary sessions.
 * Run: php scripts/smoke_http_treasury_requests.php [--preview-seconds=120]
 * Creates uniquely identified fixtures; removes only those fixtures in finally.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI uniquement.'); }
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$root = realpath(__DIR__ . '/..');
$tag = 'HTTPREQ-' . bin2hex(random_bytes(6));
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . strtolower($tag);
$users = []; $sessions = []; $requestIds = []; $temporaryFiles = [];
$process = null; $checks = 0; $baseUrl = ''; $exitCode = 0;

function httpRequestAssert(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function httpRequestCall(string $role, string $path, ?array $post = null): array {
    global $sessions, $baseUrl;
    $headers = [];
    $curl = curl_init($baseUrl . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>30,
        CURLOPT_COOKIE=>'PHPSESSID=' . $sessions[$role]['id'],
        CURLOPT_HEADERFUNCTION=>static function ($curl, string $line) use (&$headers): int {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
            return strlen($line);
        },
    ]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($curl);
    $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false) throw new RuntimeException('HTTP ' . $path . ' : ' . $error);
    if ($code >= 500 || preg_match('/(?:Fatal error|Uncaught (?:Error|Exception|PDOException)|Warning:)/', substr($body,0,100000))) {
        throw new RuntimeException('Erreur serveur sur ' . $path . ' (' . $code . ') : ' . substr(strip_tags($body),0,600));
    }
    return ['status'=>$code,'headers'=>$headers,'body'=>$body];
}
function httpRequestRow(int $id): array {
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM treasury_fund_requests WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: [];
}
function httpRequestCreate(string $role, string $object): int {
    global $sessions, $users, $pdo, $requestIds, $tag;
    $get = httpRequestCall($role,'/treasury/demande-ajouter');
    httpRequestAssert($get['status']===200, 'Formulaire inaccessible pour '.$role);
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $get['body'], $csrfMatch);
    httpRequestAssert(($csrfMatch[1]??'')===$sessions[$role]['csrf'], 'Token CSRF absent du formulaire.');
    $category = (int)$pdo->query("SELECT id FROM financial_categories WHERE actif=1 AND type_flux='depense' ORDER BY id LIMIT 1")->fetchColumn();
    httpRequestAssert($category>0,'Catégorie active nécessaire.');
    $post = httpRequestCall($role,'/treasury/demande-ajouter',[
        'csrf_token'=>$sessions[$role]['csrf'], 'request_date'=>date('Y-m-d'),
        'department'=>'Département falsifié', 'requester_name'=>'Demandeur falsifié',
        'beneficiary'=>$tag.' Fournisseur', 'objet'=>$object, 'description'=>'Justification du besoin pour le test HTTP temporaire.',
        'currency'=>'USD','category_id'=>$category,'urgency'=>'moyenne',
        'amount'=>1,'amount_usd'=>999999,
        'items'=>[
            ['description'=>'Ordinateurs','quantity'=>2,'unit_price'=>750,'line_total'=>1],
            ['description'=>'Installation','quantity'=>1,'unit_price'=>500,'line_total'=>1],
        ],
    ]);
    $find = $pdo->prepare('SELECT id FROM treasury_fund_requests WHERE created_by=? AND objet=? ORDER BY id DESC LIMIT 1');
    $find->execute([$users[$role]['id'],$object]);
    $id = (int)$find->fetchColumn();
    if ($id) $requestIds[] = $id;
    httpRequestAssert($post['status']===302 && $id>0,'Brouillon non créé par le contrôleur : '.substr(strip_tags($post['body']),0,300));
    httpRequestAssert(str_ends_with($post['headers']['location']??'','/treasury/demande-voir/'.$id),'Redirection vers la fiche absente.');
    $row = httpRequestRow($id);
    httpRequestAssert($row['status']==='brouillon','Le brouillon a changé prématurément de statut.');
    httpRequestAssert((float)$row['amount']===2000.0 && (float)$row['amount_usd']===2000.0,'Total issu des lignes non recalculé.');
    httpRequestAssert($row['department']===$users[$role]['service'] && $row['requester_name']===$users[$role]['nom'],'Identité ou département falsifié accepté.');
    return $id;
}
function httpRequestDecision(string $role, int $id, string $level, string $decision, array $extra=[]): array {
    global $sessions;
    return httpRequestCall($role,'/treasury/demande-valider/'.$id,$extra+[
        'csrf_token'=>$sessions[$role]['csrf'],'approval_level'=>$level,'decision'=>$decision,
        'commentaire'=>'Décision pour le test HTTP temporaire.',
    ]);
}

try {
    httpRequestAssert(extension_loaded('curl') && function_exists('proc_open'),'cURL et proc_open requis.');
    $columns = $pdo->query('SHOW COLUMNS FROM treasury_fund_requests')->fetchAll(PDO::FETCH_UNIQUE);
    httpRequestAssert(isset($columns['department']) && isset($columns['approved_amount']),'Migration des demandes nécessaire.');
    // Listing dispatches due reminders. Do not trigger reminders for existing unrelated requests.
    $due = (int)$pdo->query("SELECT COUNT(*) FROM treasury_fund_requests WHERE status='en_suspens' AND reminder_date<=CURDATE()")->fetchColumn();
    httpRequestAssert($due===0,'Des demandes réelles ont un rappel dû. Différer ce test pour préserver leurs notifications.');
    if (!mkdir($tempDir,0700,true) && !is_dir($tempDir)) throw new RuntimeException('Répertoire temporaire inaccessible.');
    $sessionDir = $tempDir . DIRECTORY_SEPARATOR . 'sessions';
    mkdir($sessionDir,0700);
    ini_set('session.save_path',$sessionDir);
    $roles = [
        'agentA'=>['agent',$tag.'-A'], 'agentB'=>['agent',$tag.'-B'],
        'chefA'=>['chef_service',$tag.'-A'], 'tresorier'=>['tresorier',$tag.'-Tresorerie'],
        'comptable'=>['comptable_senior',$tag.'-Comptabilite'],
        'daf'=>['directeur_financier',$tag.'-Finance'], 'dg'=>['directeur_general',$tag.'-DG'],
        'auditeur'=>['auditeur_interne',$tag.'-Audit'],
    ];
    foreach ($roles as $key=>[$role,$service]) {
        $name = $tag.' '.$key;
        $pdo->prepare('INSERT INTO users (nom,email,password,role,service,actif) VALUES (?,?,?,?,?,1)')->execute([
            $name,strtolower($tag.'-'.$key).'@example.invalid',bin2hex(random_bytes(30)),$role,$service,
        ]);
        $id = (int)$pdo->lastInsertId();
        $users[$key] = ['id'=>$id,'nom'=>$name,'service'=>$service];
        $sessions[$key] = ['id'=>bin2hex(random_bytes(16)),'csrf'=>bin2hex(random_bytes(32))];
        session_id($sessions[$key]['id']); session_start();
        $_SESSION = ['user_id'=>$id,'user_nom'=>$name,'user_role'=>$role,'user_service'=>$service,'csrf_token'=>$sessions[$key]['csrf']];
        session_write_close();
    }

    // Reserve a free loopback port, then start the real router without touching Apache or live sessions.
    $socket = stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
    if (!$socket) throw new RuntimeException('Port local inaccessible : '.$error);
    $address = stream_socket_get_name($socket,false); fclose($socket);
    $port = (int)substr(strrchr($address,':'),1);
    $baseUrl = 'http://127.0.0.1:'.$port;
    $router = $tempDir . DIRECTORY_SEPARATOR . 'router.php';
    $routerSource = '<?php $root='.var_export($root,true).'; $path=parse_url($_SERVER["REQUEST_URI"],PHP_URL_PATH); if ($path!=="/" && is_file($root.$path)) return false; $_GET["url"]=ltrim($path,"/"); $_SERVER["SCRIPT_NAME"]="/index.php"; require $root."/index.php";';
    file_put_contents($router,$routerSource); $temporaryFiles[]=$router;
    $log = $tempDir . DIRECTORY_SEPARATOR . 'server.log'; $temporaryFiles[]=$log;
    $environment = getenv(); $environment['APP_URL']=$baseUrl;
    $process = proc_open([PHP_BINARY,'-d','session.save_path='.$sessionDir,'-d','display_errors=1','-S','127.0.0.1:'.$port,'-t',$root,$router],
        [0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root,$environment,['bypass_shell'=>true]);
    if (!is_resource($process)) throw new RuntimeException('Démarrage du serveur PHP impossible.');
    fclose($pipes[0]);
    $ready=false;
    for ($attempt=0;$attempt<50;$attempt++) {
        $connection=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,0.1);
        if ($connection) { fclose($connection);$ready=true;break; }
        usleep(100000);
    }
    httpRequestAssert($ready,'Serveur HTTP non disponible.');

    $main = httpRequestCreate('agentA',$tag.' Demande département A');
    $foreign = httpRequestCreate('agentB',$tag.' Demande département B');
    $detail = httpRequestCall('agentA','/treasury/demande-voir/'.$main);
    httpRequestAssert($detail['status']===200 && str_contains($detail['body'],'Ordinateurs') && str_contains($detail['body'],'Installation'),'Lignes absentes de la fiche.');
    $badCsrf = httpRequestCall('agentA','/treasury/demande-voir/'.$main,['csrf_token'=>'invalide','action'=>'soumettre']);
    httpRequestAssert($badCsrf['status']===302 && httpRequestRow($main)['status']==='brouillon','CSRF invalide accepté.');

    foreach (['/treasury/demande-voir/','/treasury/demande-pdf/'] as $prefix) {
        $denied = httpRequestCall('agentB',$prefix.$main);
        httpRequestAssert($denied['status']===302 && str_ends_with($denied['headers']['location']??'','/treasury/demandes'),'Lecture inter-département autorisée : '.$prefix);
    }
    $denied = httpRequestCall('agentB','/treasury/demande-voir/'.$main,['csrf_token'=>$sessions['agentB']['csrf'],'action'=>'soumettre']);
    httpRequestAssert($denied['status']===302 && httpRequestRow($main)['status']==='brouillon','Écriture inter-département autorisée.');
    $listA = httpRequestCall('agentA','/treasury/demandes');
    $listB = httpRequestCall('agentB','/treasury/demandes');
    httpRequestAssert($listA['status']===200 && str_contains($listA['body'],$tag.' Demande département A') && !str_contains($listA['body'],$tag.' Demande département B'),'Liste agent A non isolée.');
    httpRequestAssert($listB['status']===200 && str_contains($listB['body'],$tag.' Demande département B') && !str_contains($listB['body'],$tag.' Demande département A'),'Liste agent B non isolée.');
    $chief = httpRequestCall('chefA','/treasury/demande-voir/'.$main);
    $chiefList = httpRequestCall('chefA','/treasury/demandes');
    httpRequestAssert($chief['status']===200 && str_contains($chiefList['body'],$tag.' Demande département A') && !str_contains($chiefList['body'],$tag.' Demande département B'),'Périmètre du chef de département incorrect.');
    $audit = httpRequestCall('auditeur','/treasury/demande-ajouter');
    httpRequestAssert($audit['status']===302,'Auditeur autorisé à créer une demande.');
    $auditPost = httpRequestCall('auditeur','/treasury/demande-ajouter',['csrf_token'=>$sessions['auditeur']['csrf'],'objet'=>$tag.' Audit interdit']);
    $auditCount = $pdo->prepare('SELECT COUNT(*) FROM treasury_fund_requests WHERE created_by=?');$auditCount->execute([$users['auditeur']['id']]);
    httpRequestAssert($auditPost['status']===302 && (int)$auditCount->fetchColumn()===0,'Écriture auditeur acceptée.');

    $submit = httpRequestCall('agentA','/treasury/demande-voir/'.$main,['csrf_token'=>$sessions['agentA']['csrf'],'action'=>'soumettre']);
    httpRequestAssert($submit['status']===302 && httpRequestRow($main)['status']==='a_controler','Transmission au trésorier échouée.');
    httpRequestDecision('comptable',$main,'comptable','valide');
    httpRequestAssert(httpRequestRow($main)['status']==='a_controler','Contrôle trésorier contourné.');
    $control = httpRequestCall('tresorier','/treasury/demande-voir/'.$main,['csrf_token'=>$sessions['tresorier']['csrf'],'action'=>'controler','commentaire'=>'Dossier contrôlé.']);
    httpRequestAssert($control['status']===302 && httpRequestRow($main)['status']==='soumise','Contrôle trésorier échoué.');
    $hold = httpRequestDecision('comptable',$main,'comptable','en_suspens',['commentaire'=>'Devis à compléter.','reminder_date'=>date('Y-m-d',strtotime('+1 day'))]);
    $held = httpRequestRow($main);
    httpRequestAssert($hold['status']===302 && $held['status']==='en_suspens' && $held['suspended_level']==='comptable' && !empty($held['reminder_date']),'Suspension/rappel non enregistrés.');
    $holdView = httpRequestCall('comptable','/treasury/demande-voir/'.$main);
    httpRequestAssert($holdView['status']===200 && str_contains($holdView['body'],'Devis à compléter.'),'Motif de suspension absent de la fiche.');
    httpRequestDecision('daf',$main,'direction_financiere','reprendre');
    httpRequestAssert(httpRequestRow($main)['status']==='en_suspens','Reprise par un autre niveau acceptée.');
    httpRequestDecision('comptable',$main,'comptable','reprendre',['commentaire'=>'Devis reçu.']);
    httpRequestAssert(httpRequestRow($main)['status']==='soumise','Reprise comptable échouée.');
    httpRequestDecision('comptable',$main,'comptable','valide',['approved_amount'=>'1500','commentaire'=>'Validation partielle sur devis retenu.']);
    $partial = httpRequestRow($main);
    httpRequestAssert($partial['status']==='validee_comptable' && (float)$partial['approved_amount']===1500.0 && (float)$partial['amount']===2000.0,'Validation partielle comptable incorrecte.');
    httpRequestDecision('daf',$main,'direction_financiere','valide',['approved_amount'=>'1200','commentaire'=>'Plafond DAF retenu.']);
    httpRequestAssert(httpRequestRow($main)['status']==='validee_comptable' && (float)httpRequestRow($main)['approved_amount']===1200.0,'Double validation Direction ou plafond DAF incorrect.');
    httpRequestDecision('dg',$main,'direction_generale','valide',['approved_amount'=>'1200']);
    httpRequestAssert(httpRequestRow($main)['status']==='validee_direction' && (float)httpRequestRow($main)['approved_amount']===1200.0,'Validation Direction finale échouée.');
    $pdf = httpRequestCall('agentA','/treasury/demande-pdf/'.$main);
    httpRequestAssert($pdf['status']===200 && str_starts_with($pdf['body'],'%PDF-') && str_contains($pdf['headers']['content-type']??'','application/pdf'),'Fiche PDF authentifiée invalide.');
    $auditView = httpRequestCall('auditeur','/treasury/demande-voir/'.$main);
    httpRequestAssert($auditView['status']===200,'Lecture auditeur refusée.');
    $events = $pdo->prepare('SELECT COUNT(*) FROM treasury_request_approvals WHERE request_id=?');$events->execute([$main]);
    httpRequestAssert((int)$events->fetchColumn()===6,'Historique des décisions HTTP incomplet.');

    echo json_encode(['status'=>'ok','checks'=>$checks,'base_url'=>$baseUrl,'request_id'=>$main],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
    foreach ($argv as $arg) {
        if (!preg_match('/^--preview-seconds=(\d+)$/',$arg,$match)) continue;
        $seconds = min(180,(int)$match[1]);
        if ($seconds<1) break;
        $manifest = $tempDir . DIRECTORY_SEPARATOR . 'browser-manifest.json'; $temporaryFiles[]=$manifest;
        file_put_contents($manifest,json_encode(['base_url'=>$baseUrl,'request_id'=>$main,'foreign_request_id'=>$foreign,'sessions'=>$sessions],JSON_PRETTY_PRINT));
        echo 'PREVIEW_MANIFEST '.$manifest.PHP_EOL; flush();
        $deadline=microtime(true)+$seconds;
        // Optional preview window only; no live application sessions are involved.
        while (microtime(true)<$deadline) usleep(250000);
        break;
    }
} catch (Throwable $e) {
    fwrite(STDERR,'HTTP_REQUEST_TEST_ERROR: '.$e->getMessage().PHP_EOL); $exitCode=1;
    if (isset($log) && is_file($log)) fwrite(STDERR,substr((string)file_get_contents($log),-3500).PHP_EOL);
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Also recover any request inserted before an assertion, identified by our new user IDs.
    foreach ($users as $user) {
        $find=$pdo->prepare('SELECT id FROM treasury_fund_requests WHERE created_by=?');$find->execute([$user['id']]);
        $requestIds=array_merge($requestIds,$find->fetchAll(PDO::FETCH_COLUMN));
    }
    foreach (array_unique(array_map('intval',$requestIds)) as $id) {
        $pdo->prepare('DELETE FROM notifications WHERE lien=?')->execute([$baseUrl.'/treasury/demande-voir/'.$id]);
        $pdo->prepare('DELETE FROM treasury_fund_requests WHERE id=?')->execute([$id]);
    }
    foreach ($users as $user) {
        $pdo->prepare('DELETE FROM notifications WHERE user_id=?')->execute([$user['id']]);
        $pdo->prepare('DELETE FROM audit_logs WHERE user_id=?')->execute([$user['id']]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$user['id']]);
    }
    foreach ($sessions as $session) {
        $path=($sessionDir??'').DIRECTORY_SEPARATOR.'sess_'.$session['id'];
        if (is_file($path)) unlink($path);
    }
    foreach ($temporaryFiles as $path) if (is_file($path)) unlink($path);
    if (isset($sessionDir) && is_dir($sessionDir)) rmdir($sessionDir);
    if (is_dir($tempDir)) rmdir($tempDir);
}
exit($exitCode);
