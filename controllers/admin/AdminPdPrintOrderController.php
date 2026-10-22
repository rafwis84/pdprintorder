<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/../../classes/PdPrintOrderAccess.php';

class AdminPdPrintOrderController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->bootstrap = true;
        $this->meta_title = $this->l('Drukowanie zamówień');
    }

    public function initContent()
    {
        // Żądania AJAX obsługujemy w postProcess(). Nie wolno ich przekierowywać
        // do listy zamówień, ponieważ frontend oczekuje odpowiedzi JSON/PDF.
        if ($this->isPdpoAjaxRequest()) {
            return;
        }

        parent::initContent();
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders'));
    }

    public function postProcess()
    {
        if ($this->isPdpoAjaxRequest()) {
            $action = (string) Tools::getValue('action', '');

            if ($action === 'saveComment') {
                $this->ajaxProcessSaveComment();
                return;
            }

            if ($action === 'printOrder') {
                $this->ajaxProcessPrintOrder();
                return;
            }

            $this->sendJson(false, 'Nieznana akcja modułu Drukowanie zamówień.', 400);
            return;
        }

        parent::postProcess();
    }

    public function ajaxProcessPrintOrder(): void
    {
        try {
            $this->assertValidRequest();
            $idOrder = (int) Tools::getValue('id_order');
            if ($idOrder <= 0) {
                $this->sendJson(false, 'Brak prawidłowego ID zamówienia.', 400);
            }

            PdPrintOrderAccess::getOrders([$idOrder], $this->context);

            require_once _PS_MODULE_DIR_ . 'pdprintorder/classes/PdPrintOrderPackingPdf.php';
            PdPrintOrderPackingPdf::render([$idOrder], $this->context);
            exit;
        } catch (PdPrintOrderAccessException $exception) {
            $this->sendJson(false, $exception->getMessage(), $exception->getCode());
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                'pdprintorder: błąd generowania PDF: ' . $exception->getMessage(),
                3,
                null,
                'Order',
                (int) Tools::getValue('id_order'),
                true
            );
            $this->sendJson(false, 'Nie udało się wygenerować wydruku PDF. Szczegóły zapisano w logu PrestaShop.', 500);
        }
    }

    public function ajaxProcessSaveComment(): void
    {
        try {
            $this->assertValidRequest('update');
            if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
                $this->sendJson(false, 'Komentarz można zapisać wyłącznie metodą POST.', 405);
            }

            require_once _PS_MODULE_DIR_ . 'pdprintorder/classes/PdPrintOrderCommentRepository.php';

            $idOrder = (int) Tools::getValue('id_order');
            $idOrderDetail = (int) Tools::getValue('id_order_detail');
            $comment = (string) Tools::getValue('comment', '');
            $idEmployee = isset($this->context->employee->id) ? (int) $this->context->employee->id : 0;

            if ($idOrder <= 0 || $idOrderDetail <= 0) {
                $this->sendJson(false, 'Brak wymaganych danych pozycji zamówienia.', 400);
            }

            PdPrintOrderAccess::getOrders([$idOrder], $this->context, 'update');

            if (!PdPrintOrderCommentRepository::saveComment($idOrder, $idOrderDetail, $comment, $idEmployee)) {
                $this->sendJson(false, 'Nie udało się zapisać komentarza. Sprawdź, czy pozycja należy do tego zamówienia.', 400);
            }

            $normalizedComment = trim(strip_tags($comment));
            $normalizedComment = (string) Tools::substr(
                $normalizedComment,
                0,
                PdPrintOrderCommentRepository::MAX_COMMENT_LENGTH
            );

            $this->sendJson(true, 'Komentarz zapisany.', 200, [
                'comment' => $normalizedComment,
            ]);
        } catch (PdPrintOrderAccessException $exception) {
            $this->sendJson(false, $exception->getMessage(), $exception->getCode());
        } catch (Throwable $exception) {
            PrestaShopLogger::addLog(
                'pdprintorder: błąd zapisu komentarza: ' . $exception->getMessage(),
                3,
                null,
                'Order',
                (int) Tools::getValue('id_order'),
                true
            );
            $this->sendJson(false, 'Wystąpił błąd podczas zapisu komentarza. Szczegóły zapisano w logu PrestaShop.', 500);
        }
    }

    private function isPdpoAjaxRequest(): bool
    {
        return (string) Tools::getValue('ajax', '') === '1'
            || !empty($this->ajax);
    }

    private function assertValidRequest(string $permission = 'read'): void
    {
        PdPrintOrderAccess::assertPermission($this->context, $permission);

        $providedToken = (string) Tools::getValue('token', '');
        $expectedToken = (string) Tools::getAdminTokenLite('AdminPdPrintOrder');
        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            $this->sendJson(false, 'Nieprawidłowy token bezpieczeństwa.', 403);
        }
    }

    private function sendJson(bool $success, string $message, int $statusCode, array $extra = []): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        $payload = json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        if ($payload === false) {
            http_response_code(500);
            $payload = '{"success":false,"message":"Nie udało się przygotować odpowiedzi serwera."}';
        }

        if (method_exists($this, 'ajaxDie')) {
            $this->ajaxDie($payload);
        }

        echo $payload;
        exit;
    }
}
