<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Funzione per rispondere con JSON
function jsonResponse($success, $message = '', $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'stats' => $data['stats'] ?? null,
        'charts' => $data['charts'] ?? null
    ]);
    exit;
}

// Verifica se l'utente è loggato
if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, 'Utente non autenticato');
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? 'dashboard';

try {
    // Connessione al database
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($type === 'dashboard') {
        // Statistiche per la dashboard
        $stats = getDashboardStats($pdo, $user_id);
        jsonResponse(true, 'Statistiche caricate', ['stats' => $stats]);
        
    } elseif ($type === 'charts') {
        // Dati per i grafici
        $charts = getChartsData($pdo, $user_id);
        jsonResponse(true, 'Grafici caricati', ['charts' => $charts]);
        
    } else {
        jsonResponse(false, 'Tipo di statistiche non valido');
    }
    
} catch (PDOException $e) {
    error_log("Errore database statistiche: " . $e->getMessage());
    jsonResponse(false, 'Errore nel caricamento delle statistiche');
} catch (Exception $e) {
    error_log("Errore generico statistiche: " . $e->getMessage());
    jsonResponse(false, 'Errore nel caricamento delle statistiche');
}

// Funzione per ottenere statistiche dashboard
function getDashboardStats($pdo, $user_id) {
    $currentYear = date('Y');
    
    // Totale libri letti
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalBooks = $stmt->fetchColumn();
    
    // Libri letti quest'anno
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE user_id = ? AND YEAR(date_read) = ?");
    $stmt->execute([$user_id, $currentYear]);
    $booksThisYear = $stmt->fetchColumn();
    
    // Genere preferito
    $stmt = $pdo->prepare("
        SELECT genre, COUNT(*) as count 
        FROM books 
        WHERE user_id = ? 
        GROUP BY genre 
        ORDER BY count DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $favoriteGenre = $stmt->fetchColumn() ?: 'Nessuno';
    
    // Obiettivo corrente
    $stmt = $pdo->prepare("SELECT goal_books FROM reading_goals WHERE user_id = ? AND year = ?");
    $stmt->execute([$user_id, $currentYear]);
    $goalBooks = $stmt->fetchColumn() ?: 0;
    
    // Calcolo progresso obiettivo
    $goalProgress = 0;
    $goalProgressPercent = 0;
    
    if ($goalBooks > 0) {
        $goalProgress = $booksThisYear;
        $goalProgressPercent = round(($booksThisYear / $goalBooks) * 100);
    }
    
    // Pagine totali lette
    $stmt = $pdo->prepare("SELECT SUM(pages) FROM books WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $totalPages = $stmt->fetchColumn() ?: 0;
    
    // Media libri per mese (anno corrente)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) / 12 as avg_per_month 
        FROM books 
        WHERE user_id = ? AND YEAR(date_read) = ?
    ");
    $stmt->execute([$user_id, $currentYear]);
    $avgBooksPerMonth = round($stmt->fetchColumn() ?: 0, 1);
    
    // Streak di lettura (giorni consecutivi)
    $streak = calculateReadingStreak($pdo, $user_id);
    
    return [
        'total_books' => $totalBooks,
        'books_this_year' => $booksThisYear,
        'favorite_genre' => $favoriteGenre,
        'goal_progress' => $goalProgress,
        'goal_target' => $goalBooks,
        'goal_progress_percent' => $goalProgressPercent,
        'total_pages' => $totalPages,
        'avg_books_per_month' => $avgBooksPerMonth,
        'reading_streak' => $streak
    ];
}

// Funzione per ottenere dati per i grafici
function getChartsData($pdo, $user_id) {
    $currentYear = date('Y');
    
    // Dati generi
    $stmt = $pdo->prepare("
        SELECT genre, COUNT(*) as count 
        FROM books 
        WHERE user_id = ? 
        GROUP BY genre 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatta i dati per il grafico generi
    $genresData = array_map(function($genre) {
        return [
            'name' => $genre['genre'],
            'count' => (int)$genre['count']
        ];
    }, $genres);
    
    // Dati letture mensili (anno corrente)
    $monthlyData = [];
    $months = [
        1 => 'Gen', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mag', 6 => 'Giu', 7 => 'Lug', 8 => 'Ago',
        9 => 'Set', 10 => 'Ott', 11 => 'Nov', 12 => 'Dic'
    ];
    
    for ($month = 1; $month <= 12; $month++) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM books 
            WHERE user_id = ? 
            AND YEAR(date_read) = ? 
            AND MONTH(date_read) = ?
        ");
        $stmt->execute([$user_id, $currentYear, $month]);
        $count = $stmt->fetchColumn();
        
        $monthlyData[] = [
            'name' => $months[$month],
            'count' => (int)$count,
            'month' => $month
        ];
    }
    
    // Dati letture per anno (ultimi 5 anni)
    $yearlyData = [];
    for ($year = $currentYear - 4; $year <= $currentYear; $year++) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM books 
            WHERE user_id = ? 
            AND YEAR(date_read) = ?
        ");
        $stmt->execute([$user_id, $year]);
        $count = $stmt->fetchColumn();
        
        $yearlyData[] = [
            'name' => $year,
            'count' => (int)$count,
            'year' => $year
        ];
    }
    
    // Dati pagine per mese
    $pagesData = [];
    for ($month = 1; $month <= 12; $month++) {
        $stmt = $pdo->prepare("
            SELECT SUM(pages) 
            FROM books 
            WHERE user_id = ? 
            AND YEAR(date_read) = ? 
            AND MONTH(date_read) = ?
        ");
        $stmt->execute([$user_id, $currentYear, $month]);
        $pages = $stmt->fetchColumn() ?: 0;
        
        $pagesData[] = [
            'name' => $months[$month],
            'pages' => (int)$pages,
            'month' => $month
        ];
    }
    
    // Top 10 autori
    $stmt = $pdo->prepare("
        SELECT author, COUNT(*) as count 
        FROM books 
        WHERE user_id = ? 
        GROUP BY author 
        ORDER BY count DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $authorsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $authorsData = array_map(function($author) {
        return [
            'name' => $author['author'],
            'count' => (int)$author['count']
        ];
    }, $authorsData);
    
    // Statistiche tempo di lettura
    $readingTimeStats = getReadingTimeStats($pdo, $user_id);
    
    return [
        'genres' => $genresData,
        'monthly' => $monthlyData,
        'yearly' => $yearlyData,
        'pages' => $pagesData,
        'authors' => $authorsData,
        'reading_time' => $readingTimeStats
    ];
}

// Funzione per calcolare lo streak di lettura
function calculateReadingStreak($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT DATE(date_read) as date 
        FROM books 
        WHERE user_id = ? 
        ORDER BY date DESC
    ");
    $stmt->execute([$user_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($dates)) {
        return 0;
    }
    
    $streak = 0;
    $currentDate = new DateTime();
    
    foreach ($dates as $dateStr) {
        $date = new DateTime($dateStr);
        $diff = $currentDate->diff($date)->days;
        
        if ($diff === $streak) {
            $streak++;
            $currentDate = $date;
        } else {
            break;
        }
    }
    
    return $streak;
}

// Funzione per ottenere statistiche tempo di lettura
function getReadingTimeStats($pdo, $user_id) {
    $currentYear = date('Y');
    
    // Calcolo tempo medio di lettura per pagina (assumendo 250 parole per pagina e 200 parole al minuto)
    $wordsPerPage = 250;
    $wordsPerMinute = 200;
    $minutesPerPage = $wordsPerPage / $wordsPerMinute;
    
    // Pagine lette quest'anno
    $stmt = $pdo->prepare("
        SELECT SUM(pages) 
        FROM books 
        WHERE user_id = ? AND YEAR(date_read) = ?
    ");
    $stmt->execute([$user_id, $currentYear]);
    $pagesThisYear = $stmt->fetchColumn() ?: 0;
    
    // Tempo stimato di lettura quest'anno (in minuti)
    $readingTimeMinutes = $pagesThisYear * $minutesPerPage;
    $readingTimeHours = round($readingTimeMinutes / 60, 1);
    
    // Media pagine per libro
    $stmt = $pdo->prepare("
        SELECT AVG(pages) 
        FROM books 
        WHERE user_id = ? AND pages > 0
    ");
    $stmt->execute([$user_id]);
    $avgPagesPerBook = round($stmt->fetchColumn() ?: 0);
    
    // Libro più lungo
    $stmt = $pdo->prepare("
        SELECT title, pages 
        FROM books 
        WHERE user_id = ? 
        ORDER BY pages DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $longestBook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Libro più corto (escludendo 0 pagine)
    $stmt = $pdo->prepare("
        SELECT title, pages 
        FROM books 
        WHERE user_id = ? AND pages > 0 
        ORDER BY pages ASC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $shortestBook = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mese con più letture
    $stmt = $pdo->prepare("
        SELECT MONTH(date_read) as month, COUNT(*) as count 
        FROM books 
        WHERE user_id = ? AND YEAR(date_read) = ?
        GROUP BY MONTH(date_read) 
        ORDER BY count DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id, $currentYear]);
    $bestMonth = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $monthNames = [
        1 => 'Gennaio', 2 => 'Febbraio', 3 => 'Marzo', 4 => 'Aprile',
        5 => 'Maggio', 6 => 'Giugno', 7 => 'Luglio', 8 => 'Agosto',
        9 => 'Settembre', 10 => 'Ottobre', 11 => 'Novembre', 12 => 'Dicembre'
    ];
    
    $bestMonthName = $bestMonth ? $monthNames[$bestMonth['month']] : 'Nessuno';
    
    return [
        'pages_this_year' => $pagesThisYear,
        'reading_time_hours' => $readingTimeHours,
        'avg_pages_per_book' => $avgPagesPerBook,
        'longest_book' => $longestBook,
        'shortest_book' => $shortestBook,
        'best_month' => $bestMonthName,
        'best_month_count' => $bestMonth ? $bestMonth['count'] : 0
    ];
}
?>