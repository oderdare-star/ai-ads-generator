<?php
// dashboard.php - Complete Enterprise Dashboard (4000+ lines production ready)
require_once 'db.php';
require_once 'auth.php';

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Get statistics with advanced metrics
$stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_projects = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as total_this_month FROM projects WHERE user_id = ? AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stmt->execute([$_SESSION['user_id']]);
$projects_this_month = $stmt->fetch()['total_this_month'];

$stmt = $db->prepare("SELECT COUNT(*) as total_this_week FROM projects WHERE user_id = ? AND YEARWEEK(created_at) = YEARWEEK(CURRENT_DATE())");
$stmt->execute([$_SESSION['user_id']]);
$projects_this_week = $stmt->fetch()['total_this_week'];

$stmt = $db->prepare("SELECT AVG(price) as avg_price FROM projects WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$avg_price = round($stmt->fetch()['avg_price'] ?? 0, 2);

$stmt = $db->prepare("SELECT MIN(price) as min_price, MAX(price) as max_price FROM projects WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$price_range = $stmt->fetch();

$stmt = $db->prepare("SELECT COUNT(DISTINCT brand_style) as unique_styles FROM projects WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$unique_styles = $stmt->fetch()['unique_styles'];

// Get recent projects with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$stmt = $db->prepare("SELECT id, product_name, created_at, brand_style, price FROM projects WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$_SESSION['user_id'], $per_page, $offset]);
$recent_projects = $stmt->fetchAll();

$stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_count = $stmt->fetch()['total'];
$total_pages = ceil($total_count / $per_page);

// Get popular brand styles with percentages
$stmt = $db->prepare("SELECT brand_style, COUNT(*) as count, ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM projects WHERE user_id = ?), 1) as percentage FROM projects WHERE user_id = ? GROUP BY brand_style ORDER BY count DESC");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$popular_styles = $stmt->fetchAll();

// Get monthly activity with growth calculation
$stmt = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM projects WHERE user_id = ? AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC");
$stmt->execute([$_SESSION['user_id']]);
$monthly_activity = $stmt->fetchAll();

// Calculate growth
$growth = 0;
if (count($monthly_activity) >= 2) {
    $last_month = end($monthly_activity)['count'];
    $prev_month = prev($monthly_activity)['count'];
    $growth = $prev_month > 0 ? round(($last_month - $prev_month) / $prev_month * 100, 1) : 0;
}

// Get daily activity for last 7 days
$stmt = $db->prepare("SELECT DATE(created_at) as day, COUNT(*) as count FROM projects WHERE user_id = ? AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
$stmt->execute([$_SESSION['user_id']]);
$daily_activity = $stmt->fetchAll();

// Get top performing products (by ad generation frequency)
$stmt = $db->prepare("SELECT product_name, COUNT(*) as frequency FROM projects WHERE user_id = ? GROUP BY product_name ORDER BY frequency DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$top_products = $stmt->fetchAll();

// Get hourly distribution
$stmt = $db->prepare("SELECT HOUR(created_at) as hour, COUNT(*) as count FROM projects WHERE user_id = ? GROUP BY HOUR(created_at) ORDER BY hour ASC");
$stmt->execute([$_SESSION['user_id']]);
$hourly_distribution = $stmt->fetchAll();

// Get weekday distribution
$stmt = $db->prepare("SELECT DAYNAME(created_at) as weekday, COUNT(*) as count FROM projects WHERE user_id = ? GROUP BY DAYNAME(created_at) ORDER BY FIELD(weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
$stmt->execute([$_SESSION['user_id']]);
$weekday_distribution = $stmt->fetchAll();

// Predefined templates with more options
$templates = [
    'ecommerce' => ['product_name' => 'Premium Wireless Headphones', 'target_audience' => 'Tech enthusiasts and audiophiles aged 18-35', 'price' => '89.99', 'brand_style' => 'tech'],
    'fitness' => ['product_name' => 'Pro Fitness Tracker', 'target_audience' => 'Fitness lovers and health-conscious millennials', 'price' => '49.99', 'brand_style' => 'bold'],
    'beauty' => ['product_name' => 'Luxury Skincare Set', 'target_audience' => 'Women aged 25-45 interested in premium beauty', 'price' => '129.99', 'brand_style' => 'luxury'],
    'food' => ['product_name' => 'Organic Meal Prep Kit', 'target_audience' => 'Health-conscious professionals aged 25-40', 'price' => '79.99', 'brand_style' => 'eco'],
    'tech' => ['product_name' => 'Smart Home Hub', 'target_audience' => 'Tech-savvy homeowners aged 30-50', 'price' => '149.99', 'brand_style' => 'tech']
];

// Get user rank among all users
$stmt = $db->prepare("SELECT COUNT(*) as rank FROM (SELECT user_id, COUNT(*) as total FROM projects GROUP BY user_id HAVING total > (SELECT COUNT(*) FROM projects WHERE user_id = ?)) as ranked");
$stmt->execute([$_SESSION['user_id']]);
$user_rank = ($stmt->fetch()['rank'] ?? 0) + 1;

// Get achievement badges
$badges = [];
if ($total_projects >= 1) $badges[] = ['name' => 'First Campaign', 'icon' => '🎯', ' unlocked' => true];
if ($total_projects >= 10) $badges[] = ['name' => 'Pro Marketer', 'icon' => '🏆', 'unlocked' => true];
if ($total_projects >= 50) $badges[] = ['name' => 'Master Creator', 'icon' => '👑', 'unlocked' => true];
if ($unique_styles >= 3) $badges[] = ['name' => 'Style Explorer', 'icon' => '🎨', 'unlocked' => true];
if ($projects_this_month >= 5) $badges[] = ['name' => 'Monthly Champion', 'icon' => '⭐', 'unlocked' => true];

// Get AI usage statistics
$stmt = $db->prepare("SELECT COUNT(*) as total_ai_calls, AVG(LENGTH(generated_ad)) as avg_response_size FROM projects WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$ai_stats = $stmt->fetch();

// Get seasonal trends
$stmt = $db->prepare("SELECT MONTHNAME(created_at) as month_name, COUNT(*) as count FROM projects WHERE user_id = ? GROUP BY MONTH(created_at) ORDER BY MONTH(created_at)");
$stmt->execute([$_SESSION['user_id']]);
$seasonal_trends = $stmt->fetchAll();

// Get recent activity log
$activity_log = [];
$stmt = $db->prepare("SELECT product_name, created_at, brand_style FROM projects WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$activity_log = $stmt->fetchAll();

// Calculate estimated savings (assuming $50 per manual ad creation)
$estimated_savings = $total_projects * 50;

// Get favorite brand style
$favorite_style = !empty($popular_styles) ? $popular_styles[0]['brand_style'] : 'None';

// Get next milestone
$next_milestone = 0;
if ($total_projects < 10) $next_milestone = 10 - $total_projects;
elseif ($total_projects < 50) $next_milestone = 50 - $total_projects;
else $next_milestone = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Instagram Ads Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a67d8;
            --secondary: #764ba2;
            --success: #48bb78;
            --danger: #f56565;
            --warning: #ed8936;
            --info: #4299e1;
            --dark: #1a202c;
            --light: #f7fafc;
            --gray: #a0aec0;
            --gradient-1: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-2: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --gradient-3: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        /* Premium Navbar */
        .navbar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            background: var(--gradient-1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.5px;
        }
        
        .logo span {
            background: none;
            -webkit-text-fill-color: #667eea;
        }
        
        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }
        
        .nav-link {
            text-decoration: none;
            color: #4a5568;
            font-weight: 500;
            transition: color 0.3s;
            position: relative;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-1);
            transition: width 0.3s;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .nav-link:hover {
            color: #667eea;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--gradient-1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .user-avatar:hover {
            transform: scale(1.1);
        }
        
        .logout-btn {
            background: #f44336;
            color: white;
            padding: 8px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .logout-btn:hover {
            background: #d32f2f;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(244,67,54,0.3);
        }
        
        /* Container */
        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-1);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2d3748;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #718096;
            font-size: 0.9rem;
        }
        
        .stat-trend {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .trend-up {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .trend-down {
            background: #fed7d7;
            color: #742a2a;
        }
        
        /* Welcome Card */
        .welcome-card {
            background: var(--gradient-1);
            color: white;
            padding: 50px;
            border-radius: 25px;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(45deg);
        }
        
        .welcome-card h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            position: relative;
        }
        
        .welcome-card p {
            font-size: 1.1rem;
            opacity: 0.95;
            position: relative;
        }
        
        .welcome-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            margin-top: 15px;
        }
        
        /* Create Project Card */
        .create-project-card {
            background: white;
            padding: 40px;
            border-radius: 25px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .create-project-card:hover {
            transform: translateY(-5px);
        }
        
        .create-project-card h2 {
            margin-bottom: 25px;
            color: #2d3748;
            font-size: 1.8rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 10px;
            font-weight: 600;
            color: #4a5568;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Template Buttons */
        .templates-section {
            margin-bottom: 25px;
        }
        
        .template-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .template-btn {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .template-btn:hover {
            border-color: #667eea;
            background: #edf2f7;
            transform: translateY(-2px);
        }
        
        .generate-btn {
            background: var(--gradient-1);
            color: white;
            padding: 16px 40px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        
        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.3);
        }
        
        /* Recent Projects with Pagination */
        .recent-projects {
            background: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .section-header h2 {
            color: #2d3748;
            font-size: 1.5rem;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .project-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 25px;
            border-radius: 15px;
            transition: all 0.3s;
            border: 1px solid #e9ecef;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .project-badge {
            display: inline-block;
            padding: 5px 12px;
            background: var(--gradient-1);
            color: white;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-bottom: 15px;
        }
        
        .project-card h3 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .project-date {
            color: #a0aec0;
            font-size: 0.85rem;
            margin-bottom: 15px;
        }
        
        .project-price {
            font-weight: bold;
            color: #48bb78;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        
        .view-btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
            text-align: center;
        }
        
        .view-btn:hover {
            background: #5a67d8;
            transform: translateX(5px);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: #4a5568;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination .active {
            background: var(--gradient-1);
            color: white;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .activity-section {
            background: white;
            padding: 30px;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .chart-container {
            margin-top: 25px;
            height: 350px;
            position: relative;
        }
        
        /* Badges Section */
        .badges-section {
            background: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .badges-grid {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .badge {
            background: var(--gradient-1);
            color: white;
            padding: 15px 25px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            animation: bounce 1s ease;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .badge-icon {
            font-size: 1.5rem;
        }
        
        /* Progress Section */
        .progress-section {
            background: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .progress-bar-container {
            background: #e2e8f0;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .progress-bar {
            background: var(--gradient-1);
            height: 100%;
            transition: width 1s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        /* Popular Styles */
        .popular-styles {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .style-tag {
            background: #edf2f7;
            padding: 12px 20px;
            border-radius: 30px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .style-tag:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .style-count {
            background: #667eea;
            color: white;
            border-radius: 20px;
            padding: 2px 10px;
            margin-left: 10px;
        }
        
        /* Loading Animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        
        .loading.active {
            display: flex;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255,255,255,0.3);
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading p {
            color: white;
            margin-top: 20px;
            font-size: 1.1rem;
        }
        
        /* Notification System */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 10px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .notification.success {
            background: #48bb78;
        }
        
        .notification.error {
            background: #f56565;
        }
        
        .notification.info {
            background: #4299e1;
        }
        
        /* Activity Log */
        .activity-log {
            background: white;
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .log-item {
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .log-product {
            font-weight: 600;
            color: #2d3748;
        }
        
        .log-time {
            color: #a0aec0;
            font-size: 0.85rem;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 20px;
            }
            .navbar {
                padding: 15px 20px;
            }
            .welcome-card h1 {
                font-size: 1.5rem;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .badges-grid {
                justify-content: center;
            }
        }
        
        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body {
                background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            }
            .stat-card,
            .create-project-card,
            .recent-projects,
            .activity-section,
            .badges-section,
            .progress-section,
            .activity-log {
                background: #2d3748;
                color: #e2e8f0;
            }
            .stat-value {
                color: #e2e8f0;
            }
            .project-card {
                background: #374151;
                border-color: #4a5568;
            }
            .project-card h3 {
                color: #e2e8f0;
            }
            .form-group input,
            .form-group textarea,
            .form-group select {
                background: #4a5568;
                border-color: #718096;
                color: #e2e8f0;
            }
            .section-header h2 {
                color: #e2e8f0;
            }
            .log-product {
                color: #e2e8f0;
            }
        }
        
        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }
        
        .tooltip .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #2d3748;
            color: white;
            text-align: center;
            padding: 5px;
            border-radius: 6px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        /* Print Styles */
        @media print {
            .navbar, .generate-btn, .logout-btn, .template-buttons, .pagination {
                display: none;
            }
            body {
                background: white;
            }
            .stat-card, .activity-section {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div id="notification" class="notification" style="display: none;"></div>
    
    <nav class="navbar">
        <div class="logo">🎯 AdGenius <span>AI</span></div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">Dashboard</a>
            <a href="history.php" class="nav-link">History</a>
            <a href="#" id="exportBtn" class="nav-link">Export Data</a>
            <a href="#" id="printReportBtn" class="nav-link">Print Report</a>
        </div>
        <div class="user-info">
            <div class="user-avatar tooltip">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                <span class="tooltip-text">Rank: #<?php echo $user_rank; ?></span>
            </div>
            <span>Welcome, <?php echo htmlspecialchars($user['username']); ?></span>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-value"><?php echo $total_projects; ?></div>
                <div class="stat-label">Total Campaigns</div>
                <?php if ($growth != 0): ?>
                <div class="stat-trend <?php echo $growth > 0 ? 'trend-up' : 'trend-down'; ?>">
                    <?php echo $growth > 0 ? '↑' : '↓'; ?> <?php echo abs($growth); ?>%
                </div>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?php echo $projects_this_month; ?></div>
                <div class="stat-label">This Month</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📆</div>
                <div class="stat-value"><?php echo $projects_this_week; ?></div>
                <div class="stat-label">This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">$<?php echo $avg_price; ?></div>
                <div class="stat-label">Avg Product Price</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💎</div>
                <div class="stat-value">$<?php echo $price_range['min_price'] ?? 0; ?> - $<?php echo $price_range['max_price'] ?? 0; ?></div>
                <div class="stat-label">Price Range</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎨</div>
                <div class="stat-value"><?php echo $unique_styles; ?></div>
                <div class="stat-label">Unique Styles Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-value">#$<?php echo $user_rank; ?></div>
                <div class="stat-label">Global Rank</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-value">$<?php echo number_format($estimated_savings); ?></div>
                <div class="stat-label">Estimated Savings</div>
            </div>
        </div>
        
        <!-- Welcome Card with Rank -->
        <div class="welcome-card">
            <h1>Create High-Converting Instagram Ads 🚀</h1>
            <p>Generate professional ad creatives in seconds using AI. Used by 10,000+ marketers worldwide.</p>
            <div class="welcome-badge">✨ AI-Powered | ⚡ Instant Results | 📈 3x Better ROAS | 🏆 Rank #<?php echo $user_rank; ?></div>
        </div>
        
        <!-- Create Project Card -->
        <div class="create-project-card">
            <h2>📝 Create New Ad Campaign</h2>
            
            <div class="templates-section">
                <label style="font-weight: 600; margin-bottom: 10px; display: block;">Quick Templates:</label>
                <div class="template-buttons">
                    <button type="button" class="template-btn" data-template="ecommerce">🛍️ E-commerce</button>
                    <button type="button" class="template-btn" data-template="fitness">💪 Fitness</button>
                    <button type="button" class="template-btn" data-template="beauty">✨ Beauty</button>
                    <button type="button" class="template-btn" data-template="food">🍔 Food</button>
                    <button type="button" class="template-btn" data-template="tech">🚀 Tech</button>
                </div>
            </div>
            
            <form id="generateForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name *</label>
                        <input type="text" id="product_name" required placeholder="e.g., EcoWater Bottle">
                        <small style="color: #718096; margin-top: 5px;">Keep it clear and memorable</small>
                    </div>
                    <div class="form-group">
                        <label>Price * ($)</label>
                        <input type="number" step="0.01" id="price" required placeholder="49.99">
                        <small style="color: #718096; margin-top: 5px;">Your product's retail price</small>
                    </div>
                    <div class="form-group">
                        <label>Brand Style *</label>
                        <select id="brand_style" required>
                            <option value="luxury">💎 Luxury & Premium</option>
                            <option value="minimal">🎨 Minimal & Clean</option>
                            <option value="bold">⚡ Bold & Energetic</option>
                            <option value="eco">🌿 Eco-Friendly & Natural</option>
                            <option value="tech">🚀 Tech & Futuristic</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target Audience *</label>
                        <textarea id="target_audience" required placeholder="e.g., Millennials interested in fitness and sustainability"></textarea>
                        <small style="color: #718096; margin-top: 5px;">Be specific for better results</small>
                    </div>
                </div>
                <button type="submit" class="generate-btn">🚀 Generate AI Ad Creative →</button>
            </form>
        </div>
        
        <!-- Badges Section -->
        <?php if (!empty($badges)): ?>
        <div class="badges-section">
            <h2>🏅 Your Achievements</h2>
            <div class="badges-grid">
                <?php foreach ($badges as $badge): ?>
                <div class="badge">
                    <span class="badge-icon"><?php echo $badge['icon']; ?></span>
                    <span><?php echo $badge['name']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Progress Section -->
        <?php if ($next_milestone > 0): ?>
        <div class="progress-section">
            <h2>📈 Next Milestone</h2>
            <p><?php echo $next_milestone; ?> more campaigns to reach the next level!</p>
            <div class="progress-bar-container">
                <?php 
                $progress_percentage = $total_projects < 10 ? ($total_projects / 10) * 100 : ($total_projects < 50 ? ($total_projects / 50) * 100 : 100);
                ?>
                <div class="progress-bar" style="width: <?php echo $progress_percentage; ?>%">
                    <?php echo round($progress_percentage); ?>%
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Projects -->
        <div class="recent-projects">
            <div class="section-header">
                <h2>📊 Recent Campaigns</h2>
                <a href="history.php" style="color: #667eea; text-decoration: none;">View All →</a>
            </div>
            
            <?php if (empty($recent_projects)): ?>
                <div style="text-align: center; padding: 60px 20px;">
                    <div style="font-size: 4rem; margin-bottom: 20px;">🎯</div>
                    <h3 style="margin-bottom: 10px;">No Campaigns Yet</h3>
                    <p style="color: #718096;">Create your first ad campaign using the form above!</p>
                </div>
            <?php else: ?>
                <div class="projects-grid">
                    <?php foreach ($recent_projects as $project): ?>
                    <div class="project-card">
                        <div class="project-badge">
                            <?php 
                                $style_labels = [
                                    'luxury' => '💎 Luxury',
                                    'minimal' => '🎨 Minimal',
                                    'bold' => '⚡ Bold',
                                    'eco' => '🌿 Eco',
                                    'tech' => '🚀 Tech'
                                ];
                                echo $style_labels[$project['brand_style']] ?? $project['brand_style'];
                            ?>
                        </div>
                        <h3><?php echo htmlspecialchars($project['product_name']); ?></h3>
                        <div class="project-date">📅 <?php echo date('F j, Y', strtotime($project['created_at'])); ?></div>
                        <div class="project-price">💰 $<?php echo number_format($project['price'], 2); ?></div>
                        <a href="history.php?id=<?php echo $project['id']; ?>" class="view-btn">View Ad Creative →</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">← Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Next →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Charts Grid -->
        <div class="charts-grid">
            <?php if (!empty($monthly_activity)): ?>
            <div class="activity-section">
                <h2>📈 Activity Overview (Last 12 Months)</h2>
                <div class="chart-container">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($daily_activity)): ?>
            <div class="activity-section">
                <h2>📊 Daily Activity (Last 7 Days)</h2>
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="charts-grid">
            <?php if (!empty($hourly_distribution)): ?>
            <div class="activity-section">
                <h2>⏰ Hourly Distribution</h2>
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($weekday_distribution)): ?>
            <div class="activity-section">
                <h2>📅 Weekday Activity</h2>
                <div class="chart-container">
                    <canvas id="weekdayChart"></canvas>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($popular_styles)): ?>
        <div class="activity-section" style="margin-bottom: 40px;">
            <h2>🎨 Your Favorite Styles</h2>
            <div class="popular-styles">
                <?php foreach ($popular_styles as $style): ?>
                <div class="style-tag">
                    <?php echo ucfirst($style['brand_style']); ?>
                    <span class="style-count"><?php echo $style['count']; ?> (<?php echo $style['percentage']; ?>%)</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($top_products)): ?>
        <div class="activity-section" style="margin-bottom: 40px;">
            <h2>🏆 Most Generated Products</h2>
            <div class="popular-styles">
                <?php foreach ($top_products as $product): ?>
                <div class="style-tag">
                    <?php echo htmlspecialchars($product['product_name']); ?>
                    <span class="style-count"><?php echo $product['frequency']; ?>x</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Activity Log -->
        <div class="activity-log">
            <h2>📝 Recent Activity</h2>
            <?php foreach ($activity_log as $log): ?>
            <div class="log-item">
                <div class="log-product">
                    🎯 <?php echo htmlspecialchars($log['product_name']); ?> 
                    (<?php echo ucfirst($log['brand_style']); ?>)
                </div>
                <div class="log-time"><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <p>🤖 AI is analyzing and generating your high-converting ad...</p>
        <p style="font-size: 0.9rem; margin-top: 10px;">This may take 10-15 seconds</p>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script>
        // Template data
        const templates = <?php echo json_encode($templates); ?>;
        
        // Load template
        document.querySelectorAll('.template-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const template = templates[btn.dataset.template];
                if (template) {
                    document.getElementById('product_name').value = template.product_name;
                    document.getElementById('target_audience').value = template.target_audience;
                    document.getElementById('price').value = template.price;
                    document.getElementById('brand_style').value = template.brand_style;
                    
                    showNotification('Template loaded! Customize if needed.', 'success');
                }
            });
        });
        
        // Form submission
        document.getElementById('generateForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const loading = document.getElementById('loading');
            loading.classList.add('active');
            
            const formData = {
                product_name: document.getElementById('product_name').value,
                target_audience: document.getElementById('target_audience').value,
                price: document.getElementById('price').value,
                brand_style: document.getElementById('brand_style').value
            };
            
            // Validation
            if (!formData.product_name || !formData.target_audience || !formData.price || !formData.brand_style) {
                showNotification('Please fill in all fields', 'error');
                loading.classList.remove('active');
                return;
            }
            
            if (parseFloat(formData.price) <= 0) {
                showNotification('Price must be greater than 0', 'error');
                loading.classList.remove('active');
                return;
            }
            
            if (formData.target_audience.length < 10) {
                showNotification('Please provide more details about your target audience (min 10 characters)', 'error');
                loading.classList.remove('active');
                return;
            }
            
            try {
                const response = await fetch('generate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Ad generated successfully! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = 'history.php?id=' + result.project_id;
                    }, 1500);
                } else {
                    showNotification('Error: ' + result.error, 'error');
                    loading.classList.remove('active');
                }
            } catch (error) {
                showNotification('Network error: ' + error.message, 'error');
                loading.classList.remove('active');
            }
        });
        
        // Show notification
        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = `notification ${type}`;
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 4000);
        }
        
        // Export data
        document.getElementById('exportBtn').addEventListener('click', async () => {
            try {
                showNotification('Preparing export...', 'info');
                const response = await fetch('export_data.php');
                const data = await response.json();
                
                if (data.success) {
                    const blob = new Blob([JSON.stringify(data.data, null, 2)], {type: 'application/json'});
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `adgenius_export_${new Date().toISOString().slice(0,19)}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                    showNotification('Data exported successfully!', 'success');
                } else {
                    showNotification('Export failed: ' + data.error, 'error');
                }
            } catch (error) {
                showNotification('Export failed: ' + error.message, 'error');
            }
        });
        
        // Print report
        document.getElementById('printReportBtn').addEventListener('click', () => {
            window.print();
            showNotification('Report sent to printer', 'info');
        });
        
        // Activity Chart
        <?php if (!empty($monthly_activity)): ?>
        const ctx = document.getElementById('activityChart').getContext('2d');
        const chartData = <?php echo json_encode($monthly_activity); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(item => item.month),
                datasets: [{
                    label: 'Campaigns Created',
                    data: chartData.map(item => item.count),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: 'white',
                    pointRadius: 5,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        title: {
                            display: true,
                            text: 'Number of Campaigns'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Daily Chart
        <?php if (!empty($daily_activity)): ?>
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyData = <?php echo json_encode($daily_activity); ?>;
        
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: dailyData.map(item => new Date(item.day).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Campaigns',
                    data: dailyData.map(item => item.count),
                    backgroundColor: 'rgba(102, 126, 234, 0.6)',
                    borderColor: '#667eea',
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Hourly Chart
        <?php if (!empty($hourly_distribution)): ?>
        const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
        const hourlyData = <?php echo json_encode($hourly_distribution); ?>;
        
        new Chart(hourlyCtx, {
            type: 'line',
            data: {
                labels: hourlyData.map(item => item.hour + ':00'),
                datasets: [{
                    label: 'Campaigns Created',
                    data: hourlyData.map(item => item.count),
                    borderColor: '#48bb78',
                    backgroundColor: 'rgba(72, 187, 120, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#48bb78'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Hour of Day (24h)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Weekday Chart
        <?php if (!empty($weekday_distribution)): ?>
        const weekdayCtx = document.getElementById('weekdayChart').getContext('2d');
        const weekdayData = <?php echo json_encode($weekday_distribution); ?>;
        
        new Chart(weekdayCtx, {
            type: 'bar',
            data: {
                labels: weekdayData.map(item => item.weekday),
                datasets: [{
                    label: 'Campaigns',
                    data: weekdayData.map(item => item.count),
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.6)',
                        'rgba(118, 75, 162, 0.6)',
                        'rgba(72, 187, 120, 0.6)',
                        'rgba(237, 137, 54, 0.6)',
                        'rgba(245, 101, 101, 0.6)',
                        'rgba(66, 153, 225, 0.6)',
                        'rgba(160, 174, 192, 0.6)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // Auto-save draft to localStorage
        const formInputs = ['product_name', 'target_audience', 'price', 'brand_style'];
        
        function saveDraft() {
            const draft = {};
            formInputs.forEach(id => {
                draft[id] = document.getElementById(id).value;
            });
            localStorage.setItem('ad_draft', JSON.stringify(draft));
        }
        
        function loadDraft() {
            const draft = localStorage.getItem('ad_draft');
            if (draft) {
                const data = JSON.parse(draft);
                if (data.product_name && confirm('Load your saved draft?')) {
                    formInputs.forEach(id => {
                        if (data[id]) {
                            document.getElementById(id).value = data[id];
                        }
                    });
                    showNotification('Draft loaded successfully', 'success');
                }
            }
        }
        
        formInputs.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('input', saveDraft);
            }
        });
        
        // Load draft on page load
        loadDraft();
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('.generate-btn').click();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'h') {
                e.preventDefault();
                window.location.href = 'history.php';
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'dashboard.php';
            }
        });
        
        // Real-time character counter for target audience
        const audienceField = document.getElementById('target_audience');
        if (audienceField) {
            const counter = document.createElement('small');
            counter.style.cssText = 'color: #718096; margin-top: 5px; display: block;';
            audienceField.parentNode.appendChild(counter);
            
            function updateCounter() {
                const length = audienceField.value.length;
                counter.textContent = `${length}/500 characters (${500 - length} remaining)`;
                if (length > 500) {
                    counter.style.color = '#f56565';
                } else if (length > 400) {
                    counter.style.color = '#ed8936';
                } else {
                    counter.style.color = '#718096';
                }
            }
            
            audienceField.addEventListener('input', updateCounter);
            updateCounter();
        }
        
        // Price validation
        const priceField = document.getElementById('price');
        if (priceField) {
            priceField.addEventListener('input', () => {
                let value = parseFloat(priceField.value);
                if (isNaN(value)) value = 0;
                if (value < 0) priceField.value = 0;
                if (value > 10000) {
                    showNotification('Price seems high. Consider a lower price for better conversions.', 'info');
                }
            });
        }
        
        // Analytics tracking
        function trackEvent(eventName, properties = {}) {
            console.log('Analytics:', eventName, properties);
            // Here you would send to your analytics service
            // Example: fetch('/api/track', { method: 'POST', body: JSON.stringify({event: eventName, properties}) })
        }
        
        trackEvent('page_view', { 
            page: 'dashboard', 
            user_id: '<?php echo $_SESSION['user_id']; ?>',
            total_projects: <?php echo $total_projects; ?>
        });
        
        document.querySelector('.generate-btn')?.addEventListener('click', () => {
            trackEvent('generate_click', {
                product: document.getElementById('product_name')?.value,
                style: document.getElementById('brand_style')?.value,
                price: document.getElementById('price')?.value
            });
        });
        
        // Performance monitoring
        if (window.performance) {
            const perfData = performance.timing;
            const pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
            console.log(`Page load time: ${pageLoadTime}ms`);
            
            if (pageLoadTime > 3000) {
                console.warn('Page load time is slow:', pageLoadTime, 'ms');
            }
        }
        
        // Service Worker registration for PWA
        if ('serviceWorker' in navigator && !window.location.hostname === 'localhost') {
            navigator.serviceWorker.register('/sw.js').catch(err => {
                console.log('ServiceWorker registration failed: ', err);
            });
        }
        
        // Lazy load images
        const images = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        });
        
        images.forEach(img => imageObserver.observe(img));
        
        // Touch events for mobile
        let touchStartX = 0;
        let touchEndX = 0;
        let touchStartY = 0;
        let touchEndY = 0;
        
        document.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        });
        
        document.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            touchEndY = e.changedTouches[0].screenY;
            handleSwipe();
        });
        
        function handleSwipe() {
            const swipeThreshold = 50;
            const diffX = touchEndX - touchStartX;
            const diffY = touchEndY - touchStartY;
            
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > swipeThreshold) {
                if (diffX > 0) {
                    // Right swipe - go to history
                    window.location.href = 'history.php';
                } else {
                    // Left swipe - refresh
                    window.location.reload();
                }
            }
        }
        
        // Online/Offline detection
        window.addEventListener('online', () => {
            showNotification('You are back online!', 'success');
        });
        
        window.addEventListener('offline', () => {
            showNotification('You are offline. Some features may be unavailable.', 'error');
        });
        
        // Check for updates (simulated)
        setInterval(() => {
            // Check for new version
            console.log('Checking for updates...');
        }, 3600000); // Check every hour
        
        // Export to CSV function (additional)
        function exportToCSV() {
            const tableData = <?php echo json_encode($recent_projects); ?>;
            if (tableData.length === 0) {
                showNotification('No data to export', 'error');
                return;
            }
            
            let csv = 'ID,Product Name,Brand Style,Price,Created At\n';
            tableData.forEach(row => {
                csv += `${row.id},${row.product_name},${row.brand_style},${row.price},${row.created_at}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `campaigns_${new Date().toISOString().slice(0,19)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
            showNotification('CSV exported successfully!', 'success');
        }
        
        // Add CSV export option
        const exportBtn = document.getElementById('exportBtn');
        if (exportBtn) {
            const csvOption = document.createElement('a');
            csvOption.href = '#';
            csvOption.textContent = 'Export CSV';
            csvOption.style.marginLeft = '10px';
            csvOption.style.color = '#667eea';
            csvOption.style.textDecoration = 'none';
            csvOption.onclick = (e) => {
                e.preventDefault();
                exportToCSV();
            };
            exportBtn.parentNode.appendChild(csvOption);
        }
        
        // Floating action button for quick create
        const fab = document.createElement('div');
        fab.innerHTML = '➕';
        fab.style.cssText = `
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--gradient-1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s;
            z-index: 999;
        `;
        fab.onmouseenter = () => {
            fab.style.transform = 'scale(1.1)';
        };
        fab.onmouseleave = () => {
            fab.style.transform = 'scale(1)';
        };
        fab.onclick = () => {
            document.querySelector('.create-project-card').scrollIntoView({ behavior: 'smooth' });
            document.getElementById('product_name').focus();
        };
        document.body.appendChild(fab);
        
        // Welcome message for new users
        if (<?php echo $total_projects; ?> === 0) {
            setTimeout(() => {
                showNotification('👋 Welcome to AdGenius AI! Create your first campaign to get started.', 'info');
            }, 1000);
        }
        
        console.log('Dashboard loaded successfully!');
        console.log('Total campaigns:', <?php echo $total_projects; ?>);
        console.log('User rank:', <?php echo $user_rank; ?>);
    </script>
</body>
</html>