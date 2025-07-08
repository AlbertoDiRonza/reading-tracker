<?php
// goals.php
// Pagina per la gestione degli obiettivi di lettura annuali

require_once 'config/session.php';
require_once 'includes/functions.php';

// Richiede login
requireLogin();

$pageTitle = 'Obiettivi di Lettura - Reading Tracker';
$userId = getCurrentUserId();
$currentYear = date('Y');

// Gestisce l'impostazione/aggiornamento dell'obiettivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_goal'])) {
    $year = (int)$_POST['year'];
    $goal = (int)$_POST['goal'];
    
    $errors = [];
    
    // Validazione
    if ($year < 2020 || $year > ($currentYear + 5)) {
        $errors[] = 'Anno non valido';
    }
    
    if ($goal < 1 || $goal > 1000) {
        $errors[] = 'L\'obiettivo deve essere tra 1 e 1000 libri';
    }
    
    if (empty($errors)) {
        try {
            $pdo = getConnection();
            
            // Verifica se esiste già un obiettivo per l'anno
            $stmt = $pdo->prepare("SELECT id FROM Progresso WHERE utente_id = ? AND anno = ?");
            $stmt->execute([$userId, $year]);
            
            if ($stmt->fetchColumn()) {
                // Aggiorna obiettivo esistente
                $stmt = $pdo->prepare("UPDATE Progresso SET obiettivo_libri = ? WHERE utente_id = ? AND anno = ?");
                $stmt->execute([$goal, $userId, $year]);
                $message = "Obiettivo aggiornato con successo!";
            } else {
                // Conta i libri già letti nell'anno
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) FROM Statistiche 
                    WHERE utente_id = ? AND YEAR(data_lettura) = ?
                ");
                $stmt->execute([$userId, $year]);
                $booksRead = $stmt->fetchColumn();
                
                // Crea nuovo obiettivo
                $stmt = $pdo->prepare("
                    INSERT INTO Progresso (utente_id, anno, obiettivo_libri, libri_letti) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $year, $goal, $booksRead]);
                $message = "Obiettivo impostato con successo!";
            }
            
            setFlashMessage('success', $message);
            
        } catch (Exception $e) {
            $errors[] = 'Errore durante l\'impostazione dell\'obiettivo: ' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        setFlashMessage('error', implode('<br>', $errors));
    }
}

// Ottiene gli obiettivi dell'utente
$pdo = getConnection();
$stmt = $pdo->prepare("
    SELECT 
        anno,
        obiettivo_libri,
        libri_letti,
        ROUND((libri_letti / obiettivo_libri) * 100, 1) as percentuale
    FROM Progresso 
    WHERE utente_id = ? 
    ORDER BY anno DESC
");
$stmt->execute([$userId]);
$goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ottiene statistiche per l'anno corrente
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as libri_letti,
        AVG(s.valutazione) as valutazione_media,
        COUNT(DISTINCT l.genere) as generi_diversi,
        SUM(l.pagine) as pagine_totali
    FROM Statistiche s
    JOIN Libro l ON s.libro_id = l.id
    WHERE s.utente_id = ? AND YEAR(s.data_lettura) = ?
");
$stmt->execute([$userId, $currentYear]);
$currentStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Ottiene l'obiettivo per l'anno corrente
$stmt = $pdo->prepare("
    SELECT obiettivo_libri, libri_letti 
    FROM Progresso 
    WHERE utente_id = ? AND anno = ?
");
$stmt->execute([$userId, $currentYear]);
$currentGoal = $stmt->fetch(PDO::FETCH_ASSOC);

// Calcola statistiche mensili per l'anno corrente
$stmt = $pdo->prepare("
    SELECT 
        MONTH(data_lettura) as mese,
        COUNT(*) as libri_letti
    FROM Statistiche 
    WHERE utente_id = ? AND YEAR(data_lettura) = ?
    GROUP BY MONTH(data_lettura)
    ORDER BY mese
");
$stmt->execute([$userId, $currentYear]);
$monthlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Crea array per tutti i mesi
$monthlyData = array_fill(1, 12, 0);
foreach ($monthlyStats as $stat) {
    $monthlyData[$stat['mese']] = $stat['libri_letti'];
}

$months = [
    1 => 'Gen', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
    5 => 'Mag', 6 => 'Giu', 7 => 'Lug', 8 => 'Ago',
    9 => 'Set', 10 => 'Ott', 11 => 'Nov', 12 => 'Dic'
];
?>

<?php include 'includes/header.php'; ?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-target"></i> Obiettivi di Lettura</h1>
        <p>Imposta e monitora i tuoi obiettivi di lettura annuali</p>
    </div>

    <!-- Obiettivo Anno Corrente -->
    <div class="current-goal-card">
        <h2>Obiettivo <?php echo $currentYear; ?></h2>
        
        <?php if ($currentGoal): ?>
            <div class="goal-progress">
                <div class="progress-info">
                    <span class="progress-text">
                        <?php echo $currentGoal['libri_letti']; ?> / <?php echo $currentGoal['obiettivo_libri']; ?> libri
                    </span>
                    <span class="progress-percentage">
                        <?php echo $currentGoal['obiettivo_libri'] > 0 ? round(($currentGoal['libri_letti'] / $currentGoal['obiettivo_libri']) * 100, 1) : 0; ?>%
                    </span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $currentGoal['obiettivo_libri'] > 0 ? min(100, ($currentGoal['libri_letti'] / $currentGoal['obiettivo_libri']) * 100) : 0; ?>%"></div>
                </div>
                
                <?php 
                $remaining = $currentGoal['obiettivo_libri'] - $currentGoal['libri_letti'];
                $daysLeft = (strtotime($currentYear . '-12-31') - time()) / (60 * 60 * 24);
                $booksPerMonth = $remaining > 0 && $daysLeft > 0 ? round($remaining / ($daysLeft / 30), 1) : 0;
                ?>
                
                <div class="goal-stats">
                    <div class="stat-item">
                        <i class="fas fa-book"></i>
                        <span class="stat-number"><?php echo max(0, $remaining); ?></span>
                        <span class="stat-label">Libri rimanenti</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-calendar"></i>
                        <span class="stat-number"><?php echo max(0, round($daysLeft)); ?></span>
                        <span class="stat-label">Giorni rimanenti</span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-chart-line"></i>
                        <span class="stat-number"><?php echo $booksPerMonth; ?></span>
                        <span class="stat-label">Libri/mese necessari</span>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="no-goal">
                <i class="fas fa-target"></i>
                <p>Non hai ancora impostato un obiettivo per il <?php echo $currentYear; ?></p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Statistiche Anno Corrente -->
    <div class="current-stats-card">
        <h3>Statistiche <?php echo $currentYear; ?></h3>
        <div class="stats-grid">
            <div class="stat-box">
                <i class="fas fa-book-open"></i>
                <span class="stat-number"><?php echo $currentStats['libri_letti'] ?? 0; ?></span>
                <span class="stat-label">Libri letti</span>
            </div>
            <div class="stat-box">
                <i class="fas fa-star"></i>
                <span class="stat-number"><?php echo $currentStats['valutazione_media'] ? round($currentStats['valutazione_media'], 1) : 'N/A'; ?></span>
                <span class="stat-label">Valutazione media</span>
            </div>
            <div class="stat-box">
                <i class="fas fa-tags"></i>
                <span class="stat-number"><?php echo $currentStats['generi_diversi'] ?? 0; ?></span>
                <span class="stat-label">Generi diversi</span>
            </div>
            <div class="stat-box">
                <i class="fas fa-file-alt"></i>
                <span class="stat-number"><?php echo number_format($currentStats['pagine_totali'] ?? 0); ?></span>
                <span class="stat-label">Pagine totali</span>
            </div>
        </div>
    </div>

    <!-- Grafico Progresso Mensile -->
    <div class="monthly-chart-card">
        <h3>Progresso Mensile <?php echo $currentYear; ?></h3>
        <div class="chart-container">
            <canvas id="monthlyChart"></canvas>
        </div>
    </div>

    <!-- Form Nuovo Obiettivo -->
    <div class="goal-form-card">
        <h3>Imposta/Modifica Obiettivo</h3>
        <form method="POST" class="goal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="year">
                        <i class="fas fa-calendar"></i> Anno
                    </label>
                    <select id="year" name="year" required>
                        <?php for ($y = $currentYear - 2; $y <= $currentYear + 5; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $currentYear ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="goal">
                        <i class="fas fa-target"></i> Obiettivo (numero di libri)
                    </label>
                    <input type="number" id="goal" name="goal" required min="1" max="1000" 
                           placeholder="Es: 24" value="<?php echo $currentGoal['obiettivo_libri'] ?? ''; ?>">
                </div>
            </div>
            
            <button type="submit" name="set_goal" class="btn btn-primary">
                <i class="fas fa-save"></i> Imposta Obiettivo
            </button>
        </form>
    </div>

    <!-- Storico Obiettivi -->
    <div class="goals-history-card">
        <h3>Storico Obiettivi</h3>
        
        <?php if (empty($goals)): ?>
            <div class="empty-state">
                <i class="fas fa-target"></i>
                <p>Non hai ancora impostato nessun obiettivo</p>
            </div>
        <?php else: ?>
            <div class="goals-table">
                <table>
                    <thead>
                        <tr>
                            <th>Anno</th>
                            <th>Obiettivo</th>
                            <th>Letti</th>
                            <th>Progresso</th>
                            <th>Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($goals as $goal): ?>
                            <tr>
                                <td><?php echo $goal['anno']; ?></td>
                                <td><?php echo $goal['obiettivo_libri']; ?></td>
                                <td><?php echo $goal['libri_letti']; ?></td>
                                <td>
                                    <div class="mini-progress">
                                        <div class="mini-progress-bar" 
                                             style="width: <?php echo min(100, $goal['percentuale']); ?>%"></div>
                                    </div>
                                    <span class="percentage"><?php echo $goal['percentuale']; ?>%</span>
                                </td>
                                <td>
                                    <?php if ($goal['percentuale'] >= 100): ?>
                                        <span class="status success">
                                            <i class="fas fa-check-circle"></i> Completato
                                        </span>
                                    <?php elseif ($goal['anno'] < $currentYear): ?>
                                        <span class="status failed">
                                            <i class="fas fa-times-circle"></i> Non completato
                                        </span>
                                    <?php else: ?>
                                        <span class="status active">
                                            <i class="fas fa-clock"></i> In corso
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script>
// Grafico progresso mensile
const ctx = document.getElementById('monthlyChart').getContext('2d');
const monthlyChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_values($months)); ?>,
        datasets: [{
            label: 'Libri letti',
            data: <?php echo json_encode(array_values($monthlyData)); ?>,
            backgroundColor: 'rgba(54, 162, 235, 0.6)',
            borderColor: 'rgba(54, 162, 235, 1)',
            borderWidth: 1
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
</script>

<?php include 'includes/footer.php'; ?>