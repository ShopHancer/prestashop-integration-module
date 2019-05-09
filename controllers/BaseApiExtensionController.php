<?php

class ModuleFrontController extends ModuleFrontControllerCore {}

class BaseApiExtensionController extends ModuleFrontControllerCore
{

    public function __construct()
    {
        parent::__construct();

        $this->ssl = true;
    }

    public function initContent()
    {
        $this->disableColumns();
        parent::initContent();
    }

    /**
     * @return SimpleXMLElement
     */
    protected function readAndParseXmlFromRawInputData()
    {
        $rawData = file_get_contents("php://input");
        $rawData = preg_replace("/\>([\n\r\n\t\ ]+)\</", '><', $rawData);

        if (strlen($rawData)) {
            return new SimpleXMLElement($rawData);
        }

        return null;
    }

    /**
     * @param $shopId
     * @return void
     */
    protected function denyAccessUnlessGranted($shopId)
    {
        $authUser = isset($_SERVER['PHP_AUTH_USER']) ? strtoupper($_SERVER['PHP_AUTH_USER']) : '';

        if (!preg_match('/[A-Z0-9]{32}/', $authUser))
        {
            $this->returnResponse(['message' => 'Permission denied'], 401);
        }

        $sql = 'SELECT * 
FROM `' . _DB_PREFIX_ . 'webservice_account` wa 
LEFT JOIN ' . _DB_PREFIX_ . 'webservice_permission wp ON wp.id_webservice_account=wa.id_webservice_account 
WHERE wa.`key`="' . $authUser . '" AND wp.`method`="' . $this->getMethod() . '" AND wp.`resource`="products"';

        $webserviceAccount = Db::getInstance()->getRow($sql);

        if (!sizeof($webserviceAccount) || !isset($webserviceAccount['key']))
        {
            $this->returnResponse(['message' => 'Permission denied to this resource'], 401);
        }

        $sql = 'SELECT was.* 
FROM `' . _DB_PREFIX_ . 'webservice_account` wa 
LEFT JOIN ' . _DB_PREFIX_ . 'webservice_account_shop was ON was.id_webservice_account=wa.id_webservice_account 
WHERE wa.`key`="' . $authUser . '" AND was.`id_shop`=' . $shopId;

        $webserviceAccountShop = Db::getInstance()->getRow($sql);

        if (!sizeof($webserviceAccountShop) || !isset($webserviceAccountShop['id_shop']))
        {
            $this->returnResponse(['message' => 'Permission denied to this shop'], 401);
        }
    }

    protected function disableColumns()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;
        $this->display_footer = false;
        $this->display_header = false;
    }

    protected function returnResponse(array $response = null, $statusCode = 200)
    {
        header('Content-Type: application/json');

        http_response_code($statusCode);

        echo $response ? json_encode($response) : '';

        die();
    }

    /**
     * @return string
     */
    protected function getMethod()
    {
        return strtoupper($_SERVER['REQUEST_METHOD']);
    }

}