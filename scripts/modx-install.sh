#!/usr/bin/env bash
set -euo pipefail

: "${MODX_ROOT:=/var/www/html}"
: "${MODX_DB_HOST:=db}"
: "${MODX_DB_NAME:=modx}"
: "${MODX_DB_USER:=modx}"
: "${MODX_DB_PASSWORD:=modx}"
: "${MODX_DB_PREFIX:=modx_}"
: "${MODX_ADMIN_USER:=aibridge}"
: "${MODX_ADMIN_PASSWORD:=aibridge}"
: "${MODX_ADMIN_EMAIL:=aibridge@example.test}"
: "${MODX_SITE_URL:=http://localhost:8080/}"

CONFIG="${MODX_ROOT}/setup/config.xml"
cat > "${CONFIG}" <<XML
<?xml version="1.0" encoding="UTF-8"?>
<modx>
  <database_type>mysql</database_type>
  <database_server>${MODX_DB_HOST}</database_server>
  <database>${MODX_DB_NAME}</database>
  <database_user>${MODX_DB_USER}</database_user>
  <database_password>${MODX_DB_PASSWORD}</database_password>
  <database_connection_charset>utf8mb4</database_connection_charset>
  <database_charset>utf8mb4</database_charset>
  <database_collation>utf8mb4_unicode_ci</database_collation>
  <table_prefix>${MODX_DB_PREFIX}</table_prefix>
  <inplace>1</inplace>
  <unpacked>1</unpacked>
  <language>en</language>
  <cmsadmin>${MODX_ADMIN_USER}</cmsadmin>
  <cmspassword>${MODX_ADMIN_PASSWORD}</cmspassword>
  <cmsadminemail>${MODX_ADMIN_EMAIL}</cmsadminemail>
  <remove_setup_directory>1</remove_setup_directory>
  <context_mgr_path>${MODX_ROOT}/manager/</context_mgr_path>
  <context_mgr_url>/manager/</context_mgr_url>
  <context_connectors_path>${MODX_ROOT}/connectors/</context_connectors_path>
  <context_connectors_url>/connectors/</context_connectors_url>
  <context_web_path>${MODX_ROOT}/</context_web_path>
  <context_web_url>/</context_web_url>
  <assets_path>${MODX_ROOT}/assets/</assets_path>
  <assets_url>/assets/</assets_url>
  <core_path>${MODX_ROOT}/core/</core_path>
  <processors_path>${MODX_ROOT}/core/src/Revolution/Processors/</processors_path>
  <http_host>localhost</http_host>
  <https_port>443</https_port>
  <cache_disabled>0</cache_disabled>
</modx>
XML

cd "${MODX_ROOT}/setup"
php ./index.php --installmode=new --config="${CONFIG}"

echo "[MODX] CLI installation completed."
