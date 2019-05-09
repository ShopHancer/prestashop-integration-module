<?php

//declare(strict_types=1);

//class ModuleFrontController extends ModuleFrontControllerCore {}
//ShopHancerIntegrationCrudApiExtensionModuleFrontController

include __DIR__ . '/../BaseFrontController.php';
include __DIR__ . '/../Response.php';
include __DIR__ . '/../Request.php';

class ShopHancerIntegrationCustomerAuthenticationModuleFrontController extends BaseFrontController
{

    public function run()
    {
        if (!$this->isMethod(Request::METHOD_POST)) {
            $this->returnResponse(null, Response::HTTP_BAD_REQUEST);
        }

        try {
            $inputData = $this->readAndParseFromRawInputData('json');
        }
        catch (Exception $e) {
            $this->returnResponse([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
            die();
        }

        $this->authorizeCustomerByEmailAndPassword($inputData['username'], $inputData['password']);
    }

    /**
     * @param string $email
     * @param string $plainPassword
     */
    public function authorizeCustomerByEmailAndPassword(/*string */$email, /*string */$plainPassword)
    {
        $email = trim($email);
        $plainPassword = trim($plainPassword);

        $customer = new CustomerCore;
        /** @var CustomerCore $customer */
        $customer = $customer->getByEmail($email, $plainPassword);

        if (!$customer || !(int)$customer->id) {
            $this->returnResponse(null, Response::HTTP_UNAUTHORIZED);
        }

        $this->returnResponse([
            'id' => $customer->id,
            'email' => $customer->email,
            'firstname' => $customer->firstname,
            'lastname' => $customer->lastname,
        ], Response::HTTP_OK);
    }


}