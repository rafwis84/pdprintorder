<?php
define('_PS_VERSION_', '8.1.0');
define('_DB_PREFIX_', 'pdpo_test_');

class Db
{
    public static ?PDO $pdo = null;
    public static array $header = [];
    public static array $details = [];
    public static bool $failDetails = false;
    public static bool $failHeader = false;
    public static function getInstance(): self { return new self(); }
    public function executeS(string $sql)
    {
        $isDetailQuery = str_contains($sql, 'AS `slip_refunded_amount`');
        if (($isDetailQuery && self::$failDetails) || (!$isDetailQuery && self::$failHeader)) { return false; }
        if (self::$pdo !== null) { return self::$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
        return $isDetailQuery ? self::$details : [self::$header];
    }
}

require __DIR__ . '/../classes/PdPrintOrderPackingPdf.php';
if (in_array('--database', $argv, true)) {
    $dsn = getenv('PDPO_TEST_DSN');
    if (!$dsn) { throw new RuntimeException('Set PDPO_TEST_DSN to run SQL integration checks.'); }
    Db::$pdo = new PDO($dsn, getenv('PDPO_TEST_DB_USER') ?: 'root', getenv('PDPO_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    // Connection-scoped temporary tables never replace persistent shop tables.
    Db::$pdo->exec('CREATE TEMPORARY TABLE pdpo_test_order_slip (id_order_slip INT, id_order INT, total_products_tax_incl DECIMAL(20,6), total_shipping_tax_incl DECIMAL(20,6))');
    Db::$pdo->exec('CREATE TEMPORARY TABLE pdpo_test_order_detail (id_order_detail INT, id_order INT, total_refunded_tax_incl DECIMAL(20,6))');
    Db::$pdo->exec('CREATE TEMPORARY TABLE pdpo_test_order_slip_detail (id_order_slip INT, id_order_detail INT, amount_tax_incl DECIMAL(20,6))');
}

$method = new ReflectionMethod(PdPrintOrderPackingPdf::class, 'getRefundTotals');
$checks = 0;
foreach ([
    // Credit-slip product/shipping totals; per-detail recorded/slip amounts; expected product total.
    [0, 0, [], 0],
    [0, 0, [[20, 0]], 20],
    [20, 0, [[20, 20]], 20],
    [60, 0, [[0, 60], [20, 0]], 80],
    [20, 0, [[35, 20]], 35],
    [20, 5, [[20, 20]], 20],
    [80, 5, [[0, 60], [20, 0], [35, 20]], 115],
    [0, 0, [[0.10, 0], [0.20, 0]], 0.30],
] as [$products, $shipping, $details, $expectedProducts]) {
    Db::$header = ['refunded_products' => $products, 'refunded_shipping' => $shipping];
    Db::$details = [];
    if (Db::$pdo) {
        foreach (['order_slip_detail', 'order_detail', 'order_slip'] as $table) { Db::$pdo->exec('DELETE FROM pdpo_test_' . $table); }
        Db::$pdo->exec("INSERT INTO pdpo_test_order_slip VALUES (1,77,$products,$shipping),(99,88,999,999)");
        Db::$pdo->exec('INSERT INTO pdpo_test_order_detail VALUES (999,88,999)');
        Db::$pdo->exec('INSERT INTO pdpo_test_order_slip_detail VALUES (99,999,999)');
    }
    foreach ($details as $i => [$recorded, $slip]) {
        $id = $i + 1;
        Db::$details[] = ['id_order_detail' => $id, 'total_refunded_tax_incl' => $recorded, 'slip_refunded_amount' => $slip];
        if (Db::$pdo) {
            Db::$pdo->exec("INSERT INTO pdpo_test_order_detail VALUES ($id,77,$recorded)");
            Db::$pdo->exec("INSERT INTO pdpo_test_order_slip_detail VALUES (1,$id,$slip)");
        }
    }
    $result = $method->invoke(null, 77);
    foreach (['refunded_products' => $expectedProducts, 'refunded_shipping' => $shipping, 'refunded_total' => $expectedProducts + $shipping] as $key => $expected) {
        if (abs($result[$key] - $expected) > 0.000001) { throw new RuntimeException("Wrong $key: " . json_encode($result)); }
        ++$checks;
    }
    if ($products === 0 && $expectedProducts === 20) {
        if (100 - $result['refunded_total'] !== 80.0) { throw new RuntimeException('100 minus refund of 20 must equal 80'); }
        ++$checks;
    }
}
foreach (['failHeader', 'failDetails'] as $failure) {
    Db::$$failure = true;
    try {
        $method->invoke(null, 77);
        throw new LogicException('Failed database read must not silently produce incorrect totals');
    } catch (RuntimeException $error) { ++$checks; }
    Db::$$failure = false;
}
echo 'PASS: ', $checks, Db::$pdo ? ' refund SQL integration checks' : ' refund regression checks', PHP_EOL;
