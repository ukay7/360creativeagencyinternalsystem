<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth as LaravelAuth;
use Illuminate\Support\Facades\DB;

final class Auth
{
    public function check(): bool
    {
        return LaravelAuth::check();
    }

    public function attempt(string $email, string $password): bool
    {
        $ok = LaravelAuth::attempt([
            'email' => mb_strtolower(trim($email)),
            'status' => 'active',
            'password' => $password,
        ]);

        if ($ok) {
            request()->session()->regenerate();
            User::query()->whereKey(LaravelAuth::id())->update(['last_login_at' => now()]);
        }

        return $ok;
    }

    public function logout(): void
    {
        LaravelAuth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    public function user(): ?array
    {
        $user = LaravelAuth::user();
        if (! $user) {
            return null;
        }

        $data = $user->toArray();
        $role = DB::table('roles')->where('id', $user->role_id)->first(['name', 'slug']);
        $data['role_name'] = $role->name ?? 'User';
        $data['role_slug'] = $role->slug ?? '';

        return $data;
    }

    public function can(string $permission): bool
    {
        $user = LaravelAuth::user();
        if (! $user) {
            return false;
        }
        if (DB::table('roles')->where('id', $user->role_id)->value('slug') === 'super_admin') {
            return true;
        }

        $permissionId = DB::table('permissions')->where('slug', $permission)->value('id');
        if ($permissionId) {
            $override = DB::table('user_permissions')
                ->where('user_id', $user->id)
                ->where('permission_id', $permissionId)
                ->value('allowed');
            if ($override !== null) {
                return (bool) $override;
            }
        }

        return DB::table('role_permissions as rp')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('rp.role_id', $user->role_id)
            ->whereIn('p.slug', ['*', $permission])
            ->exists();
    }

    public function navigationItems(): array
    {
        $items = DB::table('navigation_items as n')
            ->leftJoin('navigation_groups as g', 'g.id', '=', 'n.group_id')
            ->where('n.active', true)
            ->whereNotIn('n.module', ['time','visits','content','media'])
            ->where(fn ($query) => $query->whereNull('n.group_id')->orWhere('g.active', true))
            ->orderByRaw('COALESCE(g.position, n.position)')
            ->orderBy('n.position')
            ->select('n.*', 'g.slug as group_slug', 'g.label as group_label', 'g.icon as group_icon', 'g.position as group_position')
            ->get()
            ->map(fn (object $item): array => (array) $item)
            ->filter(fn (array $item): bool => $this->can($item['permission_slug']))
            ->values()
            ->all();

        $navigation = [];
        $groupIndexes = [];

        foreach ($items as $item) {
            if ($item['group_id'] === null) {
                $item['type'] = 'item';
                $item['root_position'] = (int) $item['position'];
                $navigation[] = $item;
                continue;
            }

            $groupId = (int) $item['group_id'];
            if (! isset($groupIndexes[$groupId])) {
                $groupIndexes[$groupId] = count($navigation);
                $navigation[] = [
                    'type' => 'group',
                    'id' => $groupId,
                    'slug' => $item['group_slug'],
                    'label' => $item['group_label'],
                    'icon' => $item['group_icon'],
                    'root_position' => (int) $item['group_position'],
                    'children' => [],
                ];
            }

            $navigation[$groupIndexes[$groupId]]['children'][] = $item + ['type' => 'item'];
        }

        usort($navigation, static fn (array $left, array $right): int => $left['root_position'] <=> $right['root_position']);

        return $navigation;
    }
}
