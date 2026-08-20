<?php

declare(strict_types=1);

/**
 * Pure authorization policy helpers.
 * No database/network dependency: safe for deterministic unit tests.
 */

function rbac_is_active(array $user): bool
{
    return (int)($user['is_active'] ?? 0) === 1;
}

function rbac_has_permission(array $userPermissions, string $permission): bool
{
    return in_array($permission, $userPermissions, true);
}

function rbac_portal_allowed(array $user, string $portal): bool
{
    if (!rbac_is_active($user)) {
        return false;
    }
    $userPortal = strtoupper((string)($user['portal_type'] ?? ''));
    return $userPortal === strtoupper($portal);
}

function rbac_scope_allows(array $user, array $resource): bool
{
    if (!rbac_is_active($user)) {
        return false;
    }

    $scope = strtoupper((string)($user['scope_type'] ?? ''));
    if ($scope === 'GLOBAL') {
        return true;
    }

    if ($scope === 'CUSTOMER') {
        $customerId = (int)($user['customer_id'] ?? 0);
        return $customerId > 0 && $customerId === (int)($resource['customer_id'] ?? 0);
    }

    if ($scope === 'SERVICE') {
        $serviceId = (int)($user['service_id'] ?? 0);
        return $serviceId > 0 && $serviceId === (int)($resource['service_id'] ?? 0);
    }

    if ($scope === 'ASSIGNED') {
        $userId = (int)($user['id'] ?? 0);
        return $userId > 0 && $userId === (int)($resource['assigned_user_id'] ?? 0);
    }

    return false;
}

function rbac_authorize(
    array $user,
    array $permissions,
    string $permission,
    string $portal,
    array $resource = []
): string {
    if (!rbac_is_active($user)) {
        return 'DENY_INACTIVE';
    }
    if (!rbac_portal_allowed($user, $portal)) {
        return 'DENY_PORTAL';
    }
    if (!rbac_has_permission($permissions, $permission)) {
        return 'DENY_NO_PERMISSION';
    }
    if ($resource !== [] && !rbac_scope_allows($user, $resource)) {
        return 'DENY_SCOPE';
    }
    return 'ALLOW';
}
