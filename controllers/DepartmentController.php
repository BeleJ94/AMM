<?php
class DepartmentController {
    public function index(): void {
        verifierSession(); verifierRole(['admin']);
        $model = new Department(); $erreurs = []; $edition = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF((string)($_POST['csrf_token'] ?? ''))) {
                $erreurs[] = 'La session du formulaire a expiré. Réessayez.';
            } else {
                try {
                    $id = max(0, (int)($_POST['id'] ?? 0));
                    if (($_POST['action'] ?? '') === 'etat') {
                        if (!$model->trouver($id)) throw new InvalidArgumentException('Département introuvable.');
                        $actif = ($_POST['actif'] ?? '') === '1';
                        $model->changerEtat($id, $actif);
                        logAudit($actif ? 'Activation département' : 'Désactivation département', 'services', $id);
                        flashMessage('succes', $actif ? 'Département réactivé.' : 'Département désactivé. Les dossiers et affectations existants sont conservés.');
                    } elseif (($_POST['action'] ?? '') === 'enregistrer') {
                        $id = $model->enregistrer((string)($_POST['nom'] ?? ''), (string)($_POST['description'] ?? ''), $id ?: null);
                        logAudit('Enregistrement département', 'services', $id);
                        flashMessage('succes', 'Département enregistré. Il est disponible dans les listes de sélection.');
                    } else throw new InvalidArgumentException('Action invalide.');
                    redirect('/admin-departements');
                } catch (InvalidArgumentException $e) { $erreurs[] = $e->getMessage(); }
            }
        }
        if (!empty($_GET['modifier'])) $edition = $model->trouver((int)$_GET['modifier']) ?: null;
        if ($erreurs && ($_POST['action'] ?? '') === 'enregistrer') $edition = ['id'=>(int)($_POST['id'] ?? 0),'nom'=>$_POST['nom'] ?? '', 'description'=>$_POST['description'] ?? ''];
        $departements = $model->lister(false);
        require __DIR__ . '/../views/admin_departements/index.php';
    }
}
