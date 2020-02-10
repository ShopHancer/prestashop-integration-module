<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ShopHancerIntegration extends Module
{

    public function __construct()
    {
        $this->name = 'shophancerintegration';
        $this->displayName = 'ShopHancer Integration';
        $this->tab = 'migration_tools';
        $this->description = 'Additional API endpoints for more precised integration with 3rd party scripts';
        $this->version = '1.0';
        $this->author = 'marek.krokwa@shophancer.com';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '1.5', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();
    }

    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        $objects = file(realpath(__DIR__ . '/hookObjectList.txt'));

        // register hooks
        foreach ($objects as $entity) {
            $this->registerHook(sprintf('actionObject%sAddAfter', $entity));
            $this->registerHook(sprintf('actionObject%sUpdateAfter', $entity));
            $this->registerHook(sprintf('actionObject%sDeleteAfter', $entity));
        }

        return parent::install();
    }

    public function __call($name, $arguments)
    {
        if (strpos(strtolower($name), 'hookactionobject') !== 0) {
            return;
        }

        if (!preg_match('/^hookactionobject([a-z0-9]+)(update|add|delete)after$/', strtolower($name), $matches)) {
            return;
        }

        list($hookParams) = $arguments;
        $object = $hookParams['object'];

        $body = array(
            'type' => 'hook_object_after',
            'datetime' => (new DateTime())->format(DateTime::RFC3339),
            'action' => $matches[2],
            'object_type' => $matches[1],
            'object_id' => $object->id,
            'shop_id' => $context = (int)Context::getContext()->shop->id,
            'metadata' => $matches[1] === 'product' ? ['sku' => $object->reference] : [],
            'content' => json_encode($object),
        );

        $path = sprintf('/webhooks/prestashop/%s', _PS_VERSION_);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Configuration::get('SH_BASE_URL') . $path);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        curl_exec($ch);
        curl_close($ch);

        return;
    }

}
