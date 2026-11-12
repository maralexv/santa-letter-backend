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

This is the procedure that was used on the live VM (Debian 11, Apache, PHP 7.4, MariaDB 10.5, web root
`/var/www/html`, connected through the Google Cloud "SSH" button, so no SSH key exists on the Mac).
Adjust paths if yours differ. Do the steps in order and check each result before continuing.

1. **Back up (on the VM).**
   ```
   sudo mysqldump notes > ~/notes-backup-$(date +%F).sql
   sudo cp -a /var/www/html ~/webroot-backup-$(date +%F)
   ls -lh ~/notes-backup-*.sql        # must be more than a few hundred bytes
   ```
   Also move stray files such as `index.old` out of the web root: Apache serves unknown extensions as plain text.
2. **Copy the new code to a staging folder `~/santa-letter-new` on the VM.**
   - *Browser SSH (no keys).* On the Mac:
     ```
     cd ~/code/santa-letter-backend && git checkout main && git pull
     COPYFILE_DISABLE=1 tar czf ~/Desktop/santa-letter-new.tgz --exclude .git --exclude .DS_Store .
     ```
     In the SSH window click the gear icon, **Upload file**, pick the `.tgz` (it lands in your home
     folder), then on the VM:
     ```
     mkdir -p ~/santa-letter-new && tar xzf ~/santa-letter-new.tgz -C ~/santa-letter-new
     ```
   - *If the Mac has working SSH access:* `rsync -av --exclude .git ./ USER@VM_IP:~/santa-letter-new/`
3. **Create `config.php` (on the VM).** Use the same database user, password and name the old
   `getuser.php` used (`grep -E 'dbuser|dbpass' /var/www/html/getuser.php`; do not paste the output anywhere).
   ```
   cd ~/santa-letter-new && cp config.example.php config.php && nano config.php
   grep -c CHANGE_ME config.php && php -l config.php      # 0, then "No syntax errors"
   ```
4. **Pre-flight test (on the VM).** Runs the new code on the VM's own PHP and database through a
   temporary local server; the live site is not touched and nothing is written.
   ```
   cd ~/santa-letter-new
   for f in $(find . -name '*.php'); do php -l $f; done | grep -v '^No syntax'; echo lint-done
   php -S 127.0.0.1:8081 > /tmp/santa-test.log 2>&1 &
   sleep 1
   curl -s http://127.0.0.1:8081/api/user.php; echo
   curl -s -o /dev/null -w 'results http=%{http_code}\n' http://127.0.0.1:8081/results.php
   kill $!
   ```
   Expected: only `lint-done` before the JSON, `{"user":null,"csrf":"..."}`, and `results http=200`.
   On any problem read `/tmp/santa-test.log` and stop.
5. **Convert old data, once (on the VM).** The old code stored text HTML-encoded (`Tom &amp; Jerry`).
   ```
   cd ~/santa-letter-new
   php db/decode_legacy_notes.php | grep -E 'row\(s\)|Dry run'     # dry run: counts only
   ```
   If it reports rows to change, apply it exactly once, with a fresh backup and a check that no note's date moved:
   ```
   sudo mysqldump notes > ~/notes-backup-before-decode.sql
   sudo mysql notes -N -e "SELECT NoteID, Time FROM notes ORDER BY NoteID" > /tmp/times-before.txt
   php db/decode_legacy_notes.php --apply | grep -E 'row\(s\)|Done|Already'
   sudo mysql notes -N -e "SELECT NoteID, Time FROM notes ORDER BY NoteID" | diff - /tmp/times-before.txt && echo TIMES-UNCHANGED
   ```
   It refuses to run a second time. Do step 6 right after it.
6. **Switch the code (on the VM).** The old endpoints contain the database password, so delete them.
   ```
   sudo rm -f /var/www/html/header.php /var/www/html/getuser.php /var/www/html/fetchnotes.php /var/www/html/recordnewnote.php /var/www/html/removenote.php
   sudo cp -r --no-preserve=ownership ~/santa-letter-new/. /var/www/html/
   sudo rm -rf /var/www/html/db /var/www/html/README.md /var/www/html/.gitignore /var/www/html/config.example.php
   sudo chown -R root:root /var/www/html
   sudo chown root:www-data /var/www/html/config.php
   sudo chmod 640 /var/www/html/config.php
   ls -la /var/www/html
   ```
   The code stays owned by `root` (the web server user cannot rewrite it); only `config.php` is
   readable by the web server group. The app writes nothing into the web root.
7. **Check.**
   ```
   curl -s -o /dev/null -w 'index     %{http_code}\n' http://localhost/index.php
   curl -s -o /dev/null -w 'results   %{http_code}\n' http://localhost/results.php
   curl -s http://localhost/api/user.php; echo
   curl -s -o /dev/null -w 'bootstrap %{http_code}\n' http://localhost/src/bootstrap.php
   curl -s -o /dev/null -w 'config    %{http_code} size=%{size_download}\n' http://localhost/config.php
   curl -s -o /dev/null -w 'old file  %{http_code}\n' http://localhost/getuser.php
   ```
   Expected: `200`, `200`, the JSON, then `bootstrap` and `config` as below, and `404` for the old file.
   - `config` must be `403`, or `200` with `size=0`. Only `200` with a size above 0 is wrong.
   - `bootstrap` is `403`/`404` once the `.htaccess` rules work (next section). A `200` with an empty
     body is harmless but means they are not active yet.

   Then in a browser: sign in with a name, add a wish containing `&` and `é`, reload, delete it, sign
   out, open "See everyone's wishes", and sign in with an old email to see its old list.
8. **Roll back if needed.**
   ```
   sudo rm -rf /var/www/html && sudo cp -a ~/webroot-backup-DATE /var/www/html
   sudo mysql notes < ~/notes-backup-DATE.sql
   ```

Users who signed in with an email before still get their old list by typing that same email. To
move someone to a name instead:
`UPDATE users SET UserName = 'Anna' WHERE UserName = 'anna@example.com';`

## Make the `.htaccess` lock-outs work (Apache, optional)

`src/`, `partials/`, `db/` and `config.php` are blocked by `.htaccess` files, but Debian's Apache ignores
them by default (`AllowOverride None`). Nothing leaks without them (those files print nothing when
opened directly), yet they are the second layer of protection. To switch them on:
```
sudo tee /etc/apache2/conf-available/santa-letter.conf >/dev/null <<'EOF'
<Directory /var/www/html>
    AllowOverride All
</Directory>
EOF
sudo a2enconf santa-letter
sudo apachectl configtest        # must say "Syntax OK"
sudo systemctl reload apache2
```
Then repeat the step 7 `curl` checks: `bootstrap` and `config` should now be `403`, and the site still `200`.
To undo: `sudo a2disconf santa-letter && sudo systemctl reload apache2`.

## Notes for developers

- Every query is a prepared statement; the signed-in user's id comes from the session, never from the client.
- Text is stored as typed and escaped when displayed (`e()` in PHP, `textContent` in JS).
- Mutating API calls need the `X-CSRF-Token` header (handed out by `GET api/user.php`).
- Errors are logged with `error_log`, never shown to the browser.
