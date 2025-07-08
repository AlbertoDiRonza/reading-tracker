<?php
// api/get_ratings_stats.php
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
    
    // Inizializza array per tutte le valutazioni (1-5 stelle)
    $ratingsData = array_fill(0, 5, 0);
    
    // Query per ottenere la distribuzione delle valutazioni
    $stmt = $pdo->prepare("
        SELECT 
            valutazione,
            COUNT(*) as count 
        FROM Statistiche 
        WHERE utente_id = ? AND valutazione IS NOT NULL
        GROUP BY valutazione
        ORDER BY valutazione
    ");
    $stmt->execute([$user_id]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rating = (int)$row['valutazione'];
        if ($rating >= 1 && $rating <= 5) {
            $ratingsData[$rating - 1] = (int)$row['count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $ratingsData
    ]);
    
} catch (PDOException $e) {
    error_log("Errore database: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore nel caricamento dei dati']);
}
?>