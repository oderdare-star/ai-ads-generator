<?php
// export_data.php - Complete Export Data Handler
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance()->getConnection();

// Get all user projects
$stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

// Get user info
$user = $auth->getCurrentUser();

// Prepare export data with full details
$export_data = [
    'export_date' => date('Y-m-d H:i:s'),
    'user' => [
        'username' => $user['username'],
        'email' => $user['email'],
        'member_since' => $user['created_at'],
        'total_campaigns' => count($projects)
    ],
    'statistics' => [
        'total_projects' => count($projects),
        'export_timestamp' => time(),
        'export_format' => 'JSON'
    ],
    'campaigns' => []
];

// Process each project
foreach ($projects as $project) {
    $ad_data = json_decode($project['generated_ad'], true);
    
    $export_data['campaigns'][] = [
        'id' => $project['id'],
        'product_name' => $project['product_name'],
        'target_audience' => $project['target_audience'],
        'price' => $project['price'],
        'brand_style' => $project['brand_style'],
        'created_at' => $project['created_at'],
        'ad_creative' => $ad_data
    ];
}

// Return JSON response
echo json_encode([
    'success' => true,
    'data' => $export_data,
    'message' => 'Data exported successfully'
], JSON_PRETTY_PRINT);
?>