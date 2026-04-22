<?php
/**
 * WpSnapshot analyzer strings (English). Built incrementally per
 * sub-checker so each commit stays manageable.
 */
return [
    // Module wrapper
    'summary.prefix'     => 'Internal site analysis: {{score}}/100',
    'summary.outdated'   => ' · {{outdated}} of {{total}} plugins with pending update',
    'summary.site'       => '. Site: {{name}}',

    // users_roles (UsersChecker)
    'users.name'             => 'Users & roles',
    'users.display_one_admin'  => '{{total}} users · {{admins}} admin',
    'users.display_many_admin' => '{{total}} users · {{admins}} admins',
    'users.desc.ok'          => '{{total}} registered users with {{admins}} administrator. Least-privilege applied correctly.',
    'users.desc.too_many'    => '{{total}} users with {{admins}} administrators. Each extra admin increases the attack surface — it only takes one with a weak password or who falls for phishing.',
    'users.recommend.many'   => 'Review the admin list under Users → All Users (filter by role: Administrator). Demote anyone who does not need to change plugins/themes to Editor.',
    'users.recommend.two'    => 'Check whether both admins are really needed.',
    'users.solution'         => 'We apply the principle of least privilege and enable 2FA on every admin account.',

    // app_passwords (UsersChecker)
    'apppw.name'              => 'Application Passwords (REST API)',
    'apppw.display.enabled'   => 'Enabled',
    'apppw.display.disabled'  => 'Disabled',
    'apppw.desc.enabled'      => 'Application Passwords are enabled. They allow authenticating REST requests from external apps (WP mobile app, Zapier, etc.). Safe if unused, but you can disable them to reduce surface if you do not need them.',
    'apppw.desc.disabled'     => 'Application Passwords are disabled. Reduces attack surface on the REST API.',
    'apppw.solution'          => 'We configure authentication methods correctly based on actual site use.',
];
