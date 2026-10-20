#!/bin/sh
set -eu
engine=${CONTAINER_ENGINE:-podman}
image=soca-mediawiki-test
name=soca-mediawiki-test-$$
data=soca-mediawiki-data-$$
secrets=soca-mediawiki-secrets-$$
"$engine" build --network host -f demo/Dockerfile -t "$image" .
"$engine" volume create "$data" >/dev/null
"$engine" volume create "$secrets" >/dev/null
trap '"$engine" rm -f "$name" >/dev/null 2>&1 || true; "$engine" volume rm "$data" "$secrets" >/dev/null 2>&1 || true' EXIT
demo_password=$(python3 -c 'import secrets; print(secrets.token_urlsafe(24))')
"$engine" run -d --name "$name" -p 127.0.0.1::80 -v "$data":/var/www/data -v "$secrets":/run/secrets -e SOCA_DEMO_PASSWORD="$demo_password" "$image" >/dev/null
attempt=0
until "$engine" exec "$name" test -f /var/www/data/soca-ready; do
  attempt=$((attempt + 1))
  [ "$attempt" -lt 90 ] || { "$engine" logs "$name"; exit 1; }
  sleep 1
done
"$engine" exec "$name" php extensions/SOCA/maintenance/soca.php --status | python3 -c 'import sys,json; d=json.load(sys.stdin); assert not d["effective"]["enabled"] and not d["ready"]; print("Headless status and disabled defaults passed")'
integration_result=$("$engine" exec "$name" sh -c 'printf '\''require "extensions/SOCA/tests/integration.php";\n'\'' | php maintenance/run.php eval')
printf '%s\n' "$integration_result"
printf '%s\n' "$integration_result" | python3 -c 'import sys; assert "MediaWiki native integration: 20 assertions passed." in sys.stdin.read()'
port=$("$engine" port "$name" 80/tcp | sed 's/.*://')
curl -fsS "http://127.0.0.1:$port/index.php?title=Special:SOCA/metadata" | python3 -c 'import sys,json; d=json.load(sys.stdin); assert d["token_endpoint_auth_method"] == "private_key_jwt"; assert d["id_token_signed_response_alg"] == "ES256"; assert "PRIVATE" not in str(d); print("Public metadata route passed")'
curl -fsS "http://127.0.0.1:$port/index.php?title=Special:UserLogin" | python3 -c 'import sys; s=sys.stdin.read(); assert "wpPassword" in s and "SOCA" in s; print("Native local recovery and SSO buttons passed")'
"$engine" rm -f "$name" >/dev/null
"$engine" run -d --name "$name" -p 127.0.0.1::80 -v "$data":/var/www/data -v "$secrets":/run/secrets -e SOCA_DEMO_PASSWORD="$demo_password" "$image" >/dev/null
attempt=0
until "$engine" exec "$name" test -f /var/www/data/soca-ready && "$engine" exec "$name" test -f /var/www/html/LocalSettings.php; do
  attempt=$((attempt + 1))
  [ "$attempt" -lt 60 ] || { "$engine" logs "$name"; exit 1; }
  sleep 1
done
"$engine" exec "$name" php extensions/SOCA/maintenance/soca.php --status | python3 -c 'import sys,json; d=json.load(sys.stdin); assert d["effective"]["client_name"] == "SOCA demo"; print("Demo recreation with persisted configuration and key passed")'
