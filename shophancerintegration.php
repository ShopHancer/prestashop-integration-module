<?php

if (!defined('_PS_VERSION_'))
{
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

}