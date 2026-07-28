<?php
/**
 * Contrôleur Document - GED (Gestion Électronique des Documents)
 * AMM-ERP
 */
class DocumentController {

    private Document $documentModel;

    public function __construct() {
        $this->documentModel = new Document();
    }

    /**
     * Liste des documents.
     */
    public function liste(): void {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $filtres = [
            'type_doc'  => $_GET['type_doc']  ?? '',
            'service'   => $_GET['service']   ?? '',
            'recherche' => $_GET['recherche'] ?? '',
        ];

        $total      = $this->documentModel->compter($filtres);
        $pagination = paginer($total, 20, $page);
        $documents  = $this->documentModel->lister($filtres, 20, $pagination['offset']);

        require_once __DIR__ . '/../views/documents/liste.php';
    }

    /**
     * Upload d'un document.
     */
    public function upload(): void {
        $erreurs = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = "Requête invalide.";
            } else {
                if (empty($_FILES['fichier']['name'])) {
                    $erreurs[] = "Veuillez sélectionner un fichier.";
                } else {
                    $fichier = traiterUpload($_FILES['fichier'], 'documents');
                    if ($fichier === false) {
                        $erreurs[] = "Fichier invalide (type ou taille non autorisé).";
                    } else {
                        $donnees = [
                            'titre'               => trim($_POST['titre'] ?? ''),
                            'type_doc'            => trim($_POST['type_doc'] ?? ''),
                            'description'         => trim($_POST['description'] ?? ''),
                            'fichier'             => $fichier,
                            'service_proprietaire'=> trim($_POST['service_proprietaire'] ?? ''),
                            'projet'              => trim($_POST['projet'] ?? ''),
                            'tags'                => trim($_POST['tags'] ?? ''),
                            'expire_le'           => $_POST['expire_le'] ?? '',
                        ];

                        if (empty($donnees['titre'])) $erreurs[] = "Le titre est obligatoire.";

                        if (empty($erreurs)) {
                            $id = $this->documentModel->creer($donnees);
                            logAudit("Upload document: {$donnees['titre']}", 'documents', $id);
                            flashMessage('succes', "Document '{$donnees['titre']}' uploadé avec succès.");
                            redirect('/documents/voir/' . $id);
                        }
                    }
                }
            }
        }

        require_once __DIR__ . '/../views/documents/upload.php';
    }

    /**
     * Voir le détail d'un document.
     */
    public function voir(?string $id): void {
        if (empty($id)) { redirect('/documents/liste'); }
        $id = (int)$id;

        $document = $this->documentModel->trouverParId($id);
        if (!$document) {
            flashMessage('erreur', "Document introuvable.");
            redirect('/documents/liste');
        }

        $versions = $this->documentModel->getVersions($id);

        require_once __DIR__ . '/../views/documents/version.php';
    }

    /**
     * Uploader une nouvelle version d'un document.
     */
    public function nouvelleVersion(?string $id): void {
        if (empty($id) || $_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('/documents/liste'); }
        $id = (int)$id;

        if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
            flashMessage('erreur', "Requête invalide.");
            redirect('/documents/voir/' . $id);
        }

        if (empty($_FILES['fichier']['name'])) {
            flashMessage('erreur', "Veuillez sélectionner un fichier.");
            redirect('/documents/voir/' . $id);
        }

        $fichier = traiterUpload($_FILES['fichier'], 'documents');
        if ($fichier === false) {
            flashMessage('erreur', "Fichier invalide.");
            redirect('/documents/voir/' . $id);
        }

        $donnees = [
            'fichier'    => $fichier,
            'description'=> trim($_POST['description'] ?? ''),
            'expire_le'  => $_POST['expire_le'] ?? '',
        ];

        $nouvelId = $this->documentModel->nouvelleVersion($id, $donnees);
        logAudit("Nouvelle version document #$id → #$nouvelId", 'documents', $nouvelId);
        flashMessage('succes', "Nouvelle version uploadée avec succès.");
        redirect('/documents/voir/' . $nouvelId);
    }

    /**
     * Télécharger un document (avec log d'audit).
     */
    public function telecharger(?string $id): void {
        if (empty($id)) { redirect('/documents/liste'); }
        $id = (int)$id;

        $document = $this->documentModel->trouverParId($id);
        if (!$document) {
            flashMessage('erreur', "Document introuvable.");
            redirect('/documents/liste');
        }

        $cheminFichier = UPLOAD_DIR . $document['fichier'];
        if (!file_exists($cheminFichier)) {
            flashMessage('erreur', "Fichier introuvable sur le serveur.");
            redirect('/documents/voir/' . $id);
        }

        // Log du téléchargement
        logAudit("Téléchargement document: {$document['titre']}", 'documents', $id);

        // Envoyer le fichier
        $nomFichier = basename($document['fichier']);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $nomFichier . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Content-Length: ' . filesize($cheminFichier));
        readfile($cheminFichier);
        exit;
    }
}
