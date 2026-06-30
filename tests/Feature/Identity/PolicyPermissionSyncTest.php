<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Tests\TestCase;

/**
 * حارس آلي يمنع تكرار "bug الـ Evaluator":
 * أي صلاحية يتم التحقق منها داخل Policy لازم تكون:
 *  1) معرّفة فعليًا بقائمة الصلاحيات الكاملة (canonical list) — وإلا فهي typo.
 *  2) ممنوحة لدور واحد على الأقل غير Super Admin — وإلا فهي صلاحية "ميتة"
 *     (موجودة بس محد غير Super Admin يقدر يوصلها).
 */
class PolicyPermissionSyncTest extends TestCase
{
    private const POLICIES_GLOB = 'app/Domains/*/Policies/*.php';
    private const SEEDER_PATH = 'database/seeders/TenantMasterSeeder.php';

    public function test_no_typo_permissions_in_policies(): void
    {
        $checked = $this->extractCheckedPermissions();
        $canonical = $this->extractCanonicalPermissions();

        $unknown = array_diff($checked, $canonical);

        $this->assertEmpty(
            $unknown,
            " permissions being checked in Policies but not defined in seedPermissions():\n"
            . implode("\n", $unknown)
        );
    }

    public function test_no_dead_permissions_unused_by_any_policy(): void
    {
        $checked = $this->extractCheckedPermissions();
        $canonical = $this->extractCanonicalPermissions();

        $dead = array_diff($canonical, $checked);

        $this->assertEmpty(
            $dead,
            " permissions defined in seedPermissions() but not checked by any Policy (either bind them or delete them):\n"
            . implode("\n", $dead)
        );
    }

    private function extractCheckedPermissions(): array
    {
        $permissions = [];

        foreach (glob(base_path(self::POLICIES_GLOB)) as $file) {
            $content = file_get_contents($file);

            preg_match_all('/(?:hasPermission|userHasPermission)\(/', $content, $calls, PREG_OFFSET_CAPTURE);

            foreach ($calls[0] as [, $offset]) {
                $window = substr($content, $offset, 200);
                if (preg_match("/'([a-zA-Z_]+\\.[a-zA-Z]+)'/", $window, $m)) {
                    $permissions[] = $m[1];
                }
            }
        }

        return array_unique($permissions);
    }

    private function extractCanonicalPermissions(): array
    {
        $content = file_get_contents(base_path(self::SEEDER_PATH));

        preg_match(
            '/private function seedPermissions.*?(?=private function seedRolePermissions)/s',
            $content,
            $block
        );
           $blockText = $block[0] ?? '';

           $cleanBlock = preg_replace('/^\s*\/\/.*$/m', '', $blockText);
           preg_match_all("/'name'\s*=>\s*'([a-zA-Z_]+\.[a-zA-Z]+)'/", $cleanBlock, $m);           
        return array_unique($m[1]);
    }
}