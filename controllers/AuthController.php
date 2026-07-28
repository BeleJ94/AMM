<?php
/**
 * Contrôleur d'authentification
 * AMM-ERP
 */
class AuthController {

    private User $userModel;

    public function __construct() {
        $this->userModel = new User();
    }

    /**
     * Affiche et traite le formulaire de login.
     */
    public function login(): void {
        // Rediriger si déjà connecté
        if (!empty($_SESSION['user_id'])) {
            redirect('/dashboard');
        }

        $erreur = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Valider le token CSRF
            if (!validerTokenCSRF($_POST['csrf_token'] ?? '')) {
                $erreur = "Requête invalide. Veuillez réessayer.";
            } else {
                $email    = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';

                if (empty($email) || empty($password)) {
                    $erreur = "Veuillez remplir tous les champs.";
                } else {
                    $user = $this->userModel->trouverParEmail($email);

                    // Comparaison en clair (TODO: remplacer par password_verify)
                    if ($user && $user['password'] === $password) {
                        // Régénérer l'ID de session pour éviter la fixation de session
                        session_regenerate_id(true);

                        // Stocker les informations en session
                        $_SESSION['user_id']   = $user['id'];
                        $_SESSION['user_nom']  = $user['nom'];
                        $_SESSION['user_email']= $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_service'] = $user['service'];

                        // Journal d'audit
                        logAudit("Connexion réussie", 'users', $user['id']);

                        // Vérifier les deadlines à chaque connexion
                        verifierDeadlines();
                        verifierContratsExpires();
                        verifierAlertesProjets();

                        redirect('/dashboard');
                    } else {
                        $erreur = "Email ou mot de passe incorrect.";
                        logAudit("Tentative de connexion échouée pour: $email", 'users');
                    }
                }
            }
        }

        // Afficher la vue login
        require_once __DIR__ . '/../views/auth/login.php';
    }

    /**
     * Déconnecte l'utilisateur.
     */
    public function logout(): void {
        logAudit("Déconnexion", 'users', $_SESSION['user_id'] ?? null);

        // Détruire toutes les variables de session
        $_SESSION = [];

        // Détruire le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
        redirect('/');
    }
}
