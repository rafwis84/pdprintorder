<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/PdPrintOrderCommentRepository.php';
require_once __DIR__ . '/PdPrintOrderAccess.php';

class PdPrintOrderPackingPdf
{
    private const PAGE_LEFT = 10.0;
    private const PAGE_RIGHT = 10.0;
    private const TABLE_WIDTH = 190.0;

    private static array $productColumns = [
        'check' => 7.0,
        'lp' => 8.0,
        'id' => 13.0,
        'name' => 48.0,
        'comment' => 36.0,
        'manufacturer' => 24.0,
        'ean' => 24.0,
        'qty' => 9.0,
        'gross' => 21.0,
    ];

    public static function render(array $orderIds, Context $context): void
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (empty($orderIds)) {
            throw new Exception('Brak zamówień do wydruku.');
        }

        $orders = PdPrintOrderAccess::getOrders($orderIds, $context);

        if (!PdPrintOrderCommentRepository::ensureSchema()) {
            throw new Exception('Nie udało się przygotować tabeli komentarzy modułu.');
        }

        self::loadTcpdf();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('pdprintorder');
        $pdf->SetAuthor('RAFAŁ WIŚNIEWSKI');
        $pdf->SetTitle('Wydruk magazynowy zamówień');
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetMargins(self::PAGE_LEFT, 10, self::PAGE_RIGHT);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetFont('dejavusans', '', 9);

        $printedOrders = 0;

        foreach ($orders as $order) {
            ++$printedOrders;
            $pdf->AddPage();
            self::renderOrder($pdf, $order, $context);
        }

        if ($printedOrders === 0) {
            throw new Exception('Nie znaleziono prawidłowych zamówień do wydruku.');
        }

        $filename = 'zamowienia_magazyn_' . date('Y-m-d_H-i') . '.pdf';
        self::clearOutputBuffers();

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: private, max-age=0, must-revalidate');
        }

        echo $pdf->Output($filename, 'S');
        exit;
    }

    private static function renderOrder(TCPDF $pdf, Order $order, Context $context): void
    {
        $idOrder = (int) $order->id;
        $idLang = (int) $order->id_lang;
        $currency = new Currency((int) $order->id_currency);
        $orderRef = (string) $order->reference;
        $dateAdd = (string) $order->date_add;
        $payment = (string) $order->payment;

        $statusName = '';
        if ((int) $order->current_state > 0) {
            $orderState = new OrderState((int) $order->current_state, $idLang);
            if (Validate::isLoadedObject($orderState)) {
                $statusName = (string) $orderState->name;
            }
        }

        $customer = new Customer((int) $order->id_customer);
        $customerName = '';
        $customerEmail = '';
        if (Validate::isLoadedObject($customer)) {
            $customerName = trim((string) $customer->firstname . ' ' . (string) $customer->lastname);
            $customerEmail = (string) $customer->email;
        }

        $addressDelivery = new Address((int) $order->id_address_delivery);
        $addressInvoice = new Address((int) $order->id_address_invoice);

        $carrierName = '';
        if ((int) $order->id_carrier > 0) {
            $carrier = new Carrier((int) $order->id_carrier, $idLang);
            if (Validate::isLoadedObject($carrier)) {
                $carrierName = (string) $carrier->name;
            }
        }

        $apaczka = self::getApaczkaData($idOrder);
        $apSupplier = trim((string) ($apaczka['apaczka_supplier'] ?? ''));
        $apPoint = trim((string) ($apaczka['apaczka_point'] ?? ''));

        $refundedQtyMap = self::getRefundedQtyByOrderDetail($idOrder);
        $details = (array) OrderDetail::getList($idOrder);
        $productMeta = self::getProductMeta($details);
        $comments = PdPrintOrderCommentRepository::getCommentsForOrder($idOrder);
        $products = self::buildProductRows($details, $productMeta, $comments, $refundedQtyMap);

        usort($products, static function (array $a, array $b): int {
            $idA = (int) ($a['product_id'] ?? 0);
            $idB = (int) ($b['product_id'] ?? 0);
            if ($idA !== $idB) {
                return $idA <=> $idB;
            }

            $referenceA = self::lower((string) ($a['product_reference'] ?? ''));
            $referenceB = self::lower((string) ($b['product_reference'] ?? ''));
            if ($referenceA !== $referenceB) {
                return $referenceA <=> $referenceB;
            }

            return self::lower((string) ($a['product_name'] ?? '')) <=> self::lower((string) ($b['product_name'] ?? ''));
        });

        $discountLines = [];
        foreach ((array) $order->getCartRules() as $cartRule) {
            $name = (string) ($cartRule['name'] ?? '');
            $value = isset($cartRule['value']) ? (float) $cartRule['value'] : 0.0;
            if ($name !== '' && $value > 0) {
                $discountLines[] = $name . ' (-' . Tools::displayPrice($value, $currency) . ')';
            }
        }

        $productsGross = (float) $order->total_products_wt;
        $shippingGross = (float) $order->total_shipping_tax_incl;
        $discountTotal = (float) $order->total_discounts_tax_incl;
        $totalPaid = (float) $order->total_paid_tax_incl;
        $refundTotals = self::getRefundTotals($idOrder);
        $refundGross = (float) $refundTotals['refunded_total'];
        $totalAfterRefunds = max(0.0, $totalPaid - $refundGross);

        $orderedPositionsCount = count($products);
        $orderedPiecesCount = 0;
        $toShipPositionsCount = 0;
        $toShipPiecesCount = 0;

        foreach ($products as $product) {
            $ordered = (int) ($product['product_quantity'] ?? 0);
            $refunded = (int) ($product['_qty_refunded'] ?? 0);
            $net = max(0, $ordered - $refunded);
            $orderedPiecesCount += $ordered;
            $toShipPiecesCount += $net;
            if ($net > 0) {
                ++$toShipPositionsCount;
            }
        }

        self::renderHeader(
            $pdf,
            $idOrder,
            $orderRef,
            $dateAdd,
            $payment,
            $statusName,
            $orderedPositionsCount,
            $orderedPiecesCount,
            $toShipPositionsCount,
            $toShipPiecesCount
        );

        self::renderCustomer($pdf, $customerName, $customerEmail, $addressDelivery);
        self::renderDelivery($pdf, $carrierName, $apSupplier, $apPoint);
        self::renderAddresses($pdf, $addressDelivery, $addressInvoice, $idLang);
        self::renderProducts($pdf, $products, $currency);
        self::renderSummary(
            $pdf,
            $productsGross,
            $shippingGross,
            $discountTotal,
            $refundGross,
            $totalAfterRefunds,
            $currency,
            $discountLines,
            $idOrder
        );
    }

    private static function renderHeader(
        TCPDF $pdf,
        int $idOrder,
        string $orderRef,
        string $dateAdd,
        string $payment,
        string $statusName,
        int $orderedPositionsCount,
        int $orderedPiecesCount,
        int $toShipPositionsCount,
        int $toShipPiecesCount
    ): void {
        $leftX = 10.0;
        $topY = 10.0;
        $gapX = 4.0;
        $leftW = 116.0;
        $rightW = 70.0;
        $rightX = $leftX + $leftW + $gapX;
        $rightH = 42.0;

        $barcodeStyle = [
            'position' => '',
            'align' => 'C',
            'stretch' => false,
            'fitwidth' => true,
            'cellfitalign' => '',
            'border' => 0,
            'hpadding' => 'auto',
            'vpadding' => 'auto',
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'text' => false,
            'font' => 'dejavusans',
            'fontsize' => 8,
            'stretchtext' => 4,
        ];

        $pdf->Rect($rightX, $topY, $rightW, $rightH);
        $pdf->SetFont('dejavusans', 'B', 8);
        $pdf->SetXY($rightX + 2, $topY + 2);
        $pdf->Cell($rightW - 4, 4, 'KOD: ID ' . $idOrder, 0, 1, 'L');
        $pdf->write1DBarcode((string) $idOrder, 'C128', $rightX + 2, $topY + 7, $rightW - 4, 12, 0.4, $barcodeStyle, 'N');
        $pdf->SetXY($rightX + 2, $topY + 20);
        $pdf->Cell($rightW - 4, 4, 'KOD: REF ' . $orderRef, 0, 1, 'L');
        $pdf->write1DBarcode($orderRef, 'C128', $rightX + 2, $topY + 25, $rightW - 4, 12, 0.4, $barcodeStyle, 'N');

        $lines = [
            'Zamówienie: ' . $idOrder . ' - ' . $orderRef,
            'Data: ' . $dateAdd,
            'Płatność: ' . ($payment !== '' ? $payment : '-'),
        ];
        if ($statusName !== '') {
            $lines[] = 'Status: ' . $statusName;
        }
        $lines[] = 'Do wydania: Pozycji ' . $toShipPositionsCount . ' | Sztuk ' . $toShipPiecesCount;
        $lines[] = 'Zamówiono: Pozycji ' . $orderedPositionsCount . ' | Sztuk ' . $orderedPiecesCount;
        $lines[] = 'Paczka: ____ / ____';

        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->SetXY($leftX + 2, $topY + 2);
        $pdf->Cell($leftW - 4, 6, $lines[0], 0, 1, 'L');
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetXY($leftX + 2, $topY + 9);
        $pdf->MultiCell($leftW - 4, 5, implode("\n", array_slice($lines, 1)), 0, 'L', false);

        $leftH = max($rightH, (float) ceil(($pdf->GetY() - $topY) + 2));
        $pdf->Rect($leftX, $topY, $leftW, $leftH);
        $pdf->SetY($topY + max($leftH, $rightH) + 4);
    }

    private static function renderCustomer(TCPDF $pdf, string $customerName, string $customerEmail, Address $addressDelivery): void
    {
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 7, 'Klient', 0, 1, 'L');
        $pdf->SetFont('dejavusans', '', 9);

        $phone = '';
        $deliveryName = '';
        if (Validate::isLoadedObject($addressDelivery)) {
            $phone = (string) ($addressDelivery->phone_mobile ?: $addressDelivery->phone);
            $deliveryName = trim((string) $addressDelivery->firstname . ' ' . (string) $addressDelivery->lastname);
        }

        $name = $customerName !== '' ? $customerName : $deliveryName;
        $text = 'Imię i nazwisko: ' . ($name !== '' ? $name : '-') . "\n"
            . 'Telefon: ' . ($phone !== '' ? $phone : '-') . "\n"
            . 'Email: ' . ($customerEmail !== '' ? $customerEmail : '-');

        $pdf->MultiCell(0, 5, $text, 1, 'L', false);
    }

    private static function renderDelivery(TCPDF $pdf, string $carrierName, string $apSupplier, string $apPoint): void
    {
        $pdf->Ln(2);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 7, 'Dostawa', 0, 1, 'L');
        $pdf->SetFont('dejavusans', '', 9);

        $lines = ['Metoda dostawy: ' . ($carrierName !== '' ? $carrierName : '-')];
        if ($apSupplier !== '') {
            $lines[] = 'Przewoźnik: ' . $apSupplier;
        }
        if ($apPoint !== '') {
            $lines[] = 'Punkt odbioru: ' . $apPoint;
        }

        $pdf->MultiCell(0, 5, implode("\n", $lines), 1, 'L', false);

        if ($apPoint !== '') {
            $pdf->Ln(2);
            $pdf->SetFont('dejavusans', 'B', 12);
            $pdf->SetFillColor(245, 245, 245);
            $pdf->MultiCell(0, 9, 'PUNKT ODBIORU: ' . $apPoint, 1, 'L', true);
            $pdf->SetFont('dejavusans', '', 9);
        }
    }

    private static function renderAddresses(TCPDF $pdf, Address $addressDelivery, Address $addressInvoice, int $idLang): void
    {
        $deliveryText = self::formatAddressBlock($addressDelivery, $idLang);
        $invoiceText = self::formatAddressBlock($addressInvoice, $idLang);
        $columnWidth = 95.0;

        $pdf->SetFont('dejavusans', '', 9);
        $deliveryHeight = max(12.0, ((float) $pdf->getNumLines($deliveryText, $columnWidth - 2) * 5.0) + 2.0);
        $invoiceHeight = max(12.0, ((float) $pdf->getNumLines($invoiceText, $columnWidth - 2) * 5.0) + 2.0);
        $bodyHeight = max($deliveryHeight, $invoiceHeight);

        self::ensureVerticalSpace($pdf, 17.0 + $bodyHeight);
        $pdf->Ln(2);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 7, 'Dane adresowe', 0, 1, 'L');

        $startY = $pdf->GetY();
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetXY(10, $startY);
        $pdf->Cell($columnWidth, 6, 'Adres dostawy', 1, 0, 'L');
        $pdf->SetXY(105, $startY);
        $pdf->Cell($columnWidth, 6, 'Adres faktury', 1, 1, 'L');

        $bodyY = $startY + 6;
        self::drawTableCell($pdf, 10, $bodyY, $columnWidth, $bodyHeight, $deliveryText, 'L', '', 9);
        self::drawTableCell($pdf, 105, $bodyY, $columnWidth, $bodyHeight, $invoiceText, 'L', '', 9);
        $pdf->SetY($bodyY + $bodyHeight + 2);
    }

    private static function renderProducts(TCPDF $pdf, array $products, Currency $currency): void
    {
        self::ensureVerticalSpace($pdf, 20);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 7, 'Produkty', 0, 1, 'L');
        self::renderProductTableHeader($pdf);

        $rowNumber = 1;
        foreach ($products as $product) {
            $idProduct = (int) ($product['product_id'] ?? 0);
            $name = (string) ($product['product_name'] ?? '');
            $comment = (string) ($product['_comment'] ?? '');
            $manufacturer = (string) ($product['_manufacturer'] ?? '');
            $ean = (string) ($product['product_ean13'] ?? '');
            $qtyOrdered = (int) ($product['product_quantity'] ?? 0);
            $qtyRefunded = (int) ($product['_qty_refunded'] ?? 0);
            $qtyNet = max(0, $qtyOrdered - $qtyRefunded);
            $gross = (float) ($product['_unit_gross'] ?? 0.0);
            $fullyRefunded = $qtyOrdered > 0 && $qtyNet === 0;

            if ($qtyRefunded > 0) {
                if ($fullyRefunded) {
                    $name .= "\nZWROT 100% · do wydania: 0 · zwrócono: " . $qtyRefunded;
                } else {
                    $name .= "\nZwrócono: " . $qtyRefunded;
                }
            }

            $fontStyle = $fullyRefunded ? 'B' : '';
            $rowHeight = self::calculateProductRowHeight($pdf, $name, $comment, $manufacturer, $ean);

            if (self::needsPageBreak($pdf, $rowHeight)) {
                $pdf->AddPage();
                $pdf->SetFont('dejavusans', 'B', 10);
                $pdf->Cell(0, 6, 'Produkty — ciąg dalszy', 0, 1, 'L');
                self::renderProductTableHeader($pdf);
            }

            $x = self::PAGE_LEFT;
            $y = $pdf->GetY();
            $columns = self::$productColumns;

            self::drawTableCell($pdf, $x, $y, $columns['check'], $rowHeight, '☐', 'C', '', 8);
            $x += $columns['check'];
            self::drawTableCell($pdf, $x, $y, $columns['lp'], $rowHeight, (string) $rowNumber, 'C', '', 8);
            $x += $columns['lp'];
            self::drawTableCell($pdf, $x, $y, $columns['id'], $rowHeight, (string) $idProduct, 'C', '', 8);
            $x += $columns['id'];
            self::drawTableCell($pdf, $x, $y, $columns['name'], $rowHeight, $name, 'L', $fontStyle, 8);
            $x += $columns['name'];
            self::drawTableCell($pdf, $x, $y, $columns['comment'], $rowHeight, $comment, 'L', '', 8);
            $x += $columns['comment'];
            self::drawTableCell($pdf, $x, $y, $columns['manufacturer'], $rowHeight, $manufacturer, 'L', $fontStyle, 8);
            $x += $columns['manufacturer'];
            self::drawTableCell($pdf, $x, $y, $columns['ean'], $rowHeight, $ean, 'L', '', 8);
            $x += $columns['ean'];
            self::drawTableCell($pdf, $x, $y, $columns['qty'], $rowHeight, (string) $qtyNet, 'C', '', 8);
            $x += $columns['qty'];
            self::drawTableCell($pdf, $x, $y, $columns['gross'], $rowHeight, Tools::displayPrice($gross, $currency), 'R', '', 8);

            $pdf->SetY($y + $rowHeight);
            ++$rowNumber;
        }
    }

    private static function renderProductTableHeader(TCPDF $pdf): void
    {
        $columns = self::$productColumns;
        $pdf->SetFont('dejavusans', 'B', 7.4);
        $pdf->Cell($columns['check'], 8, '', 1, 0, 'C');
        $pdf->Cell($columns['lp'], 8, 'Lp.', 1, 0, 'C');
        $pdf->Cell($columns['id'], 8, 'ID', 1, 0, 'C');
        $pdf->Cell($columns['name'], 8, 'Nazwa', 1, 0, 'L');
        $pdf->Cell($columns['comment'], 8, 'Komentarz', 1, 0, 'L');
        $pdf->Cell($columns['manufacturer'], 8, 'Producent', 1, 0, 'L');
        $pdf->Cell($columns['ean'], 8, 'EAN13', 1, 0, 'L');
        $pdf->Cell($columns['qty'], 8, 'Ilość', 1, 0, 'C');
        $pdf->Cell($columns['gross'], 8, 'Brutto', 1, 1, 'R');
    }

    private static function calculateProductRowHeight(TCPDF $pdf, string $name, string $comment, string $manufacturer, string $ean): float
    {
        $columns = self::$productColumns;
        $pdf->SetFont('dejavusans', '', 8);
        $lineHeight = 4.0;
        $minimumHeight = 8.0;

        $heights = [
            max(1, (int) $pdf->getNumLines($name, $columns['name'] - 1)) * $lineHeight + 2,
            max(1, (int) $pdf->getNumLines($comment, $columns['comment'] - 1)) * $lineHeight + 2,
            max(1, (int) $pdf->getNumLines($manufacturer, $columns['manufacturer'] - 1)) * $lineHeight + 2,
            max(1, (int) $pdf->getNumLines($ean, $columns['ean'] - 1)) * $lineHeight + 2,
        ];

        return max($minimumHeight, (float) max($heights));
    }

    private static function drawTableCell(
        TCPDF $pdf,
        float $x,
        float $y,
        float $width,
        float $height,
        string $text,
        string $align,
        string $fontStyle,
        float $fontSize
    ): void {
        $pdf->SetFont('dejavusans', $fontStyle, $fontSize);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell(
            $width,
            $height,
            $text,
            1,
            $align,
            false,
            0,
            $x,
            $y,
            true,
            0,
            false,
            true,
            $height,
            'M'
        );
    }

    private static function renderSummary(
        TCPDF $pdf,
        float $productsGross,
        float $shippingGross,
        float $discountTotal,
        float $refundGross,
        float $totalAfterRefunds,
        Currency $currency,
        array $discountLines,
        int $idOrder
    ): void {
        self::ensureVerticalSpace($pdf, 48);
        $pdf->Ln(2);
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->Cell(0, 7, 'Podsumowanie', 0, 1, 'L');
        $pdf->SetFont('dejavusans', '', 9);

        self::summaryRow($pdf, 'Wartość brutto produktów:', Tools::displayPrice($productsGross, $currency));
        self::summaryRow($pdf, 'Koszt wysyłki:', Tools::displayPrice($shippingGross, $currency));
        self::summaryRow($pdf, 'Rabat:', ($discountTotal > 0 ? '-' : '') . Tools::displayPrice($discountTotal, $currency));
        self::summaryRow($pdf, 'Zwroty / korekty:', ($refundGross > 0 ? '-' : '') . Tools::displayPrice($refundGross, $currency));

        $pdf->SetFont('dejavusans', 'B', 10);
        self::summaryRow($pdf, 'Razem po zwrotach:', Tools::displayPrice($totalAfterRefunds, $currency), 7);
        $pdf->SetFont('dejavusans', '', 9);

        if (!empty($discountLines)) {
            self::ensureVerticalSpace($pdf, 15);
            $pdf->Ln(1);
            $pdf->MultiCell(0, 5, "Użyte rabaty:\n- " . implode("\n- ", $discountLines), 1, 'L', false);
        }

        $slips = self::getOrderSlips($idOrder);
        if (!empty($slips)) {
            self::ensureVerticalSpace($pdf, 14 + (count($slips) * 5));
            $pdf->Ln(2);
            $pdf->SetFont('dejavusans', 'B', 11);
            $pdf->Cell(0, 7, 'Zwroty / korekty', 0, 1, 'L');
            $pdf->SetFont('dejavusans', '', 9);

            foreach ($slips as $slip) {
                $idSlip = (int) ($slip['id_order_slip'] ?? 0);
                $date = (string) ($slip['date_add'] ?? '');
                $value = (float) ($slip['total_products_tax_incl'] ?? 0)
                    + (float) ($slip['total_shipping_tax_incl'] ?? 0);
                $pdf->Cell(
                    0,
                    5,
                    'Korekta #' . $idSlip . ' | ' . $date . ' | ' . Tools::displayPrice($value, $currency),
                    0,
                    1,
                    'L'
                );
            }
        }
    }

    private static function summaryRow(TCPDF $pdf, string $label, string $value, float $height = 6.0): void
    {
        $pdf->Cell(70, $height, $label, 1, 0, 'L');
        $pdf->Cell(0, $height, $value, 1, 1, 'R');
    }

    private static function buildProductRows(array $details, array $productMeta, array $comments, array $refundedQtyMap): array
    {
        $products = [];

        foreach ($details as $detail) {
            $idOrderDetail = (int) ($detail['id_order_detail'] ?? 0);
            $idProduct = (int) ($detail['product_id'] ?? 0);
            $meta = $productMeta[$idProduct] ?? [];
            $ean = (string) ($detail['product_ean13'] ?? '');

            if ($ean === '') {
                $ean = (string) ($meta['ean13'] ?? '');
            }

            $gross = 0.0;
            if (isset($detail['unit_price_tax_incl'])) {
                $gross = (float) $detail['unit_price_tax_incl'];
            } elseif (isset($detail['product_price_wt'])) {
                $gross = (float) $detail['product_price_wt'];
            } elseif (isset($detail['product_price'])) {
                $gross = (float) $detail['product_price'];
            }

            $products[] = [
                'id_order_detail' => $idOrderDetail,
                'product_id' => $idProduct,
                'product_name' => (string) ($detail['product_name'] ?? ''),
                'product_reference' => (string) ($detail['product_reference'] ?? ''),
                'product_ean13' => $ean,
                'product_quantity' => (int) ($detail['product_quantity'] ?? 0),
                '_manufacturer' => (string) ($meta['manufacturer'] ?? ''),
                '_comment' => (string) ($comments[$idOrderDetail] ?? ''),
                '_qty_refunded' => (int) ($refundedQtyMap[$idOrderDetail] ?? 0),
                '_unit_gross' => $gross,
            ];
        }

        return $products;
    }

    private static function getProductMeta(array $details): array
    {
        $productIds = [];
        foreach ($details as $detail) {
            $idProduct = (int) ($detail['product_id'] ?? 0);
            if ($idProduct > 0) {
                $productIds[$idProduct] = $idProduct;
            }
        }

        if (empty($productIds)) {
            return [];
        }

        $idsSql = implode(',', array_map('intval', array_values($productIds)));
        $rows = Db::getInstance()->executeS(
            'SELECT p.`id_product`, p.`ean13`, m.`name` AS `manufacturer`
             FROM `' . _DB_PREFIX_ . 'product` p
             LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m
               ON m.`id_manufacturer` = p.`id_manufacturer`
             WHERE p.`id_product` IN (' . $idsSql . ')'
        );

        $meta = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $meta[(int) $row['id_product']] = [
                    'ean13' => (string) ($row['ean13'] ?? ''),
                    'manufacturer' => (string) ($row['manufacturer'] ?? ''),
                ];
            }
        }

        return $meta;
    }

    private static function getApaczkaData(int $idOrder): array
    {
        static $available = null;

        if ($available === null) {
            $available = [];
            $columns = Db::getInstance()->executeS('SHOW COLUMNS FROM `' . _DB_PREFIX_ . 'orders`');

            if (is_array($columns)) {
                foreach ($columns as $column) {
                    $field = (string) ($column['Field'] ?? '');
                    if ($field !== '') {
                        $available[$field] = true;
                    }
                }
            }
        }

        $select = [];
        if (isset($available['apaczka_supplier'])) {
            $select[] = '`apaczka_supplier`';
        }
        if (isset($available['apaczka_point'])) {
            $select[] = '`apaczka_point`';
        }

        if (empty($select)) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT ' . implode(', ', $select) . '
             FROM `' . _DB_PREFIX_ . 'orders`
             WHERE `id_order` = ' . (int) $idOrder
        );

        if (!is_array($rows) || empty($rows)) {
            return [];
        }

        return (array) $rows[0];
    }

    private static function getRefundedQtyByOrderDetail(int $idOrder): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT osd.`id_order_detail`, SUM(osd.`product_quantity`) AS `qty`
             FROM `' . _DB_PREFIX_ . 'order_slip_detail` osd
             INNER JOIN `' . _DB_PREFIX_ . 'order_slip` os
               ON os.`id_order_slip` = osd.`id_order_slip`
             WHERE os.`id_order` = ' . (int) $idOrder . '
             GROUP BY osd.`id_order_detail`'
        );

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $map[(int) $row['id_order_detail']] = (int) $row['qty'];
            }
        }

        return $map;
    }

    private static function getRefundTotals(int $idOrder): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT
                COALESCE(SUM(`total_products_tax_incl`), 0) AS `refunded_products`,
                COALESCE(SUM(`total_shipping_tax_incl`), 0) AS `refunded_shipping`
             FROM `' . _DB_PREFIX_ . 'order_slip`
             WHERE `id_order` = ' . (int) $idOrder
        );

        $row = is_array($rows) && !empty($rows) ? (array) $rows[0] : [];
        $refundedProducts = (float) ($row['refunded_products'] ?? 0);
        $refundedShipping = (float) ($row['refunded_shipping'] ?? 0);

        return [
            'refunded_products' => $refundedProducts,
            'refunded_shipping' => $refundedShipping,
            'refunded_total' => $refundedProducts + $refundedShipping,
        ];
    }

    private static function getOrderSlips(int $idOrder): array
    {
        $rows = Db::getInstance()->executeS(
            'SELECT `id_order_slip`, `date_add`, `total_products_tax_incl`, `total_shipping_tax_incl`
             FROM `' . _DB_PREFIX_ . 'order_slip`
             WHERE `id_order` = ' . (int) $idOrder . '
             ORDER BY `date_add` ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    private static function formatAddressBlock(Address $address, int $idLang): string
    {
        if (!Validate::isLoadedObject($address)) {
            return '-';
        }

        $lines = [];
        if (!empty($address->company)) {
            $lines[] = (string) $address->company;
        }

        $fullName = trim((string) $address->firstname . ' ' . (string) $address->lastname);
        if ($fullName !== '') {
            $lines[] = $fullName;
        }
        if (!empty($address->address1)) {
            $lines[] = (string) $address->address1;
        }
        if (!empty($address->address2)) {
            $lines[] = (string) $address->address2;
        }

        $city = trim((string) $address->postcode . ' ' . (string) $address->city);
        if ($city !== '') {
            $lines[] = $city;
        }

        if ((int) $address->id_country > 0) {
            $country = new Country((int) $address->id_country, $idLang);
            if (Validate::isLoadedObject($country) && !empty($country->name)) {
                $lines[] = (string) $country->name;
            }
        }

        if (!empty($address->phone)) {
            $lines[] = 'Tel.: ' . (string) $address->phone;
        }
        if (!empty($address->phone_mobile)) {
            $lines[] = 'Tel. kom.: ' . (string) $address->phone_mobile;
        }
        if (!empty($address->vat_number)) {
            $lines[] = 'NIP: ' . (string) $address->vat_number;
        }

        return implode("\n", $lines);
    }

    private static function ensureVerticalSpace(TCPDF $pdf, float $requiredHeight): void
    {
        if (self::needsPageBreak($pdf, $requiredHeight)) {
            $pdf->AddPage();
        }
    }

    private static function needsPageBreak(TCPDF $pdf, float $requiredHeight): bool
    {
        $bottom = (float) $pdf->getPageHeight() - (float) $pdf->getBreakMargin();
        return ((float) $pdf->GetY() + $requiredHeight) > $bottom;
    }

    private static function loadTcpdf(): void
    {
        if (class_exists('TCPDF')) {
            return;
        }

        $candidates = [
            _PS_TOOL_DIR_ . 'tcpdf/tcpdf.php',
            _PS_ROOT_DIR_ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
            _PS_ROOT_DIR_ . '/vendor/tcpdf/tcpdf.php',
            _PS_ROOT_DIR_ . '/classes/pdf/tcpdf/tcpdf.php',
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path)) {
                require_once $path;
                if (class_exists('TCPDF')) {
                    return;
                }
            }
        }

        throw new Exception('Nie znaleziono biblioteki TCPDF wymaganej do wygenerowania wydruku.');
    }

    private static function lower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private static function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}
