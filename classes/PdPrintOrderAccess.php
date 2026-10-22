<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PdPrintOrderAccessException extends RuntimeException
{
}

class PdPrintOrderAccess
{
    public static function assertPermission(Context $context, string $permission = 'read'): void
    {
        $employee = $context->employee;
        if (!Validate::isLoadedObject($employee) || !$employee->isLoggedBack()) {
            throw new PdPrintOrderAccessException('Sesja pracownika wygasła. Zaloguj się ponownie do panelu.', 401);
        }

        // A valid CSRF token does not grant permission to read or edit orders.
        $roles = ['ROLE_MOD_TAB_ADMINORDERS_READ'];
        if ($permission === 'update') {
            $roles[] = 'ROLE_MOD_TAB_ADMINORDERS_UPDATE';
        } elseif ($permission !== 'read') {
            throw new InvalidArgumentException('Nieprawidłowy typ uprawnienia.');
        }

        foreach ($roles as $role) {
            if (!Access::isGranted($role, (int) $employee->id_profile)) {
                throw new PdPrintOrderAccessException('Brak uprawnień do tej operacji na zamówieniu.', 403);
            }
        }
    }

    /**
     * Validate the entire batch before reading documents or producing output.
     *
     * @return Order[]
     */
    public static function getOrders(array $orderIds, Context $context, string $permission = 'read'): array
    {
        self::assertPermission($context, $permission);
        $orders = [];

        foreach (array_unique(array_map('intval', $orderIds)) as $idOrder) {
            $order = new Order($idOrder);
            if ($idOrder <= 0 || !Validate::isLoadedObject($order)) {
                throw new PdPrintOrderAccessException('Nie znaleziono zamówienia.', 404);
            }

            if (!$context->employee->hasAuthOnShop((int) $order->id_shop)) {
                throw new PdPrintOrderAccessException('Brak dostępu do sklepu tego zamówienia.', 403);
            }

            $orders[] = $order;
        }

        return $orders;
    }
}
