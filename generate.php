<?php
// generate.php - AI Generation API Endpoint
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json');

// Configuration class for API credentials
class Config {
    private static $api_key = null;
    private static $config_file = __DIR__ . '/config.json';
    
    public static function getOpenRouterKey() {
        if (self::$api_key) {
            return self::$api_key;
        }
        
        // Try to read from config file first
        if (file_exists(self::$config_file)) {
            $config = json_decode(file_get_contents(self::$config_file), true);
            if (isset($config['openrouter_api_key'])) {
                self::$api_key = $config['openrouter_api_key'];
                return self::$api_key;
            }
        }
        
        // Fallback to environment variable
        self::$api_key = getenv('OPENROUTER_API_KEY');
        
        // Last resort - should be moved to config
        if (!self::$api_key) {
            self::$api_key = 'OUR_API_KEY_HERE';
        }
        
        return self::$api_key;
    }
}

// Validation function
function validateInput($input) {
    $errors = [];
    
    if (empty($input['product_name']) || strlen($input['product_name']) > 255) {
        $errors[] = 'Product name is required and must be less than 255 characters';
    }
    
    if (empty($input['target_audience']) || strlen($input['target_audience']) > 500) {
        $errors[] = 'Target audience is required and must be less than 500 characters';
    }
    
    if (!isset($input['price']) || !is_numeric($input['price']) || $input['price'] <= 0 || $input['price'] > 999999.99) {
        $errors[] = 'Valid price is required (0.01 - 999999.99)';
    }
    
    if (empty($input['brand_style']) || strlen($input['brand_style']) > 255) {
        $errors[] = 'Brand style is required and must be less than 255 characters';
    }
    
    return $errors;
}

// Sanitization function
function sanitizeInput($input) {
    return [
        'product_name' => htmlspecialchars(strip_tags(trim($input['product_name'])), ENT_QUOTES, 'UTF-8'),
        'target_audience' => htmlspecialchars(strip_tags(trim($input['target_audience'])), ENT_QUOTES, 'UTF-8'),
        'price' => floatval($input['price']),
        'brand_style' => htmlspecialchars(strip_tags(trim($input['brand_style'])), ENT_QUOTES, 'UTF-8')
    ];
}

// Rate limiting
function checkRateLimit($db, $user_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as request_count 
        FROM generation_logs 
        WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Limit to 50 requests per hour per user
    if ($result['request_count'] >= 50) {
        return ['allowed' => false, 'message' => 'Rate limit exceeded. Please try again later.'];
    }
    
    return ['allowed' => true];
}

// Log API requests for monitoring
function logApiRequest($db, $user_id, $endpoint, $status, $response_time, $error_message = null) {
    $stmt = $db->prepare("
        INSERT INTO generation_logs (user_id, endpoint, status, response_time_ms, error_message, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    return $stmt->execute([$user_id, $endpoint, $status, $response_time, $error_message]);
}

// Authentication check
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'code' => 'AUTH_REQUIRED']);
    exit();
}

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed', 'code' => 'METHOD_NOT_ALLOWED']);
    exit();
}

// Get and validate JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg(), 'code' => 'INVALID_JSON']);
    exit();
}

// Validate required fields
$required_fields = ['product_name', 'target_audience', 'price', 'brand_style'];
$missing_fields = array_diff($required_fields, array_keys($input));
if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'Missing required fields: ' . implode(', ', $missing_fields),
        'code' => 'MISSING_FIELDS'
    ]);
    exit();
}

// Validate input values
$validation_errors = validateInput($input);
if (!empty($validation_errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $validation_errors, 'code' => 'VALIDATION_ERROR']);
    exit();
}

// Sanitize inputs
$sanitized = sanitizeInput($input);
$product_name = $sanitized['product_name'];
$target_audience = $sanitized['target_audience'];
$price = $sanitized['price'];
$brand_style = $sanitized['brand_style'];

// Check rate limiting
$db = Database::getInstance()->getConnection();
$rateLimitCheck = checkRateLimit($db, $_SESSION['user_id']);
if (!$rateLimitCheck['allowed']) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => $rateLimitCheck['message'], 'code' => 'RATE_LIMIT_EXCEEDED']);
    exit();
}

// Enhanced prompt for better results
$prompt = "You are a senior performance marketing strategist and creative director with 10+ years of experience in DTC brands. Generate a HIGH-CONVERTING Instagram ad creative that follows proven direct response principles.

PRODUCT DETAILS:
- Product Name: $product_name
- Target Audience: $target_audience
- Price: $$price
- Brand Style: $brand_style

REQUIREMENTS:
1. Hook must create curiosity or address a pain point in under 10 words
2. Caption should use power words and social proof elements
3. Visual design should be platform-specific (Instagram feed/story)
4. Color palette must be contrasting and attention-grabbing
5. CTA must create urgency (limited time, scarcity, or FOMO)

Generate EXACTLY in this JSON format (no extra text, no markdown):
{
    \"hook\": \"Short 5-10 word headline with emotion+urgency\",
    \"caption\": \"2 lines max, highly persuasive with psychological triggers\",
    \"visual_design\": \"Detailed Instagram post layout description including composition, imagery style, and text placement\",
    \"color_palette\": [\"#HEX1\", \"#HEX2\", \"#HEX3\", \"#HEX4\"],
    \"font_style\": \"Primary and secondary font names only\",
    \"cta\": \"Urgency-driven call to action with specific action verb\",
    \"marketing_angle\": \"One sentence explaining the psychological trigger being used\"
}

Make it conversion-optimized for $brand_style brand style targeting $target_audience. Focus on benefits not features.";

$api_key = Config::getOpenRouterKey();
$api_url = 'https://openrouter.ai/api/v1/chat/completions';

$post_data = [
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are a professional Instagram ad creator and direct response copywriter. Return only valid JSON. Never include markdown formatting or explanatory text.'
        ],
        [
            'role' => 'user',
            'content' => $prompt
        ]
    ],
    'temperature' => 0.7,
    'max_tokens' => 800,
    'top_p' => 0.9,
    'frequency_penalty' => 0.3,
    'presence_penalty' => 0.3
];

// Start timing for performance monitoring
$start_time = microtime(true);

// cURL request with retry logic
$max_retries = 3;
$attempt = 0;
$response = null;
$http_code = null;
$curl_error = null;

while ($attempt < $max_retries) {
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
        'HTTP-Referer: ' . (isset($_SERVER['HTTPS'])?'https://':'http://') . $_SERVER['HTTP_HOST'],
        'X-Title: ' . ($_SERVER['HTTP_HOST'] ?? 'Ad Generator App')
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Success or non-retryable error
    if ($http_code === 200) {
        break;
    }
    
    // Only retry on server errors or timeouts
    if ($http_code >= 500 || $curl_error) {
        $attempt++;
        if ($attempt < $max_retries) {
            usleep(1000000 * $attempt); // Exponential backoff: 1s, 2s
            continue;
        }
    } else {
        break; // Client error, don't retry
    }
}

$response_time_ms = round((microtime(true) - $start_time) * 1000);

// Log the request
logApiRequest($db, $_SESSION['user_id'], 'openrouter', $http_code, $response_time_ms, $curl_error ?: ($http_code != 200 ? $response : null));

// Handle errors
if ($curl_error) {
    http_response_code(503);
    echo json_encode([
        'success' => false, 
        'error' => 'Service temporarily unavailable. Please try again.',
        'code' => 'API_CONNECTION_ERROR',
        'debug' => $curl_error // Remove in production
    ]);
    exit();
}

if ($http_code !== 200) {
    $error_data = json_decode($response, true);
    $error_message = $error_data['error']['message'] ?? 'Unknown API error';
    
    http_response_code(502);
    echo json_encode([
        'success' => false, 
        'error' => 'AI service error: ' . $error_message,
        'code' => 'API_ERROR',
        'status_code' => $http_code
    ]);
    exit();
}

$response_data = json_decode($response, true);

if (!isset($response_data['choices'][0]['message']['content'])) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid response from AI service',
        'code' => 'INVALID_API_RESPONSE'
    ]);
    exit();
}

$ai_content = $response_data['choices'][0]['message']['content'];

// Enhanced JSON parsing with better cleaning
$ai_content = preg_replace('/```json\s*|\s*```/', '', $ai_content);
$ai_content = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $ai_content); // Remove control characters
$ai_content = trim($ai_content);

// Try to extract JSON if there's extra text
if (!str_starts_with($ai_content, '{')) {
    if (preg_match('/\{[\s\S]*\}/', $ai_content, $matches)) {
        $ai_content = $matches[0];
    }
}

$ad_data = json_decode($ai_content, true);

if (!$ad_data || !isset($ad_data['hook'])) {
    // Attempt to fix common JSON issues
    $fixed_json = json_encode($ad_data);
    if ($fixed_json === null) {
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to parse AI response',
            'code' => 'JSON_PARSE_ERROR',
            'debug' => substr($ai_content, 0, 500) // Remove in production
        ]);
        exit();
    }
}

// Validate required fields in response
$required_ad_fields = ['hook', 'caption', 'visual_design', 'color_palette', 'font_style', 'cta', 'marketing_angle'];
$missing_ad_fields = array_diff($required_ad_fields, array_keys($ad_data));

if (!empty($missing_ad_fields)) {
    // Fill missing fields with defaults
    foreach ($missing_ad_fields as $field) {
        switch ($field) {
            case 'hook':
                $ad_data['hook'] = "Don't Miss Out!";
                break;
            case 'caption':
                $ad_data['caption'] = "Limited time offer!";
                break;
            case 'cta':
                $ad_data['cta'] = "Shop Now";
                break;
            default:
                $ad_data[$field] = "Customize this";
        }
    }
}

// Save to database with transaction
try {
    $db->beginTransaction();
    
    $stmt = $db->prepare("
        INSERT INTO projects (
            user_id, 
            product_name, 
            target_audience, 
            price, 
            brand_style, 
            generated_ad, 
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $json_ad = json_encode($ad_data);
    
    if ($stmt->execute([$_SESSION['user_id'], $product_name, $target_audience, $price, $brand_style, $json_ad])) {
        $project_id = $db->lastInsertId();
        
        // Update rate limit count
        $update_stmt = $db->prepare("
            UPDATE generation_logs 
            SET status = 'success' 
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) 
            ORDER BY created_at DESC LIMIT 1
        ");
        $update_stmt->execute([$_SESSION['user_id']]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'project_id' => $project_id, 
            'ad_data' => $ad_data,
            'response_time_ms' => $response_time_ms
        ]);
    } else {
        throw new Exception('Database insert failed');
    }
} catch (Exception $e) {
    $db->rollBack();
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Failed to save project: ' . $e->getMessage(),
        'code' => 'DATABASE_ERROR'
    ]);
}
?>