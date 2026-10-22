<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PdPrintOrderOutput
{
    public static function clearBuffers(): void
    {
        while (ob_get_level() > 0) {
            $status = ob_get_status();
            $flags = (int) $status['flags'];
            if (($flags & PHP_OUTPUT_HANDLER_REMOVABLE) !== 0) {
                if (!ob_end_clean()) {
                    throw new RuntimeException('Nie udało się zamknąć bufora wyjścia przed wygenerowaniem PDF.');
                }
                continue;
            }

            // A server/module-owned buffer cannot be removed. Clean its pending
            // content when allowed, and leave it in place to handle the PDF.
            if (($flags & PHP_OUTPUT_HANDLER_CLEANABLE) !== 0 && ob_clean()) {
                // Older buffers are inaccessible beneath this one. Never emit
                // a PDF after HTML already waiting in any of those buffers.
                $buffers = ob_get_status(true);
                array_pop($buffers);
                foreach ($buffers as $buffer) {
                    if ((int) ($buffer['buffer_used'] ?? 0) > 0) {
                        throw new RuntimeException('Zewnętrzny bufor zawiera dane, których nie można usunąć przed wygenerowaniem PDF.');
                    }
                }
                return;
            }

            throw new RuntimeException('Bufor wyjścia serwera nie pozwala bezpiecznie przygotować pliku PDF.');
        }
    }
}
