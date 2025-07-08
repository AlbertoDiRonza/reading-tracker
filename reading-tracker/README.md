# Piattaforma per il Monitoraggio Abitudini di Lettura

## Requisiti di Sistema

- XAMPP (Apache, MySQL, PHP)
- Browser Web moderno (Chrome, Firefox, Safari, Edge)
- Connessione Internet (per ricerca ISBN)
- Git (per clonare la repository)

## Installazione

### 1. Installazione XAMPP

- Scarica XAMPP dal sito ufficiale: https://www.apachefriends.org/
- Installa XAMPP seguendo le istruzioni per il tuo sistema operativo
- Avvia XAMPP Control Panel

### 2. Installazione Git (se non già installato)

- **Windows**: Scarica Git da https://git-scm.com/download/win
- **macOS**: Installa tramite Homebrew `brew install git` o scarica da https://git-scm.com/download/mac
- **Linux**: Installa tramite package manager, es. `sudo apt install git` (Ubuntu/Debian)

### 3. Clonazione del Progetto

#### Opzione A: Usando Git da Terminale/Command Line

1. Apri il terminale/prompt dei comandi
2. Naviga nella cartella htdocs di XAMPP:
   ```bash
   # Windows
   cd C:\xampp\htdocs\
   
   # macOS/Linux
   cd /opt/lampp/htdocs/
   ```
3. Clona la repository:
   ```bash
   git clone https://github.com/AlbertoDiRonza/reading-tracker.git reading-tracker
   ```
4. Entra nella cartella del progetto:
   ```bash
   cd reading-tracker
   ```

#### Opzione B: Usando GitHub Desktop

1. Scarica e installa GitHub Desktop da https://desktop.github.com/
2. Apri GitHub Desktop e accedi al tuo account GitHub
3. Clicca su "Clone a repository from the Internet"
4. Inserisci l'URL della repository o selezionala dalla lista
5. Scegli come cartella di destinazione la cartella htdocs di XAMPP:
   - **Windows**: `C:\xampp\htdocs\reading-tracker`
   - **macOS/Linux**: `/opt/lampp/htdocs/reading-tracker`
6. Clicca "Clone"

#### Opzione C: Download ZIP (alternativa senza Git)

1. Vai sulla pagina GitHub della repository
2. Clicca sul bottone verde "Code"
3. Seleziona "Download ZIP"
4. Estrai il file ZIP nella cartella htdocs di XAMPP
5. Rinomina la cartella estratta in `reading-tracker`

### 4. Configurazione del Progetto

**Avvia i servizi XAMPP:**
- Apri XAMPP Control Panel
- Avvia Apache e MySQL

### 5. Configurazione Database

**Accedi a phpMyAdmin:**
- Apri il browser e vai su http://localhost/phpmyadmin

**Crea il database:**
- Clicca su "Nuovo" nel menu laterale
- Nome database: `reading_tracker`
- Collation: `utf8mb4_general_ci`
- Clicca "Crea"

**Importa la struttura:**
- Seleziona il database `reading_tracker`
- Clicca sulla tab "SQL"
- Copia e incolla il contenuto del file `database.sql`
- Clicca "Esegui"

### 6. Configurazione Connessione Database

- Apri il file `config/database.php`
- Verifica le impostazioni di connessione:
```php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'reading_tracker';
```

## Struttura del Progetto

```
reading-tracker/
├── index.php                 # Pagina principale
├── login.php                 # Pagina di login
├── register.php              # Pagina di registrazione
├── dashboard.php             # Dashboard utente
├── books.php                 # Gestione libri
├── goals.php                 # Gestione obiettivi
├── statistics.php            # Pagina statistiche
├── add_book.php              # Pagina aggiunta libro
├── config/
│   ├── database.php          # Configurazione database
│   └── session.php           # Gestione sessioni
├── includes/
│   ├── header.php            # Header comune
│   ├── footer.php            # Footer comune
│   └── functions.php         # Funzioni utili
├── api/
│   ├── isbn_search.php       # API ricerca ISBN
│   ├── statistics.php        # API statistiche
│   ├── generes_stats_api.php # Dati generi 
│   ├── ratings_stats_api.php # Dati ratings
│   ├── monthly_stats_api.php # Dati utente mensili
│   └── yearly_stats_api.php  # Dati utente annuali
├── assets/
│   ├── css/
│   │   └── style.css         # Stili CSS
│   └── js/
│       └── script.js         # JavaScript
├── database.sql              # Struttura database
└── README.md                 # Questo file
```

## Accesso all'Applicazione

- Apri il browser e vai su http://localhost/reading-tracker/
- Registra un nuovo account o accedi con credenziali esistenti

## Aggiornamento del Progetto

Se hai clonato la repository usando Git, puoi aggiornare il progetto con le ultime modifiche:

```bash
# Naviga nella cartella del progetto
cd C:\xampp\htdocs\reading-tracker  # Windows
# oppure
cd /opt/lampp/htdocs/reading-tracker  # macOS/Linux

# Aggiorna il progetto
git pull origin main
```

## Risoluzione Problemi Comuni

- **Errore di connessione database**: Verifica che MySQL sia avviato in XAMPP
- **Pagina non trovata**: Controlla che Apache sia avviato e che i file siano nella cartella corretta
- **Errori di permessi**: Su Linux/macOS, potresti dover modificare i permessi della cartella
- **Problemi con Git**: Verifica di avere Git installato e configurato correttamente
