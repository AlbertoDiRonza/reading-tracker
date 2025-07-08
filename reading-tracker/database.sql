-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Lug 08, 2025 alle 11:48
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `reading_tracker`
--

DELIMITER $$
--
-- Procedure
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AggiornaProgresso` (IN `user_id` INT, IN `anno_corrente` INT)   BEGIN
    DECLARE libri_contati INT DEFAULT 0;
    
    -- Conta i libri letti nell'anno specificato
    SELECT COUNT(*) INTO libri_contati
    FROM Statistiche s
    WHERE s.utente_id = user_id 
    AND YEAR(s.data_lettura) = anno_corrente;
    
    -- Aggiorna o inserisce il progresso
    INSERT INTO Progresso (utente_id, anno, libri_letti, obiettivo_libri) 
    VALUES (user_id, anno_corrente, libri_contati, 0)
    ON DUPLICATE KEY UPDATE 
        libri_letti = libri_contati;
END$$

--
-- Funzioni
--
CREATE DEFINER=`root`@`localhost` FUNCTION `CalcolaPercentualeObiettivo` (`user_id` INT, `anno` INT) RETURNS DECIMAL(5,2) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE obiettivo INT DEFAULT 0;
    DECLARE letti INT DEFAULT 0;
    DECLARE percentuale DECIMAL(5,2) DEFAULT 0;
    
    SELECT COALESCE(obiettivo_libri, 0), COALESCE(libri_letti, 0) 
    INTO obiettivo, letti
    FROM Progresso 
    WHERE utente_id = user_id AND anno = anno;
    
    IF obiettivo > 0 THEN
        SET percentuale = (letti / obiettivo) * 100;
        IF percentuale > 100 THEN
            SET percentuale = 100;
        END IF;
    ELSE
        SET percentuale = 0;
    END IF;
    
    RETURN percentuale;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `libro`
--

CREATE TABLE `libro` (
  `id` int(11) NOT NULL,
  `isbn` varchar(13) DEFAULT NULL,
  `titolo` varchar(255) NOT NULL,
  `autore` varchar(255) DEFAULT NULL,
  `genere` varchar(100) DEFAULT NULL,
  `anno_pubblicazione` int(11) DEFAULT NULL,
  `editore` varchar(255) DEFAULT NULL,
  `pagine` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `libro`
--

INSERT INTO `libro` (`id`, `isbn`, `titolo`, `autore`, `genere`, `anno_pubblicazione`, `editore`, `pagine`) VALUES
(10, '9780544003415', 'The Lord Of The Rings', 'J.R.R. Tolkien', NULL, NULL, 'William Morrow Paperbacks', 500),
(11, '9780547928227', 'The Hobbit', 'J.R.R. Tolkien', NULL, 2012, 'Mariner Books', 300),
(12, '9780439708180', 'Harry Potter and the sorcerer&#039;s stone', 'J. K. Rowling', NULL, 1999, 'Scholastic Paperbacks', 784);

-- --------------------------------------------------------

--
-- Struttura della tabella `progresso`
--

CREATE TABLE `progresso` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `anno` int(11) NOT NULL,
  `obiettivo_libri` int(11) DEFAULT 0,
  `libri_letti` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `progresso`
--

INSERT INTO `progresso` (`id`, `utente_id`, `anno`, `obiettivo_libri`, `libri_letti`) VALUES
(15, 4, 2025, 3, 3);

-- --------------------------------------------------------

--
-- Struttura della tabella `statistiche`
--

CREATE TABLE `statistiche` (
  `id` int(11) NOT NULL,
  `utente_id` int(11) NOT NULL,
  `libro_id` int(11) NOT NULL,
  `data_lettura` date NOT NULL,
  `valutazione` int(11) DEFAULT NULL CHECK (`valutazione` >= 1 and `valutazione` <= 5),
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `statistiche`
--

INSERT INTO `statistiche` (`id`, `utente_id`, `libro_id`, `data_lettura`, `valutazione`, `note`) VALUES
(8, 4, 10, '2025-07-08', 4, 'Capolavoro'),
(9, 4, 11, '2025-07-08', 4, 'molto carino'),
(10, 4, 12, '2025-07-08', 3, NULL);

--
-- Trigger `statistiche`
--
DELIMITER $$
CREATE TRIGGER `aggiorna_progresso_after_delete` AFTER DELETE ON `statistiche` FOR EACH ROW BEGIN
    CALL AggiornaProgresso(OLD.utente_id, YEAR(OLD.data_lettura));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `aggiorna_progresso_after_insert` AFTER INSERT ON `statistiche` FOR EACH ROW BEGIN
    CALL AggiornaProgresso(NEW.utente_id, YEAR(NEW.data_lettura));
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `utente`
--

CREATE TABLE `utente` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dump dei dati per la tabella `utente`
--

INSERT INTO `utente` (`id`, `username`, `password`) VALUES
(4, 'user', '$2y$10$0I/aRND8ea7iwo/2TJIbTO6QiZENaRYbChMZPb.ChZEBNFptAFy5K');

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `vista_statistiche_utente`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `vista_statistiche_utente` (
`utente_id` int(11)
,`username` varchar(50)
,`libri_letti_totali` bigint(21)
,`valutazione_media` decimal(14,4)
,`libri_anno_corrente` bigint(21)
,`genere_preferito` varchar(100)
);

-- --------------------------------------------------------

--
-- Struttura per vista `vista_statistiche_utente`
--
DROP TABLE IF EXISTS `vista_statistiche_utente`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_statistiche_utente`  AS SELECT `u`.`id` AS `utente_id`, `u`.`username` AS `username`, count(`s`.`id`) AS `libri_letti_totali`, coalesce(avg(`s`.`valutazione`),0) AS `valutazione_media`, count(case when year(`s`.`data_lettura`) = year(curdate()) then 1 end) AS `libri_anno_corrente`, (select `b`.`genere` from (`libro` `b` join `statistiche` `st` on(`b`.`id` = `st`.`libro_id`)) where `st`.`utente_id` = `u`.`id` and `b`.`genere` is not null group by `b`.`genere` order by count(0) desc limit 1) AS `genere_preferito` FROM ((`utente` `u` left join `statistiche` `s` on(`u`.`id` = `s`.`utente_id`)) left join `libro` `l` on(`s`.`libro_id` = `l`.`id`)) GROUP BY `u`.`id`, `u`.`username` ;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `libro`
--
ALTER TABLE `libro`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_isbn` (`isbn`),
  ADD KEY `idx_libro_isbn` (`isbn`),
  ADD KEY `idx_libro_genere` (`genere`);

--
-- Indici per le tabelle `progresso`
--
ALTER TABLE `progresso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_year` (`utente_id`,`anno`),
  ADD KEY `idx_progresso_utente_anno` (`utente_id`,`anno`);

--
-- Indici per le tabelle `statistiche`
--
ALTER TABLE `statistiche`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_book` (`utente_id`,`libro_id`),
  ADD KEY `libro_id` (`libro_id`),
  ADD KEY `idx_statistiche_utente` (`utente_id`),
  ADD KEY `idx_statistiche_data` (`data_lettura`);

--
-- Indici per le tabelle `utente`
--
ALTER TABLE `utente`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_utente_username` (`username`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `libro`
--
ALTER TABLE `libro`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT per la tabella `progresso`
--
ALTER TABLE `progresso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT per la tabella `statistiche`
--
ALTER TABLE `statistiche`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT per la tabella `utente`
--
ALTER TABLE `utente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `progresso`
--
ALTER TABLE `progresso`
  ADD CONSTRAINT `progresso_ibfk_1` FOREIGN KEY (`utente_id`) REFERENCES `utente` (`id`) ON DELETE CASCADE;

--
-- Limiti per la tabella `statistiche`
--
ALTER TABLE `statistiche`
  ADD CONSTRAINT `statistiche_ibfk_1` FOREIGN KEY (`utente_id`) REFERENCES `utente` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `statistiche_ibfk_2` FOREIGN KEY (`libro_id`) REFERENCES `libro` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
