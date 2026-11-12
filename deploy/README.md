# One-command deploy

Run on the VM, from a git clone of this repo:

```
cd ~/santa-letter-backend
./deploy/deploy.sh
```

It pulls `main` from GitHub, checks the PHP, replaces the web root, tests the live site and **rolls back
by itself** if the test fails. It never touches the database.

| Command | What it does |
|---|---|
| `./deploy/deploy.sh` | Deploy the latest `main` (does nothing if it is already deployed) |
| `./deploy/deploy.sh --dry-run` | Show what would change; changes nothing |
| `./deploy/deploy.sh --rollback` | Put the previous version back |
| `./deploy/deploy.sh --force` | Redeploy even if this commit is already live |

## What happens, in order

1. Fetches `origin/main`. Refuses if the clone has local changes.
2. Builds the new web root from the tracked files of `origin/main` in a temp folder and runs `php -l` on every
   PHP file. **A syntax error stops here; nothing has been changed.**
3. Prepares the new folder: root-owned, dirs `755`, files `644`, and carries over `config.php` unchanged
   (`root:www-data`, `640`). Refuses to continue if `config.php` is missing from the web root.
4. Swaps folders: the old web root is moved to `~/deploy-backups/webroot-<timestamp>`, the new one takes its place.
5. Tests the live site: `index.php` and `results.php` return 200 (so the database connection works),
   `api/user.php` returns JSON, and `config.php` is not readable. **If any test fails, the old version is put
   back automatically**; the failed files are kept in `~/deploy-backups/failed-<timestamp>` and the script exits with an error.
6. Keeps the newest 5 backups; records the deployed commit in `~/deploy-backups/last-deployed` and `deploy.log`.

## Good to know

- **The deploy owns the web root.** Everything in `/var/www/html` except `config.php` is replaced by the repo's
  files. Files you add by hand are removed (they stay in that deploy's backup). Use `--dry-run` to see them
  listed as `Only in /var/www/html`. To keep more files across deploys: `KEEP_FILES="config.php other.txt"`.
- `db/`, `deploy/`, `README.md`, `.gitignore` and `config.example.php` are **not** copied into the web root.
- **Database changes are never automatic.** If a change needs a schema change or a data conversion, do that by hand first.
- Everything merged to `main` is what gets deployed. Review pull requests before merging.
- Settings (environment variables, all optional): `WEB_ROOT` (default `/var/www/html`), `BRANCH` (`main`),
  `BASE_URL` (`http://localhost`), `BACKUP_DIR` (`~/deploy-backups`), `KEEP_FILES`, `KEEP_BACKUPS` (`5`), `WEB_GROUP` (`www-data`).
- The script uses `sudo` for the web root, so the user running it needs sudo rights.

## One-time setup on the VM

The repo is private, so the VM needs its own **read-only deploy key** for this one repository. Do not put a
personal access token on the VM.

1. Create the key (no passphrase, so deploys can run unattended):
   ```
   ssh-keygen -t ed25519 -C "santa-vm-deploy" -f ~/.ssh/santa_deploy -N ""
   cat ~/.ssh/santa_deploy.pub
   ```
2. On GitHub: the repo, **Settings, Deploy keys, Add deploy key**. Paste the line printed above. Leave
   **Allow write access** unticked.
3. Tell SSH to use that key for GitHub:
   ```
   cat >> ~/.ssh/config <<'EOF'
   Host github-santa
       HostName github.com
       User git
       IdentityFile ~/.ssh/santa_deploy
       IdentitiesOnly yes
   EOF
   chmod 600 ~/.ssh/config
   ssh -T git@github-santa
   ```
   Type `yes` when asked about the host. Expected: `Hi maralexv/santa-letter-backend! You've successfully authenticated...`
4. Clone the repo (needs `git`; install with `sudo apt-get install -y git` if missing):
   ```
   git clone git@github-santa:maralexv/santa-letter-backend.git ~/santa-letter-backend
   ```
5. Preview, then deploy:
   ```
   cd ~/santa-letter-backend
   ./deploy/deploy.sh --dry-run
   ./deploy/deploy.sh
   ```

## Troubleshooting

- `Could not fetch from GitHub`: the deploy key is missing or not added to the repo; run `ssh -T git@github-santa`.
- `PHP syntax error in ...`: fix it on GitHub; nothing was changed on the server.
- `... returned 500` followed by "Rolled back": the new version crashed or cannot reach the database. Read
  `sudo tail -20 /var/log/apache2/error.log` and the files in `~/deploy-backups/failed-<timestamp>`.
- `Rolled back, but the site checks still FAIL`: the problem is not the new code (database or Apache is down).
- `The clone has local changes`: `cd ~/santa-letter-backend && git status`, then `git checkout -- .` to discard them.
