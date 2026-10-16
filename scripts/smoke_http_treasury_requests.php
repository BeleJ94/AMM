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
$users = []; $sessions = []; $requestIds = []; $temporaryFiles = []; $departments = []; $auditIds = [];
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
function httpRequestCreate(string $role, string $object, ?int $departmentId = null): int {
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
        'department_id'=>$departmentId ?? -12345,
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
    if ($departmentId === null) {
        httpRequestAssert($row['department']===$users[$role]['service'] && $row['requester_name']===$users[$role]['nom'] && (int)$row['department_id']===$users[$role]['service_id'],'Identité ou département falsifié accepté.');
    } else {
        httpRequestAssert((int)$row['department_id']===$departmentId,'Département sélectionné non enregistré.');
    }
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
        'admin'=>['admin',$tag.'-Administration'],
    ];
    foreach ($roles as $key=>[$role,$service]) {
        if (!isset($departments[$service])) {
            $pdo->prepare('INSERT INTO services (nom,description,actif) VALUES (?, ?, 1)')->execute([$service,'Référentiel temporaire test HTTP']);
            $departments[$service] = (int)$pdo->lastInsertId();
        }
        $name = $tag.' '.$key;
        $pdo->prepare('INSERT INTO users (nom,email,password,role,service,actif) VALUES (?,?,?,?,?,1)')->execute([
            $name,strtolower($tag.'-'.$key).'@example.invalid',bin2hex(random_bytes(30)),$role,$service,
        ]);
        $id = (int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE users SET service_id=? WHERE id=?')->execute([$departments[$service],$id]);
        $users[$key] = ['id'=>$id,'nom'=>$name,'service'=>$service,'service_id'=>$departments[$service]];
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

    // Référentiel, accès administrateur, renommage et désactivation.
    $directory = httpRequestCall('admin','/admin-departements');
    httpRequestAssert($directory['status']===200 && str_contains($directory['body'],'Référentiel des départements'),'Référentiel admin inaccessible.');
    httpRequestAssert(httpRequestCall('agentA','/admin-departements')['status']===302,'Référentiel accessible hors administration.');
    $newName = $tag.'-Nouveau';
    $payload = ['csrf_token'=>$sessions['admin']['csrf'],'action'=>'enregistrer','nom'=>$newName,'description'=>'Département de démonstration'];
    $bad = httpRequestCall('admin','/admin-departements',array_merge($payload,['csrf_token'=>'incorrect']));
    httpRequestAssert($bad['status']===200 && str_contains($bad['body'],'expiré'),'CSRF référentiel ignoré.');
    $created = httpRequestCall('admin','/admin-departements',$payload);
    $find = $pdo->prepare('SELECT id FROM services WHERE nom=?'); $find->execute([$newName]); $newDepartment=(int)$find->fetchColumn();
    if ($newDepartment) $departments[$newName]=$newDepartment;
    httpRequestAssert($created['status']===302 && $newDepartment>0,'Création de département échouée.');
    $duplicate = httpRequestCall('admin','/admin-departements',$payload);
    httpRequestAssert($duplicate['status']===200 && str_contains($duplicate['body'],'existe déjà'),'Doublon département accepté.');
    $treasurerForm=httpRequestCall('tresorier','/treasury/demande-ajouter');
    httpRequestAssert(str_contains($treasurerForm['body'],'name="department_id"') && str_contains($treasurerForm['body'],$newName),'Liste déroulante non alimentée.');
    $referenceRequest=httpRequestCreate('tresorier',$tag.' Demande référentiel',$newDepartment);
    $renameName=$tag.'-A-Renomme';$originalA=$users['agentA']['service'];$departmentA=$users['agentA']['service_id'];
    $renamed=httpRequestCall('admin','/admin-departements',['csrf_token'=>$sessions['admin']['csrf'],'action'=>'enregistrer','id'=>$departmentA,'nom'=>$renameName,'description'=>'Renommage de test']);
    httpRequestAssert($renamed['status']===302,'Renommage impossible.');
    $chiefAfter=httpRequestCall('chefA','/treasury/demande-voir/'.$main);
    $chiefAfterList=httpRequestCall('chefA','/treasury/demandes');
    httpRequestAssert($chiefAfter['status']===200 && str_contains($chiefAfterList['body'],$tag.' Demande département A'),'Renommage coupe le périmètre du chef.');
    httpRequestAssert(httpRequestRow($main)['department']===$originalA,'Le libellé historique a été réécrit.');
    $renamedForm=httpRequestCall('agentA','/treasury/demande-ajouter');
    httpRequestAssert(str_contains($renamedForm['body'],$renameName),'Session non actualisée après renommage.');
    $deactivate=httpRequestCall('admin','/admin-departements',['csrf_token'=>$sessions['admin']['csrf'],'action'=>'etat','id'=>$newDepartment,'actif'=>'0']);
    httpRequestAssert($deactivate['status']===302,'Désactivation impossible.');
    $activeForm=httpRequestCall('tresorier','/treasury/demande-ajouter');
    httpRequestAssert(!str_contains($activeForm['body'],$newName),'Département inactif encore proposé.');
    $inactivePost=httpRequestCall('tresorier','/treasury/demande-ajouter',['csrf_token'=>$sessions['tresorier']['csrf'],'department_id'=>$newDepartment,'requester_name'=>'Test','objet'=>'Refus inactif']);
    httpRequestAssert($inactivePost['status']===200 && str_contains($inactivePost['body'],'département actif'),'Département inactif accepté côté serveur.');
    httpRequestAssert(httpRequestCall('tresorier','/treasury/demande-voir/'.$referenceRequest)['status']===200,'Désactivation masque les demandes historiques.');
    httpRequestCall('admin','/admin-departements',['csrf_token'=>$sessions['admin']['csrf'],'action'=>'etat','id'=>$newDepartment,'actif'=>'1']);
    httpRequestAssert(str_contains(httpRequestCall('tresorier','/treasury/demande-ajouter')['body'],$newName),'Réactivation non répercutée.');
    $userForm=httpRequestCall('admin','/users/modifier/'.$users['agentB']['id']);
    httpRequestAssert($userForm['status']===200 && str_contains($userForm['body'],'name="service_id"'),'Affectation utilisateur sans référentiel.');
    $userPost=httpRequestCall('admin','/users/modifier/'.$users['agentB']['id'],['csrf_token'=>$sessions['admin']['csrf'],'nom'=>$users['agentB']['nom'],'email'=>strtolower($tag.'-agentB').'@example.invalid','role'=>'agent','service_id'=>$newDepartment,'telephone'=>'']);
    $assigned=$pdo->prepare('SELECT service_id,service FROM users WHERE id=?');$assigned->execute([$users['agentB']['id']]);$assignment=$assigned->fetch();
    httpRequestAssert($userPost['status']===302 && (int)$assignment['service_id']===$newDepartment && $assignment['service']===$newName,'Affectation utilisateur non synchronisée.');

    // Journal d'audit : droits, données historiques, identité figée et exports filtrés.
    require_once __DIR__.'/../includes/functions.php';
    require_once __DIR__.'/../models/AuditLog.php';
    $snapshotAction=$tag.'-AUDIT-SNAPSHOT Validation';
    $_SESSION=['user_id'=>$users['agentA']['id'],'user_nom'=>$users['agentA']['nom'],'user_role'=>'agent','user_service'=>$renameName];
    $_SERVER['REMOTE_ADDR']='127.0.0.1'; $_GET['url']='treasury/demande-valider/'.$main;
    logAudit($snapshotAction,'treasury_fund_requests',$main,json_encode(['commentaire'=>'Trace de test','password'=>'NE-PAS-AFFICHER-'.$tag,'nested'=>['csrf_token'=>'SECRET-'.$tag]],JSON_UNESCAPED_UNICODE));
    $snapshotId=(int)$pdo->lastInsertId();$auditIds[]=$snapshotId;
    $stored=$pdo->prepare('SELECT details FROM audit_logs WHERE id=?');$stored->execute([$snapshotId]);$metadata=(string)$stored->fetchColumn();
    httpRequestAssert(str_contains($metadata,'_audit_version') && !str_contains($metadata,'NE-PAS-AFFICHER-') && !str_contains($metadata,'SECRET-'),'Métadonnées absentes ou secret enregistré.');
    $insertLog=$pdo->prepare('INSERT INTO audit_logs (user_id,action,table_name,record_id,ip_address,details,created_at) VALUES (?,?,?,?,?,?,?)');
    foreach ([['Création '.$tag.'-AUDIT-LEGACY','2001-01-02 23:59:59','Texte historique'],['Création '.$tag.'-AUDIT-LEGACY','2001-01-03 00:00:00','Hors période'],['=2+3 '.$tag.'-AUDIT-CSV',date('Y-m-d H:i:s'),'Export sécurisé'],['<img src=x onerror=alert(1)> '.$tag.'-AUDIT-XSS',date('Y-m-d H:i:s'),json_encode(['password'=>'ANCIEN-SECRET-'.$tag])]] as [$action,$date,$detail]) {
        $insertLog->execute([$users['agentA']['id'],$action,'documents',$main,'127.0.0.1',$detail,$date]);$auditIds[]=(int)$pdo->lastInsertId();
    }
    $insertLog->execute([null,'Tentative de connexion échouée pour: '.$tag,'users',null,'127.0.0.1',null,date('Y-m-d H:i:s')]);$anonymousId=(int)$pdo->lastInsertId();$auditIds[]=$anonymousId;
    $modelAudit=new AuditLog();
    httpRequestAssert((int)$modelAudit->statistiques(['recherche'=>$tag.'-AUDIT-LEGACY','date_debut'=>'2001-01-02','date_fin'=>'2001-01-02'])['total']===1,'Borne de fin de journée incorrecte.');
    httpRequestAssert((int)$modelAudit->statistiques(['recherche'=>$tag,'user_id'=>'anonyme','type'=>'echec'])['total']===1,'Échec de connexion anonyme absent.');
    $pdo->prepare('UPDATE users SET nom=? WHERE id=?')->execute([$tag.' Nom actuel différent',$users['agentA']['id']]);
    $snapshot=$modelAudit->lister(['recherche'=>$tag.'-AUDIT-SNAPSHOT','user_id'=>(string)$users['agentA']['id'],'module'=>'treasury','record_id'=>$main]);
    httpRequestAssert(count($snapshot)===1 && $snapshot[0]['acteur_nom']===$users['agentA']['nom'],'Identité historique réécrite après renommage du compte.');
    httpRequestAssert($modelAudit->statistiques(['recherche'=>"' OR 1=1 -- ".$tag])['total']==0,'Recherche non paramétrée.');
    $logQuery='?periode=tout&recherche='.rawurlencode($tag);
    $journal=httpRequestCall('admin','/admin-logs'.$logQuery);
    httpRequestAssert($journal['status']===200 && str_contains($journal['body'],'Journal des utilisateurs') && str_contains($journal['body'],$snapshotAction),'Journal administrateur inaccessible.');
    httpRequestAssert(str_contains($journal['body'],'Texte historique') && str_contains($journal['body'],'&lt;img src=x') && !str_contains($journal['body'],'<img src=x') && !str_contains($journal['body'],'ANCIEN-SECRET-'),'Compatibilité historique, échappement ou masquage incorrect.');
    foreach (['agentA','daf','auditeur'] as $role) httpRequestAssert(httpRequestCall($role,'/admin-logs'.$logQuery)['status']===302,'Journal accessible à '.$role);
    $sessions['guest']=['id'=>bin2hex(random_bytes(16)),'csrf'=>''];
    httpRequestAssert(httpRequestCall('guest','/admin-logs')['status']===302,'Journal accessible sans connexion.');
    $invalidLog=httpRequestCall('admin','/admin-logs?date_debut=2026-02-30');
    httpRequestAssert($invalidLog['status']===422 && str_contains($invalidLog['body'],'invalide'),'Date invalide acceptée.');
    $filteredLog=httpRequestCall('admin','/admin-logs?periode=tout&module=treasury&user_id='.$users['agentA']['id'].'&recherche='.rawurlencode($tag.'-AUDIT-SNAPSHOT'));
    httpRequestAssert($filteredLog['status']===200 && str_contains($filteredLog['body'],$snapshotAction) && !str_contains($filteredLog['body'],$tag.'-AUDIT-LEGACY'),'Filtres utilisateur et module incorrects.');
    $readonly=httpRequestCall('admin','/admin-logs',['action'=>'supprimer','id'=>$snapshotId]);
    httpRequestAssert($readonly['status']===405 && count($modelAudit->lister(['recherche'=>$tag.'-AUDIT-SNAPSHOT']))===1,'Journal modifiable par POST.');
    $csv=httpRequestCall('admin','/admin-logs?periode=tout&export=csv&recherche='.rawurlencode($tag.'-AUDIT-CSV'));
    httpRequestAssert($csv['status']===200 && str_contains($csv['headers']['content-type']??'','text/csv') && str_starts_with($csv['body'],"\xEF\xBB\xBF") && str_contains($csv['body'],"'=2+3"),'Export CSV absent ou formule non neutralisée.');
    $exportLog=$modelAudit->statistiques(['user_id'=>(string)$users['admin']['id'],'recherche'=>'Export du journal des utilisateurs','type'=>'consultation']);
    httpRequestAssert((int)$exportLog['total']>0,'Export du journal non tracé.');
    $pageLog=httpRequestCall('admin','/admin-logs?periode=tout&par_page=25&page=99999');
    httpRequestAssert($pageLog['status']===200 && str_contains($pageLog['body'],'Pagination du journal'),'Pagination hors limites non gérée.');

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
    foreach ($auditIds as $auditId) $pdo->prepare('DELETE FROM audit_logs WHERE id=?')->execute([$auditId]);
    foreach ($users as $user) {
        $pdo->prepare('DELETE FROM notifications WHERE user_id=?')->execute([$user['id']]);
        $pdo->prepare('DELETE FROM audit_logs WHERE user_id=?')->execute([$user['id']]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$user['id']]);
    }
    foreach (array_unique($departments) as $departmentId) $pdo->prepare('DELETE FROM services WHERE id=?')->execute([$departmentId]);
    foreach ($sessions as $session) {
        $path=($sessionDir??'').DIRECTORY_SEPARATOR.'sess_'.$session['id'];
        if (is_file($path)) unlink($path);
    }
    foreach ($temporaryFiles as $path) if (is_file($path)) unlink($path);
    if (isset($sessionDir) && is_dir($sessionDir)) rmdir($sessionDir);
    if (is_dir($tempDir)) rmdir($tempDir);
}
exit($exitCode);
