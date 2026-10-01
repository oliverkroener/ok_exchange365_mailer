#!/usr/bin/env bash
#
# Lab primitives for the ok_exchange365_mailer cross-version test matrix.
#
# Adapted from the typo3-toolkit "typo3-test-matrix" skill's bin/testlab, with one
# mechanism it does not have: each TYPO3 major may test a *different git branch* of
# the extension, materialised through `git worktree`.
#
# Sourced by runTests.sh; not meant to be executed directly.

# --------------------------------------------------------------------------- paths

lab_dir()     { printf '%s/typo3-%s' "$LAB_ROOT" "$1"; }
lab_project() { printf '%s%s' "$PROJECT_PREFIX" "$1"; }
lab_url()     { printf 'https://%s.ddev.site' "$(lab_project "$1")"; }
worktree_dir() { printf '%s/worktrees/%s' "$LAB_ROOT" "$1"; }

# Absolute host path of the extension source a given major must test.
# `main` is the working tree itself, so edits are live; every other branch is
# materialised as a detached git worktree under the lab root.
lab_source() {
    local major="$1" branch
    branch="$(version_field "$major" branch)"
    if [ "$branch" = "$MAIN_BRANCH" ]; then
        printf '%s' "$REPO_ROOT"
    else
        worktree_dir "$branch"
    fi
}

# ---------------------------------------------------------------------- source prep

# Materialise the branch a major needs. Idempotent: an existing worktree is updated
# to the branch tip rather than recreated.
prepare_source() {
    local major="$1" branch wt
    branch="$(version_field "$major" branch)"
    [ "$branch" = "$MAIN_BRANCH" ] && { log "  source: working tree ($REPO_ROOT)"; return 0; }

    wt="$(worktree_dir "$branch")"
    mkdir -p "$(dirname "$wt")"

    if [ -d "$wt/.git" ] || [ -f "$wt/.git" ]; then
        # Leave an existing worktree exactly as it is. It may carry local commits or
        # uncommitted work on a maintenance branch, and silently resetting it to the
        # remote would discard that - the matrix must test what is there, not what
        # was last pushed.
        local head
        head="$(git -C "$wt" rev-parse --abbrev-ref HEAD 2>/dev/null || echo detached)"
        log "  source: existing worktree $wt (on $head)"
        if [ -n "$(git -C "$wt" status --porcelain 2>/dev/null)" ]; then
            log "          (uncommitted changes present - testing them as-is)"
        fi
    else
        log "  source: creating worktree $wt for $branch"
        git -C "$REPO_ROOT" worktree prune
        # Track the local branch if it exists, otherwise create it from the remote.
        if git -C "$REPO_ROOT" show-ref --verify --quiet "refs/heads/$branch"; then
            git -C "$REPO_ROOT" worktree add "$wt" "$branch" >/dev/null 2>&1 || return 1
        else
            git -C "$REPO_ROOT" worktree add -B "$branch" "$wt" "origin/$branch" >/dev/null 2>&1 || return 1
        fi
    fi
}

# ----------------------------------------------------------------------- provisioning

lab_exists() { ddev describe "$(lab_project "$1")" >/dev/null 2>&1; }

# Write the read-only bind mount that exposes the extension source inside the
# container, OUTSIDE the docroot. Must exist before `ddev start`.
write_extsrc_mount() {
    local major="$1" dir src
    dir="$(lab_dir "$major")"
    src="$(lab_source "$major")"
    mkdir -p "$dir/.ddev"
    cat > "$dir/.ddev/docker-compose.extsrc.yaml" <<YAML
services:
  web:
    volumes:
      - "${src}:/var/www/ext-src:ro"
      - "${REPO_ROOT}/Build/testing:/var/www/matrix:ro"
YAML
}

provision_lab() {
    local major="$1" dir project php db dist
    dir="$(lab_dir "$major")"
    project="$(lab_project "$major")"
    php="$(version_field "$major" php)"
    db="$(version_field "$major" db)"
    dist="$(version_field "$major" distribution)"

    mkdir -p "$dir"

    if [ -f "$dir/composer.json" ] && lab_exists "$major"; then
        log "  lab exists, reusing"
        write_extsrc_mount "$major"
        ( cd "$dir" && ddev start ) >>"$RUN_LOG" 2>&1 || return 1
        return 0
    fi

    ( cd "$dir" && ddev config --auto \
        --project-name="$project" \
        --project-type=typo3 \
        --docroot=public \
        --php-version="$php" \
        --database="$db" ) >>"$RUN_LOG" 2>&1 || return 1

    write_extsrc_mount "$major"

    ( cd "$dir" && ddev start ) >>"$RUN_LOG" 2>&1 || return 1
    # Create without installing first: the older distributions pull in Composer
    # plugins (e.g. helhum/typo3-console-plugin on TYPO3 9) that the current
    # Composer blocks until they are allowed - and, with a terminal attached, it
    # would wait for a "trust this plugin?" answer forever. stdin is closed on
    # every ddev call here for the same reason.
    ( cd "$dir" && ddev composer create-project "$dist" --no-interaction --no-progress --no-install ) \
        >>"$RUN_LOG" 2>&1 </dev/null || return 1
    local plugin
    for plugin in typo3/class-alias-loader typo3/cms-composer-installers \
                  helhum/typo3-console-plugin helhum/dotenv-connector phpstan/extension-installer; do
        ( cd "$dir" && ddev composer config --no-plugins "allow-plugins.$plugin" true ) \
            >>"$RUN_LOG" 2>&1 </dev/null || return 1
    done
    # ELTS majors: every freely published patch carries advisories, so the first
    # install must already run with the audit block lifted (see wire_extension).
    ( cd "$dir" && ddev composer config --no-plugins policy.advisories.block false ) \
        >>"$RUN_LOG" 2>&1 </dev/null || true
    ( cd "$dir" && ddev composer config platform.php "$php" ) >>"$RUN_LOG" 2>&1 </dev/null || true
    ( cd "$dir" && ddev composer install --no-interaction --no-progress ) \
        >>"$RUN_LOG" 2>&1 </dev/null || return 1
}

# ---------------------------------------------------------------------------- wiring

wire_extension() {
    local major="$1" dir
    dir="$(lab_dir "$major")"

    ( cd "$dir" && ddev composer config repositories.ext-under-test \
        '{"type":"path","url":"/var/www/ext-src","options":{"symlink":true}}' ) \
        >>"$RUN_LOG" 2>&1 || return 1
    # The base distributions ship a config.platform.php pin that can be older than
    # the lab actually runs (the TYPO3 10 skeleton pins 7.2 while the lab is 7.4),
    # which makes Composer reject packages the lab could happily run.
    ( cd "$dir" && ddev composer config platform.php "$(version_field "$major" php)" ) \
        >>"$RUN_LOG" 2>&1 || true
    ( cd "$dir" && ddev composer config minimum-stability dev ) >>"$RUN_LOG" 2>&1
    # TYPO3 11 and 12 are ELTS: every freely published patch carries security
    # advisories, so Composer's audit refuses to load ANY of them and the major
    # cannot be tested at all. Overriding this is safe for a throwaway lab - and
    # the report's environment header states plainly that a lab is not a
    # security-current installation.
    ( cd "$dir" && ddev composer config --no-plugins policy.advisories.block false ) \
        >>"$RUN_LOG" 2>&1 || true
    ( cd "$dir" && ddev composer config --no-plugins allow-plugins.phpstan/extension-installer true ) \
        >>"$RUN_LOG" 2>&1
    ( cd "$dir" && ddev composer config prefer-stable true ) >>"$RUN_LOG" 2>&1
    ( cd "$dir" && ddev composer require "${PACKAGE}:@dev" --with-all-dependencies --no-interaction --no-progress ) \
        >>"$RUN_LOG" 2>&1 || return 1

    # The blinding listener targets an event that ships with typo3/cms-lowlevel.
    # The extension only suggests that package, so the base distribution may not
    # carry it - without it PHPStan cannot resolve the event class and the
    # functional blinding test has nothing to dispatch.
    ( cd "$dir" && ddev composer require typo3/cms-lowlevel --no-interaction --no-progress ) \
        >>"$RUN_LOG" 2>&1 || true
}

# The single most important check in the harness. If the lab is testing a copy
# fetched from Packagist instead of the mounted source, every later result is a lie.
canary() {
    local major="$1" dir src token file rc=0
    dir="$(lab_dir "$major")"
    src="$(lab_source "$major")"
    token=".testlab-canary-$$-$RANDOM"
    file="$src/$token"

    printf 'canary' > "$file" 2>/dev/null || return 1
    ( cd "$dir" && ddev exec test -f "/var/www/ext-src/$token" ) >/dev/null 2>&1 || rc=1
    rm -f "$file"
    [ "$rc" -ne 0 ] && return 1

    # ...and the INSTALLED extension must be a symlink into that mount rather than a
    # real directory copied from Packagist. Where that symlink lives depends on the
    # major: TYPO3 12+ keeps it at vendor/<vendor>/<package>, while TYPO3 10 and 11
    # use the classic layout and materialise it at public/typo3conf/ext/<key>.
    if ( cd "$dir" && ddev exec test -L "vendor/${PACKAGE}" ) >/dev/null 2>&1; then
        return 0
    fi
    if ( cd "$dir" && ddev exec test -L "public/typo3conf/ext/${EXT_KEY}" ) >/dev/null 2>&1; then
        return 0
    fi
    return 1
}

# Has this lab already been installed? `typo3 setup` and `install:setup` both refuse
# to run twice, so provisioning has to be able to tell.
is_installed() {
    local major="$1" dir count
    dir="$(lab_dir "$major")"
    # Ask the database directly. DDEV injects the credentials through the
    # environment rather than settings.php, so inspecting config files is
    # unreliable - and "the database already has tables" is the exact condition
    # `typo3 setup` refuses on.
    count=$( ( cd "$dir" && ddev mysql -N -B \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='db'" ) \
        2>/dev/null | tr -dc '0-9' )
    [ -n "$count" ] && [ "$count" -gt 0 ] 2>/dev/null
}

install_typo3() {
    local major="$1" dir project url installer
    dir="$(lab_dir "$major")"
    project="$(lab_project "$major")"
    url="$(lab_url "$major")"
    installer="$(version_field "$major" installer)"

    # Idempotency: `typo3 setup` refuses to run against a database that already has
    # tables, so a warm lab must skip straight to the refresh below rather than
    # failing the whole major on a re-run.
    if is_installed "$major"; then
        log "  already installed, skipping setup"
    elif [ "$installer" = "setup" ]; then
        # NOTE: --create-site must use the "=" form. It is VALUE_OPTIONAL on 12.4
        # but VALUE_REQUIRED on 13.4/14.3, so the space form breaks on the newer cores.
        ( cd "$dir" && ddev exec ./vendor/bin/typo3 setup --no-interaction --force \
            --server-type=other --driver=mysqli \
            --host=db --port=3306 --dbname=db --username=db --password=db \
            --admin-username="$ADMIN_USER" \
            --admin-user-password="$ADMIN_PASSWORD" \
            --admin-email="$ADMIN_EMAIL" \
            --project-name="Testlab ${EXT_KEY} v${major}" \
            --create-site="${url}/" ) >>"$RUN_LOG" 2>&1 || return 1
    else
        # TYPO3 10/11 have no `setup` command; drive them through TYPO3 Console.
        ( cd "$dir" && ddev composer require helhum/typo3-console --no-interaction --no-progress ) \
            >>"$RUN_LOG" 2>&1 || return 1
        # TYPO3 Console 5 (TYPO3 9) has no --site-base-url; its site gets base "/",
        # which answers on any host.
        local base_url_option="--site-base-url=${url}/"
        [ "$major" -lt 10 ] 2>/dev/null && base_url_option=""
        ( cd "$dir" && ddev exec ./vendor/bin/typo3cms install:setup --no-interaction \
            --database-driver=mysqli \
            --database-host-name=db --database-port=3306 \
            --database-name=db --database-user-name=db --database-user-password=db \
            --admin-user-name="$ADMIN_USER" \
            --admin-password="$ADMIN_PASSWORD" \
            --site-name="Testlab ${EXT_KEY} v${major}" \
            --site-setup-type=site $base_url_option \
            --web-server-config=none ) >>"$RUN_LOG" 2>&1 || return 1
    fi

    write_additional_config "$major"
    ( cd "$dir" && ddev exec ./vendor/bin/typo3 extension:setup ) >>"$RUN_LOG" 2>&1 \
        || ( cd "$dir" && ddev exec ./vendor/bin/typo3cms extension:setupactive ) >>"$RUN_LOG" 2>&1 \
        || true
    ( cd "$dir" && ddev exec ./vendor/bin/typo3 cache:flush ) >>"$RUN_LOG" 2>&1 \
        || ( cd "$dir" && ddev exec ./vendor/bin/typo3cms cache:flush ) >>"$RUN_LOG" 2>&1 \
        || true
}

# Surface errors, but keep deprecations LOGGED rather than thrown - throwing them
# turns every core-internal deprecation into a fake failure.
write_additional_config() {
    local major="$1" dir target
    dir="$(lab_dir "$major")"
    if [ "$major" -ge 12 ] 2>/dev/null; then
        target="$dir/config/system/additional.php"
    else
        target="$dir/public/typo3conf/AdditionalConfiguration.php"
    fi
    mkdir -p "$(dirname "$target")"
    cat > "$target" <<'PHPEOF'
<?php

defined('TYPO3') || defined('TYPO3_MODE') || die();

// This file replaces the one DDEV generates for TYPO3 projects, so it has to
// carry DDEV's database credentials. Without them only the CLI works and every
// web request fails with a connection error.
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'] = array_replace(
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'] ?? [],
    [
        'dbname' => 'db',
        'host' => 'db',
        'port' => 3306,
        'user' => 'db',
        'password' => 'db',
        'driver' => 'mysqli',
    ]
);

$GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = true;
$GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = true;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = 1;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '*';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors']
    = E_ALL & ~E_NOTICE & ~E_WARNING & ~E_USER_DEPRECATED & ~E_DEPRECATED;

// The offline layers must never reach the network.
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = 'null';

// The frontend live check passes its run token as query parameters.
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = array_merge(
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] ?? [],
    ['ex365token', 'ex365mode', 'ex365run']
);

// Opt-in live send: runTests.sh --live writes credentials next to this file.
$liveConfig = __DIR__ . '/exchange365-live.php';
if (is_file($liveConfig)) {
    require $liveConfig;
}
PHPEOF
}

# ------------------------------------------------------------------------- teardown

destroy_lab() {
    local major="$1" dir project
    dir="$(lab_dir "$major")"
    project="$(lab_project "$major")"
    # Delete by explicit project name only. Never `ddev poweroff` / `ddev delete --all`:
    # both reach far outside this harness and would hit the user's real projects.
    if lab_exists "$major"; then
        ddev delete -Oy "$project" >/dev/null 2>&1 || true
    fi
    rm -rf "$dir"
}

stop_lab() {
    local project
    project="$(lab_project "$1")"
    lab_exists "$1" && ddev stop "$project" >/dev/null 2>&1 || true
}

# ------------------------------------------------------------------------ live send

live_fragment_path() {
    if [ "$1" -ge 12 ] 2>/dev/null; then
        printf '%s/config/system/exchange365-live.php' "$(lab_dir "$1")"
    else
        printf '%s/public/typo3conf/exchange365-live.php' "$(lab_dir "$1")"
    fi
}

# Switch the lab to the Exchange 365 transport. With "full", the credentials are
# set in TYPO3_CONF_VARS (the backend/CLI path); with "transport-only" they are
# not, so the frontend can only get them from := getEnv() TypoScript.
# The values are read from the container environment at runtime - no secret is
# ever written into a PHP file.
write_live_fragment() {
    local major="$1" mode="$2" target
    target="$(live_fragment_path "$major")"
    mkdir -p "$(dirname "$target")"
    {
        echo '<?php'
        echo "\$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = 'OliverKroener\\OkExchange365\\Mail\\Transport\\Exchange365Transport';"
        echo "\$GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = (string)getenv('EXCHANGE365_FROM_EMAIL');"
        if [ "$mode" = "full" ]; then
            for pair in tenantId:TENANT_ID clientId:CLIENT_ID clientSecret:CLIENT_SECRET \
                        fromEmail:FROM_EMAIL graphSenderUserId:GRAPH_SENDER_USER_ID; do
                echo "\$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_${pair%%:*}'] = (string)getenv('EXCHANGE365_${pair#*:}');"
            done
            echo "\$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_exchange365_saveToSentItems'] = '0';"
        fi
    } > "$target"
}

lab_cache_flush() {
    ( cd "$(lab_dir "$1")" && { ddev exec ./vendor/bin/typo3 cache:flush || ddev exec ./vendor/bin/typo3cms cache:flush; } ) \
        >>"$RUN_LOG" 2>&1 || true
}

# Put the test credentials into the web container's real process environment,
# where PHP's getenv() - and with it TypoScript's getEnv() - can see them.
write_live_env() {
    local major="$1" file
    file="$(lab_dir "$major")/.ddev/.env.web"
    ( umask 077
      {
          for key in EXCHANGE365_TENANT_ID EXCHANGE365_CLIENT_ID EXCHANGE365_CLIENT_SECRET \
                     EXCHANGE365_FROM_EMAIL EXCHANGE365_GRAPH_SENDER_USER_ID EXCHANGE365_TEST_RECIPIENT; do
              printf "%s='%s'\n" "$key" "${!key:-}"
          done
          printf 'EX365_MATRIX_TOKEN="%s"\n' "$LIVE_TOKEN"
          printf 'EX365_MATRIX_RUN="%s"\n' "$RUN_ID"
          printf 'EX365_MATRIX_MAJOR="%s"\n' "$major"
      } > "$file" )
}

remove_live_state() {
    local major="$1"
    rm -f "$(live_fragment_path "$major")" "$(lab_dir "$major")/.ddev/.env.web"
}

# The frontend fixture class must be autoloadable by the lab, from the mount.
wire_fixture() {
    local major="$1" dir
    dir="$(lab_dir "$major")"
    php -r '
        $f = $argv[1] . "/composer.json";
        $j = json_decode(file_get_contents($f), true);
        $j["autoload"]["psr-4"]["OliverKroener\\OkExchange365\\TestFixture\\"] = "/var/www/matrix/Fixture/";
        file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    ' "$dir" || return 1
    ( cd "$dir" && ddev composer dump-autoload ) >>"$RUN_LOG" 2>&1 || return 1
    ( cd "$dir" && ddev exec php /var/www/matrix/install-fixture.php ) >>"$RUN_LOG" 2>&1 || return 1
}

# GET the lab frontend from inside the container and print the fixture marker.
frontend_marker() {
    local major="$1" mode="$2" url
    url="$(lab_url "$major")/?ex365token=${LIVE_TOKEN}&ex365run=${RUN_ID}&ex365mode=${mode}"
    # `ddev exec` hands its arguments to `bash -c`, so the URL must be quoted for
    # that inner shell too - otherwise every "&" ends the command there.
    ( cd "$(lab_dir "$major")" && ddev exec "curl -sk --max-time 60 '$url'" ) 2>>"$RUN_LOG" \
        | grep -oE 'EX365-(OK|FAIL|NOOP)[^<]*' | head -1 || true
}

# Opt-in only (--live). Sends real messages through Microsoft Graph:
#   live-cli        credentials from TYPO3_CONF_VARS, CLI context
#   browser         backend login; the secret is masked in System > Configuration
#   live-frontend   credentials ONLY from := getEnv() TypoScript, frontend context
#   getenv-missing  an unset getEnv() variable fails cleanly with a TransportException-style error
live_checks() {
    local major="$1" dir out marker
    dir="$(lab_dir "$major")"

    # shellcheck disable=SC1090
    set -a; . "$LIVE_ENV"; set +a
    : "${EXCHANGE365_GRAPH_SENDER_USER_ID:=}"
    : "${EXCHANGE365_TEST_RECIPIENT:=$EXCHANGE365_FROM_EMAIL}"
    LIVE_TOKEN="$(php -r 'echo bin2hex(random_bytes(16));')"

    write_live_env "$major"
    ( cd "$dir" && ddev restart ) >>"$RUN_LOG" 2>&1 || { record "$major" live-env fail "ddev restart failed"; MATRIX_FAILED=1; remove_live_state "$major"; return 0; }

    # Every stalled Graph call seen so far was the first one after `ddev restart`.
    # Wait until both Microsoft endpoints answer before the first real send.
    local tries=0
    until ( cd "$dir" && ddev exec "curl -s -o /dev/null --max-time 5 https://login.microsoftonline.com/common/v2.0/.well-known/openid-configuration && curl -s -o /dev/null --max-time 5 https://graph.microsoft.com/v1.0/" ) >/dev/null 2>&1; do
        tries=$((tries + 1))
        [ "$tries" -ge 12 ] && { log "  network warm-up did not succeed - continuing anyway"; break; }
        sleep 5
    done

    if ! wire_fixture "$major"; then
        record "$major" live-fixture fail "see run.log"; MATRIX_FAILED=1; remove_live_state "$major"; return 0
    fi

    # 1. CLI context, credentials from TYPO3_CONF_VARS
    write_live_fragment "$major" full
    lab_cache_flush "$major"
    out="$REPORT_DIR/v${major}-live-cli.log"
    # A live send is tried at most twice. The lab network (Docker on WSL2 in
    # particular) occasionally stalls a connection; a pass on the second attempt
    # is reported as such, never as a plain pass.
    if ( cd "$dir" && ddev exec "timeout 180 php /var/www/matrix/live-send.php" ) >"$out" 2>&1; then
        record "$major" live-cli pass "real mail accepted by Graph"
    else
        first="$(grep -o 'Error: .*' "$out" | head -1 | cut -c1-90)"
        sleep 5
        if ( cd "$dir" && ddev exec "timeout 180 php /var/www/matrix/live-send.php" ) >>"$out" 2>&1; then
            record "$major" live-cli pass "accepted on 2nd attempt (1st: ${first:-see log})"
        else
            record "$major" live-cli fail "$(grep -o 'FAILED: .*' "$out" | tail -1 | cut -c1-160)"; MATRIX_FAILED=1
        fi
    fi

    # 2. Backend: the secret is masked in System > Configuration
    out="$REPORT_DIR/v${major}-browser.log"
    if LAB_URL="$(lab_url "$major")" ADMIN_USER="$ADMIN_USER" ADMIN_PASSWORD="$ADMIN_PASSWORD" \
        CHECK_SECRET="$EXCHANGE365_CLIENT_SECRET" OUT_DIR="$REPORT_DIR" MAJOR="$major" \
        node "$REPO_ROOT/Build/testing/browser/backend-check.mjs" >"$out" 2>&1; then
        record "$major" browser pass "secret masked - v${major}-config-module.png"
    else
        record "$major" browser fail "$(grep FAIL "$out" | head -1 | cut -c1-160)"; MATRIX_FAILED=1
    fi

    # 3. Frontend context, credentials only from := getEnv()
    write_live_fragment "$major" transport-only
    lab_cache_flush "$major"
    marker="$(frontend_marker "$major" send)"
    printf '%s\n' "$marker" > "$REPORT_DIR/v${major}-live-frontend.log"
    local retried=""
    if [[ "$marker" != EX365-OK* && "$marker" == *"cURL error"* ]]; then
        retried=" (accepted on 2nd attempt, 1st: $(printf '%s' "$marker" | grep -o 'cURL error [0-9]*'))"
        sleep 5
        marker="$(frontend_marker "$major" send)"
        printf '%s\n' "$marker" >> "$REPORT_DIR/v${major}-live-frontend.log"
    fi
    if [[ "$marker" == EX365-OK* ]]; then
        record "$major" live-frontend pass "getEnv() TypoScript only - real mail accepted${retried}"
    else
        record "$major" live-frontend fail "${marker:-no fixture output} " ; MATRIX_FAILED=1
    fi

    # 4. Unset getEnv() variable: clean failure naming the missing field
    marker="$(frontend_marker "$major" missing)"
    printf '%s\n' "$marker" > "$REPORT_DIR/v${major}-getenv-missing.log"
    if [[ "$marker" == EX365-FAIL* && "$marker" == *clientSecret* ]]; then
        record "$major" getenv-missing pass "fails cleanly, names clientSecret"
    else
        record "$major" getenv-missing fail "${marker:-no fixture output}"; MATRIX_FAILED=1
    fi

    remove_live_state "$major"
    lab_cache_flush "$major"
}

# --------------------------------------------------------------- test dependencies

# Composer does NOT install a path package's require-dev, nor register its
# autoload-dev. Both have to be added to the lab root, with the per-major pins from
# Build/matrix.json - an unpinned testing-framework would drag in a PHPUnit major
# whose metadata dialect the committed tests do not use.
install_test_deps() {
    local major="$1" dir tf pu
    dir="$(lab_dir "$major")"
    tf="$(version_field "$major" testingFramework)"
    pu="$(version_field "$major" phpunit)"

    ( cd "$dir" && ddev composer require --dev --no-interaction --no-progress --no-update \
        "typo3/testing-framework:${tf}" "phpunit/phpunit:${pu}" ) >>"$RUN_LOG" 2>&1 || return 1
    ( cd "$dir" && ddev composer update --no-interaction --no-progress \
        typo3/testing-framework phpunit/phpunit ) >>"$RUN_LOG" 2>&1 || return 1

    # Where the extension actually lands depends on the major: TYPO3 12+ keeps it at
    # vendor/<vendor>/<package>, while TYPO3 10 and 11 use the classic layout and
    # symlink it into public/typo3conf/ext/<key>. Point the test namespace at
    # whichever one exists, or the base test case is simply not autoloadable.
    # NOTE: test with -L, not -d. The classic-layout symlink points at the CONTAINER
    # path /var/www/ext-src, which does not resolve on the host - so -d is false even
    # though the link is correct, and the mapping would silently fall back to the
    # vendor path that does not exist on these majors.
    local testsPath="vendor/${PACKAGE}/Tests/"
    if [ -L "$dir/public/typo3conf/ext/${EXT_KEY}" ] || [ -d "$dir/public/typo3conf/ext/${EXT_KEY}" ]; then
        testsPath="public/typo3conf/ext/${EXT_KEY}/Tests/"
    fi

    # `composer config` cannot set autoload-dev.psr-4, so patch composer.json directly.
    php -r '
        $f = $argv[1] . "/composer.json";
        $j = json_decode(file_get_contents($f), true);
        $j["autoload-dev"]["psr-4"]["OliverKroener\\OkExchange365\\Tests\\"] = $argv[2];
        file_put_contents($f, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    ' "$dir" "$testsPath" || return 1

    ( cd "$dir" && ddev composer dump-autoload ) >>"$RUN_LOG" 2>&1 || return 1
}
