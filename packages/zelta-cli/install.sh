#!/bin/bash
# Zelta CLI Installer
# Usage: curl -fsSL https://cli.zelta.app/install.sh | bash

set -euo pipefail

REPO="FinAegis/core-banking-prototype-laravel"
INSTALL_DIR="${ZELTA_INSTALL_DIR:-/usr/local/bin}"
BINARY_NAME="zelta"

echo "Installing Zelta CLI..."

# Detect OS and architecture
OS=$(uname -s | tr '[:upper:]' '[:lower:]')
ARCH=$(uname -m)

case "$ARCH" in
    x86_64) ARCH="amd64" ;;
    aarch64|arm64) ARCH="arm64" ;;
    *) echo "Unsupported architecture: $ARCH"; exit 1 ;;
esac

# Download latest release
LATEST=$(curl -fsSL "https://api.github.com/repos/${REPO}/releases/latest" | grep '"tag_name"' | sed -E 's/.*"([^"]+)".*/\1/')
DOWNLOAD_URL="https://github.com/${REPO}/releases/download/${LATEST}/zelta-${OS}-${ARCH}"

echo "Downloading Zelta CLI ${LATEST}..."
curl -fsSL "$DOWNLOAD_URL" -o "/tmp/${BINARY_NAME}"
chmod +x "/tmp/${BINARY_NAME}"

# Install
if [ -w "$INSTALL_DIR" ]; then
    mv "/tmp/${BINARY_NAME}" "${INSTALL_DIR}/${BINARY_NAME}"
else
    sudo mv "/tmp/${BINARY_NAME}" "${INSTALL_DIR}/${BINARY_NAME}"
fi

echo ""
echo "Zelta CLI installed to ${INSTALL_DIR}/${BINARY_NAME}"
echo ""
echo "Get started:"
echo "  zelta auth login --key <your-api-key>"
echo "  zelta pay list"
echo "  zelta sms send --to +370xxx --message 'Hello'"
echo ""
