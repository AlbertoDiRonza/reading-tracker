<?php
// config/database.php
// Configurazione della connessione al database

// Configurazioni del database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'reading_tracker';
$charset = 'utf8mb4';

// DSN (Data Source Name) per PDO
$dsn = "mysql:host=$host;dbname=$database;charset=$charset";

// Opzioni PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // Connessione al database
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Imposta il charset
    $pdo->exec("SET NAMES $charset");
    
} catch (PDOException $e) {
    // Gestione errori di connessione
    error_log("Errore di connessione al database: " . $e->getMessage());
    die("Errore di connessione al database. Controlla la configurazione.");
}

// Funzione per ottenere la connessione
function getConnection() {
    global $pdo;
    return $pdo;
}

// Funzione per chiudere la connessione
function closeConnection() {
    global $pdo;
    $pdo = null;
}

// Funzione per test della connessione
function testConnection() {
    try {
        $pdo = getConnection();
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>