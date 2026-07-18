<?php
\Config::set('database.connections.tenant.database', 'tenant_6194db19-e8fb-43a2-a9eb-517b3c0df991');
\DB::purge('tenant');

$admin = \App\Domains\Identity\Models\User::on('tenant')->where('email', 'tenant.admin@alpha-engine.example')->firstOrFail();
$tenantAdmin = \App\Domains\Identity\Models\Role::on('tenant')->where('role_name', 'Tenant Admin')->firstOrFail();

$identityPermissions = [
    'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.deactivate', 'users.resetPassword',
    'roles.viewAny', 'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign',
    'security_policies.view', 'security_policies.update'
];

$permissionIds = [];
foreach ($identityPermissions as $name) {
    [$resource, $action] = explode('.', $name, 2);
    $p = \App\Domains\Identity\Models\Permission::on('tenant')->firstOrCreate(
        ['permission_name' => $name],
        [
            'permission_id' => (string) \Illuminate\Support\Str::uuid(),
            'tenant_id'     => $admin->tenant_id,
            'resource_type' => $resource,
            'action_type'   => $action,
            'description'   => "Permission to {$action} {$resource}.",
            'created_at'    => now()
        ]
    );
    $permissionIds[] = (string) $p->permission_id;
}

$tenantAdmin->setConnection('tenant')->permissions()->syncWithoutDetaching($permissionIds);

if (! \App\Domains\Identity\Models\SecurityPolicy::on('tenant')->where('tenant_id', $admin->tenant_id)->exists()) {
    \App\Domains\Identity\Models\SecurityPolicy::on('tenant')->create([
        'tenant_id'                                => $admin->tenant_id,
        'created_by_user_id'                       => $admin->id,
        'mfa_enabled'                              => false,
        'password_min_length'                      => 12,
        'password_require_uppercase'               => true,
        'password_require_lowercase'               => true,
        'password_require_numbers'                 => true,
        'password_require_special_chars'           => true,
        'session_timeout_minutes'                  => 60,
        'session_absolute_timeout_hours'           => 12,
        'session_force_reauth_on_privilege_change' => true,
        'ip_whitelisting_enabled'                  => false,
        'enable_biometric_auth'                    => false,
        'enforce_tls_1_3_minimum'                  => true,
        'disable_weak_ciphers'                     => true,
        'updated_at'                               => now()
    ]);
}

echo "\n=== DONE! EVERYTHING SYNCED SUCCESSFULLY ===\n";