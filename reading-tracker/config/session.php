<?php
// config/session.php
// Gestione delle sessioni e autenticazione

// Configurazione sicurezza sessione - SOLO se la sessione non è già attiva
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Impostare a 1 per HTTPS
    
    // Avvia la sessione
    session_start();
}

// Funzione per verificare se l'utente è loggato
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Funzione per ottenere l'ID utente corrente
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Funzione per ottenere lo username corrente
function getCurrentUsername() {
    return $_SESSION['username'] ?? null;
}

// Funzione per fare il login
function login($userId, $username) {
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
    
    // Regenera l'ID di sessione per sicurezza
    session_regenerate_id(true);
}

// Funzione per fare il logout
function logout() {
    // Cancella tutte le variabili di sessione
    $_SESSION = array();
    
    // Cancella il cookie di sessione
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Distrugge la sessione
    session_destroy();
    
    // Reindirizza alla pagina di login
    header("Location: login.php");
    exit();
}

// Funzione per verificare se la sessione è scaduta
function isSessionExpired() {
    $timeout = 3600; // 1 ora
    
    if (isset($_SESSION['login_time'])) {
        if (time() - $_SESSION['login_time'] > $timeout) {
            return true;
        }
    }
    
    return false;
}

// Funzione per richiedere il login
function requireLogin() {
    if (!isLoggedIn() || isSessionExpired()) {
        if (isSessionExpired()) {
            logout();
        }
        header("Location: login.php");
        exit();
    }
}

// Funzione per reindirizzare se già loggato
function redirectIfLoggedIn() {
    if (isLoggedIn() && !isSessionExpired()) {
        header("Location: dashboard.php");
        exit();
    }
}

// Funzione per impostare un messaggio flash
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Funzione per ottenere e cancellare un messaggio flash
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

// Funzione per validare token CSRF
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Funzione per generare token CSRF
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Aggiorna il timestamp della sessione
if (isLoggedIn()) {
    $_SESSION['login_time'] = time();
}
?>