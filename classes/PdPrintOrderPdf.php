<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PdPrintOrderPdf
{
    public static function render(string $type, array $orderIds, Context $context): void
    {
        $type = $type === 'delivery' ? 'delivery' : 'invoice';
        $documents = [];

        foreach ($orderIds as $idOrder) {
            $order = new Order((int) $idOrder);
            if (!Validate::isLoadedObject($order)) {
                continue;
            }

            $invoices = $order->getInvoicesCollection();
            foreach ($invoices as $invoice) {
                if (!Validate::isLoadedObject($invoice)) {
                    continue;
                }

                if ($type === 'invoice' && (int) $invoice->number <= 0) {
                    continue;
                }

                if ($type === 'delivery' && (int) $invoice->delivery_number <= 0) {
                    continue;
                }

                $documents[] = $invoice;
            }
        }

        if (empty($documents)) {
            if ($type === 'delivery') {
                throw new Exception('Brak dokumentów WZ do wydruku dla wybranych zamówień.');
            }
            throw new Exception('Brak faktur do wydruku dla wybranych zamówień.');
        }

        if ($type === 'invoice') {
            $pdf = new PDF($documents, PDF::TEMPLATE_INVOICE, $context->smarty);
            $filename = 'faktury_' . date('Y-m-d_H-i') . '.pdf';
        } else {
            $pdf = new PDF($documents, PDF::TEMPLATE_DELIVERY_SLIP, $context->smarty);
            $filename = 'wz_' . date('Y-m-d_H-i') . '.pdf';
        }

        self::clearOutputBuffers();
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
        }

        $pdf->render(true);
        exit;
    }

    private static function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
