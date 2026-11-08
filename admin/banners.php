<?php
// ===================================================
// Admin: Promotional Hero & Deals Banners Management
// ===================================================
require_once __DIR__ . '/includes/admin-header.php';

// Handle POST actions: Create, Edit, Toggle, Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $link = trim($_POST['link'] ?? 'menu.php');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $image_path = trim($_POST['image_url'] ?? '');

        // Handle image upload if provided
        if (isset($_FILES['banner_file']) && $_FILES['banner_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['banner_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $target_dir = __DIR__ . '/../assets/images/banners/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                $filename = 'banner_' . time() . '_' . rand(100, 999) . '.' . $ext;
                $target_file = $target_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    $image_path = 'assets/images/banners/' . $filename;
                }
            }
        }

        if (empty($title)) {
            set_flash('error', 'Banner title cannot be empty.');
        } else {
            if ($action === 'create') {
                if (empty($image_path)) {
                    $image_path = 'assets/images/hero/biryani.png'; // default fallback
                }
                $stmt = $pdo->prepare("INSERT INTO banners (title, subtitle, image, link, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $subtitle, $image_path, $link, $sort_order, $is_active]);
                set_flash('success', "Banner '{$title}' created successfully!");
            } else {
                $banner_id = (int)$_POST['banner_id'];
                if (!empty($image_path)) {
                    $stmt = $pdo->prepare("UPDATE banners SET title = ?, subtitle = ?, image = ?, link = ?, sort_order = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$title, $subtitle, $image_path, $link, $sort_order, $is_active, $banner_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE banners SET title = ?, subtitle = ?, link = ?, sort_order = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$title, $subtitle, $link, $sort_order, $is_active, $banner_id]);
                }
                set_flash('success', "Banner updated successfully!");
            }
        }
    } elseif ($action === 'toggle_active') {
        $banner_id = (int)$_POST['banner_id'];
        $pdo->prepare("UPDATE banners SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?")->execute([$banner_id]);
        set_flash('success', "Banner status toggled.");
    } elseif ($action === 'delete') {
        $banner_id = (int)$_POST['banner_id'];
        $pdo->prepare("DELETE FROM banners WHERE id = ?")->execute([$banner_id]);
        set_flash('success', "Banner removed permanently.");
    }
    header('Location: banners.php');
    exit;
}

$banners = $pdo->query("SELECT * FROM banners ORDER BY sort_order ASC, created_at DESC")->fetchAll();
?>

<div class="container" style="padding: 24px 20px; max-width: 1280px;">
    <!-- Top Bar -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 700; color: #0B192C; margin: 0 0 6px 0;">Promotional Banners & Deals Carousel</h1>
            <p style="color: #64748B; font-size: 14px; margin: 0;">Highlight festivals, top deals, discount banners, and seasonal specials on the storefront hero section.</p>
        </div>
        <button onclick="openCreateBannerModal()" class="btn btn-primary" style="padding: 10px 18px; font-size: 14px; font-weight: 600;">
            + Add Promotional Banner
        </button>
    </div>

    <!-- Banners Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px;">
        <?php if (empty($banners)): ?>
            <div style="grid-column: 1/-1; background: #fff; border-radius: 12px; padding: 40px; text-align: center; color: #94A3B8; border: 1px solid #E2E8F0;">
                No banners found. Click "+ Add Promotional Banner" to publish marketing assets.
            </div>
        <?php else: ?>
            <?php foreach ($banners as $b): ?>
                <div style="background: #fff; border-radius: 12px; border: 1px solid #E2E8F0; overflow: hidden; box-shadow: var(--shadow-sm); display: flex; flex-direction: column;">
                    <!-- Banner Visual Preview -->
                    <div style="height: 150px; background: #1E293B; position: relative; overflow: hidden;">
                        <img src="../<?php echo htmlspecialchars($b['image']); ?>" alt="<?php echo htmlspecialchars($b['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; opacity: 0.9;" onerror="this.src='../assets/images/hero/biryani.png'">
                        <div style="position: absolute; top: 10px; right: 10px; display: flex; gap: 6px;">
                            <span style="padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; <?php echo $b['is_active'] ? 'background: #10B981; color: #fff;' : 'background: #64748B; color: #fff;'; ?>">
                                <?php echo $b['is_active'] ? 'Active' : 'Hidden'; ?>
                            </span>
                            <span style="background: rgba(0,0,0,0.6); color: #fff; padding: 3px 8px; border-radius: 10px; font-size: 11px;">
                                Order: <?php echo $b['sort_order']; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Content Details -->
                    <div style="padding: 16px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <h3 style="margin: 0 0 6px 0; font-size: 16px; color: #0F172A; font-weight: 700;"><?php echo htmlspecialchars($b['title']); ?></h3>
                            <p style="margin: 0 0 10px 0; font-size: 13px; color: #64748B;"><?php echo htmlspecialchars($b['subtitle']); ?></p>
                            <div style="font-size: 11px; color: #2563EB; word-break: break-all;">
                                🔗 <code><?php echo htmlspecialchars($b['link']); ?></code>
                            </div>
                        </div>

                        <!-- Actions Strip -->
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid #F1F5F9;">
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="banner_id" value="<?php echo $b['id']; ?>">
                                <button type="submit" class="btn" style="padding: 5px 10px; font-size: 11px; font-weight: 600; <?php echo $b['is_active'] ? 'background: #FEF2F2; color: #DC2626;' : 'background: #F0FDF4; color: #16A34A;'; ?>">
                                    <?php echo $b['is_active'] ? 'Hide' : 'Publish'; ?>
                                </button>
                            </form>

                            <div style="display: flex; gap: 6px;">
                                <button type="button" onclick='openEditBannerModal(<?php echo json_encode($b); ?>)' class="btn" style="padding: 5px 12px; font-size: 11px; background: #EFF6FF; color: #2563EB;">
                                    Edit
                                </button>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Delete banner <?php echo htmlspecialchars($b['title']); ?>?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="banner_id" value="<?php echo $b['id']; ?>">
                                    <button type="submit" class="btn" style="padding: 5px 8px; font-size: 11px; background: #F1F5F9; color: #64748B;">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Create/Edit Banner -->
<div id="bannerModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: #fff; width: 100%; max-width: 520px; border-radius: 16px; padding: 24px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 id="bannerModalTitle" style="margin: 0; font-size: 18px; color: #0B192C;">Add Promotional Banner</h3>
            <button type="button" onclick="closeBannerModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #94A3B8;">&times;</button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" id="b_action" value="create">
            <input type="hidden" name="banner_id" id="b_id" value="">

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Banner Headline *</label>
                <input type="text" name="title" id="b_title" required placeholder="e.g. Royal Biryani Festival" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Subtitle / Tagline</label>
                <input type="text" name="subtitle" id="b_subtitle" placeholder="e.g. Up to 50% OFF on gourmet handi biryanis" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Target URL / Link</label>
                <input type="text" name="link" id="b_link" value="menu.php" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Image File Upload</label>
                <input type="file" name="banner_file" accept="image/*" style="width: 100%; padding: 8px; border: 1px dashed #CBD5E1; border-radius: 8px; font-size: 12px;">
            </div>

            <div style="margin-bottom: 14px;">
                <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Or Relative Image Path</label>
                <input type="text" name="image_url" id="b_image_url" placeholder="assets/images/hero/biryani.png" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #334155; margin-bottom: 4px;">Display Sort Order</label>
                    <input type="number" name="sort_order" id="b_sort_order" value="0" style="width: 100%; padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 13px;">
                </div>
                <div style="display: flex; align-items: center; padding-top: 18px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #334155; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="b_is_active" value="1" checked style="width: 16px; height: 16px;">
                        Active Status
                    </label>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeBannerModal()" class="btn" style="background: #E2E8F0; color: #334155;">Cancel</button>
                <button type="submit" class="btn btn-primary" id="b_submitBtn">Save Banner</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateBannerModal() {
    document.getElementById('bannerModalTitle').textContent = 'Add Promotional Banner';
    document.getElementById('b_action').value = 'create';
    document.getElementById('b_id').value = '';
    document.getElementById('b_title').value = '';
    document.getElementById('b_subtitle').value = '';
    document.getElementById('b_link').value = 'menu.php';
    document.getElementById('b_image_url').value = 'assets/images/hero/biryani.png';
    document.getElementById('b_sort_order').value = '0';
    document.getElementById('b_is_active').checked = true;
    document.getElementById('b_submitBtn').textContent = 'Create Banner';
    document.getElementById('bannerModal').style.display = 'flex';
}

function openEditBannerModal(banner) {
    document.getElementById('bannerModalTitle').textContent = 'Edit Banner: ' + banner.title;
    document.getElementById('b_action').value = 'update';
    document.getElementById('b_id').value = banner.id;
    document.getElementById('b_title').value = banner.title;
    document.getElementById('b_subtitle').value = banner.subtitle || '';
    document.getElementById('b_link').value = banner.link || 'menu.php';
    document.getElementById('b_image_url').value = banner.image;
    document.getElementById('b_sort_order').value = banner.sort_order;
    document.getElementById('b_is_active').checked = (banner.is_active == 1);
    document.getElementById('b_submitBtn').textContent = 'Update Banner';
    document.getElementById('bannerModal').style.display = 'flex';
}

function closeBannerModal() {
    document.getElementById('bannerModal').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
