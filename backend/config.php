<?php

// Load environment variables from .env 
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}


define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: ''); 
define('DB_NAME', getenv('DB_NAME') ?: 'account_payment_system');
define('DB_PORT', getenv('DB_PORT') ?: 3306);

// Application Settings
define('APP_NAME', 'Payment & Accounting Sub-system');
define('APP_VERSION', '1.0.0');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/payment-system');

// Fee Configuration (Default values - can be overridden by database)
define('DEFAULT_RATE_PER_UNIT', 500);
define('DEFAULT_MISC_FEES', 5000);
define('DEFAULT_LAB_FEES_PER_UNIT', 100);
define('DEFAULT_OTHER_FEES', 2000);

// Payment Configuration
define('PAYMENT_METHODS', [
    'Cash' => 'Cash',
    'GCash' => 'GCash',
    'Bank Transfer' => 'Bank Transfer',
    'Credit Card' => 'Credit Card',
    'Check' => 'Check'
]);

// Scholarship Types
define('SCHOLARSHIP_TYPES', [
    'full_scholarship' => 'Full Scholarship (100%)',
    'partial_scholarship' => 'Partial Scholarship',
    'discount' => 'Discount',
    'voucher' => 'Voucher',
    'grant' => 'Grant'
]);

// Discount Categories
define('DISCOUNT_CATEGORIES', [
    'academic' => 'Academic Excellence',
    'athletic' => 'Athletic Scholarship',
    'need_based' => 'Need-Based',
    'sibling' => 'Sibling Discount',
    'employee' => 'Employee Dependent',
    'early_bird' => 'Early Payment Discount',
    'loyalty' => 'Loyalty Discount'
]);

// Penalty Configuration
define('ENABLE_PENALTIES', true);
define('PENALTY_RATE', 0.02); // 2% per month
define('PENALTY_START_DAYS', 30); // Start charging penalties after 30 days
define('GRACE_PERIOD_DAYS', 7); // Grace period before penalties

// Receipt Configuration
define('RECEIPT_PREFIX', 'RCP');
define('TRANSACTION_PREFIX', 'TXN');
define('ASSESSMENT_PREFIX', 'ASM');
define('SCHOLARSHIP_PREFIX', 'SCH');

// File Upload Configuration
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('ALLOWED_FILE_TYPES', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

// Pagination
define('ITEMS_PER_PAGE', 10);
define('MAX_ITEMS_PER_PAGE', 100);

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('SESSION_NAME', 'PAYMENT_SYSTEM_SESSION');

// Email Configuration (for future use)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'noreply@school.edu');
define('SMTP_FROM_NAME', 'Payment System');

// SMS Configuration (for future use)
define('SMS_API_KEY', getenv('SMS_API_KEY') ?: '');
define('SMS_SENDER_NAME', 'SchoolPay');

// Timezone
date_default_timezone_set('Asia/Manila');

// Error Reporting - Use environment variable
$debug_mode = getenv('DEBUG_MODE') === 'true' || getenv('DEBUG_MODE') === '1';
define('DEBUG_MODE', $debug_mode);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Security Settings
define('ENABLE_CSRF_PROTECTION', true);
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_SPECIAL_CHAR', true);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Backup Configuration
define('BACKUP_DIR', __DIR__ . '/backups');
define('AUTO_BACKUP_ENABLED', true);
define('AUTO_BACKUP_FREQUENCY', 'daily'); // daily, weekly, monthly

// Logging
define('LOG_DIR', __DIR__ . '/logs');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_TRANSACTIONS', true);
define('LOG_LOGIN_ATTEMPTS', true);

// Currency
define('CURRENCY_SYMBOL', '₱');
define('CURRENCY_CODE', 'PHP');
define('DECIMAL_PLACES', 2);

// Academic Year Settings
define('CURRENT_SCHOOL_YEAR', '2025-2026');
define('SEMESTER_1_START', '06-01'); // MM-DD
define('SEMESTER_1_END', '10-31');
define('SEMESTER_2_START', '11-01');
define('SEMESTER_2_END', '03-31');
define('SUMMER_START', '04-01');
define('SUMMER_END', '05-31');

// Validation Rules
define('STUDENT_ID_PATTERN', '/^[0-9]{4}-[0-9]{3}$/'); // Format: YYYY-NNN
define('MIN_UNITS', 1);
define('MAX_UNITS', 30);

// Feature Flags
define('FEATURE_ONLINE_PAYMENT', false);
define('FEATURE_MOBILE_APP', false);
define('FEATURE_QR_CODE_PAYMENT', false);
define('FEATURE_EMAIL_NOTIFICATIONS', false);
define('FEATURE_SMS_NOTIFICATIONS', false);

// API Configuration
define('API_TIMEOUT', 30); // seconds
define('API_RATE_LIMIT', 100); // requests per minute

// Cache Configuration
define('ENABLE_CACHE', false);
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_TTL', 3600); // 1 hour

// System Status
define('SYSTEM_MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'System is under maintenance. Please try again later.');

/**
 * Get Configuration Value
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value if key not found
 * @return mixed Configuration value
 */
function getConfig($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

/**
 * Check if feature is enabled
 * 
 * @param string $feature Feature name
 * @return bool
 */
function isFeatureEnabled($feature) {
    $key = 'FEATURE_' . strtoupper($feature);
    return defined($key) && constant($key) === true;
}

/**
 * Get Database Connection
 * 
 * @return mysqli
 */
function getDatabaseConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    
    if ($conn->connect_error) {
        logMessage('ERROR', 'Database connection failed: ' . $conn->connect_error);
        
        if (DEBUG_MODE) {
            die("Connection failed: " . $conn->connect_error);
        } else {
            die("Database connection error. Please contact administrator.");
        }
    }
    
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Log Message
 * 
 * @param string $level Log level (DEBUG, INFO, WARNING, ERROR)
 * @param string $message Log message
 * @param array $context Additional context
 */
function logMessage($level, $message, $context = []) {
    if (!LOG_TRANSACTIONS && $level === 'INFO') {
        return;
    }
    
    $logFile = LOG_DIR . '/app_' . date('Y-m-d') . '.log';
    
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logEntry = "[$timestamp] [$level] $message $contextStr" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Sanitize Input
 * 
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Format Currency
 * 
 * @param float $amount Amount to format
 * @return string Formatted currency
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, DECIMAL_PLACES);
}

/**
 * Validate Student ID
 * 
 * @param string $studentId Student ID
 * @return bool
 */
function validateStudentId($studentId) {
    return preg_match(STUDENT_ID_PATTERN, $studentId);
}

/**
 * Hash Password (for future user management)
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify Password
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Initialize logging
if (LOG_TRANSACTIONS) {
    logMessage('INFO', 'System initialized', [
        'version' => APP_VERSION,
        'debug_mode' => DEBUG_MODE,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>