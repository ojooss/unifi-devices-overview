#!/bin/bash
set -e

mkdir -p /var/www/html/var/data /var/www/html/var/log

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

php bin/console cache:warmup

chown -R www-data:www-data /var/www/html/var
chmod -R a+rw /var/www/html/var

# Optional scheduled UniFi scan: UNIFI_SCAN_SCHEDULE is a cron expression, e.g. "0 * * * *".
# cron does not pass the container environment on to its jobs, so it is saved to a file
# (readable by www-data only, it contains the API key) and sourced by the job.
rm -f /etc/cron.d/unifi-scan /etc/unifi-scan.env
if [ -n "${UNIFI_SCAN_SCHEDULE:-}" ]; then
    if [ -z "${UNIFI_API_KEY:-}" ]; then
        echo "UNIFI_SCAN_SCHEDULE is set but UNIFI_API_KEY is empty, scheduled scan disabled." >&2
    else
        export -p > /etc/unifi-scan.env
        chown www-data:www-data /etc/unifi-scan.env
        chmod 600 /etc/unifi-scan.env
        # The job runs as root only to write to the container log (/proc/1/fd/1); the scan runs as www-data.
        printf 'SHELL=/bin/bash\n%s root /sbin/runuser -u www-data -- bash -c ". /etc/unifi-scan.env && cd /var/www/html && php bin/console app:unifi:scan" >> /proc/1/fd/1 2>&1\n' \
            "$UNIFI_SCAN_SCHEDULE" > /etc/cron.d/unifi-scan
        chmod 644 /etc/cron.d/unifi-scan
        cron
    fi
fi

exec /usr/local/bin/docker-php-entrypoint "$@"
