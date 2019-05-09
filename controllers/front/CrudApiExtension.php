<?php

include __DIR__ . '/../Response.php';

class ShopHancerIntegrationCrudApiExtensionModuleFrontController extends ModuleFrontControllerCore
{

    /**
     * @param bool $displayColumnLeft
     * @throws Exception
     */
    public function initContent($displayColumnLeft = true)
    {
        $this->denyAccessUnlessGranted();

        $data = $this->readAndParseJsonFromRawInputData();

        if (!$this->isResourceSupported($this->getRequestedResource(), $this->getMethod(), $this->getRequestedShopId())) {
            $this->returnResponse([
                'error' => sprintf('Method `%s` for resource `%s` is not supported', $this->getMethod(), $data['resource']),
            ], 400);// Bad Request
        }

        switch ($this->getMethod()) {
            case 'POST':
                $this->handleCreate($data['resource'], $data['data']);
                break;
            case 'GET':
                $this->handleGet($data['resource'], $data['columns'], $data['filters']);
                break;
            case 'PUT':
                $this->handleUpdate($data['resource'], $data['data'], $data['filters']);
                break;
            case 'DELETE':
                $this->handleDelete($data['resource'], $data['filters']);
                break;
        }
    }

    /**
     * @return int
     */
    private function getRequestedShopId()
    {
        return (int)Tools::getValue('id_shop');
    }

    /**
     * @return int
     */
    private function getRequestedResource()
    {
        $resource = (string)Tools::getValue('resource');

        if (preg_match('/[a-zA-Z0-9\_\-]+/', $resource)) {
            return strtolower($resource);
        }

        return '';
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function readAndParseJsonFromRawInputData()
    {
        $columns = Tools::getValue('columns') ? (array)Tools::getValue('columns') : [];
        $filters = Tools::getValue('filters') ? (array)Tools::getValue('filters') : [];

        $rawData = file_get_contents("php://input");
        $array = (array)json_decode($rawData, true);

        if (!isset($array['data'])) {
            $array['data'] = [];
        }

        $array['resource'] = $this->getRequestedResource();
        $array['filters'] = $filters;
        $array['columns'] = $columns;

        return $array;
    }

    /**
     * @param string $resource
     * @param $method
     * @param int $shopId
     * @return bool
     * @throws Exception
     */
    private function isResourceSupported($resource, $method, $shopId)
    {
        if ('stock_available' === $resource && 'POST' === $method) {
            $method = 'GET';
        }

        $resourceMapping = [
            'product_shop' => 'products',
            'cms_shop' => 'cms_shops',
            'currency' => 'currencies',
            'attribute_impact' => 'combinations',
        ];

        $sql = sprintf(
            "SELECT wp.* FROM %s wp 
                    INNER JOIN %s wa ON wa.id_webservice_account = wp.id_webservice_account 
                    INNER JOIN %s was ON was.id_shop=%d AND was.id_webservice_account=wa.id_webservice_account
                    WHERE wa.key='%s' AND wp.resource='%s' AND wp.method='%s'",
            $this->getTableName('webservice_permission'),
            $this->getTableName('webservice_account'),
            $this->getTableName('webservice_account_shop'),
            $shopId,
            $this->getAuthenticationToken(),
            isset($resourceMapping[$resource]) ? $resourceMapping[$resource] : $resource . 's',
            $method
        );

        $rs = Db::getInstance()->getRow($sql);

        return sizeof($rs) && (int)$rs['id_webservice_permission'];
    }

    /**
     * @param $resource
     * @param array $data
     */
    public function handleCreate($resource, array $data)
    {
        $sqlIns = sprintf(
            "INSERT INTO `%s` (%s)",
            $this->getTableName($resource),
            '`' . implode('`,`', array_keys($data)) . '`'
        );

        $sqlDta = sprintf(
            "VALUES (%s)",
            '"' . implode('","', array_values($data)) . '"'
        );

        $sql = $sqlIns . ' ' . $sqlDta;

        try {
            if (!Db::getInstance()->execute($sql)) {
                throw new Exception(Db::getInstance()->getMsgError($sql));
            }

            $id = Db::getInstance()->Insert_ID();

            $result = Db::getInstance()->getRow(sprintf(
                "SELECT * FROM `%s` WHERE `%s` = %d",
                $this->getTableName($resource),
                'id_' . $resource,
                $id
            ))
            ;

            $this->returnResponse($result, 201);// Created
        }
        catch (Exception $e) {
            // write to log
            $this->returnResponse([
                'error' => $e->getMessage(),
            ], 400);// Bad Request
        }
    }

    /**
     * @param string $resource
     * @return string
     */
    private function getTableName($resource)
    {
        return sprintf('%s%s', _DB_PREFIX_, $resource);
    }

    /**
     * @param $resource
     * @param array $data
     * @param array $filters
     */
    public function handleUpdate($resource, array $data, array $filters)
    {
        $columns = [];

        foreach ($data as $column => $value) {
            $valueFormatted = is_numeric($value) ? $value : sprintf('"%s"', $value);

            $columns[] = sprintf(
                '`%s` = %s',
                $column,
                $valueFormatted
            );
        }

        $sql = sprintf(
            "UPDATE `%s` SET %s",
            $this->getTableName($resource),
            implode(', ', $columns)
        );

        if (sizeof($filters)) {
            $sql .= " WHERE " . $this->glueWhereClause($filters);
        }

        try {
            if (!Db::getInstance()->execute($sql)) {
                throw new Exception(Db::getInstance()->getMsgError($sql));
            }

            $this->returnResponse([], 200);// Ok
        }
        catch (Exception $e) {
            // write to log
            $this->returnResponse([], 400);// Bad Request
        }
    }

    /**
     * @param $resource
     * @param array $filters
     */
    public function handleDelete($resource, array $filters)
    {
        $sql = sprintf(
            "DELETE FROM `%s`",
            $this->getTableName($resource)
        );

        if (sizeof($filters)) {
            $sql .= " WHERE " . $this->glueWhereClause($filters);
        }

        if (!Db::getInstance()->execute($sql)) {
            $this->returnResponse([
                'error' => Db::getInstance()->getMsgError($sql),
            ], 400);
        }

        $this->returnResponse([], 204);// No Content
    }

    /**
     * @param $resource
     * @param array $columns
     * @param array $filters
     */
    public function handleGet($resource, array $columns = [], array $filters = [])
    {
        $sql = sprintf(
            "SELECT %s FROM `%s`",
            sizeof($columns) ? implode(', ', $columns) : '*',
            sprintf('%s%s', _DB_PREFIX_, $resource)
        );

        if (sizeof($filters)) {
            $sql .= " WHERE " . $this->glueWhereClause($filters);
        }

        $result = Db::getInstance()->executeS($sql);

        if (false === $result) {
            $this->returnResponse([], 404);
        }

        $this->returnResponse($result, 200);
    }

    /**
     * @param array $filters
     * @return string
     */
    private function glueWhereClause(array $filters)
    {
        $where = [];

        foreach ($filters as $column => $value) {
            $valueFormatted = is_numeric($value) ? $value : sprintf('"%s"', $value);

            $where[] = sprintf('`%s` = %s', $column, $valueFormatted);
        }

        if (sizeof($where)) {
            return implode(" AND ", $where);
        }

        return '1';
    }


    /**
     * @return void
     */
    protected function denyAccessUnlessGranted()
    {
        if (!preg_match('/[A-Z0-9]{32}/', $this->getAuthenticationToken())) {
            $this->returnResponse(['message' => 'Permission denied'], 401);
        }

        $sql = sprintf('SELECT wa.* FROM `%swebservice_account` wa WHERE wa.`key`="%s"',
            _DB_PREFIX_,
            $this->getAuthenticationToken()
        );

        $webserviceAccountShop = Db::getInstance()->getRow($sql);

        if (!sizeof($webserviceAccountShop) || !isset($webserviceAccountShop['active']) || !$webserviceAccountShop['active']) {
            $this->returnResponse(['message' => 'Permission denied to this shop'], 401);
        }
    }

    /**
     * @return string
     */
    private function getAuthenticationToken()//: string
    {
        return isset($_SERVER['PHP_AUTH_USER']) ? strtoupper((string)$_SERVER['PHP_AUTH_USER']) : '';
    }

    protected function returnResponse(array $response, $statusCode)
    {
        header('Content-Type: application/json');

        http_response_code($statusCode);

        echo sizeof($response) ? json_encode($response) : '';

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
