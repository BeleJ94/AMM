<?php
/**
 * Modèle Notification - Centre de notifications in-app
 * AMM-ERP
 */
class Notification {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Récupère les notifications d'un utilisateur.
     */
    public function listerPourUser(int $userId, bool $nonLuesSeulement = false, int $limite = 20): array {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        if ($nonLuesSeulement) {
            $sql .= " AND est_lu = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $limite]);
        return $stmt->fetchAll();
    }

    /**
     * Compte les notifications non lues d'un utilisateur.
     */
    public function compterNonLues(int $userId): int {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND est_lu = 0"
        );
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Marque une notification comme lue.
     */
    public function marquerLue(int $id, int $userId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE notifications SET est_lu = 1 WHERE id = ? AND user_id = ?"
        );
        return $stmt->execute([$id, $userId]);
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues.
     */
    public function marquerToutesLues(int $userId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE notifications SET est_lu = 1 WHERE user_id = ? AND est_lu = 0"
        );
        return $stmt->execute([$userId]);
    }

    /**
     * Crée une nouvelle notification.
     */
    public function creer(int $userId, string $type, string $message, string $lien = ''): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (user_id, type_notif, message, lien, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$userId, $type, $message, $lien]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Retourne l'icône Bootstrap correspondant au type de notification.
     */
    public static function iconeType(string $type): string {
        switch ($type) {
            case 'assignation':
                return 'bi-envelope-arrow-down';
            case 'rappel':
                return 'bi-bell';
            case 'escalation':
                return 'bi-exclamation-triangle';
            case 'echeance':
                return 'bi-calendar-x';
            default:
                return 'bi-info-circle';
        }
    }

    /**
     * Retourne la couleur Bootstrap correspondant au type.
     */
    public static function couleurType(string $type): string {
        switch ($type) {
            case 'assignation':
                return 'primary';
            case 'rappel':
                return 'warning';
            case 'escalation':
            case 'echeance':
                return 'danger';
            default:
                return 'info';
        }
    }
}
