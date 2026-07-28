<?php
/**
 * Modèle Contrat - Gestion des contrats et paiements
 * AMM-ERP
 */
class Contrat {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    /**
     * Liste tous les contrats avec filtres.
     */
    public function lister(array $filtres = [], int $limite = 20, int $offset = 0): array {
        $sql = "SELECT c.*, d.titre AS document_titre
                FROM contrats c
                LEFT JOIN documents d ON c.document_id = d.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql .= " AND c.statut = ?";
            $params[] = $filtres['statut'];
        }
        if (!empty($filtres['devise'])) {
            $sql .= " AND c.devise = ?";
            $params[] = $filtres['devise'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (c.partenaire LIKE ? OR c.objet LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params = array_merge($params, [$term, $term]);
        }

        $sql .= " ORDER BY c.date_fin ASC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Compte les contrats.
     */
    public function compter(array $filtres = []): int {
        $sql = "SELECT COUNT(*) FROM contrats WHERE 1=1";
        $params = [];
        if (!empty($filtres['statut'])) {
            $sql .= " AND statut = ?";
            $params[] = $filtres['statut'];
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Trouve un contrat par son ID.
     */
    public function trouverParId(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT c.*, d.titre AS document_titre
             FROM contrats c
             LEFT JOIN documents d ON c.document_id = d.id
             WHERE c.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Crée un nouveau contrat.
     */
    public function creer(array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO contrats
             (partenaire, objet, valeur, devise, date_signature, date_debut, date_fin,
              tacite_reconduction, statut, document_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'actif', ?, NOW())"
        );
        $stmt->execute([
            $donnees['partenaire'],
            $donnees['objet'],
            $donnees['valeur'],
            $donnees['devise'],
            $donnees['date_signature'] ?: null,
            $donnees['date_debut'],
            $donnees['date_fin'],
            $donnees['tacite_reconduction'] ?? 0,
            $donnees['document_id'] ?: null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Modifie un contrat.
     */
    public function modifier(int $id, array $donnees): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE contrats
             SET partenaire = ?, objet = ?, valeur = ?, devise = ?,
                 date_signature = ?, date_debut = ?, date_fin = ?,
                 tacite_reconduction = ?, statut = ?, document_id = ?
             WHERE id = ?"
        );
        return $stmt->execute([
            $donnees['partenaire'],
            $donnees['objet'],
            $donnees['valeur'],
            $donnees['devise'],
            $donnees['date_signature'] ?: null,
            $donnees['date_debut'],
            $donnees['date_fin'],
            $donnees['tacite_reconduction'] ?? 0,
            $donnees['statut'],
            $donnees['document_id'] ?: null,
            $id,
        ]);
    }

    // ---------------------------------------------------------------
    // PAIEMENTS
    // ---------------------------------------------------------------

    /**
     * Ajoute un paiement à un contrat.
     */
    public function ajouterPaiement(int $contratId, array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO paiements (contrat_id, date_paiement, montant, preuve_fichier, commentaire)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $contratId,
            $donnees['date_paiement'],
            $donnees['montant'],
            $donnees['preuve_fichier'] ?? null,
            $donnees['commentaire'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Récupère les paiements d'un contrat.
     */
    public function getPaiements(int $contratId): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM paiements WHERE contrat_id = ? ORDER BY date_paiement DESC"
        );
        $stmt->execute([$contratId]);
        return $stmt->fetchAll();
    }

    /**
     * Total payé sur un contrat.
     */
    public function totalPaye(int $contratId): float {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(montant), 0) FROM paiements WHERE contrat_id = ?"
        );
        $stmt->execute([$contratId]);
        return (float)$stmt->fetchColumn();
    }

    // ---------------------------------------------------------------
    // ALERTES ÉCHÉANCES
    // ---------------------------------------------------------------

    /**
     * Contrats expirant dans N jours.
     */
    public function expirantDans(int $jours): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM contrats
             WHERE statut = 'actif'
               AND date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
             ORDER BY date_fin ASC"
        );
        $stmt->execute([$jours]);
        return $stmt->fetchAll();
    }

    /**
     * Statistiques financières pour le dashboard.
     */
    public function statistiques(): array {
        $stats = [];

        // Total des contrats actifs (USD)
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(valeur), 0) FROM contrats WHERE statut = 'actif' AND devise = 'USD'"
        );
        $stats['total_usd'] = (float)$stmt->fetchColumn();

        // Total des contrats actifs (CDF)
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(valeur), 0) FROM contrats WHERE statut = 'actif' AND devise = 'CDF'"
        );
        $stats['total_cdf'] = (float)$stmt->fetchColumn();

        // Total payé (USD)
        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(p.montant), 0)
             FROM paiements p
             JOIN contrats c ON p.contrat_id = c.id
             WHERE c.devise = 'USD'"
        );
        $stats['paye_usd'] = (float)$stmt->fetchColumn();

        // Nombre de contrats actifs
        $stats['actifs'] = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM contrats WHERE statut = 'actif'"
        )->fetchColumn();

        // Nombre de contrats expirant dans 30 jours
        $stats['expirant_30j'] = count($this->expirantDans(30));

        return $stats;
    }
}
