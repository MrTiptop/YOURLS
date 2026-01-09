#!/bin/bash
# WARNING: This script attempts to download ALL plugins from the YOURLS Awesome List
# This is NOT recommended as it can cause conflicts and performance issues
# Use at your own risk!

set -e

PLUGINS_DIR="/Users/dewet0000/Documents/GitHub/YOURLS/user/plugins"
cd "$PLUGINS_DIR"

echo "⚠️  WARNING: This will attempt to download ALL 244+ plugins from the YOURLS Awesome List"
echo "⚠️  This is NOT recommended and may cause:"
echo "   - Plugin conflicts"
echo "   - Performance issues"
echo "   - Security vulnerabilities"
echo "   - System instability"
echo ""
read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Cancelled."
    exit 0
fi

echo ""
echo "📦 Fetching plugin list from Awesome YOURLS..."
echo ""

# Download the README to parse plugin URLs
TMP_FILE=$(mktemp)
curl -s "https://raw.githubusercontent.com/YOURLS/awesome/main/README.md" > "$TMP_FILE"

# Extract GitHub URLs from the README
echo "🔍 Extracting plugin URLs..."
grep -oP 'https://github\.com/[^/]+/[^)]+' "$TMP_FILE" | sort -u > "${TMP_FILE}.urls"

TOTAL=$(wc -l < "${TMP_FILE}.urls" | tr -d ' ')
echo "Found $TOTAL potential plugins"
echo ""

# Function to install a plugin
install_plugin() {
    local url=$1
    local name=$(basename "$url" .git)
    
    # Skip if already exists
    if [ -d "$name" ]; then
        echo "⏭️  $name already exists"
        return
    fi
    
    echo "⬇️  Installing $name..."
    if git clone "$url.git" "$name" 2>/dev/null; then
        rm -rf "$name/.git"
        echo "✅ Installed $name"
    else
        echo "❌ Failed to install $name"
    fi
}

# Install all plugins
COUNTER=0
while IFS= read -r url; do
    COUNTER=$((COUNTER + 1))
    echo "[$COUNTER/$TOTAL]"
    install_plugin "$url"
    echo ""
done < "${TMP_FILE}.urls"

rm -f "$TMP_FILE" "${TMP_FILE}.urls"

echo ""
echo "✅ Download complete!"
echo ""
echo "⚠️  IMPORTANT:"
echo "   1. Review all plugins before activating"
echo "   2. Activate plugins one at a time and test"
echo "   3. Many plugins may conflict with each other"
echo "   4. Go to http://localhost:8080/admin/plugins.php to manage"
echo ""
