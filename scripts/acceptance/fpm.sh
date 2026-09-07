#!/usr/bin/env bash
set -euo pipefail
umask 077

# A disposable Nginx TLS ingress -> Nginx origin -> PHP-FPM topology, never a live project.
archive=$(realpath "${1:?Pass the accepted source ZIP}")
work=$(mktemp -d /tmp/fnlla-fpm.XXXXXXXX)
php_bin=${PHP_BIN:-/usr/bin/php8.3}
fpm_bin=${PHP_FPM_BIN:-/usr/sbin/php-fpm8.3}
# Enable OPcache before module startup, not as a per-request pool override.
fpm_options=(-d opcache.enable=1 -d opcache.validate_timestamps=0 -d opcache.file_update_protection=0)
fpm_pid=''
nginx_pid=''
cleanup() {
    if [[ -n "$nginx_pid" ]]; then kill "$nginx_pid" 2>/dev/null || true; wait "$nginx_pid" 2>/dev/null || true; fi
    if [[ -n "$fpm_pid" ]]; then kill "$fpm_pid" 2>/dev/null || true; wait "$fpm_pid" 2>/dev/null || true; fi
    # Evidence stays private in runner temp; never upload credentials, cookies or recovery logs.
}
trap cleanup EXIT
unzip -q "$archive" -d "$work/source"
source="$work/source"
for profile in full plain packages; do
    options=(--profile=full)
    if [[ "$profile" == plain ]]; then options=(--profile=plain); fi
    if [[ "$profile" == packages ]]; then options+=(--packages); fi
    "$php_bin" "$source/fnlla" make:project "$work/$profile" 'Acceptance Application' "${options[@]}"
    (cd "$work/$profile" && composer install --no-dev --no-interaction --prefer-dist && "$php_bin" scripts/test.php && "$php_bin" scripts/lint.php)
done
project="$work/full"
touch "$project/.fnlla-http-acceptance"
base=https://127.0.0.1:18443
"$php_bin" "$source/scripts/acceptance/http-smoke.php" "$project" "$base" prepare
openssl req -x509 -newkey rsa:2048 -nodes -days 1 -subj '/CN=127.0.0.1' \
    -addext 'subjectAltName=IP:127.0.0.1' -keyout "$work/tls.key" -out "$work/tls.crt" >/dev/null 2>&1
export FNLLA_ACCEPTANCE_CA="$work/tls.crt"
export FNLLA_ACCEPTANCE_OPCACHE=1

cat > "$work/fpm.conf" <<EOF
[global]
pid = $work/fpm.pid
error_log = $work/fpm-error.log
daemonize = no
[acceptance]
listen = 127.0.0.1:19000
pm = static
pm.max_children = 2
clear_env = yes
catch_workers_output = yes
security.limit_extensions = .php
php_admin_value[display_errors] = Off
php_admin_value[log_errors] = On
php_admin_value[error_log] = $work/php-error.log
EOF
cat > "$work/nginx.conf" <<EOF
daemon off;
pid $work/nginx.pid;
error_log $work/nginx-error.log;
events { worker_connections 128; }
http {
    include /etc/nginx/mime.types;
    access_log off;
    client_body_temp_path $work/client-body;
    proxy_temp_path $work/proxy;
    fastcgi_temp_path $work/fastcgi;
    server {
        listen 127.0.0.1:18443 ssl;
        server_name 127.0.0.1;
        ssl_certificate $work/tls.crt;
        ssl_certificate_key $work/tls.key;
        location / {
            proxy_pass http://127.0.0.1:18080;
            proxy_set_header Host \$http_host;
            proxy_set_header X-Forwarded-Proto \$scheme;
            proxy_set_header X-Forwarded-For \$remote_addr;
            proxy_set_header X-Forwarded-Host \$http_host;
        }
    }
    server {
        listen 127.0.0.1:18080;
        server_name 127.0.0.1;
        root $project/public;
        index index.php;
        location ~ (^|/)\. { return 404; }
        location / { try_files \$uri \$uri/ /index.php?\$query_string; }
        location = /index.php {
            include /etc/nginx/fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $project/public/index.php;
            fastcgi_pass 127.0.0.1:19000;
        }
        location ~ \.php { return 404; }
    }
}
EOF
"$fpm_bin" "${fpm_options[@]}" --fpm-config "$work/fpm.conf" --nodaemonize > "$work/fpm-console.log" 2>&1 &
fpm_pid=$!
nginx -p "$work/" -c "$work/nginx.conf" > "$work/nginx-console.log" 2>&1 &
nginx_pid=$!
ready=0
for attempt in {1..50}; do
    if curl --silent --fail --connect-timeout 1 --max-time 3 --cacert "$work/tls.crt" "$base/" >/dev/null; then ready=1; break; fi
    sleep 0.2
done
if [[ "$ready" != 1 ]]; then
    # No account or recovery token exists yet. Limit diagnostics to this startup phase.
    echo 'FAIL HTTP stack readiness (before account creation)'
    curl --silent --show-error --cacert "$work/tls.crt" --output /dev/null --write-out 'HTTP %{http_code}\n' "$base/" || true
    for log in fpm-console nginx-console fpm-error nginx-error php-error; do
        if [[ -f "$work/$log.log" ]]; then tail -n 30 "$work/$log.log"; fi
    done
    exit 1
fi
"$php_bin" "$source/scripts/acceptance/http-smoke.php" "$project" "$base" exercise
kill "$fpm_pid"
wait "$fpm_pid" || true
"$fpm_bin" "${fpm_options[@]}" --fpm-config "$work/fpm.conf" --nodaemonize > "$work/fpm-console.log" 2>&1 &
fpm_pid=$!
sleep 1
"$php_bin" "$source/scripts/acceptance/http-smoke.php" "$project" "$base" after-reload
echo 'PASS source ZIP, Full/Plain/package installs, TLS proxy, PHP-FPM and OPcache acceptance'
