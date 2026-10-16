                        <?php foreach ($courriers as $c): ?>
                            <tr data-register-state="<?= e(strtolower($c['statut'])) ?>" data-register-complete="<?= in_array($c['statut'], ['Termine', 'Clos', 'Archive'], true) ? 'true' : 'false' ?>">
                                <td>
                                    <a href="<?= APP_URL ?>/courrier/voir/<?= $c['id'] ?>" class="fw-semibold text-decoration-none">
                                        <?= e($c['numero_unique']) ?>
                                    </a>
                                </td>
                                <td><?= e($c['reference_document'] ?? '—') ?></td>
                                <td><?= e($c['expediteur']) ?></td>
                                <td class="text-truncate" style="max-width: 220px;" title="<?= e($c['objet']) ?>">
                                    <?= e($c['objet']) ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= badgeUrgence($c['urgence']) ?>">
                                        <?= ucfirst(e($c['urgence'])) ?>
                                    </span>
                                </td>
                                <td data-register-state="<?= e(strtolower($c['statut'])) ?>">
                                    <span class="badge bg-<?= badgeStatut($c['statut']) ?>">
                                        <?= e($c['statut']) ?>
                                    </span>
                                </td>
                                <td><?= formatDate($c['date_reception']) ?></td>
                                <td>
                                    <?php if (!empty($c['deadline'])): ?>
                                        <?php
                                        $jours = (int)ceil((strtotime($c['deadline']) - time()) / 86400);
                                        $cls   = $jours < 0 ? 'text-danger fw-bold' : ($jours <= 3 ? 'text-warning fw-semibold' : '');
                                        ?>
                                        <span class="<?= $cls ?>"><?= formatDate($c['deadline']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($c['service_destinataire'] ?? '—') ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= APP_URL ?>/courrier/voir/<?= $c['id'] ?>" class="btn btn-outline-primary" title="Voir">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (in_array($_SESSION['user_role'], ['reception','admin','chef_service'])): ?>
                                            <a href="<?= APP_URL ?>/courrier/assigner/<?= $c['id'] ?>" class="btn btn-outline-warning" title="Assigner">
                                                <i class="bi bi-person-plus"></i>
                                            </a>
                                            <a href="<?= APP_URL ?>/courrier/modifier/<?= $c['id'] ?>" class="btn btn-outline-secondary" title="Modifier">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
