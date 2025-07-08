<?php
// api/get_yearly_stats.php
session_start();
require_once '../config/database.php';

// Verifica se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorizzato']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query per ottenere i dati annuali
    $stmt = $pdo->prepare("
        SELECT 
            YEAR(data_lettura) as year,
            COUNT(*) as count 
        FROM Statistiche 
        WHERE utente_id = ? 
        GROUP BY YEAR(data_lettura)
        ORDER BY YEAR(data_lettura)
    ");
    $stmt->execute([$user_id]);
    
    $yearlyData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $yearlyData[] = [
            'year' => (int)$row['year'],
            'count' => (int)$row['count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $yearlyData
    ]);
    
} catch (PDOException $e) {
    error_log("Errore database: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore nel caricamento dei dati']);
}
?>