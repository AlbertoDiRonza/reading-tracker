<?php
// includes/functions.php
// Funzioni comuni per l'applicazione

require_once 'config/database.php';

/*
 * Converte stringhe vuote in NULL per il database
 * @param string|null $value
 * @return string|null
 */
function emptyToNull($value) {
    return empty($value) ? null : $value;
}

/*
 * Prepara i dati del libro per l'inserimento nel database
 * @param array $bookData
 * @return array
 */
function prepareBookData($bookData) {
    return [
        'isbn' => emptyToNull($bookData['isbn'] ?? ''),
        'titolo' => $bookData['title'] ?? '',
        'autore' => $bookData['author'] ?? '',
        'genere' => emptyToNull($bookData['genre'] ?? ''),
        'anno_pubblicazione' => emptyToNull($bookData['year'] ?? ''),
        'editore' => emptyToNull($bookData['publisher'] ?? ''),
        'pagine' => emptyToNull($bookData['pages'] ?? '')
    ];
}

/*
 * Cerca un libro esistente nel database
 * @param PDO $pdo
 * @param string $isbn
 * @param string $title
 * @param string $author
 * @return int|null
 */
function findExistingBook($pdo, $isbn, $title, $author) {
    $bookId = null;
    
    // Prima cerca per ISBN se presente
    if (!empty($isbn)) {
        $stmt = $pdo->prepare("SELECT id FROM Libro WHERE isbn = ?");
        $stmt->execute([$isbn]);
        $bookId = $stmt->fetchColumn();
    }
    
    // Se non trovato per ISBN, cerca per titolo e autore
    if (!$bookId) {
        $stmt = $pdo->prepare("SELECT id FROM Libro WHERE titolo = ? AND autore = ?");
        $stmt->execute([$title, $author]);
        $bookId = $stmt->fetchColumn();
    }
    
    return $bookId ?: null;
}

/*
 * Inserisce un nuovo libro nel database
 * @param PDO $pdo
 * @param array $bookData
 * @return int
 */
function insertBook($pdo, $bookData) {
    $data = prepareBookData($bookData);
    
    $stmt = $pdo->prepare("
        INSERT INTO Libro (isbn, titolo, autore, genere, anno_pubblicazione, editore, pagine) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['isbn'],
        $data['titolo'],
        $data['autore'],
        $data['genere'],
        $data['anno_pubblicazione'],
        $data['editore'],
        $data['pagine']
    ]);
    
    return $pdo->lastInsertId();
}

/*
 * Valida i dati di un libro
 * @param array $bookData
 * @return array Array di errori (vuoto se tutto ok)
 */
function validateBookData($bookData) {
    $errors = [];
    
    if (empty($bookData['title'])) {
        $errors[] = 'Il titolo è obbligatorio';
    }
    
    if (empty($bookData['author'])) {
        $errors[] = 'L\'autore è obbligatorio';
    }
    
    if (empty($bookData['read_date'])) {
        $errors[] = 'La data di lettura è obbligatoria';
    }
    
    if (!empty($bookData['isbn']) && !validateISBN($bookData['isbn'])) {
        $errors[] = 'ISBN non valido';
    }
    
    if (!empty($bookData['year']) && !validateYear($bookData['year'])) {
        $errors[] = 'Anno di pubblicazione non valido';
    }
    
    if (!empty($bookData['rating']) && !validateRating($bookData['rating'])) {
        $errors[] = 'Valutazione deve essere tra 1 e 5';
    }
    
    return $errors;
}

// Funzione per sanificare input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Funzione per validare email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Funzione per validare ISBN
function validateISBN($isbn) {
    // Rimuove trattini e spazi
    $isbn = preg_replace('/[^0-9X]/', '', $isbn);
    
    // Verifica lunghezza
    if (strlen($isbn) != 10 && strlen($isbn) != 13) {
        return false;
    }
    
    // Validazione ISBN-10
    if (strlen($isbn) == 10) {
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$isbn[$i] * (10 - $i);
        }
        $checksum = (11 - ($sum % 11)) % 11;
        $lastChar = ($checksum == 10) ? 'X' : (string)$checksum;
        return $isbn[9] == $lastChar;
    }
    
    // Validazione ISBN-13
    if (strlen($isbn) == 13) {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$isbn[$i] * (($i % 2) ? 3 : 1);
        }
        $checksum = (10 - ($sum % 10)) % 10;
        return $isbn[12] == (string)$checksum;
    }
    
    return false;
}

// Funzione per ottenere informazioni libro da ISBN
function getBookInfoFromISBN($isbn) {
    $url = "https://openlibrary.org/api/books?bibkeys=ISBN:" . $isbn . "&format=json&jscmd=data";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Reading Tracker App');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        $bookKey = "ISBN:" . $isbn;
        
        if (isset($data[$bookKey])) {
            $book = $data[$bookKey];
            return [
                'title' => $book['title'] ?? '',
                'authors' => isset($book['authors']) ? implode(', ', array_column($book['authors'], 'name')) : '',
                'publisher' => isset($book['publishers']) ? implode(', ', array_column($book['publishers'], 'name')) : '',
                'publish_year' => $book['publish_date'] ?? '',
                'pages' => $book['number_of_pages'] ?? 0,
                'subjects' => isset($book['subjects']) ? implode(', ', array_column($book['subjects'], 'name')) : ''
            ];
        }
    }
    
    return false;
}

// Funzione per aggiungere un libro al database
function addBook($isbn, $title, $author, $genre, $year, $publisher, $pages) {
    try {
        $pdo = getConnection();
        
        // Verifica se il libro esiste già
        $stmt = $pdo->prepare("SELECT id FROM Libro WHERE isbn = ? OR (titolo = ? AND autore = ?)");
        $stmt->execute([$isbn, $title, $author]);
        
        if ($stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Libro già presente nel database'];
        }
        
        // Inserisce il nuovo libro
        $stmt = $pdo->prepare("INSERT INTO Libro (isbn, titolo, autore, genere, anno_pubblicazione, editore, pagine) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$isbn, $title, $author, $genre, $year, $publisher, $pages]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Libro aggiunto con successo'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Errore durante l\'aggiunta del libro: ' . $e->getMessage()];
    }
}

// Funzione per ottenere libri dell'utente
function getUserBooks($userId) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("
            SELECT l.*, s.data_lettura, s.valutazione, s.note 
            FROM Libro l 
            JOIN Statistiche s ON l.id = s.libro_id 
            WHERE s.utente_id = ? 
            ORDER BY s.data_lettura DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return [];
    }
}

// Funzione per ottenere statistiche utente
function getUserStatistics($userId) {
    try {
        $pdo = getConnection();
        
        // Libri letti per anno
        $stmt = $pdo->prepare("
            SELECT YEAR(data_lettura) as anno, COUNT(*) as libri_letti 
            FROM Statistiche 
            WHERE utente_id = ? 
            GROUP BY YEAR(data_lettura) 
            ORDER BY anno DESC
        ");
        $stmt->execute([$userId]);
        $booksPerYear = $stmt->fetchAll();
        
        // Generi preferiti
        $stmt = $pdo->prepare("
            SELECT l.genere, COUNT(*) as count 
            FROM Libro l 
            JOIN Statistiche s ON l.id = s.libro_id 
            WHERE s.utente_id = ? 
            GROUP BY l.genere 
            ORDER BY count DESC
        ");
        $stmt->execute([$userId]);
        $genreStats = $stmt->fetchAll();
        
        // Valutazione media
        $stmt = $pdo->prepare("
            SELECT AVG(valutazione) as media_valutazione 
            FROM Statistiche 
            WHERE utente_id = ? AND valutazione IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $avgRating = $stmt->fetchColumn();
        
        // Totale libri letti
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Statistiche WHERE utente_id = ?");
        $stmt->execute([$userId]);
        $totalBooks = $stmt->fetchColumn();
        
        return [
            'books_per_year' => $booksPerYear,
            'genre_stats' => $genreStats,
            'avg_rating' => round($avgRating, 1),
            'total_books' => $totalBooks
        ];
        
    } catch (Exception $e) {
        return [
            'books_per_year' => [],
            'genre_stats' => [],
            'avg_rating' => 0,
            'total_books' => 0
        ];
    }
}

// Funzione per ottenere il progresso dell'utente
function getUserProgress($userId, $year = null) {
    try {
        $pdo = getConnection();
        
        if ($year === null) {
            $year = date('Y');
        }
        
        $stmt = $pdo->prepare("
            SELECT obiettivo_libri, libri_letti 
            FROM Progresso 
            WHERE utente_id = ? AND anno = ?
        ");
        $stmt->execute([$userId, $year]);
        $progress = $stmt->fetch();
        
        if (!$progress) {
            return ['obiettivo' => 0, 'letti' => 0, 'percentuale' => 0];
        }
        
        $percentage = $progress['obiettivo_libri'] > 0 ? 
            round(($progress['libri_letti'] / $progress['obiettivo_libri']) * 100, 1) : 0;
        
        return [
            'obiettivo' => $progress['obiettivo_libri'],
            'letti' => $progress['libri_letti'],
            'percentuale' => $percentage
        ];
        
    } catch (Exception $e) {
        return ['obiettivo' => 0, 'letti' => 0, 'percentuale' => 0];
    }
}

// Funzione per impostare obiettivo di lettura
function setReadingGoal($userId, $year, $goal) {
    try {
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO Progresso (utente_id, anno, obiettivo_libri, libri_letti) 
            VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE obiettivo_libri = ?
        ");
        $stmt->execute([$userId, $year, $goal, $goal]);
        
        return ['success' => true, 'message' => 'Obiettivo impostato con successo'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Errore durante l\'impostazione dell\'obiettivo'];
    }
}

// Funzione per aggiungere libro letto
function addBookRead($userId, $bookId, $readDate, $rating = null, $notes = '') {
    try {
        $pdo = getConnection();
        
        // Verifica se il libro è già stato letto da questo utente
        $stmt = $pdo->prepare("SELECT id FROM Statistiche WHERE utente_id = ? AND libro_id = ?");
        $stmt->execute([$userId, $bookId]);
        
        if ($stmt->fetchColumn()) {
            return ['success' => false, 'message' => 'Hai già letto questo libro'];
        }
        
        // Aggiungi la statistica
        $stmt = $pdo->prepare("
            INSERT INTO Statistiche (utente_id, libro_id, data_lettura, valutazione, note) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $bookId, $readDate, $rating, $notes]);
        
        return ['success' => true, 'message' => 'Libro aggiunto alla lista dei letti'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Errore durante l\'aggiunta: ' . $e->getMessage()];
    }
}

// Funzione per cercare libri nel database
function searchBooks($query) {
    try {
        $pdo = getConnection();
        $searchTerm = '%' . $query . '%';
        
        $stmt = $pdo->prepare("
            SELECT * FROM Libro 
            WHERE titolo LIKE ? OR autore LIKE ? OR isbn LIKE ?
            ORDER BY titolo
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return [];
    }
}

// Funzione per formattare la data
function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

// Funzione per ottenere i generi più letti
function getTopGenres($userId, $limit = 5) {
    try {
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("
            SELECT l.genere, COUNT(*) as count 
            FROM Libro l 
            JOIN Statistiche s ON l.id = s.libro_id 
            WHERE s.utente_id = ? AND l.genere IS NOT NULL AND l.genere != ''
            GROUP BY l.genere 
            ORDER BY count DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return [];
    }
}

// Funzione per ottenere libri recenti
function getRecentBooks($userId, $limit = 5) {
    try {
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("
            SELECT l.*, s.data_lettura, s.valutazione 
            FROM Libro l 
            JOIN Statistiche s ON l.id = s.libro_id 
            WHERE s.utente_id = ? 
            ORDER BY s.data_lettura DESC 
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return [];
    }
}

// Funzione per validare anno
function validateYear($year) {
    return is_numeric($year) && $year >= 1900 && $year <= date('Y') + 10;
}

// Funzione per validare valutazione
function validateRating($rating) {
    return is_numeric($rating) && $rating >= 1 && $rating <= 5;
}

// Funzione per generare colori casuali per i grafici
function generateRandomColor() {
    return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
}

// Funzione per ottenere tutti i libri disponibili
function getAllBooks($limit = 100) {
    try {
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("SELECT * FROM Libro ORDER BY titolo LIMIT ?");
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return [];
    }
}

// Funzione per log degli errori
function logError($message, $file = '', $line = '') {
    $logMessage = date('Y-m-d H:i:s') . " - Error: $message";
    if ($file) $logMessage .= " in $file";
    if ($line) $logMessage .= " on line $line";
    $logMessage .= PHP_EOL;
    
    error_log($logMessage, 3, 'logs/error.log');
}
?>