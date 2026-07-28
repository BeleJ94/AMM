<?php
/**
 * Modèle User - Gestion des utilisateurs
 * AMM-ERP
 */
class User {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Trouve un utilisateur par son email (pour le login).
     */
    public function trouverParEmail(string $email) {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE email = ? AND actif = 1 LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Trouve un utilisateur par son ID.
     */
    public function trouverParId(int $id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Liste tous les utilisateurs (avec filtre optionnel).
     */
    public function listerTous(bool $actifSeulement = false): array {
        $sql = "SELECT * FROM users";
        if ($actifSeulement) {
            $sql .= " WHERE actif = 1";
        }
        $sql .= " ORDER BY nom ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    /**
     * Liste les utilisateurs d'un service donné.
     */
    public function listerParService(string $service): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM users WHERE service = ? AND actif = 1 ORDER BY nom ASC"
        );
        $stmt->execute([$service]);
        return $stmt->fetchAll();
    }

    /**
     * Crée un nouvel utilisateur.
     */
    public function creer(array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (nom, email, password, role, service, telephone, actif, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 1, NOW())"
        );
        $stmt->execute([
            $donnees['nom'],
            $donnees['email'],
            $donnees['password'], // Stocké en clair (TODO: password_hash)
            $donnees['role'],
            $donnees['service'] ?? null,
            $donnees['telephone'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Modifie un utilisateur existant.
     */
    public function modifier(int $id, array $donnees): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE users
             SET nom = ?, email = ?, role = ?, service = ?, telephone = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $donnees['nom'],
            $donnees['email'],
            $donnees['role'],
            $donnees['service'] ?? null,
            $donnees['telephone'] ?? null,
            $id,
        ]);
    }

    /**
     * Change le mot de passe d'un utilisateur.
     */
    public function changerMotDePasse(int $id, string $nouveauMdp): bool {
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$nouveauMdp, $id]);
    }

    /**
     * Active ou désactive un utilisateur (soft delete).
     */
    public function toggleActif(int $id): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET actif = IF(actif = 1, 0, 1) WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    /**
     * Vérifie si un email est déjà utilisé.
     */
    public function emailExiste(string $email, ?int $exclureId = null): bool {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        if ($exclureId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exclureId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Compte total des utilisateurs.
     */
    public function compter(): int {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    }
}
