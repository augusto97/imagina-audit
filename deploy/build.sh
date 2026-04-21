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
# --legacy-peer-deps: openapi-typescript pide TS 5.x pero usamos TS 6.x.
# El mismatch de peer es inofensivo en dev — solo afecta a la instalación.
npm install --legacy-peer-deps
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
# NUNCA incluir archivos .db en el artefacto — al subir por FTP sobrescribirían
# la DB real del servidor. Solo el schema se distribuye, el .db se crea al arrancar.
find "$DEPLOY_DIR/database" -name "*.db" -delete 2>/dev/null || true
find "$DEPLOY_DIR/database" -name "*.db-wal" -delete 2>/dev/null || true
find "$DEPLOY_DIR/database" -name "*.db-shm" -delete 2>/dev/null || true
cp -r "$PROJECT_DIR/backend/cron" "$DEPLOY_DIR/cron"
mkdir -p "$DEPLOY_DIR/cache"
mkdir -p "$DEPLOY_DIR/logs"
mkdir -p "$DEPLOY_DIR/uploads"
# El .htaccess del uploads bloquea ejecución de PHP (defense-in-depth)
if [ -f "$PROJECT_DIR/backend/uploads/.htaccess" ]; then
  cp "$PROJECT_DIR/backend/uploads/.htaccess" "$DEPLOY_DIR/uploads/.htaccess"
fi

# Storage de plugins (vault) — los ZIPs se descargan en runtime, solo
# copiamos el .htaccess que bloquea acceso directo.
mkdir -p "$DEPLOY_DIR/storage/plugins"
if [ -f "$PROJECT_DIR/backend/storage/.htaccess" ]; then
  cp "$PROJECT_DIR/backend/storage/.htaccess" "$DEPLOY_DIR/storage/.htaccess"
fi

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
# ═══════════════════════════════════════════════════════════════════
# Imagina Audit — .htaccess raíz (Apache 2.4+, requiere mod_rewrite)
# Si la app vive bajo /audit/ en lugar de la raíz, ajusta RewriteBase.
# ═══════════════════════════════════════════════════════════════════

<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  # Si el sitio vive en subcarpeta: RewriteBase /audit/

  # IMPORTANTE: NO tocar /api/* — ese tiene su propio .htaccess que
  # rutea al backend PHP. Sin esta exclusión, el fallback SPA secuestra
  # todas las requests al backend.
  RewriteCond %{REQUEST_URI} ^/api/
  RewriteRule ^ - [L]

  # Dejar pasar archivos reales (assets compilados, widget, etc.)
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteCond %{REQUEST_FILENAME} !-d

  # Todo lo demás → SPA (React Router)
  RewriteRule ^(.*)$ index.html [L]
</IfModule>

# Cache largo para assets compilados (tienen hash en el nombre)
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 1 year"
  ExpiresByType application/javascript "access plus 1 year"
  ExpiresByType image/webp "access plus 1 year"
  ExpiresByType font/woff2 "access plus 1 year"
</IfModule>

# Bloquear acceso directo a archivos sensibles
<FilesMatch "\.(env|db|sqlite|log|sql)$">
    Require all denied
</FilesMatch>

# Bloquear archivos de versionado si quedaron copiados
<FilesMatch "^\.(git|htaccess-bak|env.*)$">
    Require all denied
</FilesMatch>

# Deshabilitar listado de directorios
Options -Indexes
EOF

echo "[3/3] .htaccess creado"
echo ""
echo "=== Build completado ==="
echo "Sube el contenido de deploy/output/ a tu hosting."
