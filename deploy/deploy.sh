#!/usr/bin/env bash
# One-command deploy, run ON THE VM from the git clone of this repo:
#
#   ./deploy/deploy.sh             pull origin/main, check, deploy, test, roll back if the test fails
#   ./deploy/deploy.sh --dry-run   show what would change; changes nothing
#   ./deploy/deploy.sh --rollback  put the previous deployed version back
#   ./deploy/deploy.sh --force     deploy even if this commit is already deployed
#
# What it does: the web root is REPLACED by a clean copy of the tracked files of origin/main, except the
# files listed in KEEP_FILES (config.php by default), which are carried over untouched. Anything else you
# put into the web root by hand is removed (it stays in the backup). The previous web root is kept in
# BACKUP_DIR so a deploy can always be undone. It does not touch the database.
#
# Settings (environment variables, all optional):
#   WEB_ROOT=/var/www/html   BRANCH=main   BASE_URL=http://localhost   BACKUP_DIR=~/deploy-backups
#   KEEP_FILES="config.php"  KEEP_BACKUPS=5   WEB_GROUP=www-data

# Everything lives in main() and runs via "main; exit": bash reads the whole function before running it,
# so the "git merge" below can safely replace this very file while it is running.
main() {
    set -euo pipefail

    local WEB_ROOT=${WEB_ROOT:-/var/www/html}
    local BRANCH=${BRANCH:-main}
    local BASE_URL=${BASE_URL:-http://localhost}
    local BACKUP_DIR=${BACKUP_DIR:-$HOME/deploy-backups}
    local KEEP_FILES=${KEEP_FILES:-config.php}
    local KEEP_BACKUPS=${KEEP_BACKUPS:-5}
    local WEB_GROUP=${WEB_GROUP:-www-data}
    # Repo files that must not end up in the web root.
    local EXCLUDE="db deploy README.md .gitignore config.example.php"

    local SUDO="sudo"
    [ "$(id -u)" -eq 0 ] && SUDO=""

    local MODE=deploy FORCE=0
    for arg in "$@"; do
        case "$arg" in
            --dry-run)  MODE=dry ;;
            --rollback) MODE=rollback ;;
            --force)    FORCE=1 ;;
            -h|--help)  sed -n '2,12p' "$0"; return 0 ;;
            *) echo "Unknown option: $arg (try --help)" >&2; return 2 ;;
        esac
    done

    log() { printf '%s\n' "$*"; }
    die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

    local REPO
    REPO=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
    [ -d "$WEB_ROOT" ] || die "Web root $WEB_ROOT does not exist."

    exec 9>"${TMPDIR:-/tmp}/santa-deploy.lock"
    flock -n 9 || die "Another deploy is already running."

    mkdir -p "$BACKUP_DIR"; chmod 700 "$BACKUP_DIR"

    # ---- helpers ----------------------------------------------------------------------------

    smoke_test() {   # prints the failing check and returns 1 if the live site is not healthy
        local code body size
        code=$(curl -s -m 15 -o /dev/null -w '%{http_code}' "$BASE_URL/index.php" || true)
        [ "$code" = 200 ] || { log "FAILED: index.php returned $code (expected 200)"; return 1; }
        code=$(curl -s -m 15 -o /dev/null -w '%{http_code}' "$BASE_URL/results.php" || true)
        [ "$code" = 200 ] || { log "FAILED: results.php returned $code (expected 200; database problem?)"; return 1; }
        body=$(curl -s -m 15 "$BASE_URL/api/user.php" || true)
        case "$body" in *'"csrf"'*) ;; *) log "FAILED: api/user.php did not return the expected JSON"; return 1 ;; esac
        # config.php must never be readable: 403/404, or an empty 200 (PHP ran it and printed nothing).
        read -r code size < <(curl -s -m 15 -o /dev/null -w '%{http_code} %{size_download}' "$BASE_URL/config.php" || echo "000 0")
        if [ "$code" = 200 ] && [ "$size" != 0 ]; then log "FAILED: config.php is being served ($size bytes)"; return 1; fi
        return 0
    }

    stamp() { date +%Y%m%d-%H%M%S-%N; }   # unique and sortable: two deploys in one second must not collide

    swap_back() {    # $1 = directory to restore as the web root; the current one is kept as $2
        [ ! -e "$2" ] || die "$2 already exists; refusing to overwrite it."
        $SUDO mv "$WEB_ROOT" "$2"
        $SUDO mv "$1" "$WEB_ROOT"
    }

    # ---- rollback ---------------------------------------------------------------------------

    if [ "$MODE" = rollback ]; then
        local last
        last=$(ls -1d "$BACKUP_DIR"/webroot-* 2>/dev/null | sort | tail -n 1 || true)
        [ -n "$last" ] || die "No backup found in $BACKUP_DIR."
        log "Restoring $last"
        swap_back "$last" "$BACKUP_DIR/replaced-$(stamp)"
        rm -f "$BACKUP_DIR/last-deployed"
        if smoke_test; then log "Rolled back. Site checks pass."; else log "Rolled back, but the site checks FAILED: look at the Apache error log."; return 1; fi
        return 0
    fi

    # ---- fetch and inspect --------------------------------------------------------------------

    cd "$REPO"
    git rev-parse --git-dir >/dev/null 2>&1 || die "$REPO is not a git clone."
    git diff --quiet && git diff --cached --quiet || die "The clone has local changes; commit or discard them first."
    git fetch --quiet origin "$BRANCH" || die "Could not fetch from GitHub (deploy key / network?)."

    local target deployed=""
    target=$(git rev-parse "origin/$BRANCH")
    [ -f "$BACKUP_DIR/last-deployed" ] && deployed=$(cat "$BACKUP_DIR/last-deployed")

    if [ "$deployed" = "$target" ] && [ "$FORCE" = 0 ] && [ "$MODE" = deploy ]; then
        log "Already deployed: ${target:0:7}. Nothing to do (use --force to redeploy)."
        return 0
    fi

    log "Target: origin/$BRANCH at ${target:0:7}"
    if [ -n "$deployed" ] && git cat-file -e "$deployed^{commit}" 2>/dev/null; then
        log "Changes since the last deploy (${deployed:0:7}):"
        git log --oneline "$deployed..$target" | sed 's/^/  /'
    else
        log "Latest commits:"; git log --oneline -n 5 "$target" | sed 's/^/  /'
    fi

    # ---- build the new web root in a temp dir and check it -----------------------------------------

    local TMP NEW_TMP
    TMP=$(mktemp -d); NEW_TMP="$TMP/new"; mkdir "$NEW_TMP"
    # shellcheck disable=SC2064
    trap "rm -rf '$TMP'" EXIT      # double quotes: expand $TMP now, it is local to main()
    git archive "$target" | tar -x -C "$NEW_TMP"
    for x in $EXCLUDE; do rm -rf "${NEW_TMP:?}/$x"; done

    local bad=0 f
    while IFS= read -r -d '' f; do
        php -l "$f" >/dev/null 2>&1 || { log "PHP syntax error in ${f#$NEW_TMP/}"; php -l "$f" >&2 || true; bad=1; }
    done < <(find "$NEW_TMP" -name '*.php' -print0)
    [ "$bad" = 0 ] || die "Syntax errors found. Nothing was changed."

    for f in $KEEP_FILES; do
        $SUDO test -e "$WEB_ROOT/$f" || die "$f is missing from $WEB_ROOT; refusing to deploy without it."
    done

    if [ "$MODE" = dry ]; then
        log ""
        log "Dry run: files that would change in $WEB_ROOT (Only in the web root = would be removed):"
        local keep_args=() k
        for k in $KEEP_FILES; do keep_args+=(--exclude="$k"); done
        $SUDO diff -rq "${keep_args[@]}" "$WEB_ROOT" "$NEW_TMP" | sed 's/^/  /' || true
        log "Dry run finished; nothing was changed."
        return 0
    fi

    # ---- deploy -------------------------------------------------------------------------------------

    git merge --quiet --ff-only "origin/$BRANCH" || die "Could not fast-forward the clone to origin/$BRANCH."

    local STAGED="$WEB_ROOT.new"
    $SUDO rm -rf "$STAGED"
    $SUDO cp -a "$NEW_TMP" "$STAGED"
    $SUDO chown -R root:root "$STAGED"
    $SUDO find "$STAGED" -type d -exec chmod 755 {} +
    $SUDO find "$STAGED" -type f -exec chmod 644 {} +
    for f in $KEEP_FILES; do $SUDO cp -a "$WEB_ROOT/$f" "$STAGED/$f"; done
    [ -e "$STAGED/config.php" ] && $SUDO chown root:"$WEB_GROUP" "$STAGED/config.php" && $SUDO chmod 640 "$STAGED/config.php"

    local TS BK
    TS=$(stamp); BK="$BACKUP_DIR/webroot-$TS"
    [ ! -e "$BK" ] || die "$BK already exists; refusing to overwrite it."
    $SUDO mv "$WEB_ROOT" "$BK"
    if ! $SUDO mv "$STAGED" "$WEB_ROOT"; then
        $SUDO mv "$BK" "$WEB_ROOT"; die "Could not put the new version in place; the old one was restored."
    fi
    log "Deployed ${target:0:7}. Previous version saved in $BK"

    if ! smoke_test; then
        log "Rolling back to the previous version..."
        swap_back "$BK" "$BACKUP_DIR/failed-$TS"
        if smoke_test; then log "Rolled back; the site is working again on the OLD version."
        else log "Rolled back, but the site checks still FAIL: look at the Apache error log."; fi
        die "The new version failed its checks (see above). The failed files are in $BACKUP_DIR/failed-$TS"
    fi

    echo "$target" > "$BACKUP_DIR/last-deployed"
    echo "$(date -Is) $target" >> "$BACKUP_DIR/deploy.log"

    # keep only the newest KEEP_BACKUPS backups
    { ls -1d "$BACKUP_DIR"/webroot-* 2>/dev/null | sort | head -n "-$KEEP_BACKUPS" || true; } | while IFS= read -r old; do
        $SUDO rm -rf -- "$old"
    done

    log "Site checks pass. Undo with: ./deploy/deploy.sh --rollback"
    return 0
}

main "$@"
exit $?
