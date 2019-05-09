<?php

include __DIR__ . '/../BaseApiExtensionController.php';
include __DIR__ . '/../Response.php';

class ShopHancerIntegrationProductImageApiExtensionModuleFrontController extends BaseApiExtensionController
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

        try {
            $xmlInput = $this->readAndParseXmlFromRawInputData();
        }
        catch (Exception $e) {
            $this->returnResponse(['message' => 'Unable to parse XML body. Check for syntax or typo\'s.'], Response::HTTP_BAD_REQUEST);
        }

        if (!isset($xmlInput->image->id)) {
            $this->returnResponse(['message' => 'Property `id` is missing'], Response::HTTP_BAD_REQUEST);
        }

        // validate input
        switch (strtoupper($this->getMethod())) {
            case 'PUT':

                if (!isset($xmlInput->image->cover)) {
                    $this->returnResponse(['message' => 'Property `cover` is missing'], Response::HTTP_BAD_REQUEST);
                }

                if (!isset($xmlInput->image->cover)) {
                    $this->returnResponse(['message' => 'Property `position` is missing'], Response::HTTP_BAD_REQUEST);
                }

                break;
        }

        switch (strtoupper($this->getMethod())) {
            case 'PUT':
                $this->put();
                break;
        }

        throw new LogicException('Method not supported');
    }

    private function put()
    {
        $xml = $this->readAndParseXmlFromRawInputData();

        $sql = 'UPDATE `' . _DB_PREFIX_ . 'image`';
        $sql .= ' SET `position`=' . (isset($xml->image->position) ? (int)$xml->image->position : 0) . ', `cover` = ' . ($this->isCover($xml) ? 1 : 0);
        $sql .= ' WHERE `id_image`=' . (int)$xml->image->id;

        Db::getInstance()->execute($sql);

        $this->returnResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function isCover($xml)
    {
        $isCover = (int)$xml->image->cover == 1 || (string)$xml->image->cover === 'true' || $xml->image->cover === true;

        return $isCover;
    }

}