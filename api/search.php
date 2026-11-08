<?php
// ===================================================
// Real-Time Search API - Zaykaa Restaurant Platform
// Dynamic Autocomplete & Smart Alphabetical Search
// ===================================================

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once dirname(__DIR__) . '/config/database.php';

$query = trim($_GET['q'] ?? $_GET['query'] ?? '');

if ($query === '' || mb_strlen($query) === 0) {
    echo json_encode([
        'success' => true,
        'query'   => '',
        'count'   => 0,
        'results' => []
    ]);
    exit;
}

try {
    $exactPrefix = $query . '%';
    $wordPrefix  = '% ' . $query . '%';
    $contains    = '%' . $query . '%';

    // Ranked multi-tier SQL matching:
    // 1. Dish name starts with query (e.g. "b" -> "Butter Chicken", "Biryani")
    // 2. Word within dish name starts with query (e.g. "b" -> "Chicken Dum Biryani")
    // 3. Category starts with query (e.g. "b" -> "Biryani" category)
    // 4. Dish name contains query
    // 5. Category or description contains query
    $sql = "SELECT m.id, m.name, m.description, m.price, m.discount_price, m.serving_size, m.image, m.is_veg, m.is_bestseller,
                   c.name AS cat_name, c.slug AS cat_slug,
                   CASE 
                       WHEN m.name LIKE :p1 THEN 1
                       WHEN m.name LIKE :p2 THEN 2
                       WHEN c.name LIKE :p3 THEN 3
                       WHEN m.name LIKE :p4 THEN 4
                       WHEN c.name LIKE :p5 THEN 5
                       ELSE 6
                   END AS relevance_rank
            FROM menu_items m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE m.is_available = 1
              AND (
                  m.name LIKE :p6 
                  OR m.description LIKE :p7 
                  OR c.name LIKE :p8
              )
            ORDER BY relevance_rank ASC, m.is_bestseller DESC, m.name ASC
            LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':p1' => $exactPrefix,
        ':p2' => $wordPrefix,
        ':p3' => $exactPrefix,
        ':p4' => $contains,
        ':p5' => $contains,
        ':p6' => $contains,
        ':p7' => $contains,
        ':p8' => $contains,
    ]);

    $rows = $stmt->fetchAll();
    $results = [];

    foreach ($rows as $row) {
        $finalPrice = $row['discount_price'] ?: $row['price'];
        $results[] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'description'    => $row['description'],
            'price'          => (float)$row['price'],
            'discount_price' => $row['discount_price'] ? (float)$row['discount_price'] : null,
            'serving_size'   => $row['serving_size'] ?: '',
            'price_fmt'      => '₹' . number_format($finalPrice, 2),
            'original_price' => $row['discount_price'] ? '₹' . number_format($row['price'], 2) : null,
            'image'          => $row['image'] ?: 'assets/images/categories/biryani.png',
            'is_veg'         => (int)$row['is_veg'],
            'is_bestseller'  => (int)$row['is_bestseller'],
            'cat_name'       => $row['cat_name'] ?: 'Special Dish',
            'cat_slug'       => $row['cat_slug'] ?: '',
            'url'            => 'dish.php?id=' . (int)$row['id']
        ];
    }

    echo json_encode([
        'success' => true,
        'query'   => $query,
        'count'   => count($results),
        'results' => $results
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database search error: ' . $e->getMessage(),
        'results' => []
    ]);
}
