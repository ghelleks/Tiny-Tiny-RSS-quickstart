#!/bin/bash

# Settings
TMP="/tmp"
LOCKDIR="$TMP/ttrss"
LOCKFILE="$LOCKDIR/ttrss.pid"
UPDATE="$OPENSHIFT_REPO_DIR/php/update.php"

if [ ! -d $LOCKDIR ]; then
    mkdir $LOCKDIR
fi

if [ -e $LOCKFILE ]; then
    echo "PID file exists!"
    PID=$(eval echo -e `<$LOCKFILE`)

    if [ -e "/proc/$PID" ]; then
    echo "The process is active!"
    exit 2
    fi
fi

echo "Starting update.php"

# Start update.php
/usr/bin/php $UPDATE -daemon >/dev/null 2>&1 &

# Create lockfile
echo $! > $LOCKFILE
