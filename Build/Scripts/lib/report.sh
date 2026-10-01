#!/usr/bin/env bash
#
# Report writer. Both report.md and results.json derive from the same rows, so the
# report can be regenerated without re-running anything.
#
# Sourced by runTests.sh; not meant to be executed directly.

write_report() {
    local dir="$1" run_id="$2" rows="$3"
    local md="$dir/report.md" json="$dir/results.json"

    printf '%s' "$rows" > "$dir/rows.tsv"

    MATRIX_FILE="$MATRIX_FILE" RUN_ID="$run_id" REPORT_MD="$md" REPORT_JSON="$json" \
    php "$SCRIPT_DIR/lib/report.php" "$dir/rows.tsv"
}
