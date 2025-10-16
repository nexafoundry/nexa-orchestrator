#!/bin/bash
# Installation Worker Nexa sur serveur Ubuntu

echo "🦄 Nexa Worker Installation"
echo "=========================="

# Variables
ENGINE_URL="${ENGINE_URL:-https://nexafoundry.ai/love/public}"
ENGINE_TOKEN="${ENGINE_TOKEN:-nexa-engine-secret}"
WORKER_ID="${WORKER_ID:-worker-$(hostname)}"
CLAUDE_API_KEY="${CLAUDE_API_KEY}"

# 1. Installer PHP 8.3
echo "📦 Installing PHP 8.3..."
sudo apt-get update
sudo apt-get install -y php8.3 php8.3-cli php8.3-curl php8.3-mbstring

# 2. Installer Apache
echo "🌐 Installing Apache..."
sudo apt-get install -y apache2
sudo systemctl enable apache2

# 3. Copier les fichiers
echo "📁 Setting up files..."
sudo mkdir -p /var/www/nexa-worker
sudo cp -r ../public /var/www/nexa-worker/
sudo cp -r ../api /var/www/nexa-worker/
sudo cp -r ../worker /var/www/nexa-worker/
sudo mkdir -p /var/www/nexa-worker/storage/sites
sudo mkdir -p /var/www/nexa-worker/storage/logs

# 4. Configurer Apache
echo "⚙️ Configuring Apache..."
sudo tee /etc/apache2/sites-available/nexa-worker.conf > /dev/null <<EOF
<VirtualHost *:8080>
    DocumentRoot /var/www/nexa-worker/public
    
    <Directory /var/www/nexa-worker/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/nexa-worker-error.log
    CustomLog \${APACHE_LOG_DIR}/nexa-worker-access.log combined
</VirtualHost>
EOF

# Activer le port 8080
echo "Listen 8080" | sudo tee -a /etc/apache2/ports.conf

sudo a2ensite nexa-worker
sudo systemctl restart apache2

# 5. Créer fichier .env
echo "📝 Creating .env..."
sudo tee /var/www/nexa-worker/.env > /dev/null <<EOF
ENGINE_URL=$ENGINE_URL
ENGINE_TOKEN=$ENGINE_TOKEN
WORKER_ID=$WORKER_ID
CLAUDE_API_KEY=$CLAUDE_API_KEY
EOF

# 6. Démarrer l'orchestrateur en service systemd
echo "🔧 Setting up orchestrator service..."
sudo tee /etc/systemd/system/nexa-orchestrator.service > /dev/null <<EOF
[Unit]
Description=Nexa Worker Orchestrator
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/nexa-worker/worker
Environment="ENGINE_URL=$ENGINE_URL"
Environment="ENGINE_TOKEN=$ENGINE_TOKEN"
Environment="WORKER_ID=$WORKER_ID"
Environment="CLAUDE_API_KEY=$CLAUDE_API_KEY"
ExecStart=/usr/bin/php orchestrator.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable nexa-orchestrator
sudo systemctl start nexa-orchestrator

echo ""
echo "✅ Installation complete!"
echo "=========================="
echo "Worker ID: $WORKER_ID"
echo "API: http://$(hostname -I | awk '{print $1}'):8080"
echo "Status: systemctl status nexa-orchestrator"
echo ""
echo "🦄 Worker ready to receive jobs!"

