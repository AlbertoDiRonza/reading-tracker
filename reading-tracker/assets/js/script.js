// Oggetto principale per la gestione dell'applicazione
const ReadingTracker = {
    // Inizializzazione dell'applicazione
    init() {
        this.bindEvents();
        this.loadInitialData();
    },

    // Binding degli eventi
    bindEvents() {
        // Event listeners per la ricerca ISBN
        const isbnInput = document.getElementById('isbn');
        if (isbnInput) {
            isbnInput.addEventListener('input', this.debounce(this.searchISBN.bind(this), 500));
            isbnInput.addEventListener('keydown', this.handleIsbnKeydown.bind(this));
        }

        // Event listener per il form di aggiunta libro
        const addBookForm = document.getElementById('add-book-form');
        if (addBookForm) {
            addBookForm.addEventListener('submit', this.handleAddBook.bind(this));
        }

        // Event listener per il form obiettivi
        const goalForm = document.getElementById('goal-form');
        if (goalForm) {
            goalForm.addEventListener('submit', this.handleGoalSubmit.bind(this));
        }

        // Event listeners per i pulsanti di eliminazione
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('delete-book')) {
                this.deleteBook(e.target.dataset.bookId);
            }
        });

        // Chiusura dei risultati ISBN quando si clicca fuori
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.isbn-search-container')) {
                this.hideIsbnResults();
            }
        });
    },

    // Caricamento dati iniziali
    loadInitialData() {
        // Carica statistiche se siamo nella dashboard
        if (document.querySelector('.dashboard-grid')) {
            this.loadDashboardStats();
        }

        // Carica grafici se siamo nella pagina statistiche
        if (document.querySelector('.charts-container')) {
            this.loadStatisticsCharts();
        }
    },

    // Debounce function per limitare le chiamate API
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Ricerca ISBN
    async searchISBN(e) {
        const isbn = e.target.value.trim();
        
        if (isbn.length < 3) {
            this.hideIsbnResults();
            return;
        }

        try {
            this.showLoading(true);
            const response = await fetch(`api/isbn_search.php?isbn=${encodeURIComponent(isbn)}`);
            const data = await response.json();
            
            if (data.success && data.books.length > 0) {
                this.displayIsbnResults(data.books);
            } else {
                this.hideIsbnResults();
            }
        } catch (error) {
            console.error('Errore nella ricerca ISBN:', error);
            this.showAlert('Errore nella ricerca del libro', 'error');
        } finally {
            this.showLoading(false);
        }
    },

    // Gestione tasti per la ricerca ISBN
    handleIsbnKeydown(e) {
        const resultsContainer = document.querySelector('.isbn-results');
        if (!resultsContainer) return;

        const items = resultsContainer.querySelectorAll('.isbn-result-item');
        let activeIndex = -1;

        items.forEach((item, index) => {
            if (item.classList.contains('active')) {
                activeIndex = index;
            }
        });

        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                if (activeIndex < items.length - 1) {
                    this.setActiveIsbnResult(activeIndex + 1);
                }
                break;
            case 'ArrowUp':
                e.preventDefault();
                if (activeIndex > 0) {
                    this.setActiveIsbnResult(activeIndex - 1);
                }
                break;
            case 'Enter':
                e.preventDefault();
                if (activeIndex >= 0) {
                    this.selectIsbnResult(items[activeIndex]);
                }
                break;
            case 'Escape':
                this.hideIsbnResults();
                break;
        }
    },

    // Impostazione elemento attivo nei risultati ISBN
    setActiveIsbnResult(index) {
        const items = document.querySelectorAll('.isbn-result-item');
        items.forEach((item, i) => {
            item.classList.toggle('active', i === index);
        });
    },

    // Visualizzazione risultati ISBN
displayIsbnResults(books) {
    let resultsContainer = document.querySelector('.isbn-results');
    
    if (!resultsContainer) {
        resultsContainer = document.createElement('div');
        resultsContainer.className = 'isbn-results';
        
        // Cerca il contenitore ISBN in diversi modi
        const searchContainer = document.querySelector('.isbn-search-container') || 
                               document.querySelector('#isbn').parentElement ||
                               document.querySelector('#isbn-form') ||
                               document.querySelector('form');
        
        if (!searchContainer) {
            console.error('Contenitore per i risultati ISBN non trovato');
            return;
        }
        
        searchContainer.appendChild(resultsContainer);
    }

    resultsContainer.innerHTML = books.map(book => `
        <div class="isbn-result-item" data-book='${JSON.stringify(book)}'>
            <div class="isbn-result-title">${book.title}</div>
            <div class="isbn-result-author">${book.author}</div>
        </div>
    `).join('');

    // Aggiungi event listeners ai risultati
    resultsContainer.querySelectorAll('.isbn-result-item').forEach(item => {
        item.addEventListener('click', () => this.selectIsbnResult(item));
    });

    resultsContainer.style.display = 'block';
},

    // Selezione risultato ISBN
    selectIsbnResult(item) {
        const bookData = JSON.parse(item.dataset.book);
        
        // Compila i campi del form
        document.getElementById('title').value = bookData.title;
        document.getElementById('author').value = bookData.author;
        document.getElementById('genre').value = bookData.genre || '';
        document.getElementById('isbn').value = bookData.isbn;

        this.hideIsbnResults();
        this.showAlert('Libro selezionato! Compila i campi rimanenti.', 'success');
    },

    // Nascondere risultati ISBN
    hideIsbnResults() {
        const resultsContainer = document.querySelector('.isbn-results');
        if (resultsContainer) {
            resultsContainer.style.display = 'none';
        }
    },

    // Gestione aggiunta libro
    async handleAddBook(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        
        try {
            this.showLoading(true);
            const response = await fetch('api/add_book.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Libro aggiunto con successo!', 'success');
                e.target.reset();
                this.hideIsbnResults();
                
                // Ricarica la lista dei libri se presente
                if (document.querySelector('.books-grid')) {
                    this.loadBooks();
                }
            } else {
                this.showAlert(data.message || 'Errore nell\'aggiunta del libro', 'error');
            }
        } catch (error) {
            console.error('Errore nell\'aggiunta del libro:', error);
            this.showAlert('Errore nel server', 'error');
        } finally {
            this.showLoading(false);
        }
    },

    // Gestione form obiettivi
    async handleGoalSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        
        try {
            this.showLoading(true);
            const response = await fetch('api/goal.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Obiettivo aggiornato con successo!', 'success');
                this.updateGoalProgress();
            } else {
                this.showAlert(data.message || 'Errore nell\'aggiornamento dell\'obiettivo', 'error');
            }
        } catch (error) {
            console.error('Errore nell\'aggiornamento dell\'obiettivo:', error);
            this.showAlert('Errore nel server', 'error');
        } finally {
            this.showLoading(false);
        }
    },

    // Eliminazione libro
    async deleteBook(bookId) {
        if (!confirm('Sei sicuro di voler eliminare questo libro?')) {
            return;
        }

        try {
            this.showLoading(true);
            const response = await fetch('api/delete_book.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ book_id: bookId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showAlert('Libro eliminato con successo!', 'success');
                this.loadBooks();
            } else {
                this.showAlert(data.message || 'Errore nell\'eliminazione del libro', 'error');
            }
        } catch (error) {
            console.error('Errore nell\'eliminazione del libro:', error);
            this.showAlert('Errore nel server', 'error');
        } finally {
            this.showLoading(false);
        }
    },

    // Caricamento libri
    async loadBooks() {
        try {
            const response = await fetch('api/books.php');
            const data = await response.json();
            
            if (data.success) {
                this.displayBooks(data.books);
            }
        } catch (error) {
            console.error('Errore nel caricamento dei libri:', error);
        }
    },

    // Visualizzazione libri
    displayBooks(books) {
        const booksGrid = document.querySelector('.books-grid');
        if (!booksGrid) return;

        booksGrid.innerHTML = books.map(book => `
            <div class="book-card fade-in">
                <div class="book-title">${book.title}</div>
                <div class="book-author">di ${book.author}</div>
                <div class="book-genre">${book.genre}</div>
                <div class="book-date">Letto il: ${this.formatDate(book.date_read)}</div>
                <div class="mt-1">
                    <button class="btn btn-danger delete-book" data-book-id="${book.id}">
                        Elimina
                    </button>
                </div>
            </div>
        `).join('');
    },

    // Caricamento statistiche dashboard
    async loadDashboardStats() {
        try {
            const response = await fetch('api/statistics.php?type=dashboard');
            const data = await response.json();
            
            if (data.success) {
                this.updateDashboardStats(data.stats);
            }
        } catch (error) {
            console.error('Errore nel caricamento delle statistiche:', error);
        }
    },

    // Aggiornamento statistiche dashboard
    updateDashboardStats(stats) {
        const elements = {
            'total-books': stats.total_books,
            'books-this-year': stats.books_this_year,
            'favorite-genre': stats.favorite_genre,
            'goal-progress': stats.goal_progress
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });

        // Aggiorna barra di progresso
        this.updateProgressBar(stats.goal_progress_percent);
    },

    // Aggiornamento barra di progresso
    updateProgressBar(percentage) {
        const progressBar = document.querySelector('.progress-fill');
        const progressText = document.querySelector('.progress-text');
        
        if (progressBar && progressText) {
            progressBar.style.width = `${Math.min(percentage, 100)}%`;
            progressText.textContent = `${percentage}% completato`;
        }
    },

    // Caricamento grafici statistiche
    async loadStatisticsCharts() {
        try {
            const response = await fetch('api/statistics.php?type=charts');
            const data = await response.json();
            
            if (data.success) {
                this.renderCharts(data.charts);
            }
        } catch (error) {
            console.error('Errore nel caricamento dei grafici:', error);
        }
    },

    // Rendering grafici
    renderCharts(chartsData) {
        // Grafico generi
        if (chartsData.genres && document.getElementById('genres-chart')) {
            this.renderGenresChart(chartsData.genres);
        }

        // Grafico letture mensili
        if (chartsData.monthly && document.getElementById('monthly-chart')) {
            this.renderMonthlyChart(chartsData.monthly);
        }
    },

    // Grafico generi (usando Chart.js se disponibile)
    renderGenresChart(genresData) {
        const ctx = document.getElementById('genres-chart');
        if (!ctx) return;

        // Implementazione semplice senza Chart.js
        const container = ctx.parentElement;
        container.innerHTML = `
            <h3 class="chart-title">Generi Preferiti</h3>
            <div class="genres-list">
                ${genresData.map(genre => `
                    <div class="genre-item">
                        <span class="genre-name">${genre.name}</span>
                        <div class="genre-bar">
                            <div class="genre-fill" style="width: ${(genre.count / genresData[0].count) * 100}%"></div>
                        </div>
                        <span class="genre-count">${genre.count}</span>
                    </div>
                `).join('')}
            </div>
        `;
    },

    // Grafico letture mensili
    renderMonthlyChart(monthlyData) {
        const ctx = document.getElementById('monthly-chart');
        if (!ctx) return;

        const container = ctx.parentElement;
        container.innerHTML = `
            <h3 class="chart-title">Letture Mensili</h3>
            <div class="monthly-chart">
                ${monthlyData.map(month => `
                    <div class="month-item">
                        <div class="month-bar">
                            <div class="month-fill" style="height: ${(month.count / Math.max(...monthlyData.map(m => m.count))) * 100}%"></div>
                        </div>
                        <span class="month-label">${month.name}</span>
                        <span class="month-count">${month.count}</span>
                    </div>
                `).join('')}
            </div>
        `;
    },

    // Aggiornamento progresso obiettivo
    async updateGoalProgress() {
        try {
            const response = await fetch('api/goal.php?action=get');
            const data = await response.json();
            
            if (data.success) {
                this.updateProgressBar(data.progress_percent);
            }
        } catch (error) {
            console.error('Errore nel caricamento del progresso:', error);
        }
    },

    // Visualizzazione loading
    showLoading(show) {
        const loadingElements = document.querySelectorAll('.loading');
        loadingElements.forEach(el => {
            el.style.display = show ? 'inline-block' : 'none';
        });

        // Disabilita/abilita pulsanti
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(btn => {
            btn.disabled = show;
        });
    },

    // Visualizzazione alert
    showAlert(message, type = 'info') {
        // Rimuovi alert esistenti
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        // Crea nuovo alert
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} fade-in`;
        alert.textContent = message;

        // Inserisci l'alert
        const container = document.querySelector('.container');
        if (container) {
            container.insertBefore(alert, container.firstChild);
        }

        // Rimuovi l'alert dopo 5 secondi
        setTimeout(() => {
            alert.remove();
        }, 5000);
    },

    // Formattazione data
    formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    },

    // Validazione form
    validateForm(form) {
        const requiredFields = form.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('error');
                isValid = false;
            } else {
                field.classList.remove('error');
            }
        });

        return isValid;
    },

    // Gestione errori di rete
    handleNetworkError(error) {
        console.error('Errore di rete:', error);
        this.showAlert('Errore di connessione. Verifica la tua connessione internet.', 'error');
    },

    // Sanitizzazione input
    sanitizeInput(input) {
        const div = document.createElement('div');
        div.textContent = input;
        return div.innerHTML;
    },

    // Gestione responsive menu (se necessario)
    toggleMobileMenu() {
        const navLinks = document.querySelector('.nav-links');
        if (navLinks) {
            navLinks.classList.toggle('active');
        }
    },

    // Inizializzazione tooltips
    initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        tooltipElements.forEach(element => {
            element.addEventListener('mouseenter', this.showTooltip.bind(this));
            element.addEventListener('mouseleave', this.hideTooltip.bind(this));
        });
    },

    // Visualizzazione tooltip
    showTooltip(e) {
        const text = e.target.dataset.tooltip;
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        document.body.appendChild(tooltip);

        const rect = e.target.getBoundingClientRect();
        tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
        tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
    },

    // Nascondere tooltip
    hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    },

    // Gestione storage locale (fallback se il server non è disponibile)
    saveToLocalStorage(key, data) {
        try {
            localStorage.setItem(key, JSON.stringify(data));
        } catch (error) {
            console.warn('Local storage non disponibile:', error);
        }
    },

    // Caricamento da storage locale
    loadFromLocalStorage(key) {
        try {
            const data = localStorage.getItem(key);
            return data ? JSON.parse(data) : null;
        } catch (error) {
            console.warn('Errore nel caricamento da local storage:', error);
            return null;
        }
    },

    // Pulizia storage locale
    clearLocalStorage() {
        try {
            localStorage.clear();
        } catch (error) {
            console.warn('Errore nella pulizia del local storage:', error);
        }
    }
};

// Utility functions globali
const Utils = {
    // Generazione ID unico
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    },

    // Capitalizzazione prima lettera
    capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    },

    // Truncate text
    truncate(text, length = 100) {
        return text.length > length ? text.substring(0, length) + '...' : text;
    },

    // Validazione email
    isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    },

    // Validazione ISBN
    isValidISBN(isbn) {
        const cleaned = isbn.replace(/[^0-9X]/gi, '');
        return cleaned.length === 10 || cleaned.length === 13;
    },

    // Formattazione numeri
    formatNumber(num) {
        return new Intl.NumberFormat('it-IT').format(num);
    },

    // Calcolo tempo di lettura stimato
    calculateReadingTime(pages, wordsPerPage = 250, wordsPerMinute = 200) {
        const totalWords = pages * wordsPerPage;
        const minutes = totalWords / wordsPerMinute;
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = Math.floor(minutes % 60);
        
        if (hours > 0) {
            return `${hours}h ${remainingMinutes}m`;
        } else {
            return `${remainingMinutes}m`;
        }
    }
};

// Gestione temi (dark/light mode)
const ThemeManager = {
    init() {
        this.currentTheme = this.loadTheme();
        this.applyTheme(this.currentTheme);
        this.bindEvents();
    },

    loadTheme() {
        return localStorage.getItem('theme') || 'light';
    },

    saveTheme(theme) {
        localStorage.setItem('theme', theme);
    },

    applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        this.currentTheme = theme;
    },

    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme);
        this.saveTheme(newTheme);
    },

    bindEvents() {
        const themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', this.toggleTheme.bind(this));
        }
    }
};

// Gestione notifiche
const NotificationManager = {
    permission: 'default',

    async init() {
        if ('Notification' in window) {
            this.permission = await Notification.requestPermission();
        }
    },

    show(title, options = {}) {
        if (this.permission === 'granted') {
            return new Notification(title, {
                icon: 'assets/icons/book-icon.png',
                badge: 'assets/icons/book-icon.png',
                ...options
            });
        }
    },

    showGoalReminder(booksRead, goalTarget) {
        const remaining = goalTarget - booksRead;
        if (remaining > 0) {
            this.show('Promemoria Obiettivo Lettura', {
                body: `Ti mancano ${remaining} libri per raggiungere il tuo obiettivo!`,
                tag: 'goal-reminder'
            });
        }
    }
};

// Gestione offline
const OfflineManager = {
    isOnline: navigator.onLine,

    init() {
        this.bindEvents();
        this.updateStatus();
    },

    bindEvents() {
        window.addEventListener('online', () => {
            this.isOnline = true;
            this.updateStatus();
            this.syncOfflineData();
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateStatus();
        });
    },

    updateStatus() {
        const statusElement = document.getElementById('connection-status');
        if (statusElement) {
            statusElement.textContent = this.isOnline ? 'Online' : 'Offline';
            statusElement.className = this.isOnline ? 'status-online' : 'status-offline';
        }
    },

    async syncOfflineData() {
        const offlineData = ReadingTracker.loadFromLocalStorage('offline-books');
        if (offlineData && offlineData.length > 0) {
            for (const book of offlineData) {
                try {
                    await ReadingTracker.addBook(book);
                } catch (error) {
                    console.error('Errore sync libro offline:', error);
                }
            }
            ReadingTracker.saveToLocalStorage('offline-books', []);
        }
    }
};

// Inizializzazione al caricamento della pagina
document.addEventListener('DOMContentLoaded', () => {
    ReadingTracker.init();
    ThemeManager.init();
    NotificationManager.init();
    OfflineManager.init();
    
    // Inizializzazione tooltips
    ReadingTracker.initTooltips();
    
    // Gestione form di ricerca globale
    const searchForm = document.getElementById('search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const query = document.getElementById('search-input').value;
            ReadingTracker.searchBooks(query);
        });
    }
});

// Gestione errori globali
window.addEventListener('error', (e) => {
    console.error('Errore JavaScript:', e.error);
    ReadingTracker.showAlert('Si è verificato un errore. Ricarica la pagina.', 'error');
});

// Gestione promesse non gestite
window.addEventListener('unhandledrejection', (e) => {
    console.error('Promise rejection non gestita:', e.reason);
    ReadingTracker.showAlert('Errore di connessione. Riprova.', 'error');
});

// Export per uso in altri file se necessario
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ReadingTracker, Utils, ThemeManager, NotificationManager, OfflineManager };
}