#!/bin/bash
# Script de build para Imagina Audit
# Compila el frontend y prepara los archivos para deploy

set -e

echo "=== Imagina Audit — Build Script ==="
echo ""

# 1. Compilar frontend
echo "[1/3] Compilando frontend..."
cd "$(dirname "$0")/../frontend"
npm install --production=false
npm run build
echo "[OK] Frontend compilado en frontend/dist/"

# 2. Crear carpeta de deploy
DEPLOY_DIR="$(dirname "$0")/output"
rm -rf "$DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR"

# 3. Copiar archivos
echo "[2/3] Copiando archivos..."

# Frontend estático
cp -r dist/* "$DEPLOY_DIR/"

# Backend PHP
cp -r ../backend/api "$DEPLOY_DIR/api"
cp -r ../backend/lib "$DEPLOY_DIR/lib"
cp -r ../backend/analyzers "$DEPLOY_DIR/analyzers"
cp -r ../backend/config "$DEPLOY_DIR/config"
cp -r ../backend/data "$DEPLOY_DIR/data"
cp -r ../backend/database "$DEPLOY_DIR/database"
cp -r ../backend/cron "$DEPLOY_DIR/cron"
mkdir -p "$DEPLOY_DIR/cache"
mkdir -p "$DEPLOY_DIR/logs"

# .htaccess files
cp ../backend/.htaccess "$DEPLOY_DIR/.htaccess.backend"
cp ../backend/cache/.htaccess "$DEPLOY_DIR/cache/.htaccess"
cp ../backend/logs/.htaccess "$DEPLOY_DIR/logs/.htaccess"
cp ../backend/database/.htaccess "$DEPLOY_DIR/database/.htaccess"
cp ../backend/lib/.htaccess "$DEPLOY_DIR/lib/.htaccess"
cp ../backend/analyzers/.htaccess "$DEPLOY_DIR/analyzers/.htaccess"
cp ../backend/config/.htaccess "$DEPLOY_DIR/config/.htaccess"
cp ../backend/data/.htaccess "$DEPLOY_DIR/data/.htaccess"

# .env ejemplo
cp ../backend/.env.example "$DEPLOY_DIR/.env.example"

echo "[OK] Archivos copiados a deploy/output/"

# 3. Crear .htaccess para React Router
cat > "$DEPLOY_DIR/.htaccess" << 'EOF'
<IfModule mod_rewrite.c>
  RewriteEngine On
  # IMPORTANTE: Si la app vive en /audit/, cambiar a: RewriteBase /audit/
  RewriteBase /

  # No tocar archivos reales ni carpetas que existen en disco
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d

  # Redirigir todo lo demás a index.html (React Router)
  RewriteRule ^(.*)$ index.html [L]
</IfModule>

# Bloquear acceso a archivos sensibles
<FilesMatch "\.(env|db|sqlite|log|sql)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
EOF

echo "[3/3] .htaccess creado"
echo ""
echo "=== Build completado ==="
echo "Sube el contenido de deploy/output/ a tu hosting."
