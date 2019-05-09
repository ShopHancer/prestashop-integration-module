<?php

include __DIR__ . '/../BaseApiExtensionController.php';
include __DIR__ . '/../Response.php';

class ShopHancerIntegrationProductImageStoreApiExtensionModuleFrontController extends BaseApiExtensionController
{

    public function initContent()
    {
        parent::initContent();

        $shop = $this->getCurrentShop();

        $this->denyAccessUnlessGranted($shop->id);

        try {
            $xmlInput = $this->readAndParseXmlFromRawInputData();
        }
        catch (Exception $e) {
            $this->returnResponse(['message' => 'Unable to parse XML body. Check for syntax or typo\'s.'], Response::HTTP_BAD_REQUEST);
        }

        // validate input
        switch (strtoupper($this->getMethod())) {
            case 'PUT':
            case 'POST':

                if (!isset($xmlInput->image->cover)) {
                    $this->returnResponse(['message' => 'Property `cover` is missing'], Response::HTTP_BAD_REQUEST);
                }

                break;
        }

        switch (strtoupper($this->getMethod())) {
            case 'GET':
                $this->get();
                break;
            case 'PUT':
                $this->put();
                break;
            case 'POST':
                $this->post();
                break;
            case 'DELETE':
                $this->delete();
                break;
        }

        throw new LogicException('Method not supported');
    }

    private function post($finishRequest = true)
    {
        $xml = $this->readAndParseXmlFromRawInputData();
        $shop = $this->getCurrentShop();

        $imageShop = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'image_shop` WHERE `id_image`=' . (int)$xml->image->id . ' AND `id_shop`=' . $shop->id);

        if (!isset($imageShop['id_image']) || (int)$imageShop['id_image'] != (int)$xml->image->id) {
            $sql = 'INSERT INTO ' . _DB_PREFIX_ . 'image_shop (`id_image`, `id_shop`, `cover`) ';
            $sql .= ' VALUES (' . (int)$xml->image->id . ', ' . $shop->id . ', ' . ($this->isCover($xml) ? 1 : 0) . ')';

            Db::getInstance()->execute($sql);
        }

        if ($finishRequest) {
            $this->returnResponse(null, Response::HTTP_NO_CONTENT);
        }
    }

    private function delete()
    {
        $xml = $this->readAndParseXmlFromRawInputData();
        $shop = $this->getCurrentShop();

        if (!isset($xml->image->id)) {
            $this->returnResponse(['message' => 'Property `id` is missing'], Response::HTTP_BAD_REQUEST);
        }

        $sql = 'DELETE FROM ' . _DB_PREFIX_ . 'image_shop WHERE id_image=' . (int)$xml->image->id . ' AND id_shop=' . $shop->id;
        Db::getInstance()->execute($sql);

        $this->returnResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function put()
    {
        $xml = $this->readAndParseXmlFromRawInputData();

        if (!isset($xml->image->id)) {
            $this->returnResponse(['message' => 'Property `id` is missing'], Response::HTTP_BAD_REQUEST);
        }

        $shop = $this->getCurrentShop();

        // fallback in case when image not exists in this store | multi store
        $this->post(false);

        if ($this->isMultistoreEnabled()) {

            $sql = 'UPDATE `' . _DB_PREFIX_ . 'image_shop`';
            $sql .= ' SET `cover` = ' . ($this->isCover($xml) ? 1 : 0);
            $sql .= ' WHERE `id_image`=' . (int)$xml->image->id . ' AND id_shop=' . $shop->id;

            Db::getInstance()->execute($sql);
        }
        else {

            $sql = 'UPDATE `' . _DB_PREFIX_ . 'image`';
            $sql .= ' SET `position`=' . (isset($xml->image->position) ? (int)$xml->image->position : 0) . ', `cover` = ' . ($this->isCover($xml) ? 1 : 0);
            $sql .= ' WHERE `id_image`=' . (int)$xml->image->id;

            Db::getInstance()->execute($sql);
        }

        $this->returnResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function getCurrentShop()
    {
        $shopId = (int)Tools::getValue('id_shop') ?: 1;

        $shop = new Shop($shopId);

        if (!Validate::isLoadedObject($shop) || !$shop->active || !$shopId) {
            $this->returnResponse(['Shop not found'], Response::HTTP_NOT_FOUND);
        }

        return $shop;
    }

    public function get()
    {
        $shop = $this->getCurrentShop();
        $imageId = (int)Tools::getValue('id');

        if (0 === $imageId) {
            $this->returnResponse(null, Response::HTTP_BAD_REQUEST);
        }

        if ($this->isMultistoreEnabled()) {

            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'image_shop`';
            $sql .= ' WHERE `id_image`=' . $imageId . ' AND id_shop=' . $shop->id;

            $data = Db::getInstance()->getRow($sql);
        }
        else {

            $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'image`';
            $sql .= ' WHERE `id_image`=' . (int)$imageId;

            $data = Db::getInstance()->getRow($sql);
        }

        $this->returnResponse($data, Response::HTTP_OK);
    }

    private function isCover($xml)
    {
        $isCover = (int)$xml->image->cover == 1 || (string)$xml->image->cover === 'true' || $xml->image->cover === true;

        return $isCover;
    }

    private function isMultistoreEnabled()
    {
        return Shop::isFeatureActive();
    }
}