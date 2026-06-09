<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Logger helper
function log_message($message) {
    $logPath = __DIR__ . '/../../backend/api.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($logPath, "[$time] $message\n", FILE_APPEND);
}

log_message("Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

// Simple pure PHP JWT implementation
class SimpleJWT {
    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    
    private static function base64UrlDecode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }

    public static function sign($payload, $secret, $expiry = 7200) {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $payload['exp'] = time() + $expiry;
        $payloadJson = json_encode($payload);
        
        $base64Header = self::base64UrlEncode($header);
        $base64Payload = self::base64UrlEncode($payloadJson);
        
        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", $secret, true);
        $base64Signature = self::base64UrlEncode($signature);
        
        return "$base64Header.$base64Payload.$base64Signature";
    }

    public static function verify($token, $secret) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        
        list($base64Header, $base64Payload, $base64Signature) = $parts;
        
        $signature = hash_hmac('sha256', "$base64Header.$base64Payload", $secret, true);
        if (self::base64UrlEncode($signature) !== $base64Signature) {
            return false;
        }
        
        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false; // Expired
        }
        
        return $payload;
    }
}

// Global environment variables container
$env_vars = [];

// Load .env variables
function load_env($path) {
    global $env_vars;
    if (!file_exists($path)) {
        log_message("Warning: .env file not found at $path");
        return;
    }
    log_message("Loading .env file from $path");
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) continue;
        $name = trim($parts[0]);
        $value = trim($parts[1]);
        $value = preg_replace('/^["\']|["\']$/', '', $value);
        $env_vars[$name] = $value;
        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}

// Helper to retrieve env variables safely
function get_env_var($name, $default = null) {
    global $env_vars;
    if (isset($env_vars[$name])) return $env_vars[$name];
    if (isset($_ENV[$name])) return $_ENV[$name];
    $val = getenv($name);
    return $val !== false ? $val : $default;
}

// Pure PHP SMTP Client using Sockets (SSL port 465 or plain)
function send_smtp_mail($to, $subject, $html_body, $config) {
    $host = $config['host'];
    $port = $config['port'];
    $user = $config['user'];
    $pass = $config['pass'];
    $from = $config['from'];
    
    log_message("SMTP: Connecting to $host:$port...");
    
    $from_email = $user;
    if (preg_match('/<([^>]+)>/', $from, $matches)) {
        $from_email = $matches[1];
    }
    
    $socket_url = ($port == 465) ? "ssl://$host" : $host;
    $socket = stream_socket_client("$socket_url:$port", $errno, $errstr, 15);
    if (!$socket) {
        throw new Exception("Could not connect to SMTP server: $errstr ($errno)");
    }
    
    fgets($socket, 512);
    
    $commands = [
        "EHLO " . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost') => 250,
        "AUTH LOGIN" => 334,
        base64_encode($user) => 334,
        base64_encode($pass) => 235,
        "MAIL FROM:<$from_email>" => 250,
        "RCPT TO:<$to>" => 250,
        "DATA" => 354
    ];
    
    foreach ($commands as $cmd => $expected_code) {
        fwrite($socket, $cmd . "\r\n");
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if ($code !== $expected_code) {
            fclose($socket);
            throw new Exception("SMTP Error after command '$cmd': $response");
        }
    }
    
    // Headers
    $headers = [
        "MIME-Version: 1.0",
        "Content-type: text/html; charset=UTF-8",
        "From: $from",
        "To: <$to>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?="
    ];
    
    $data = implode("\r\n", $headers) . "\r\n\r\n" . $html_body . "\r\n.\r\n";
    fwrite($socket, $data);
    
    $response = fgets($socket, 512);
    $code = (int)substr($response, 0, 3);
    if ($code !== 250) {
        fclose($socket);
        throw new Exception("SMTP Error after DATA: $response");
    }
    
    fwrite($socket, "QUIT\r\n");
    fclose($socket);
    log_message("SMTP: Email successfully sent to $to");
    return true;
}

// Locate and load .env file
$envPath = __DIR__ . '/../../backend/.env';
if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/.env'; // fallback
}
load_env($envPath);

$secret = get_env_var('SECRET_KEY', 'afsos_super_secret_key_change_me');

// Database Initialization (SQLite)
$dbPath = __DIR__ . '/../../backend/database.sqlite';
$dbDir = dirname($dbPath);
if (!file_exists($dbDir)) {
    mkdir($dbDir, 0755, true);
}

log_message("Opening database at: $dbPath");
$db = new SQLite3($dbPath);
$db->exec("PRAGMA foreign_keys = ON;");

// Initialize Tables
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT UNIQUE,
    has_voted BOOLEAN DEFAULT 0,
    reminder_sent DATETIME,
    login_code TEXT,
    login_code_expires DATETIME
)");

$db->exec("CREATE TABLE IF NOT EXISTS candidates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    profession TEXT,
    location TEXT,
    pdf_url TEXT
)");

$db->exec("CREATE TABLE IF NOT EXISTS votes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    candidate_id INTEGER,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(candidate_id) REFERENCES candidates(id)
)");

$db->exec("CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT
)");

// Seed settings
if ($db->querySingle("SELECT COUNT(*) FROM settings WHERE key = 'max_votes'") == 0) {
    $db->exec("INSERT INTO settings (key, value) VALUES ('max_votes', '5')");
}
if ($db->querySingle("SELECT COUNT(*) FROM settings WHERE key = 'election_end_date'") == 0) {
    $defaultDate = date('Y-m-d\TH:i', time() + 7 * 24 * 3600);
    $db->exec("INSERT INTO settings (key, value) VALUES ('election_end_date', '$defaultDate')");
}
if ($db->querySingle("SELECT COUNT(*) FROM users WHERE email = 'admin@afsos.org'") == 0) {
    $db->exec("INSERT INTO users (email) VALUES ('admin@afsos.org')");
}

// Router parsing
$request_uri = $_SERVER['REQUEST_URI'];
$path = '';
if (preg_match('/\/api\/([^\?]+)/', $request_uri, $matches)) {
    $path = trim($matches[1], '/');
}

// Helper to authenticate JWT token
function get_auth_user($db, $secret) {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : '';
    if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }
    
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        return SimpleJWT::verify($token, $secret);
    }
    return null;
}

// Fallback headers helper
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

// -------------------------------------------------------------
// ENDPOINTS
// -------------------------------------------------------------

// 1. GET public-settings
if ($path === 'public-settings' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $max_votes = $db->querySingle("SELECT value FROM settings WHERE key = 'max_votes'");
    $end_date = $db->querySingle("SELECT value FROM settings WHERE key = 'election_end_date'");
    echo json_encode(['max_votes' => $max_votes, 'election_end_date' => $end_date]);
    exit;
}

// 2. POST request-code
if ($path === 'request-code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = isset($data['email']) ? trim($data['email']) : '';
    
    log_message("Request code for email: $email");
    
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $res = $stmt->execute();
    $user = $res->fetchArray(SQLITE3_ASSOC);
    
    $isAdmin = ($email === 'admin@afsos.org');
    if (!$user && !$isAdmin) {
        log_message("Access denied: User not eligible: $email");
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    
    // Check if election closed
    $endDateStr = $db->querySingle("SELECT value FROM settings WHERE key = 'election_end_date'");
    if ($endDateStr && !$isAdmin) {
        if (time() > strtotime($endDateStr)) {
            log_message("Access denied: Election closed: $email");
            http_response_code(403);
            echo json_encode(['error' => 'election_closed']);
            exit;
        }
    }
    
    $code = (string)rand(100000, 999999);
    $expires = date('c', time() + 15 * 60);
    
    $stmt2 = $db->prepare("UPDATE users SET login_code = :code, login_code_expires = :expires WHERE email = :email");
    $stmt2->bindValue(':code', $code, SQLITE3_TEXT);
    $stmt2->bindValue(':expires', $expires, SQLITE3_TEXT);
    $stmt2->bindValue(':email', $email, SQLITE3_TEXT);
    $stmt2->execute();
    
    $subject = 'Votre code de connexion sécurisé - Élection du CA AFSOS';
    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
        <div style="background-color: #1a3a6b; padding: 20px; text-align: center;">
            <h2 style="color: #ffffff; margin: 0; font-size: 20px;">Élection du Conseil d\'Administration</h2>
        </div>
        <div style="padding: 30px; background-color: #ffffff; color: #1e293b;">
            <p style="font-size: 16px; margin-bottom: 20px;">Bonjour,</p>
            <p style="font-size: 15px; line-height: 1.5; margin-bottom: 30px;">
                Vous avez demandé à vous connecter au portail de vote sécurisé de l\'AFSOS. 
                Veuillez utiliser le code de sécurité à 6 chiffres ci-dessous pour finaliser votre connexion :
            </p>
            <div style="text-align: center; margin-bottom: 30px;">
                <span style="display: inline-block; background-color: #f4f6f9; border: 1px solid #c9d3e0; border-radius: 6px; padding: 15px 30px; font-size: 28px; font-weight: bold; letter-spacing: 5px; color: #1a3a6b;">
                    ' . $code . '
                </span>
            </div>
            <p style="font-size: 13px; color: #475569; margin-bottom: 10px;">
                <em>Ce code est valide pendant 15 minutes. Ne le partagez avec personne.</em>
            </p>
            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
            <p style="font-size: 12px; color: #94a3b8; text-align: center;">
                Ceci est un email automatique, merci de ne pas y répondre.
            </p>
        </div>
    </div>';
    
    $smtp_host = get_env_var('SMTP_HOST');
    $smtp_user = get_env_var('SMTP_USER');
    
    if (!empty($smtp_host) && !empty($smtp_user)) {
        try {
            log_message("Sending code via SMTP to $email (Host: $smtp_host)");
            send_smtp_mail($email, $subject, $html, [
                'host' => $smtp_host,
                'port' => get_env_var('SMTP_PORT', 465),
                'user' => $smtp_user,
                'pass' => get_env_var('SMTP_PASS'),
                'from' => get_env_var('SMTP_FROM', '"AFSOS" <noreply@vote-afsos.com>')
            ]);
            echo json_encode(['success' => true, 'message' => 'Code envoyé par email']);
        } catch (Exception $e) {
            log_message("SMTP Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur lors de l\'envoi de l\'email: ' . $e->getMessage()]);
        }
    } else {
        log_message("Dev Mode: SMTP config missing in env. Returning code $code in body.");
        echo json_encode(['success' => true, 'message' => 'Code généré (Mode Démo)', 'code' => $code]);
    }
    exit;
}

// 3. POST verify-code
if ($path === 'verify-code' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = isset($data['email']) ? trim($data['email']) : '';
    $code = isset($data['code']) ? trim($data['code']) : '';
    
    $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $res = $stmt->execute();
    $user = $res->fetchArray(SQLITE3_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Utilisateur introuvable']);
        exit;
    }
    
    $isAdmin = ($email === 'admin@afsos.org');
    $isStaticAdminCode = ($isAdmin && $code === '199719');
    
    if (!$isStaticAdminCode) {
        $now = time();
        $expires = strtotime($user['login_code_expires']);
        if ($user['login_code'] !== $code || $now > $expires) {
            http_response_code(401);
            echo json_encode(['error' => 'Code de sécurité invalide ou expiré.']);
            exit;
        }
        $stmt2 = $db->prepare("UPDATE users SET login_code = NULL, login_code_expires = NULL WHERE id = :id");
        $stmt2->bindValue(':id', $user['id'], SQLITE3_INTEGER);
        $stmt2->execute();
    }
    
    $token = SimpleJWT::sign(['id' => $user['id'], 'email' => $email, 'isAdmin' => $isAdmin], $secret);
    echo json_encode([
        'token' => $token,
        'user' => [
            'email' => $email,
            'has_voted' => (bool)$user['has_voted'],
            'isAdmin' => $isAdmin
        ]
    ]);
    exit;
}

// 4. GET candidates
if ($path === 'candidates' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = get_auth_user($db, $secret);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Non autorisé']);
        exit;
    }
    
    $res = $db->query("SELECT * FROM candidates");
    $candidates = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $candidates[] = $row;
    }
    echo json_encode($candidates);
    exit;
}

// 5. POST vote
if ($path === 'vote' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = get_auth_user($db, $secret);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Non autorisé']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $candidateIds = isset($data['candidateIds']) ? $data['candidateIds'] : [];
    
    $max_votes = (int)$db->querySingle("SELECT value FROM settings WHERE key = 'max_votes'");
    if (count($candidateIds) > $max_votes) {
        http_response_code(400);
        echo json_encode(['error' => "Vous ne pouvez voter que pour $max_votes candidats maximum."]);
        exit;
    }
    
    $endDateStr = $db->querySingle("SELECT value FROM settings WHERE key = 'election_end_date'");
    if ($endDateStr) {
        if (time() > strtotime($endDateStr)) {
            http_response_code(403);
            echo json_encode(['error' => "L'élection est clôturée. Vous ne pouvez plus voter."]);
            exit;
        }
    }
    
    $has_voted = $db->querySingle("SELECT has_voted FROM users WHERE id = " . (int)$user['id']);
    if ($has_voted) {
        http_response_code(400);
        echo json_encode(['error' => "Vous avez déjà voté."]);
        exit;
    }
    
    $db->exec("BEGIN TRANSACTION;");
    try {
        if (count($candidateIds) > 0) {
            $stmt = $db->prepare("INSERT INTO votes (candidate_id) VALUES (:cid)");
            foreach ($candidateIds as $cid) {
                $stmt->bindValue(':cid', $cid, SQLITE3_INTEGER);
                $stmt->execute();
            }
        } else {
            $db->exec("INSERT INTO votes (candidate_id) VALUES (NULL)");
        }
        
        $db->exec("UPDATE users SET has_voted = 1 WHERE id = " . (int)$user['id']);
        $db->exec("COMMIT;");
        
        $voterEmail = $user['email'];
        if ($voterEmail && $voterEmail !== 'admin@afsos.org') {
            $subject = 'Confirmation de votre vote - Élection du CA AFSOS';
            $html = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                <div style="background-color: #1a3a6b; padding: 20px; text-align: center;">
                    <h2 style="color: #ffffff; margin: 0; font-size: 20px;">Élection du Conseil d\'Administration</h2>
                </div>
                <div style="padding: 30px; background-color: #ffffff; color: #1e293b;">
                    <p style="font-size: 16px; margin-bottom: 20px;">Bonjour,</p>
                    <p style="font-size: 15px; line-height: 1.5; margin-bottom: 20px;">
                        Nous vous confirmons que votre vote pour l\'élection du Conseil d\'Administration de l\'AFSOS a bien été enregistré avec succès.
                    </p>
                    <p style="font-size: 15px; line-height: 1.5; margin-bottom: 30px;">
                        Conformément aux principes de confidentialité et d\'anonymat de notre système de vote électronique, le détail de vos choix reste strictement secret et n\'est pas lié à votre identité.
                    </p>
                    <p style="font-size: 14px; color: #475569; margin-bottom: 10px;">
                        Merci pour votre participation active.
                    </p>
                    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
                    <p style="font-size: 12px; color: #94a3b8; text-align: center;">
                        Ceci est un email automatique, merci de ne pas y répondre.
                    </p>
                </div>
            </div>';
            
            $smtp_host = get_env_var('SMTP_HOST');
            $smtp_user = get_env_var('SMTP_USER');
            
            if (!empty($smtp_host) && !empty($smtp_user)) {
                try {
                    log_message("Sending vote confirmation via SMTP to $voterEmail");
                    send_smtp_mail($voterEmail, $subject, $html, [
                        'host' => $smtp_host,
                        'port' => get_env_var('SMTP_PORT', 465),
                        'user' => $smtp_user,
                        'pass' => get_env_var('SMTP_PASS'),
                        'from' => get_env_var('SMTP_FROM', '"AFSOS" <noreply@vote-afsos.com>')
                    ]);
                } catch (Exception $e) {
                    log_message("Vote confirmation SMTP error: " . $e->getMessage());
                }
            }
        }
        echo json_encode(['success' => true, 'message' => 'Vote enregistré avec succès']);
    } catch (Exception $e) {
        $db->exec("ROLLBACK;");
        http_response_code(500);
        echo json_encode(['error' => "Erreur lors du vote: " . $e->getMessage()]);
    }
    exit;
}

// 6. GET settings
if ($path === 'settings' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    $res = $db->query("SELECT key, value FROM settings");
    $settings = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $settings[$row['key']] = $row['value'];
    }
    echo json_encode($settings);
    exit;
}

// 7. POST admin/settings
if ($path === 'admin/settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $max_votes = isset($data['max_votes']) ? $data['max_votes'] : '';
    $election_end_date = isset($data['election_end_date']) ? $data['election_end_date'] : '';
    
    $db->exec("BEGIN TRANSACTION;");
    if ($max_votes !== '') {
        $stmt = $db->prepare("UPDATE settings SET value = :val WHERE key = 'max_votes'");
        $stmt->bindValue(':val', (string)$max_votes, SQLITE3_TEXT);
        $stmt->execute();
    }
    if ($election_end_date !== '') {
        $stmt = $db->prepare("UPDATE settings SET value = :val WHERE key = 'election_end_date'");
        $stmt->bindValue(':val', $election_end_date, SQLITE3_TEXT);
        $stmt->execute();
    }
    $db->exec("COMMIT;");
    echo json_encode(['success' => true]);
    exit;
}

// 8. GET admin/stats
if ($path === 'admin/stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    $totalEligible = $db->querySingle("SELECT COUNT(*) FROM users WHERE email != 'admin@afsos.org'");
    $totalVoted = $db->querySingle("SELECT SUM(has_voted) FROM users WHERE email != 'admin@afsos.org'");
    
    $res = $db->query("SELECT candidate_id, COUNT(*) as vote_count FROM votes GROUP BY candidate_id");
    $results = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $results[] = $row;
    }
    
    echo json_encode([
        'stats' => [
            'totalEligible' => (int)$totalEligible,
            'totalVoted' => (int)$totalVoted
        ],
        'results' => $results
    ]);
    exit;
}

// 9. GET admin/users
if ($path === 'admin/users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    $res = $db->query("SELECT id, email, has_voted, reminder_sent FROM users WHERE email != 'admin@afsos.org'");
    $users = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $users[] = [
            'id' => $row['id'],
            'email' => $row['email'],
            'has_voted' => (bool)$row['has_voted'],
            'reminder_sent' => $row['reminder_sent']
        ];
    }
    echo json_encode($users);
    exit;
}

// 10. POST admin/remind-user
if ($path === 'admin/remind-user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = isset($data['userId']) ? (int)$data['userId'] : 0;
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id AND email != 'admin@afsos.org'");
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $targetUser = $res->fetchArray(SQLITE3_ASSOC);
    
    if (!$targetUser) {
        http_response_code(404);
        echo json_encode(['error' => 'Utilisateur introuvable']);
        exit;
    }
    if ($targetUser['has_voted']) {
        http_response_code(400);
        echo json_encode(['error' => 'L\'utilisateur a déjà voté']);
        exit;
    }
    
    $voteLink = get_env_var('VOTE_LINK', 'http://localhost:5173');
    log_message("Reminding user: " . $targetUser['email'] . " (Vote Link: $voteLink)");
    
    $subject = 'Élection du Conseil d\'Administration AFSOS - Ouverture du scrutin';
    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
        <div style="background-color: #1a3a6b; padding: 20px; text-align: center;">
            <h2 style="color: #ffffff; margin: 0; font-size: 20px;">Élection du Conseil d\'Administration</h2>
        </div>
        <div style="padding: 30px; background-color: #ffffff; color: #1e293b;">
            <p style="font-size: 15px; line-height: 1.5; margin-bottom: 20px;">
                Cher membre AFSOS,<br/><br/>
                L\'élection au vote du Conseil d\'Administration AFSOS est désormais ouverte.
            </p>
            <p style="font-size: 15px; line-height: 1.5; margin-bottom: 30px;">
                Pour participer au vote, veuillez vous rendre sur la plateforme sécurisée en cliquant sur le bouton ci-dessous :
            </p>
            <div style="text-align: center; margin-bottom: 30px;">
                <a href="' . $voteLink . '" style="display: inline-block; background-color: #1a3a6b; color: #ffffff; text-decoration: none; padding: 12px 25px; border-radius: 6px; font-weight: bold;">
                    Accéder au portail de vote
                </a>
            </div>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 20px;">
                Si le bouton ci-dessus ne fonctionne pas, vous pouvez copier et coller ce lien dans votre navigateur :<br/>
                <a href="' . $voteLink . '" style="color: #1a3a6b;">' . $voteLink . '</a>
            </p>
            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
            <p style="font-size: 12px; color: #94a3b8; text-align: center;">
                Ceci est un email automatique, merci de ne pas y répondre.
            </p>
        </div>
    </div>';
    
    $smtp_host = get_env_var('SMTP_HOST');
    $smtp_user = get_env_var('SMTP_USER');
    
    if (!empty($smtp_host) && !empty($smtp_user)) {
        try {
            send_smtp_mail($targetUser['email'], $subject, $html, [
                'host' => $smtp_host,
                'port' => get_env_var('SMTP_PORT', 465),
                'user' => $smtp_user,
                'pass' => get_env_var('SMTP_PASS'),
                'from' => get_env_var('SMTP_FROM', '"AFSOS" <noreply@vote-afsos.com>')
            ]);
            
            $now = date('Y-m-d H:i:s');
            $db->exec("UPDATE users SET reminder_sent = '$now' WHERE id = $userId");
            echo json_encode(['success' => true, 'message' => "Invitation envoyée à " . $targetUser['email']]);
        } catch (Exception $e) {
            log_message("Remind user SMTP error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erreur SMTP: ' . $e->getMessage()]);
        }
    } else {
        $now = date('Y-m-d H:i:s');
        $db->exec("UPDATE users SET reminder_sent = '$now' WHERE id = $userId");
        log_message("Dev Mode: Simulated invitation to " . $targetUser['email']);
        echo json_encode(['success' => true, 'message' => 'Invitation envoyée (Simulée)']);
    }
    exit;
}

// 11. POST admin/upload-csv
if ($path === 'admin/upload-csv' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'Fichier CSV requis']);
        exit;
    }
    
    $file = fopen($_FILES['file']['tmp_name'], 'r');
    if (!$file) {
        http_response_code(500);
        echo json_encode(['error' => 'Impossible d\'ouvrir le fichier']);
        exit;
    }
    
    $headers = fgetcsv($file);
    $emailIdx = -1;
    foreach ($headers as $idx => $header) {
        $h = trim(strtolower($header));
        if ($h === 'email' || $h === 'e-mail' || strpos($h, 'mail') !== false) {
            $emailIdx = $idx;
            break;
        }
    }
    if ($emailIdx === -1) $emailIdx = 0;
    
    $emails = [];
    while (($row = fgetcsv($file)) !== false) {
        if (isset($row[$emailIdx])) {
            $email = trim($row[$emailIdx]);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower($email);
            }
        }
    }
    fclose($file);
    
    if (count($emails) > 0) {
        $db->exec("BEGIN TRANSACTION;");
        $placeholders = implode(',', array_map(function($e) {
            return "'" . SQLite3::escapeString($e) . "'";
        }, $emails));
        
        $db->exec("DELETE FROM users WHERE email != 'admin@afsos.org' AND email NOT IN ($placeholders)");
        
        $stmt = $db->prepare("INSERT OR IGNORE INTO users (email) VALUES (:email)");
        foreach ($emails as $email) {
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->execute();
        }
        $db->exec("COMMIT;");
    }
    echo json_encode(['success' => true, 'count' => count($emails)]);
    exit;
}

// 12. GET admin/export-results
if ($path === 'admin/export-results' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="resultats_afsos_vote.csv"');
    
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Candidat', 'Profession', 'Voix']);
    
    $res = $db->query("
        SELECT c.name, c.profession, COUNT(v.id) as vote_count
        FROM candidates c
        LEFT JOIN votes v ON c.id = v.candidate_id
        GROUP BY c.id
        ORDER BY vote_count DESC
    ");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        fputcsv($out, [$row['name'], $row['profession'], $row['vote_count']]);
    }
    
    $whiteVotes = $db->querySingle("SELECT COUNT(*) FROM votes WHERE candidate_id IS NULL");
    if ($whiteVotes > 0) {
        fputcsv($out, ['VOTE BLANC', '', $whiteVotes]);
    }
    fclose($out);
    exit;
}

// 13. POST admin/reset-vote
if ($path === 'admin/reset-vote' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    $db->exec("BEGIN TRANSACTION;");
    $db->exec("UPDATE users SET has_voted = 0, reminder_sent = NULL;");
    $db->exec("DELETE FROM votes;");
    $db->exec("COMMIT;");
    echo json_encode(['success' => true, 'message' => 'Élection réinitialisée avec succès.']);
    exit;
}

// 14. POST admin/candidates
if ($path === 'admin/candidates' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $profession = isset($_POST['profession']) ? trim($_POST['profession']) : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $pdf_url = '';
    
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
        $pdfDir = __DIR__ . '/uploads/pdfs';
        if (!file_exists($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        $filename = time() . '-' . preg_replace('/[^a-zA-Z0-9.]/', '_', $_FILES['pdf']['name']);
        move_uploaded_file($_FILES['pdf']['tmp_name'], "$pdfDir/$filename");
        $pdf_url = "/uploads/pdfs/$filename";
    }
    
    $stmt = $db->prepare("INSERT INTO candidates (name, profession, location, pdf_url) VALUES (:name, :profession, :location, :pdf_url)");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':profession', $profession, SQLITE3_TEXT);
    $stmt->bindValue(':location', $location, SQLITE3_TEXT);
    $stmt->bindValue(':pdf_url', $pdf_url, SQLITE3_TEXT);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'id' => $db->lastInsertRowID()]);
    exit;
}

// 15. DELETE admin/candidates/:id
if (preg_match('/^admin\/candidates\/(\d+)$/', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    
    $candidateId = (int)$matches[1];
    
    $pdf_url = $db->querySingle("SELECT pdf_url FROM candidates WHERE id = $candidateId");
    if ($pdf_url) {
        $filename = basename($pdf_url);
        $filePath = __DIR__ . '/uploads/pdfs/' . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    $db->exec("BEGIN TRANSACTION;");
    $db->exec("DELETE FROM votes WHERE candidate_id = $candidateId;");
    $db->exec("DELETE FROM candidates WHERE id = $candidateId;");
    $db->exec("COMMIT;");
    echo json_encode(['success' => true, 'message' => 'Candidat supprimé.']);
    exit;
}

// 16. GET admin/queue-status (Mock)
if ($path === 'admin/queue-status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = get_auth_user($db, $secret);
    if (!$user || !$user['isAdmin']) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit;
    }
    echo json_encode(['queueLength' => 0, 'isProcessing' => false, 'delayMs' => 20000]);
    exit;
}

// Catch-all
log_message("Warning: Endpoint not found for path: $path");
http_response_code(404);
echo json_encode(['error' => 'Not Found']);
exit;
