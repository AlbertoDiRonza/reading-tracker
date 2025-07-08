<?php
// dashboard.php
// Dashboard principale dell'utente

require_once 'config/session.php';
require_once 'includes/functions.php';

// Richiede login
requireLogin();

$pageTitle = 'Dashboard - Reading Tracker';
$userId = getCurrentUserId();

// Ottiene statistiche utente
$userStats = getUserStatistics($userId);
$progress = getUserProgress($userId);
$recentBooks = getRecentBooks($userId, 5);
$topGenres = getTopGenres($userId, 3);

// Dati per i grafici
$monthlyData = [];
$currentYear = date('Y');
for ($i = 1; $i <= 12; $i++) {
    $month = str_pad($i, 2, '0', STR_PAD_LEFT);
    $pdo = getConnection();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM Statistiche 
        WHERE utente_id = ? 
        AND DATE_FORMAT(data_lettura, '%Y-%m') = ?
    ");
    $stmt->execute([$userId, "$currentYear-$month"]);
    $monthlyData[] = $stmt->fetchColumn();
}
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
        <p>Benvenuto, <?php echo htmlspecialchars(getCurrentUsername()); ?>!</p>
    </div>
    
    <!-- Statistiche rapide -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $userStats['total_books']; ?></h3>
                <p>Libri letti</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $userStats['avg_rating']; ?></h3>
                <p>Valutazione media</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-target"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $progress['percentuale']; ?>%</h3>
                <p>Obiettivo <?php echo date('Y'); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $progress['letti']; ?>/<?php echo $progress['obiettivo']; ?></h3>
                <p>Progresso annuale</p>
            </div>
        </div>
    </div>
    
    <!-- Grafici e contenuti principali -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3><i class="fas fa-chart-line"></i> Letture per Mese</h3>
            <div class="chart-container">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        
        <div class="dashboard-card">
            <h3><i class="fas fa-bullseye"></i> Progresso Obiettivo</h3>
            <div class="progress-container">
                <div class="progress-circle">
                    <div class="progress-value"><?php echo $progress['percentuale']; ?>%</div>
                </div>
                <div class="progress-info">
                    <p><strong><?php echo $progress['letti']; ?></strong> libri letti</p>
                    <p><strong><?php echo $progress['obiettivo']; ?></strong> obiettivo</p>
                    <p><strong><?php echo max(0, $progress['obiettivo'] - $progress['letti']); ?></strong> rimanenti</p>
                </div>
            </div>
        </div>
        
        <div class="dashboard-card">
            <h3><i class="fas fa-heart"></i> Generi Preferiti</h3>
            <div class="genres-list">
                <?php if (empty($topGenres)): ?>
                    <p class="no-data">Nessun dato disponibile</p>
                <?php else: ?>
                    <?php foreach ($topGenres as $genre): ?>
                        <div class="genre-item">
                            <span class="genre-name"><?php echo htmlspecialchars($genre['genere']); ?></span>
                            <span class="genre-count"><?php echo $genre['count']; ?> libri</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-card">
            <h3><i class="fas fa-clock"></i> Letture Recenti</h3>
            <div class="recent-books">
                <?php if (empty($recentBooks)): ?>
                    <p class="no-data">Nessun libro letto di recente</p>
                    <a href="books.php" class="btn btn-primary">Aggiungi il primo libro</a>
                <?php else: ?>
                    <?php foreach ($recentBooks as $book): ?>
                        <div class="book-item">
                            <div class="book-info">
                                <h4><?php echo htmlspecialchars($book['titolo']); ?></h4>
                                <p><?php echo htmlspecialchars($book['autore']); ?></p>
                                <small><?php echo formatDate($book['data_lettura']); ?></small>
                            </div>
                            <div class="book-rating">
                                <?php if ($book['valutazione']): ?>
                                    <div class="stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $book['valutazione'] ? 'active' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="books.php" class="btn btn-secondary">Vedi tutti i libri</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Azioni rapide -->
    <div class="quick-actions">
        <h3><i class="fas fa-bolt"></i> Azioni Rapide</h3>
        <div class="actions-grid">
            <a href="add_book.php" class="action-btn">
                <i class="fas fa-plus"></i>
                <span>Aggiungi Libro</span>
            </a>
            <a href="goals.php" class="action-btn">
                <i class="fas fa-target"></i>
                <span>Imposta Obiettivo</span>
            </a>
            <a href="statistics.php" class="action-btn">
                <i class="fas fa-chart-bar"></i>
                <span>Vedi Statistiche</span>
            </a>
        </div>
    </div>
</div>

<script>
// Grafico letture mensili
const ctx = document.getElementById('monthlyChart').getContext('2d');
const monthlyChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'],
        datasets: [{
            label: 'Libri letti',
            data: <?php echo json_encode($monthlyData); ?>,
            borderColor: '#4A90E2',
            backgroundColor: 'rgba(74, 144, 226, 0.1)',
            borderWidth: 2,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Animazione cerchio progresso
document.addEventListener('DOMContentLoaded', function() {
    const progressCircle = document.querySelector('.progress-circle');
    const percentage = <?php echo $progress['percentuale']; ?>;
    
    progressCircle.style.background = `conic-gradient(#4A90E2 ${percentage * 3.6}deg, #e0e0e0 0deg)`;
});
</script>

<?php include 'includes/footer.php'; ?>