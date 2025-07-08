<?php
// api/get_monthly_stats.php
session_start();
require_once '../config/database.php';

// Verifica se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$user_id = $_SESSION['user_id'];
$current_year = date('Y');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Inizializza array per tutti i mesi
    $monthlyData = array_fill(0, 12, 0);
    
    // Query per ottenere i dati mensili dell'anno corrente
    $stmt = $pdo->prepare("
        SELECT MONTH(data_lettura) as mese, COUNT(*) as count 
        FROM Statistiche 
        WHERE utente_id = ? AND YEAR(data_lettura) = ?
        GROUP BY MONTH(data_lettura)
        ORDER BY MONTH(data_lettura)
    ");
    $stmt->execute([$user_id, $current_year]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthlyData[$row['mese'] - 1] = (int)$row['count'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $monthlyData,
        'year' => $current_year
    ]);
    
} catch (PDOException $e) {
    error_log("Errore database: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore nel caricamento dei dati']);
}
?>