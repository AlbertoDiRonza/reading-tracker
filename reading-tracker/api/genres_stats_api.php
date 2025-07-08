<?php
// api/get_genres_stats.php
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
    
    // Query per ottenere la distribuzione dei generi
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(l.genere, 'Non specificato') as genre,
            COUNT(*) as count 
        FROM Statistiche s 
        JOIN Libro l ON s.libro_id = l.id 
        WHERE s.utente_id = ? 
        GROUP BY l.genere 
        ORDER BY count DESC
    ");
    $stmt->execute([$user_id]);
    
    $genresData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $genresData[] = [
            'genre' => $row['genre'],
            'count' => (int)$row['count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $genresData
    ]);
    
} catch (PDOException $e) {
    error_log("Errore database: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Errore nel caricamento dei dati']);
}
?>