<?php
/**
 * Sweet Choice - Admin Reviews Management
 */

$adminTitle = 'Manage Reviews';
require_once __DIR__ . '/header.php';

// Handle Actions (Approve, Unapprove, Delete)
if (isset($_GET['action'])) {
    $revId = (int)($_GET['id'] ?? 0);
    if (verifyCsrfToken($_GET['csrf_token'] ?? '') && $revId > 0) {
        if ($_GET['action'] === 'approve') {
            $upd = $pdo->prepare("UPDATE reviews SET is_approved = 1 WHERE id = ?");
            $upd->execute([$revId]);
            setFlash('success', "Review #{$revId} approved.");
        } elseif ($_GET['action'] === 'unapprove') {
            $upd = $pdo->prepare("UPDATE reviews SET is_approved = 0 WHERE id = ?");
            $upd->execute([$revId]);
            setFlash('info', "Review #{$revId} hidden.");
        } elseif ($_GET['action'] === 'delete') {
            $del = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $del->execute([$revId]);
            setFlash('success', "Review #{$revId} deleted.");
        }
    }
    header("Location: reviews.php");
    exit;
}

$reviews = $pdo->query("SELECT r.*, p.name_en AS product_name, u.name AS user_name, u.email AS user_email 
                        FROM reviews r 
                        JOIN products p ON r.product_id = p.id 
                        JOIN users u ON r.user_id = u.id 
                        ORDER BY r.id DESC")->fetchAll();
?>

<div class="admin-card">
    <div style="margin-bottom:20px;">
        <h2 style="font-size:1.4rem;">💬 Customer Reviews & Testimonials (<?= count($reviews); ?>)</h2>
        <p style="font-size:0.85rem; color:var(--color-secondary-text);">Moderate customer reviews on dessert products.</p>
    </div>

    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Dessert</th>
                <th>Customer</th>
                <th>Rating</th>
                <th>Review Comment</th>
                <th>Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reviews)): ?>
                <tr><td colspan="8" style="text-align:center; color:var(--color-secondary-text);">No reviews submitted yet.</td></tr>
            <?php else: ?>
                <?php foreach ($reviews as $rev): ?>
                    <tr>
                        <td>#<?= $rev['id']; ?></td>
                        <td><strong><a href="../public/product.php?id=<?= $rev['product_id']; ?>" target="_blank"><?= e($rev['product_name']); ?></a></strong></td>
                        <td>
                            <div><?= e($rev['user_name']); ?></div>
                            <div style="font-size:0.75rem; color:var(--color-secondary-text);"><?= e($rev['user_email']); ?></div>
                        </td>
                        <td><?= renderStars((float)$rev['rating']); ?></td>
                        <td style="max-width:320px;">
                            <div style="font-size:0.88rem; line-height:1.5;"><?= nl2br(e($rev['comment'])); ?></div>
                        </td>
                        <td><?= date('Y/m/d', strtotime($rev['created_at'])); ?></td>
                        <td>
                            <span class="badge <?= $rev['is_approved'] ? 'badge-available' : 'badge-sold-out'; ?>">
                                <?= $rev['is_approved'] ? 'Approved' : 'Pending'; ?>
                            </span>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px;">
                                <?php if ($rev['is_approved']): ?>
                                    <a href="reviews.php?action=unapprove&id=<?= $rev['id']; ?>&csrf_token=<?= getCsrfToken(); ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px; font-size:0.75rem;">Hide</a>
                                <?php else: ?>
                                    <a href="reviews.php?action=approve&id=<?= $rev['id']; ?>&csrf_token=<?= getCsrfToken(); ?>" class="btn btn-primary btn-sm" style="padding:4px 8px; font-size:0.75rem;">Approve</a>
                                <?php endif; ?>
                                <a href="reviews.php?action=delete&id=<?= $rev['id']; ?>&csrf_token=<?= getCsrfToken(); ?>" 
                                   class="btn btn-secondary btn-sm" 
                                   style="padding:4px 8px; font-size:0.75rem; color:var(--color-danger);"
                                   onclick="return confirm('Delete this review?');">Delete</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
