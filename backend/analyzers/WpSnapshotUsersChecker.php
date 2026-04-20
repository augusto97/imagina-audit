<?php
/**
 * Checks del snapshot sobre usuarios y autenticación.
 *
 * Sub-checker de WpSnapshotAnalyzer.
 */

class WpSnapshotUsersChecker {
    public function __construct(private array $snapshot) {}

    private function getSection(string $key): array {
        return $this->snapshot['sections'][$key]['data'] ?? [];
    }

    public function analyzeUsers(): ?array {
        $users = $this->getSection('users');
        if (empty($users)) return null;

        $total = $users['total_users'] ?? 0;
        $roleCounts = $users['role_counts'] ?? [];
        $admins = $roleCounts['administrator'] ?? 0;

        $score = $admins <= 1 ? 100 : ($admins <= 3 ? 80 : 50);

        return Scoring::createMetric(
            'users_roles', 'Usuarios y roles',
            $total, "$total usuarios · $admins administradores",
            $score,
            "$total usuarios registrados. $admins administradores." . ($admins > 3 ? ' Demasiados admins aumenta la superficie de ataque.' : ''),
            $admins > 3 ? 'Revisar si todos los admins son necesarios. Asignar roles más específicos (Editor, Autor) donde sea posible.' : '',
            'Auditamos los roles de usuario y aplicamos el principio de mínimo privilegio.',
            ['total' => $total, 'roles' => $roleCounts]
        );
    }

    public function analyzeWeakAdminUsers(): ?array {
        $users = $this->getSection('users');
        if (empty($users)) return null;

        $userList = $users['users'] ?? [];
        $weakNames = ['admin', 'administrator', 'root', 'test', 'user'];
        $found = [];

        foreach ($userList as $u) {
            $login = strtolower($u['user_login'] ?? ($u['login'] ?? ($u['username'] ?? '')));
            $role = $u['role'] ?? ($u['roles'] ?? '');
            if (is_array($role)) $role = implode(', ', $role);
            if (in_array($login, $weakNames, true)) {
                $found[] = ['login' => $login, 'role' => $role];
            }
        }

        if (empty($found)) return null;

        return Scoring::createMetric(
            'weak_admin_users', 'Usuarios con nombres predecibles',
            count($found), count($found) . ' usuarios con nombre débil',
            count($found) >= 2 ? 20 : 40,
            'Se detectaron usuarios con nombres predecibles (' . implode(', ', array_column($found, 'login')) . '). Estos son los primeros que los atacantes intentan en ataques de fuerza bruta.',
            'Crear nuevos usuarios con nombres únicos, transferir el contenido y eliminar los usuarios con nombres predecibles.',
            'Cambiamos usernames predecibles y configuramos protección contra fuerza bruta.',
            ['users' => $found]
        );
    }
}
