<?php
// ===================================================
// Admin Notifications API — polled by the bell in the admin header
// GET  ?action=list[&since=<id>]   -> unread count + latest items
// POST action=mark_read&id=<id>    -> mark one as read
// POST action=mark_all             -> mark everything read
// ===================================================
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}
ensure_admin_notifications_table();

$action = $_REQUEST['action'] ?? 'list';
$types = admin_notification_types();

if ($action === 'mark_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE id = ?")->execute([(int) ($_POST['id'] ?? 0)]);
    json_response(['success' => true]);
}

if ($action === 'mark_all' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->exec("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");
    json_response(['success' => true]);
}

$unread = (int) $pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
$rows = $pdo->query("SELECT * FROM admin_notifications ORDER BY id DESC LIMIT 20")->fetchAll();
$latestId = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM admin_notifications")->fetchColumn();

$items = array_map(function ($n) use ($types) {
    $ts = strtotime($n['created_at']);
    $ago = time() - $ts;
    if ($ago < 60) {
        $when = 'just now';
    } elseif ($ago < 3600) {
        $when = floor($ago / 60) . ' min ago';
    } elseif ($ago < 86400) {
        $when = floor($ago / 3600) . ' hr ago';
    } else {
        $when = date('d M, h:i A', $ts);
    }
    return [
        'id'      => (int) $n['id'],
        'type'    => $n['type'],
        'icon'    => $types[$n['type']]['icon'] ?? '🔔',
        'title'   => $n['title'],
        'message' => $n['message'],
        'link'    => $n['link'],
        'is_read' => (bool) $n['is_read'],
        'when'    => $when,
    ];
}, $rows);

json_response(['success' => true, 'unread' => $unread, 'latest_id' => $latestId, 'items' => $items]);
