<?php
/**
 * Controleur Treasury - Tresorerie operationnelle
 * AMM-ERP
 */
class TreasuryController {

    private Treasury $treasuryModel;
    private FinancialTracking $financeModel;

    public function __construct() {
        $this->treasuryModel = new Treasury();
        $this->financeModel = new FinancialTracking();
    }

    public function dashboard(): void {
        $this->verifierAccesTresorerie();

        $annee = max(2020, (int)($_GET['annee'] ?? date('Y')));
        $mois = min(12, max(1, (int)($_GET['mois'] ?? date('n'))));
        $stats = $this->treasuryModel->statistiquesDashboard($annee, $mois);
        $operationsRecentes = $this->treasuryModel->dernieresOperations(10);
        $soldesBanque = $this->treasuryModel->soldesBanqueParCompte();
        $tauxCdfUsd = max(1, (float)$this->treasuryModel->getSetting('default_exchange_rate_cdf_usd', '2000'));
        $soldeBanqueUsd = array_sum(array_map(static function ($compte) use ($tauxCdfUsd) {
            $solde = (float)($compte['solde'] ?? 0);
            return ($compte['devise'] ?? 'USD') === 'CDF' ? $solde / $tauxCdfUsd : $solde;
        }, $soldesBanque));
        $ordresEnAttente = $this->treasuryModel->listerOrdres(['status' => 'emis'], 50, 0);
        $engagementsUsd = array_sum(array_map(static function ($ordre) {
            return (float)($ordre['amount_usd'] ?? 0);
        }, $ordresEnAttente));
        $positionLiquidite = [
            'banque_usd' => $soldeBanqueUsd,
            'caisse_usd' => (float)$stats['solde_caisse_usd'],
            'total_usd' => $soldeBanqueUsd + (float)$stats['solde_caisse_usd'],
            'engagements_usd' => $engagementsUsd,
            'disponible_usd' => $soldeBanqueUsd + (float)$stats['solde_caisse_usd'] - $engagementsUsd,
        ];

        $actionsPrioritaires = [];
        $peutComptable = $this->peutValiderNiveau('comptable');
        $peutDf = $this->peutValiderNiveau('direction_financiere');
        $peutDg = $this->peutValiderNiveau('direction_generale');
        $peutExecuter = $this->peutExecuterPaiement();
        $peutEmettre = $this->peutEmettreOrdre();
        $demandesSoumises = $this->treasuryModel->listerDemandes(['status' => 'soumise'], 15, 0);
        $demandesDirection = $this->treasuryModel->listerDemandes(['status' => 'validee_comptable'], 20, 0);
        $demandesPretes = $this->treasuryModel->listerDemandes(['status' => 'validee_direction'], 15, 0);

        if ($this->peutControlerDemandes()) {
            foreach ($this->treasuryModel->listerDemandes(['status'=>'a_controler'], 20, 0) as $demande) {
                $actionsPrioritaires[] = $this->actionDashboardDemande($demande, 'Contrôle avant transmission au comptable', 'Trésorier A', 'Contrôler', 'info');
            }
        }
        foreach ($this->treasuryModel->listerDemandes(['status'=>'en_suspens'], 20, 0) as $demande) {
            if ($this->peutValiderNiveau((string)$demande['suspended_level'])) {
                $actionsPrioritaires[] = $this->actionDashboardDemande($demande, 'En suspens · rappel le ' . formatDate($demande['reminder_date']), 'Validateur du dossier', 'Reprendre', 'warning');
            }
        }

        if ($peutComptable) {
            foreach ($demandesSoumises as $demande) {
                $actionsPrioritaires[] = $this->actionDashboardDemande($demande, 'Contrôle comptable requis', 'Comptable senior', 'Examiner', 'warning');
            }
        }
        foreach ($demandesDirection as $demande) {
            $dfDone = !empty($demande['direction_financiere_validee']);
            $dgDone = !empty($demande['direction_generale_validee']);
            if ($peutDf && !$dfDone) {
                $actionsPrioritaires[] = $this->actionDashboardDemande($demande, 'Décision de la Direction financière', 'Direction financière', 'Décider', 'primary');
            }
            if ($peutDg && !$dgDone) {
                $actionsPrioritaires[] = $this->actionDashboardDemande($demande, 'Décision de la Direction générale', 'Direction générale', 'Décider', 'primary');
            }
        }
        if ($peutEmettre) {
            foreach ($demandesPretes as $demande) {
                $actionsPrioritaires[] = $this->actionDashboardDemande($demande, 'Ordre de paiement à émettre', 'Direction financière', 'Créer l’OP', 'primary', '/treasury/ordre-paiement/');
            }
        }
        if ($peutExecuter) {
            foreach ($ordresEnAttente as $ordre) {
                $issuedByCurrentUser = (int)($ordre['issued_by'] ?? 0) === (int)($_SESSION['user_id'] ?? 0);
                $actionsPrioritaires[] = [
                    'type' => 'Ordre de paiement',
                    'reference' => $ordre['order_number'],
                    'label' => $issuedByCurrentUser ? 'Exécution par un tiers requise' : 'Paiement à exécuter',
                    'owner' => $issuedByCurrentUser ? 'Autre agent habilité' : 'Trésorerie',
                    'beneficiary' => $ordre['beneficiary'],
                    'amount' => formatMontant((float)$ordre['amount'], $ordre['currency']),
                    'amount_usd' => (float)$ordre['amount_usd'],
                    'waiting_days' => max(0, (int)floor((time() - strtotime((string)$ordre['issued_at'])) / 86400)),
                    'url' => APP_URL . '/treasury/ordre-paiement/' . (int)$ordre['request_id'],
                    'button' => $issuedByCurrentUser ? 'Suivre' : 'Exécuter',
                    'tone' => 'warning',
                ];
            }
        }
        usort($actionsPrioritaires, static function ($a, $b) {
            if ($a['waiting_days'] === $b['waiting_days']) return $b['amount_usd'] <=> $a['amount_usd'];
            return $b['waiting_days'] <=> $a['waiting_days'];
        });
        $actionsPrioritaires = array_slice($actionsPrioritaires, 0, 10);
        $profilCockpit = $peutDg ? 'Direction générale' : ($peutDf ? 'Direction financière' : ($peutComptable ? 'Contrôle comptable' : ($peutExecuter ? 'Opérations de trésorerie' : 'Supervision')));

        $this->render('dashboard', compact(
            'annee', 'mois', 'stats', 'operationsRecentes', 'soldesBanque',
            'positionLiquidite', 'actionsPrioritaires', 'profilCockpit'
        ));
    }

    public function rapports(): void {
        $this->verifierAccesTresorerie();

        $type = $_GET['type'] ?? 'situation_globale';
        $filtres = [
            'type' => $type,
            'date_debut' => $_GET['date_debut'] ?? date('Y-m-01'),
            'date_fin' => $_GET['date_fin'] ?? date('Y-m-d'),
            'annee' => (int)($_GET['annee'] ?? date('Y')),
            'mois' => (int)($_GET['mois'] ?? date('n')),
            'account_id' => (int)($_GET['account_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
            'payment_method' => $_GET['payment_method'] ?? '',
            'recherche' => trim($_GET['recherche'] ?? ''),
        ];
        $rapport = $this->treasuryModel->rapportTresorerie($type, $filtres);
        $comptes = $this->financeModel->listerComptes();

        $this->render('rapports', compact('type', 'filtres', 'rapport', 'comptes'));
    }

    public function encaissements(): void {
        $this->verifierAccesTresorerie();

        $filtres = [
            'status' => $_GET['status'] ?? '',
            'payment_method' => $_GET['payment_method'] ?? '',
            'account_id' => (int)($_GET['account_id'] ?? 0),
            'date_debut' => $_GET['date_debut'] ?? '',
            'date_fin' => $_GET['date_fin'] ?? '',
            'recherche' => trim($_GET['recherche'] ?? ''),
        ];

        $encaissements = $this->treasuryModel->listerEncaissements($filtres, 5000, 0);
        $tousEncaissements = $this->treasuryModel->listerEncaissements([], 5000, 0);
        $comptes = $this->financeModel->listerComptes();
        $receiptStats = ['total'=>count($tousEncaissements),'brouillon'=>0,'valide'=>0,'annule'=>0,'montant_valide_usd'=>0.0];
        foreach ($tousEncaissements as $encaissement) {
            $status = (string)$encaissement['status'];
            if (isset($receiptStats[$status])) $receiptStats[$status]++;
            if ($status === 'valide') $receiptStats['montant_valide_usd'] += (float)$encaissement['amount_usd'];
        }
        $receiptKpiDetails = [];
        $definitions = [
            'total'=>['title'=>'Tous les encaissements','subtitle'=>'Vue consolidée du registre des recettes'],
            'brouillon'=>['title'=>'Encaissements à valider','subtitle'=>'Reçus enregistrés en attente de contrôle'],
            'valide'=>['title'=>'Encaissements validés','subtitle'=>'Recettes comptabilisées et intégrées à la trésorerie'],
            'annule'=>['title'=>'Encaissements annulés','subtitle'=>'Reçus clôturés sans comptabilisation'],
        ];
        foreach ($definitions as $metric=>$definition) {
            $lignes = $metric === 'total' ? $tousEncaissements : array_values(array_filter($tousEncaissements, static function ($ligne) use ($metric) {
                return ($ligne['status'] ?? '') === $metric;
            }));
            $totalUsd = array_sum(array_map(static function ($ligne) { return (float)$ligne['amount_usd']; }, $lignes));
            $receiptKpiDetails[$metric] = [
                'title'=>$definition['title'],'subtitle'=>$definition['subtitle'],'count'=>count($lignes),
                'total_usd'=>$totalUsd,'average_usd'=>count($lignes)?$totalUsd/count($lignes):0,
                'rows'=>array_map(function ($ligne) {
                    $status = (string)$ligne['status'];
                    return [
                        'number'=>$ligne['receipt_number'],'date'=>formatDate($ligne['date_receipt']),
                        'payer'=>$ligne['payer_name'],'object'=>$ligne['objet'],'account'=>$ligne['account_nom']??'—',
                        'method'=>$this->libelleTresorerie($ligne['payment_method']),
                        'amount'=>formatMontant((float)$ligne['amount'],$ligne['currency']),
                        'amount_usd'=>(float)$ligne['amount_usd'],'status'=>$this->libelleTresorerie($status),
                        'action'=>$status==='brouillon'?'Validation requise':($status==='valide'?'Recette comptabilisée':'Encaissement annulé'),
                        'url'=>APP_URL.'/treasury/encaissement-voir/'.(int)$ligne['id'],
                    ];
                }, $lignes),
            ];
        }

        $this->render('encaissements', compact('filtres', 'encaissements', 'comptes', 'receiptStats', 'receiptKpiDetails'));
    }

    public function ajouterEncaissement(): void {
        $this->verifierAccesTresorerie('operation');

        $erreurs = [];
        $comptes = $this->financeModel->listerComptes();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = 'Requête invalide.';
            } else {
                $preuve = null;
                if (!empty($_FILES['proof_file']['name'])) {
                    $preuve = traiterUpload($_FILES['proof_file'], 'treasury');
                    if ($preuve === false) {
                        $erreurs[] = 'Pièce justificative invalide.';
                    }
                }

                $donnees = [
                    'date_receipt' => $_POST['date_receipt'] ?? date('Y-m-d'),
                    'payer_name' => trim($_POST['payer_name'] ?? ''),
                    'objet' => trim($_POST['objet'] ?? ''),
                    'amount' => (float)($_POST['amount'] ?? 0),
                    'currency' => $_POST['currency'] ?? 'USD',
                    'exchange_rate' => (float)($_POST['exchange_rate'] ?? 0),
                    'payment_method' => $_POST['payment_method'] ?? '',
                    'account_id' => (int)($_POST['account_id'] ?? 0),
                    'proof_file' => $preuve,
                    'reference_payment' => trim($_POST['reference_payment'] ?? ''),
                    'notes' => trim($_POST['notes'] ?? ''),
                    'created_by' => (int)($_SESSION['user_id'] ?? 0),
                ];

                $this->validerDonneesEncaissement($donnees, $erreurs);

                if (empty($erreurs)) {
                    try {
                        $id = $this->treasuryModel->creerEncaissement($donnees);
                        logAudit('Creation encaissement tresorerie #' . $id, 'treasury_receipts', $id);
                        $_SESSION['encaissement_swal'] = [
                            'icon' => 'success',
                            'title' => 'Encaissement enregistré',
                            'text' => 'Le reçu a été créé en brouillon avec succès.',
                        ];
                        redirect('/treasury/encaissement-voir/' . $id);
                    } catch (DomainException $e) { $erreurs[] = $e->getMessage(); }
                }
            }
        }

        $this->render('encaissement_ajouter', compact('erreurs', 'comptes'));
    }

    public function voirEncaissement(?string $id): void {
        $this->verifierAccesTresorerie();
        if (empty($id)) {
            redirect('/treasury/encaissements');
        }

        $encaissement = $this->treasuryModel->trouverEncaissementParId((int)$id);
        if (!$encaissement) {
            flashMessage('erreur', 'Encaissement introuvable.');
            redirect('/treasury/encaissements');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                flashMessage('erreur', 'Requête invalide.');
                redirect('/treasury/encaissement-voir/' . (int)$id);
            }

            $this->verifierAccesTresorerie('operation');
            if (!$this->peutValiderEncaissement()) {
                flashMessage('erreur', 'Droits insuffisants pour valider un encaissement.');
                redirect('/treasury/encaissement-voir/' . (int)$id);
            }

            $action = $_POST['action'] ?? 'valider';
            if ($action !== 'valider') {
                redirect('/treasury/encaissement-voir/' . (int)$id);
            }

            try { $ok = $this->treasuryModel->validerEncaissement((int)$id, (int)($_SESSION['user_id'] ?? 0)); } catch (DomainException $e) {
            flashMessage('erreur', $e->getMessage());
            redirect('/treasury/encaissement-voir/' . (int)$id);
        }
            if ($ok) {
                logAudit('Validation encaissement tresorerie #' . (int)$id, 'treasury_receipts', (int)$id);
                flashMessage('succes', 'Encaissement valide et transaction financiere creee.');
            } else {
                flashMessage('erreur', 'Impossible de valider cet encaissement.');
            }
            redirect('/treasury/encaissement-voir/' . (int)$id);
        }

        $this->render('encaissement_voir', compact('encaissement'));
    }

    /** Génère le reçu officiel au format PDF (aperçu inline ou téléchargement). */
    public function pdfEncaissement(?string $id): void {
        $this->verifierAccesTresorerie();
        if (empty($id)) {
            http_response_code(404);
            exit('Reçu introuvable.');
        }

        $encaissement = $this->treasuryModel->trouverEncaissementParId((int)$id);
        if (!$encaissement) {
            http_response_code(404);
            exit('Reçu introuvable.');
        }
        if (!class_exists('Dompdf\\Dompdf')) {
            http_response_code(503);
            exit('Le moteur PDF est indisponible.');
        }

        $logoPath = __DIR__ . '/../assets/images/logo/logo_amm.jpg';
        $logoDataUri = '';
        if (is_file($logoPath)) {
            $logoDataUri = 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($logoPath));
        }

        ob_start();
        require __DIR__ . '/../views/treasury/encaissement_pdf.php';
        $html = (string)ob_get_clean();

        $options = new Dompdf\Options();
        // XAMPP peut exposer un sys_temp_dir vide/non inscriptible à Apache.
        // Dompdf et php-font-lib ont besoin d'un chemin réel pour les polices
        // temporaires et leur cache, sinon fopen() reçoit une chaîne vide.
        $pdfTempDir = null;
        $tempBases = array_unique(array_filter([
            trim((string)ini_get('sys_temp_dir')),
            sys_get_temp_dir(),
            rtrim(UPLOAD_DIR, '/\\'),
            '/Applications/XAMPP/xamppfiles/temp',
            '/tmp',
        ]));

        foreach ($tempBases as $base) {
            $candidat = rtrim($base, '/\\') . '/amm-erp-dompdf';
            if (!is_dir($candidat)) {
                @mkdir($candidat, 0777, true);
            }
            if (!is_dir($candidat)) {
                continue;
            }
            // Cache technique local partagé entre PHP CLI et Apache/XAMPP.
            @chmod($candidat, 0777);
            $testFile = @tempnam($candidat, 'write-test-');
            if ($testFile !== false) {
                $testOk = @file_put_contents($testFile, 'ok') !== false;
                @unlink($testFile);
                if ($testOk) {
                    $pdfTempDir = $candidat;
                    break;
                }
            }
        }

        if ($pdfTempDir === null) {
            http_response_code(503);
            exit('Aucun répertoire temporaire utilisable par le moteur PDF. Vérifiez les permissions du dossier XAMPP temp ou assets/uploads.');
        }
        $options->setTempDir($pdfTempDir);
        $options->setFontCache($pdfTempDir);
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(realpath(__DIR__ . '/..'));

        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $numero = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$encaissement['receipt_number']);
        $telecharger = isset($_GET['download']) && $_GET['download'] === '1';
        $dompdf->stream(($numero ?: 'recu-encaissement') . '.pdf', ['Attachment' => $telecharger]);
        exit;
    }

    public function annulerEncaissement(?string $id): void {
        $this->verifierAccesTresorerie('operation');
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/treasury/encaissements');
        }
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect('/treasury/encaissement-voir/' . (int)$id);
        }

        if (!$this->peutAnnulerTresorerie()) {
            flashMessage('erreur', 'Droits insuffisants pour annuler une operation de tresorerie.');
            redirect('/treasury/encaissement-voir/' . (int)$id);
        }

        $motif = trim($_POST['motif'] ?? '');
        if ($motif === '') {
            flashMessage('erreur', 'Le motif d’annulation est obligatoire.');
            redirect('/treasury/encaissement-voir/' . (int)$id);
        }

        $ok = $this->treasuryModel->annulerEncaissement((int)$id, (int)($_SESSION['user_id'] ?? 0), $motif);
        if ($ok) {
            logAudit('Annulation encaissement tresorerie #' . (int)$id, 'treasury_receipts', (int)$id, $motif);
        }
        flashMessage($ok ? 'succes' : 'erreur', $ok ? 'Encaissement annule.' : 'Impossible d’annuler cet encaissement.');
        redirect('/treasury/encaissement-voir/' . (int)$id);
    }

    public function demandes(): void {
        verifierSession();
        $accesDepartement = !accesGlobalDemandesFonds();
        $peutCreerDemande = ($_SESSION['user_role'] ?? '') !== 'auditeur_interne';
        $perimetre = perimetreDemandesFonds();
        $this->treasuryModel->envoyerRappelsDemandes();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $filtres = [
            'status' => $_GET['status'] ?? '',
            'urgency' => $_GET['urgency'] ?? '',
            'date_debut' => $_GET['date_debut'] ?? '',
            'date_fin' => $_GET['date_fin'] ?? '',
            'recherche' => trim($_GET['recherche'] ?? ''),
        ];

        // DataTables assure la pagination, le tri et la recherche côté interface.
        // Une limite de sécurité élevée évite une double pagination incohérente.
        $filtres = array_merge($filtres, $perimetre);
        $demandes = $this->treasuryModel->listerDemandes($filtres, 5000, 0);
        $requestStats = [
            'total' => $this->treasuryModel->compterDemandes($perimetre),
            'a_controler' => $this->treasuryModel->compterDemandes(array_merge(['status' => 'a_controler'], $perimetre)),
            'en_suspens' => $this->treasuryModel->compterDemandes(array_merge(['status' => 'en_suspens'], $perimetre)),
            'soumise' => $this->treasuryModel->compterDemandes(array_merge(['status' => 'soumise'], $perimetre)),
            'validee_comptable' => $this->treasuryModel->compterDemandes(array_merge(['status' => 'validee_comptable'], $perimetre)),
            'validee_direction' => $this->treasuryModel->compterDemandes(array_merge(['status' => 'validee_direction'], $perimetre)),
            'haute' => $this->treasuryModel->compterDemandes(array_merge(['urgency' => 'haute'], $perimetre)),
        ];
        $requestKpiDetails = [];
        foreach (array_keys($requestStats) as $metric) {
            $definition = $this->definitionKpiDemandes($metric);
            $lignes = $this->treasuryModel->listerDemandes(array_merge($definition['filters'], $perimetre), 5000, 0);
            $requestKpiDetails[$metric] = [
                'title' => $definition['title'],
                'subtitle' => $definition['subtitle'],
                'count' => count($lignes),
                'total_usd' => array_sum(array_map(static function ($ligne) {
                    return (float)($ligne['amount_usd'] ?? 0);
                }, $lignes)),
                'rows' => array_map(function ($ligne) {
                    return [
                        'id' => (int)$ligne['id'],
                        'number' => $ligne['request_number'],
                        'date' => formatDate($ligne['request_date']),
                        'beneficiary' => $ligne['beneficiary'],
                        'object' => $ligne['objet'],
                        'category' => $ligne['category_nom'] ?? '—',
                        'amount' => formatMontant((float)$ligne['amount'], $ligne['currency']),
                        'amount_usd' => (float)$ligne['amount_usd'],
                        'urgency' => $this->libelleTresorerie($ligne['urgency']),
                        'status' => $this->libelleTresorerie($ligne['status']),
                        'url' => APP_URL . '/treasury/demande-voir/' . (int)$ligne['id'],
                    ];
                }, $lignes),
            ];
        }

        $this->render('demandes', compact('filtres', 'demandes', 'requestStats', 'requestKpiDetails', 'accesDepartement', 'peutCreerDemande'));
    }

    public function pdfDemandesKpi(): void {
        $this->verifierAccesTresorerie();
        $metric = $_GET['metric'] ?? 'total';
        if (!in_array($metric, ['total','a_controler','en_suspens','soumise','validee_comptable','validee_direction','haute','registre'], true)) {
            $metric = 'total';
        }
        $definition = $metric === 'registre' ? $this->definitionRegistreDemandes() : $this->definitionKpiDemandes($metric);
        $demandesKpi = $this->treasuryModel->listerDemandes($definition['filters'], 5000, 0);
        $totalUsd = array_sum(array_map(static function ($ligne) {
            return (float)($ligne['amount_usd'] ?? 0);
        }, $demandesKpi));
        if (!class_exists('Dompdf\\Dompdf')) {
            http_response_code(503);
            exit('Le moteur PDF est indisponible.');
        }

        $logoPath = __DIR__ . '/../assets/images/logo/logo_amm.jpg';
        $logoDataUri = is_file($logoPath)
            ? 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($logoPath))
            : '';
        ob_start();
        require __DIR__ . '/../views/treasury/demandes_kpi_pdf.php';
        $html = (string)ob_get_clean();

        $tempDir = null;
        foreach (array_unique(array_filter([trim((string)ini_get('sys_temp_dir')), sys_get_temp_dir(), rtrim(UPLOAD_DIR, '/\\'), '/Applications/XAMPP/xamppfiles/temp', '/tmp'])) as $base) {
            $candidate = rtrim($base, '/\\') . '/amm-erp-dompdf';
            if (!is_dir($candidate)) @mkdir($candidate, 0777, true);
            if (!is_dir($candidate)) continue;
            @chmod($candidate, 0777);
            $testFile = @tempnam($candidate, 'write-test-');
            if ($testFile !== false) {
                $ok = @file_put_contents($testFile, 'ok') !== false;
                @unlink($testFile);
                if ($ok) {
                    $tempDir = $candidate;
                    break;
                }
            }
        }
        if ($tempDir === null) {
            http_response_code(503);
            exit('Aucun répertoire temporaire utilisable par le moteur PDF.');
        }

        $options = new Dompdf\Options();
        $options->setTempDir($tempDir);
        $options->setFontCache($tempDir);
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(realpath(__DIR__ . '/..'));
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $download = isset($_GET['download']) && $_GET['download'] === '1';
        $dompdf->stream('demandes-fonds-' . $metric . '-' . date('Y-m-d') . '.pdf', ['Attachment' => $download]);
        exit;
    }

    public function excelDemandesKpi(): void {
        $this->verifierAccesTresorerie();
        $metric = $_GET['metric'] ?? 'total';
        if (!in_array($metric, ['total','a_controler','en_suspens','soumise','validee_comptable','validee_direction','haute','registre'], true)) {
            $metric = 'total';
        }
        $definition = $metric === 'registre' ? $this->definitionRegistreDemandes() : $this->definitionKpiDemandes($metric);
        $demandesKpi = $this->treasuryModel->listerDemandes($definition['filters'], 5000, 0);
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            http_response_code(503);
            exit('Le moteur Excel est indisponible.');
        }

        $workbook = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $workbook->getProperties()
            ->setCreator(APP_NAME)
            ->setCompany('Anticipation des Matériels Modernes')
            ->setTitle($definition['title'] . ' - Vue CFO');
        $summary = $workbook->getActiveSheet();
        $summary->setTitle('Synthèse CFO');
        $details = $workbook->createSheet();
        $details->setTitle('Détail des demandes');
        $navy = '0B2847';
        $blue = '1769E0';
        $green = '16834B';
        $red = 'B12634';
        $white = 'FFFFFF';
        $light = 'EDF3F8';
        $border = 'D9E2EC';

        $summary->setShowGridlines(false);
        $summary->mergeCells('A1:H1');
        $summary->setCellValue('A1', 'AMM · DEMANDES DE FONDS — VUE CFO');
        $summary->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
        ]);
        $summary->mergeCells('A2:H2');
        $summary->setCellValue('A2', $definition['title'] . ' · ' . $definition['subtitle'] . ' · Généré le ' . date('d/m/Y H:i'));
        $summary->getStyle('A2:H2')->getFill()->setFillType('solid')->getStartColor()->setRGB($light);

        $totalUsd = array_sum(array_map(static function ($ligne) {
            return (float)($ligne['amount_usd'] ?? 0);
        }, $demandesKpi));
        $averageUsd = count($demandesKpi) > 0 ? $totalUsd / count($demandesKpi) : 0;
        $highCount = count(array_filter($demandesKpi, static function ($ligne) {
            return ($ligne['urgency'] ?? '') === 'haute';
        }));
        $summary->fromArray([['VOLUME', 'MONTANT TOTAL USD', 'MONTANT MOYEN USD', 'URGENCES HAUTES']], null, 'A5');
        $summary->fromArray([[count($demandesKpi), $totalUsd, $averageUsd, $highCount]], null, 'A6');
        $summary->getStyle('A5:D5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $blue]],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
        ]);
        $summary->getRowDimension(5)->setRowHeight(30);
        $summary->getStyle('A6:D6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
            'alignment' => ['horizontal' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => $border]]],
        ]);
        $summary->getStyle('B6:C6')->getNumberFormat()->setFormatCode('$#,##0.00;[Red]($#,##0.00);-');

        $statusCounts = [];
        foreach ($demandesKpi as $demande) {
            $label = $this->libelleTresorerie($demande['status']);
            $statusCounts[$label] = ($statusCounts[$label] ?? 0) + 1;
        }
        if (empty($statusCounts)) $statusCounts['Aucune demande'] = 0;
        $summary->fromArray([['Statut', 'Nombre']], null, 'A10');
        $statusRow = 11;
        foreach ($statusCounts as $label => $count) {
            $summary->setCellValue('A' . $statusRow, $label);
            $summary->setCellValue('B' . $statusRow, $count);
            $statusRow++;
        }
        $summary->getStyle('A10:B10')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
        ]);
        $labels = [new PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', '\'Synthèse CFO\'!$B$10', null, 1)];
        $categories = [new PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', '\'Synthèse CFO\'!$A$11:$A$' . ($statusRow - 1), null, $statusRow - 11)];
        $values = [new PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('Number', '\'Synthèse CFO\'!$B$11:$B$' . ($statusRow - 1), null, $statusRow - 11)];
        $series = new PhpOffice\PhpSpreadsheet\Chart\DataSeries(
            PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_BARCHART,
            PhpOffice\PhpSpreadsheet\Chart\DataSeries::GROUPING_CLUSTERED,
            [0],
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_COLUMN);
        $chart = new PhpOffice\PhpSpreadsheet\Chart\Chart(
            'requestStatusChart',
            new PhpOffice\PhpSpreadsheet\Chart\Title('Répartition par statut'),
            null,
            new PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$series])
        );
        $chart->setTopLeftPosition('D9')->setBottomRightPosition('H23');
        $summary->addChart($chart);
        foreach (range('A', 'H') as $column) $summary->getColumnDimension($column)->setWidth(20);
        $summary->getPageSetup()->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $summary->getPageSetup()->setFitToWidth(1)->setFitToHeight(1);

        $details->setShowGridlines(false);
        $details->freezePane('A5');
        $isRegister = $metric === 'registre';
        $detailLastColumn = $isRegister ? 'K' : 'J';
        $details->mergeCells('A1:' . $detailLastColumn . '1');
        $details->setCellValue('A1', $definition['title'] . ' — DÉTAIL DES DEMANDES');
        $details->getStyle('A1:' . $detailLastColumn . '1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
        ]);
        $detailHeaders = ['Numéro', 'Date', 'Bénéficiaire', 'Objet', 'Catégorie', 'Montant', 'Devise', 'Montant USD', 'Urgence', 'Statut'];
        if ($isRegister) $detailHeaders[] = 'Prochaine action';
        $details->fromArray([$detailHeaders], null, 'A4');
        $details->getStyle('A4:' . $detailLastColumn . '4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $blue]],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $actionLabels = [
            'brouillon' => 'À compléter et soumettre',
            'soumise' => 'Contrôle comptable requis',
            'validee_comptable' => 'Approbation Direction requise',
            'validee_direction' => 'Ordre de paiement à émettre',
            'ordre_emis' => 'Paiement à exécuter',
            'payee' => 'Paiement terminé',
            'rejetee' => 'Correction requise',
            'annulee' => 'Demande annulée',
        ];
        $row = 5;
        foreach ($demandesKpi as $demande) {
            $detailRow = [
                $demande['request_number'],
                PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime($demande['request_date'] . ' 12:00:00')),
                $demande['beneficiary'],
                $demande['objet'],
                $demande['category_nom'] ?? '—',
                (float)$demande['amount'],
                $demande['currency'],
                (float)$demande['amount_usd'],
                $this->libelleTresorerie($demande['urgency']),
                $this->libelleTresorerie($demande['status']),
            ];
            if ($isRegister) {
                $actionLabel = $actionLabels[$demande['status']] ?? 'Suivi requis';
                if (($demande['status'] ?? '') === 'validee_comptable') {
                    if (!empty($demande['direction_generale_validee']) && empty($demande['direction_financiere_validee'])) {
                        $actionLabel = 'Validation Direction financière requise';
                    } elseif (!empty($demande['direction_financiere_validee']) && empty($demande['direction_generale_validee'])) {
                        $actionLabel = 'Validation Direction générale requise';
                    } else {
                        $actionLabel = 'Double approbation Direction requise';
                    }
                }
                $detailRow[] = $actionLabel;
            }
            $details->fromArray([$detailRow], null, 'A' . $row);
            $row++;
        }
        $lastRow = max(5, $row - 1);
        $details->getStyle('B5:B' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $details->getStyle('F5:F' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);-');
        $details->getStyle('H5:H' . $lastRow)->getNumberFormat()->setFormatCode('$#,##0.00;[Red]($#,##0.00);-');
        $details->getStyle('A5:' . $detailLastColumn . $lastRow)->getBorders()->getBottom()->setBorderStyle('hair')->getColor()->setRGB($border);
        for ($r = 6; $r <= $lastRow; $r += 2) {
            $details->getStyle('A' . $r . ':' . $detailLastColumn . $r)->getFill()->setFillType('solid')->getStartColor()->setRGB('F8FAFC');
        }
        $details->setAutoFilter('A4:' . $detailLastColumn . $lastRow);
        $widths = ['A'=>20,'B'=>13,'C'=>25,'D'=>40,'E'=>22,'F'=>16,'G'=>10,'H'=>17,'I'=>14,'J'=>22];
        if ($isRegister) $widths['K'] = 32;
        foreach ($widths as $column => $width) $details->getColumnDimension($column)->setWidth($width);
        $details->getStyle('C5:E' . $lastRow)->getAlignment()->setWrapText(true);
        $details->getPageSetup()->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $details->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(4, 4);
        $details->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $details->getHeaderFooter()->setOddFooter('&LAMM-ERP · Demandes de fonds&CPage &P / &N&R' . date('d/m/Y H:i'));

        $workbook->setActiveSheetIndex(0);
        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook);
        $writer->setIncludeCharts(true);
        $writer->setPreCalculateFormulas(true);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="demandes-fonds-CFO-' . $metric . '-' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0, must-revalidate');
        $writer->save('php://output');
        $workbook->disconnectWorksheets();
        exit;
    }

    public function ajouterDemande(): void {
        verifierSession();
        if (($_SESSION['user_role'] ?? '') === 'auditeur_interne') {
            flashMessage('erreur', 'Le profil auditeur dispose d’un accès en lecture.');
            redirect('/treasury/demandes');
        }
        $erreurs = [];
        $accesDepartement = !accesGlobalDemandesFonds();
        $categories = $this->financeModel->listerCategories('depense');
        $comptes = $accesDepartement ? [] : $this->financeModel->listerComptes();
        $departementModel = new Department();
        $departements = $departementModel->lister();
        $departementAffecte = !empty($_SESSION['user_service_id']) ? $departementModel->trouver((int)$_SESSION['user_service_id']) : $departementModel->trouverParNom((string)($_SESSION['user_service'] ?? ''));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = 'La session du formulaire a expiré. Veuillez réessayer.';
            } else {
                $selection = $accesDepartement ? $departementAffecte : $departementModel->trouver((int)($_POST['department_id'] ?? 0));
                $department = $selection['nom'] ?? '';
                if (!$selection || !$selection['actif']) $erreurs[] = 'Sélectionnez un département actif dans le référentiel. Si votre affectation est inactive, contactez l’administrateur.';
                $requester = $accesDepartement ? trim((string)($_SESSION['user_nom'] ?? '')) : trim((string)($_POST['requester_name'] ?? $_SESSION['user_nom'] ?? ''));
                $donnees = [
                    'request_date' => $_POST['request_date'] ?? date('Y-m-d'),
                    'department' => $department,
                    'department_id' => $selection ? (int)$selection['id'] : null,
                    'requester_name' => $requester,
                    'beneficiary' => trim((string)($_POST['beneficiary'] ?? '')),
                    'objet' => trim((string)($_POST['objet'] ?? '')),
                    'description' => trim((string)($_POST['description'] ?? '')),
                    'currency' => $_POST['currency'] ?? 'USD',
                    'exchange_rate' => (float)($_POST['exchange_rate'] ?? 0),
                    'category_id' => (int)($_POST['category_id'] ?? 0),
                    'preferred_account_id' => $accesDepartement ? 0 : (int)($_POST['preferred_account_id'] ?? 0),
                    'urgency' => $_POST['urgency'] ?? 'moyenne',
                    'created_by' => (int)$_SESSION['user_id'],
                    'amount' => 0,
                ];
                try {
                    if (!is_array($_POST['items'] ?? null)) throw new InvalidArgumentException('Ajoutez au moins une ligne de bien ou service.');
                    $lignes = $this->treasuryModel->normaliserLignesDemande($_POST['items']);
                    $donnees['items'] = $lignes['items'];
                    $donnees['amount'] = $lignes['total'];
                } catch (InvalidArgumentException $e) { $erreurs[] = $e->getMessage(); }
                $this->validerDonneesDemande($donnees, $erreurs);
                if ($department === '' || mb_strlen($department) > 100) $erreurs[] = 'Un département demandeur valide est obligatoire (100 caractères maximum).';
                if ($requester === '' || mb_strlen($requester) > 150) $erreurs[] = 'Le nom du demandeur est obligatoire (150 caractères maximum).';
                if ($donnees['description'] === '') $erreurs[] = 'Expliquez la justification du besoin.';
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$donnees['request_date']);
                if (!$date || $date->format('Y-m-d') !== $donnees['request_date']) $erreurs[] = 'La date de la demande est invalide.';
                if (!in_array($donnees['urgency'], ['basse','moyenne','haute'], true)) $erreurs[] = 'Priorité invalide.';
                if (empty($erreurs) && !empty($_FILES['justification_file']['name'])) {
                    $preuve = traiterUpload($_FILES['justification_file'], 'treasury');
                    if ($preuve === false) $erreurs[] = 'Le justificatif est invalide ou dépasse la taille autorisée.';
                    else $donnees['justification_file'] = $preuve;
                }
                if (empty($erreurs)) {
                    try {
                        $id = $this->treasuryModel->creerDemandeFonds($donnees);
                        logAudit('Création demande de fonds #' . $id, 'treasury_fund_requests', $id);
                        if (!empty($_POST['soumettre'])) {
                            if (!$this->treasuryModel->soumettreDemande($id, (int)$_SESSION['user_id'])) throw new RuntimeException('La demande a été enregistrée mais sa transmission a échoué. Retrouvez-la dans vos brouillons.');
                            $this->notifierTresoriersDemande($id);
                            logAudit('Transmission au contrôle trésorerie #' . $id, 'treasury_fund_requests', $id);
                        }
                        flashMessage('succes', !empty($_POST['soumettre']) ? 'Demande transmise au trésorier pour contrôle.' : 'Brouillon enregistré. Vous pouvez le transmettre au trésorier depuis sa fiche.');
                        redirect('/treasury/demande-voir/' . $id);
                    } catch (Throwable $e) {
                        error_log('Création demande : ' . $e->getMessage());
                        $erreurs[] = 'L’enregistrement n’a pas abouti. Vérifiez vos demandes avant de réessayer.';
                    }
                }
            }
        }
        $this->render('demande_ajouter', compact('erreurs', 'categories', 'comptes', 'accesDepartement', 'departements', 'departementAffecte'));
    }
    public function voirDemande(?string $id): void {
        verifierSession();
        if (empty($id)) redirect('/treasury/demandes');
        $demande = $this->demandeAccessible((int)$id);
        $peutSoumettre = $demande['status'] === 'brouillon' && (int)$demande['created_by'] === (int)$_SESSION['user_id'] && ($_SESSION['user_role'] ?? '') !== 'auditeur_interne';
        $peutControlerDemande = $demande['status'] === 'a_controler' && $this->peutControlerDemandes();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                flashMessage('erreur', 'La session du formulaire a expiré.');
                redirect('/treasury/demande-voir/' . (int)$id);
            }
            $action = $_POST['action'] ?? '';
            $ok = false;
            if ($action === 'soumettre' && $peutSoumettre) {
                $ok = $this->treasuryModel->soumettreDemande((int)$id, (int)$_SESSION['user_id']);
                if ($ok) $this->notifierTresoriersDemande((int)$id);
            } elseif ($action === 'controler' && $peutControlerDemande) {
                $commentaire = trim((string)($_POST['commentaire'] ?? ''));
                $ok = $this->treasuryModel->controlerDemande((int)$id, (int)$_SESSION['user_id'], $commentaire);
                if ($ok) {
                    $this->notifierValidateursDemande($this->treasuryModel->trouverDemandeParId((int)$id), 'comptable');
                    $this->notifierCreateurDemande($demande, 'Votre demande ' . $demande['request_number'] . ' a été contrôlée et transmise au comptable senior.');
                }
            }
            if ($ok) logAudit('Demande de fonds : ' . $action, 'treasury_fund_requests', (int)$id);
            flashMessage($ok ? 'succes' : 'erreur', $ok ? ($action === 'controler' ? 'Contrôle enregistré. La demande est transmise au comptable senior.' : 'Demande transmise au trésorier pour contrôle.') : 'Cette action n’est pas disponible pour votre profil ou pour l’état actuel de la demande.');
            redirect('/treasury/demande-voir/' . (int)$id);
        }
        $validations = $this->treasuryModel->validationsDemande((int)$id);
        $lignesDemande = $this->treasuryModel->itemsDemande((int)$id);
        $ordre = $this->treasuryModel->trouverOrdreParDemande((int)$id);
        $niveauxValidationAutorises = [];
        foreach (['comptable','direction_financiere','direction_generale'] as $niveau) {
            $statut = $demande['status'] === 'en_suspens' ? $demande['previous_status'] : $demande['status'];
            $etape = $niveau === 'comptable' ? $statut === 'soumise' : $statut === 'validee_comptable';
            $dejaValide = false;
            foreach ($validations as $validation) if ($validation['approval_level'] === $niveau && $validation['decision'] === 'valide') $dejaValide = true;
            $niveauxValidationAutorises[$niveau] = $etape && !$dejaValide && $this->peutValiderNiveau($niveau) && $this->respecteSeparationTachesDemande($demande)
                && ($demande['status'] !== 'en_suspens' || $demande['suspended_level'] === $niveau);
        }
        $peutAnnulerDemande = accesGlobalDemandesFonds() && $this->peutAnnulerTresorerie();
        $peutEmettreOrdre = accesGlobalDemandesFonds() && $this->peutEmettreOrdre() && $this->respecteSeparationTachesDemande($demande);
        $peutCreerDemande = ($_SESSION['user_role'] ?? '') !== 'auditeur_interne';
        $accesDepartement = !accesGlobalDemandesFonds();
        $this->render('demande_voir', compact('demande','validations','lignesDemande','ordre','niveauxValidationAutorises','peutAnnulerDemande','peutEmettreOrdre','peutSoumettre','peutControlerDemande','peutCreerDemande','accesDepartement'));
    }

    public function pdfDemande(?string $id): void {
        verifierSession();
        $demande = $this->demandeAccessible((int)$id);
        $lignesDemande = $this->treasuryModel->itemsDemande((int)$id);
        $validations = $this->treasuryModel->validationsDemande((int)$id);
        ob_start();
        require __DIR__ . '/../views/treasury/demande_pdf.php';
        $html = ob_get_clean();
        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf\Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $pdf->stream('demande-fonds-' . (int)$id . '.pdf', ['Attachment' => ($_GET['download'] ?? '') === '1']);
        exit;
    }
    public function ordres(): void {
        $this->verifierAccesTresorerie();

        $filtres = [
            'status' => $_GET['status'] ?? '',
            'recherche' => trim($_GET['recherche'] ?? ''),
        ];
        $ordres = $this->treasuryModel->listerOrdres($filtres, 5000, 0);
        $tousOrdres = $this->treasuryModel->listerOrdres([], 5000, 0);
        $orderStats = [
            'total' => count($tousOrdres),
            'emis' => 0,
            'execute' => 0,
            'annule' => 0,
            'montant_a_executer' => 0.0,
        ];
        foreach ($tousOrdres as $ordre) {
            $status = (string)($ordre['status'] ?? '');
            if (isset($orderStats[$status])) {
                $orderStats[$status]++;
            }
            if ($status === 'emis') {
                $orderStats['montant_a_executer'] += (float)($ordre['amount_usd'] ?? 0);
            }
        }
        $orderKpiDetails = [];
        foreach (['total', 'emis', 'execute', 'annule'] as $metric) {
            $definition = $this->definitionKpiOrdres($metric);
            $lignes = $this->treasuryModel->listerOrdres($definition['filters'], 5000, 0);
            $totalUsd = array_sum(array_map(static function ($ligne) {
                return (float)($ligne['amount_usd'] ?? 0);
            }, $lignes));
            $orderKpiDetails[$metric] = [
                'title' => $definition['title'],
                'subtitle' => $definition['subtitle'],
                'count' => count($lignes),
                'total_usd' => $totalUsd,
                'average_usd' => count($lignes) > 0 ? $totalUsd / count($lignes) : 0,
                'rows' => array_map(function ($ligne) {
                    $status = (string)$ligne['status'];
                    $action = $status === 'emis' ? 'Paiement à exécuter' : ($status === 'execute' ? 'Paiement exécuté' : 'Ordre annulé');
                    return [
                        'number' => $ligne['order_number'],
                        'date' => formatDate($ligne['order_date']),
                        'request' => $ligne['request_number'],
                        'beneficiary' => $ligne['beneficiary'],
                        'object' => $ligne['objet'],
                        'account' => $ligne['account_nom'] ?? '—',
                        'method' => $this->libelleTresorerie(($ligne['account_type'] ?? '') === 'caisse' ? 'especes' : $ligne['payment_method']),
                        'amount' => formatMontant((float)$ligne['amount'], $ligne['currency']),
                        'amount_usd' => (float)$ligne['amount_usd'],
                        'status' => $this->libelleTresorerie($status),
                        'action' => $action,
                        'url' => APP_URL . '/treasury/ordre-paiement/' . (int)$ligne['request_id'],
                    ];
                }, $lignes),
            ];
        }

        $this->render('ordres', compact('filtres', 'ordres', 'orderStats', 'orderKpiDetails'));
    }

    public function pdfOrdresKpi(): void {
        $this->verifierAccesTresorerie();
        $metric = in_array($_GET['metric'] ?? '', ['total','emis','execute','annule'], true) ? $_GET['metric'] : 'total';
        $definition = $this->definitionKpiOrdres($metric);
        $ordresKpi = $this->treasuryModel->listerOrdres($definition['filters'], 5000, 0);
        $totalUsd = array_sum(array_map(static function ($ligne) {
            return (float)($ligne['amount_usd'] ?? 0);
        }, $ordresKpi));
        if (!class_exists('Dompdf\\Dompdf')) {
            http_response_code(503);
            exit('Le moteur PDF est indisponible.');
        }
        $logoPath = __DIR__ . '/../assets/images/logo/logo_amm.jpg';
        $logoDataUri = is_file($logoPath) ? 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($logoPath)) : '';
        ob_start();
        require __DIR__ . '/../views/treasury/ordres_kpi_pdf.php';
        $html = (string)ob_get_clean();
        $tempDir = null;
        foreach (array_unique(array_filter([
            trim((string)ini_get('sys_temp_dir')),
            sys_get_temp_dir(),
            rtrim(UPLOAD_DIR, '/\\'),
            '/Applications/XAMPP/xamppfiles/temp',
            '/tmp',
        ])) as $base) {
            $candidate = rtrim($base, '/\\') . '/amm-erp-dompdf';
            if (!is_dir($candidate)) @mkdir($candidate, 0777, true);
            if (!is_dir($candidate)) continue;
            @chmod($candidate, 0777);
            $testFile = @tempnam($candidate, 'write-test-');
            if ($testFile === false) continue;
            $writable = @file_put_contents($testFile, 'ok') !== false;
            @unlink($testFile);
            if ($writable) {
                $tempDir = $candidate;
                break;
            }
        }
        if ($tempDir === null) {
            http_response_code(503);
            exit('Aucun répertoire temporaire utilisable par le moteur PDF.');
        }
        $options = new Dompdf\Options();
        $options->setTempDir($tempDir);
        $options->setFontCache($tempDir);
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(realpath(__DIR__ . '/..'));
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('ordres-paiement-CFO-' . $metric . '-' . date('Y-m-d') . '.pdf', [
            'Attachment' => isset($_GET['download']) && $_GET['download'] === '1',
        ]);
        exit;
    }

    public function excelOrdresKpi(): void {
        $this->verifierAccesTresorerie();
        $metric = in_array($_GET['metric'] ?? '', ['total','emis','execute','annule'], true) ? $_GET['metric'] : 'total';
        $definition = $this->definitionKpiOrdres($metric);
        $ordresKpi = $this->treasuryModel->listerOrdres($definition['filters'], 5000, 0);
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            http_response_code(503);
            exit('Le moteur Excel est indisponible.');
        }
        $workbook = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $workbook->getProperties()->setCreator(APP_NAME)->setCompany('Anticipation des Matériels Modernes')->setTitle($definition['title'] . ' - Vue CFO');
        $summary = $workbook->getActiveSheet();
        $summary->setTitle('Synthèse CFO');
        $details = $workbook->createSheet();
        $details->setTitle('Détail des OP');
        $navy = '0B2847'; $blue = '1769E0'; $white = 'FFFFFF'; $light = 'EDF3F8'; $border = 'D9E2EC';
        $totalUsd = array_sum(array_map(static function ($ligne) { return (float)($ligne['amount_usd'] ?? 0); }, $ordresKpi));
        $averageUsd = count($ordresKpi) ? $totalUsd / count($ordresKpi) : 0;
        $cashCount = count(array_filter($ordresKpi, static function ($ligne) { return ($ligne['account_type'] ?? '') === 'caisse'; }));

        $summary->setShowGridlines(false);
        $summary->mergeCells('A1:H1')->setCellValue('A1', 'AMM · ORDRES DE PAIEMENT — VUE CFO');
        $summary->getStyle('A1:H1')->applyFromArray(['font'=>['bold'=>true,'size'=>18,'color'=>['rgb'=>$white]],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>$navy]]]);
        $summary->mergeCells('A2:H2')->setCellValue('A2', $definition['title'] . ' · ' . $definition['subtitle'] . ' · Généré le ' . date('d/m/Y H:i'));
        $summary->getStyle('A2:H2')->getFill()->setFillType('solid')->getStartColor()->setRGB($light);
        $summary->fromArray([['VOLUME','ENGAGEMENT TOTAL USD','MONTANT MOYEN USD','OP SUR CAISSE'],[count($ordresKpi),$totalUsd,$averageUsd,$cashCount]], null, 'A5');
        $summary->getStyle('A5:D5')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>$white]],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>$blue]],'alignment'=>['horizontal'=>'center']]);
        $summary->getStyle('A6:D6')->applyFromArray(['font'=>['bold'=>true,'size'=>16],'alignment'=>['horizontal'=>'center'],'borders'=>['allBorders'=>['borderStyle'=>'thin','color'=>['rgb'=>$border]]]]);
        $summary->getStyle('B6:C6')->getNumberFormat()->setFormatCode('$#,##0.00;[Red]($#,##0.00);-');
        foreach (range('A','H') as $column) $summary->getColumnDimension($column)->setWidth(21);
        $summary->getPageSetup()->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $summary->getPageSetup()->setFitToWidth(1)->setFitToHeight(1);

        $details->setShowGridlines(false);
        $details->freezePane('A5');
        $details->mergeCells('A1:K1')->setCellValue('A1', $definition['title'] . ' — DÉTAIL DES ORDRES DE PAIEMENT');
        $details->getStyle('A1:K1')->applyFromArray(['font'=>['bold'=>true,'size'=>16,'color'=>['rgb'=>$white]],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>$navy]]]);
        $headers = ['Ordre','Date','Demande','Bénéficiaire','Objet','Compte','Mode','Montant','Devise','Montant USD','Statut'];
        $details->fromArray([$headers], null, 'A4');
        $details->getStyle('A4:K4')->applyFromArray(['font'=>['bold'=>true,'color'=>['rgb'=>$white]],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>$blue]],'alignment'=>['horizontal'=>'center']]);
        $row = 5;
        foreach ($ordresKpi as $ordre) {
            $method = ($ordre['account_type'] ?? '') === 'caisse' ? 'especes' : $ordre['payment_method'];
            $details->fromArray([[
                $ordre['order_number'], PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime($ordre['order_date'] . ' 12:00:00')),
                $ordre['request_number'], $ordre['beneficiary'], $ordre['objet'], $ordre['account_nom'] ?? '—',
                $this->libelleTresorerie($method), (float)$ordre['amount'], $ordre['currency'],
                (float)$ordre['amount_usd'], $this->libelleTresorerie($ordre['status']),
            ]], null, 'A' . $row++);
        }
        $lastRow = max(5, $row - 1);
        $details->getStyle('B5:B' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $details->getStyle('H5:H' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00;[Red](#,##0.00);-');
        $details->getStyle('J5:J' . $lastRow)->getNumberFormat()->setFormatCode('$#,##0.00;[Red]($#,##0.00);-');
        $details->getStyle('A5:K' . $lastRow)->getBorders()->getBottom()->setBorderStyle('hair')->getColor()->setRGB($border);
        for ($r = 6; $r <= $lastRow; $r += 2) $details->getStyle('A'.$r.':K'.$r)->getFill()->setFillType('solid')->getStartColor()->setRGB('F8FAFC');
        $details->setAutoFilter('A4:K' . $lastRow);
        foreach (['A'=>20,'B'=>13,'C'=>20,'D'=>25,'E'=>40,'F'=>24,'G'=>16,'H'=>16,'I'=>10,'J'=>17,'K'=>16] as $column=>$width) $details->getColumnDimension($column)->setWidth($width);
        $details->getStyle('C5:G'.$lastRow)->getAlignment()->setWrapText(true);
        $details->getPageSetup()->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $details->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(4,4);
        $details->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $details->getHeaderFooter()->setOddFooter('&LAMM-ERP · Ordres de paiement&CPage &P / &N&R'.date('d/m/Y H:i'));
        $workbook->setActiveSheetIndex(0);
        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook);
        $writer->setPreCalculateFormulas(true);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="ordres-paiement-CFO-' . $metric . '-' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0, must-revalidate');
        $writer->save('php://output');
        $workbook->disconnectWorksheets();
        exit;
    }

    public function validerDemande(?string $id): void {
        $this->verifierAccesTresorerie();
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/treasury/demandes');
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'La session du formulaire a expiré.');
            redirect('/treasury/demande-voir/' . (int)$id);
        }
        $demande = $this->demandeAccessible((int)$id);
        $niveau = (string)($_POST['approval_level'] ?? $this->niveauValidationParDefaut($demande));
        if (!$this->peutValiderNiveau($niveau) || !$this->respecteSeparationTachesDemande($demande)) {
            flashMessage('erreur', 'Cette décision est réservée au validateur habilité. Le créateur et le trésorier soumetteur ne peuvent pas valider leur propre demande.');
            redirect('/treasury/demande-voir/' . (int)$id);
        }
        $decision = (string)($_POST['decision'] ?? '');
        $commentaire = trim((string)($_POST['commentaire'] ?? ''));
        $approvedRaw = trim((string)($_POST['approved_amount'] ?? ''));
        $approvedAmount = $approvedRaw === '' ? null : (is_numeric($approvedRaw) ? (float)$approvedRaw : NAN);
        $reminderDate = trim((string)($_POST['reminder_date'] ?? '')) ?: null;
        $ok = false;
        try {
            if ($decision === 'reprendre') {
                $ok = $this->treasuryModel->reprendreDemande((int)$id, $niveau, (int)$_SESSION['user_id'], $commentaire);
            } elseif (in_array($decision, ['annule','en_suspens','valide'], true)) {
                $ok = $this->treasuryModel->deciderDemande((int)$id, $niveau, (int)$_SESSION['user_id'], $decision, $commentaire, $approvedAmount, $reminderDate);
            }
        } catch (InvalidArgumentException $e) {
            flashMessage('erreur', $e->getMessage());
            redirect('/treasury/demande-voir/' . (int)$id);
        }
        if ($ok) {
            logAudit('Décision demande : ' . $decision . ' · ' . $niveau, 'treasury_fund_requests', (int)$id, json_encode(['commentaire'=>$commentaire,'montant_autorise'=>$approvedAmount,'rappel'=>$reminderDate], JSON_UNESCAPED_UNICODE));
            $maj = $this->treasuryModel->trouverDemandeParId((int)$id);
            if ($maj['status'] === 'validee_comptable') {
                $this->notifierValidateursDemande($maj, 'direction_financiere');
                $this->notifierValidateursDemande($maj, 'direction_generale');
            }
            $this->notifierCreateurDemande($maj, 'Demande ' . $maj['request_number'] . ' : ' . $this->libelleTresorerie($maj['status']) . '.');
        }
        $messages = ['annule'=>'Demande annulée. Le motif est conservé.', 'en_suspens'=>'Demande mise en suspens. Le rappel et le motif sont enregistrés.', 'valide'=>'Validation enregistrée. Le montant autorisé est affiché sur la fiche.', 'reprendre'=>'Demande reprise au même niveau de validation.'];
        flashMessage($ok ? 'succes' : 'erreur', $ok ? $messages[$decision] : 'La décision n’a pas été enregistrée. Vérifiez le niveau, le montant autorisé, le motif et la date de rappel.');
        redirect('/treasury/demande-voir/' . (int)$id);
    }
    public function annulerDemande(?string $id): void {
        $this->verifierAccesTresorerie('operation');
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/treasury/demandes');
        }
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect('/treasury/demande-voir/' . (int)$id);
        }
        if (!$this->peutAnnulerTresorerie()) {
            flashMessage('erreur', 'Droits insuffisants pour annuler cette demande.');
            redirect('/treasury/demande-voir/' . (int)$id);
        }

        $motif = trim($_POST['motif'] ?? '');
        if ($motif === '') {
            flashMessage('erreur', 'Le motif d’annulation est obligatoire.');
            redirect('/treasury/demande-voir/' . (int)$id);
        }

        $ok = $this->treasuryModel->annulerDemandeFonds((int)$id, (int)($_SESSION['user_id'] ?? 0), $motif);
        if ($ok) {
            logAudit('Annulation demande de fonds #' . (int)$id, 'treasury_fund_requests', (int)$id, $motif);
        }
        $_SESSION['demande_swal'] = [
            'icon' => $ok ? 'success' : 'error',
            'title' => $ok ? 'Demande annulée' : 'Annulation impossible',
            'text' => $ok
                ? 'La demande est clôturée et le motif a été conservé dans sa traçabilité.'
                : 'La demande n’a pas pu être annulée.',
        ];
        flashMessage($ok ? 'succes' : 'erreur', $ok ? 'Demande annulee.' : 'Impossible d’annuler cette demande.');
        redirect('/treasury/demande-voir/' . (int)$id);
    }

    public function ordrePaiement(?string $id): void {
        $this->verifierAccesTresorerie();
        if (empty($id)) {
            redirect('/treasury/demandes');
        }

        $requestId = (int)$id;
        $demande = $this->treasuryModel->trouverDemandeParId($requestId);
        if (!$demande) {
            flashMessage('erreur', 'Demande de fonds introuvable.');
            redirect('/treasury/demandes');
        }

        $ordre = $this->treasuryModel->trouverOrdreParDemande($requestId);
        $comptes = array_values(array_filter($this->financeModel->listerComptes(), static function ($compte) use ($demande) {
            return $compte['devise'] === $demande['currency'];
        }));
        foreach ($comptes as &$compte) {
            $controle = $this->controleComptePaiement((int)$compte['id'], (int)($ordre['id'] ?? 0));
            $compte['solde_disponible'] -= $controle['engagements'];
            $compte['anomalie_devise'] = $controle['anomalie'];
            $compte['montant_requis'] = (float)($demande['approved_amount'] ?? $demande['amount']);
            $compte['solde_apres'] = (float)$compte['solde_disponible'] - (float)$compte['montant_requis'];
            $compte['couverture_suffisante'] = !$controle['anomalie'] && (float)$compte['solde_apres'] >= -0.00001;
        }
        unset($compte);
        $erreurs = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = 'Requête invalide.';
            } elseif ($ordre) {
                $erreurs[] = 'Un ordre de paiement existe deja pour cette demande.';
            } elseif (($demande['status'] ?? '') !== 'validee_direction') {
                $erreurs[] = 'L’ordre ne peut être emis que pour une demande validee par la direction.';
            } elseif (!$this->peutEmettreOrdre()) {
                $erreurs[] = 'Vous n’avez pas les droits pour emettre un ordre de paiement.';
            } elseif (!$this->respecteSeparationTachesDemande($demande)) {
                $erreurs[] = 'Separation des taches: le createur/soumetteur ne peut pas emettre l’ordre de paiement de sa propre demande.';
            } else {
                $donnees = [
                    'order_date' => $_POST['order_date'] ?? date('Y-m-d'),
                    'payment_method' => $_POST['payment_method'] ?? '',
                    'account_id' => (int)($_POST['account_id'] ?? 0),
                    'payment_reference' => trim($_POST['payment_reference'] ?? ''),
                    'instructions' => trim($_POST['instructions'] ?? ''),
                    'issued_by' => (int)($_SESSION['user_id'] ?? 0),
                ];

                if (!in_array($donnees['payment_method'], ['especes','banque','mobile_money'], true)) {
                    $erreurs[] = 'Mode de paiement invalide.';
                }
                if ($donnees['account_id'] <= 0) {
                    $erreurs[] = 'Le compte de paiement est obligatoire.';
                }
                $compteSelectionne = null;
                foreach ($comptes as $compteDisponible) {
                    if ((int)$compteDisponible['id'] === $donnees['account_id']) {
                        $compteSelectionne = $compteDisponible;
                        break;
                    }
                }
                if ($compteSelectionne === null) {
                    $erreurs[] = 'Le compte de paiement sélectionné est indisponible.';
                } else {
                    $typeCompteAttendu = [
                        'especes' => 'caisse',
                        'banque' => 'banque',
                        'mobile_money' => 'mobile_money',
                    ][$donnees['payment_method']] ?? null;
                    if ($typeCompteAttendu !== null && ($compteSelectionne['type_compte'] ?? '') !== $typeCompteAttendu) {
                        $erreurs[] = 'Le mode de paiement « ' . $this->libelleTresorerie($donnees['payment_method'])
                            . ' » n’est pas compatible avec le compte « ' . $compteSelectionne['nom'] . ' ».';
                    }
                }
                if ($compteSelectionne !== null && empty($compteSelectionne['couverture_suffisante'])) {
                    if (!empty($compteSelectionne['anomalie_devise'])) {
                        $erreurs[] = 'Ce compte contient une écriture dans une autre devise. Faites vérifier son solde avant d’émettre un ordre.';
                    } else {
                    $erreurs[] = 'Solde insuffisant sur le compte « ' . $compteSelectionne['nom'] . ' » : '
                        . formatMontant((float)$compteSelectionne['solde_disponible'], $compteSelectionne['devise'])
                        . ' disponible pour '
                        . formatMontant((float)$compteSelectionne['montant_requis'], $compteSelectionne['devise'])
                        . ' requis.';
                    }
                }

                if (empty($erreurs)) {
                    $orderId = $this->treasuryModel->emettreOrdrePaiement($requestId, $donnees);
                    if ($orderId > 0) {
                        logAudit('Emission ordre de paiement #' . $orderId . ' pour demande #' . $requestId, 'treasury_payment_orders', $orderId);
                        $_SESSION['ordre_swal'] = [
                            'icon' => 'success',
                            'title' => 'Ordre de paiement émis',
                            'text' => 'L’ordre a été créé et peut maintenant être exécuté par un profil autorisé.',
                        ];
                        flashMessage('succes', 'Ordre de paiement emis.');
                        redirect('/treasury/ordre-paiement/' . $requestId);
                    }
                    $erreurs[] = 'Impossible d’emettre cet ordre de paiement.';
                }
            }
        }

        $ordre = $this->treasuryModel->trouverOrdreParDemande($requestId);
        $ordreUi = $this->etatOperationnelOrdre($demande, $ordre ?: null);
        $this->render('ordre_voir', compact('demande', 'ordre', 'comptes', 'erreurs', 'ordreUi'));
    }

    public function executerPaiement(?string $id): void {
        $this->verifierAccesTresorerie('operation');
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/treasury/demandes');
        }
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect('/treasury/demandes');
        }

        $ordre = $this->treasuryModel->trouverOrdreParId((int)$id);
        if (!$ordre) {
            flashMessage('erreur', 'Ordre de paiement introuvable.');
            redirect('/treasury/demandes');
        }
        $retour = '/treasury/ordre-paiement/' . (int)($ordre['request_id'] ?? 0);
        if (!$this->peutExecuterPaiement()) {
            flashMessage('erreur', 'Droits insuffisants pour executer un paiement.');
            redirect($retour);
        }
        if (($ordre['status'] ?? '') !== 'emis' || !empty($ordre['payment_id'])) {
            flashMessage('erreur', 'Ce paiement est deja execute ou l’ordre n’est plus payable.');
            redirect($retour);
        }
        if (($ordre['request_status'] ?? '') !== 'ordre_emis') {
            flashMessage('erreur', 'La demande source n’est pas dans un statut payable.');
            redirect($retour);
        }
        if ((int)($ordre['issued_by'] ?? 0) === (int)($_SESSION['user_id'] ?? 0)) {
            flashMessage('erreur', 'Separation des taches: l’emetteur de l’ordre ne peut pas executer le paiement.');
            redirect($retour);
        }
        $comptePaiement = $this->financeModel->trouverCompteParId((int)$ordre['account_id']);
        $montantRequis = (float)$ordre['amount'];
        if (!$comptePaiement || $comptePaiement['devise'] !== $ordre['currency']) {
            flashMessage('erreur', 'Le compte de paiement doit utiliser la même devise que l’ordre. Faites corriger l’affectation avant le décaissement.');
            redirect($retour);
        }
        $controleCompte = $this->controleComptePaiement((int)$ordre['account_id'], (int)$ordre['id']);
        if ($controleCompte['anomalie']) {
            flashMessage('erreur', 'Le compte contient une écriture dans une autre devise. Faites vérifier son solde avant le décaissement.');
            redirect($retour);
        }
        $soldeDisponible = $this->financeModel->soldeCompteDisponible((int)$ordre['account_id']) - $controleCompte['engagements'];
        if (!$comptePaiement || $soldeDisponible + 0.00001 < $montantRequis) {
            $_SESSION['ordre_swal'] = [
                'icon' => 'error',
                'title' => 'Solde insuffisant',
                'text' => 'Le compte de paiement ne couvre plus le montant de cet ordre.',
            ];
            flashMessage('erreur', 'Solde insuffisant sur le compte de paiement.');
            redirect($retour);
        }

        $estPaiementCaisse = ($ordre['payment_method'] ?? '') === 'especes'
            || ($ordre['account_type'] ?? '') === 'caisse';
        $referencePaiement = trim($_POST['reference_payment'] ?? '');
        if ($referencePaiement === '' && $estPaiementCaisse) {
            $referencePaiement = 'CAISSE-' . ($ordre['order_number'] ?? ('OP-' . (int)$id));
        } elseif ($referencePaiement === '') {
            flashMessage('erreur', 'La référence de paiement est obligatoire.');
            redirect($retour);
        }

        $preuve = null;
        $preuveExterneObligatoire = !$estPaiementCaisse;
        if ($preuveExterneObligatoire && empty($_FILES['proof_file']['name'])) {
            flashMessage('erreur', 'La preuve externe est obligatoire pour ce mode de paiement.');
            redirect($retour);
        }
        if (!empty($_FILES['proof_file']['name'])) {
            $preuve = traiterUpload($_FILES['proof_file'], 'treasury');
            if ($preuve === false) {
                flashMessage('erreur', 'Preuve de paiement invalide.');
                redirect($retour);
            }
        }

        $donnees = [
            'payment_date' => $_POST['payment_date'] ?? date('Y-m-d'),
            'reference_payment' => $referencePaiement,
            'proof_file' => $preuve,
            'notes' => trim($_POST['notes'] ?? ''),
            'executed_by' => (int)($_SESSION['user_id'] ?? 0),
        ];
        $paymentId = $this->treasuryModel->executerPaiement((int)$id, $donnees);
        if ($paymentId > 0) {
            logAudit('Execution paiement tresorerie #' . $paymentId . ' ordre #' . (int)$id, 'treasury_payments', $paymentId);
            $_SESSION['ordre_swal'] = [
                'icon' => 'success',
                'title' => 'Paiement exécuté',
                'text' => 'La dépense a été comptabilisée. Le bordereau interne est disponible dans le dossier de l’ordre.',
            ];
            flashMessage('succes', 'Paiement execute et transaction financiere creee.');
        } else {
            $_SESSION['ordre_swal'] = [
                'icon' => 'error',
                'title' => 'Paiement non exécuté',
                'text' => 'Aucune dépense n’a été créée. Vérifiez l’état de l’ordre puis réessayez.',
            ];
            flashMessage('erreur', 'Impossible d’executer ce paiement.');
        }

        redirect($retour);
    }

    public function pdfBordereauPaiement(?string $id): void {
        $this->verifierAccesTresorerie();
        if (empty($id)) {
            http_response_code(404);
            exit('Paiement introuvable.');
        }
        $ordre = $this->treasuryModel->trouverOrdreParId((int)$id);
        if (!$ordre || empty($ordre['payment_id']) || !in_array($ordre['status'] ?? '', ['execute','annule'], true)) {
            http_response_code(404);
            exit('Le bordereau n’est disponible qu’après exécution du paiement.');
        }
        if (!class_exists('Dompdf\\Dompdf')) {
            http_response_code(503);
            exit('Le moteur PDF est indisponible.');
        }

        $logoPath = __DIR__ . '/../assets/images/logo/logo_amm.jpg';
        $logoDataUri = is_file($logoPath)
            ? 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($logoPath))
            : '';
        ob_start();
        require __DIR__ . '/../views/treasury/paiement_bordereau_pdf.php';
        $html = (string)ob_get_clean();

        $tempDir = null;
        foreach (array_unique(array_filter([trim((string)ini_get('sys_temp_dir')), sys_get_temp_dir(), rtrim(UPLOAD_DIR, '/\\'), '/Applications/XAMPP/xamppfiles/temp', '/tmp'])) as $base) {
            $candidate = rtrim($base, '/\\') . '/amm-erp-dompdf';
            if (!is_dir($candidate)) @mkdir($candidate, 0777, true);
            if (!is_dir($candidate)) continue;
            @chmod($candidate, 0777);
            $testFile = @tempnam($candidate, 'write-test-');
            if ($testFile !== false) {
                $ok = @file_put_contents($testFile, 'ok') !== false;
                @unlink($testFile);
                if ($ok) { $tempDir = $candidate; break; }
            }
        }
        if ($tempDir === null) {
            http_response_code(503);
            exit('Aucun répertoire temporaire utilisable par le moteur PDF.');
        }
        $options = new Dompdf\Options();
        $options->setTempDir($tempDir);
        $options->setFontCache($tempDir);
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(realpath(__DIR__ . '/..'));
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $download = isset($_GET['download']) && $_GET['download'] === '1';
        $dompdf->stream('bordereau-paiement-' . $ordre['order_number'] . '.pdf', ['Attachment' => $download]);
        exit;
    }

    public function annulerOrdrePaiement(?string $id): void {
        $this->verifierAccesTresorerie('operation');
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/treasury/demandes');
        }
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', 'Requête invalide.');
            redirect('/treasury/demandes');
        }
        if (!$this->peutAnnulerTresorerie()) {
            flashMessage('erreur', 'Droits insuffisants pour annuler cet ordre de paiement.');
            redirect('/treasury/demandes');
        }

        $ordre = $this->treasuryModel->trouverOrdreParId((int)$id);
        $retour = $ordre
            ? (!empty($_POST['retour_demande'])
                ? '/treasury/demande-voir/' . (int)$ordre['request_id']
                : '/treasury/ordre-paiement/' . (int)$ordre['request_id'])
            : '/treasury/demandes';
        $motif = trim($_POST['motif'] ?? '');
        if ($motif === '') {
            flashMessage('erreur', 'Le motif d’annulation est obligatoire.');
            redirect($retour);
        }

        $ok = $this->treasuryModel->annulerOrdrePaiement((int)$id, (int)($_SESSION['user_id'] ?? 0), $motif);
        if ($ok) {
            logAudit('Annulation ordre de paiement #' . (int)$id, 'treasury_payment_orders', (int)$id, $motif);
        }
        $_SESSION['demande_swal'] = [
            'icon' => $ok ? 'success' : 'error',
            'title' => $ok ? 'Ordre de paiement annulé' : 'Annulation impossible',
            'text' => $ok
                ? 'L’ordre ne peut plus être exécuté et le motif a été journalisé.'
                : 'L’ordre de paiement n’a pas pu être annulé.',
        ];
        flashMessage($ok ? 'succes' : 'erreur', $ok ? 'Ordre de paiement annule.' : 'Impossible d’annuler cet ordre de paiement.');
        redirect($retour);
    }

    public function comptesTresorerie(): void {
        $this->verifierAccesTresorerie();
        $type = in_array($_GET['type'] ?? '', ['caisse','banque','mobile_money','autre'], true) ? $_GET['type'] : '';
        $devise = in_array($_GET['devise'] ?? '', ['USD','CDF'], true) ? $_GET['devise'] : '';
        $recherche = trim((string)($_GET['recherche'] ?? ''));
        $alertesSeulement = ($_GET['alertes'] ?? '') === '1';
        $tauxCdfUsd = max(1, (float)$this->treasuryModel->getSetting('default_exchange_rate_cdf_usd', '2000'));
        $comptes = [];
        foreach ($this->treasuryModel->repertoireComptesTresorerie(true) as $compte) {
            $engagementDevise = ($compte['devise'] ?? 'USD') === 'CDF'
                ? (float)$compte['engagements_usd'] * $tauxCdfUsd
                : (float)$compte['engagements_usd'];
            $compte['engagements'] = $engagementDevise;
            $compte['disponible'] = (float)$compte['solde'] - $engagementDevise;
            $compte['status_operationnel'] = $this->statutOperationnelCompte($compte);
            $compte['actions_count'] = (int)($compte['rapprochements_en_attente'] ?? 0);
            if (($compte['type_compte'] ?? '') === 'caisse' && empty($compte['cash_status_today'])) {
                $compte['actions_count']++;
            }
            if (($compte['type_compte'] ?? '') === 'banque' && empty($compte['bank_status_latest'])) {
                $compte['actions_count']++;
            }
            if ((float)$compte['disponible'] < 0) {
                $compte['actions_count']++;
            }
            if ($type !== '' && $compte['type_compte'] !== $type) continue;
            if ($devise !== '' && $compte['devise'] !== $devise) continue;
            if ($recherche !== '' && stripos($compte['nom'] . ' ' . ($compte['reference_compte'] ?? ''), $recherche) === false) continue;
            if ($alertesSeulement && (int)$compte['actions_count'] === 0) continue;
            $comptes[] = $compte;
        }
        $tousComptes = $this->treasuryModel->repertoireComptesTresorerie(true);
        $resume = ['total'=>count($tousComptes),'liquidite_usd'=>0.0,'engagements_usd'=>0.0,'alertes'=>0];
        foreach ($tousComptes as $compte) {
            $soldeUsd = ($compte['devise'] ?? 'USD') === 'CDF' ? (float)$compte['solde'] / $tauxCdfUsd : (float)$compte['solde'];
            $disponibleUsd = $soldeUsd - (float)$compte['engagements_usd'];
            $resume['liquidite_usd'] += $soldeUsd;
            $resume['engagements_usd'] += (float)$compte['engagements_usd'];
            if (
                $disponibleUsd < 0
                || (int)($compte['rapprochements_en_attente'] ?? 0) > 0
                || (($compte['type_compte'] ?? '') === 'caisse' && empty($compte['cash_status_today']))
                || (($compte['type_compte'] ?? '') === 'banque' && empty($compte['bank_status_latest']))
            ) {
                $resume['alertes']++;
            }
        }
        $resume['disponible_usd'] = $resume['liquidite_usd'] - $resume['engagements_usd'];
        $filtres = compact('type', 'devise', 'recherche', 'alertesSeulement');
        $this->render('comptes', compact('comptes', 'resume', 'filtres'));
    }

    public function voirCompteTresorerie(?string $id): void {
        $this->verifierAccesTresorerie();
        $accountId = (int)$id;
        $compte = $accountId > 0 ? $this->financeModel->trouverCompteParId($accountId) : null;
        if (!$compte) {
            flashMessage('erreur', 'Compte de trésorerie introuvable.');
            redirect('/treasury/comptes');
        }
        if ($compte['type_compte'] === 'caisse') {
            $_GET['cash_account_id'] = $accountId;
            $this->caisse();
            return;
        }

        $dateDebut = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_debut'] ?? '') ? $_GET['date_debut'] : date('Y-m-01');
        $dateFin = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_fin'] ?? '') ? $_GET['date_fin'] : date('Y-m-d');
        $repertoire = $this->treasuryModel->repertoireComptesTresorerie(false);
        $resumeCompte = null;
        foreach ($repertoire as $ligne) {
            if ((int)$ligne['id'] === $accountId) {
                $resumeCompte = $ligne;
                break;
            }
        }
        if (!$resumeCompte) {
            $resumeCompte = $compte + ['solde'=>0,'entrees_mois'=>0,'sorties_mois'=>0,'engagements_usd'=>0,'rapprochements_en_attente'=>0];
        }
        $tauxCdfUsd = max(1, (float)$this->treasuryModel->getSetting('default_exchange_rate_cdf_usd', '2000'));
        $engagements = $compte['devise'] === 'CDF' ? (float)$resumeCompte['engagements_usd'] * $tauxCdfUsd : (float)$resumeCompte['engagements_usd'];
        $resumeCompte['engagements'] = $engagements;
        $resumeCompte['disponible'] = (float)$resumeCompte['solde'] - $engagements;
        $resumeCompte['status_operationnel'] = $this->statutOperationnelCompte($resumeCompte);
        $mouvements = $this->treasuryModel->mouvementsCompteTresorerie($accountId, $dateDebut, $dateFin);
        $releves = $compte['type_compte'] === 'banque'
            ? $this->treasuryModel->listerRelevesBancaires(['account_id'=>$accountId], 24, 0)
            : [];
        $filtres = ['date_debut'=>$dateDebut,'date_fin'=>$dateFin];
        $this->render('compte_voir', compact('compte', 'resumeCompte', 'mouvements', 'releves', 'filtres'));
    }

    public function caisse(): void {
        $this->verifierAccesTresorerie();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                flashMessage('erreur', 'Requête invalide.');
                redirect('/treasury/caisse');
            }

            $this->verifierAccesTresorerie('operation');
            $action = $_POST['action'] ?? '';
            $cashAccountId = (int)($_POST['cash_account_id'] ?? 0);
            $date = $_POST['session_date'] ?? date('Y-m-d');

            if ($cashAccountId <= 0) {
                flashMessage('erreur', 'Compte caisse obligatoire.');
                redirect('/treasury/caisse');
            }

            $sessionExistante = $this->treasuryModel->trouverSessionCaisse($cashAccountId, $date);
            if ($sessionExistante
                && in_array($sessionExistante['status'], ['cloturee','controlee','verrouillee'], true)
                && !$this->peutModifierJourneeCloturee()) {
                flashMessage('erreur', 'Journee de caisse cloturee: modification reservee a admin/direction.');
                redirect('/treasury/caisse?cash_account_id=' . $cashAccountId . '&date_debut=' . $date . '&date_fin=' . $date);
            }

            if ($action === 'ouvrir') {
                $sessionId = $this->treasuryModel->ouvrirSessionCaisse($cashAccountId, $date, (int)($_SESSION['user_id'] ?? 0));
                $this->treasuryModel->recalculerSessionCaisse($cashAccountId, $date);
                logAudit('Ouverture session de caisse #' . $sessionId, 'treasury_cash_sessions', $sessionId);
                flashMessage('succes', 'Journee de caisse ouverte.');
            } elseif ($action === 'recalculer') {
                $ok = $this->treasuryModel->recalculerSessionCaisse($cashAccountId, $date);
                logAudit('Recalcul session de caisse ' . $date . ' compte #' . $cashAccountId, 'treasury_cash_sessions', (int)($sessionExistante['id'] ?? 0));
                flashMessage($ok ? 'succes' : 'erreur', $ok ? 'Livre de caisse recalcule.' : 'Impossible de recalculer cette journee.');
            }

            redirect('/treasury/caisse?cash_account_id=' . $cashAccountId . '&date_debut=' . $date . '&date_fin=' . $date);
        }

        $filtres = [
            'cash_account_id' => (int)($_GET['cash_account_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
            'date_debut' => $_GET['date_debut'] ?? date('Y-m-01'),
            'date_fin' => $_GET['date_fin'] ?? date('Y-m-d'),
        ];
        $sessions = $this->treasuryModel->listerSessionsCaisse($filtres, 60, 0);
        $comptes = $this->comptesParType('caisse');
        $stats = $this->treasuryModel->statistiquesDashboard();
        $dateSelectionnee = $filtres['date_fin'] ?: date('Y-m-d');
        $compteSelectionne = $filtres['cash_account_id'] ?: (int)($comptes[0]['id'] ?? 0);
        $sessionCourante = $compteSelectionne > 0 ? $this->treasuryModel->trouverSessionCaisse($compteSelectionne, $dateSelectionnee) : null;
        $mouvements = $compteSelectionne > 0
            ? $this->treasuryModel->mouvementsCaissePeriode($compteSelectionne, $filtres['date_debut'], $filtres['date_fin'])
            : [];
        $soldeInitialMouvements = $compteSelectionne > 0
            ? $this->treasuryModel->soldeCaisseAvantDate($compteSelectionne, $filtres['date_debut'])
            : 0.0;
        $plafondCaisseUsd = $this->treasuryModel->plafondCaisseUsd();
        $peutModifierCloturee = $this->peutModifierJourneeCloturee();
        $peutControlerCaisse = $this->peutValiderNiveau('comptable');
        $peutOpererTresorerie = $this->peutOpererTresorerie();

        $this->render('caisse', compact(
            'filtres',
            'sessions',
            'comptes',
            'stats',
            'dateSelectionnee',
            'compteSelectionne',
            'sessionCourante',
            'mouvements',
            'soldeInitialMouvements',
            'plafondCaisseUsd',
            'peutModifierCloturee',
            'peutControlerCaisse',
            'peutOpererTresorerie'
        ));
    }

    public function cloturerCaisse(): void {
        $this->verifierAccesTresorerie('operation');
        $cashFeedback = static function (string $icon, string $title, string $text): void {
            $_SESSION['cash_swal'] = compact('icon', 'title', 'text');
        };
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/treasury/caisse');
        }
        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            $cashFeedback('error', 'Action refusée', 'La requête a expiré. Veuillez reprendre l’opération.');
            redirect('/treasury/caisse');
        }

        $action = $_POST['action'] ?? 'cloturer';
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $commentaire = trim($_POST['commentaire'] ?? '');

        if ($sessionId <= 0) {
            $cashFeedback('error', 'Session invalide', 'La journée de caisse sélectionnée ne peut pas être traitée.');
            redirect('/treasury/caisse');
        }

        $session = $this->treasuryModel->trouverSessionCaisseParId($sessionId);
        if (!$session) {
            $cashFeedback('error', 'Session introuvable', 'La journée de caisse demandée n’existe plus ou n’est pas accessible.');
            redirect('/treasury/caisse');
        }

        $retour = '/treasury/caisse?cash_account_id=' . (int)$session['cash_account_id']
            . '&date_debut=' . $session['session_date']
            . '&date_fin=' . $session['session_date'];
        if (($_POST['return_to'] ?? '') === 'compte') {
            $retour = '/treasury/compte/' . (int)$session['cash_account_id']
                . '?date_debut=' . $session['session_date']
                . '&date_fin=' . $session['session_date'];
        }

        if ($action === 'controler') {
            if (!$this->peutValiderNiveau('comptable')) {
                $cashFeedback('error', 'Contrôle non autorisé', 'Cette validation est réservée au comptable senior ou à un profil habilité.');
                redirect($retour);
            }
            $ok = $this->treasuryModel->controlerSessionCaisse($sessionId, (int)($_SESSION['user_id'] ?? 0), $commentaire);
            logAudit('Controle session de caisse #' . $sessionId, 'treasury_cash_sessions', $sessionId);
            $cashFeedback(
                $ok ? 'success' : 'error',
                $ok ? 'Contrôle validé' : 'Contrôle impossible',
                $ok ? 'La journée de caisse a été contrôlée et sécurisée.' : 'La session ne peut pas être contrôlée dans son état actuel.'
            );
            redirect($retour);
        }

        if (in_array($session['status'], ['cloturee','controlee','verrouillee'], true) && !$this->peutModifierJourneeCloturee()) {
            $cashFeedback('error', 'Modification non autorisée', 'Cette journée est déjà clôturée. Sa correction est réservée à la Direction ou à l’administrateur.');
            redirect($retour);
        }

        if (($_POST['confirm_closure'] ?? '') !== '1') {
            $cashFeedback('warning', 'Confirmation requise', 'Confirmez le comptage physique avant de clôturer la journée.');
            redirect($retour);
        }

        $physicalBalanceRaw = trim((string)($_POST['physical_balance'] ?? ''));
        if ($physicalBalanceRaw === '' || !is_numeric($physicalBalanceRaw) || (float)$physicalBalanceRaw < 0) {
            $cashFeedback('error', 'Comptage invalide', 'Le montant physique compté doit être un nombre valide et positif.');
            redirect($retour);
        }

        $physicalBalance = (float)$physicalBalanceRaw;
        $theoreticalBalance = (float)($session['theoretical_balance'] ?? 0);
        if (abs($physicalBalance - $theoreticalBalance) > 0.001 && $commentaire === '') {
            $cashFeedback('warning', 'Justification requise', 'Ajoutez un commentaire pour expliquer l’écart de caisse constaté.');
            redirect($retour);
        }

        $ok = $this->treasuryModel->cloturerSessionCaisse($sessionId, $physicalBalance, (int)($_SESSION['user_id'] ?? 0), $commentaire);
        logAudit('Cloture session de caisse #' . $sessionId, 'treasury_cash_sessions', $sessionId);
        $cashFeedback(
            $ok ? 'success' : 'error',
            $ok ? 'Journée clôturée' : 'Clôture impossible',
            $ok ? 'Le comptage a été enregistré et la journée transmise au contrôle comptable.' : 'La journée ne peut pas être clôturée dans son état actuel.'
        );
        redirect($retour);
    }

    /** Génère le livre de caisse officiel avec synthèse exécutive et journal détaillé. */
    public function pdfLivreCaisse(): void {
        $this->verifierAccesTresorerie();

        $cashAccountId = (int)($_GET['cash_account_id'] ?? 0);
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d');
        $dateFin = $_GET['date_fin'] ?? $dateDebut;
        if ($cashAccountId <= 0 || !strtotime($dateDebut) || !strtotime($dateFin) || $dateDebut > $dateFin) {
            http_response_code(422);
            exit('Paramètres du livre de caisse invalides.');
        }

        $compte = null;
        foreach ($this->comptesParType('caisse') as $compteCaisse) {
            if ((int)$compteCaisse['id'] === $cashAccountId) {
                $compte = $compteCaisse;
                break;
            }
        }
        if (!$compte) {
            http_response_code(404);
            exit('Compte de caisse introuvable.');
        }
        if (!class_exists('Dompdf\\Dompdf')) {
            http_response_code(503);
            exit('Le moteur PDF est indisponible.');
        }

        $mouvements = $this->treasuryModel->mouvementsCaissePeriode($cashAccountId, $dateDebut, $dateFin);
        $soldeOuverture = $this->treasuryModel->soldeCaisseAvantDate($cashAccountId, $dateDebut);
        $soldeProgressif = $soldeOuverture;
        $totalEntrees = 0.0;
        $totalSorties = 0.0;
        foreach ($mouvements as &$mouvement) {
            $montant = (float)$mouvement['amount'];
            if (($mouvement['sens'] ?? '') === 'entree') {
                $totalEntrees += $montant;
                $soldeProgressif += $montant;
            } else {
                $totalSorties += $montant;
                $soldeProgressif -= $montant;
            }
            $mouvement['running_balance'] = $soldeProgressif;
        }
        unset($mouvement);

        $soldeTheorique = $soldeOuverture + $totalEntrees - $totalSorties;
        $session = $dateDebut === $dateFin
            ? $this->treasuryModel->trouverSessionCaisse($cashAccountId, $dateFin)
            : null;
        $devise = $compte['devise'] ?? 'USD';
        $plafondCaisseUsd = $this->treasuryModel->plafondCaisseUsd();
        $statut = $session['status'] ?? 'non_ouverte';
        $montantPhysique = $session && $session['physical_balance'] !== null
            ? (float)$session['physical_balance']
            : null;
        $ecart = $montantPhysique !== null ? $montantPhysique - $soldeTheorique : null;

        $logoPath = __DIR__ . '/../assets/images/logo/logo_amm.jpg';
        $logoDataUri = is_file($logoPath)
            ? 'data:image/jpeg;base64,' . base64_encode((string)file_get_contents($logoPath))
            : '';

        ob_start();
        require __DIR__ . '/../views/treasury/caisse_pdf.php';
        $html = (string)ob_get_clean();

        $tempDir = null;
        foreach (array_unique(array_filter([
            trim((string)ini_get('sys_temp_dir')),
            sys_get_temp_dir(),
            rtrim(UPLOAD_DIR, '/\\'),
            '/Applications/XAMPP/xamppfiles/temp',
            '/tmp',
        ])) as $base) {
            $candidate = rtrim($base, '/\\') . '/amm-erp-dompdf';
            if (!is_dir($candidate)) {
                @mkdir($candidate, 0777, true);
            }
            if (!is_dir($candidate)) {
                continue;
            }
            @chmod($candidate, 0777);
            $testFile = @tempnam($candidate, 'write-test-');
            if ($testFile !== false) {
                $testOk = @file_put_contents($testFile, 'ok') !== false;
                @unlink($testFile);
                if ($testOk) {
                    $tempDir = $candidate;
                    break;
                }
            }
        }
        if ($tempDir === null) {
            http_response_code(503);
            exit('Aucun répertoire temporaire utilisable par le moteur PDF.');
        }

        $options = new Dompdf\Options();
        $options->setTempDir($tempDir);
        $options->setFontCache($tempDir);
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot(realpath(__DIR__ . '/..'));

        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $nomCompte = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$compte['nom']);
        $nomFichier = 'livre-caisse-' . ($nomCompte ?: $cashAccountId) . '-' . $dateDebut;
        if ($dateFin !== $dateDebut) {
            $nomFichier .= '-au-' . $dateFin;
        }
        $telecharger = isset($_GET['download']) && $_GET['download'] === '1';
        $dompdf->stream($nomFichier . '.pdf', ['Attachment' => $telecharger]);
        exit;
    }

    /** Exporte un classeur Excel de pilotage du livre de caisse pour la Direction financière. */
    public function excelLivreCaisse(): void {
        $this->verifierAccesTresorerie();

        $cashAccountId = (int)($_GET['cash_account_id'] ?? 0);
        $dateDebut = $_GET['date_debut'] ?? date('Y-m-d');
        $dateFin = $_GET['date_fin'] ?? $dateDebut;
        if ($cashAccountId <= 0 || !strtotime($dateDebut) || !strtotime($dateFin) || $dateDebut > $dateFin) {
            http_response_code(422);
            exit('Paramètres du livre de caisse invalides.');
        }
        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            http_response_code(503);
            exit('Le moteur Excel est indisponible.');
        }

        $compte = null;
        foreach ($this->comptesParType('caisse') as $compteCaisse) {
            if ((int)$compteCaisse['id'] === $cashAccountId) {
                $compte = $compteCaisse;
                break;
            }
        }
        if (!$compte) {
            http_response_code(404);
            exit('Compte de caisse introuvable.');
        }

        $mouvements = $this->treasuryModel->mouvementsCaissePeriode($cashAccountId, $dateDebut, $dateFin);
        $soldeOuverture = $this->treasuryModel->soldeCaisseAvantDate($cashAccountId, $dateDebut);
        $session = $dateDebut === $dateFin
            ? $this->treasuryModel->trouverSessionCaisse($cashAccountId, $dateFin)
            : null;
        $devise = $compte['devise'] ?? 'USD';
        $plafondCaisseUsd = $this->treasuryModel->plafondCaisseUsd();
        $montantPhysique = $session && $session['physical_balance'] !== null
            ? (float)$session['physical_balance']
            : null;

        $classeur = new PhpOffice\PhpSpreadsheet\Spreadsheet();
        $classeur->getProperties()
            ->setCreator(APP_NAME)
            ->setCompany('Anticipation des Matériels Modernes')
            ->setTitle('Livre de caisse - Vision CFO')
            ->setSubject('Pilotage et contrôle de la caisse')
            ->setDescription('Synthèse CFO, journal détaillé et contrôles du livre de caisse.');

        $summary = $classeur->getActiveSheet();
        $summary->setTitle('Synthèse CFO');
        $journal = $classeur->createSheet();
        $journal->setTitle('Journal de caisse');
        $checks = $classeur->createSheet();
        $checks->setTitle('Contrôles');

        $navy = '0B2847';
        $blue = '1769E0';
        $green = '16834B';
        $red = 'B12634';
        $lightBlue = 'EAF2FB';
        $lightGray = 'F4F7FA';
        $border = 'D9E2EC';
        $white = 'FFFFFF';
        $text = '17283E';
        $moneyFormat = '#,##0.00 "' . $devise . '";[Red](#,##0.00) "' . $devise . '";-';

        // ---------------- Synthèse CFO ----------------
        $summary->setShowGridlines(false);
        $summary->getSheetView()->setZoomScale(85);
        $summary->mergeCells('A1:L1');
        $summary->setCellValue('A1', 'AMM · LIVRE DE CAISSE — SYNTHÈSE CFO');
        $summary->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
            'alignment' => ['horizontal' => 'left', 'vertical' => 'center'],
        ]);
        $summary->getRowDimension(1)->setRowHeight(34);
        $summary->mergeCells('A2:L2');
        $summary->setCellValue('A2', $compte['nom'] . ' · ' . formatDate($dateDebut) . ($dateFin !== $dateDebut ? ' au ' . formatDate($dateFin) : '') . ' · Devise : ' . $devise);
        $summary->getStyle('A2:L2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['rgb' => '53677D']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EDF3F8']],
        ]);
        $summary->mergeCells('A4:L4');
        $summary->setCellValue('A4', 'VUE EXÉCUTIVE DES LIQUIDITÉS');
        $summary->getStyle('A4:L4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $blue]],
        ]);

        $summary->mergeCells('A6:B6')->mergeCells('C6:D6')->mergeCells('E6:F6')
            ->mergeCells('G6:H6')->mergeCells('I6:J6')->mergeCells('K6:L6');
        $summary->mergeCells('A7:B8')->mergeCells('C7:D8')->mergeCells('E7:F8')
            ->mergeCells('G7:H8')->mergeCells('I7:J8')->mergeCells('K7:L8');
        $summary->setCellValue('A6', 'SOLDE D’OUVERTURE');
        $summary->setCellValue('C6', 'TOTAL ENTRÉES');
        $summary->setCellValue('E6', 'TOTAL SORTIES');
        $summary->setCellValue('G6', 'SOLDE THÉORIQUE');
        $summary->setCellValue('I6', 'SOLDE PHYSIQUE');
        $summary->setCellValue('K6', 'ÉCART DE CAISSE');
        $summary->setCellValue('A7', '=\'Journal de caisse\'!H8');
        $lastJournalRow = max(8, 8 + count($mouvements));
        $summary->setCellValue('C7', '=SUM(\'Journal de caisse\'!F9:F' . $lastJournalRow . ')');
        $summary->setCellValue('E7', '=SUM(\'Journal de caisse\'!G9:G' . $lastJournalRow . ')');
        $summary->setCellValue('G7', '=A7+C7-E7');
        $summary->setCellValue('I7', $montantPhysique);
        $summary->setCellValue('K7', '=IF(I7="","",I7-G7)');
        $summary->getStyle('A6:L6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '63758A']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $lightGray]],
            'alignment' => ['horizontal' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => $border]]],
        ]);
        $summary->getStyle('A7:L8')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => $text]],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
            'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => $border]]],
        ]);
        $summary->getStyle('G7:H8')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
        ]);
        $summary->getStyle('A7:L8')->getNumberFormat()->setFormatCode($moneyFormat);
        $summary->getRowDimension(7)->setRowHeight(30);

        $summary->mergeCells('A10:L10');
        $summary->setCellValue('A10', 'CADRE DE CONTRÔLE ET GOUVERNANCE');
        $summary->getStyle('A10:L10')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
        ]);
        $controlValues = [
            ['A11', 'Statut de la journée', 'A12', strtoupper(str_replace('_', ' ', $session['status'] ?? 'non_ouverte'))],
            ['C11', 'Plafond autorisé', 'C12', $plafondCaisseUsd],
            ['E11', 'Clôturé par', 'E12', $session['closed_by_nom'] ?? '—'],
            ['G11', 'Contrôlé par', 'G12', $session['checked_by_nom'] ?? '—'],
            ['I11', 'Nombre de mouvements', 'I12', count($mouvements)],
            ['K11', 'Dernière actualisation', 'K12', date('d/m/Y H:i')],
        ];
        foreach ($controlValues as $control) {
            $summary->mergeCells($control[0] . ':' . chr(ord($control[0][0]) + 1) . substr($control[0], 1));
            $summary->mergeCells($control[2] . ':' . chr(ord($control[2][0]) + 1) . substr($control[2], 1));
            $summary->setCellValue($control[0], $control[1]);
            $summary->setCellValue($control[2], $control[3]);
        }
        $summary->getStyle('A11:L11')->applyFromArray([
            'font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '63758A']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $lightGray]],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $summary->getStyle('A12:L12')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => ['horizontal' => 'center'],
            'borders' => ['bottom' => ['borderStyle' => 'thin', 'color' => ['rgb' => $border]]],
        ]);
        $summary->getStyle('C12:D12')->getNumberFormat()->setFormatCode('$#,##0.00;[Red]($#,##0.00);-');

        $daily = [];
        foreach ($mouvements as $mouvement) {
            $date = $mouvement['operation_date'];
            if (!isset($daily[$date])) {
                $daily[$date] = ['entrees' => 0.0, 'sorties' => 0.0];
            }
            $daily[$date][$mouvement['sens'] === 'entree' ? 'entrees' : 'sorties'] += (float)$mouvement['amount'];
        }
        if (empty($daily)) {
            $daily[$dateDebut] = ['entrees' => 0.0, 'sorties' => 0.0];
        }
        $summary->fromArray([['Date', 'Entrées', 'Sorties']], null, 'A16');
        $dailyRow = 17;
        foreach ($daily as $date => $flux) {
            $summary->setCellValue('A' . $dailyRow, PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime($date . ' 12:00:00')));
            $summary->setCellValue('B' . $dailyRow, $flux['entrees']);
            $summary->setCellValue('C' . $dailyRow, $flux['sorties']);
            $dailyRow++;
        }
        $summary->getStyle('A16:C16')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
        ]);
        $summary->getStyle('A17:A' . ($dailyRow - 1))->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $summary->getStyle('B17:C' . ($dailyRow - 1))->getNumberFormat()->setFormatCode($moneyFormat);

        $labels = [new PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', '\'Synthèse CFO\'!$B$16:$C$16', null, 2)];
        $categories = [new PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('String', '\'Synthèse CFO\'!$A$17:$A$' . ($dailyRow - 1), null, $dailyRow - 17)];
        $values = [
            new PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('Number', '\'Synthèse CFO\'!$B$17:$B$' . ($dailyRow - 1), null, $dailyRow - 17),
            new PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues('Number', '\'Synthèse CFO\'!$C$17:$C$' . ($dailyRow - 1), null, $dailyRow - 17),
        ];
        $series = new PhpOffice\PhpSpreadsheet\Chart\DataSeries(
            PhpOffice\PhpSpreadsheet\Chart\DataSeries::TYPE_BARCHART,
            PhpOffice\PhpSpreadsheet\Chart\DataSeries::GROUPING_CLUSTERED,
            range(0, count($values) - 1),
            $labels,
            $categories,
            $values
        );
        $series->setPlotDirection(PhpOffice\PhpSpreadsheet\Chart\DataSeries::DIRECTION_COLUMN);
        $plotArea = new PhpOffice\PhpSpreadsheet\Chart\PlotArea(null, [$series]);
        $legend = new PhpOffice\PhpSpreadsheet\Chart\Legend(PhpOffice\PhpSpreadsheet\Chart\Legend::POSITION_BOTTOM, null, false);
        $chart = new PhpOffice\PhpSpreadsheet\Chart\Chart(
            'cashFlowChart',
            new PhpOffice\PhpSpreadsheet\Chart\Title('Flux de caisse sur la période'),
            $legend,
            $plotArea,
            true,
            0,
            null,
            new PhpOffice\PhpSpreadsheet\Chart\Title('Montants (' . $devise . ')')
        );
        $chart->setTopLeftPosition('E15');
        $chart->setBottomRightPosition('L29');
        $summary->addChart($chart);

        foreach (range('A', 'L') as $column) {
            $summary->getColumnDimension($column)->setWidth(13);
        }
        $summary->getPageSetup()->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $summary->getPageSetup()->setFitToWidth(1)->setFitToHeight(1);
        $summary->getPageMargins()->setTop(0.4)->setRight(0.4)->setBottom(0.4)->setLeft(0.4);

        // ---------------- Journal détaillé ----------------
        $journal->setShowGridlines(false);
        $journal->freezePane('A8');
        $journal->mergeCells('A1:H1');
        $journal->setCellValue('A1', 'AMM · JOURNAL DÉTAILLÉ DE CAISSE');
        $journal->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
        ]);
        $journal->mergeCells('A2:H2');
        $journal->setCellValue('A2', $compte['nom'] . ' · ' . formatDate($dateDebut) . ($dateFin !== $dateDebut ? ' au ' . formatDate($dateFin) : ''));
        $journal->setCellValue('A4', 'Convention');
        $journal->mergeCells('B4:H4');
        $journal->setCellValue('B4', 'Les montants sont exprimés en ' . $devise . '. Les entrées sont positives, les sorties sont présentées dans une colonne distincte.');
        $journal->getStyle('A4:H4')->applyFromArray([
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $lightBlue]],
            'font' => ['italic' => true, 'color' => ['rgb' => '4C6380']],
        ]);
        $journal->fromArray([['Date', 'Référence', 'Nature', 'Tiers', 'Libellé', 'Entrée', 'Sortie', 'Solde progressif']], null, 'A7');
        $journal->getStyle('A7:H7')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $journal->setCellValue('A8', PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime($dateDebut . ' 12:00:00')));
        $journal->setCellValue('B8', 'REPORT');
        $journal->setCellValue('C8', 'Ouverture');
        $journal->setCellValue('E8', 'Solde reporté à l’ouverture');
        $journal->setCellValue('H8', $soldeOuverture);
        $row = 9;
        foreach ($mouvements as $mouvement) {
            $journal->setCellValue('A' . $row, PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new DateTime($mouvement['operation_date'] . ' 12:00:00')));
            $journal->setCellValue('B' . $row, $mouvement['reference_operation']);
            $journal->setCellValue('C' . $row, $mouvement['sens'] === 'entree' ? 'Encaissement' : 'Décaissement');
            $journal->setCellValue('D' . $row, $mouvement['partenaire']);
            $journal->setCellValue('E' . $row, $mouvement['libelle']);
            $journal->setCellValue('F' . $row, $mouvement['sens'] === 'entree' ? (float)$mouvement['amount'] : 0);
            $journal->setCellValue('G' . $row, $mouvement['sens'] === 'sortie' ? (float)$mouvement['amount'] : 0);
            $journal->setCellValue('H' . $row, '=H' . ($row - 1) . '+F' . $row . '-G' . $row);
            $row++;
        }
        $lastDataRow = max(8, $row - 1);
        $totalRow = $lastDataRow + 2;
        $journal->mergeCells('A' . $totalRow . ':E' . $totalRow);
        $journal->setCellValue('A' . $totalRow, 'TOTAUX ET SOLDE DE CLÔTURE');
        $journal->setCellValue('F' . $totalRow, '=SUM(F9:F' . $lastDataRow . ')');
        $journal->setCellValue('G' . $totalRow, '=SUM(G9:G' . $lastDataRow . ')');
        $journal->setCellValue('H' . $totalRow, '=H' . $lastDataRow);
        $journal->getStyle('A' . $totalRow . ':H' . $totalRow)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $lightBlue]],
            'borders' => ['top' => ['borderStyle' => 'medium', 'color' => ['rgb' => $navy]]],
        ]);
        $journal->getStyle('A8:A' . $lastDataRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $journal->getStyle('F8:H' . $totalRow)->getNumberFormat()->setFormatCode($moneyFormat);
        $journal->getStyle('A8:H' . $lastDataRow)->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => 'hair', 'color' => ['rgb' => $border]]],
            'alignment' => ['vertical' => 'center'],
        ]);
        for ($r = 9; $r <= $lastDataRow; $r += 2) {
            $journal->getStyle('A' . $r . ':H' . $r)->getFill()->setFillType('solid')->getStartColor()->setRGB('F8FAFC');
        }
        $journal->getStyle('F8:F' . $totalRow)->getFont()->getColor()->setRGB($green);
        $journal->getStyle('G8:G' . $totalRow)->getFont()->getColor()->setRGB($red);
        $journal->getStyle('H9:H' . $lastDataRow)->getFont()->getColor()->setRGB('000000');
        $journal->getStyle('H9:H' . $lastDataRow)->getFont()->setBold(true);
        $journal->setAutoFilter('A7:H' . $lastDataRow);
        $widths = ['A' => 13, 'B' => 21, 'C' => 16, 'D' => 24, 'E' => 42, 'F' => 17, 'G' => 17, 'H' => 19];
        foreach ($widths as $column => $width) {
            $journal->getColumnDimension($column)->setWidth($width);
        }
        $journal->getStyle('D8:E' . $lastDataRow)->getAlignment()->setWrapText(true);
        $journal->getPageSetup()->setOrientation(PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $journal->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(7, 7);
        $journal->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $journal->getPageMargins()->setTop(0.45)->setRight(0.35)->setBottom(0.45)->setLeft(0.35);
        $journal->getHeaderFooter()->setOddFooter('&LAMM-ERP · Livre de caisse&CPage &P / &N&R' . date('d/m/Y H:i'));

        // ---------------- Contrôles ----------------
        $checks->setShowGridlines(false);
        $checks->mergeCells('A1:G1');
        $checks->setCellValue('A1', 'AMM · MATRICE DE CONTRÔLE CFO');
        $checks->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $navy]],
        ]);
        $checks->fromArray([['Contrôle', 'Valeur constatée', 'Valeur attendue', 'Écart', 'Tolérance', 'Statut', 'Observation']], null, 'A4');
        $checks->getStyle('A4:G4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $white]],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $blue]],
        ]);
        $checks->fromArray([
            ['Équation de caisse', '=\'Synthèse CFO\'!G7', '=\'Synthèse CFO\'!A7+\'Synthèse CFO\'!C7-\'Synthèse CFO\'!E7', '=B5-C5', 0.01, '=IF(ABS(D5)<=E5,"OK","ALERTE")', 'Ouverture + entrées - sorties = solde théorique'],
            ['Écart physique', '=\'Synthèse CFO\'!I7', '=\'Synthèse CFO\'!G7', '=IF(B6="","",B6-C6)', 0.01, '=IF(B6="","À COMPTER",IF(ABS(D6)<=E6,"OK","ALERTE"))', 'Comparer le comptage physique au solde théorique'],
            ['Plafond de caisse', '=\'Synthèse CFO\'!G7', $plafondCaisseUsd, '=B7-C7', 0, '=IF(B7<=C7,"OK","DÉPASSEMENT")', 'Plafond fixé par la Direction'],
            ['Traçabilité clôture', $session['closed_by_nom'] ?? '', 'Responsable identifié', '', '', '=IF(B8="","À COMPLÉTER","OK")', 'Vérifier le responsable de clôture'],
            ['Contrôle comptable', $session['checked_by_nom'] ?? '', 'Comptable identifié', '', '', '=IF(B9="","À COMPLÉTER","OK")', 'Vérifier le visa du Comptable senior'],
        ], null, 'A5');
        $checks->mergeCells('A12:E12');
        $checks->setCellValue('A12', 'STATUT GLOBAL DU LIVRE');
        $checks->setCellValue('F12', '=IF(COUNTIF(F5:F9,"ALERTE")+COUNTIF(F5:F9,"DÉPASSEMENT")>0,"ATTENTION REQUISE",IF(COUNTIF(F5:F9,"À COMPLÉTER")+COUNTIF(F5:F9,"À COMPTER")>0,"CONTRÔLES EN ATTENTE","CONFORME"))');
        $checks->mergeCells('F12:G12');
        $checks->getStyle('A12:G12')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $lightBlue]],
            'borders' => ['top' => ['borderStyle' => 'medium', 'color' => ['rgb' => $navy]]],
        ]);
        $checks->getStyle('B5:E7')->getNumberFormat()->setFormatCode($moneyFormat);
        $checks->getStyle('A5:G9')->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => 'thin', 'color' => ['rgb' => $border]]],
            'alignment' => ['vertical' => 'center', 'wrapText' => true],
        ]);
        $checks->getStyle('F5:F9')->getFont()->setBold(true);
        $checks->getStyle('F5:F9')->getAlignment()->setHorizontal('center');
        $checks->getStyle('F5:F9')->getConditionalStyles();
        $okRule = new PhpOffice\PhpSpreadsheet\Style\Conditional();
        $okRule->setConditionType(PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CONTAINSTEXT)
            ->setOperatorType(PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_CONTAINSTEXT)
            ->setText('OK');
        $okRule->getStyle()->getFill()->setFillType('solid')->getStartColor()->setRGB('DFF3E8');
        $okRule->getStyle()->getFont()->getColor()->setRGB($green);
        $alertRule = new PhpOffice\PhpSpreadsheet\Style\Conditional();
        $alertRule->setConditionType(PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CONTAINSTEXT)
            ->setOperatorType(PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_CONTAINSTEXT)
            ->setText('ALERTE');
        $alertRule->getStyle()->getFill()->setFillType('solid')->getStartColor()->setRGB('FCE8EA');
        $alertRule->getStyle()->getFont()->getColor()->setRGB($red);
        $checks->getStyle('F5:F9')->setConditionalStyles([$okRule, $alertRule]);
        $checkWidths = ['A' => 28, 'B' => 20, 'C' => 22, 'D' => 16, 'E' => 14, 'F' => 21, 'G' => 48];
        foreach ($checkWidths as $column => $width) {
            $checks->getColumnDimension($column)->setWidth($width);
        }
        $checks->getRowDimension(1)->setRowHeight(30);
        $checks->freezePane('A5');

        $classeur->setActiveSheetIndex(0);
        $writer = new PhpOffice\PhpSpreadsheet\Writer\Xlsx($classeur);
        $writer->setIncludeCharts(true);
        $writer->setPreCalculateFormulas(true);

        $nomCompte = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)$compte['nom']);
        $nomFichier = 'livre-caisse-CFO-' . ($nomCompte ?: $cashAccountId) . '-' . $dateDebut;
        if ($dateFin !== $dateDebut) {
            $nomFichier .= '-au-' . $dateFin;
        }
        $nomFichier .= '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
        header('Cache-Control: max-age=0, must-revalidate');
        header('Pragma: public');
        $writer->save('php://output');
        $classeur->disconnectWorksheets();
        exit;
    }

    public function banque(): void {
        $this->verifierAccesTresorerie();

        $filtres = [
            'account_id' => (int)($_GET['account_id'] ?? 0),
            'period_year' => (int)($_GET['period_year'] ?? date('Y')),
            'period_month' => (int)($_GET['period_month'] ?? 0),
            'status' => $_GET['status'] ?? '',
        ];
        $releves = $this->treasuryModel->listerRelevesBancaires($filtres, 30, 0);
        $comptes = $this->comptesParType('banque');

        $this->render('banque', compact('filtres', 'releves', 'comptes'));
    }

    public function ajouterReleve(): void {
        $this->verifierAccesTresorerie('operation');

        $erreurs = [];
        $comptes = $this->comptesParType('banque');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = 'Requête invalide.';
            } else {
                $fichier = null;
                if (!empty($_FILES['statement_file']['name'])) {
                    $fichier = traiterUpload($_FILES['statement_file'], 'treasury');
                    if ($fichier === false) {
                        $erreurs[] = 'Fichier de releve invalide.';
                    }
                }

                $donnees = [
                    'account_id' => (int)($_POST['account_id'] ?? 0),
                    'period_year' => (int)($_POST['period_year'] ?? date('Y')),
                    'period_month' => (int)($_POST['period_month'] ?? date('n')),
                    'statement_number' => trim($_POST['statement_number'] ?? ''),
                    'opening_balance' => (float)($_POST['opening_balance'] ?? 0),
                    'closing_balance' => (float)($_POST['closing_balance'] ?? 0),
                    'currency' => $_POST['currency'] ?? 'USD',
                    'statement_file' => $fichier,
                    'created_by' => (int)($_SESSION['user_id'] ?? 0),
                ];

                if ($donnees['account_id'] <= 0) {
                    $erreurs[] = 'Le compte bancaire est obligatoire.';
                }
                if ($donnees['period_month'] < 1 || $donnees['period_month'] > 12) {
                    $erreurs[] = 'Mois invalide.';
                }
                if (!in_array($donnees['currency'], ['USD','CDF'], true)) {
                    $erreurs[] = 'Devise invalide.';
                }
                if (empty($erreurs)) {
                    $existant = $this->treasuryModel->listerRelevesBancaires([
                        'account_id' => $donnees['account_id'],
                        'period_year' => $donnees['period_year'],
                        'period_month' => $donnees['period_month'],
                    ], 1, 0);
                    if (!empty($existant)) {
                        $erreurs[] = 'Un releve existe deja pour ce compte et cette periode.';
                    }
                }

                if (empty($erreurs)) {
                    $id = $this->treasuryModel->creerReleveBancaire($donnees);
                    logAudit('Creation releve bancaire #' . $id, 'treasury_bank_statements', $id);
                    flashMessage('succes', 'Releve bancaire cree.');
                    redirect('/treasury/rapprochement/' . $id);
                }
            }
        }

        $this->render('releve_ajouter', compact('erreurs', 'comptes'));
    }

    public function rapprochement(?string $id): void {
        $this->verifierAccesTresorerie();
        if (empty($id)) {
            redirect('/treasury/banque');
        }

        $statementId = (int)$id;
        $releve = $this->treasuryModel->trouverReleveBancaire($statementId);
        if (!$releve) {
            flashMessage('erreur', 'Releve bancaire introuvable.');
            redirect('/treasury/banque');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                flashMessage('erreur', 'Requête invalide.');
                redirect('/treasury/rapprochement/' . $statementId);
            }

            $this->verifierAccesTresorerie('operation');
            $action = $_POST['action'] ?? '';
            $rapprochementActuel = $this->treasuryModel->trouverRapprochementParReleve($statementId);
            $estVerrouille = in_array($releve['status'], ['rapproche','verrouille'], true)
                || in_array($rapprochementActuel['status'] ?? '', ['valide','verrouille'], true);

            if ($estVerrouille) {
                flashMessage('erreur', 'Rapprochement valide: modifications verrouillees.');
                redirect('/treasury/rapprochement/' . $statementId);
            }

            if ($action === 'ajouter_ligne') {
                $debit = (float)($_POST['debit'] ?? 0);
                $credit = (float)($_POST['credit'] ?? 0);
                $libelle = trim($_POST['label'] ?? '');

                if ($libelle === '' || ($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0)) {
                    flashMessage('erreur', 'Ligne invalide: libelle obligatoire et un seul montant debit ou credit.');
                    redirect('/treasury/rapprochement/' . $statementId);
                }

                $lineId = $this->treasuryModel->ajouterLigneReleve($statementId, [
                    'operation_date' => $_POST['operation_date'] ?? date('Y-m-d'),
                    'label' => $libelle,
                    'reference_operation' => trim($_POST['reference_operation'] ?? ''),
                    'debit' => $debit,
                    'credit' => $credit,
                    'line_comment' => trim($_POST['line_comment'] ?? ''),
                ]);
                logAudit('Ajout ligne releve bancaire #' . $lineId, 'treasury_bank_statement_lines', $lineId);
                flashMessage('succes', 'Ligne de releve ajoutee.');
                redirect('/treasury/rapprochement/' . $statementId);
            }

            if ($action === 'rapprocher_ligne') {
                $lineId = (int)($_POST['line_id'] ?? 0);
                $transactionId = (int)($_POST['transaction_id'] ?? 0);
                $ok = $lineId > 0 && $transactionId > 0
                    ? $this->treasuryModel->rapprocherLigne($lineId, $transactionId, (int)($_SESSION['user_id'] ?? 0))
                    : false;
                logAudit('Rapprochement ligne bancaire #' . $lineId . ' transaction #' . $transactionId, 'treasury_bank_statement_lines', $lineId);
                flashMessage($ok ? 'succes' : 'erreur', $ok ? 'Ligne rapprochee.' : 'Impossible de rapprocher cette ligne.');
                redirect('/treasury/rapprochement/' . $statementId);
            }

            if ($action === 'valider') {
                if (!$this->peutValiderNiveau('comptable')) {
                    flashMessage('erreur', 'Validation reservee au comptable senior ou profil habilite.');
                    redirect('/treasury/rapprochement/' . $statementId);
                }
                $rapprochementId = $this->treasuryModel->creerOuTrouverRapprochement($statementId, (int)($_SESSION['user_id'] ?? 0));
                $ok = $this->treasuryModel->validerRapprochement($rapprochementId, (int)($_SESSION['user_id'] ?? 0), trim($_POST['commentaire'] ?? ''));
                logAudit('Validation rapprochement bancaire #' . $rapprochementId, 'treasury_bank_reconciliations', $rapprochementId);
                flashMessage($ok ? 'succes' : 'erreur', $ok ? 'Rapprochement valide.' : 'Impossible de valider ce rapprochement.');
                redirect('/treasury/rapprochement/' . $statementId);
            }
        }

        $lignes = $this->treasuryModel->lignesReleve($statementId);
        $rapprochement = $this->treasuryModel->trouverRapprochementParReleve($statementId);
        $transactions = $this->transactionsRapprochables((int)$releve['account_id']);
        $peutValiderRapprochement = $this->peutValiderNiveau('comptable');

        $this->render('rapprochement', compact('releve', 'lignes', 'rapprochement', 'transactions', 'peutValiderRapprochement'));
    }

    private function definitionKpiDemandes(string $metric): array {
        $definitions = [
            'a_controler' => ['title'=>'Contrôle trésorerie', 'subtitle'=>'Demandes à vérifier avant transmission au comptable', 'filters'=>['status'=>'a_controler']],
            'en_suspens' => ['title'=>'Demandes en suspens', 'subtitle'=>'Décisions différées avec date de rappel', 'filters'=>['status'=>'en_suspens']],
            'total' => [
                'title' => 'Toutes les demandes',
                'subtitle' => 'Vue consolidée du portefeuille des demandes de fonds',
                'filters' => [],
            ],
            'soumise' => [
                'title' => 'Demandes à valider',
                'subtitle' => 'Demandes en attente de validation comptable',
                'filters' => ['status' => 'soumise'],
            ],
            'validee_comptable' => [
                'title' => 'Circuit Direction',
                'subtitle' => 'Demandes en attente des validations DF et DG',
                'filters' => ['status' => 'validee_comptable'],
            ],
            'validee_direction' => [
                'title' => 'Prêtes pour ordre',
                'subtitle' => 'Demandes autorisées pour émission d’un ordre de paiement',
                'filters' => ['status' => 'validee_direction'],
            ],
            'haute' => [
                'title' => 'Urgences hautes',
                'subtitle' => 'Demandes prioritaires nécessitant une attention immédiate',
                'filters' => ['urgency' => 'haute'],
            ],
        ];
        return $definitions[$metric] ?? $definitions['total'];
    }

    private function definitionKpiOrdres(string $metric): array {
        $definitions = [
            'total' => ['title'=>'Tous les ordres','subtitle'=>'Vue consolidée du portefeuille des ordres de paiement','filters'=>[]],
            'emis' => ['title'=>'Ordres à exécuter','subtitle'=>'Engagements autorisés en attente de décaissement','filters'=>['status'=>'emis']],
            'execute' => ['title'=>'Ordres exécutés','subtitle'=>'Paiements effectivement réalisés et traçables','filters'=>['status'=>'execute']],
            'annule' => ['title'=>'Ordres annulés','subtitle'=>'Ordres clôturés sans exécution du paiement','filters'=>['status'=>'annule']],
        ];
        return $definitions[$metric] ?? $definitions['total'];
    }

    private function actionDashboardDemande(
        array $demande,
        string $label,
        string $owner,
        string $button,
        string $tone,
        string $urlPrefix = '/treasury/demande-voir/'
    ): array {
        $anchor = $demande['last_action_at'] ?? $demande['submitted_at'] ?? $demande['created_at'] ?? date('Y-m-d H:i:s');
        $timestamp = strtotime((string)$anchor);
        return [
            'type' => 'Demande de fonds',
            'reference' => $demande['request_number'],
            'label' => $label,
            'owner' => $owner,
            'beneficiary' => $demande['beneficiary'],
            'amount' => formatMontant((float)$demande['amount'], $demande['currency']),
            'amount_usd' => (float)$demande['amount_usd'],
            'waiting_days' => $timestamp ? max(0, (int)floor((time() - $timestamp) / 86400)) : 0,
            'url' => APP_URL . $urlPrefix . (int)$demande['id'],
            'button' => $button,
            'tone' => $tone,
        ];
    }

    private function statutOperationnelCompte(array $compte): string {
        if ((float)($compte['disponible'] ?? $compte['solde'] ?? 0) < 0) {
            return 'solde_insuffisant';
        }
        if (($compte['type_compte'] ?? '') === 'caisse') {
            return $compte['cash_status_today'] ?: 'non_ouverte';
        }
        if (($compte['type_compte'] ?? '') === 'banque') {
            return $compte['bank_status_latest'] ?: 'sans_releve';
        }
        return !empty($compte['actif']) ? 'actif' : 'inactif';
    }

    private function definitionRegistreDemandes(): array {
        $statusAutorises = ['brouillon','a_controler','en_suspens','soumise','validee_comptable','validee_direction','rejetee','ordre_emis','payee','annulee'];
        $urgencesAutorisees = ['basse','moyenne','haute'];
        $status = in_array($_GET['status'] ?? '', $statusAutorises, true) ? $_GET['status'] : '';
        $urgence = in_array($_GET['urgency'] ?? '', $urgencesAutorisees, true) ? $_GET['urgency'] : '';
        $dateDebut = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_debut'] ?? '') ? $_GET['date_debut'] : '';
        $dateFin = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_fin'] ?? '') ? $_GET['date_fin'] : '';
        $recherche = trim((string)($_GET['recherche'] ?? ''));
        $filters = array_filter([
            'status' => $status,
            'urgency' => $urgence,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'recherche' => $recherche,
        ], static function ($value) {
            return $value !== '';
        });

        $criteres = [];
        if ($status !== '') $criteres[] = 'statut : ' . $this->libelleTresorerie($status);
        if ($urgence !== '') $criteres[] = 'urgence : ' . $this->libelleTresorerie($urgence);
        if ($dateDebut !== '') $criteres[] = 'du ' . date('d/m/Y', strtotime($dateDebut));
        if ($dateFin !== '') $criteres[] = 'au ' . date('d/m/Y', strtotime($dateFin));
        if ($recherche !== '') $criteres[] = 'recherche : « ' . $recherche . ' »';

        return [
            'title' => 'Registre des demandes de fonds',
            'subtitle' => empty($criteres) ? 'Vue consolidée de l’ensemble du portefeuille' : 'Périmètre filtré · ' . implode(' · ', $criteres),
            'filters' => $filters,
        ];
    }

    private function libelleTresorerie(string $value): string {
        $labels = [
            'a_controler' => 'À contrôler · Trésorerie',
            'en_suspens' => 'En suspens',
            'brouillon' => 'Brouillon',
            'soumise' => 'Soumise',
            'validee_comptable' => 'Validée comptable',
            'validee_direction' => 'Validée direction',
            'rejetee' => 'Rejetée',
            'ordre_emis' => 'Ordre émis',
            'payee' => 'Payée',
            'annulee' => 'Annulée',
            'basse' => 'Basse',
            'moyenne' => 'Moyenne',
            'haute' => 'Haute',
        ];
        return $labels[$value] ?? ucfirst(str_replace('_', ' ', $value));
    }

    private function validerDonneesEncaissement(array $donnees, array &$erreurs): void {
        if ($donnees['payer_name'] === '') $erreurs[] = 'Le payeur est obligatoire.';
        if ($donnees['objet'] === '') $erreurs[] = 'L’objet est obligatoire.';
        if ($donnees['amount'] <= 0) $erreurs[] = 'Le montant doit etre superieur a 0.';
        if (!in_array($donnees['currency'], ['USD','CDF'], true)) $erreurs[] = 'Devise invalide.';
        if ($donnees['currency'] === 'CDF' && $donnees['exchange_rate'] <= 0) $erreurs[] = 'Le taux est obligatoire pour CDF.';
        if (!in_array($donnees['payment_method'], ['especes','banque','mobile_money'], true)) $erreurs[] = 'Mode de paiement invalide.';
        if ($donnees['account_id'] <= 0) $erreurs[] = 'Le compte est obligatoire.';
    }

    private function validerDonneesDemande(array $donnees, array &$erreurs): void {
        if ($donnees['beneficiary'] === '') $erreurs[] = 'Le beneficiaire est obligatoire.';
        if (mb_strlen($donnees['beneficiary']) > 200) $erreurs[] = 'Le bénéficiaire ne peut pas dépasser 200 caractères.';
        if ($donnees['objet'] === '') $erreurs[] = 'L’objet est obligatoire.';
        if (mb_strlen($donnees['objet']) > 255) $erreurs[] = 'L’objet ne peut pas dépasser 255 caractères.';
        if ($donnees['amount'] <= 0) $erreurs[] = 'Le montant doit etre superieur a 0.';
        if (!in_array($donnees['currency'], ['USD','CDF'], true)) $erreurs[] = 'Devise invalide.';
        if ($donnees['currency'] === 'CDF' && $donnees['exchange_rate'] <= 0) $erreurs[] = 'Le taux est obligatoire pour CDF.';
        if ($donnees['category_id'] <= 0) $erreurs[] = 'La categorie est obligatoire.';
        else {
            $categorie = $this->financeModel->trouverCategorieParId((int)$donnees['category_id']);
            if (!$categorie || empty($categorie['actif']) || $categorie['type_flux'] !== 'depense') $erreurs[] = 'Sélectionnez une catégorie de dépense active.';
        }
        if (!in_array($donnees['urgency'], ['basse','moyenne','haute'], true)) $erreurs[] = 'Urgence invalide.';
    }

    private function demandeAccessible(int $id): array {
        $demande = $this->treasuryModel->trouverDemandeParId($id);
        $perimetre = perimetreDemandesFonds();
        $autorise = $demande && (empty($perimetre)
            || (isset($perimetre['created_by']) && (int)$demande['created_by'] === $perimetre['created_by'])
            || (isset($perimetre['department_id']) && (int)($demande['department_id'] ?? 0) === $perimetre['department_id'])
            || (empty($demande['department_id']) && isset($perimetre['department']) && (string)($demande['department'] ?? '') === $perimetre['department']));
        if (!$autorise) {
            flashMessage('erreur', 'Cette demande est introuvable ou en dehors de votre périmètre.');
            redirect('/treasury/demandes');
        }
        return $demande;
    }

    private function controleComptePaiement(int $accountId, int $excludeOrder = 0): array {
        $stmt = getDB()->prepare("SELECT COALESCE(SUM(amount),0) FROM treasury_payment_orders WHERE account_id=? AND status='emis' AND id<>?");
        $stmt->execute([$accountId, $excludeOrder]);
        $engagements = (float)$stmt->fetchColumn();
        $stmt = getDB()->prepare("SELECT COUNT(*) FROM financial_transactions t JOIN financial_accounts a ON a.id=t.compte_id WHERE a.id=? AND t.statut='valide' AND t.devise<>a.devise");
        $stmt->execute([$accountId]);
        return ['engagements'=>$engagements, 'anomalie'=>(int)$stmt->fetchColumn() > 0];
    }

    private function peutControlerDemandes(): bool {
        if (($_SESSION['user_role'] ?? '') === 'auditeur_interne') return false;
        $service = $this->normaliserService((string)($_SESSION['user_service'] ?? ''));
        return in_array($_SESSION['user_role'] ?? '', ['admin','tresorier'], true) || strpos($service, 'tresorerie') !== false;
    }

    private function notifierTresoriersDemande(int $id): void {
        $demande = $this->treasuryModel->trouverDemandeParId($id);
        if (!$demande) return;
        $users = getDB()->query("SELECT id, role, service FROM users WHERE actif = 1")->fetchAll();
        foreach ($users as $user) {
            if ($user['role'] === 'tresorier' || $user['role'] === 'admin' || strpos($this->normaliserService((string)$user['service']), 'tresorerie') !== false) {
                creerNotification((int)$user['id'], 'demande_controle', 'Demande ' . $demande['request_number'] . ' : contrôle trésorerie attendu.', APP_URL . '/treasury/demande-voir/' . $id);
            }
        }
    }

    private function verifierAccesTresorerie(string $niveau = 'lecture'): void {
        if (!$this->aAccesTresorerie()) {
            flashMessage('erreur', 'Acces refuse au module Tresorerie.');
            redirect('/dashboard');
        }

        if ($niveau === 'operation' && !$this->peutOpererTresorerie()) {
            flashMessage('erreur', 'Droits insuffisants pour cette operation de tresorerie.');
            redirect('/treasury/dashboard');
        }
    }

    private function aAccesTresorerie(): bool {
        return accesGlobalDemandesFonds();
    }

    private function peutOpererTresorerie(): bool {
        $role = $_SESSION['user_role'] ?? '';
        if ($role === 'auditeur_interne') return false;
        return in_array($role, [
                'admin','direction','chef_service','tresorier','comptable_senior',
                'directeur_financier','directeur_general'
            ], true)
            || strpos($this->normaliserService((string)($_SESSION['user_service'] ?? '')), 'tresorerie') !== false;
    }

    private function peutValiderEncaissement(): bool {
        return $this->peutValiderNiveau('comptable') || $this->peutValiderNiveau('direction_financiere');
    }

    private function peutExecuterPaiement(): bool {
        $role = $_SESSION['user_role'] ?? '';
        if ($role === 'auditeur_interne') return false;
        $service = $this->normaliserService((string)($_SESSION['user_service'] ?? ''));

        return in_array($role, ['admin','tresorier'], true)
            || strpos($service, 'tresorerie') !== false
            || ($role === 'direction' && strpos($service, 'finance') !== false);
    }

    private function peutAnnulerTresorerie(): bool {
        return $this->peutValiderNiveau('comptable') || $this->peutValiderNiveau('direction_financiere');
    }

    private function peutValiderNiveau(string $niveau): bool {
        $role = $_SESSION['user_role'] ?? '';
        if ($role === 'auditeur_interne') return false;
        $service = $this->normaliserService((string)($_SESSION['user_service'] ?? ''));

        if ($role === 'admin') {
            return true;
        }
        if ($niveau === 'comptable') {
            return $role === 'comptable_senior'
                || ($role === 'chef_service' && (
                    strpos($service, 'finance') !== false
                    || strpos($service, 'comptable') !== false
                    || strpos($service, 'comptabilite') !== false
                ));
        }
        if ($niveau === 'direction_financiere') {
            return $role === 'directeur_financier'
                || ($role === 'direction' && (
                    strpos($service, 'finance') !== false
                    || strpos($service, 'direction financiere') !== false
                    || strpos($service, 'direction des finances') !== false
                ));
        }
        if ($niveau === 'direction_generale') {
            return in_array($role, ['directeur_general','admin'], true)
                || ($role === 'direction' && strpos($service, 'generale') !== false)
                || strpos($service, 'direction generale') !== false;
        }

        return false;
    }

    private function peutEmettreOrdre(): bool {
        return $this->peutValiderNiveau('comptable') || $this->peutValiderNiveau('direction_financiere');
    }

    private function etatOperationnelOrdre(array $demande, ?array $ordre): array {
        $userId = (int)($_SESSION['user_id'] ?? 0);

        $emissionBlocages = [];
        if ($ordre) {
            $emissionBlocages[] = 'Un ordre existe déjà pour cette demande.';
        }
        if (($demande['status'] ?? '') !== 'validee_direction') {
            $emissionBlocages[] = 'La demande doit être validée par la direction.';
        }
        if (!$this->peutEmettreOrdre()) {
            $emissionBlocages[] = 'Votre profil ne peut pas émettre un ordre de paiement.';
        }
        if (!$this->respecteSeparationTachesDemande($demande)) {
            $emissionBlocages[] = 'Séparation des tâches: le créateur/soumetteur ne peut pas émettre cet ordre.';
        }

        $executionBlocages = [];
        if (!$ordre) {
            $executionBlocages[] = 'Aucun ordre de paiement émis.';
        } else {
            if (($ordre['status'] ?? '') !== 'emis') {
                $executionBlocages[] = 'L’ordre n’est plus en attente d’exécution.';
            }
            if (!empty($ordre['payment_id'])) {
                $executionBlocages[] = 'Un paiement est déjà enregistré pour cet ordre.';
            }
            if (($ordre['request_status'] ?? '') !== 'ordre_emis') {
                $executionBlocages[] = 'La demande source n’est pas dans un statut payable.';
            }
            if ((int)($ordre['issued_by'] ?? 0) === $userId && $userId > 0) {
                $executionBlocages[] = 'Séparation des tâches: l’émetteur de l’ordre ne peut pas exécuter le paiement.';
            }
            if (!$this->peutExecuterPaiement()) {
                $executionBlocages[] = 'Votre profil ne peut pas exécuter le paiement.';
            }
        }

        return [
            'peut_emettre' => empty($emissionBlocages),
            'emission_blocages' => $emissionBlocages,
            'peut_executer' => empty($executionBlocages),
            'execution_blocages' => $executionBlocages,
        ];
    }

    private function peutModifierJourneeCloturee(): bool {
        return in_array($_SESSION['user_role'] ?? '', ['admin','directeur_general'], true);
    }

    private function respecteSeparationTachesDemande(array $demande): bool {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        return $userId !== (int)($demande['created_by'] ?? 0)
            && $userId !== (int)($demande['submitted_by'] ?? 0);
    }

    private function niveauValidationParDefaut(array $demande): string {
        if (($demande['status'] ?? '') === 'soumise') {
            return 'comptable';
        }
        if ($this->peutValiderNiveau('direction_financiere')) {
            return 'direction_financiere';
        }
        return 'direction_generale';
    }

    private function normaliserService(string $service): string {
        $service = strtolower($service);
        return strtr($service, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a',
            'ù' => 'u', 'û' => 'u',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o',
            'ç' => 'c',
        ]);
    }

    private function comptesParType(string $typeCompte): array {
        return array_values(array_filter($this->financeModel->listerComptes(), static function ($compte) use ($typeCompte) {
            return ($compte['type_compte'] ?? '') === $typeCompte;
        }));
    }

    private function transactionsRapprochables(int $accountId): array {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "SELECT id, reference_unique, date_operation, type_flux, montant, devise, partenaire, description
             FROM financial_transactions
             WHERE compte_id = ? AND statut = 'valide'
               AND id NOT IN (
                    SELECT matched_transaction_id
                    FROM treasury_bank_statement_lines
                    WHERE matched_transaction_id IS NOT NULL
                      AND status IN ('rapproche','ecart')
               )
             ORDER BY date_operation DESC, id DESC
             LIMIT 100"
        );
        $stmt->execute([$accountId]);
        return $stmt->fetchAll();
    }

    private function notifierValidateursDemande(array $demande, string $niveau): void {
        $destinataires = $this->utilisateursPourNiveauValidation($niveau);
        $message = 'Demande de fonds a valider: ' . ($demande['request_number'] ?? ('#' . (int)$demande['id']))
            . ' - ' . ($demande['objet'] ?? '');
        $lien = APP_URL . '/treasury/demande-voir/' . (int)$demande['id'];

        foreach ($destinataires as $userId) {
            creerNotification((int)$userId, 'validation', $message, $lien);
        }
    }

    private function notifierCreateurDemande(array $demande, string $message): void {
        $createdBy = (int)($demande['created_by'] ?? 0);
        if ($createdBy > 0) {
            creerNotification($createdBy, 'info', $message, APP_URL . '/treasury/demande-voir/' . (int)$demande['id']);
        }
    }

    private function utilisateursPourNiveauValidation(string $niveau): array {
        $pdo = getDB();
        $rows = $pdo->query("SELECT id, role, service FROM users WHERE actif = 1")->fetchAll();
        $ids = [];

        foreach ($rows as $row) {
            $role = (string)($row['role'] ?? '');
            $service = $this->normaliserService((string)($row['service'] ?? ''));
            $match = false;

            if ($niveau === 'comptable') {
                $match = $role === 'admin'
                    || $role === 'comptable_senior'
                    || ($role === 'chef_service' && (
                        strpos($service, 'finance') !== false
                        || strpos($service, 'comptable') !== false
                        || strpos($service, 'comptabilite') !== false
                    ));
            } elseif ($niveau === 'direction_financiere') {
                $match = $role === 'admin'
                    || $role === 'directeur_financier'
                    || ($role === 'direction' && (
                        strpos($service, 'finance') !== false
                        || strpos($service, 'direction financiere') !== false
                        || strpos($service, 'direction des finances') !== false
                    ));
            } elseif ($niveau === 'direction_generale') {
                $match = in_array($role, ['admin', 'directeur_general'], true)
                    || ($role === 'direction' && strpos($service, 'generale') !== false)
                    || strpos($service, 'direction generale') !== false;
            }

            if ($match) {
                $ids[] = (int)$row['id'];
            }
        }

        return array_values(array_unique($ids));
    }

    private function render(string $vue, array $vars = []): void {
        extract($vars);
        $chemin = __DIR__ . '/../views/treasury/' . $vue . '.php';

        if (file_exists($chemin)) {
            require_once $chemin;
            return;
        }

        $pageTitle = 'Tresorerie - ' . APP_NAME;
        require_once __DIR__ . '/../views/layouts/header.php';
        echo '<div class="container-fluid">';
        afficherFlash();
        echo '<div class="alert alert-info shadow-sm">';
        echo '<h1 class="h4 mb-2">Module Tresorerie</h1>';
        echo '<p class="mb-0">La route est branchee. La vue <code>views/treasury/' . e($vue) . '.php</code> sera ajoutee a l etape UI.</p>';
        echo '</div>';
        echo '</div>';
        require_once __DIR__ . '/../views/layouts/footer.php';
    }
}
