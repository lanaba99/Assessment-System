<?php

declare(strict_types=1);

namespace App\Domains\Identity\Repositories;

use App\Domains\Identity\Models\IpWhitelist;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\IpUtils;

class IpWhitelistRepository
{
    public function __construct(
        private readonly IpWhitelist $model,
    ) {
    }

    public function findById(string $tenantId, string $whitelistId): ?IpWhitelist
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->whereKey($whitelistId)
            ->first();
    }

    /**
     * @return Collection<int, IpWhitelist>
     */
    public function listActiveForTenant(string $tenantId): Collection
    {
        $now = now();

        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->get();
    }

    /**
     * Resolve the request IP against every active whitelist row for the
     * tenant, supporting three shapes per row:
     *   - exact:  ip_address holds a literal v4/v6 address (no slash, no end).
     *   - CIDR:   ip_address holds a CIDR block ("192.168.1.0/24", "2001:db8::/32").
     *   - range:  ip_address holds the start, ip_range_end holds the end (v4 only).
     *
     * Returns the first matching row, or null if nothing matches.
     */
    public function findMatchForIp(string $tenantId, string $ipAddress): ?IpWhitelist
    {
        $rows = $this->listActiveForTenant($tenantId);

        foreach ($rows as $row) {
            if ($this->rowMatchesIp($row, $ipAddress)) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Retained for backward compatibility; new callers should prefer findMatchForIp.
     */
    public function findExactMatch(string $tenantId, string $ipAddress): ?IpWhitelist
    {
        return $this->model
            ->newQuery()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('ip_address', $ipAddress)
            ->first();
    }

    public function create(string $tenantId, string $createdByUserId, array $attributes): IpWhitelist
    {
        $attributes['tenant_id'] = $tenantId;
        $attributes['created_by_user_id'] = $createdByUserId;
        $attributes['created_at'] = $attributes['created_at'] ?? now();

        return $this->model->newQuery()->create($attributes);
    }

    public function deactivate(string $tenantId, string $whitelistId): ?IpWhitelist
    {
        $entry = $this->findById($tenantId, $whitelistId);

        if ($entry === null) {
            return null;
        }

        $entry->fill(['is_active' => false])->save();

        return $entry;
    }

    private function rowMatchesIp(IpWhitelist $row, string $ipAddress): bool
    {
        $ip = trim((string) $row->ip_address);
        $end = $row->ip_range_end !== null ? trim((string) $row->ip_range_end) : null;

        if ($ip === '') {
            return false;
        }

        if ($end !== null && $end !== '') {
            return $this->ipInInclusiveRange($ipAddress, $ip, $end);
        }

        // IpUtils::checkIp handles plain IPs and CIDR for both IPv4 and IPv6.
        return IpUtils::checkIp($ipAddress, $ip);
    }

    private function ipInInclusiveRange(string $ipAddress, string $start, string $end): bool
    {
        $request = @inet_pton($ipAddress);
        $low = @inet_pton($start);
        $high = @inet_pton($end);

        if ($request === false || $low === false || $high === false) {
            return false;
        }

        if (strlen($request) !== strlen($low) || strlen($request) !== strlen($high)) {
            return false;
        }

        return strcmp($request, $low) >= 0 && strcmp($request, $high) <= 0;
    }
}
