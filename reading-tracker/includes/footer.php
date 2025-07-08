</main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-book"></i> Reading Tracker</h3>
                <p>Monitora le tue abitudini di lettura e raggiungi i tuoi obiettivi.</p>
            </div>
            
            <div class="footer-section">
                <h4>Funzionalità</h4>
                <ul>
                    <li><a href="books.php">Gestione Libri</a></li>
                    <li><a href="goals.php">Obiettivi di Lettura</a></li>
                    <li><a href="statistics.php">Statistiche</a></li>
                </ul>
            </div>
   
    <!-- Scripts -->
    <script src="assets/js/script.js"></script>
    
    <script>
        // Auto-chiudi messaggi flash dopo 5 secondi
        setTimeout(function() {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                flashMessage.style.display = 'none';
            }
        }, 5000);
        
        // Funzione per chiudere manualmente il messaggio flash
        function closeAlert() {
            const flashMessage = document.getElementById('flashMessage');
            if (flashMessage) {
                flashMessage.style.display = 'none';
            }
        }
    </script>
</body>
</html>