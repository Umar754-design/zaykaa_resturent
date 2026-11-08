<?php
// ===================================================
// Menu Management - Restaurant Partner
// ===================================================
include 'includes/header.php';

$restId = (int)$currentRestaurant['id'];

// Handle item delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $delStmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ? AND restaurant_id = ?");
    $delStmt->execute([$delId, $restId]);
    set_flash('success', 'Menu item deleted successfully.');
    header("Location: menu.php");
    exit;
}

// Fetch all items for this restaurant
$stmt = $pdo->prepare("SELECT m.*, c.name as category_name, mc.name as menu_cat_name 
                       FROM menu_items m 
                       LEFT JOIN categories c ON m.category_id = c.id 
                       LEFT JOIN menu_categories mc ON m.menu_category_id = mc.id 
                       WHERE m.restaurant_id = ? 
                       ORDER BY m.id DESC");
$stmt->execute([$restId]);
$items = $stmt->fetchAll();
?>

<div style="padding: 30px 0 60px;">
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--primary-green); margin: 0 0 4px;">
                    Menu & Inventory Management 🍽️
                </h1>
                <p style="color: #666; font-size: 14px; margin: 0;">
                    Manage dish pricing, availability, veg/non-veg flags, and customization add-ons
                </p>
            </div>

            <a href="menu-item-edit.php" style="padding: 10px 22px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 13px;">
                + Add New Dish
            </a>
        </div>

        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
            <div class="table-responsive">
                <table class="zayka-table">
                    <thead>
                        <tr>
                            <th>Dish Image</th>
                            <th>Dish Name</th>
                            <th>Category</th>
                            <th>Serving</th>
                            <th>Price</th>
                            <th>Type</th>
                            <th>Stock Status</th>
                            <th>Bestseller</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $it): ?>
                            <tr>
                                <td style="width: 70px;">
                                    <img src="../<?php echo htmlspecialchars($it['image']); ?>" alt="<?php echo htmlspecialchars($it['name']); ?>" style="width: 55px; height: 50px; border-radius: 8px; object-fit: cover;">
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #333; font-size: 15px;"><?php echo htmlspecialchars($it['name']); ?></div>
                                    <div style="font-size: 11px; color: #777; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($it['description']); ?>
                                    </div>
                                </td>
                                <td style="font-size: 13px; color: #555;">
                                    <?php echo htmlspecialchars($it['menu_cat_name'] ?: $it['category_name'] ?: 'Main Menu'); ?>
                                </td>
                                <td>
                                    <?php if (!empty($it['serving_size'])): ?>
                                        <span style="font-size: 12px; font-weight: 600; color: var(--primary-green); background: #FAF8F2; border: 1px solid #EFE9D9; padding: 2px 8px; border-radius: 6px; white-space: nowrap;">
                                            <?php echo htmlspecialchars($it['serving_size']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #9CA3AF; font-style: italic;">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: var(--primary-green);"><?php echo format_price($it['price']); ?></strong>
                                    <?php if ($it['discount_price']): ?>
                                        <div style="font-size: 11px; color: #16A34A; font-weight: bold;">Offer: <?php echo format_price($it['discount_price']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo $it['is_veg'] ? 'veg-icon' : 'non-veg-icon'; ?>"></span>
                                    <span style="font-size: 12px; margin-left: 6px; font-weight: 600;"><?php echo $it['is_veg'] ? 'Veg' : 'Non-Veg'; ?></span>
                                </td>
                                <td>
                                    <!-- Instant AJAX in-stock switch -->
                                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                        <input type="checkbox" <?php echo $it['is_available'] ? 'checked' : ''; ?> 
                                               onchange="toggleItemStock(<?php echo $it['id']; ?>, this.checked)" style="width: 18px; height: 18px; accent-color: #059669;">
                                        <span style="font-size: 12px; font-weight: 600; color: <?php echo $it['is_available'] ? '#059669' : '#DC2626'; ?>;" id="stockLabel-<?php echo $it['id']; ?>">
                                            <?php echo $it['is_available'] ? 'In Stock' : 'Out of Stock'; ?>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <?php if ($it['is_bestseller']): ?>
                                        <span style="font-size: 11px; font-weight: 700; background: #FEF3C7; color: #D97706; padding: 2px 8px; border-radius: 4px;">★ YES</span>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 12px;">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="menu-item-edit.php?id=<?php echo $it['id']; ?>" style="padding: 5px 12px; background: #FAF8F2; border: 1px solid var(--light-border); border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #333;">
                                            ✏️ Edit
                                        </a>
                                        <a href="menu.php?delete=<?php echo $it['id']; ?>" onclick="return confirm('Are you sure you want to delete this dish?')" style="padding: 5px 12px; background: #FDE8E8; border: 1px solid #F87171; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #9B1C1C;">
                                            ✕
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: #888;">
                                    No menu items added yet. Click "+ Add New Dish" above to create your first offering!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
function toggleItemStock(itemId, isAvailable) {
    const formData = new FormData();
    formData.append('type', 'menu_item_availability');
    formData.append('item_id', itemId);
    formData.append('is_available', isAvailable ? '1' : '0');

    fetch('../api/toggle-status.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                const lbl = document.getElementById('stockLabel-' + itemId);
                if (lbl) {
                    lbl.innerText = isAvailable ? 'In Stock' : 'Out of Stock';
                    lbl.style.color = isAvailable ? '#059669' : '#DC2626';
                }
            } else {
                showToast(data.message, 'error');
            }
        });
}
</script>

<?php include 'includes/footer.php'; ?>
