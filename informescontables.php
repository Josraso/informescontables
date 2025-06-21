<?php
/**
 * Módulo Informes Contables para PrestaShop
 * 
 * @author Tu nombre
 * @version 1.0.0
 * @compatible PrestaShop 1.7.x - 8.x
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class InformesContables extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'informescontables';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Tu nombre';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Informes Contables');
        $this->description = $this->l('Genera informes contables personalizables y Modelo 347');
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Configuration::updateValue('INFORMES_CONT_IMPORTE_347', '3005.06');
        
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->installTab() &&
            $this->createTables();
    }

    public function uninstall()
    {
        Configuration::deleteByName('INFORMES_CONT_IMPORTE_347');
        
        return parent::uninstall() &&
            $this->uninstallTab() &&
            $this->deleteTables();
    }

    private function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminInformesContables';
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Informes Contables';
        }
        
        // Buscar el ID del menú Pedidos
        $id_parent = (int)Tab::getIdFromClassName('AdminParentOrders');
        if (!$id_parent) {
            // Si no existe AdminParentOrders, intentar con SELL
            $id_parent = (int)Tab::getIdFromClassName('SELL');
            if (!$id_parent) {
                // Si tampoco existe, usar el menú principal
                $id_parent = 0;
            }
        }
        
        $tab->id_parent = $id_parent;
        $tab->module = $this->name;
        $tab->position = 99; // Al final del menú

        return $tab->add();
    }

    private function uninstallTab()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminInformesContables');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            return $tab->delete();
        }
        return true;
    }

    private function createTables()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."informes_contables_log` (
            `id_log` int(11) NOT NULL AUTO_INCREMENT,
            `tipo_informe` varchar(50) NOT NULL,
            `fecha_generacion` datetime NOT NULL,
            `id_employee` int(11) NOT NULL,
            `parametros` text,
            PRIMARY KEY (`id_log`)
        ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8;";

        $sql2 = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."informes_contables_emails` (
            `id_email` int(11) NOT NULL AUTO_INCREMENT,
            `id_customer` int(11) NOT NULL,
            `tipo_informe` varchar(50) NOT NULL,
            `fecha_envio` datetime NOT NULL,
            `email` varchar(255) NOT NULL,
            `estado` varchar(50) NOT NULL,
            PRIMARY KEY (`id_email`)
        ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8;";

        return Db::getInstance()->execute($sql) && Db::getInstance()->execute($sql2);
    }

    private function deleteTables()
    {
        $sql = "DROP TABLE IF EXISTS `"._DB_PREFIX_."informes_contables_log`";
        $sql2 = "DROP TABLE IF EXISTS `"._DB_PREFIX_."informes_contables_emails`";
        
        return Db::getInstance()->execute($sql) && Db::getInstance()->execute($sql2);
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitInformesContablesModule')) {
            Configuration::updateValue('INFORMES_CONT_IMPORTE_347', Tools::getValue('INFORMES_CONT_IMPORTE_347'));
            $output .= $this->displayConfirmation($this->l('Configuración actualizada'));
        }

        return $output.$this->renderForm();
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitInformesContablesModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuración'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Importe mínimo Modelo 347'),
                        'name' => 'INFORMES_CONT_IMPORTE_347',
                        'suffix' => '€',
                        'desc' => $this->l('Importe mínimo para incluir clientes en el Modelo 347 (por defecto 3.005,06€)'),
                        'required' => true
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Guardar'),
                ),
            ),
        );
    }

    protected function getConfigFormValues()
    {
        return array(
            'INFORMES_CONT_IMPORTE_347' => Configuration::get('INFORMES_CONT_IMPORTE_347', '3005.06'),
        );
    }

    public function hookHeader()
    {
        // Hook vacío pero necesario para evitar el error
    }
    
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('controller') == 'AdminInformesContables') {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }
}