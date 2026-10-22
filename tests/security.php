<?php
// Isolated regression tests: execute the module's real code against test doubles.
namespace Symfony\Component\HttpFoundation {
    class Request
    {
        public object $request;
        public function __construct(array $data)
        {
            $this->request = new class($data) {
                public function __construct(private array $data) {}
                public function all(): array { return $this->data; }
            };
        }
    }
    class Response
    {
        public function __construct(public string $content = '', public int $status = 200, array $headers = []) {}
    }
    class RedirectResponse extends Response {}
}

namespace {
    define('_PS_VERSION_', '8.1.0');
    define('_PS_MODULE_DIR_', dirname(__DIR__, 2) . '/');
    define('_DB_PREFIX_', 'ps_');
    define('_MYSQL_ENGINE_', 'InnoDB');

    class Employee
    {
        public int $id = 1;
        public int $id_profile = 2;
        public bool $logged = true;
        public function isLoggedBack(): bool { return $this->logged; }
        public function hasAuthOnShop(int $shop): bool { return $shop === 1; }
    }
    class Context
    {
        public ?Employee $employee;
        public object $link;
        public function __construct() { $this->employee = new Employee(); }
        public static function getContext(): self { return $GLOBALS['context']; }
    }
    class Validate
    {
        public static function isLoadedObject($object): bool { return is_object($object) && $object->id > 0; }
    }
    class Access
    {
        public static bool $read = true;
        public static bool $update = true;
        public static function isGranted($role, $profile): bool
        {
            if ($profile !== 2) { throw new \RuntimeException('Wrong employee profile'); }
            return match ($role) {
                'ROLE_MOD_TAB_ADMINORDERS_READ' => self::$read,
                'ROLE_MOD_TAB_ADMINORDERS_UPDATE' => self::$update,
                default => throw new \RuntimeException('Unexpected permission: ' . $role),
            };
        }
    }
    class Order
    {
        public int $id;
        public int $id_shop;
        public static int $invoiceReads = 0;
        public function __construct(int $id)
        {
            $this->id = in_array($id, [1, 2], true) ? $id : 0;
            $this->id_shop = $id;
        }
        public function getInvoicesCollection(): array { ++self::$invoiceReads; return []; }
    }
    class Tools
    {
        // Deliberately no jsonEncode(), matching PrestaShop 8.1.
        public static function getValue($key, $default = false) { return $_POST[$key] ?? $default; }
        public static function getAdminTokenLite($tab): string { return 'valid-token'; }
        public static function substr($value, $start, $length): string { return substr($value, $start, $length); }
    }
    class Configuration
    {
        public static function get($key): string { return '1.2.0'; }
        public static function updateValue($key, $value): bool { return true; }
    }
    class Db
    {
        public static int $queries = 0;
        public static int $writes = 0;
        public static bool $fail = false;
        public static function getInstance(): self { return new self(); }
        public function executeS($sql): array
        {
            ++self::$queries;
            if (str_starts_with($sql, 'SHOW TABLES')) { return [['table' => 'ps_pdprintorder_comment']]; }
            if (str_contains($sql, '`id_order_detail` = 11') && str_contains($sql, '`id_order` = 1')) {
                return [['product_id' => 5]];
            }
            return [];
        }
        public function execute($sql): bool { ++self::$writes; return !self::$fail; }
    }
    function pSQL($value): string { return addslashes($value); }
    class PrestaShopLogger
    {
        public static int $errors = 0;
        public static function addLog(...$args): void { ++self::$errors; }
    }
    class ModuleAdminController
    {
        public Context $context;
        public bool $bootstrap;
        public string $meta_title;
        public function __construct() { $this->context = Context::getContext(); }
        public function l($value) { return $value; }
        protected function ajaxDie($payload): void
        {
            echo json_encode([
                'status' => http_response_code(), 'body' => json_decode($payload, true, 512, JSON_THROW_ON_ERROR),
                'writes' => Db::$writes, 'queries' => Db::$queries, 'logs' => PrestaShopLogger::$errors,
            ], JSON_THROW_ON_ERROR);
            exit;
        }
    }
    class Module
    {
        public Context $context;
    }

    require_once __DIR__ . '/../classes/PdPrintOrderAccess.php';
    require_once __DIR__ . '/../classes/PdPrintOrderPdf.php';
    require_once __DIR__ . '/../classes/PdPrintOrderPackingPdf.php';
    require_once __DIR__ . '/../controllers/admin/AdminPdPrintOrderController.php';
    require_once __DIR__ . '/../src/Controller/Admin/PdPrintOrderBulkController.php';
    require_once __DIR__ . '/../pdprintorder.php';

    $context = new Context();

    if (($argv[1] ?? '') === 'case') {
        $scenario = $argv[2];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['token' => 'valid-token', 'id_order' => 1, 'id_order_detail' => 11,
            'comment' => ' Uwagi <b>test</b> ', 'ajax' => '1', 'action' => 'saveComment'];
        switch ($scenario) {
            case 'no-read-save': case 'no-read-print': Access::$read = false; break;
            case 'readonly-save': Access::$update = false; break;
            case 'foreign-save': case 'foreign-print': $_POST['id_order'] = 2; break;
            case 'bad-token': $_POST['token'] = 'wrong'; break;
            case 'get-save': $_SERVER['REQUEST_METHOD'] = 'GET'; break;
            case 'missing-order': $_POST['id_order'] = 999; break;
            case 'wrong-detail': $_POST['id_order_detail'] = 12; break;
            case 'database-failure': Db::$fail = true; break;
            case 'expired': $context->employee->logged = false; break;
            case 'anonymous': $context->employee = null; break;
            case 'invalid-utf8': $_POST['comment'] = "test\xFF"; break;
        }
        if (str_ends_with($scenario, '-print')) { $_POST['action'] = 'printOrder'; }
        (new AdminPdPrintOrderController())->postProcess();
        throw new \RuntimeException('Controller did not terminate with a response');
    }

    $checks = 0;
    function check(bool $condition, string $message): void
    {
        global $checks;
        if (!$condition) { throw new \RuntimeException($message); }
        ++$checks;
    }
    foreach ([
        'save-ok' => 200, 'invalid-utf8' => 200,
        'no-read-save' => 403, 'no-read-print' => 403, 'readonly-save' => 403,
        'foreign-save' => 403, 'foreign-print' => 403, 'bad-token' => 403,
        'get-save' => 405, 'missing-order' => 404, 'wrong-detail' => 400,
        'database-failure' => 400, 'expired' => 401, 'anonymous' => 401,
    ] as $scenario => $status) {
        $process = proc_open([PHP_BINARY, __FILE__, 'case', $scenario], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        check(proc_close($process) === 0 && $err === '', "$scenario: unexpected PHP error: $err");
        $response = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        check($response['status'] === $status, "$scenario: wrong HTTP status");
        check($response['body']['success'] === ($status === 200), "$scenario: wrong success flag");
        check($response['writes'] === (in_array($scenario, ['save-ok', 'invalid-utf8', 'database-failure'], true) ? 1 : 0), "$scenario: unauthorized write");
        if ($status === 403 || $status === 401) { check($response['queries'] === 0, "$scenario: accessed comment data before authorization"); }
        if ($scenario === 'save-ok') { check($response['body']['comment'] === 'Uwagi test', 'JSON lost normalized comment'); }
    }

    Access::$update = false;
    check(count(PdPrintOrderAccess::getOrders([1, 1], $context)) === 1, 'Read-only employee cannot read own-shop order');
    foreach (['invoice', 'delivery', 'packing'] as $type) {
        try {
            if ($type === 'packing') { PdPrintOrderPackingPdf::render([1, 2], $context); }
            else { PdPrintOrderPdf::render($type, [1, 2], $context); }
            throw new \RuntimeException('Foreign shop PDF was permitted');
        } catch (PdPrintOrderAccessException $e) {
            check($e->getCode() === 403, "$type: incorrect access denial");
            check(Order::$invoiceReads === 0 && Db::$queries === 0, "$type: data read before the entire batch was authorized");
        }
    }

    $controller = new \PdPrintOrder\Controller\Admin\PdPrintOrderBulkController();
    $request = new \Symfony\Component\HttpFoundation\Request(['order_orders_bulk' => [1, 2]]);
    foreach (['bulkInvoice', 'bulkDelivery', 'bulkPacking'] as $method) {
        check($controller->$method($request)->status === 403, "$method: access denial not preserved in HTTP response");
    }

    $buildRows = new \ReflectionMethod(PdPrintOrderPackingPdf::class, 'buildProductRows');
    foreach ([
        // ordered, refunded, returned, credit-slip quantity, expected remaining
        [5, 0, 0, 0, 5], [5, 2, 1, 0, 2], [5, 2, 0, 2, 3],
        [5, 0, 0, 2, 3], [5, 2, 1, 2, 2], [5, 0, 5, 0, 0],
        [5, 9, 9, 9, 0], [5, -1, -1, -1, 5],
    ] as [$ordered, $refunded, $returned, $slip, $expected]) {
        $row = $buildRows->invoke(null, [[
            'id_order_detail' => 11, 'product_quantity' => $ordered,
            'product_quantity_refunded' => $refunded, 'product_quantity_return' => $returned,
        ]], [], [], [11 => $slip])[0];
        check($row['product_quantity'] - $row['_qty_refunded'] === $expected, 'Wrong remaining quantity after refund/return');
    }

    $module = (new \ReflectionClass(PdPrintOrder::class))->newInstanceWithoutConstructor();
    $module->context = $context;
    $actionUrl = new \ReflectionMethod(PdPrintOrder::class, 'getOrderActionUrl');
    foreach ([true, false] as $tokensEnabled) {
        $context->link = new class($tokensEnabled) {
            public function __construct(private bool $tokensEnabled) {}
            public function getAdminLink($controller, $withToken = true, $sfParams = [], $params = []): string
            {
                if ($this->tokensEnabled && $withToken) { $params['token'] = Tools::getAdminTokenLite($controller); }
                return '/admin/index.php?' . http_build_query(['controller' => $controller] + $params);
            }
        };
        foreach (['saveComment', 'printOrder'] as $action) {
            parse_str(parse_url($actionUrl->invoke($module, $action, $action === 'printOrder' ? 1 : 0), PHP_URL_QUERY), $query);
            check(($query['token'] ?? '') === 'valid-token', 'Missing CSRF token when global tokens are disabled');
            check($query['ajax'] === '1' && $query['action'] === $action, 'Wrong action URL');
            check($action !== 'printOrder' || $query['id_order'] === '1', 'Missing order ID in print URL');
        }
    }
    echo "PASS: $checks PHP regression checks\n";
}
