<?php
/**
 * Modele Projet - Gestion portefeuille projets
 * AMM-ERP
 */
class Projet {

    private PDO $pdo;

    public function __construct() {
        $this->pdo = getDB();
    }

    public function compter(array $filtres = []): int {
        $sql = "SELECT COUNT(*) FROM projects p WHERE 1=1";
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql .= " AND p.statut = ?";
            $params[] = $filtres['statut'];
        }
        if (!empty($filtres['responsable_id'])) {
            $sql .= " AND p.responsable_id = ?";
            $params[] = (int)$filtres['responsable_id'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (p.titre LIKE ? OR p.description LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function lister(array $filtres = [], int $limite = 20, int $offset = 0): array {
        $sql = "SELECT p.*, u.nom AS responsable_nom, uc.nom AS createur_nom
                FROM projects p
                LEFT JOIN users u ON p.responsable_id = u.id
                LEFT JOIN users uc ON p.created_by = uc.id
                WHERE 1=1";
        $params = [];

        if (!empty($filtres['statut'])) {
            $sql .= " AND p.statut = ?";
            $params[] = $filtres['statut'];
        }
        if (!empty($filtres['responsable_id'])) {
            $sql .= " AND p.responsable_id = ?";
            $params[] = (int)$filtres['responsable_id'];
        }
        if (!empty($filtres['recherche'])) {
            $sql .= " AND (p.titre LIKE ? OR p.description LIKE ?)";
            $term = '%' . $filtres['recherche'] . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY p.date_fin ASC, p.id DESC LIMIT ? OFFSET ?";
        $params[] = $limite;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function trouverParId(int $id) {
        $stmt = $this->pdo->prepare(
            "SELECT p.*, u.nom AS responsable_nom, uc.nom AS createur_nom
             FROM projects p
             LEFT JOIN users u ON p.responsable_id = u.id
             LEFT JOIN users uc ON p.created_by = uc.id
             WHERE p.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function creer(array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO projects
             (titre, description, responsable_id, budget, devise, date_debut, date_fin, statut, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->execute([
            $donnees['titre'],
            $donnees['description'] ?: null,
            $donnees['responsable_id'] ?: null,
            $donnees['budget'],
            $donnees['devise'],
            $donnees['date_debut'],
            $donnees['date_fin'],
            $donnees['statut'] ?? 'non_demarre',
            $donnees['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function modifier(int $id, array $donnees): bool {
        $stmt = $this->pdo->prepare(
            "UPDATE projects
             SET titre = ?, description = ?, responsable_id = ?, budget = ?, devise = ?,
                 date_debut = ?, date_fin = ?, statut = ?
             WHERE id = ?"
        );

        return $stmt->execute([
            $donnees['titre'],
            $donnees['description'] ?: null,
            $donnees['responsable_id'] ?: null,
            $donnees['budget'],
            $donnees['devise'],
            $donnees['date_debut'],
            $donnees['date_fin'],
            $donnees['statut'],
            $id,
        ]);
    }

    public function listerPhases(int $projectId): array {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM project_phases
             WHERE project_id = ?
             ORDER BY ordre_phase ASC, id ASC"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function ajouterPhase(int $projectId, array $donnees): int {
        $ordre = !empty($donnees['ordre_phase']) ? (int)$donnees['ordre_phase'] : $this->prochainOrdrePhase($projectId);
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_phases
             (project_id, nom, description, ordre_phase, date_debut, date_fin, statut, progress_pct, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->execute([
            $projectId,
            $donnees['nom'],
            $donnees['description'] ?: null,
            $ordre,
            $donnees['date_debut'] ?: null,
            $donnees['date_fin'] ?: null,
            $donnees['statut'] ?? 'non_demarre',
            $donnees['progress_pct'] ?? 0,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function listerJalons(int $projectId): array {
        $stmt = $this->pdo->prepare(
            "SELECT m.*, p.nom AS phase_nom
             FROM project_milestones m
             LEFT JOIN project_phases p ON m.phase_id = p.id
             WHERE m.project_id = ?
             ORDER BY m.date_butoir ASC, m.id ASC"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function ajouterJalon(int $projectId, array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_milestones
             (project_id, phase_id, titre, description, date_butoir, statut, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->execute([
            $projectId,
            $donnees['phase_id'] ?: null,
            $donnees['titre'],
            $donnees['description'] ?: null,
            $donnees['date_butoir'],
            $donnees['statut'] ?? 'non_demarre',
            $donnees['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function changerStatutJalon(int $jalonId, string $statut): bool {
        $data = ['statut' => $statut];
        if ($statut === 'atteint') {
            $sql = "UPDATE project_milestones SET statut = ?, atteint_at = NOW(), updated_at = NOW() WHERE id = ?";
        } else {
            $sql = "UPDATE project_milestones SET statut = ?, atteint_at = NULL, updated_at = NOW() WHERE id = ?";
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$data['statut'], $jalonId]);
    }

    public function listerTaches(int $projectId): array {
        $stmt = $this->pdo->prepare(
            "SELECT t.*, p.nom AS phase_nom, u.nom AS assigne_nom
             FROM project_tasks t
             LEFT JOIN project_phases p ON t.phase_id = p.id
             LEFT JOIN users u ON t.assigned_to = u.id
             WHERE t.project_id = ?
             ORDER BY
                CASE t.priorite WHEN 'haute' THEN 1 WHEN 'moyenne' THEN 2 ELSE 3 END,
                t.deadline IS NULL,
                t.deadline ASC,
                t.id DESC"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function ajouterTache(int $projectId, array $donnees): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_tasks
             (project_id, phase_id, assigned_to, titre, description, deadline, priorite, statut, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->execute([
            $projectId,
            $donnees['phase_id'] ?: null,
            $donnees['assigned_to'] ?: null,
            $donnees['titre'],
            $donnees['description'] ?: null,
            $donnees['deadline'] ?: null,
            $donnees['priorite'] ?? 'moyenne',
            $donnees['statut'] ?? 'a_faire',
            $donnees['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function changerStatutTache(int $taskId, string $statut): bool {
        if ($statut === 'termine') {
            $sql = "UPDATE project_tasks SET statut = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?";
        } else {
            $sql = "UPDATE project_tasks SET statut = ?, completed_at = NULL, updated_at = NOW() WHERE id = ?";
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$statut, $taskId]);
    }

    /**
     * Retourne les liaisons jalon-taches pour un projet.
     * Format: [milestone_id => ['task_ids' => int[], 'total' => int, 'pending' => int]]
     */
    public function listerRelationsJalonTaches(int $projectId): array {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT mt.milestone_id,
                        GROUP_CONCAT(mt.task_id ORDER BY mt.task_id) AS task_ids,
                        COUNT(*) AS total,
                        SUM(CASE WHEN t.statut <> 'termine' THEN 1 ELSE 0 END) AS pending
                 FROM project_milestone_tasks mt
                 JOIN project_milestones m ON m.id = mt.milestone_id
                 JOIN project_tasks t ON t.id = mt.task_id
                 WHERE m.project_id = ?
                 GROUP BY mt.milestone_id"
            );
            $stmt->execute([$projectId]);

            $map = [];
            foreach ($stmt->fetchAll() as $row) {
                $ids = [];
                if (!empty($row['task_ids'])) {
                    $ids = array_map('intval', explode(',', (string)$row['task_ids']));
                }
                $map[(int)$row['milestone_id']] = [
                    'task_ids' => $ids,
                    'total' => (int)$row['total'],
                    'pending' => (int)$row['pending'],
                ];
            }

            return $map;
        } catch (PDOException $e) {
            // Si la migration de liaison n'est pas encore appliquee, on reste compatible.
            return [];
        }
    }

    /**
     * Remplace l'ensemble des taches liees a un jalon pour un projet donne.
     */
    public function definirTachesJalon(int $projectId, int $milestoneId, array $taskIds): bool {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM project_milestones WHERE id = ? AND project_id = ?");
            $stmt->execute([$milestoneId, $projectId]);
            if ((int)$stmt->fetchColumn() === 0) {
                return false;
            }

            $taskIds = array_values(array_unique(array_filter(array_map('intval', $taskIds), static function ($id) {
                return $id > 0;
            })));

            if (!empty($taskIds)) {
                $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
                $params = $taskIds;
                $params[] = $projectId;

                $stmt = $this->pdo->prepare(
                    "SELECT COUNT(*) FROM project_tasks WHERE id IN ($placeholders) AND project_id = ?"
                );
                $stmt->execute($params);

                if ((int)$stmt->fetchColumn() !== count($taskIds)) {
                    return false;
                }
            }

            $this->pdo->beginTransaction();

            $stmtDelete = $this->pdo->prepare("DELETE FROM project_milestone_tasks WHERE milestone_id = ?");
            $stmtDelete->execute([$milestoneId]);

            if (!empty($taskIds)) {
                $stmtInsert = $this->pdo->prepare(
                    "INSERT INTO project_milestone_tasks (milestone_id, task_id, created_at)
                     VALUES (?, ?, NOW())"
                );
                foreach ($taskIds as $taskId) {
                    $stmtInsert->execute([$milestoneId, $taskId]);
                }
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    public function listerLiens(int $projectId): array {
        $stmt = $this->pdo->prepare(
            "SELECT l.*, u.nom AS createur_nom
             FROM project_links l
             LEFT JOIN users u ON l.created_by = u.id
             WHERE l.project_id = ?
             ORDER BY l.created_at DESC"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function ajouterLien(int $projectId, array $donnees): bool {
        $stmt = $this->pdo->prepare(
            "INSERT INTO project_links
             (project_id, module_type, entity_id, label, lien, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );

        return $stmt->execute([
            $projectId,
            $donnees['module_type'],
            (int)$donnees['entity_id'],
            $donnees['label'] ?: null,
            $donnees['lien'],
            $donnees['created_by'] ?? null,
        ]);
    }

    public function statistiquesProjet(int $projectId): array {
        $stats = [
            'total_taches' => 0,
            'taches_terminees' => 0,
            'taches_en_retard' => 0,
            'total_jalons' => 0,
            'jalons_atteints' => 0,
            'jalons_en_retard' => 0,
            'progress_global_pct' => 0.0,
            'progress_phases_pct' => 0.0,
        ];

        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total_taches,
                SUM(CASE WHEN statut = 'termine' THEN 1 ELSE 0 END) AS taches_terminees,
                SUM(CASE WHEN statut <> 'termine' AND deadline IS NOT NULL AND deadline < CURDATE() THEN 1 ELSE 0 END) AS taches_en_retard
             FROM project_tasks
             WHERE project_id = ?"
        );
        $stmt->execute([$projectId]);
        $row = $stmt->fetch() ?: [];
        $stats['total_taches'] = (int)($row['total_taches'] ?? 0);
        $stats['taches_terminees'] = (int)($row['taches_terminees'] ?? 0);
        $stats['taches_en_retard'] = (int)($row['taches_en_retard'] ?? 0);

        $stmt = $this->pdo->prepare(
            "SELECT
                COUNT(*) AS total_jalons,
                SUM(CASE WHEN statut = 'atteint' THEN 1 ELSE 0 END) AS jalons_atteints,
                SUM(CASE WHEN statut <> 'atteint' AND date_butoir < CURDATE() THEN 1 ELSE 0 END) AS jalons_en_retard
             FROM project_milestones
             WHERE project_id = ?"
        );
        $stmt->execute([$projectId]);
        $row = $stmt->fetch() ?: [];
        $stats['total_jalons'] = (int)($row['total_jalons'] ?? 0);
        $stats['jalons_atteints'] = (int)($row['jalons_atteints'] ?? 0);
        $stats['jalons_en_retard'] = (int)($row['jalons_en_retard'] ?? 0);

        if ($stats['total_taches'] > 0) {
            $stats['progress_global_pct'] = round(($stats['taches_terminees'] / $stats['total_taches']) * 100, 1);
        }

        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(AVG(progress_pct), 0) FROM project_phases WHERE project_id = ?"
        );
        $stmt->execute([$projectId]);
        $stats['progress_phases_pct'] = round((float)$stmt->fetchColumn(), 1);

        return $stats;
    }

    public function statistiquesGlobales(): array {
        $stats = [
            'total_projets' => 0,
            'projets_en_cours' => 0,
            'projets_en_retard' => 0,
            'budget_total_usd' => 0.0,
        ];

        $stats['total_projets'] = (int)$this->pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
        $stats['projets_en_cours'] = (int)$this->pdo->query("SELECT COUNT(*) FROM projects WHERE statut = 'en_cours'")->fetchColumn();

        $stmt = $this->pdo->query(
            "SELECT COUNT(*)
             FROM projects
             WHERE statut <> 'termine'
               AND date_fin < CURDATE()"
        );
        $stats['projets_en_retard'] = (int)$stmt->fetchColumn();

        $stmt = $this->pdo->query(
            "SELECT COALESCE(SUM(CASE
                        WHEN devise = 'USD' THEN budget
                        WHEN devise = 'CDF' THEN budget / 2000
                        ELSE 0
                    END), 0)
             FROM projects"
        );
        $stats['budget_total_usd'] = (float)$stmt->fetchColumn();

        return $stats;
    }

    public function elementsCalendrier(int $annee, int $mois): array {
        $debut = sprintf('%04d-%02d-01', $annee, $mois);
        $fin = date('Y-m-t', strtotime($debut));

        $elements = [];

        $stmt = $this->pdo->prepare(
            "SELECT m.id, m.project_id, p.titre AS projet_titre, m.titre, m.date_butoir AS date_event, m.statut,
                    'jalon' AS type
             FROM project_milestones m
             JOIN projects p ON p.id = m.project_id
             WHERE m.date_butoir BETWEEN ? AND ?"
        );
        $stmt->execute([$debut, $fin]);
        foreach ($stmt->fetchAll() as $row) {
            $elements[] = $row;
        }

        $stmt = $this->pdo->prepare(
            "SELECT t.id, t.project_id, p.titre AS projet_titre, t.titre, t.deadline AS date_event, t.statut,
                    'tache' AS type
             FROM project_tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.deadline IS NOT NULL
               AND t.deadline BETWEEN ? AND ?"
        );
        $stmt->execute([$debut, $fin]);
        foreach ($stmt->fetchAll() as $row) {
            $elements[] = $row;
        }

        usort($elements, static function ($a, $b) {
            return strcmp((string)$a['date_event'], (string)$b['date_event']);
        });

        return $elements;
    }

    public function phaseMap(int $projectId): array {
        $rows = $this->listerPhases($projectId);
        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row['nom'];
        }
        return $map;
    }

    /**
     * Met a jour les dates d'un element pilote depuis le Gantt.
     */
    public function mettreAJourDatesDepuisGantt(int $projectId, string $entityType, int $entityId, string $startDate, string $endDate): bool {
        if ($entityId <= 0) {
            return false;
        }

        $startTs = strtotime($startDate);
        $endTs = strtotime($endDate);
        if ($startTs === false || $endTs === false) {
            return false;
        }
        $start = date('Y-m-d', $startTs);
        $end = date('Y-m-d', $endTs);
        if ($end < $start) {
            $end = $start;
        }

        switch ($entityType) {
            case 'phase':
                $stmt = $this->pdo->prepare(
                    "UPDATE project_phases
                     SET date_debut = ?, date_fin = ?, updated_at = NOW()
                     WHERE id = ? AND project_id = ?"
                );
                return $stmt->execute([$start, $end, $entityId, $projectId]);

            case 'task':
                // Le modele actuel stocke principalement deadline pour les taches.
                $stmt = $this->pdo->prepare(
                    "UPDATE project_tasks
                     SET deadline = ?, updated_at = NOW()
                     WHERE id = ? AND project_id = ?"
                );
                return $stmt->execute([$end, $entityId, $projectId]);

            case 'milestone':
                $stmt = $this->pdo->prepare(
                    "UPDATE project_milestones
                     SET date_butoir = ?, updated_at = NOW()
                     WHERE id = ? AND project_id = ?"
                );
                return $stmt->execute([$end, $entityId, $projectId]);

            default:
                return false;
        }
    }

    private function prochainOrdrePhase(int $projectId): int {
        $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(ordre_phase), 0) + 1 FROM project_phases WHERE project_id = ?");
        $stmt->execute([$projectId]);
        return (int)$stmt->fetchColumn();
    }
}
