<?php
/** Référentiel partagé des départements, adossé à la table historique services. */
class Department {
    private PDO $pdo;
    public function __construct() { $this->pdo = getDB(); }

    public function lister(bool $actifsSeulement = true): array {
        return $this->pdo->query("SELECT s.*, (SELECT COUNT(*) FROM users u WHERE u.service_id=s.id) AS utilisateurs FROM services s"
            . ($actifsSeulement ? ' WHERE s.actif=1' : '') . ' ORDER BY s.nom')->fetchAll();
    }
    public function trouver(int $id) {
        $stmt = $this->pdo->prepare('SELECT * FROM services WHERE id=?');
        $stmt->execute([$id]); return $stmt->fetch();
    }
    public function trouverParNom(string $nom) {
        $stmt = $this->pdo->prepare('SELECT * FROM services WHERE nom=? LIMIT 1');
        $stmt->execute([trim($nom)]); return $stmt->fetch();
    }
    public function enregistrer(string $nom, string $description, ?int $id = null): int {
        $nom = trim($nom); $description = trim($description);
        if ($nom === '' || mb_strlen($nom) > 100) throw new InvalidArgumentException('Le nom doit contenir entre 1 et 100 caractères.');
        if (mb_strlen($description) > 2000) throw new InvalidArgumentException('La description est limitée à 2 000 caractères.');
        $this->pdo->beginTransaction();
        try {
            $existant = $this->trouverParNom($nom);
            if ($existant && (int)$existant['id'] !== $id) throw new InvalidArgumentException('Ce département existe déjà, éventuellement dans les départements inactifs.');
            if ($id !== null) {
                if (!$this->trouver($id)) throw new InvalidArgumentException('Département introuvable.');
                $this->pdo->prepare('UPDATE services SET nom=?, description=? WHERE id=?')->execute([$nom,$description,$id]);
                // Le libellé courant suit le référentiel ; les demandes conservent leur libellé historique.
                $this->pdo->prepare('UPDATE users SET service=? WHERE service_id=?')->execute([$nom,$id]);
            } else {
                $this->pdo->prepare('INSERT INTO services (nom,description,actif,created_at) VALUES (?,?,1,NOW())')->execute([$nom,$description]);
                $id = (int)$this->pdo->lastInsertId();
            }
            $this->pdo->commit(); return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            if ($e instanceof PDOException && $e->getCode() === '23000') throw new InvalidArgumentException('Ce département existe déjà.');
            throw $e;
        }
    }
    public function changerEtat(int $id, bool $actif): bool {
        $stmt = $this->pdo->prepare('UPDATE services SET actif=? WHERE id=?');
        $stmt->execute([$actif ? 1 : 0,$id]); return $stmt->rowCount() > 0;
    }
}
