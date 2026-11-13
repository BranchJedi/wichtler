#!/usr/bin/env bash
# Runs the browser scenarios against a running instance of the app.
#
#   e2e/run.sh                         all scenarios against the local Docker stack
#   e2e/run.sh 04                      only scenarios whose file name starts with 04
#   E2E_BASE_URL=http://... e2e/run.sh another instance
#
# The scenarios create users and groups named "e2e.*" / "E2E *" and need the Wichtler admin.
# Point them at a test instance only. Afterwards: e2e/run.sh cleanup
set -euo pipefail

here="$(cd "$(dirname "$0")" && pwd)"
root="$(dirname "$here")"
base="${E2E_BASE_URL:-http://127.0.0.1:8089}"
out="$here/output"

env_value() { grep "^$1=" "$root/.env.docker" 2>/dev/null | cut -d= -f2- || true; }
admin_user="${E2E_ADMIN_USER:-$(env_value DOCKER_ADMIN_USERNAME)}"
admin_password="${E2E_ADMIN_PASSWORD:-$(env_value DOCKER_ADMIN_PASSWORD)}"

compose() { (cd "$root" && docker compose "$@"); }

cleanup() {
    compose exec -T wichtler_db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" -e "
        DELETE FROM refresh_tokens WHERE username LIKE \"e2e.%\";
        DELETE FROM wichtel_group WHERE title LIKE \"E2E %\";
        DELETE FROM app_user WHERE username LIKE \"e2e.%\";"'
    # Many sign-ups and logins from one address in a short time: start the next run with a clean slate.
    compose exec -T wichtler_app php bin/console cache:pool:clear cache.rate_limiter >/dev/null
    echo "Removed e2e users and groups from the local Docker stack."
}

if [ "${1:-}" = "cleanup" ]; then
    cleanup
    exit 0
fi

if [ -z "$admin_user" ] || [ -z "$admin_password" ]; then
    echo "Set E2E_ADMIN_USER and E2E_ADMIN_PASSWORD, or create .env.docker." >&2
    exit 2
fi

failed=0
for scenario in "$here"/${1:-}*.mjs; do
    name="$(basename "$scenario")"
    [ "$name" = "harness.mjs" ] && continue
    echo "== $name"
    node "$scenario" "$base" "$out" "$admin_user" "$admin_password" || failed=1
done

if [ "$failed" = "0" ]; then echo "All scenarios passed."; else echo "Some scenarios FAILED. Screenshots: $out"; fi
exit "$failed"
