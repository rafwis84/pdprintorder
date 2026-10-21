<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PdPrintOrderCommentRepository
{
    public const MAX_COMMENT_LENGTH = 255;

    public static function installSchema(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pdprintorder_comment` (
            `id_pdprintorder_comment` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_order` INT UNSIGNED NOT NULL,
            `id_order_detail` INT UNSIGNED NOT NULL,
            `id_product` INT UNSIGNED NOT NULL DEFAULT 0,
            `comment` VARCHAR(255) NOT NULL DEFAULT \'\',
            `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_pdprintorder_comment`),
            UNIQUE KEY `uniq_order_detail` (`id_order_detail`),
            KEY `idx_order` (`id_order`),
            KEY `idx_product` (`id_product`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $ok = (bool) Db::getInstance()->execute($sql);
        if ($ok) {
            Configuration::updateValue('PDPRINTORDER_SCHEMA_VERSION', '1.2.0');
        }

        return $ok;
    }

    public static function ensureSchema(): bool
    {
        static $schemaReady = null;

        if ($schemaReady !== null) {
            return $schemaReady;
        }

        $tableName = _DB_PREFIX_ . 'pdprintorder_comment';
        $likeTableName = str_replace(['\\', '_', '%'], ['\\\\', '\_', '\%'], $tableName);
        $rows = Db::getInstance()->executeS(
            'SHOW TABLES LIKE \'' . pSQL($likeTableName) . '\''
        );

        if (is_array($rows) && !empty($rows)) {
            if ((string) Configuration::get('PDPRINTORDER_SCHEMA_VERSION') !== '1.2.0') {
                Configuration::updateValue('PDPRINTORDER_SCHEMA_VERSION', '1.2.0');
            }

            $schemaReady = true;
            return true;
        }

        $schemaReady = self::installSchema();
        return $schemaReady;
    }

    public static function getCommentsForOrder(int $idOrder): array
    {
        if ($idOrder <= 0 || !self::ensureSchema()) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT `id_order_detail`, `comment`
             FROM `' . _DB_PREFIX_ . 'pdprintorder_comment`
             WHERE `id_order` = ' . (int) $idOrder
        );

        $comments = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $comments[(int) $row['id_order_detail']] = (string) $row['comment'];
            }
        }

        return $comments;
    }

    public static function saveComment(int $idOrder, int $idOrderDetail, string $comment, int $idEmployee): bool
    {
        if ($idOrder <= 0 || $idOrderDetail <= 0 || !self::ensureSchema()) {
            return false;
        }

        $detailRows = Db::getInstance()->executeS(
            'SELECT `product_id`
             FROM `' . _DB_PREFIX_ . 'order_detail`
             WHERE `id_order_detail` = ' . (int) $idOrderDetail . '
               AND `id_order` = ' . (int) $idOrder
        );

        if (!is_array($detailRows) || empty($detailRows)) {
            return false;
        }

        $idProduct = (int) ($detailRows[0]['product_id'] ?? 0);
        $comment = trim(strip_tags($comment));
        $comment = (string) Tools::substr($comment, 0, self::MAX_COMMENT_LENGTH);
        $now = date('Y-m-d H:i:s');

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'pdprintorder_comment`
            (`id_order`, `id_order_detail`, `id_product`, `comment`, `id_employee`, `date_add`, `date_upd`)
            VALUES (
                ' . (int) $idOrder . ',
                ' . (int) $idOrderDetail . ',
                ' . (int) $idProduct . ',
                \'' . pSQL($comment) . '\',
                ' . (int) $idEmployee . ',
                \'' . pSQL($now) . '\',
                \'' . pSQL($now) . '\'
            )
            ON DUPLICATE KEY UPDATE
                `id_order` = VALUES(`id_order`),
                `id_product` = VALUES(`id_product`),
                `comment` = VALUES(`comment`),
                `id_employee` = VALUES(`id_employee`),
                `date_upd` = VALUES(`date_upd`)';

        return (bool) Db::getInstance()->execute($sql);
    }

    public static function countComments(): int
    {
        if (!self::ensureSchema()) {
            return 0;
        }

        $rows = Db::getInstance()->executeS(
            'SELECT COUNT(*) AS `cnt`
             FROM `' . _DB_PREFIX_ . 'pdprintorder_comment`
             WHERE `comment` <> \'\''
        );

        if (!is_array($rows) || empty($rows)) {
            return 0;
        }

        return (int) ($rows[0]['cnt'] ?? 0);
    }
}
