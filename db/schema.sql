-- Schema as it exists on the live database (taken from a mysqldump of the VM, MariaDB 10.5).
-- Things worth knowing:
--   * notes.NoteText is nullable and stored as utf32; the app talks utf8mb4 and MariaDB converts.
--   * notes.Time is "ON UPDATE current_timestamp()": ANY update of a note row resets its time to now.
--     The app never updates notes. In any manual UPDATE of notes also write "Time = Time"
--     (otherwise old notes jump into the current year).

-- ---------------------------------------------------------------------------
-- A) Fresh database only (testing / new install). Do NOT run on the live DB.
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `UserID` int(11) NOT NULL AUTO_INCREMENT,
  `UserName` varchar(100) NOT NULL,
  PRIMARY KEY (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `notes` (
  `NoteID` int(11) NOT NULL AUTO_INCREMENT,
  `NoteText` varchar(16000) CHARACTER SET utf32 COLLATE utf32_general_ci DEFAULT NULL,
  `Time` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `User_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`NoteID`),
  KEY `User_id` (`User_id`),
  CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`User_id`) REFERENCES `users` (`UserID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------------
-- B) Live database: one OPTIONAL change. Not needed for the app to work.
--    It makes it impossible to create the same name twice (two people signing in
--    with a new name at the same instant). Run a backup first, then the check,
--    which must return no rows (the comparison ignores upper/lower case):
-- ---------------------------------------------------------------------------
-- SELECT UserName, COUNT(*) FROM users GROUP BY UserName HAVING COUNT(*) > 1;
--
-- ALTER TABLE users ADD UNIQUE KEY uq_users_username (UserName);
--
-- Performance indexes are pointless at this size (tens of rows) and notes.User_id is already indexed.
