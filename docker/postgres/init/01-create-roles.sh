#!/bin/bash
set -e

psql -v ON_ERROR_STOP=1 \
  --username "$POSTGRES_USER" \
  --dbname "$POSTGRES_DB" \
  -c "CREATE ROLE uhs_ams_candidate LOGIN PASSWORD '${UHS_AMS_CANDIDATE_DB_PASSWORD}';"