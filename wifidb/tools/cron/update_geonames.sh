#!/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/opt/unison

cd ${0%/*}
if ps -ef | grep -v grep | grep exportd.php ; then
	echo Geonames Daemon Already Running! Exiting!
else
	echo Starting Geonames Daemon...
		timestamp=$(date +%s)
        php ../daemon/geonamed.php -v > ../log/geoname/geonamed_${timestamp}.log &
fi

echo "Cleaning up old logs..."
find ../log/geoname/ -mtime +7 -type f -delete

echo "Done. Exiting."
exit 0