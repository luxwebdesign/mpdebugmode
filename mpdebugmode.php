<?php
/**
* 2016 Michael Dekker
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@michaeldekker.com so we can send you a copy immediately.
*
*  @author    Michael Dekker <prestashop@michaeldekker.com>
*  @copyright 2016 Michael Dekker
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Mpdebugmode extends Module
{
    const DEBUGMODE_ENABLED = 'MPDEBUGMODE_DEBUGMODE_ENABLED';

    const DEBUG_MODE_SUCCEEDED = 0;
    const DEBUG_MODE_ERROR_NO_READ_ACCESS = 1;
    const DEBUG_MODE_ERROR_NO_READ_ACCESS_CUSTOM = 2;
    const DEBUG_MODE_ERROR_NO_WRITE_ACCESS = 3;
    const DEBUG_MODE_ERROR_NO_WRITE_ACCESS_CUSTOM = 4;
    const DEBUG_MODE_ERROR_NO_DEFINITION_FOUND = 5;

    /**
     * Mpdebugmode constructor.
     */
    public function __construct()
    {
        $this->name = 'mpdebugmode';
        $this->tab = 'administration';
        $this->version = '1.2.1';
        $this->author = 'Michael Dekker';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Enable debug mode');
        $this->description = $this->l('Enable debug mode from your Back Office');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install this module
     *
     * @return bool Whether install succeeded
     */
    public function install()
    {
        if (!function_exists('preg_match')) {
            Context::getContext()->controller->errors[] = Tools::displayError($this->l('mpdebugmode: Regex (PHP-module: PCRE) support is missing from the server. Installation has been cancelled to prevent damage.'));
            parent::uninstall();

            return false;
        }
        Configuration::updateValue(self::DEBUGMODE_ENABLED, false);

        return parent::install() && $this->registerHook('backOfficeHeader');
    }

    /**
     * Uninstall this module
     *
     * @return bool Whether uninstall succeeded
     */
    public function uninstall()
    {
        Configuration::deleteByName(self::DEBUGMODE_ENABLED);

        return $this->unregisterHook('backOfficeHeader') && parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $output = '';

        if (!$this->isDefinesReadable()) {
            $output .= $this->displayError(sprintf($this->l('Error: could not read file. Make sure you have the correct permissions set on the file %s'), _PS_ROOT_DIR_.'/config/defines.inc.php'));
        } else {
            /**
             * If values have been submitted in the form, process.
             */
            if (((bool) Tools::isSubmit('submitMpdebugmodeModule')) == true) {
                $output .= $this->postProcess();
            }
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     *
     * @return string Configuration form HTML
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMpdebugmodeModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     *
     * @return array Array with options
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => Translate::getAdminTranslation('Debug mode', 'AdminPerformance'),
                'icon' => 'icon-bug',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => Translate::getAdminTranslation('Debug mode', 'AdminPerformance'),
                        'name' => self::DEBUGMODE_ENABLED,
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     *
     * @return array Array with config values
     */
    protected function getConfigFormValues()
    {
        return array(self::DEBUGMODE_ENABLED => $this->isDebugModeEnabled());
    }

    /**
     * Save form data.
     *
     * @return string Error messages HTML
     */
    protected function postProcess()
    {
        $output = '';

        if (Tools::isSubmit(self::DEBUGMODE_ENABLED)) {
            if ((bool) Tools::getValue(self::DEBUGMODE_ENABLED)) {
                $errorCode = $this->enableDebugMode();
            } else {
                $errorCode = $this->disableDebugMode();
            }

            if (!empty($errorCode)) {
                switch ($errorCode) {
                    case self::DEBUG_MODE_ERROR_NO_WRITE_ACCESS_CUSTOM:
                        $output .= $this->displayError(sprintf($this->l('Error: could not write to file: %s. Make sure that the file or directory is writable.'), _PS_ROOT_DIR_.'/config/defines.old.php'));
                        break;
                    case self::DEBUG_MODE_ERROR_NO_DEFINITION_FOUND:
                        $output .= $this->displayError(sprintf($this->l('Error: could not find whether debug mode is enabled. Make sure you have the correct permissions set on the file %s'), _PS_ROOT_DIR_.'/config/defines.inc.php'));
                        break;
                    case self::DEBUG_MODE_ERROR_NO_WRITE_ACCESS:
                        $output .= $this->displayError(sprintf($this->l('Error: could not write to file. Make sure you have the correct permissions set on the file %s'), _PS_ROOT_DIR_.'/config/defines.inc.php'));
                        break;
                    case self::DEBUG_MODE_ERROR_NO_READ_ACCESS:
                        $output .= $this->displayError(sprintf($this->l('Error: could not read file. Make sure you have the correct permissions set on the file %s'), _PS_ROOT_DIR_.'/config/defines.inc.php'));
                        break;
                    default:
                        break;
                }
            }
        }

        if (empty($output)) {
            $output .= $this->displayConfirmation($this->l('Changed successfully'));
        }

        return $output;
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    *
    * @return string Hook HTML
    */
    public function hookBackOfficeHeader()
    {
        // PrestaShop 1.7 already has a debug icon
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<') && _PS_MODE_DEV_) {
            $this->context->smarty->assign(array(
                'debugmode_link' => $this->context->link->getAdminLink('AdminModules', true).'&configure=mpdebugmode&module_name=mpdebugmode&tab_module=administration',
            ));
            $this->context->controller->addJquery();

            return $this->display(__FILE__, 'dev_mode_js.tpl');
        }

        return '';
    }

    /**
     * Is Debug Mode enabled?
     *
     * @return bool Whether debug mode is enabled
     */
    public function isDebugModeEnabled()
    {
        // Always try the custom defines file first
        $definesClean = '';
        if ($this->isDefinesReadable(true)) {
            $definesClean = php_strip_whitespace(_PS_ROOT_DIR_.'/config/defines_custom.inc.php');
        }

        $m = array();
        if (!preg_match('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', $definesClean, $m)) {
            $definesClean = php_strip_whitespace(_PS_ROOT_DIR_.'/config/defines.inc.php');
            if (!preg_match('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', $definesClean, $m)) {
                return false;
            }
        }

        if (Tools::strtolower($m[1]) === 'true') {
            return true;
        }

        return false;
    }

    /**
     * Check read permission on defines.inc.php
     *
     * @param bool $custom Whether the custom defines file should be used
     * @return bool Whether the file can be read
     */
    public function isDefinesReadable($custom = false)
    {
        if ($custom) {
            return is_readable(_PS_ROOT_DIR_.'/config/defines_custom.inc.php');
        }

        return is_readable(_PS_ROOT_DIR_.'/config/defines.inc.php');
    }

    /**
     * Enable debug mode
     *
     * @return int Whether changing debug mode succeeded or error code
     */
    public function enableDebugMode()
    {
        // Check custom defines file first
        if ($this->isDefinesReadable(true)) {
            // Take commented lines into account
            $definesCustomClean = php_strip_whitespace(_PS_ROOT_DIR_.'/config/defines_custom.inc.php');
            $definesCustom = Tools::file_get_contents(_PS_ROOT_DIR_.'/config/defines_custom.inc.php');
            if (!empty($definesCustomClean) && preg_match('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', $definesCustomClean)) {
                $definesCustom = preg_replace('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', 'define(\'_PS_MODE_DEV_\', true);', $definesCustom);
                if (!@file_put_contents(_PS_ROOT_DIR_.'/config/defines_custom.inc.php', $definesCustom)) {
                    return self::DEBUG_MODE_ERROR_NO_WRITE_ACCESS_CUSTOM;
                }

                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate(_PS_ROOT_DIR_.'/config/defines_custom.inc.php');
                }

                return self::DEBUG_MODE_SUCCEEDED;
            }
        }

        if (!$this->isDefinesReadable()) {
            return self::DEBUG_MODE_ERROR_NO_READ_ACCESS;
        }
        $definesClean = php_strip_whitespace(_PS_ROOT_DIR_.'/config/defines.inc.php');
        $defines = Tools::file_get_contents(_PS_ROOT_DIR_.'/config/defines.inc.php');
        if (!preg_match('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', $definesClean)) {
            return self::DEBUG_MODE_ERROR_NO_DEFINITION_FOUND;
        }
        $defines = preg_replace('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', 'define(\'_PS_MODE_DEV_\', true);', $defines);
        if (!@file_put_contents(_PS_ROOT_DIR_.'/config/defines.inc.php', $defines)) {
            return self::DEBUG_MODE_ERROR_NO_WRITE_ACCESS;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(_PS_ROOT_DIR_.'/config/defines.inc.php');
        }

        return self::DEBUG_MODE_SUCCEEDED;
    }

    /**
     * Disable debug mode
     *
     * @return int Whether changing debug mode succeeded or error code
     */
    public function disableDebugMode()
    {
        // Check custom defines file first
        if ($this->isDefinesReadable(true)) {
            $definesCustomClean = php_strip_whitespace(_PS_ROOT_DIR_.'/config/defines_custom.inc.php');
            $definesCustom = Tools::file_get_contents(_PS_ROOT_DIR_.'/config/defines_custom.inc.php');
            if (!empty($definesCustomClean) && preg_match('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', $definesCustomClean)) {
                $definesCustom = preg_replace('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', 'define(\'_PS_MODE_DEV_\', false);', $definesCustom);
                if (!@file_put_contents(_PS_ROOT_DIR_.'/config/defines_custom.inc.php', $definesCustom)) {
                    return self::DEBUG_MODE_ERROR_NO_WRITE_ACCESS_CUSTOM;
                }

                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate(_PS_ROOT_DIR_.'/config/defines_custom.inc.php');
                }

                return self::DEBUG_MODE_SUCCEEDED;
            }
        }

        if (!$this->isDefinesReadable()) {
            return self::DEBUG_MODE_ERROR_NO_READ_ACCESS;
        }
        $definesClean = php_strip_whitespace(_PS_ROOT_DIR_.'/config/defines.inc.php');
        $defines = Tools::file_get_contents(_PS_ROOT_DIR_.'/config/defines.inc.php');
        if (!preg_match('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', $definesClean)) {
            return self::DEBUG_MODE_ERROR_NO_DEFINITION_FOUND;
        }
        $defines = preg_replace('/define\(\'_PS_MODE_DEV_\', ([a-zA-Z]+)\);/Ui', 'define(\'_PS_MODE_DEV_\', false);', $defines);
        if (!@file_put_contents(_PS_ROOT_DIR_.'/config/defines.inc.php', $defines)) {
            return self::DEBUG_MODE_ERROR_NO_WRITE_ACCESS;
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(_PS_ROOT_DIR_.'/config/defines.inc.php');
        }

        return self::DEBUG_MODE_SUCCEEDED;
    }
}
