<?php
// register.php
// Pagina di registrazione

require_once 'config/session.php';
require_once 'includes/functions.php';

// Reindirizza se già loggato
redirectIfLoggedIn();

$pageTitle = 'Registrati - Reading Tracker';

// Gestisce il form di registrazione
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Validazione
    if (empty($username)) {
        $errors[] = 'Username è obbligatorio';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username deve essere almeno 3 caratteri';
    }
      
    if (empty($password)) {
        $errors[] = 'Password è obbligatoria';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password deve essere almeno 6 caratteri';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Le password non corrispondono';
    }
    
    if (empty($errors)) {
        try {
            $pdo = getConnection();
            
            // Verifica se username esiste già
            $stmt = $pdo->prepare("SELECT id FROM Utente WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn()) {
                $errors[] = 'Username già in uso';
            }
              
            if (empty($errors)) {
                // Hash della password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Inserisce nuovo utente
                $stmt = $pdo->prepare("INSERT INTO Utente (username, password) VALUES (?, ?)");
                $stmt->execute([$username, $hashedPassword]);
                
                setFlashMessage('success', 'Registrazione completata con successo! Ora puoi accedere.');
                header("Location: login.php");
                exit();
            }
        } catch (Exception $e) {
            $errors[] = 'Errore durante la registrazione. Riprova più tardi.';
        }
    }
    
    if (!empty($errors)) {
        setFlashMessage('error', implode('<br>', $errors));
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2><i class="fas fa-user-plus"></i> Registrati</h2>
            <p>Crea il tuo account per iniziare a monitorare le tue abitudini di lettura.</p>
        </div>
        
        <form class="auth-form" method="POST" action="">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" id="username" name="username" required 
                       placeholder="Scegli un username" 
                       value="<?php echo htmlspecialchars($username ?? ''); ?>">
                <small class="form-help">Almeno 3 caratteri</small>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" id="password" name="password" required 
                       placeholder="Scegli una password">
                <small class="form-help">Almeno 6 caratteri</small>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">
                    <i class="fas fa-lock"></i> Conferma Password
                </label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="Ripeti la password">
            </div>
            
            <button type="submit" class="btn btn-primary btn-full">
                <i class="fas fa-user-plus"></i> Registrati
            </button>
        </form>
        
        <div class="auth-footer">
            <p>Hai già un account? <a href="login.php">Accedi qui</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>