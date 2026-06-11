<?php
/**
 * Cache en archivos JSON con TTL
 * Almacena resultados de auditorías para evitar escaneos repetidos
 */

class Cache {
    private string $cacheDir;
    private int $ttl;

    public function __construct() {
        $this->cacheDir = dirname(__DIR__) . '/cache';
        $this->ttl = (int) env('CACHE_TTL_SECONDS', '86400'); // 24 horas por defecto

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Obtiene un valor del cache
     * @return mixed|null Retorna null si no existe o expiró
     */
    public function get(string $key): mixed {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null || !isset($data['expires_at'], $data['value'])) {
            return null;
        }

        // Verificar TTL
        if (time() > $data['expires_at']) {
            @unlink($filePath);
            return null;
        }

        return $data['value'];
    }

    /**
     * Guarda un valor en el cache
     */
    public function set(string $key, mixed $value, ?int $ttl = null): void {
        $filePath = $this->getFilePath($key);
        $effectiveTtl = $ttl ?? $this->ttl;

        $data = [
            'key' => $key,
            'value' => $value,
            'created_at' => time(),
            'expires_at' => time() + $effectiveTtl,
        ];

        @file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Elimina un valor del cache
     */
    public function delete(string $key): void {
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    /**
     * Limpia entradas expiradas del cache
     */
    public function cleanup(): int {
        $count = 0;
        $files = glob($this->cacheDir . '/*.json');

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if ($data === null || !isset($data['expires_at']) || time() > $data['expires_at']) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Obtiene un valor del cache usando un nombre de archivo legible
     * Ideal para cache de vulnerabilidades: vuln_elementor, vuln_theme_flavflavor, etc.
     * @return mixed|null Retorna null si no existe o expiró
     */
    public function getByName(string $name): mixed {
        $filePath = $this->getNamedFilePath($name);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null || !isset($data['expires_at'], $data['value'])) {
            return null;
        }

        if (time() > $data['expires_at']) {
            @unlink($filePath);
            return null;
        }

        return $data['value'];
    }

    /**
     * Guarda un valor en el cache usando un nombre de archivo legible
     */
    public function setByName(string $name, mixed $value, ?int $ttl = null): void {
        $filePath = $this->getNamedFilePath($name);
        $effectiveTtl = $ttl ?? $this->ttl;

        $data = [
            'key' => $name,
            'value' => $value,
            'created_at' => time(),
            'expires_at' => time() + $effectiveTtl,
        ];

        @file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Genera la ruta del archivo de cache a partir de la key
     */
    private function getFilePath(string $key): string {
        $hash = md5($key);
        return $this->cacheDir . '/' . $hash . '.json';
    }

    /**
     * Genera la ruta del archivo de cache usando nombre legible (sanitizado)
     * Ej: "vuln_elementor" → cache/vuln_elementor.json
     */
    private function getNamedFilePath(string $name): string {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        return $this->cacheDir . '/' . $safeName . '.json';
    }
}
