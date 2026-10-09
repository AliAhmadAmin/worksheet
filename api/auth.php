<?php
/**
 * Authentication API: Strict Email & Password Login
 * Default Password: DiscoverPakistan123
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true) ?? $_POST;
    $action = $data['action'] ?? $action;
}

switch ($action) {
    case 'login':
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if (empty($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email address is required to log in.']);
            exit;
        }

        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password is required.']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT e.*, d.name as department_name, t.name as team_name 
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.id 
            LEFT JOIN teams t ON e.team_id = t.id 
            WHERE LOWER(e.email) = LOWER(?) AND e.is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No employee account found with this email address.']);
            exit;
        }

        if (isset($user['can_login']) && (int)$user['can_login'] === 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Portal login access is disabled for this staff profile.']);
            exit;
        }

        if (empty($user['password_hash'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'No login password configured for this employee profile.']);
            exit;
        }

        // Brute force rate-limiting check
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $rateLimitKey = 'login_attempts_' . md5($clientIp . '_' . strtolower($email));
        $attempts = $_SESSION[$rateLimitKey] ?? ['count' => 0, 'locked_until' => 0];

        if ($attempts['locked_until'] > time()) {
            $remaining = ceil(($attempts['locked_until'] - time()) / 60);
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => "Too many failed login attempts. Please wait {$remaining} minute(s) before trying again."]);
            exit;
        }

        // Strict Password Verification
        $isValid = password_verify($password, $user['password_hash']);

        if (!$isValid) {
            // Increment failed attempts
            $attempts['count'] = ($attempts['count'] ?? 0) + 1;
            if ($attempts['count'] >= 5) {
                $attempts['locked_until'] = time() + (15 * 60); // Lock for 15 minutes
                $attempts['count'] = 0;
            }
            $_SESSION[$rateLimitKey] = $attempts;

            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid email or password. Please check your credentials.']);
            exit;
        }

        // Reset rate-limiting on success
        unset($_SESSION[$rateLimitKey]);

        // Regenerate Session ID to prevent Session Fixation Attacks
        session_regenerate_id(true);

        // Login success - populate session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['department_id'] = $user['department_id'] ?? 0;

        // Determine destination landing dashboard
        $redirectUrl = 'index.php';
        $userRole = strtolower($user['role'] ?? '');
        $deptLower = strtolower($user['department_name'] ?? '');
        if ($userRole === 'hr' || $deptLower === 'hr' || $deptLower === 'human resources') {
            $redirectUrl = 'hr.php';
        } elseif (stripos($deptLower, 'programming') !== false) {
            $redirectUrl = 'programming.php';
        } elseif (stripos($deptLower, 'news') !== false) {
            $redirectUrl = 'newsroom.php';
        }

        // Remove sensitive fields from output
        unset($user['password_hash']);

        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => $user,
            'redirect_url' => $redirectUrl
        ]);
        break;

    case 'current_user':
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            echo json_encode(['success' => false, 'logged_in' => false, 'message' => 'Authentication required']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT e.*, d.name as department_name, t.name as team_name 
            FROM employees e 
            LEFT JOIN departments d ON e.department_id = d.id 
            LEFT JOIN teams t ON e.team_id = t.id 
            WHERE e.id = ? AND e.is_active = 1
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user) {
            unset($user['password_hash']);
            echo json_encode(['success' => true, 'logged_in' => true, 'user' => $user]);
        } else {
            session_unset();
            session_destroy();
            echo json_encode(['success' => false, 'logged_in' => false, 'message' => 'Session expired']);
        }
        break;

    case 'logout':
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
