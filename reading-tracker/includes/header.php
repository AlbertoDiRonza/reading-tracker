<?php
// includes/header.php
// Header comune per tutte le pagine

if (!isset($pageTitle)) {
    $pageTitle = 'Reading Tracker';
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="<?php echo isLoggedIn() ? 'dashboard.php' : 'index.php'; ?>" class="logo">
                    <i class="fas fa-book"></i>
                    Reading Tracker
                </a>
                
                <ul class="nav-links">
                    <?php if (isLoggedIn()): ?>
                        <li><a href="dashboard.php" class="<?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a></li>
                        <li><a href="books.php" class="<?php echo $currentPage == 'books.php' ? 'active' : ''; ?>">
                            <i class="fas fa-book-open"></i> I Miei Libri
                        </a></li>
                        <li><a href="goals.php" class="<?php echo $currentPage == 'goals.php' ? 'active' : ''; ?>">
                            <i class="fas fa-target"></i> Obiettivi
                        </a></li>
                        <li><a href="statistics.php" class="<?php echo $currentPage == 'statistics.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> Statistiche
                        </a></li>
                        <li class="user-dropdown">
                            <button class="user-button" id="userDropdownToggle">
                                <i class="fas fa-user"></i> 
                                <?php echo htmlspecialchars(getCurrentUsername()); ?>
                                <i class="fas fa-chevron-down dropdown-arrow"></i>
                            </button>
                            <div class="user-dropdown-menu" id="userDropdownMenu">
                                <a href="#" class="dropdown-item delete-account-btn" onclick="deleteAccount(event)">
                                    <i class="fas fa-trash-alt"></i>
                                    Elimina Account
                                </a>
                            </div>
                        </li>
                        <li><a href="logout.php" class="btn btn-secondary">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="<?php echo $currentPage == 'login.php' ? 'active' : ''; ?>">
                            <i class="fas fa-sign-in-alt"></i> Accedi
                        </a></li>
                        <li><a href="register.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Registrati
                        </a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <?php
            // Mostra messaggi flash
            $flashMessage = getFlashMessage();
            if ($flashMessage):
            ?>
                <div class="alert alert-<?php echo $flashMessage['type']; ?>" id="flashMessage">
                    <i class="fas fa-<?php echo $flashMessage['type'] == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($flashMessage['message']); ?>
                    <button class="alert-close" onclick="closeAlert()">&times;</button>
                </div>
            <?php endif; ?>

    <script>
        // Script per header
        document.addEventListener('DOMContentLoaded', function() {
            // Funzione per chiudere gli alert
            window.closeAlert = function() {
                const alert = document.getElementById('flashMessage');
                if (alert) {
                    alert.style.display = 'none';
                }
            };
            
            // Auto-chiudi gli alert dopo 5 secondi
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                setTimeout(function() {
                    closeAlert();
                }, 5000);
            }

            // Gestione dropdown utente
            const userDropdownToggle = document.getElementById('userDropdownToggle');
            const userDropdownMenu = document.getElementById('userDropdownMenu');

            if (userDropdownToggle && userDropdownMenu) {
                userDropdownToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userDropdownMenu.classList.toggle('show');
                });

                // Chiudi dropdown quando si clicca fuori
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.user-dropdown')) {
                        userDropdownMenu.classList.remove('show');
                    }
                });
            }
        });

        // Funzione per eliminazione account
        function deleteAccount(event) {
            event.preventDefault();
            
            if (confirm('Sei sicuro di voler eliminare il tuo account? Questa azione è irreversibile.')) {
                window.location.href = 'delete_account.php';
            }
        }
    </script>