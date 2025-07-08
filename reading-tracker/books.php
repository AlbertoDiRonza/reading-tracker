<?php
// books.php
// Pagina per la gestione dei libri dell'utente

require_once 'config/session.php';
require_once 'includes/functions.php';

// Richiede login
requireLogin();

$pageTitle = 'I Miei Libri - Reading Tracker';
$userId = getCurrentUserId();

// Gestisce l'eliminazione di un libro
if (isset($_POST['delete_book'])) {
    $bookId = (int)$_POST['book_id'];
    
    try {
        $pdo = getConnection();
        
        // Inizia una transazione per garantire la consistenza dei dati
        $pdo->beginTransaction();
        
        // Prima elimina dalla tabella Statistiche (se esiste)
        $stmt = $pdo->prepare("DELETE FROM Statistiche WHERE utente_id = ? AND libro_id = ?");
        $stmt->execute([$userId, $bookId]);
        
        // Verifica se altri utenti hanno questo libro
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Statistiche WHERE libro_id = ?");
        $stmt->execute([$bookId]);
        $otherUsers = $stmt->fetchColumn();
        
        // Se nessun altro utente ha questo libro, eliminalo dalla tabella libro
        if ($otherUsers == 0) {
            $stmt = $pdo->prepare("DELETE FROM libro WHERE id = ?");
            $stmt->execute([$bookId]);
        }
        
        // Conferma la transazione
        $pdo->commit();
        
        setFlashMessage('success', 'Libro eliminato completamente dal database!');
        header("Location: books.php");
        exit();
    } catch (Exception $e) {
        // In caso di errore, annulla la transazione
        $pdo->rollback();
        setFlashMessage('error', 'Errore durante l\'eliminazione del libro: ' . $e->getMessage());
    }
}

// Ottiene tutti i libri dell'utente
$userBooks = getUserBooks($userId);

// Filtri
$filterGenre = $_GET['genre'] ?? '';
$filterYear = $_GET['year'] ?? '';
$filterRating = $_GET['rating'] ?? '';

// Applica filtri
if ($filterGenre || $filterYear || $filterRating) {
    $userBooks = array_filter($userBooks, function($book) use ($filterGenre, $filterYear, $filterRating) {
        $genreMatch = !$filterGenre || $book['genere'] == $filterGenre;
        $yearMatch = !$filterYear || date('Y', strtotime($book['data_lettura'])) == $filterYear;
        $ratingMatch = !$filterRating || $book['valutazione'] == $filterRating;
        
        return $genreMatch && $yearMatch && $ratingMatch;
    });
}

// Ottiene i generi per il filtro
$genres = array_unique(array_column($userBooks, 'genere'));
$years = array_unique(array_map(function($book) { return date('Y', strtotime($book['data_lettura'])); }, $userBooks));
rsort($years);
?>

<?php include 'includes/header.php'; ?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-book-open"></i> I Miei Libri</h1>
        <div class="page-actions">
            <a href="add_book.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Aggiungi Libro
            </a>
        </div>
    </div>

    <!-- Filtri -->
    <div class="filters-card">
        <h3><i class="fas fa-filter"></i> Filtri</h3>
        <form method="GET" class="filters-form">
            <div class="filter-group">
                <label for="genre">Genere:</label>
                <select name="genre" id="genre">
                    <option value="">Tutti i generi</option>
                    <?php foreach ($genres as $genre): ?>
                        <?php if ($genre): ?>
                            <option value="<?php echo htmlspecialchars($genre); ?>" 
                                    <?php echo $filterGenre == $genre ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($genre); ?>
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="year">Anno:</label>
                <select name="year" id="year">
                    <option value="">Tutti gli anni</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>" 
                                <?php echo $filterYear == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="rating">Valutazione:</label>
                <select name="rating" id="rating">
                    <option value="">Tutte le valutazioni</option>
                    <option value="5" <?php echo $filterRating == '5' ? 'selected' : ''; ?>>⭐⭐⭐⭐⭐</option>
                    <option value="4" <?php echo $filterRating == '4' ? 'selected' : ''; ?>>⭐⭐⭐⭐</option>
                    <option value="3" <?php echo $filterRating == '3' ? 'selected' : ''; ?>>⭐⭐⭐</option>
                    <option value="2" <?php echo $filterRating == '2' ? 'selected' : ''; ?>>⭐⭐</option>
                    <option value="1" <?php echo $filterRating == '1' ? 'selected' : ''; ?>>⭐</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Filtra
            </button>
            
            <a href="books.php" class="btn btn-outline">
                <i class="fas fa-times"></i> Rimuovi Filtri
            </a>
        </form>
    </div>

    <!-- Statistiche rapide -->
    <div class="stats-summary">
        <div class="stat-item">
            <i class="fas fa-book"></i>
            <span class="stat-number"><?php echo count($userBooks); ?></span>
            <span class="stat-label">Libri totali</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-star"></i>
            <span class="stat-number"><?php 
                $ratings = array_filter(array_column($userBooks, 'valutazione'));
                echo $ratings ? round(array_sum($ratings) / count($ratings), 1) : 'N/A';
            ?></span>
            <span class="stat-label">Valutazione media</span>
        </div>
        <div class="stat-item">
            <i class="fas fa-calendar"></i>
            <span class="stat-number"><?php echo date('Y'); ?></span>
            <span class="stat-label">Anno corrente</span>
        </div>
    </div>

    <!-- Lista libri -->
    <div class="books-grid">
        <?php if (empty($userBooks)): ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <h3>Nessun libro trovato</h3>
                <p>Non hai ancora aggiunto libri alla tua libreria o i filtri non hanno prodotto risultati.</p>
                <a href="add_book.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Aggiungi il primo libro
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($userBooks as $book): ?>
                <div class="book-card">
                    <div class="book-header">
                        <h3><?php echo htmlspecialchars($book['titolo']); ?></h3>
                        <div class="book-actions">
                            <button class="btn-icon" onclick="toggleBookDetails(<?php echo $book['id']; ?>)">
                                <i class="fas fa-info-circle"></i>
                            </button>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Sei sicuro di voler rimuovere questo libro?')">
                                <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                <button type="submit" name="delete_book" class="btn-icon btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="book-info">
                        <p><strong>Autore:</strong> <?php echo htmlspecialchars($book['autore']); ?></p>
                        <p><strong>Data lettura:</strong> <?php echo formatDate($book['data_lettura']); ?></p>
                        
                        <?php if ($book['genere']): ?>
                            <p><strong>Genere:</strong> 
                                <span class="genre-tag"><?php echo htmlspecialchars($book['genere']); ?></span>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($book['valutazione']): ?>
                            <div class="book-rating">
                                <strong>Valutazione:</strong>
                                <div class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $book['valutazione'] ? 'active' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="book-details" id="details-<?php echo $book['id']; ?>" style="display: none;">
                        <?php if ($book['isbn']): ?>
                            <p><strong>ISBN:</strong> <?php echo htmlspecialchars($book['isbn']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($book['editore']): ?>
                            <p><strong>Editore:</strong> <?php echo htmlspecialchars($book['editore']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($book['anno_pubblicazione']): ?>
                            <p><strong>Anno pubblicazione:</strong> <?php echo $book['anno_pubblicazione']; ?></p>
                        <?php endif; ?>
                        
                        <?php if ($book['pagine']): ?>
                            <p><strong>Pagine:</strong> <?php echo $book['pagine']; ?></p>
                        <?php endif; ?>
                        
                        <?php if ($book['note']): ?>
                            <div class="book-notes">
                                <strong>Note:</strong>
                                <p><?php echo nl2br(htmlspecialchars($book['note'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleBookDetails(bookId) {
    const details = document.getElementById('details-' + bookId);
    if (details.style.display === 'none') {
        details.style.display = 'block';
    } else {
        details.style.display = 'none';
    }
}

// Auto-submit form on filter change
document.addEventListener('DOMContentLoaded', function() {
    const filterInputs = document.querySelectorAll('.filters-form select');
    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
            this.form.submit();
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>