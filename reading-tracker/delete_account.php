<?php
// delete_account.php
// Script per eliminare l'account utente e tutti i dati associati

require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

// Verifica che l'utente sia autenticato
if (!isLoggedIn()) {
    setFlashMessage('Devi essere autenticato per eliminare l\'account.', 'error');
    header('Location: login.php');
    exit;
}

$userId = getCurrentUserId();

try {
    // Ottieni la connessione al database usando la funzione definita in database.php
    $pdo = getConnection();
    
    // Inizia una transazione
    $pdo->beginTransaction();
    
    // Elimina tutti i dati associati all'utente
    
    // 1. Elimina le sessioni di lettura
    $stmt = $pdo->prepare("DELETE FROM reading_sessions WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // 2. Elimina i libri dell'utente
    $stmt = $pdo->prepare("DELETE FROM books WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // 3. Elimina gli obiettivi dell'utente
    $stmt = $pdo->prepare("DELETE FROM reading_goals WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // 4. Elimina l'utente
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    
    // Conferma la transazione
    $pdo->commit();
    
    // Distruggi la sessione
    session_destroy();
    
    // Reindirizza alla home page
    header('Location: index.php');
    exit;
    
} catch (Exception $e) {
    // Rollback in caso di errore
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    // Log dell'errore
    error_log("Errore durante l'eliminazione dell'account per l'utente $userId: " . $e->getMessage());
    
    // Messaggio di errore e reindirizzamento
    setFlashMessage('Si è verificato un errore durante l\'eliminazione dell\'account. Riprova più tardi.', 'error');
    header('Location: dashboard.php');
    exit;
}
?>