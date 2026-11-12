-- DRAFT. Reconstructed from the PHP code (table and column names are real; types are guesses).
-- Compare with the live database before using anything below:
--   SHOW CREATE TABLE users\G
--   SHOW CREATE TABLE notes\G

-- ---------------------------------------------------------------------------
-- A) Fresh database only (testing / new install). Do NOT run on the live DB.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    UserID   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    UserName VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_users_username (UserName)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notes (
    NoteID   INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    NoteText TEXT NOT NULL,
    Time     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    User_id  INT UNSIGNED NOT NULL,
    KEY idx_notes_user_time (User_id, Time),
    KEY idx_notes_time (Time)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- B) Existing database: optional speed and safety indexes. Run after a backup.
--    The app works without them; they only make lookups faster and stop
--    duplicate user names. Run the check first: it must return no rows.
-- ---------------------------------------------------------------------------
-- SELECT UserName, COUNT(*) FROM users GROUP BY UserName HAVING COUNT(*) > 1;
--
-- ALTER TABLE users ADD UNIQUE KEY uq_users_username (UserName);
-- ALTER TABLE notes ADD KEY idx_notes_user_time (User_id, Time);
-- ALTER TABLE notes ADD KEY idx_notes_time (Time);
--
-- If an index name already exists, SHOW INDEX FROM notes; and skip that line.
