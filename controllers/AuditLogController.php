<?php
class AuditLogController {
    public function index(): void {
        verifierSession(); verifierRole(['admin']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405); header('Allow: GET'); exit('Journal en lecture seule.');
        }
        $model=new AuditLog(); $erreurs=[];
        $tout=($_GET['periode'] ?? '')==='tout';
        $filtres=['date_debut'=>trim((string)($_GET['date_debut'] ?? ($tout ? '' : date('Y-m-d',strtotime('-29 days'))))), 'date_fin'=>trim((string)($_GET['date_fin'] ?? ($tout ? '' : date('Y-m-d')))), 'user_id'=>(string)($_GET['user_id'] ?? ''), 'module'=>(string)($_GET['module'] ?? ''), 'type'=>(string)($_GET['type'] ?? ''), 'recherche'=>mb_substr(trim((string)($_GET['recherche'] ?? '')),0,200), 'record_id'=>max(0,(int)($_GET['record_id'] ?? 0))];
        foreach (['date_debut','date_fin'] as $cle) {
            if ($filtres[$cle]==='') continue;
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$filtres[$cle]);
            if (!$date || $date->format('Y-m-d')!==$filtres[$cle] || $filtres[$cle]<'1000-01-01' || $filtres[$cle]>'9998-12-31') { $erreurs[]='La période saisie est invalide.'; $filtres[$cle]=''; }
        }
        if ($filtres['date_debut'] && $filtres['date_fin'] && $filtres['date_debut']>$filtres['date_fin']) $erreurs[]='La date de début doit précéder la date de fin.';
        if ($filtres['user_id']!=='' && $filtres['user_id']!=='anonyme' && (!ctype_digit($filtres['user_id']) || (int)$filtres['user_id']<=0)) { $erreurs[]='Utilisateur invalide.'; $filtres['user_id']=''; }
        if ($filtres['module'] && !isset(AuditLog::modules()[$filtres['module']])) { $erreurs[]='Module invalide.'; $filtres['module']=''; }
        if ($filtres['type'] && !isset(AuditLog::types()[$filtres['type']])) { $erreurs[]='Type d’événement invalide.'; $filtres['type']=''; }
        if ($erreurs) http_response_code(422);
        $stats=$erreurs ? ['total'=>0,'utilisateurs'=>0,'echecs'=>0,'annulations'=>0,'derniere'=>null,'max_id'=>null] : $model->statistiques($filtres);
        if (($_GET['export'] ?? '')==='csv' && !$erreurs) {
            $filtres['max_id']=(int)($stats['max_id'] ?? 0);
            header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="journal-utilisateurs-'.date('Y-m-d').'.csv"'); header('Cache-Control: private, no-store');
            $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Date / heure','Utilisateur','Rôle','Département','Module','Type','Action','Dossier','Adresse IP','Détails'],';');
            $count=0;
            if ($stats['total']>0) do {
                $rows=$model->lister($filtres,500);
                foreach ($rows as $row) {
                    $cells=[$row['created_at'],$row['acteur_nom'],libelleRole($row['acteur_role']),$row['acteur_service'],AuditLog::modules()[$row['module']],AuditLog::types()[$row['type']],$row['action'],($row['table_name'] ?? '').($row['record_id'] ? ' #'.$row['record_id'] : ''),$row['ip_address'],$row['detail_texte']];
                    $cells=array_map(static fn($v)=>preg_match('/^[\s]*[=+@-]/u',(string)$v) ? "'".$v : (string)$v,$cells);
                    fputcsv($out,$cells,';'); $filtres['before_id']=(int)$row['id']; $count++;
                }
            } while (count($rows)===500 && !connection_aborted());
            fclose($out); logAudit('Export du journal des utilisateurs','audit_logs',null,json_encode(['filtres'=>array_diff_key($filtres,array_flip(['before_id','max_id'])),'lignes'=>$count],JSON_UNESCAPED_UNICODE)); exit;
        }
        header('Cache-Control: private, no-store');
        $tailleDemandee=(int)($_GET['per_page'] ?? $_GET['par_page'] ?? 15);
        $parPage=in_array($tailleDemandee,[15,25,50,100],true) ? $tailleDemandee : 15;
        $pages=max(1,(int)ceil((int)$stats['total']/$parPage)); $page=min($pages,max(1,(int)($_GET['page'] ?? 1)));
        $logs=$erreurs ? [] : $model->lister($filtres,$parPage,($page-1)*$parPage);
        $utilisateurs=$model->utilisateurs();
        require __DIR__.'/../views/admin_logs/index.php';
    }
}
