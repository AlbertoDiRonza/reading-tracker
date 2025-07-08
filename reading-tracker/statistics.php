<?php

// Includi i file necessari
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$session_username = $_SESSION['username'];

// Imposta il titolo della pagina
$pageTitle = 'Statistiche - Reading Tracker';

// Inizializza le variabili
$totalBooks = 0;
$booksThisYear = 0;
$favoriteGenreName = 'Nessuno';
$goalBooks = 0;
$goalProgress = 0;
$avgRating = 0;
$errorMessage = '';
$monthlyData = array_fill(0, 12, 0);
$genresData = [];
$yearlyData = [];
$ratingsData = array_fill(0, 5, 0);

// Ottieni statistiche di base
try {
    $pdo = getConnection();
    
    // Test connessione database
    $test = $pdo->query("SELECT 1");
    if (!$test) {
        throw new Exception("Connessione database fallita");
    }
    
    // Statistiche generali
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Statistiche WHERE utente_id = ?");
    $stmt->execute([$user_id]);
    $totalBooks = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Statistiche WHERE utente_id = ? AND YEAR(data_lettura) = YEAR(CURDATE())");
    $stmt->execute([$user_id]);
    $booksThisYear = $stmt->fetchColumn();
    
    // Genere preferito
    $stmt = $pdo->prepare("
        SELECT l.genere, COUNT(*) as count 
        FROM Statistiche s 
        JOIN Libro l ON s.libro_id = l.id 
        WHERE s.utente_id = ? 
        GROUP BY l.genere 
        ORDER BY count DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $favoriteGenre = $stmt->fetch(PDO::FETCH_ASSOC);
    $favoriteGenreName = $favoriteGenre ? $favoriteGenre['genere'] : 'Nessuno';
    
    // Obiettivo corrente
    $currentYear = date('Y');
    $stmt = $pdo->prepare("SELECT obiettivo_libri, libri_letti FROM Progresso WHERE utente_id = ? AND anno = ?");
    $stmt->execute([$user_id, $currentYear]);
    $goalData = $stmt->fetch(PDO::FETCH_ASSOC);
    $goalBooks = $goalData ? $goalData['obiettivo_libri'] : 0;
    $goalProgress = $goalData ? $goalData['libri_letti'] : 0;
    
    // Valutazione media
    $stmt = $pdo->prepare("SELECT AVG(valutazione) FROM Statistiche WHERE utente_id = ? AND valutazione IS NOT NULL");
    $stmt->execute([$user_id]);
    $avgRating = round($stmt->fetchColumn(), 1);
    
    // Dati per i grafici
    // Letture mensili
    $stmt = $pdo->prepare("
        SELECT MONTH(data_lettura) as month, COUNT(*) as count 
        FROM Statistiche 
        WHERE utente_id = ? AND YEAR(data_lettura) = YEAR(CURDATE())
        GROUP BY MONTH(data_lettura)
        ORDER BY month
    ");
    $stmt->execute([$user_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthlyData[$row['month'] - 1] = $row['count'];
    }
    
    // Generi
    $stmt = $pdo->prepare("
        SELECT l.genere, COUNT(*) as count 
        FROM Statistiche s 
        JOIN Libro l ON s.libro_id = l.id 
        WHERE s.utente_id = ? 
        GROUP BY l.genere 
        ORDER BY count DESC
    ");
    $stmt->execute([$user_id]);
    $genresData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Letture annuali
    $stmt = $pdo->prepare("
        SELECT YEAR(data_lettura) as year, COUNT(*) as count 
        FROM Statistiche 
        WHERE utente_id = ? 
        GROUP BY YEAR(data_lettura)
        ORDER BY year
    ");
    $stmt->execute([$user_id]);
    $yearlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Valutazioni
    $stmt = $pdo->prepare("
        SELECT valutazione, COUNT(*) as count 
        FROM Statistiche 
        WHERE utente_id = ? AND valutazione IS NOT NULL
        GROUP BY valutazione
        ORDER BY valutazione
    ");
    $stmt->execute([$user_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ratingsData[$row['valutazione'] - 1] = $row['count'];
    }
    
} catch (PDOException $e) {
    error_log("Errore database: " . $e->getMessage());
    $errorMessage = "Errore di connessione al database: " . $e->getMessage();
} catch (Exception $e) {
    error_log("Errore generico: " . $e->getMessage());
    $errorMessage = "Errore: " . $e->getMessage();
}

// Includi l'header
include 'includes/header.php';
?>

<style>
    .statistics-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .page-header {
        text-align: center;
        margin-bottom: 40px;
        padding: 20px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
    }
    
    .page-header h1 {
        margin: 0;
        font-size: 2.5em;
        font-weight: 300;
    }
    
    .page-header p {
        margin: 10px 0 0 0;
        opacity: 0.9;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 40px;
    }
    
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0,0,0,0.15);
    }
    
    .stat-card h3 {
        margin: 0 0 15px 0;
        color: #333;
        font-size: 1.1em;
        font-weight: 500;
    }
    
    .stat-value {
        font-size: 2.5em;
        font-weight: bold;
        color: #667eea;
        margin: 15px 0;
    }
    
    .stat-label {
        color: #666;
        font-size: 0.9em;
    }
    
    .progress-bar {
        background: #e9ecef;
        border-radius: 10px;
        height: 8px;
        margin: 10px 0;
        overflow: hidden;
    }
    
    .progress-fill {
        background: linear-gradient(90deg, #667eea, #764ba2);
        height: 100%;
        border-radius: 10px;
        transition: width 0.3s ease;
    }
    
    .charts-section {
        margin-top: 40px;
    }
    
    .chart-container {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        margin-bottom: 30px;
    }
    
    .chart-title {
        font-size: 1.4em;
        margin-bottom: 20px;
        color: #333;
        text-align: center;
        font-weight: 500;
    }
    
    .chart-wrapper {
        position: relative;
        height: 400px;
        margin: 20px 0;
    }
    
    .loading {
        text-align: center;
        padding: 40px;
        color: #666;
    }
    
    .error {
        text-align: center;
        padding: 20px;
        color: #dc3545;
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        border-radius: 8px;
        margin: 20px 0;
    }
    
    .tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 2px solid #e9ecef;
        overflow-x: auto;
    }
    
    .tab {
        padding: 12px 24px;
        cursor: pointer;
        background: none;
        border: none;
        font-size: 1em;
        color: #666;
        border-bottom: 2px solid transparent;
        white-space: nowrap;
        transition: all 0.3s ease;
    }
    
    .tab.active {
        color: #667eea;
        border-bottom-color: #667eea;
    }
    
    .tab:hover {
        color: #667eea;
        background: rgba(102, 126, 234, 0.1);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .insights {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
    }
    
    .insights h4 {
        margin-top: 0;
        color: #333;
        font-size: 1.2em;
    }
    
    .insights ul {
        margin: 15px 0;
        padding-left: 20px;
    }
    
    .insights li {
        margin: 8px 0;
        color: #555;
        line-height: 1.4;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #666;
    }
    
    .empty-state h3 {
        color: #999;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        margin-bottom: 20px;
    }
    
    .empty-state a {
        display: inline-block;
        padding: 12px 24px;
        background: #667eea;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        transition: background 0.3s ease;
    }
    
    .empty-state a:hover {
        background: #5a67d8;
    }
    
    .rating-display {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 5px;
        margin-top: 10px;
    }
    
    .star {
        color: #ffc107;
        font-size: 1.2em;
    }
    
    .star.empty {
        color: #dee2e6;
    }
    
    .debug-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin: 20px 0;
        font-family: monospace;
        font-size: 0.9em;
    }
    
    @media (max-width: 768px) {
        .statistics-container {
            padding: 15px;
        }
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .chart-wrapper {
            height: 300px;
        }
        
        .page-header h1 {
            font-size: 2em;
        }
    }
</style>

<div class="statistics-container">
    <div class="page-header">
        <h1>📊 Statistiche di Lettura</h1>
        <p>Analizza le tue abitudini di lettura e scopri i tuoi pattern</p>
    </div>
    
    <?php if (!empty($errorMessage)): ?>
        <div class="error">
            <strong>Errore:</strong> <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>
    
    
    <?php if ($totalBooks > 0): ?>
        <!-- Statistiche Rapide -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📚 Libri Totali</h3>
                <div class="stat-value"><?php echo $totalBooks; ?></div>
                <div class="stat-label">Libri letti in totale</div>
            </div>
            
            <div class="stat-card">
                <h3>📅 Quest'Anno</h3>
                <div class="stat-value"><?php echo $booksThisYear; ?></div>
                <div class="stat-label">Libri letti nel <?php echo date('Y'); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>🎯 Obiettivo</h3>
                <div class="stat-value">
                    <?php echo $goalBooks > 0 ? $goalProgress . '/' . $goalBooks : 'Non impostato'; ?>
                </div>
                <div class="stat-label">Progresso obiettivo</div>
                <?php if ($goalBooks > 0): ?>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo min(100, ($goalProgress / $goalBooks) * 100); ?>%"></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="stat-card">
                <h3>❤️ Genere Preferito</h3>
                <div class="stat-value" style="font-size: 1.5em;"><?php echo htmlspecialchars($favoriteGenreName); ?></div>
                <div class="stat-label">Il tuo genere più letto</div>
            </div>
            
            <div class="stat-card">
                <h3>⭐ Valutazione Media</h3>
                <div class="stat-value"><?php echo $avgRating > 0 ? $avgRating : '-'; ?></div>
                <div class="stat-label">Media delle tue valutazioni</div>
                <?php if ($avgRating > 0): ?>
                    <div class="rating-display">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star <?php echo $i <= $avgRating ? '' : 'empty'; ?>">★</span>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Insights -->
        <div class="insights">
            <h4>🔍 I tuoi Insights</h4>
            <ul id="insightsList">
                <li>📈 Hai letto <?php echo $totalBooks; ?> libri in totale</li>
                <?php if ($booksThisYear > 0): ?>
                    <li>🎯 Quest'anno hai letto <?php echo $booksThisYear; ?> libri</li>
                <?php endif; ?>
                <?php if ($goalBooks > 0): ?>
                    <li>🏆 Sei al <?php echo round(($goalProgress / $goalBooks) * 100); ?>% del tuo obiettivo annuale</li>
                <?php endif; ?>
                <?php if ($favoriteGenreName !== 'Nessuno'): ?>
                    <li>❤️ Il tuo genere preferito è <?php echo htmlspecialchars($favoriteGenreName); ?></li>
                <?php endif; ?>
                <?php if ($avgRating > 0): ?>
                    <li>⭐ Valuti i tuoi libri con una media di <?php echo $avgRating; ?> stelle</li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Tabs per diversi tipi di grafici -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('overview')">📊 Panoramica</button>
            <button class="tab" onclick="showTab('genres')">📚 Generi</button>
            <button class="tab" onclick="showTab('timeline')">📈 Timeline</button>
            <button class="tab" onclick="showTab('ratings')">⭐ Valutazioni</button>
        </div>
        
        <!-- Tab Overview -->
        <div id="overview" class="tab-content active">
            <div class="chart-container">
                <h2 class="chart-title">📊 Letture Mensili <?php echo date('Y'); ?></h2>
                <div class="chart-wrapper">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tab Generi -->
        <div id="genres" class="tab-content">
            <div class="chart-container">
                <h2 class="chart-title">📚 Distribuzione Generi</h2>
                <div class="chart-wrapper">
                    <canvas id="genresChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tab Timeline -->
        <div id="timeline" class="tab-content">
            <div class="chart-container">
                <h2 class="chart-title">📈 Letture per Anno</h2>
                <div class="chart-wrapper">
                    <canvas id="yearlyChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Tab Valutazioni -->
        <div id="ratings" class="tab-content">
            <div class="chart-container">
                <h2 class="chart-title">⭐ Distribuzione Valutazioni</h2>
                <div class="chart-wrapper">
                    <canvas id="ratingsChart"></canvas>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Stato vuoto -->
        <div class="empty-state">
            <h3>📚 Nessun libro registrato</h3>
            <p>Inizia a registrare i tuoi libri per vedere le statistiche!</p>
            <a href="books.php">Aggiungi il primo libro</a>
        </div>
    <?php endif; ?>
</div>

<script>
    // Variabili globali per i grafici
    let charts = {};
    
    // Dati PHP per JavaScript
    const chartData = {
        monthly: <?php echo json_encode($monthlyData); ?>,
        genres: <?php echo json_encode($genresData); ?>,
        yearly: <?php echo json_encode($yearlyData); ?>,
        ratings: <?php echo json_encode($ratingsData); ?>
    };
    
    // Funzione per mostrare/nascondere tab
    function showTab(tabName) {
        // Nascondi tutte le tab
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Rimuovi classe active da tutti i button
        document.querySelectorAll('.tab').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Mostra tab selezionata
        document.getElementById(tabName).classList.add('active');
        event.target.classList.add('active');
    }
    
    // Carica dati e crea grafici al caricamento della pagina
    document.addEventListener('DOMContentLoaded', function() {
        if (<?php echo $totalBooks; ?> > 0) {
            createAllCharts();
        }
    });
    
    // Crea tutti i grafici
    function createAllCharts() {
        createMonthlyChart();
        createGenresChart();
        createYearlyChart();
        createRatingsChart();
    }
    
    // Grafico letture mensili
    function createMonthlyChart() {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        
        if (charts.monthly) {
            charts.monthly.destroy();
        }
        
        charts.monthly = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'],
                datasets: [{
                    label: 'Libri Letti',
                    data: chartData.monthly,
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: 'rgba(102, 126, 234, 1)',
                    borderWidth: 1,
                    borderRadius: 8,
                    borderSkipped: false,
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
    }
    
    // Grafico generi
    function createGenresChart() {
        const ctx = document.getElementById('genresChart').getContext('2d');
        
        if (charts.genres) {
            charts.genres.destroy();
        }
        
        const colors = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c',
            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
            '#ffecd2', '#fcb69f', '#a8edea', '#fed6e3'
        ];
        
        charts.genres = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartData.genres.map(item => item.genere),
                datasets: [{
                    data: chartData.genres.map(item => item.count),
                    backgroundColor: colors.slice(0, chartData.genres.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }
    
    // Grafico annuale
    function createYearlyChart() {
        const ctx = document.getElementById('yearlyChart').getContext('2d');
        
        if (charts.yearly) {
            charts.yearly.destroy();
        }
        
        charts.yearly = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.yearly.map(item => item.year),
                datasets: [{
                    label: 'Libri Letti',
                    data: chartData.yearly.map(item => item.count),
                    borderColor: 'rgba(102, 126, 234, 1)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
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
    }
    
    // Grafico valutazioni
    function createRatingsChart() {
        const ctx = document.getElementById('ratingsChart').getContext('2d');
        
        if (charts.ratings) {
            charts.ratings.destroy();
        }
        
        charts.ratings = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['1 ⭐', '2 ⭐', '3 ⭐', '4 ⭐', '5 ⭐'],
                datasets: [{
                    label: 'Numero di Libri',
                    data: chartData.ratings,
                    backgroundColor: [
                        '#ff6b6b', '#ffa500', '#ffeb3b', '#4caf50', '#2196f3'
                    ],
                    borderWidth: 1,
                    borderRadius: 8,
                    borderSkipped: false,
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
    }
</script>

<?php include 'includes/footer.php'; ?>