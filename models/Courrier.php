<?php
/**
 * Modèle Courrier - Gestion des courriers et workflow
 * AMM-ERP
 */
class Courrier {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Liste les courriers avec filtres et pagination.
     */
    public function lister(array $filtres = [], int $limite = 20, int $offset = 0, string $tri = 'created_at', string $direction = 'DESC'): array {
        $sql = "SELECT c.*, u.nom AS createur
                FROM courriers c
                LEFT JOIN users u ON c.created_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql .= " AND c.statut = ?";
            $params[] = $filtres['statut'];
        }
        if (!empty($filtres['urgence'])) {
            $sql .= " AND c.urgence = ?";
            $params[] = $filtres['urgence'];
        }
        if (!empty($filtres['service'])) {
            $sql .= " AND c.service_destinataire = ?";
            $params[] = $filtres['service'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (c.numero_unique LIKE ? OR c.reference_document LIKE ? OR c.expediteur LIKE ? OR c.objet LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params = array_merge($params, [$term, $term, $term, $term]);
        }
        if (!empty($filtres['date_debut'])) {
            $sql .= " AND c.date_reception >= ?";
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $sql .= " AND c.date_reception <= ?";
            $params[] = $filtres['date_fin'];
        }

        $colonnes = ['created_at', 'numero_unique', 'reference_document', 'expediteur', 'objet', 'urgence', 'statut', 'date_reception', 'deadline', 'service_destinataire'];
        if (!in_array($tri, $colonnes, true)) $tri = 'created_at';
        $direction = $direction === 'ASC' ? 'ASC' : 'DESC';
        $sql .= " ORDER BY c.$tri $direction, c.id DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Compte le total des courriers (pour pagination et dashboard).
     */
    public function compter(array $filtres = []): int {
        $sql = "SELECT COUNT(*) FROM courriers c WHERE 1=1";
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql .= " AND c.statut = ?";
            $params[] = $filtres['statut'];
        }
        if (!empty($filtres['urgence'])) {
            $sql .= " AND c.urgence = ?";
            $params[] = $filtres['urgence'];
        }
        if (!empty($filtres['service'])) {
            $sql .= " AND c.service_destinataire = ?";
            $params[] = $filtres['service'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (c.numero_unique LIKE ? OR c.reference_document LIKE ? OR c.expediteur LIKE ? OR c.objet LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params = array_merge($params, [$term, $term, $term, $term]);
        }

        if (!empty($filtres['date_debut'])) {
            $sql .= " AND c.date_reception >= ?";
            $params[] = $filtres['date_debut'];
        }
        if (!empty($filtres['date_fin'])) {
            $sql .= " AND c.date_reception <= ?";
            $params[] = $filtres['date_fin'];
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Récupère un courrier par son ID avec ses assignations et preuves.
     */
    public function trouverParId(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, u.nom AS createur
             FROM courriers c
             LEFT JOIN users u ON c.created_by = u.id
             WHERE c.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Crée un nouveau courrier.
     */
    public function creer(array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO courriers
             (numero_unique, reference_document, expediteur, type_document, objet, urgence, date_reception,
              deadline, statut, service_destinataire, fichier_scan, recu_physique, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Recu', ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $donnees['numero_unique'],
            $donnees['reference_document'] ?: null,
            $donnees['expediteur'],
            $donnees['type_document'] ?? null,
            $donnees['objet'],
            $donnees['urgence'] ?? 'basse',
            $donnees['date_reception'],
            $donnees['deadline'] ?: null,
            $donnees['service_destinataire'] ?? null,
            $donnees['fichier_scan'] ?? null,
            $donnees['recu_physique'] ?? 0,
            $_SESSION['user_id'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Modifie un courrier existant.
     */
    public function modifier(int $id, array $donnees): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE courriers
             SET reference_document = ?, expediteur = ?, type_document = ?, objet = ?, urgence = ?,
                 date_reception = ?, deadline = ?, service_destinataire = ?,
                 recu_physique = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $donnees['reference_document'] ?: null,
            $donnees['expediteur'],
            $donnees['type_document'] ?? null,
            $donnees['objet'],
            $donnees['urgence'],
            $donnees['date_reception'],
            $donnees['deadline'] ?: null,
            $donnees['service_destinataire'] ?? null,
            $donnees['recu_physique'] ?? 0,
            $id,
        ]);
    }

    /**
     * Change le statut d'un courrier.
     */
    public function changerStatut(int $id, string $statut): bool {
        $stmt = $this->pdo->prepare("UPDATE courriers SET statut = ? WHERE id = ?");
        return $stmt->execute([$statut, $id]);
    }

    // ---------------------------------------------------------------
    // ASSIGNATIONS
    // ---------------------------------------------------------------

    /**
     * Assigne un courrier à un ou plusieurs utilisateurs.
     */
    public function assigner(int $courrierId, array $userIds, string $instructions = '', ?string $duDate = null): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO assignations
             (courrier_id, assigned_to, assigned_by, assigned_at, instructions, due_date, statut)
             VALUES (?, ?, ?, NOW(), ?, ?, 'en_attente')"
        );
        foreach ($userIds as $userId) {
            $stmt->execute([
                $courrierId,
                (int)$userId,
                $_SESSION['user_id'],
                $instructions,
                $duDate ?: null,
            ]);
        }

        // Mettre à jour le statut du courrier
        $this->changerStatut($courrierId, 'Assigne');
    }

    /**
     * Accuse réception d'une assignation.
     */
    public function accuserReception(int $courrierId, int $userId): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE assignations
             SET acknowledged_at = NOW(), statut = 'accepte'
             WHERE courrier_id = ? AND assigned_to = ? AND acknowledged_at IS NULL"
        );
        $ok = $stmt->execute([$courrierId, $userId]);
        if ($ok) {
            $this->changerStatut($courrierId, 'Accuse');
        }
        return $ok;
    }

    /**
     * Récupère les assignations d'un courrier.
     */
    public function getAssignations(int $courrierId): array {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, u.nom AS agent_nom, u.email AS agent_email
             FROM assignations a
             JOIN users u ON a.assigned_to = u.id
             WHERE a.courrier_id = ?
             ORDER BY a.assigned_at DESC"
        );
        $stmt->execute([$courrierId]);
        return $stmt->fetchAll();
    }

    // ---------------------------------------------------------------
    // PREUVES
    // ---------------------------------------------------------------

    /**
     * Ajoute une preuve à un courrier.
     */
    public function ajouterPreuve(int $courrierId, array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO preuves (courrier_id, type_preuve, fichier, commentaire, uploaded_by, uploaded_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $courrierId,
            $donnees['type_preuve'],
            $donnees['fichier'] ?? null,
            $donnees['commentaire'] ?? null,
            $_SESSION['user_id'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Récupère les preuves d'un courrier.
     */
    public function getPreuves(int $courrierId): array {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nom AS uploadeur
             FROM preuves p
             LEFT JOIN users u ON p.uploaded_by = u.id
             WHERE p.courrier_id = ?
             ORDER BY p.uploaded_at DESC"
        );
        $stmt->execute([$courrierId]);
        return $stmt->fetchAll();
    }

    /**
     * Vérifie si un courrier a au moins une preuve.
     */
    public function aDesPreuves(int $courrierId): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM preuves WHERE courrier_id = ?"
        );
        $stmt->execute([$courrierId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Clôture un courrier (vérifie la présence de preuve).
     */
    public function cloturer(int $id) {
        if (!$this->aDesPreuves($id)) {
            return "Impossible de clôturer : aucune preuve attachée.";
        }
        $this->changerStatut($id, 'Clos');
        return true;
    }

    // ---------------------------------------------------------------
    // STATISTIQUES POUR DASHBOARD
    // ---------------------------------------------------------------

    /**
     * Statistiques globales des courriers.
     */
    public function statistiques(): array {
        $stats = [];

        $requetes = [
            'total'         => "SELECT COUNT(*) FROM courriers",
            'recus'         => "SELECT COUNT(*) FROM courriers WHERE statut = 'Recu'",
            'en_cours'      => "SELECT COUNT(*) FROM courriers WHERE statut IN ('Assigne','Accuse','En_cours')",
            'overdue'       => "SELECT COUNT(*) FROM courriers WHERE statut = 'Overdue'",
            'aujourd_hui'   => "SELECT COUNT(*) FROM courriers WHERE DATE(date_reception) = CURDATE()",
            'non_assignes'  => "SELECT COUNT(*) FROM courriers WHERE statut = 'Recu'",
            'urgents'       => "SELECT COUNT(*) FROM courriers WHERE urgence = 'haute' AND statut NOT IN ('Clos','Archive')",
        ];

        foreach ($requetes as $cle => $sql) {
            $stats[$cle] = (int)$this->pdo->query($sql)->fetchColumn();
        }

        return $stats;
    }

    /**
     * Courriers assignés à un utilisateur.
     */
    public function mesCourriers(int $userId): array {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, a.statut AS statut_assignation, a.due_date, a.acknowledged_at
             FROM courriers c
             JOIN assignations a ON c.id = a.courrier_id
             WHERE a.assigned_to = ? AND a.statut != 'termine'
             ORDER BY c.urgence DESC, c.deadline ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Courriers non accusés depuis plus de 24h.
     */
    public function nonAccusesDepuis(int $heures = 24): array {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, a.assigned_to, u.nom AS agent_nom, u.email AS agent_email
             FROM courriers c
             JOIN assignations a ON c.id = a.courrier_id
             JOIN users u ON a.assigned_to = u.id
             WHERE a.acknowledged_at IS NULL
               AND a.assigned_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
             ORDER BY a.assigned_at ASC"
        );
        $stmt->execute([$heures]);
        return $stmt->fetchAll();
    }
}
