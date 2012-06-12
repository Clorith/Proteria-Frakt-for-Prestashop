<?php
	if (!defined('_CAN_LOAD_FILES_'))
		exit;
	
    /**
     * Proteria Module extension
     */
	class proteriafrakt extends Module
	{
        //  Define codes used by Proteria for shipping types
		static $types = array(
			'101'	=>	'Bring Parcels',
			'102'	=>	'Bring Cargo',
			'3'		=>	'Schenker',
			'4'		=>	'Norsk Fraktbrev',
			'5'		=>	'Budbilservice',
			'6'		=>	'Tollpost Globe',
			'7'		=>	'Brevetikett',
			'9'		=>	'DPD',
			'10'	=>	'DHL',
			'12'	=>	'Toten Transport',
			'14'	=>	'Ontime Logistics',
			'15'	=>	'Eek Transport',
			'18'	=>	'CMR',
			'20'	=>	'Nor Lines',
			'21'	=>	'System-Transport',
			'22'	=>	'Convo (diverse budbilfirma)'
		);
		static $packages = array(
			'1100'	=>	'Klimanøytral servicepakke',
			'1101'	=>	'Returservice Servicepakke',
			'1102'	=>	'På Døren',
			'1105'	=>	'Hjemlevering prosjekt',
			'1106'	=>	'Bedriftspakke Dør - Dør',
			'1107'	=>	'Bedriftspakke Postkontor',
			'1108'	=>	'Returservice Bedriftspakke',
			'1109'	=>	'Bedriftspakke Ekspress over Natt - 0900',
			'1110'	=>	'Bedriftspakke Ekspress over Natt - 0700',
			'1111'	=>	'Returservice bedriftspakke Ekspress',
			'1112'	=>	'Postens Pallelaster',
			'1113'	=>	'Bedriftspakke Flerkolli',
			'1114'	=>	'Abonnement-transport',
			'1115'	=>	'Cross Docking',
			'1116'	=>	'Cross Docking Ekspress',
			'1117'	=>	'Miljøretur',
			'1118'	=>	'Postautomat Same Day',
			'1119'	=>	'Postautomat Next day',
			'1120'	=>	'Minipakke',
			'1121'	=>	'CarryOn Homeshopping',
			'1122'	=>	'CarryOn Homeshopping Return',
			'1123'	=>	'CarryOnCarryOn Homeshopping BulkSplit',
			'1124'	=>	'CarryOn Homeshopping BulkSplit Return',
			'1126'	=>	'CarryOn Business',
			'1127'	=>	'CarryOn Business Return',
			'1128'	=>	'CarryOn Business 0900',
			'1129'	=>	'CarryOn Business Bulksplit 0900',
			'1130'	=>	'CarryOn Business Bulksplit',
			'1131'	=>	'CarryOn Business Pallet',
			'1132'	=>	'CarryOn Business Pallet Return',
			'1134'	=>	'CarryOn Business Bulk return',
			'1200'	=>	'Stykkgods (innenlands)',
			'1201'	=>	'Partigods (innenlands)',
			'1202'	=>	'Stykk/Parti (utenlands)',
			'1203'	=>	'Flyfrakt',
			'1204'	=>	'Sjøfrakt',
			'1205'	=>	'Supply Base Truck',
			'1206'	=>	'Supply Base Ship',
			'1135'	=>	'PUM: personlig utlevering med mottakerbevis',
			'1136'	=>	'REK (rekommandert): registrert brevsending',
			'1137'	=>	'CarryOn Business Pallet 0900',
			'1138'	=>	'CarryOn Business EU-import',
			'1139'	=>	'CarryOn Homeshopping EU-import'
		);
		
        /**
         * Constructor, contains the data displayed in the backends Modules section
         */
		public function __construct()
		{
			$this->name	= 'proteriafrakt';
			$this->tab = 'shipping_logistics';
			$this->version = '1.2';
			$this->author = 'Marius';
			
			parent::__construct();
			
			$this->displayName = $this->l('Proteria Frakt');
			$this->description = $this->l('Modul for generering av XML import filer for Proteria Frakt');
			$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
			if (!Configuration::get('PROTERIAFRAKT_DIRECTORY'))
				$this->warning = $this->l('No output directory provided');
		}
		
        /**
         * Installation handler, set options when we install the Module
         * @return boolean
         */
		public function install()
		{
			if (!parent::install() OR 
				!$this->registerHook('invoice') OR 
				!$this->registerHook('updateCarrier') OR 
				!$this->registerHook('postUpdateOrderStatus') OR 
				!Configuration::updateValue('PROTERIAFRAKT_NAME', '') OR 
				!Configuration::updateValue('PROTERIAFRAKT_DIRECTORY', str_replace('\\', '/', dirname(__FILE__) ) . '/proteria/') OR 
				!Configuration::updateValue('PROTERIAFRAKT_SENDINGTYPE', '101'))
				return false;

			return true;
		}
        /**
         * Uninstall function, removes the values we've set so we don't clutter the database with unused entries
         * @return boolean
         */
		public function uninstall()
		{
			if (!parent::uninstall() OR 
				!$this->unregisterHook('invoice') OR 
				!$this->unregisterHook('updateCarrier') OR 
				!Configuration::deleteByname('PROTERIAFRAKT_NAME') OR 
				!Configuration::deleteByname('PROTERIAFRAKT_DIRECTORY') OR 
				!Configuration::deleteByname('PROTERIAFRAKT_SENDINGTYPE'))
				return false;
			
			return true;
		}
		
        /**
         * Write configuration changes to the database
         * @return string
         */
		public function getContent()
		{
			$this->_errors = array();
			
			$output = '<h2>' . $this->displayName . '</h2>';
			
			if (Tools::isSubmit('submitProteriaFrakt'))
			{
				$path = Tools::getValue('path');
				$name = Tools::getValue('name');
				$sendingtype = Tools::getValue('type');
				$status = Tools::getValue('status');
				if (empty($path))
					$this->_errors[] = $this->l('Path: no path entered');
					
				if (sizeof($this->_errors) == 0)
				{
					Configuration::updateValue('PROTERIAFRAKT_DIRECTORY', $path);
					Configuration::updateValue('PROTERIAFRAKT_NAME', $name);
					Configuration::updateValue('PROTERIAFRAKT_SENDINGTYPE', $sendingtype);
					Configuration::updateValue('PROTERIAFRAKT_ORDER_STATUS', $status);
					
					$qry = '
						SELECT 
							`' . _DB_PREFIX_ . 'carrier`.`id_carrier`,
							`' . _DB_PREFIX_ . 'carrier`.`name` 
						FROM 
							`' . _DB_PREFIX_ . 'carrier` 
						WHERE 
							`deleted` = 0
					';
					
					$carriers = Db::getInstance()->ExecuteS($qry);
					
					foreach ($carriers AS $carrier)
					{
						Configuration::updateValue('PROTERIAFRAKT_CARRIER_' . $carrier['id_carrier'], Tools::getValue('carrier-' . $carrier['id_carrier']));
					}
					
					$output .= $this->displayConf($this->l('Settings updated'));
				}
				else
					$output .= $this->displayErrors();
			}
			
			return $output . $this->displayForm();
		}
        /**
         * Display the configuration screen
         * @return string
         */
		public function displayForm()
		{
			global $cookie;
			
			$directory = Tools::getValue('path', Configuration::get('PROTERIAFRAKT_DIRECTORY'));
			$name = Tools::getValue('name', Configuration::get('PROTERIAFRAKT_NAME'));
			$type = Tools::getValue('type', Configuration::get('PROTERIAFRAKT_SENDINGTYPE'));
			$packages = Tools::getValue('packages', Configuration::get('PROTERIAFRAKT_PACKAGES'));
			$status = Tools::getValue('status', Configuration::get('PROTERIAFRAKT_ORDER_STATUS'));
			
			$return = "";
			
			$return .= '
				<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
					<fieldset>
						<legend>' . $this->l('Settings') . '</legend>
						
						<label>' . $this->l('Output diretory') . '</label>
						<div class="margin-form">
							<input type="text" name="path" value="' . $directory . '" />
						</div>
						
						<label>' . $this->l('Name') . '</label>
						<div class="margin-form">
							<input type="text" name="name" value="' . $name . '" />
						</div>
						
						<label>' . $this->l('Status') . '</label>
						<div class="margin-form">
							<select name="status">
			';

			$sql = "
				SELECT 
					`" . _DB_PREFIX_ . "order_state_lang`.`id_order_state`,
					`" . _DB_PREFIX_ . "order_state_lang`.`name` 
				FROM 
					`" . _DB_PREFIX_ . "order_state_lang` 
				WHERE 
					`" . _DB_PREFIX_ . "order_state_lang`.`id_lang` = '" . (int)($cookie->id_lang) . "'
			";
			
			$langs = Db::getInstance()->ExecuteS($sql);
			foreach ($langs AS $lang)
			{
				$return .= '<option value="' . $lang['id_order_state'] . '"' . ($lang['id_order_state'] == $status ? ' SELECTED' : '') . '>' . $lang['name'] . '</option>';
			}
			
					
			$return .= '			
							</select><br />
							<small>' . $this->l('Which order status should trigger the automatic Proteria XML generator?') . '</small>
						</div>
						
						<label>' . $this->l('Shipping type') . '</label>
						<div class="margin-form">
							<select name="type">
			';
			
			foreach (proteriafrakt::$types AS $typeId => $typeName)
			{
				$return .= '<option value="' . $typeId . '"' . ($typeId == $type ? ' SELECTED' : '') . '>' . $typeName . '</option>';
			}
			
			$return .= '
							</select>
						</div>
						
						<center><input type="submit" name="submitProteriaFrakt" value="' . $this->l('Save') . '" class="button" /></center>
					</fieldset>
					
					<fieldset>
						<legend>' . $this->l('Carriers') . '</legend>
			';
			
			$qry = '
				SELECT 
					`' . _DB_PREFIX_ . 'carrier`.`id_carrier`,
					`' . _DB_PREFIX_ . 'carrier`.`name` 
				FROM 
					`' . _DB_PREFIX_ . 'carrier` 
				WHERE 
					`deleted` = 0
			';
			
			$carriers = Db::getInstance()->ExecuteS($qry);
			
			foreach ($carriers AS $carrier)
			{
				$return .= '<label>' . $carrier['name'] . '</label>
					<div class="margin-form">
						<select name="carrier-' . $carrier['id_carrier'] . '">
				';
				
				foreach (proteriafrakt::$packages AS $typeId => $typeName)
				{
					$return .= '<option value="' . $typeId . '"' . ($typeId == Tools::getValue('carrier-' . $carrier['id_carrier'], Configuration::get('PROTERIAFRAKT_CARRIER_' . $carrier['id_carrier'])) ? ' SELECTED' : '') . '>' . $typeName . '</option>';
				}
				
				$return .= '
						</select>
					</div>
				';
			}
			
			$return .= '
						<center><input type="submit" name="submitProteriaFrakt" value="' . $this->l('Save') . '" class="button" /></center>
					</fieldset>
			';
			
			$return .=	'
				</form>
			';
			
			return $return;
		}
        
        /**
         * Function for outputting errors in a friendly human readable format
         * @return string
         */
		public function displayErrors()
		{
			$errors =
				'<div class="error">
					<img src="../img/admin/error2.png" />
					' . sizeof($this->_errors) . ' ' . (sizeof($this->_errors) > 1 ? $this->l('errors') : $this->l('error')) . '
					<ol>';
					
			foreach ($this->_errors AS $error)
				$errors .= '<li>' . $error . '</li>';
				
			$errors .= '
					</ol>
				</div>';
				
			return $errors;
		}
		public function displayConf($conf)
		{
			return
				'<div class="conf">
					<img src="../img/admin/ok2.png" /> ' . $conf . '
				</div>';
		}
		
        /**
         * Write proteria shipping XML file to the preconfigured location monitored by proteria
         * @param int £oid The order id
         * @return string
         */
		private function proteria($oid)
		{
			if (empty($oid))
				return false;
			
			$qry = "
				SELECT 
					`" . _DB_PREFIX_ . "orders`.`id_address_delivery`,
					`" . _DB_PREFIX_ . "orders`.`id_carrier`,
					`" . _DB_PREFIX_ . "address`.`lastname`,
					`" . _DB_PREFIX_ . "address`.`firstname`,
					`" . _DB_PREFIX_ . "address`.`address1`,
					`" . _DB_PREFIX_ . "address`.`postcode`,
					`" . _DB_PREFIX_ . "address`.`city`,
					`" . _DB_PREFIX_ . "address`.`phone`,
					`" . _DB_PREFIX_ . "address`.`phone_mobile` 
				FROM 
					`" . _DB_PREFIX_ . "orders` 
				JOIN 
					`" . _DB_PREFIX_ . "address` 
				ON 
					(`" . _DB_PREFIX_ . "address`.`id_address` = `" . _DB_PREFIX_ . "orders`.`id_address_delivery`) 
				WHERE 
					`" . _DB_PREFIX_ . "orders`.`id_order` = '{$oid}' 
				LIMIT 
					1
			";
			
			$data = Db::getInstance()->ExecuteS($qry);
			
			$path = Configuration::get('PROTERIAFRAKT_DIRECTORY') . Configuration::get('PROTERIAFRAKT_NAME') . $oid . '.xml';
			
			$data = $data[0];
			
			$xml = simplexml_load_file(dirname(__FILE__) . '/proteria-base.xml');
			
			$xml->Sending->OrdreNr					=	$oid;
			$xml->Sending->SendingsType				=	Configuration::get('PROTERIAFRAKT_SENDINGTYPE');
			$xml->Sending->PakkeType				=	Configuration::get('PROTERIAFRAKT_CARRIER_' . $data['id_carrier']);
			$xml->Sending->Mottaker->Navn			=	$data['firstname'] . ' ' . $data['lastname'];
			$xml->Sending->Mottaker->PostAdr1		=	$data['address1'];
			$xml->Sending->Mottaker->PostNr			=	$data['postcode'];
			$xml->Sending->Mottaker->PostSted		=	$data['city'];
			$xml->Sending->Mottaker->LevAdr1		=	$data['address1'];
			$xml->Sending->Mottaker->LevPostNr		=	$data['postcode'];
			$xml->Sending->Mottaker->LevPostSted	=	$data['city'];
			$xml->Sending->Mottaker->KontaktPerson	=	$data['firstname'] . ' ' . $data['lastname'];
			$xml->Sending->Mottaker->Mobil			=	(!empty($data['phone_mobile']) ? str_replace(" ", "", $data['phone_mobile']) : str_replace(" ", "", $data['phone']));
			
			if (!$write = @fopen($path, "w+"))
			{
				$work = '<font color="red">Det har oppst&aring;tt en feil under opprettelsen av Proteria Import filen.</font><br>';
				return $work;
			}
			if (!fwrite($write, $xml->asXML()))
				$work = '<font color="red">Det har oppst&aring;tt en feil under skrivingen til Proteria Import filen.</font><br>';
			else
				$work = '<font color="green">Proteria Import filen er skrevet.</font><br>';
			
			return $work;
			
		}
		
        /**
         * Hook into the invoice system to display a button for manually sending an order to proteria
         * @param array $params Parameters sent by Prestashop with the hook
         * @return string
         */
		public function hookinvoice($params)
		{
			$return = '
			<br><br />
			<fieldset style="width: 400px">
				<legend>Proteria Frakt</legend>
			';
			
			if (Tools::isSubmit('sendProteriaFrakt'))
			{
				$return .= $this->proteria($_GET['id_order']);
			}
			
			$return .= '
				<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">
					<center>
						<input type="submit" name="sendProteriaFrakt" value="' . $this->l('Send denne ordren til Proteria Frakt') . '" class="button" />
					</center>
				</form>
			</fieldset>';
			
			return $return;
		}
		
        /**
         * Prestashop removes old carriers and creates a new DB entry when a change is made, this is where we check if the carrier used is assocaited with proteria and if then adds our values ot the newly created entry
         * @param array @param Parameters sent by Prestashop with the hook
         */
		public function hookupdateCarrier($params)
		{			
			$old_check = Configuration::get('PROTERIAFRAKT_CARRIER_' . $params['id_carrier']);
			
			if (!empty($old_check))
			{
				Configuration::deleteByname('PROTERIAFRAKT_CARRIER_' . $params['id_carrier']);
				Configuration::updateValue('PROTERIAFRAKT_CARRIER_' . $params['carrier']->id, $old_check);
			}
		}
		
        /**
         * Triggered when the status of an order is changed, if the new status matches our automattic settings from the Proteria config page, call the proteria XML writer function
         * @param array $params Parameters send by Prestashop with the hook
         */
		public function hookpostUpdateOrderStatus($params)
		{
			if ($params['newOrderStatus']->id == Configuration::get('PROTERIAFRAKT_ORDER_STATUS'))
			{
				$return = $this->proteria($params['id_order']);
			}
		}
		
	}
	
?>