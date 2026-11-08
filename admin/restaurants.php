<?php
// ===================================================
// Restaurant Management - Admin Control Center
// ===================================================
include 'includes/admin-header.php';

// Handle quick actions: approve, suspend, delete
if (isset($_GET['action']) && isset($_GET['id'])) {
    $act = $_GET['action'];
    $rId = (int)$_GET['id'];

    if ($act === 'approve') {
        $pdo->prepare("UPDATE restaurants SET is_approved = 1 WHERE id = ?")->execute([$rId]);
        set_flash('success', 'Restaurant approved and live on platform!');
    } elseif ($act === 'suspend') {
        $pdo->prepare("UPDATE restaurants SET is_approved = 0, status = 'closed' WHERE id = ?")->execute([$rId]);
        set_flash('warning', 'Restaurant suspended from platform.');
    } elseif ($act === 'delete') {
        $pdo->prepare("DELETE FROM restaurants WHERE id = ?")->execute([$rId]);
        set_flash('error', 'Restaurant deleted permanently.');
    }
    header("Location: restaurants.php");
    exit;
}

$restaurants = $pdo->query("SELECT r.*, u.name as owner_name, u.email as owner_email, u.phone as owner_phone,
                                   COUNT(o.id) as total_orders, COALESCE(SUM(o.total_amount), 0) as total_sales
                            FROM restaurants r 
                            JOIN users u ON r.owner_id = u.id 
                            LEFT JOIN orders o ON r.id = o.restaurant_id 
                            GROUP BY r.id 
                            ORDER BY r.id DESC")->fetchAll();
?>

<div style="padding: 30px 0 60px;">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #0B192C; margin: 0 0 4px;">
                    Restaurant Partners Directory
                </h1>
                <p style="color: #64748B; font-size: 14px; margin: 0;">
                    Manage merchant onboarding, approvals, commission percentages, and storefront visibility
                </p>
            </div>

            <a href="restaurant-edit.php" style="padding: 10px 20px; background: #059669; color: #fff; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 13px;">
                + Add Restaurant
            </a>
        </div>

        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
            <div class="table-responsive">
                <table class="zayka-table">
                    <thead>
                        <tr>
                            <th>Restaurant</th>
                            <th>Owner / Contact</th>
                            <th>City & Radius</th>
                            <th>Commission</th>
                            <th>Orders & GMV</th>
                            <th>Rating</th>
                            <th>Approval Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($restaurants)): ?>
                            <?php foreach ($restaurants as $r): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 700; color: #0B192C; font-size: 15px;">
                                        <?php echo htmlspecialchars($r['name']); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #64748B;"><?php echo htmlspecialchars($r['cuisines']); ?></div>
                                    <?php if ($r['is_pure_veg']): ?>
                                        <span style="font-size: 10px; background: #DEF7EC; color: #046C4E; font-weight: 700; padding: 1px 6px; border-radius: 4px;">100% PURE VEG</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 13px;">
                                    <strong><?php echo htmlspecialchars($r['owner_name']); ?></strong><br>
                                    <span style="color: #64748B; font-size: 12px;"><?php echo htmlspecialchars($r['owner_phone']); ?></span>
                                </td>
                                <td style="font-size: 13px;">
                                    <?php echo htmlspecialchars($r['city']); ?><br>
                                    <span style="font-size: 11px; color: #64748B;">Radius: <?php echo (float)$r['delivery_radius_km']; ?> km</span>
                                </td>
                                <td>
                                    <strong style="color: #059669;"><?php echo (float)$r['commission_rate']; ?>%</strong>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #0B192C;"><?php echo format_price($r['total_sales']); ?></div>
                                    <div style="font-size: 11px; color: #64748B;"><?php echo (int)$r['total_orders']; ?> orders</div>
                                </td>
                                <td>
                                    <span class="rating-pill">★ <?php echo number_format($r['rating'], 1); ?></span>
                                </td>
                                <td>
                                    <?php if ($r['is_approved']): ?>
                                        <span style="font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; background: #DEF7EC; color: #046C4E;">
                                            APPROVED
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 12px; background: #FEF3C7; color: #B45309;">
                                            PENDING / SUSPENDED
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <a href="restaurant-edit.php?id=<?php echo $r['id']; ?>" style="padding: 5px 10px; background: #F1F5F9; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #334155;">
                                            Edit
                                        </a>
                                        <?php if ($r['is_approved']): ?>
                                            <a href="restaurants.php?action=suspend&id=<?php echo $r['id']; ?>" style="padding: 5px 10px; background: #FEF3C7; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #B45309;">
                                                Suspend
                                            </a>
                                        <?php else: ?>
                                            <a href="restaurants.php?action=approve&id=<?php echo $r['id']; ?>" style="padding: 5px 10px; background: #DEF7EC; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #046C4E;">
                                                Approve
                                            </a>
                                        <?php endif; ?>
                                        <a href="restaurants.php?action=delete&id=<?php echo $r['id']; ?>" onclick="return confirm('Permanently delete restaurant?')" style="padding: 5px 10px; background: #FDE8E8; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #9B1C1C;">
                                            ✕
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #888; padding: 30px;">
                                    No restaurants registered yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>
