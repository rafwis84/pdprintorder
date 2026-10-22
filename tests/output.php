<?php
define('_PS_VERSION_', '8.1.0');
require __DIR__ . '/../classes/PdPrintOrderPdf.php';
require __DIR__ . '/../classes/PdPrintOrderPackingPdf.php';

if (($argv[1] ?? '') === 'case') {
    set_time_limit(2);
    set_error_handler(function ($severity, $message) { throw new ErrorException($message, 0, $severity); });
    $class = $argv[2];
    $scenario = $argv[3];
    $method = new ReflectionMethod($class, 'clearOutputBuffers');
    if ($scenario === 'nested') {
        ob_start(); echo 'OUTER HTML';
        ob_start(); echo 'INNER HTML';
    } elseif ($scenario === 'locked-cleanable' || $scenario === 'locked-nested') {
        ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);
        echo 'STALE HTML';
        if ($scenario === 'locked-nested') { ob_start(); echo 'INNER HTML'; }
    } elseif ($scenario === 'locked-uncleanable') {
        ob_start(null, 0, PHP_OUTPUT_HANDLER_FLUSHABLE);
    } elseif ($scenario === 'dirty-outer') {
        ob_start(); echo 'OUTER HTML';
        ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);
    }
    $denied = false;
    try { $method->invoke(null); } catch (RuntimeException $error) { $denied = true; }
    $pending = ob_get_level() ? ob_get_contents() : '';
    fwrite(STDERR, json_encode(['denied' => $denied, 'level' => ob_get_level(), 'pending' => $pending], JSON_THROW_ON_ERROR));
    if (!$denied) {
        // Repeated cleanup must also terminate, and preserve clean locked buffers.
        $method->invoke(null);
        echo '%PDF-TEST';
    }
    exit;
}

$checks = 0;
foreach ([PdPrintOrderPdf::class, PdPrintOrderPackingPdf::class] as $class) {
    foreach (['empty', 'nested', 'locked-cleanable', 'locked-nested', 'locked-uncleanable', 'dirty-outer'] as $scenario) {
        $process = proc_open([PHP_BINARY, __FILE__, 'case', $class, $scenario], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException("$class/$scenario: $err"); }
        ++$checks;
        $result = json_decode($err, true, 512, JSON_THROW_ON_ERROR);
        $expectedDenial = in_array($scenario, ['locked-uncleanable', 'dirty-outer'], true);
        if ($result['denied'] !== $expectedDenial) { throw new RuntimeException("$class/$scenario: wrong buffer handling"); }
        ++$checks;
        if (!$expectedDenial && ($out !== '%PDF-TEST' || $result['pending'] !== '')) { throw new RuntimeException("$class/$scenario: HTML contaminates PDF"); }
        if ($expectedDenial && str_contains($out, '%PDF-TEST')) { throw new RuntimeException("$class/$scenario: PDF emitted on failure"); }
        ++$checks;
    }
}
echo "PASS: $checks output-buffer checks (both PDF paths, removable/locked/nested buffers)\n";
