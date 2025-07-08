<?php
// index.php
// Pagina principale dell'applicazione

require_once 'config/session.php';
require_once 'includes/functions.php';

// Reindirizza se già loggato
redirectIfLoggedIn();

$pageTitle = 'Reading Tracker - Monitora le tue abitudini di lettura';
?>

<?php include 'includes/header.php'; ?>

<div class="hero-section">
    <div class="hero-content">
        <h1><i class="fas fa-book-reader"></i> Reading Tracker</h1>
        <p class="hero-description">Monitora le tue abitudini di lettura, imposta obiettivi e scopri le tue statistiche personali</p>
        
        <div class="hero-features">
            <div class="feature-card">
                <i class="fas fa-book-open"></i>
                <h3>Gestisci i tuoi libri</h3>
                <p>Aggiungi libri manualmente o tramite ricerca ISBN</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-target"></i>
                <h3>Imposta obiettivi</h3>
                <p>Stabilisci quanti libri vuoi leggere ogni anno</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-chart-line"></i>
                <h3>Visualizza statistiche</h3>
                <p>Scopri i tuoi generi preferiti e il tuo progresso</p>
            </div>
        </div>
        
        <div class="hero-actions">
            <a href="register.php" class="btn btn-primary btn-large">
                <i class="fas fa-user-plus"></i> Inizia Subito
            </a>
            <a href="login.php" class="btn btn-secondary btn-large">
                <i class="fas fa-sign-in-alt"></i> Accedi
            </a>
        </div>
    </div>
</div>

<div class="info-section">
    <div class="container">
        <h2>Perché usare Reading Tracker?</h2>
        <div class="info-grid">
            <div class="info-item">
                <i class="fas fa-clock"></i>
                <h3>Risparmia tempo</h3>
                <p>Tieni traccia facilmente di tutti i libri che leggi senza dover ricordare tutto</p>
            </div>
            
            <div class="info-item">
                <i class="fas fa-trophy"></i>
                <h3>Raggiungi i tuoi obiettivi</h3>
                <p>Imposta obiettivi di lettura e monitora il tuo progresso durante l'anno</p>
            </div>
            
            <div class="info-item">
                <i class="fas fa-brain"></i>
                <h3>Scopri i tuoi gusti</h3>
                <p>Analizza le statistiche per scoprire i tuoi generi preferiti e le tue abitudini</p>
            </div>
            
            <div class="info-item">
                <i class="fas fa-mobile-alt"></i>
                <h3>Sempre con te</h3>
                <p>Accedi da qualsiasi dispositivo e tieni sempre aggiornata la tua libreria</p>
            </div>
        </div>
    </div>
</div>

<div class="cta-section">
    <div class="container">
        <h2>Inizia a monitorare le tue letture oggi stesso!</h2>
        <p>Unisciti a migliaia di lettori che hanno già migliorato le loro abitudini di lettura</p>
        <a href="register.php" class="btn btn-primary btn-xl">
            <i class="fas fa-rocket"></i> Registrati Gratis
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>