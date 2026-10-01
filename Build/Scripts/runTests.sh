#!/usr/bin/env bash
#
# Cross-version test matrix for ok_exchange365_mailer.
#
# Provisions one disposable DDEV lab per TYPO3 major, wires the matching git branch
# of this extension in as a Composer path repository, and runs the test layers.
# The labs live outside the repository and no existing DDEV project is touched.
#
#   Build/Scripts/runTests.sh [options]
#
#   --versions=12,13   only these majors (default: all in Build/matrix.json)
#   --layers=unit,cgl  only these layers
#   --live             real Microsoft Graph sends (CLI + frontend getEnv), browser
#                      check and getEnv negative check per lab
#                      (credentials: ~/.config/ok-ex365/test.env or $OK_EX365_TEST_ENV)
#   --keep-labs        never delete labs, even on a fully green run
#   --destroy          delete every lab and worktree, then exit
#   --status           show lab state, then exit
#   -h | --help

set -o errexit -o pipefail -o nounset

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
MATRIX_FILE="$REPO_ROOT/Build/matrix.json"
MAIN_BRANCH="main"

# `ddev start` may try to add a hostname to /etc/hosts and prompt for sudo. In a
# non-interactive run that prompt cannot be answered and the start fails, which
# previously showed up only as an unexplained "provision fail".
export DDEV_NONINTERACTIVE=true

# Nothing in the matrix may ever wait for input. Every `ddev` call inherits
# stdin, so a stray prompt (Composer's "trust this plugin?", ddev's own
# confirmations) would otherwise hang an unattended run forever.
exec </dev/null

ADMIN_USER="admin"
ADMIN_PASSWORD="Testlab-Password-1!"
ADMIN_EMAIL="admin@example.com"

# ------------------------------------------------------------------------- helpers

C_RESET=$'\033[0m'; C_RED=$'\033[31m'; C_GREEN=$'\033[32m'
C_YELLOW=$'\033[33m'; C_BLUE=$'\033[34m'; C_DIM=$'\033[2m'

log()  { printf '%s\n' "$*"; }
info() { printf '%s==>%s %s\n' "$C_BLUE" "$C_RESET" "$*"; }
warn() { printf '%s[warn]%s %s\n' "$C_YELLOW" "$C_RESET" "$*" >&2; }
die()  { printf '%s[error]%s %s\n' "$C_RED" "$C_RESET" "$*" >&2; exit 1; }

jqm() { php -r '
    $m = json_decode(file_get_contents($argv[1]), true);
    $path = explode(".", $argv[2]);
    $v = $m;
    foreach ($path as $p) { $v = is_array($v) && array_key_exists($p, $v) ? $v[$p] : null; }
    if (is_array($v)) { echo implode(",", $v); } elseif (is_bool($v)) { echo $v ? "1" : "0"; }
    else { echo (string)$v; }
' "$MATRIX_FILE" "$1"; }

version_field() { php -r '
    $m = json_decode(file_get_contents($argv[1]), true);
    foreach ($m["versions"] as $v) {
        if ((string)$v["major"] === (string)$argv[2]) {
            $x = $v[$argv[3]] ?? "";
            echo is_array($x) ? implode(",", $x) : (string)$x;
            return;
        }
    }
' "$MATRIX_FILE" "$1" "$2"; }

all_majors() { php -r '
    $m = json_decode(file_get_contents($argv[1]), true);
    echo implode(" ", array_map(fn($v) => $v["major"], $m["versions"]));
' "$MATRIX_FILE"; }

# --------------------------------------------------------------------------- config

[ -f "$MATRIX_FILE" ] || die "matrix not found: $MATRIX_FILE"

PACKAGE="$(jqm package)"
EXT_KEY="$(jqm extensionKey)"
PROJECT_PREFIX="$(jqm projectPrefix)"
LAB_ROOT="$(jqm labRoot)"
LAB_ROOT="${LAB_ROOT/#\~/$HOME}"

VERSIONS=""
LAYERS="$(jqm defaultLayers)"
RUN_LIVE=0
KEEP_LABS=0
DO_DESTROY=0
DO_STATUS=0

for arg in "$@"; do
    case "$arg" in
        --versions=*) VERSIONS="${arg#*=}" ;;
        --layers=*)   LAYERS="${arg#*=}" ;;
        --live)       RUN_LIVE=1 ;;
        --keep-labs)  KEEP_LABS=1 ;;
        --destroy)    DO_DESTROY=1 ;;
        --status)     DO_STATUS=1 ;;
        -h|--help)    sed -n '2,20p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *)            die "unknown option: $arg (try --help)" ;;
    esac
done

[ -n "$VERSIONS" ] || VERSIONS="$(all_majors)"
VERSIONS="${VERSIONS//,/ }"
LAYERS=",${LAYERS//,/,},"

has_layer() { [[ "$LAYERS" == *",$1,"* ]]; }

RUN_ID="$(date -u +%Y%m%d-%H%M%S)"
REPORT_DIR="$REPO_ROOT/Build/reports/$RUN_ID"
mkdir -p "$REPORT_DIR"
RUN_LOG="$REPORT_DIR/run.log"
: > "$RUN_LOG"

# shellcheck source=lib/lab.sh
. "$SCRIPT_DIR/lib/lab.sh"
# shellcheck source=lib/report.sh
. "$SCRIPT_DIR/lib/report.sh"

# ------------------------------------------------------------------ early exit modes

if [ "$DO_DESTROY" -eq 1 ]; then
    for major in $(all_majors); do
        info "destroying lab v$major"
        destroy_lab "$major"
    done
    kept_dirty=0
    for wt in "$LAB_ROOT"/worktrees/*; do
        [ -d "$wt" ] || continue
        # Never discard uncommitted work on a maintenance branch. The labs are
        # disposable; a half-finished backport in a worktree is not.
        if [ -n "$(git -C "$wt" status --porcelain 2>/dev/null)" ]; then
            warn "keeping $wt - it has uncommitted changes"
            kept_dirty=1
            continue
        fi
        info "removing worktree $wt"
        git -C "$REPO_ROOT" worktree remove --force "$wt" 2>/dev/null || rm -rf "$wt"
    done
    git -C "$REPO_ROOT" worktree prune 2>/dev/null || true

    if [ "$kept_dirty" -eq 0 ]; then
        rm -rf "$LAB_ROOT"
        log "All labs and worktrees removed."
    else
        for major in $(all_majors); do rm -rf "$(lab_dir "$major")"; done
        log "Labs removed. Worktrees with uncommitted changes were kept."
    fi
    exit 0
fi

if [ "$DO_STATUS" -eq 1 ]; then
    printf '%-8s %-24s %-10s %s\n' "TYPO3" "DDEV PROJECT" "STATE" "LAB DIR"
    for major in $(all_majors); do
        state="absent"
        lab_exists "$major" && state="$(ddev describe "$(lab_project "$major")" -j 2>/dev/null \
            | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["raw"]["status"] ?? "unknown";' 2>/dev/null || echo present)"
        printf '%-8s %-24s %-10s %s\n' "$major" "$(lab_project "$major")" "$state" "$(lab_dir "$major")"
    done
    exit 0
fi

# ------------------------------------------------------------------ preflight checks

command -v ddev >/dev/null 2>&1 || die "ddev not found. Install DDEV: https://ddev.com/"
docker info >/dev/null 2>&1 || die "Docker daemon is not running."

free_mb=$(df -Pm "$HOME" | awk 'NR==2 {print $4}')
if [ "$free_mb" -lt 6144 ]; then
    die "Only ${free_mb} MB free at $HOME; the matrix needs about 6 GB. Aborting before provisioning."
fi

# Credentials for --live live OUTSIDE the repository and outside the source that is
# mounted into the labs. Override the location with OK_EX365_TEST_ENV.
LIVE_ENV="${OK_EX365_TEST_ENV:-$HOME/.config/ok-ex365/test.env}"
if [ "$RUN_LIVE" -eq 1 ]; then
    [ -f "$LIVE_ENV" ] || die "--live given but $LIVE_ENV is missing. Copy Build/testing/.env.test.dist there (chmod 600) and fill it in."
    command -v node >/dev/null 2>&1 || die "--live needs node for the browser check."
    if [ ! -d "$REPO_ROOT/Build/testing/browser/node_modules/playwright-core" ]; then
        ( cd "$REPO_ROOT/Build/testing/browser" && npm install --no-audit --no-fund ) >>"$RUN_LOG" 2>&1 \
            || die "npm install in Build/testing/browser failed - see $RUN_LOG"
    fi
fi

mkdir -p "$LAB_ROOT"

# ------------------------------------------------------------------------- reporting

RESULT_ROWS=""   # major<TAB>layer<TAB>status<TAB>detail

record() {
    RESULT_ROWS+="$1	$2	$3	${4:-}"$'\n'
    local colour="$C_DIM" mark="$3"
    case "$3" in
        pass) colour="$C_GREEN" ;;
        fail) colour="$C_RED" ;;
        skip) colour="$C_YELLOW" ;;
    esac
    printf '    %-22s %s%s%s %s\n' "$2" "$colour" "$mark" "$C_RESET" "${4:-}"
}

# Run one layer inside a lab. Any failure is recorded and never aborts the matrix.
run_layer() {
    local major="$1" layer="$2" dir out rc
    dir="$(lab_dir "$major")"
    out="$REPORT_DIR/v${major}-${layer}.log"

    case "$layer" in
        unit)
            set +e
            ( cd "$dir" && ddev exec vendor/bin/phpunit \
                -c /var/www/ext-src/Build/phpunit/UnitTests.xml ) >"$out" 2>&1
            rc=$?
            set -e
            ;;
        functional)
            set +e
            # The database credentials come from FunctionalTests.xml's <env> block
            # (declared without force="true", so a real env var still wins).
            # `ddev exec` has no -e flag, so do not try to pass them here.
            ( cd "$dir" && ddev exec vendor/bin/phpunit \
                -c /var/www/ext-src/Build/phpunit/FunctionalTests.xml ) >"$out" 2>&1
            rc=$?
            set -e
            ;;
        phpstan)
            local level
            level="$(version_field "$major" phpstanLevel)"
            [ -n "$level" ] || level=8
            set +e
            # PHPStan itself is mandatory; the TYPO3 extension is best-effort.
            # saschaegerer/phpstan-typo3 has NO version covering TYPO3 12 on PHP 8.1
            # (1.x caps at core 11.5, 2.x and 3.x require PHP >= 8.2), so insisting
            # on it would turn a tooling gap into a false product failure.
            ( cd "$dir" && ddev composer require --dev --no-interaction --no-progress \
                phpstan/phpstan ) >>"$RUN_LOG" 2>&1
            phpstan_rc=$?
            typo3ext="without phpstan-typo3"
            if [ "$phpstan_rc" -eq 0 ]; then
                ( cd "$dir" && ddev composer require --dev --no-interaction --no-progress \
                    phpstan/extension-installer saschaegerer/phpstan-typo3 ) >>"$RUN_LOG" 2>&1 \
                    && typo3ext="with phpstan-typo3"
            fi
            ( cd "$dir" && ddev exec vendor/bin/phpstan analyse \
                -c /var/www/ext-src/Build/phpstan.neon --level "$level" --no-progress ) >"$out" 2>&1
            rc=$?
            set -e
            if [ "$rc" -eq 0 ]; then
                record "$major" "$layer" pass "level $level, $typo3ext"
            else
                record "$major" "$layer" fail "level $level, $typo3ext - see $(basename "$out")"
                MATRIX_FAILED=1
            fi
            return 0
            ;;
        cgl)
            set +e
            ( cd "$dir" && ddev composer require --dev --no-interaction --no-progress \
                typo3/coding-standards ) >>"$RUN_LOG" 2>&1
            ( cd "$dir" && ddev exec vendor/bin/php-cs-fixer fix \
                --config=/var/www/ext-src/Build/.php-cs-fixer.dist.php \
                --dry-run --diff --using-cache=no ) >"$out" 2>&1
            rc=$?
            set -e
            ;;
        *)  return 0 ;;
    esac

    if [ "$rc" -eq 0 ]; then
        record "$major" "$layer" pass "$(layer_summary "$layer" "$out")"
    else
        record "$major" "$layer" fail "see $(basename "$out")"
        # Must mark the whole matrix red. A failing layer that still reports
        # "green" at the end is the exact false-positive this harness exists to
        # prevent.
        MATRIX_FAILED=1
    fi
}

layer_summary() {
    case "$1" in
        unit|functional) { grep -oE 'OK \([0-9]+ test' "$2" || grep -oE 'Tests: [0-9]+' "$2"; } \
            | tail -1 | grep -oE '[0-9]+' | sed 's/$/ tests/' || true ;;
        *) : ;;
    esac
}

# --------------------------------------------------------------------------- the run

info "Run $RUN_ID - majors:$(printf ' %s' $VERSIONS) - layers: ${LAYERS//,/ }"
log "${C_DIM}Labs: $LAB_ROOT   Report: $REPORT_DIR${C_RESET}"
log ""

MATRIX_FAILED=0

for major in $VERSIONS; do
    branch="$(version_field "$major" branch)"
    php_version="$(version_field "$major" php)"
    [ -n "$branch" ] || { warn "major $major not in matrix.json - skipping"; continue; }

    info "TYPO3 $major  (branch $branch, PHP $php_version)"

    if ! prepare_source "$major"; then
        record "$major" source fail "could not materialise $branch"
        MATRIX_FAILED=1; continue
    fi

    if has_layer resolve || has_layer install; then
        if provision_lab "$major"; then
            record "$major" provision pass
        else
            record "$major" provision fail "see run.log"
            MATRIX_FAILED=1; continue
        fi
    fi

    if has_layer resolve; then
        if wire_extension "$major"; then
            record "$major" resolve pass
        else
            # A Composer conflict is a FINDING: the declared constraints do not hold
            # for this major. Report it and move on rather than aborting the matrix.
            record "$major" resolve fail "composer could not resolve - see run.log"
            MATRIX_FAILED=1; continue
        fi
    fi

    if has_layer canary; then
        if canary "$major"; then
            record "$major" canary pass "source is live"
        else
            record "$major" canary fail "lab is NOT testing the working tree - results void"
            MATRIX_FAILED=1; continue
        fi
    fi

    if has_layer install; then
        if install_typo3 "$major"; then
            record "$major" install pass
        else
            record "$major" install fail "see run.log"
            MATRIX_FAILED=1; continue
        fi
    fi

    if has_layer unit || has_layer functional; then
        if install_test_deps "$major"; then
            record "$major" test-deps pass
        else
            record "$major" test-deps fail "testing-framework/phpunit did not install"
            MATRIX_FAILED=1
        fi
    fi

    skip_layers=",$(version_field "$major" skipLayers),"
    for layer in unit functional phpstan cgl; do
        has_layer "$layer" || continue
        if [[ "$skip_layers" == *",$layer,"* ]]; then
            record "$major" "$layer" skip "skipped for this major in Build/matrix.json"
            continue
        fi
        run_layer "$major" "$layer"
    done

    if [ "$RUN_LIVE" -eq 1 ]; then
        live_checks "$major"
    else
        record "$major" live skip "pass --live to enable"
    fi

    stop_lab "$major"
    log ""
done

# ------------------------------------------------------------------------ the report

write_report "$REPORT_DIR" "$RUN_ID" "$RESULT_ROWS"

log ""
if [ "$MATRIX_FAILED" -eq 0 ]; then
    printf '%sMatrix green.%s  Report: %s/report.md\n' "$C_GREEN" "$C_RESET" "$REPORT_DIR"
    if [ "$KEEP_LABS" -eq 0 ]; then
        log "Removing labs (pass --keep-labs to keep them)."
        for major in $VERSIONS; do destroy_lab "$major"; done
    fi
    exit 0
else
    printf '%sMatrix has failures.%s  Labs kept for inspection. Report: %s/report.md\n' \
        "$C_RED" "$C_RESET" "$REPORT_DIR"
    exit 1
fi
