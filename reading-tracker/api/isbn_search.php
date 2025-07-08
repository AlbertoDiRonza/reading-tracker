<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Funzione per rispondere con JSON
function jsonResponse($success, $message = '', $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'books' => $data ?? []
    ]);
    exit;
}

// Verifica se è stata fornita una query ISBN
if (!isset($_GET['isbn']) || empty(trim($_GET['isbn']))) {
    jsonResponse(false, 'ISBN non fornito');
}

$isbn = trim($_GET['isbn']);

// Validazione base dell'ISBN
if (strlen($isbn) < 3) {
    jsonResponse(false, 'ISBN troppo corto');
}

// Funzione per pulire l'ISBN
function cleanISBN($isbn) {
    return preg_replace('/[^0-9X]/i', '', $isbn);
}

// Funzione per validare l'ISBN
function isValidISBN($isbn) {
    $cleanedISBN = cleanISBN($isbn);
    return strlen($cleanedISBN) === 10 || strlen($cleanedISBN) === 13;
}

// Funzione per chiamare l'API di Open Library
function searchOpenLibrary($isbn) {
    $cleanedISBN = cleanISBN($isbn);
    
    if (!isValidISBN($cleanedISBN)) {
        return null;
    }
    
    $url = "https://openlibrary.org/api/books?bibkeys=ISBN:{$cleanedISBN}&format=json&jscmd=data";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Reading Tracker App/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (empty($data)) {
        return null;
    }
    
    $bookData = reset($data); // Prendi il primo elemento
    
    return [
        'title' => $bookData['title'] ?? 'Titolo non disponibile',
        'author' => isset($bookData['authors']) ? 
                   implode(', ', array_map(function($author) { 
                       return $author['name']; 
                   }, $bookData['authors'])) : 'Autore non disponibile',
        'isbn' => $cleanedISBN,
        'publisher' => isset($bookData['publishers']) ? 
                      implode(', ', array_map(function($pub) { 
                          return $pub['name']; 
                      }, $bookData['publishers'])) : '',
        'publish_date' => $bookData['publish_date'] ?? '',
        'pages' => $bookData['number_of_pages'] ?? 0,
        'subjects' => isset($bookData['subjects']) ? 
                     array_slice(array_map(function($sub) { 
                         return $sub['name']; 
                     }, $bookData['subjects']), 0, 5) : [],
        'cover_url' => isset($bookData['cover']) ? $bookData['cover']['medium'] : ''
    ];
}

// Funzione per chiamare l'API di Google Books (fallback)
function searchGoogleBooks($isbn) {
    $cleanedISBN = cleanISBN($isbn);
    
    if (!isValidISBN($cleanedISBN)) {
        return null;
    }
    
    $url = "https://www.googleapis.com/books/v1/volumes?q=isbn:{$cleanedISBN}";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Reading Tracker App/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (empty($data['items'])) {
        return null;
    }
    
    $book = $data['items'][0]['volumeInfo'];
    
    return [
        'title' => $book['title'] ?? 'Titolo non disponibile',
        'author' => isset($book['authors']) ? 
                   implode(', ', $book['authors']) : 'Autore non disponibile',
        'isbn' => $cleanedISBN,
        'publisher' => $book['publisher'] ?? '',
        'publish_date' => $book['publishedDate'] ?? '',
        'pages' => $book['pageCount'] ?? 0,
        'subjects' => array_slice($book['categories'] ?? [], 0, 5),
        'cover_url' => $book['imageLinks']['thumbnail'] ?? ''
    ];
}

// Funzione per cercare per titolo/autore se ISBN non trova risultati
function searchByTitleAuthor($query) {
    $url = "https://openlibrary.org/search.json?q=" . urlencode($query) . "&limit=5";
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Reading Tracker App/1.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return [];
    }
    
    $data = json_decode($response, true);
    
    if (empty($data['docs'])) {
        return [];
    }
    
    $books = [];
    foreach (array_slice($data['docs'], 0, 5) as $book) {
        $books[] = [
            'title' => $book['title'] ?? 'Titolo non disponibile',
            'author' => isset($book['author_name']) ? 
                       implode(', ', array_slice($book['author_name'], 0, 2)) : 'Autore non disponibile',
            'isbn' => isset($book['isbn']) ? $book['isbn'][0] : '',
            'publisher' => isset($book['publisher']) ? $book['publisher'][0] : '',
            'publish_date' => $book['first_publish_year'] ?? '',
            'pages' => 0,
            'subjects' => array_slice($book['subject'] ?? [], 0, 3),
            'cover_url' => isset($book['cover_i']) ? 
                          "https://covers.openlibrary.org/b/id/{$book['cover_i']}-M.jpg" : ''
        ];
    }
    
    return $books;
}

// Funzione per determinare il genere basato sui subjects
function determineGenre($subjects) {
    if (empty($subjects)) {
        return 'Generale';
    }
    
    $genreMapping = [
        'fiction' => 'Narrativa',
        'romance' => 'Romance',
        'mystery' => 'Giallo',
        'thriller' => 'Thriller',
        'fantasy' => 'Fantasy',
        'science fiction' => 'Fantascienza',
        'biography' => 'Biografia',
        'history' => 'Storia',
        'philosophy' => 'Filosofia',
        'psychology' => 'Psicologia',
        'science' => 'Scienze',
        'technology' => 'Tecnologia',
        'business' => 'Business',
        'self-help' => 'Auto-aiuto',
        'cooking' => 'Cucina',
        'travel' => 'Viaggi',
        'art' => 'Arte',
        'music' => 'Musica',
        'sports' => 'Sport',
        'health' => 'Salute'
    ];
    
    $subjects = array_map('strtolower', $subjects);
    
    foreach ($genreMapping as $keyword => $genre) {
        foreach ($subjects as $subject) {
            if (strpos($subject, $keyword) !== false) {
                return $genre;
            }
        }
    }
    
    return 'Generale';
}

try {
    $books = [];
    
    // Prova prima con Open Library
    $book = searchOpenLibrary($isbn);
    if ($book) {
        $book['genre'] = determineGenre($book['subjects']);
        $books[] = $book;
    }
    
    // Se non ha trovato risultati con ISBN, prova con Google Books
    if (empty($books) && isValidISBN($isbn)) {
        $book = searchGoogleBooks($isbn);
        if ($book) {
            $book['genre'] = determineGenre($book['subjects']);
            $books[] = $book;
        }
    }
    
    // Se ancora nessun risultato, prova ricerca per titolo/autore
    if (empty($books)) {
        $searchBooks = searchByTitleAuthor($isbn);
        foreach ($searchBooks as $book) {
            $book['genre'] = determineGenre($book['subjects']);
            $books[] = $book;
        }
    }
    
    if (empty($books)) {
        jsonResponse(false, 'Nessun libro trovato');
    }
    
    jsonResponse(true, 'Libri trovati', $books);
    
} catch (Exception $e) {
    error_log("Errore ricerca ISBN: " . $e->getMessage());
    jsonResponse(false, 'Errore nella ricerca del libro');
}
?>