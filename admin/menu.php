<?php
// ===================================================
// Menu Items Management - Admin Control Center
// ===================================================
include 'includes/admin-header.php';

// Handle dish delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $delStmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
    $delStmt->execute([$delId]);
    set_flash('success', 'Menu item deleted successfully.');
    header("Location: menu.php");
    exit;
}

// Category filter
$catFilter = (int)($_GET['cat'] ?? 0);
$search = trim($_GET['search'] ?? '');

$sql = "SELECT m.*, r.name as restaurant_name, c.name as category_name 
        FROM menu_items m 
        LEFT JOIN restaurants r ON m.restaurant_id = r.id 
        LEFT JOIN categories c ON m.category_id = c.id 
        WHERE 1=1";
$params = [];

if ($catFilter > 0) {
    $sql .= " AND m.category_id = ?";
    $params[] = $catFilter;
}

if ($search !== '') {
    $sql .= " AND (m.name LIKE ? OR m.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY m.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, name ASC")->fetchAll();
?>

<div style="padding: 30px 0 60px;">
    <div class="container">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: #0B192C; margin: 0 0 4px;">
                    Platform Menu & Catalog Control 🍽️
                </h1>
                <p style="color: #64748B; font-size: 14px; margin: 0;">
                    Manage dish pricing, serving sizes, categories, veg/non-veg status, and availability across all restaurants
                </p>
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="menu-edit.php" style="padding: 10px 20px; background: #059669; color: #fff; border-radius: 20px; font-weight: 700; text-decoration: none; font-size: 13px; display: inline-flex; align-items: center; gap: 6px;">
                    <span>+</span> Add Dish
                </a>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div style="background: #FFFFFF; border-radius: 14px; border: 1px solid var(--light-border); padding: 16px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; box-shadow: var(--shadow-sm);">
            <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; flex: 1;">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search dish name or ingredient..." 
                       style="padding: 8px 14px; border-radius: 20px; border: 1px solid var(--light-border); font-size: 13px; min-width: 240px;">

                <select name="cat" onchange="this.form.submit()" style="padding: 8px 14px; border-radius: 20px; border: 1px solid var(--light-border); font-size: 13px; background: #fff;">
                    <option value="0">All Categories (<?php echo count($items); ?>)</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($catFilter == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" style="padding: 8px 16px; background: #1E3E62; color: #fff; border-radius: 20px; border: none; font-size: 12px; font-weight: 600; cursor: pointer;">
                    Filter
                </button>
                <?php if ($catFilter || $search): ?>
                    <a href="menu.php" style="font-size: 12px; color: #DC2626; text-decoration: none; font-weight: 600;">Reset Filters</a>
                <?php endif; ?>
            </form>

            <span style="font-size: 13px; color: #64748B;">Showing <strong><?php echo count($items); ?></strong> dishes</span>
        </div>

        <!-- Dishes Table -->
        <div style="background: #FFFFFF; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
            <div class="table-responsive">
                <table class="zayka-table">
                    <thead>
                        <tr>
                            <th>Dish Image</th>
                            <th>Dish Name</th>
                            <th>Category</th>
                            <th>Serving Size</th>
                            <th>Price</th>
                            <th>Type</th>
                            <th>Stock</th>
                            <th>Bestseller</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $it): ?>
                            <tr>
                                <td style="width: 70px;">
                                    <img src="../<?php echo htmlspecialchars($it['image'] ?: 'assets/images/categories/biryani.png'); ?>" 
                                         alt="<?php echo htmlspecialchars($it['name']); ?>" 
                                         style="width: 55px; height: 50px; border-radius: 8px; object-fit: cover;">
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: #0B192C; font-size: 15px;">
                                        <?php echo htmlspecialchars($it['name']); ?>
                                    </div>
                                    <div style="font-size: 11px; color: #64748B; max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($it['description']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-size: 12px; color: #475569; font-weight: 600; background: #F1F5F9; padding: 3px 8px; border-radius: 6px;">
                                        <?php echo htmlspecialchars($it['category_name'] ?: 'Unassigned'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($it['serving_size'])): ?>
                                        <span style="font-size: 12px; font-weight: 600; color: #046C4E; background: #DEF7EC; border: 1px solid #BCF0DA; padding: 3px 8px; border-radius: 6px; white-space: nowrap;">
                                            <?php echo htmlspecialchars($it['serving_size']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #94A3B8; font-style: italic;">
                                            Serving size not specified
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color: #059669; font-size: 14px;"><?php echo format_price($it['price']); ?></strong>
                                    <?php if ($it['discount_price']): ?>
                                        <div style="font-size: 11px; color: #16A34A; font-weight: 600;">Offer: <?php echo format_price($it['discount_price']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo $it['is_veg'] ? 'veg-icon' : 'non-veg-icon'; ?>"></span>
                                    <span style="font-size: 12px; margin-left: 6px; font-weight: 600; color: #475569;"><?php echo $it['is_veg'] ? 'Veg' : 'Non-Veg'; ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; <?php echo $it['is_available'] ? 'background:#DEF7EC; color:#046C4E;' : 'background:#FDE8E8; color:#9B1C1C;'; ?>">
                                        <?php echo $it['is_available'] ? 'In Stock' : 'Out of Stock'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($it['is_bestseller']): ?>
                                        <span style="font-size: 11px; font-weight: 700; background: #FEF3C7; color: #D97706; padding: 2px 8px; border-radius: 4px;">★ YES</span>
                                    <?php else: ?>
                                        <span style="color: #94A3B8; font-size: 12px;">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 8px;">
                                        <a href="menu-edit.php?id=<?php echo $it['id']; ?>" style="padding: 5px 12px; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #1E293B;">
                                            ✏️ Edit
                                        </a>
                                        <a href="menu.php?delete=<?php echo $it['id']; ?>" onclick="return confirm('Are you sure you want to delete this dish from the catalog?')" style="padding: 5px 10px; background: #FDE8E8; border: 1px solid #F87171; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; color: #9B1C1C;">
                                            ✕
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: #64748B;">
                                    No dishes match the selected filter. Click "+ Add Dish" above to create one.
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
