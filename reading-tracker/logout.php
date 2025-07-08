<?php
// logout.php
session_start();

// Distruggi tutte le variabili di sessione
$_SESSION = array();

// Se viene usato un cookie di sessione, cancellalo
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Distruggi la sessione
session_destroy();

// Imposta un messaggio flash di successo
session_start();
$_SESSION['flash_message'] = array(
    'type' => 'success',
    'message' => 'Logout effettuato con successo!'
);

// Reindirizza alla pagina di login o homepage
header("Location: index.php");
exit();
?>