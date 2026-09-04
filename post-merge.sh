#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

pnpm install --frozen-lockfile --ignore-scripts
pnpm --filter @workspace/hospital-malanje run build