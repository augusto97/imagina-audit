<?php
/**
 * Checks del snapshot sobre usuarios y roles.
 *
 * wp-snapshot solo expone conteos por rol (no la lista de usuarios con
 * username para no filtrar datos sensibles), así que los análisis se
 * basan en role_counts.
 *
 * Sub-checker de WpSnapshotAnalyzer.
 */

class WpSnapshotUsersChecker {
    public function __construct(private array $snapshot) {}

    private function getSection(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    public function analyzeRoleCounts(): ?array {
        $users = $this->getSection('users');
        if (empty($users)) return null;

        $total = (int) ($users['total_users'] ?? 0);
        $roles = $users['roles'] ?? [];
        if (empty($roles)) return null;

        // wp-snapshot retorna roles como lista: [{slug, name, user_count, cap_count}, ...]
        $byRole = [];
        $adminCount = 0;
        foreach ($roles as $r) {
            $slug = $r['slug'] ?? '';
            $count = (int) ($r['user_count'] ?? 0);
            if ($count === 0) continue;
            $byRole[] = [
                'slug' => $slug,
                'name' => $r['name'] ?? $slug,
                'userCount' => $count,
                'capCount' => (int) ($r['cap_count'] ?? 0),
            ];
            if ($slug === 'administrator') $adminCount = $count;
        }
        usort($byRole, fn($a, $b) => $b['userCount'] <=> $a['userCount']);

        $score = 100;
        $issues = [];
        if ($adminCount > 3) { $score -= 30; $issues[] = "$adminCount administradores es mucho"; }
        elseif ($adminCount > 1) { $score -= 10; }
        if ($adminCount === 0) { $score = 60; $issues[] = 'Sin administrador visible (raro)'; }

        return Scoring::createMetric(
            'users_roles', 'Usuarios y roles',
            $total,
            "$total usuarios · $adminCount admin" . ($adminCount !== 1 ? 's' : ''),
            Scoring::clamp($score),
            $adminCount <= 1
                ? "$total usuarios registrados con $adminCount administrador. Mínimo privilegio aplicado correctamente."
                : "$total usuarios con $adminCount administradores. Cada admin adicional aumenta la superficie de ataque — basta con que uno tenga password débil o sea víctima de phishing.",
            $adminCount > 3
                ? 'Revisar la lista de admins en Usuarios → Todos los usuarios (filtro rol: Administrador). Bajar a Editor quienes no necesiten cambiar plugins/temas.'
                : ($adminCount === 2 ? 'Revisar si los 2 admins son realmente necesarios.' : ''),
            'Aplicamos principio de mínimo privilegio y activamos 2FA en todas las cuentas admin.',
            ['total' => $total, 'administrators' => $adminCount, 'rolesBreakdown' => $byRole]
        );
    }

    public function analyzeSecurityChecks(): ?array {
        // Pasa los checks del security section que no están ya cubiertos en otros sub-checkers
        $checks = $this->getSection('security')['checks'] ?? [];

        // App passwords habilitado (habilita REST API con credenciales — es informativo)
        $appPw = $checks['app_passwords'] ?? null;
        if ($appPw === null) return null;

        $enabled = (bool) ($appPw['value'] ?? false);

        return Scoring::createMetric(
            'app_passwords', 'Application Passwords (REST API)',
            $enabled,
            $enabled ? 'Habilitadas' : 'Deshabilitadas',
            null,
            $enabled
                ? 'Application Passwords están habilitadas. Permiten autenticar requests REST desde apps externas (mobile WP app, Zapier, etc.). Seguro si no se usan, pero si no las necesitas puedes desactivarlas para reducir superficie.'
                : 'Application Passwords deshabilitadas. Reduce superficie de ataque sobre REST API.',
            '',
            'Configuramos correctamente los métodos de autenticación según el uso real del sitio.',
            ['enabled' => $enabled]
        );
    }
}
