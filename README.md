# santa-letter-backend

Family Christmas wish list. People type their name, add wishes for the current year, delete them,
and everyone can see all wishes on one page. Plain PHP (7.4+, developed on 8.3) and MariaDB/MySQL,
no framework.

```
index.php            wish list page (JS app)
results.php          everyone's wishes this year (names containing "@" are masked: anna@x.com -> anna***)
api/user.php         GET who am I / POST sign in with a name / DELETE sign out
api/notes.php        GET my wishes / POST add / DELETE remove (always the signed-in user's own)
assets/              style.css, app.js
partials/head.php    shared <head>
src/bootstrap.php    config, DB connection, session + CSRF, validation helpers
db/schema.sql        the live table definitions, plus one optional UNIQUE index
db/decode_legacy_notes.php   one-off migration from the old code, see "Deploy"
config.example.php   copy to config.php (git-ignored) and fill in
```

Signing in is by name only, with no password or verification. Anyone who types a name sees and
edits that name's list. That is fine for a family; it is not real security.

## Run locally

```
mariadb -e "CREATE DATABASE notes CHARACTER SET utf8mb4"
mariadb notes < db/schema.sql          # section A of the file
cp config.example.php config.php        # then edit user/pass
php -S 127.0.0.1:8080
```

## Config

`config.php` (or any file named by the `SANTA_CONFIG` environment variable, best kept outside the web
root) returns `db` settings and `limits` (max name/note length; keep them within your column sizes).

**Character set.** The old code never set a connection character set. The new code uses
`'charset' => 'utf8mb4'`. If wishes with accents or emoji (`é`, `🎁`) look garbled after deploying,
set `'charset' => 'latin1'` in `config.php` and reload. Plain English text is unaffected either way.

## Deploy to the VM

These steps assume Apache serving `/var/www/html`, MariaDB reachable with `sudo mysql`, and that you
copy from your Mac. Adjust paths if yours differ. Do them in order.

1. **Back up (on the VM).**
   ```
   sudo mysqldump notes > ~/notes-backup-$(date +%F).sql
   sudo cp -a /var/www/html ~/webroot-backup-$(date +%F)
   ```
2. **Copy the new code to a staging folder (on the Mac).**
   ```
   cd ~/code/santa-letter-backend && git checkout main && git pull
   rsync -av --exclude .git ./ USER@VM_IP:~/santa-letter-new/
   ```
3. **Create `config.php` (on the VM).** Use the same database user, password and name the old
   `getuser.php` used.
   ```
   cd ~/santa-letter-new && cp config.example.php config.php && nano config.php
   ```
4. **Convert old data, once (on the VM).** The old code stored text HTML-encoded (`Tom &amp; Jerry`).
   ```
   php db/decode_legacy_notes.php            # dry run: lists what would change
   php db/decode_legacy_notes.php --apply    # do it, exactly once
   ```
   It refuses to run a second time. Do this step right before step 5, not days earlier.
5. **Switch the code (on the VM).** The old endpoints contain the database password, so delete them.
   Also move any stray backup files (such as `index.old`) out of the web root: Apache serves them as plain text.
   ```
   cd /var/www/html && sudo rm -f header.php getuser.php fetchnotes.php recordnewnote.php removenote.php
   sudo rsync -a --chown=root:root --exclude db --exclude .git ~/santa-letter-new/ /var/www/html/
   sudo chown root:www-data /var/www/html/config.php && sudo chmod 640 /var/www/html/config.php
   ```
   The code stays owned by `root` (the web server user cannot rewrite it); only `config.php` is
   readable by the web server group. The app writes nothing into the web root.
6. **Optional unique-name index.** Section B of `db/schema.sql` (run its duplicate check first). The app works without it.
7. **Check.**
   - Open the site, type a name, add a wish containing `&` and `é`, reload, delete it, open "See everyone's wishes".
   - `curl -s -o /dev/null -w '%{http_code}\n' http://VM_IP/src/bootstrap.php` must print `403` or `404`.
     If it prints `200`, Apache is ignoring `.htaccess`; move `config.php` outside the web root
     (set `SANTA_CONFIG`) and block `src/`, `db/`, `partials/` in the server config.
   - `curl -s http://VM_IP/config.php | wc -c` must print `0`.
8. **Roll back if needed.**
   ```
   sudo rm -rf /var/www/html && sudo cp -a ~/webroot-backup-DATE /var/www/html
   sudo mysql notes < ~/notes-backup-DATE.sql
   ```

Users who signed in with an email before still get their old list by typing that same email. To
move someone to a name instead:
`UPDATE users SET UserName = 'Anna' WHERE UserName = 'anna@example.com';`



## Notes for developers

- Every query is a prepared statement; the signed-in user's id comes from the session, never from the client.
- Text is stored as typed and escaped when displayed (`e()` in PHP, `textContent` in JS).
- Mutating API calls need the `X-CSRF-Token` header (handed out by `GET api/user.php`).
- Errors are logged with `error_log`, never shown to the browser.
