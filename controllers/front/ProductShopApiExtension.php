<?php

include __DIR__ . '/../BaseApiExtensionController.php';
include __DIR__ . '/../Response.php';

class ShopHancerIntegrationProductShopApiExtensionModuleFrontController extends BaseApiExtensionController
{

    public function initContent()
    {
        parent::initContent();

        $shopId = (int)Tools::getValue('id_shop');

        if (!$shopId) {
            $this->returnResponse(['Shop not found'], 404);
        }

        $shop = new Shop($shopId);
        if (!Validate::isLoadedObject($shop) || !$shop->active || !$shopId) {
            $this->returnResponse(['Shop not found'], 404);
        }

        $this->denyAccessUnlessGranted($shopId);

        switch ($this->getMethod()) {
            case 'GET':
                throw new LogicException('Implement me first!');
                break;
            case 'POST':
                $this->createNew();
                break;
            case 'DELETE':
                $this->delete();
                break;
        }

        throw new LogicException('Method not supported');
    }

    private function createNew()
    {
        $rawData = file_get_contents("php://input");
        $rawData = preg_replace("/\>([\n\r\n\t\ ]+)\</", '><', $rawData);
        $xml = new SimpleXMLElement($rawData);

        $productId = (int)$xml->product->id;
        $reference = trim((string)$xml->product->reference);
        $shopId = (int)Tools::getValue('id_shop');
        $shop = new Shop($shopId);

        if (!$productId || !$reference) {
            return $this->returnResponse(['message' => 'Input data not valid'], 400);
        }

        $product = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'product` p WHERE p.`id_product`=' . $productId);

        if (!sizeof($product) || !isset($product['id_product'])) {
            return $this->returnResponse(['message' => 'Can\'t find product'], 400);
        }

        // todo: check if exists with `active`=0
        $productShop = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'product_shop` ps WHERE ps.`id_product`=' . $productId . ' AND ps.id_shop=' . $shopId);

        if (sizeof($productShop) && isset($productShop['id_product'])) {
            return $this->returnResponse(['message' => 'Product shop exists for this shop'], 400);
        }

        $price = (int)(string)$xml->product->price;

        $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'product_shop (`id_product`,`id_shop`,`id_category_default`,`id_tax_rules_group`,`price`,`available_date`,`date_upd`,`date_add`)
VALUES (' . $productId . ', ' . $shopId . ', ' . (int)$xml->product->id_category_default . ', 1, ' . $price . ', NOW(), NOW(), NOW())';

        Db::getInstance()->execute($sql);

        $sql = 'INSERT IGNORE INTO ' . _DB_PREFIX_ . 'stock_available (`id_product`,`id_product_attribute`,`id_shop`,`id_shop_group`,`quantity`,`depends_on_stock`,`out_of_stock`)
VALUES (' . $productId . ', 0, ' . $shopId . ', ' . $shop->id_shop_group . ', 0, 0, 0)';

        Db::getInstance()->execute($sql);

        $this->returnResponse(null, 201);
    }

    private function delete()
    {
        $productId = (int)Tools::getValue('id_product');
        $shopId = (int)Tools::getValue('id_shop');

        if (!$productId) {
            return $this->returnResponse(['message' => 'Missing id_product parameter'], 400);
        }

        $product = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'product` p WHERE p.`id_product`=' . $productId);

        if (!sizeof($product) || !isset($product['id_product'])) {
            return $this->returnResponse(['message' => 'Can\'t find product'], 400);
        }

        $productShop = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'product_shop` ps WHERE ps.`id_product`=' . $productId . ' AND ps.id_shop=' . $shopId);

        if (!sizeof($productShop) || !isset($productShop['id_product'])) {
            return $this->returnResponse(['message' => 'Product shop not exists for this shop'], 400);
        }

        Db::getInstance()->execute('UPDATE ' . _DB_PREFIX_ . 'product_shop SET active=0 WHERE id_product = ' . $productId . ' AND id_shop=' . $shopId);

//        Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'product_shop WHERE id_product = ' . $productId . ' AND id_shop=' . $shopId);
//        Db::getInstance()->execute('DELETE FROM ' . _DB_PREFIX_ . 'stock_available WHERE id_product = ' . $productId . ' AND id_shop=' . $shopId);

        $this->returnResponse(null, Response::HTTP_NO_CONTENT);
    }

}