#!/bin/bash

set -euo pipefail

TEST_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RED_STRIPE_REHEARSAL_ID=p3d7 \
    exec "$TEST_DIR/p3d1-install-disabled-rehearsal.sh"
