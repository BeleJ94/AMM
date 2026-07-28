<?php
/**
 * Contrôleur Utilisateurs
 * AMM-ERP
 */
class UserController {

    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    /**
     * Liste des utilisateurs.
     */
    public function liste(): void {
        $users = $this->userModel->listerTous();
        require_once __DIR__ . '/../views/users/liste.php';
    }

    /**
     * Ajouter un utilisateur.
     */
    public function ajouter(): void {
        $erreurs = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = "Requête invalide.";
            } else {
                $donnees = [
                    'nom'       => trim($_POST['nom'] ?? ''),
                    'email'     => trim($_POST['email'] ?? ''),
                    'password'  => $_POST['password'] ?? '',
                    'role'      => $_POST['role'] ?? 'agent',
                    'service'   => trim($_POST['service'] ?? ''),
                    'telephone' => trim($_POST['telephone'] ?? ''),
                ];

                // Validation
                if (empty($donnees['nom']))      $erreurs[] = "Le nom est obligatoire.";
                if (empty($donnees['email']))     $erreurs[] = "L'email est obligatoire.";
                if (empty($donnees['password']))  $erreurs[] = "Le mot de passe est obligatoire.";
                if (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) $erreurs[] = "Email invalide.";
                if (!array_key_exists($donnees['role'], rolesApplication())) $erreurs[] = "Rôle invalide.";
                if ($this->userModel->emailExiste($donnees['email'])) $erreurs[] = "Cet email est déjà utilisé.";

                if (empty($erreurs)) {
                    $id = $this->userModel->creer($donnees);
                    logAudit("Création utilisateur: " . $donnees['nom'], 'users', $id);
                    flashMessage('succes', "Utilisateur '{$donnees['nom']}' créé avec succès.");
                    redirect('/users/liste');
                }
            }
        }

        require_once __DIR__ . '/../views/users/ajouter.php';
    }

    /**
     * Modifier un utilisateur.
     */
    public function modifier(?string $id): void {
        if (empty($id)) { redirect('/users/liste'); }
        $id = (int)$id;

        $user = $this->userModel->trouverParId($id);
        if (!$user) {
            flashMessage('erreur', "Utilisateur introuvable.");
            redirect('/users/liste');
        }

        $erreurs = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreurs[] = "Requête invalide.";
            } else {
                $donnees = [
                    'nom'       => trim($_POST['nom'] ?? ''),
                    'email'     => trim($_POST['email'] ?? ''),
                    'role'      => $_POST['role'] ?? 'agent',
                    'service'   => trim($_POST['service'] ?? ''),
                    'telephone' => trim($_POST['telephone'] ?? ''),
                ];

                if (empty($donnees['nom']))  $erreurs[] = "Le nom est obligatoire.";
                if (empty($donnees['email'])) $erreurs[] = "L'email est obligatoire.";
                if (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) $erreurs[] = "Email invalide.";
                if (!array_key_exists($donnees['role'], rolesApplication())) $erreurs[] = "Rôle invalide.";
                if ($this->userModel->emailExiste($donnees['email'], $id)) $erreurs[] = "Email déjà utilisé.";

                // Changer le mot de passe si fourni
                if (!empty($_POST['nouveau_mdp'])) {
                    $this->userModel->changerMotDePasse($id, $_POST['nouveau_mdp']);
                }

                if (empty($erreurs)) {
                    $this->userModel->modifier($id, $donnees);
                    logAudit("Modification utilisateur #$id", 'users', $id);
                    flashMessage('succes', "Utilisateur modifié avec succès.");
                    redirect('/users/liste');
                }
            }
        }

        require_once __DIR__ . '/../views/users/ajouter.php'; // Vue réutilisée
    }

    /**
     * Activer / Désactiver un utilisateur.
     */
    public function toggleActif(?string $id): void {
        if (empty($id)) { redirect('/users/liste'); }
        $id = (int)$id;

        $this->userModel->toggleActif($id);
        logAudit("Toggle actif utilisateur #$id", 'users', $id);
        flashMessage('succes', "Statut de l'utilisateur mis à jour.");
        redirect('/users/liste');
    }
}
