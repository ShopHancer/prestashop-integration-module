<?php

//declare(strict_types=1);


class BaseFrontController extends ModuleFrontControllerCore
{

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

    protected function isMethod(/*string */$method)//: bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * @param string $format
     * @return SimpleXMLElement
     * @throws Exception
     */
    protected function readAndParseFromRawInputData(/*string */$format = 'xml')
    {
        $rawData = file_get_contents("php://input");

        switch ($format) {
            case 'xml':
                $rawData = preg_replace("/\>([\n\r\n\t\ ]+)\</", '><', $rawData);

                if (strlen($rawData)) {
                    return new SimpleXMLElement($rawData);
                }

                return null;
            case 'json':
                try {
                    $json = json_decode($rawData, true);

                    return $json;
                }
                catch (Exception $e) {
                    return null;
                }
            default:
                throw new Exception('Invalid input format');
        }
    }

}