#!/bin/bash
# Script de build para Imagina Audit
# Compila el frontend y prepara los archivos para deploy

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "=== Imagina Audit — Build Script ==="
echo ""

# 1. Compilar frontend
echo "[1/3] Compilando frontend..."
cd "$PROJECT_DIR/frontend"
npm install --production=false
npm run build
echo "[OK] Frontend compilado en frontend/dist/"

# 2. Crear carpeta de deploy
DEPLOY_DIR="$SCRIPT_DIR/output"
rm -rf "$DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR"

# 3. Copiar archivos
echo "[2/3] Copiando archivos..."

# Frontend estático
cp -r "$PROJECT_DIR/frontend/dist/"* "$DEPLOY_DIR/"

# Backend PHP
cp -r "$PROJECT_DIR/backend/api" "$DEPLOY_DIR/api"
cp -r "$PROJECT_DIR/backend/lib" "$DEPLOY_DIR/lib"
cp -r "$PROJECT_DIR/backend/analyzers" "$DEPLOY_DIR/analyzers"
cp -r "$PROJECT_DIR/backend/config" "$DEPLOY_DIR/config"
cp -r "$PROJECT_DIR/backend/data" "$DEPLOY_DIR/data"
cp -r "$PROJECT_DIR/backend/database" "$DEPLOY_DIR/database"
cp -r "$PROJECT_DIR/backend/cron" "$DEPLOY_DIR/cron"
mkdir -p "$DEPLOY_DIR/cache"
mkdir -p "$DEPLOY_DIR/logs"

# .htaccess files
cp "$PROJECT_DIR/backend/.htaccess" "$DEPLOY_DIR/.htaccess.backend"
cp "$PROJECT_DIR/backend/cache/.htaccess" "$DEPLOY_DIR/cache/.htaccess"
cp "$PROJECT_DIR/backend/logs/.htaccess" "$DEPLOY_DIR/logs/.htaccess"
cp "$PROJECT_DIR/backend/database/.htaccess" "$DEPLOY_DIR/database/.htaccess"
cp "$PROJECT_DIR/backend/lib/.htaccess" "$DEPLOY_DIR/lib/.htaccess"
cp "$PROJECT_DIR/backend/analyzers/.htaccess" "$DEPLOY_DIR/analyzers/.htaccess"
cp "$PROJECT_DIR/backend/config/.htaccess" "$DEPLOY_DIR/config/.htaccess"
cp "$PROJECT_DIR/backend/data/.htaccess" "$DEPLOY_DIR/data/.htaccess"

# Widget
if [ -d "$PROJECT_DIR/frontend/public/widget" ]; then
  cp -r "$PROJECT_DIR/frontend/public/widget" "$DEPLOY_DIR/widget"
fi

# .env ejemplo
cp "$PROJECT_DIR/backend/.env.example" "$DEPLOY_DIR/.env.example"

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
