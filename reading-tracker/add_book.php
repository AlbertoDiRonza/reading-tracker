<?php
// add_book.php - Versione corretta con gestione ISBN NULL
// Pagina per aggiungere un nuovo libro

require_once 'config/session.php';
require_once 'includes/functions.php';

// Richiede login
requireLogin();

$pageTitle = 'Aggiungi Libro - Reading Tracker';
$userId = getCurrentUserId();

// Gestisce il form di aggiunta libro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['method'] ?? 'manual';
    
    if ($method === 'isbn') {
        // Ricerca tramite ISBN
        $isbn = sanitizeInput($_POST['isbn'] ?? '');
        
        if (empty($isbn)) {
            setFlashMessage('error', 'Inserisci un ISBN valido');
        } elseif (!validateISBN($isbn)) {
            setFlashMessage('error', 'ISBN non valido');
        } else {
            $bookInfo = getBookInfoFromISBN($isbn);
            
            if ($bookInfo) {
                // Dati trovati, pre-popola il form
                $bookData = [
                    'isbn' => $isbn,
                    'title' => $bookInfo['title'],
                    'author' => $bookInfo['authors'],
                    'publisher' => $bookInfo['publisher'],
                    'year' => $bookInfo['publish_year'],
                    'pages' => $bookInfo['pages']
                ];
                $showForm = true;
            } else {
                setFlashMessage('error', 'Libro non trovato. Prova con l\'inserimento manuale.');
            }
        }
    } elseif ($method === 'manual' || $method === 'confirm') {
        // Inserimento manuale o conferma dopo ricerca ISBN
        $bookData = [
            'isbn' => sanitizeInput($_POST['isbn'] ?? ''),
            'title' => sanitizeInput($_POST['title'] ?? ''),
            'author' => sanitizeInput($_POST['author'] ?? ''),
            'genre' => sanitizeInput($_POST['genre'] ?? ''),
            'publisher' => sanitizeInput($_POST['publisher'] ?? ''),
            'year' => sanitizeInput($_POST['year'] ?? ''),
            'pages' => sanitizeInput($_POST['pages'] ?? ''),
            'read_date' => sanitizeInput($_POST['read_date'] ?? ''),
            'rating' => sanitizeInput($_POST['rating'] ?? ''),
            'notes' => sanitizeInput($_POST['notes'] ?? '')
        ];
        
        // Validazione
        $errors = validateBookData($bookData);
        
        if (empty($errors)) {
            try {
                $pdo = getConnection();
                $pdo->beginTransaction();
                
                // Cerca libro esistente
                $bookId = findExistingBook($pdo, $bookData['isbn'], $bookData['title'], $bookData['author']);
                
                // Se il libro non esiste, aggiungilo
                if (!$bookId) {
                    $bookId = insertBook($pdo, $bookData);
                }
                
                // Verifica se l'utente ha già letto questo libro
                $stmt = $pdo->prepare("SELECT id FROM Statistiche WHERE utente_id = ? AND libro_id = ?");
                $stmt->execute([$userId, $bookId]);
                
                if ($stmt->fetchColumn()) {
                    $errors[] = 'Hai già aggiunto questo libro alla tua lista';
                } else {
                    // Aggiungi la statistica
                    $stmt = $pdo->prepare("
                        INSERT INTO Statistiche (utente_id, libro_id, data_lettura, valutazione, note) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId, 
                        $bookId, 
                        $bookData['read_date'], 
                        emptyToNull($bookData['rating']), 
                        emptyToNull($bookData['notes'])
                    ]);
                    
                    $pdo->commit();
                    
                    setFlashMessage('success', 'Libro aggiunto con successo!');
                    header("Location: books.php");
                    exit();
                }
                
                $pdo->rollback();
                
            } catch (Exception $e) {
                $pdo->rollback();
                $errors[] = 'Errore durante l\'aggiunta del libro: ' . $e->getMessage();
            }
        }
        
        if (!empty($errors)) {
            setFlashMessage('error', implode('<br>', $errors));
        }
        
        // Mantieni i dati per il form in caso di errore
        $isbn = $bookData['isbn'];
        $title = $bookData['title'];
        $author = $bookData['author'];
        $genre = $bookData['genre'];
        $publisher = $bookData['publisher'];
        $year = $bookData['year'];
        $pages = $bookData['pages'];
        $readDate = $bookData['read_date'];
        $rating = $bookData['rating'];
        $notes = $bookData['notes'];
    }
}

// Generi predefiniti
$genres = [
    'Romanzo', 'Fantasy', 'Fantascienza', 'Giallo', 'Thriller', 'Horror',
    'Biografia', 'Autobiografia', 'Saggistica', 'Storia', 'Filosofia',
    'Poesia', 'Teatro', 'Fumetto', 'Manga', 'Young Adult', 'Distopia',
    'Avventura', 'Storico', 'Contemporaneo', 'Classico', 'Altro'
];
?>

<?php include 'includes/header.php'; ?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-plus"></i> Aggiungi Libro</h1>
        <p>Aggiungi un nuovo libro alla tua libreria personale</p>
    </div>

    <div class="add-book-container">
        <!-- Scelta del metodo -->
        <div class="method-selector">
            <h3>Come vuoi aggiungere il libro?</h3>
            <div class="method-options">
                <button class="method-btn active" onclick="selectMethod('manual')">
                    <i class="fas fa-edit"></i>
                    <span>Inserimento Manuale</span>
                    <small>Inserisci manualmente i dati del libro</small>
                </button>
                <button class="method-btn" onclick="selectMethod('isbn')">
                    <i class="fas fa-barcode"></i>
                    <span>Ricerca ISBN</span>
                    <small>Cerca automaticamente tramite codice ISBN</small>
                </button>
            </div>
        </div>

        <!-- Form ricerca ISBN -->
        <div id="isbn-form" class="form-section" style="display: none;">
            <form method="POST" class="isbn-search-form">
                <input type="hidden" name="method" value="isbn">
                
                <div class="form-group isbn-search-container">
                    <label for="isbn">
                        <i class="fas fa-barcode"></i> Codice ISBN
                    </label>
                    <input type="text" id="isbn" name="isbn" 
                           placeholder="Inserisci il codice ISBN (10 o 13 cifre)" 
                           value="<?php echo htmlspecialchars($isbn ?? ''); ?>">
                    <small class="form-help">
                        Inserisci il codice ISBN senza trattini o spazi
                    </small>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Cerca Libro
                </button>
            </form>
        </div>

        <!-- Form inserimento manuale -->
        <div id="manual-form" class="form-section">
            <form method="POST" class="book-form">
                <input type="hidden" name="method" value="<?php echo isset($bookData) ? 'confirm' : 'manual'; ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="title">
                            <i class="fas fa-book"></i> Titolo *
                        </label>
                        <input type="text" id="title" name="title" required
                               placeholder="Titolo del libro" 
                               value="<?php echo htmlspecialchars($bookData['title'] ?? $title ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="author">
                            <i class="fas fa-user"></i> Autore *
                        </label>
                        <input type="text" id="author" name="author" required
                               placeholder="Nome dell'autore" 
                               value="<?php echo htmlspecialchars($bookData['author'] ?? $author ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="genre">
                            <i class="fas fa-tags"></i> Genere
                        </label>
                        <select id="genre" name="genre">
                            <option value="">Seleziona un genere</option>
                            <?php foreach ($genres as $g): ?>
                                <option value="<?php echo $g; ?>" 
                                        <?php echo ($genre ?? '') == $g ? 'selected' : ''; ?>>
                                    <?php echo $g; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="isbn_manual">
                            <i class="fas fa-barcode"></i> ISBN (opzionale)
                        </label>
                        <input type="text" id="isbn_manual" name="isbn"
                               placeholder="ISBN (opzionale)" 
                               value="<?php echo htmlspecialchars($bookData['isbn'] ?? $isbn ?? ''); ?>">
                        <small class="form-help">Lascia vuoto se non disponibile</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="publisher">
                            <i class="fas fa-building"></i> Editore
                        </label>
                        <input type="text" id="publisher" name="publisher"
                               placeholder="Casa editrice" 
                               value="<?php echo htmlspecialchars($bookData['publisher'] ?? $publisher ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="year">
                            <i class="fas fa-calendar"></i> Anno Pubblicazione
                        </label>
                        <input type="number" id="year" name="year" min="1800" max="<?php echo date('Y'); ?>"
                               placeholder="Anno" 
                               value="<?php echo htmlspecialchars($bookData['year'] ?? $year ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="pages">
                            <i class="fas fa-file-alt"></i> Pagine
                        </label>
                        <input type="number" id="pages" name="pages" min="1"
                               placeholder="Numero di pagine" 
                               value="<?php echo htmlspecialchars($bookData['pages'] ?? $pages ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="read_date">
                            <i class="fas fa-calendar-check"></i> Data Lettura *
                        </label>
                        <input type="date" id="read_date" name="read_date" required
                               value="<?php echo htmlspecialchars($readDate ?? date('Y-m-d')); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="rating">
                            <i class="fas fa-star"></i> Valutazione
                        </label>
                        <select id="rating" name="rating">
                            <option value="">Nessuna valutazione</option>
                            <option value="1" <?php echo ($rating ?? '') == '1' ? 'selected' : ''; ?>>⭐ 1 stella</option>
                            <option value="2" <?php echo ($rating ?? '') == '2' ? 'selected' : ''; ?>>⭐⭐ 2 stelle</option>
                            <option value="3" <?php echo ($rating ?? '') == '3' ? 'selected' : ''; ?>>⭐⭐⭐ 3 stelle</option>
                            <option value="4" <?php echo ($rating ?? '') == '4' ? 'selected' : ''; ?>>⭐⭐⭐⭐ 4 stelle</option>
                            <option value="5" <?php echo ($rating ?? '') == '5' ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐ 5 stelle</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">
                        <i class="fas fa-sticky-note"></i> Note
                    </label>
                    <textarea id="notes" name="notes" rows="4"
                              placeholder="Note personali, commenti, citazioni..."><?php echo htmlspecialchars($notes ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Aggiungi Libro
                    </button>
                    <a href="books.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annulla
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function selectMethod(method) {
    // Aggiorna i pulsanti
    document.querySelectorAll('.method-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.closest('.method-btn').classList.add('active');
    
    // Mostra/nascondi i form
    if (method === 'manual') {
        document.getElementById('manual-form').style.display = 'block';
        document.getElementById('isbn-form').style.display = 'none';
    } else {
        document.getElementById('manual-form').style.display = 'none';
        document.getElementById('isbn-form').style.display = 'block';
    }
}

// Imposta la data di oggi come default
document.addEventListener('DOMContentLoaded', function() {
    const readDateInput = document.getElementById('read_date');
    if (!readDateInput.value) {
        readDateInput.value = new Date().toISOString().split('T')[0];
    }
});
</script>

<?php include 'includes/footer.php'; ?>