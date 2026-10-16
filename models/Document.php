<?php
/**
 * Modèle Document - Gestion électronique des documents (GED)
 * AMM-ERP
 */
class Document {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Liste les documents (version courante seulement : parent_id IS NULL).
     */
    public function lister(array $filtres = [], int $limite = 20, int $offset = 0): array {
        $sql = "SELECT d.*, u.nom AS uploadeur_nom
                FROM documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.parent_id IS NULL";
        $params = [];

        if (!empty($filtres['type_doc'])) {
            $sql .= " AND d.type_doc = ?";
            $params[] = $filtres['type_doc'];
        }
        if (!empty($filtres['service'])) {
            $sql .= " AND d.service_proprietaire = ?";
            $params[] = $filtres['service'];
        }
        if (!empty($filtres['projet'])) {
            $sql .= " AND d.projet LIKE ?";
            $params[] = '%' . $filtres['projet'] . '%';
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (d.titre LIKE ? OR d.description LIKE ? OR d.tags LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params = array_merge($params, [$term, $term, $term]);
        }

        $sql .= " ORDER BY d.uploaded_at DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Compte le nombre de documents (version courante).
     */
    public function compter(array $filtres = []): int {
        $sql = "SELECT COUNT(*) FROM documents d WHERE d.parent_id IS NULL";
        $params = [];

        if (!empty($filtres['service'])) {
            $sql .= " AND d.service_proprietaire = ?";
            $params[] = $filtres['service'];
        }
        if (!empty($filtres['projet'])) {
            $sql .= " AND d.projet LIKE ?";
            $params[] = '%' . $filtres['projet'] . '%';
        }

        if (!empty($filtres['type_doc'])) {
            $sql .= " AND d.type_doc = ?";
            $params[] = $filtres['type_doc'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (d.titre LIKE ? OR d.description LIKE ? OR d.tags LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params = array_merge($params, [$term, $term, $term]);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Récupère un document par son ID.
     */
    public function trouverParId(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT d.*, u.nom AS uploadeur_nom
             FROM documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             WHERE d.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Récupère toutes les versions d'un document (chaîne parent → enfants).
     */
    public function getVersions(int $id): array {
        // Trouver la racine (le document sans parent ou avec parent_id chaîné)
        $stmt = $this->pdo->prepare(
            "SELECT d.*, u.nom AS uploadeur_nom
             FROM documents d
             LEFT JOIN users u ON d.uploaded_by = u.id
             WHERE d.id = ? OR d.parent_id = ?
             ORDER BY d.version ASC"
        );
        $stmt->execute([$id, $id]);
        return $stmt->fetchAll();
    }

    /**
     * Uploade un nouveau document.
     */
    public function creer(array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO documents
             (titre, type_doc, description, fichier, version, parent_id,
              service_proprietaire, projet, tags, uploaded_by, uploaded_at, expire_le)
             VALUES (?, ?, ?, ?, 1, NULL, ?, ?, ?, ?, NOW(), ?)"
        );
        $stmt->execute([
            $donnees['titre'],
            $donnees['type_doc'] ?? null,
            $donnees['description'] ?? null,
            $donnees['fichier'],
            $donnees['service_proprietaire'] ?? null,
            $donnees['projet'] ?? null,
            $donnees['tags'] ?? null,
            $_SESSION['user_id'],
            $donnees['expire_le'] ?: null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Crée une nouvelle version d'un document existant.
     * L'ancienne version reçoit un parent_id pointant vers la nouvelle.
     */
    public function nouvelleVersion(int $parentId, array $donnees): int {
        $parent = $this->trouverParId($parentId);
        if (!$parent) return 0;

        $nouvelleVersion = $parent['version'] + 1;

        // Créer le nouveau document
        $stmt = $this->pdo->prepare(
            "INSERT INTO documents
             (titre, type_doc, description, fichier, version, parent_id,
              service_proprietaire, projet, tags, uploaded_by, uploaded_at, expire_le)
             VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, NOW(), ?)"
        );
        $stmt->execute([
            $donnees['titre'] ?? $parent['titre'],
            $parent['type_doc'],
            $donnees['description'] ?? $parent['description'],
            $donnees['fichier'],
            $nouvelleVersion,
            $parent['service_proprietaire'],
            $parent['projet'],
            $parent['tags'],
            $_SESSION['user_id'],
            $donnees['expire_le'] ?: $parent['expire_le'],
        ]);
        $nouvelId = (int)$this->pdo->lastInsertId();

        // Marquer l'ancienne version avec parent_id = nouvel ID
        $stmtUpdate = $this->pdo->prepare(
            "UPDATE documents SET parent_id = ? WHERE id = ?"
        );
        $stmtUpdate->execute([$nouvelId, $parentId]);

        return $nouvelId;
    }

    /**
     * Statistiques pour le dashboard.
     */
    public function statistiques(): array {
        $stats = [];
        $requetes = [
            'total'         => "SELECT COUNT(*) FROM documents WHERE parent_id IS NULL",
            'expirant_30j'  => "SELECT COUNT(*) FROM documents WHERE parent_id IS NULL AND expire_le BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
            'expires'       => "SELECT COUNT(*) FROM documents WHERE parent_id IS NULL AND expire_le < CURDATE()",
        ];
        foreach ($requetes as $cle => $sql) {
            $stats[$cle] = (int)$this->pdo->query($sql)->fetchColumn();
        }
        return $stats;
    }

    /**
     * Liste les documents deja expires.
     */
    public function expiresList(int $limite = 50): array {
        $stmt = $this->pdo->prepare(
            "SELECT id, titre, type_doc, service_proprietaire, expire_le
             FROM documents
             WHERE parent_id IS NULL
               AND expire_le IS NOT NULL
               AND expire_le < CURDATE()
             ORDER BY expire_le DESC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
