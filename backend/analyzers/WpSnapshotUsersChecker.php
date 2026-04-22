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
            'users_roles',
            Translator::t('wp_snapshot.users.name'),
            $total,
            $adminCount === 1
                ? Translator::t('wp_snapshot.users.display_one_admin', ['total' => $total, 'admins' => $adminCount])
                : Translator::t('wp_snapshot.users.display_many_admin', ['total' => $total, 'admins' => $adminCount]),
            Scoring::clamp($score),
            $adminCount <= 1
                ? Translator::t('wp_snapshot.users.desc.ok', ['total' => $total, 'admins' => $adminCount])
                : Translator::t('wp_snapshot.users.desc.too_many', ['total' => $total, 'admins' => $adminCount]),
            $adminCount > 3
                ? Translator::t('wp_snapshot.users.recommend.many')
                : ($adminCount === 2 ? Translator::t('wp_snapshot.users.recommend.two') : ''),
            Translator::t('wp_snapshot.users.solution'),
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
            'app_passwords',
            Translator::t('wp_snapshot.apppw.name'),
            $enabled,
            $enabled ? Translator::t('wp_snapshot.apppw.display.enabled') : Translator::t('wp_snapshot.apppw.display.disabled'),
            null,
            $enabled
                ? Translator::t('wp_snapshot.apppw.desc.enabled')
                : Translator::t('wp_snapshot.apppw.desc.disabled'),
            '',
            Translator::t('wp_snapshot.apppw.solution'),
            ['enabled' => $enabled]
        );
    }
}
