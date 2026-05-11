<?php
require_once 'db.php';
require_once 'auth.php';

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$single_project = null;
$error_message = null;

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = filter_var($_POST['delete_id'], FILTER_VALIDATE_INT);
    if ($delete_id) {
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$delete_id, $_SESSION['user_id']])) {
            header('Location: history.php?deleted=1');
            exit();
        } else {
            $error_message = "Failed to delete project.";
        }
    }
}

// Handle single project view
if (isset($_GET['id'])) {
    $project_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($project_id) {
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$project_id, $_SESSION['user_id']]);
        $single_project = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$single_project) {
            $error_message = "Project not found or access denied.";
        }
    } else {
        $error_message = "Invalid project ID.";
    }
}

// Get all projects for listing
$stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to safely get ad data
function getAdData($project, $default = null) {
    if (empty($project['generated_ad'])) {
        return $default ?: [
            'hook' => 'No data available',
            'caption' => '',
            'cta' => '',
            'marketing_angle' => '',
            'visual_design' => '',
            'font_style' => 'Arial',
            'color_palette' => ['#CCCCCC']
        ];
    }
    
    $ad = json_decode($project['generated_ad'], true);
    
    if (!$ad || !is_array($ad)) {
        return $default ?: [
            'hook' => 'Error parsing ad data',
            'caption' => 'Please regenerate this ad',
            'cta' => 'Try Again',
            'marketing_angle' => 'Data corrupted',
            'visual_design' => '',
            'font_style' => 'Arial',
            'color_palette' => ['#FF4444']
        ];
    }
    
    // Ensure color_palette is an array
    if (!isset($ad['color_palette']) || !is_array($ad['color_palette'])) {
        $ad['color_palette'] = isset($ad['color_palette']) ? [$ad['color_palette']] : ['#667eea', '#764ba2'];
    }
    
    // Set defaults for missing fields
    $defaults = [
        'hook' => 'Untitled Ad',
        'caption' => '',
        'cta' => 'Learn More',
        'marketing_angle' => 'No description available',
        'visual_design' => 'No design details',
        'font_style' => 'Arial, Helvetica, sans-serif'
    ];
    
    foreach ($defaults as $key => $default_value) {
        if (!isset($ad[$key])) {
            $ad[$key] = $default_value;
        }
    }
    
    return $ad;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad History - Instagram Ad Generator</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Header Styles */
        .header {
            background: white;
            border-radius: 15px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 28px;
        }
        
        .header-buttons {
            display: flex;
            gap: 10px;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        /* Hook Section */
        .hook {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .hook h3 {
            margin: 0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .hook h2 {
            margin: 15px 0 0;
            font-size: 28px;
            line-height: 1.3;
        }
        
        /* Section Styles */
        .section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .section h3 {
            color: #667eea;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
            font-weight: 600;
        }
        
        .section p {
            color: #333;
            line-height: 1.6;
            font-size: 16px;
        }
        
        /* Color Palette */
        .colors {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .color-box {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .color-box:hover {
            transform: scale(1.1);
        }
        
        /* Font Style Display */
        .font-preview {
            font-size: 24px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            text-align: center;
            margin-top: 10px;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
        }
        
        .btn-secondary {
            background: #48bb78;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #38a169;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        /* Project List Items */
        .project-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .project-info {
            flex: 1;
        }
        
        .project-info h3 {
            color: #333;
            margin-bottom: 8px;
            font-size: 20px;
        }
        
        .project-info .hook-preview {
            color: #667eea;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .project-info .date {
            color: #999;
            font-size: 12px;
        }
        
        .project-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #48bb78;
        }
        
        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #f56565;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
        }
        
        .empty-state svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .project-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .project-actions {
                width: 100%;
            }
            
            .project-actions .btn {
                flex: 1;
                text-align: center;
            }
            
            .hook h2 {
                font-size: 20px;
            }
        }
        
        /* Print Styles */
        @media print {
            .header-buttons,
            .project-actions,
            .btn {
                display: none;
            }
            
            body {
                background: white;
                padding: 0;
            }
            
            .card {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltip-text {
            visibility: hidden;
            background-color: #333;
            color: #fff;
            text-align: center;
            padding: 5px 10px;
            border-radius: 6px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- Header -->
    <div class="header">
        <h1>📜 Ad History</h1>
        <div class="header-buttons">
            <a href="generate.html" class="btn btn-primary">+ Create New Ad</a>
            <a href="dashboard.php" class="btn btn-outline">🏠 Dashboard</a>
            <button onclick="window.print()" class="btn btn-outline">🖨️ Print</button>
        </div>
    </div>
    
    <!-- Alert Messages -->
    <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            ✅ Project deleted successfully!
        </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            ❌ <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($single_project): ?>
        <!-- Single Project View -->
        <?php 
        $ad = getAdData($single_project);
        $colors = $ad['color_palette'];
        ?>
        
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2 style="color: #333; margin-bottom: 5px;"><?= htmlspecialchars($single_project['product_name']) ?></h2>
                    <small style="color: #999;">Created: <?= date('F j, Y g:i A', strtotime($single_project['created_at'])) ?></small>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="copyToClipboard()" class="btn btn-outline" style="background: white;">📋 Copy Ad</button>
                    <a href="history.php" class="btn btn-primary">← Back to List</a>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this project? This action cannot be undone.');">
                        <input type="hidden" name="delete_id" value="<?= $single_project['id'] ?>">
                        <button type="submit" class="btn btn-danger">🗑️ Delete</button>
                    </form>
                </div>
            </div>
            
            <div class="hook">
                <h3>🔥 HOOK</h3>
                <h2><?= htmlspecialchars($ad['hook']) ?></h2>
            </div>
            
            <?php if ($ad['caption']): ?>
            <div class="section">
                <h3>📝 CAPTION</h3>
                <p><?= nl2br(htmlspecialchars($ad['caption'])) ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($ad['cta']): ?>
            <div class="section">
                <h3>⚡ CALL TO ACTION</h3>
                <p style="font-size: 18px; font-weight: 600; color: #667eea;"><?= htmlspecialchars($ad['cta']) ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($ad['marketing_angle']): ?>
            <div class="section">
                <h3>🎯 MARKETING ANGLE</h3>
                <p><?= htmlspecialchars($ad['marketing_angle']) ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($ad['visual_design']): ?>
            <div class="section">
                <h3>🎨 VISUAL DESIGN</h3>
                <p><?= nl2br(htmlspecialchars($ad['visual_design'])) ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($ad['font_style']): ?>
            <div class="section">
                <h3>✍️ FONT STYLE</h3>
                <div class="font-preview" style="font-family: <?= htmlspecialchars($ad['font_style']) ?>;">
                    <?= htmlspecialchars($ad['font_style']) ?>
                    <div style="font-size: 14px; color: #666; margin-top: 10px;">
                        The quick brown fox jumps over the lazy dog
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($colors)): ?>
            <div class="section">
                <h3>🎨 COLOR PALETTE</h3>
                <div class="colors">
                    <?php foreach ($colors as $c): ?>
                        <div class="tooltip">
                            <div class="color-box" style="background: <?= htmlspecialchars($c) ?>"></div>
                            <span class="tooltip-text"><?= htmlspecialchars($c) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Export Options -->
            <div class="section" style="background: #e8f5e9;">
                <h3>📤 EXPORT OPTIONS</h3>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button onclick="exportAsText()" class="btn btn-secondary">📄 Export as Text</button>
                    <button onclick="exportAsHTML()" class="btn btn-secondary">🌐 Export as HTML</button>
                </div>
            </div>
        </div>
        
        <script>
        function copyToClipboard() {
            const adContent = `HOOK: <?= addslashes($ad['hook']) ?>\n\nCAPTION: <?= addslashes($ad['caption']) ?>\n\nCTA: <?= addslashes($ad['cta']) ?>\n\nMARKETING ANGLE: <?= addslashes($ad['marketing_angle']) ?>`;
            navigator.clipboard.writeText(adContent).then(() => {
                alert('Ad copied to clipboard!');
            });
        }
        
        function exportAsText() {
            const content = `Product: <?= addslashes($single_project['product_name']) ?>\nDate: <?= $single_project['created_at'] ?>\n\nHOOK: <?= addslashes($ad['hook']) ?>\n\nCAPTION: <?= addslashes($ad['caption']) ?>\n\nCTA: <?= addslashes($ad['cta']) ?>\n\nMARKETING ANGLE: <?= addslashes($ad['marketing_angle']) ?>\n\nVISUAL DESIGN: <?= addslashes($ad['visual_design']) ?>\n\nCOLORS: <?= implode(', ', $colors) ?>`;
            const blob = new Blob([content], {type: 'text/plain'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'ad_<?= $single_project['id'] ?>.txt';
            a.click();
            URL.revokeObjectURL(a.href);
        }
        
        function exportAsHTML() {
            const content = document.querySelector('.card').cloneNode(true);
            content.querySelectorAll('.btn, .project-actions, form').forEach(el => el.remove());
            const html = `<html><head><title>Ad Export</title><style>${document.querySelector('style').innerHTML}</style></head><body><div class="container">${content.outerHTML}</div></body></html>`;
            const blob = new Blob([html], {type: 'text/html'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'ad_<?= $single_project['id'] ?>.html';
            a.click();
            URL.revokeObjectURL(a.href);
        }
        </script>
        
    <?php else: ?>
        <!-- Projects List View -->
        <?php if (empty($projects)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                    <path d="M4 4h16v16H4z M8 4v16 M16 4v16 M4 8h16 M4 16h16" stroke="#ccc"/>
                    <path d="M12 8v8 M8 12h8" stroke="#ccc"/>
                </svg>
                <h3>No ads generated yet</h3>
                <p style="color: #999; margin-bottom: 20px;">Create your first Instagram ad to see it here!</p>
                <a href="generate.html" class="btn btn-primary">✨ Create Your First Ad</a>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $p): ?>
                <?php $ad = getAdData($p, ['hook' => 'Loading...']); ?>
                <div class="card">
                    <div class="project-item">
                        <div class="project-info">
                            <h3><?= htmlspecialchars($p['product_name']) ?></h3>
                            <div class="hook-preview">🎯 <?= htmlspecialchars(substr($ad['hook'], 0, 100)) ?>...</div>
                            <div class="date">📅 <?= date('M j, Y', strtotime($p['created_at'])) ?></div>
                        </div>
                        <div class="project-actions">
                            <a href="history.php?id=<?= $p['id'] ?>" class="btn btn-primary">View Details</a>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this project?');">
                                <input type="hidden" name="delete_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>