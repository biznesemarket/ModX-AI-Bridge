#!/usr/bin/env bash
set -euo pipefail

MODX_DIR="${MODX_DIR:-$PWD/.ci-modx}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-modx}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-root}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-Admin123!Admin123!}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.test}"

if [[ ! -f "$MODX_DIR/config.core.php" ]]; then
  composer create-project modx/revolution "$MODX_DIR" 3.x-dev --no-interaction
fi

python3 - "$MODX_DIR/setup/config.dist.new.xml" "$MODX_DIR/setup/config.xml" "$DB_HOST" "$DB_PORT" "$DB_NAME" "$DB_USER" "$DB_PASS" "$ADMIN_USER" "$ADMIN_PASS" "$ADMIN_EMAIL" <<'PY'
import sys
import xml.etree.ElementTree as ET

src, dst, host, port, db, user, password, admin, admin_password, email = sys.argv[1:]
tree = ET.parse(src)
root = tree.getroot()
values = {
    'database_type': 'mysql',
    'database_server': f'{host};port={port}',
    'database': db,
    'database_user': user,
    'database_password': password,
    'database_connection_charset': 'utf8mb4',
    'database_charset': 'utf8mb4',
    'database_collation': 'utf8mb4_unicode_ci',
    'table_prefix': 'modx_',
    'inplace': '1',
    'unpacked': '0',
    'language': 'en',
    'remove_setup_directory': '1',
    'administrator_username': admin,
    'administrator_password': admin_password,
    'administrator_email': email,
    'context_web_url': '/',
}
for key, value in values.items():
    node = root.find(key)
    if node is not None:
        node.text = value
ET.register_namespace('', root.tag.split('}')[0].strip('{') if root.tag.startswith('{') else '')
tree.write(dst, encoding='utf-8', xml_declaration=True)
PY

php "$MODX_DIR/setup/index.php" --installmode=new --config="$MODX_DIR/setup/config.xml"
