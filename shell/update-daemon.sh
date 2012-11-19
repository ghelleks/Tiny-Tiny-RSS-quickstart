#/bin/bash

echo "Starting update daemon."
nohup /usr/bin/php $OPENSHIFT_REPO_DIR/php/update.php -daemon >/dev/null 2>&1 &