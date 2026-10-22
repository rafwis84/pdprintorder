<?php
namespace PdPrintOrder\Controller\Admin;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PdPrintOrderBulkController
{
    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function bulkInvoice(Request $request): Response
    {
        return $this->bulkNative('invoice', $request);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function bulkDelivery(Request $request): Response
    {
        return $this->bulkNative('delivery', $request);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))")
     */
    public function bulkPacking(Request $request): Response
    {
        $orderIds = $this->getSelectedOrderIds($request);
        if (empty($orderIds)) {
            return $this->redirectToOrders();
        }

        require_once _PS_MODULE_DIR_ . 'pdprintorder/classes/PdPrintOrderPackingPdf.php';

        try {
            \PdPrintOrderPackingPdf::render($orderIds, \Context::getContext());
        } catch (\PdPrintOrderAccessException $exception) {
            return new Response($exception->getMessage(), $exception->getCode(), ['Content-Type' => 'text/plain; charset=UTF-8']);
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception);
        }

        return new Response();
    }

    private function bulkNative(string $type, Request $request): Response
    {
        $orderIds = $this->getSelectedOrderIds($request);
        if (empty($orderIds)) {
            return $this->redirectToOrders();
        }

        require_once _PS_MODULE_DIR_ . 'pdprintorder/classes/PdPrintOrderPdf.php';

        try {
            \PdPrintOrderPdf::render($type, $orderIds, \Context::getContext());
        } catch (\PdPrintOrderAccessException $exception) {
            return new Response($exception->getMessage(), $exception->getCode(), ['Content-Type' => 'text/plain; charset=UTF-8']);
        } catch (\Throwable $exception) {
            return $this->errorResponse($exception);
        }

        return new Response();
    }

    private function errorResponse(\Throwable $exception): Response
    {
        return new Response(
            'Nie udało się wygenerować dokumentu: ' . $exception->getMessage(),
            400,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    private function redirectToOrders(): RedirectResponse
    {
        $context = \Context::getContext();
        return new RedirectResponse($context->link->getAdminLink('AdminOrders'));
    }

    private function getSelectedOrderIds(Request $request): array
    {
        $data = $request->request->all();
        $ids = [];

        foreach (['order_orders_bulk', 'orders_orders_bulk', 'orders_bulk'] as $key) {
            if (isset($data[$key])) {
                $ids = is_array($data[$key]) ? $data[$key] : [$data[$key]];
                break;
            }
        }

        if (empty($ids)) {
            foreach ($data as $key => $value) {
                if (is_array($value) && substr((string) $key, -5) === '_bulk') {
                    $ids = $value;
                    break;
                }
            }
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, static function (int $id): bool {
            return $id > 0;
        });

        return array_values(array_unique($ids));
    }
}
