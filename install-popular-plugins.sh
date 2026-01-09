#!/bin/bash
# Script to install popular/useful YOURLS plugins
# This installs a curated selection, not all 244+ plugins

set -e

PLUGINS_DIR="/Users/dewet0000/Documents/GitHub/YOURLS/user/plugins"
cd "$PLUGINS_DIR"

echo "📦 Installing popular YOURLS plugins..."
echo ""

# Function to install a plugin from GitHub
install_plugin() {
    local repo=$1
    local name=$2
    
    if [ -d "$name" ]; then
        echo "⏭️  $name already exists, skipping..."
        return
    fi
    
    echo "⬇️  Installing $name..."
    git clone "$repo" "$name" 2>/dev/null || {
        echo "❌ Failed to clone $name"
        return
    }
    
    # Remove .git directory to save space
    rm -rf "$name/.git"
    echo "✅ Installed $name"
}

# Popular/Useful plugins to install
install_plugin "https://github.com/YOURLS/404-if-not-found.git" "404-if-not-found"
install_plugin "https://github.com/YOURLS/antispam.git" "antispam"
install_plugin "https://github.com/YOURLS/API-action.git" "API-action"
install_plugin "https://github.com/claytondaley/yourls-api-delete.git" "api-delete"
install_plugin "https://github.com/timcrockford/yourls-api-edit-url.git" "api-edit-url"
install_plugin "https://github.com/EpicPilgrim/302-instead.git" "302-instead"
install_plugin "https://github.com/MatthewC/yourls-2fa-support.git" "2fa-support"
install_plugin "https://github.com/armujahid/Admin-reCaptcha.git" "admin-recaptcha"
install_plugin "https://github.com/floschliep/YOURLS-Amazon-Affiliate.git" "amazon-affiliate"
install_plugin "https://github.com/wlabarron/yourls-anonymise.git" "anonymise"

echo ""
echo "✅ Plugin installation complete!"
echo ""
echo "📋 Next steps:"
echo "   1. Go to http://localhost:8080/admin/plugins.php"
echo "   2. Review and activate the plugins you want to use"
echo "   3. Test each plugin after activation"
echo ""
