<?php
/** Consultation uniquement : aucune méthode de modification ou de suppression du journal. */
class AuditLog {
    private PDO $pdo;
    public function __construct() { $this->pdo = getDB(); }

    public static function modules(): array {
        return ['auth'=>'Connexions','administration'=>'Administration','courrier'=>'Courriers','documents'=>'Documents','contrats'=>'Contrats','projets'=>'Projets','finance'=>'Finance','treasury'=>'Trésorerie','autre'=>'Autres'];
    }
    public static function types(): array {
        return ['connexion'=>'Connexion','echec'=>'Échec de connexion','deconnexion'=>'Déconnexion','refus'=>'Accès refusé','creation'=>'Création','modification'=>'Modification','validation'=>'Validation / traitement','annulation'=>'Annulation / rejet','suspension'=>'Suspension / reprise','consultation'=>'Consultation / export','autre'=>'Autres'];
    }
    private function moduleSql(): string {
        return "CASE WHEN a.action LIKE '%connexion%' THEN 'auth'
            WHEN a.table_name IN ('treasury_settings','audit_logs') THEN 'administration'
            WHEN a.table_name LIKE 'treasury_%' THEN 'treasury'
            WHEN a.table_name IN ('financial_transactions','financial_accounts','financial_categories','invoices','invoice_payments','invoice_reminders','budgets') THEN 'finance'
            WHEN a.table_name IN ('projects','project_phases','project_milestones','project_tasks','project_links','project_milestone_tasks') THEN 'projets'
            WHEN a.table_name IN ('users','services','treasury_settings','audit_logs') THEN 'administration'
            WHEN a.table_name IN ('courriers','assignations','preuves') THEN 'courrier'
            WHEN a.table_name='documents' THEN 'documents' WHEN a.table_name IN ('contrats','paiements') THEN 'contrats' ELSE 'autre' END";
    }
    private function typeSql(): string {
        return "CASE WHEN a.action LIKE '%connexion%chou%' OR a.action LIKE 'Tentative de connexion%' THEN 'echec'
            WHEN a.action LIKE '%acces refuse%' THEN 'refus'
            WHEN a.action LIKE '%deconnexion%' THEN 'deconnexion' WHEN a.action LIKE '%connexion%' THEN 'connexion'
            WHEN a.action LIKE '%annul%' OR a.action LIKE '%rejet%' THEN 'annulation'
            WHEN a.action LIKE '%suspens%' OR a.action LIKE '%reprendre%' OR a.action LIKE '%reprise%' THEN 'suspension'
            WHEN a.action LIKE '%chargement%' OR a.action LIKE '%export%' OR a.action LIKE '%consultation%' THEN 'consultation'
            WHEN a.action LIKE '%creation%' OR a.action LIKE '%ajout%' OR a.action LIKE '%upload%' THEN 'creation'
            WHEN a.action LIKE '%modification%' OR a.action LIKE '%mise a jour%' OR a.action LIKE '%activation%' OR a.action LIKE '%toggle%' OR a.action LIKE '%nouvelle version%' THEN 'modification'
            WHEN a.action LIKE '%valid%' OR a.action LIKE '%decision%' OR a.action LIKE '%controle%' OR a.action LIKE '%soumission%' OR a.action LIKE '%transmission%' OR a.action LIKE '%execution%' OR a.action LIKE '%cloture%' OR a.action LIKE '%assignation%' OR a.action LIKE '%statut%' OR a.action LIKE '%accus%' OR a.action LIKE '%emission%' OR a.action LIKE '%rapprochement%' THEN 'validation' ELSE 'autre' END";
    }
    private function filtres(array $f): array {
        $where = []; $params = [];
        if (!empty($f['date_debut'])) { $where[]='a.created_at>=?'; $params[]=$f['date_debut'].' 00:00:00'; }
        if (!empty($f['date_fin'])) { $where[]='a.created_at<?'; $params[]=(new DateTimeImmutable($f['date_fin']))->modify('+1 day')->format('Y-m-d').' 00:00:00'; }
        if (($f['user_id'] ?? '') === 'anonyme') $where[]='a.user_id IS NULL';
        elseif (!empty($f['user_id'])) { $where[]='a.user_id=?'; $params[]=(int)$f['user_id']; }
        if (!empty($f['module'])) { $where[]='('.$this->moduleSql().')=?'; $params[]=$f['module']; }
        if (!empty($f['type'])) { $where[]='('.$this->typeSql().')=?'; $params[]=$f['type']; }
        if (!empty($f['recherche'])) {
            $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$f['recherche']).'%';
            $where[]="(a.action LIKE ? ESCAPE '!' OR u.nom LIKE ? ESCAPE '!' OR a.ip_address LIKE ? ESCAPE '!' OR a.table_name LIKE ? ESCAPE '!' OR a.details LIKE ? ESCAPE '!')";
            array_push($params,$term,$term,$term,$term,$term);
        }
        if (!empty($f['record_id'])) { $where[]='a.record_id=?'; $params[]=(int)$f['record_id']; }
        if (!empty($f['max_id'])) { $where[]='a.id<=?'; $params[]=(int)$f['max_id']; }
        if (!empty($f['before_id'])) { $where[]='a.id<?'; $params[]=(int)$f['before_id']; }
        return [$where ? ' WHERE '.implode(' AND ',$where) : '',$params];
    }
    public function statistiques(array $f): array {
        [$where,$params]=$this->filtres($f);
        $stmt=$this->pdo->prepare("SELECT COUNT(*) total, COUNT(DISTINCT a.user_id) utilisateurs,
            COALESCE(SUM((".$this->typeSql().")='echec'),0) echecs,
            COALESCE(SUM((".$this->typeSql().")='annulation'),0) annulations,
            MAX(a.created_at) derniere, MAX(a.id) max_id FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id".$where);
        $stmt->execute($params); return $stmt->fetch();
    }
    public function lister(array $f, int $limit=50, int $offset=0): array {
        [$where,$params]=$this->filtres($f);
        $stmt=$this->pdo->prepare('SELECT a.*, u.nom AS user_nom, u.role AS user_role, u.service AS user_service, '.$this->moduleSql().' AS module, '.$this->typeSql().' AS type FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id'.$where.' ORDER BY a.id DESC LIMIT ? OFFSET ?');
        $params[]=max(1,min(500,$limit)); $params[]=max(0,$offset); $stmt->execute($params);
        return array_map([self::class,'presenter'],$stmt->fetchAll());
    }
    public function utilisateurs(): array {
        return $this->pdo->query("SELECT DISTINCT a.user_id id, COALESCE(u.nom,CONCAT('Ancien utilisateur #',a.user_id)) nom FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.user_id IS NOT NULL ORDER BY nom")->fetchAll();
    }
    public static function nettoyer($value) {
        if (!is_array($value)) return $value;
        $clean=[];
        foreach ($value as $key=>$item) $clean[$key]=preg_match('/password|passwd|mot.?de.?passe|nouveau_mdp|csrf|token|secret|authorization|cookie|private.?key/i',(string)$key) ? '[Masqué]' : self::nettoyer($item);
        return $clean;
    }
    public static function presenter(array $row): array {
        $decoded=json_decode((string)($row['details'] ?? ''),true);
        $structured=is_array($decoded) && ($decoded['_audit_version'] ?? null) === 1;
        $actor=$structured && is_array($decoded['acteur'] ?? null) ? $decoded['acteur'] : [];
        $row['acteur_nom']=$actor['nom'] ?? $row['user_nom'] ?? ($row['user_id'] ? 'Ancien utilisateur #'.$row['user_id'] : 'Non authentifié / système');
        $row['acteur_role']=$actor['role'] ?? $row['user_role'] ?? '';
        $row['acteur_service']=$actor['departement'] ?? $row['user_service'] ?? '';
        $row['contexte']=$structured ? ($decoded['contexte'] ?? []) : [];
        $detail=$structured ? ($decoded['evenement'] ?? null) : ($decoded ?? $row['details']);
        $row['detail_texte']=is_array($detail) ? json_encode(self::nettoyer($detail),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE) : (string)($detail ?? '');
        return $row;
    }
    public static function lien(array $row): ?string {
        $routes=['courriers'=>'/courrier/voir/','documents'=>'/documents/voir/','contrats'=>'/contrats/voir/','projects'=>'/projets/voir/','treasury_fund_requests'=>'/treasury/demande-voir/','treasury_receipts'=>'/treasury/encaissement-voir/','invoices'=>'/finance/facture-voir/','treasury_bank_statements'=>'/treasury/rapprochement/','users'=>'/users/modifier/'];
        $id=(int)($row['record_id'] ?? 0);
        if ($id<=0) return null;
        if (($row['table_name'] ?? '')==='financial_transactions') return APP_URL.'/finance/transactions?transaction_id='.$id.'&annee=0&mois=0';
        if (($row['table_name'] ?? '')==='services') return APP_URL.'/admin-departements?modifier='.$id;
        return isset($routes[$row['table_name'] ?? '']) ? APP_URL.$routes[$row['table_name']].$id : null;
    }
}
