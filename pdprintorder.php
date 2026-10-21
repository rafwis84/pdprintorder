<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

$pdPrintOrderAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($pdPrintOrderAutoload)) {
    require_once $pdPrintOrderAutoload;
}

require_once __DIR__ . '/classes/PdPrintOrderCommentRepository.php';

class PdPrintOrder extends Module
{
    public function __construct()
    {
        $this->name = 'pdprintorder';
        $this->tab = 'administration';
        $this->version = '1.2.3';
        $this->author = 'RAFAŁ WIŚNIEWSKI';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Drukowanie zamówień i komentarze magazynowe');
        $this->description = $this->l('Drukuje dokumenty zamówień i pozwala zapisać krótki komentarz przy każdej pozycji do wydruku magazynowego.');
        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => '8.1.99'];
    }

    public function install()
    {
        $installed = parent::install()
            && PdPrintOrderCommentRepository::installSchema()
            && $this->ensureHiddenTab()
            && $this->registerHook('displayAdminOrderTop')
            && $this->registerHook('displayAdminOrderSide')
            && $this->registerHook('displayAdminOrderMain')
            && $this->registerHook('actionOrderGridDefinitionModifier');

        if ($installed && method_exists('Tools', 'clearSf2Cache')) {
            Tools::clearSf2Cache();
        }

        return $installed;
    }

    public function uninstall()
    {
        // Celowo nie usuwamy tabeli komentarzy ani zapisanych danych.
        return $this->uninstallTab() && parent::uninstall();
    }

    public function ensureHiddenTab(): bool
    {
        $idExistingTab = (int) Tab::getIdFromClassName('AdminPdPrintOrder');
        if ($idExistingTab > 0) {
            return true;
        }

        $idParent = (int) Tab::getIdFromClassName('AdminOrders');
        $tab = new Tab();
        $tab->active = 0;
        $tab->class_name = 'AdminPdPrintOrder';
        $tab->name = [];

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int) $lang['id_lang']] = 'Drukowanie zamówień';
        }

        $tab->id_parent = $idParent;
        $tab->module = $this->name;

        return (bool) $tab->add();
    }

    private function uninstallTab(): bool
    {
        $idTab = (int) Tab::getIdFromClassName('AdminPdPrintOrder');
        if ($idTab <= 0) {
            return true;
        }

        $tab = new Tab($idTab);

        return (bool) $tab->delete();
    }

    public function hookDisplayAdminOrderTop(array $params): string
    {
        return $this->renderOrderButton($params);
    }

    public function hookDisplayAdminOrderSide(array $params): string
    {
        // Hook pozostaje zarejestrowany dla zgodności z wersją 1.1.0.
        return '';
    }

    public function hookDisplayAdminOrderMain(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            return '';
        }

        if (!PdPrintOrderCommentRepository::ensureSchema()) {
            return '<div class="alert alert-danger">Nie udało się przygotować tabeli komentarzy modułu Drukowanie zamówień.</div>';
        }

        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order)) {
            return '';
        }

        $details = (array) OrderDetail::getList($idOrder);
        $comments = PdPrintOrderCommentRepository::getCommentsForOrder($idOrder);
        $products = [];

        foreach ($details as $detail) {
            $idOrderDetail = (int) ($detail['id_order_detail'] ?? 0);
            if ($idOrderDetail <= 0) {
                continue;
            }

            $products[] = [
                'id_order_detail' => $idOrderDetail,
                'id_product' => (int) ($detail['product_id'] ?? 0),
                'product_name' => (string) ($detail['product_name'] ?? ''),
                'product_reference' => (string) ($detail['product_reference'] ?? ''),
                'comment' => (string) ($comments[$idOrderDetail] ?? ''),
            ];
        }

        $saveUrl = $this->context->link->getAdminLink('AdminPdPrintOrder') . '&ajax=1&action=saveComment';
        $printUrl = $this->context->link->getAdminLink('AdminPdPrintOrder') . '&ajax=1&action=printOrder&id_order=' . $idOrder;

        $this->context->smarty->assign([
            'pdpo_id_order' => $idOrder,
            'pdpo_products' => $products,
            'pdpo_save_comment_url' => $saveUrl,
            'pdpo_print_link' => $printUrl,
            'pdpo_comment_max_length' => PdPrintOrderCommentRepository::MAX_COMMENT_LENGTH,
        ]);

        return $this->fetch('module:pdprintorder/views/templates/admin/order_comments.tpl');
    }

    public function hookActionOrderGridDefinitionModifier(array $params): void
    {
        if (empty($params['definition'])) {
            return;
        }

        $definition = $params['definition'];
        $bulkActions = $definition->getBulkActions();
        $bulkActionClass = '\\PrestaShop\\PrestaShop\\Core\\Grid\\Action\\Bulk\\Type\\SubmitBulkAction';

        if (!class_exists($bulkActionClass)) {
            return;
        }

        $bulkActions->add(
            (new $bulkActionClass('pdprintorder_packing'))
                ->setName($this->l('Drukuj wydruk magazynowy'))
                ->setOptions([
                    'submit_route' => 'admin_pdprintorder_bulk_packing',
                    'submit_method' => 'POST',
                ])
        );

        $bulkActions->add(
            (new $bulkActionClass('pdprintorder_invoice'))
                ->setName($this->l('Drukuj faktury'))
                ->setOptions([
                    'submit_route' => 'admin_pdprintorder_bulk_invoice',
                    'submit_method' => 'POST',
                ])
        );

        $bulkActions->add(
            (new $bulkActionClass('pdprintorder_delivery'))
                ->setName($this->l('Drukuj dokumenty WZ'))
                ->setOptions([
                    'submit_route' => 'admin_pdprintorder_bulk_delivery',
                    'submit_method' => 'POST',
                ])
        );
    }

    private function renderOrderButton(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            return '';
        }

        $printUrl = $this->context->link->getAdminLink('AdminPdPrintOrder') . '&ajax=1&action=printOrder&id_order=' . $idOrder;

        $this->context->smarty->assign([
            'pdpo_print_link' => $printUrl,
        ]);

        return $this->fetch('module:pdprintorder/views/templates/admin/order_buttons.tpl');
    }

    public function getContent(): string
    {
        $output = '';

        $schemaReady = PdPrintOrderCommentRepository::ensureSchema();
        if (!$schemaReady) {
            $output .= $this->displayError($this->l('Nie udało się przygotować tabeli komentarzy. Sprawdź uprawnienia bazy danych.'));
        }

        if (Tools::isSubmit('submit_pdprintorder')) {
            $rawIds = trim((string) Tools::getValue('order_ids', ''));
            $type = (string) Tools::getValue('pdf_type', 'packing');
            $ids = preg_split('/[\s,;]+/', $rawIds, -1, PREG_SPLIT_NO_EMPTY);
            $orderIds = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));

            if (empty($orderIds)) {
                $output .= $this->displayError($this->l('Podaj co najmniej jedno prawidłowe ID zamówienia.'));
            } else {
                try {
                    if ($type === 'packing') {
                        require_once __DIR__ . '/classes/PdPrintOrderPackingPdf.php';
                        PdPrintOrderPackingPdf::render($orderIds, $this->context);
                    } elseif ($type === 'invoice' || $type === 'delivery') {
                        require_once __DIR__ . '/classes/PdPrintOrderPdf.php';
                        PdPrintOrderPdf::render($type, $orderIds, $this->context);
                    } else {
                        $output .= $this->displayError($this->l('Wybrano nieprawidłowy typ dokumentu.'));
                    }
                } catch (Throwable $exception) {
                    $output .= $this->displayError(
                        $this->l('Nie udało się wygenerować dokumentu: ') . $exception->getMessage()
                    );
                }
            }
        }

        $actionUrl = $this->context->link->getAdminLink(
            'AdminModules',
            true,
            [],
            ['configure' => $this->name]
        );

        $this->context->smarty->assign([
            'action_url' => $actionUrl,
            'pdpo_version' => $this->version,
            'pdpo_comments_count' => $schemaReady ? PdPrintOrderCommentRepository::countComments() : 0,
            'pdpo_schema_version' => (string) Configuration::get('PDPRINTORDER_SCHEMA_VERSION'),
        ]);

        return $output . $this->fetch('module:pdprintorder/views/templates/admin/configure.tpl');
    }
}
