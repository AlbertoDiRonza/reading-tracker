<?php
// login.php
// Pagina di login

require_once 'config/session.php';
require_once 'includes/functions.php';

// Reindirizza se già loggato
redirectIfLoggedIn();

$pageTitle = 'Accedi - Reading Tracker';

// Gestisce il form di login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        setFlashMessage('error', 'Inserisci username e password');
    } else {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("SELECT id, username, password FROM Utente WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                login($user['id'], $user['username']);
                header("Location: dashboard.php");
                exit();
            } else {
                setFlashMessage('error', 'Username o password non validi');
            }
        } catch (Exception $e) {
            setFlashMessage('error', 'Errore di connessione. Riprova più tardi.');
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h2><i class="fas fa-sign-in-alt"></i> Accedi</h2>
            <p>Benvenuto di nuovo! Accedi per continuare a monitorare le tue letture.</p>
        </div>
        
        <form class="auth-form" method="POST" action="">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" id="username" name="username" required 
                       placeholder="Il tuo username" 
                       value="<?php echo htmlspecialchars($username ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
                <input type="password" id="password" name="password" required 
                       placeholder="La tua password">
            </div>
            
            <button type="submit" class="btn btn-primary btn-full">
                <i class="fas fa-sign-in-alt"></i> Accedi
            </button>
        </form>
        
        <div class="auth-footer">
            <p>Non hai ancora un account? <a href="register.php">Registrati qui</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>