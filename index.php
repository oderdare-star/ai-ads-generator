<?php
// index.php - Landing Page & Authentication Router
require_once 'db.php';
require_once 'auth.php';

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'register') {
            $result = $auth->register(
                trim($_POST['username']),
                trim($_POST['email']),
                $_POST['password']
            );
            if ($result['success']) {
                $success = 'Registration successful! Please login.';
            } else {
                $error = $result['error'];
            }
        } elseif ($_POST['action'] === 'login') {
            $result = $auth->login(
                trim($_POST['email']),
                $_POST['password']
            );
            if ($result['success']) {
                header('Location: dashboard.php');
                exit();
            } else {
                $error = $result['error'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Ads Generator - SaaS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            width: 100%;
            max-width: 1100px;
            display: flex;
            flex-wrap: wrap;
        }
        
        .hero {
            flex: 1;
            padding: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        
        .hero p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 20px;
            opacity: 0.95;
        }
        
        .hero .features {
            list-style: none;
            margin-top: 20px;
        }
        
        .hero .features li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .forms-container {
            flex: 1;
            padding: 50px;
            background: white;
        }
        
        .form-toggle {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .toggle-btn {
            background: none;
            border: none;
            padding: 10px 0;
            font-size: 1.1rem;
            cursor: pointer;
            color: #666;
            transition: all 0.3s;
        }
        
        .toggle-btn.active {
            color: #667eea;
            border-bottom: 2px solid #667eea;
            margin-bottom: -2px;
        }
        
        .form {
            display: none;
        }
        
        .form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .success {
            background: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="hero">
            <h1>🚀 Instagram Ads Generator</h1>
            <p>Create high-converting Instagram ads in seconds with AI. Used by top brands to increase ROAS by 300%.</p>
            <ul class="features">
                <li>✓ AI-Powered Ad Copy</li>
                <li>✓ Conversion-Optimized Hooks</li>
                <li>✓ Professional Visual Guides</li>
                <li>✓ Save & Export Campaigns</li>
            </ul>
        </div>
        <div class="forms-container">
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="form-toggle">
                <button class="toggle-btn active" onclick="showForm('login')">Login</button>
                <button class="toggle-btn" onclick="showForm('register')">Register</button>
            </div>
            
            <div id="login-form" class="form active">
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit">Login →</button>
                </form>
            </div>
            
            <div id="register-form" class="form">
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password (min 8 characters)</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit">Register →</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showForm(form) {
            document.getElementById('login-form').classList.remove('active');
            document.getElementById('register-form').classList.remove('active');
            document.getElementById(`${form}-form`).classList.add('active');
            
            document.querySelectorAll('.toggle-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }
    </script>
</body>
</html>