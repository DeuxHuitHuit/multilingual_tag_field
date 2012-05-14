<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	define_safe(MTF_NAME, 'Field: Multilingual Tag List');
	define_safe(MTF_GROUP, 'multilingual_tag_field');



	class extension_multilingual_tag_field extends Extension
	{

		protected $assets_loaded = false;



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install(){
			return Symphony::Database()->query(
				"CREATE TABLE `tbl_fields_multilingualtag` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`validator` varchar(50),
					`pre_populate_source` varchar(15),
					`def_ref_lang` enum('yes','no') default 'yes',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;"
			);
		}

		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_fields_multilingualtag`");
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Delegates  */
		/*------------------------------------------------------------------------------------------------*/

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'dAddCustomPreferenceFieldsets'
				),
				array(
					'page' => '/extensions/frontend_localisation/',
					'delegate' => 'FLSavePreferences',
					'callback' => 'dFLSavePreferences'
				),
			);
		}


		/*------------------------------------------------------------------------------------------------*/
		/*  System preferences  */
		/*------------------------------------------------------------------------------------------------*/

		/**
		 * Display options on Preferences page.
		 *
		 * @param array $context
		 */
		public function dAddCustomPreferenceFieldsets($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __(MUF_NAME)));

			$label = Widget::Label(__('Consolidate entry data'));
			$label->appendChild(Widget::Input('settings['.MUF_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('Check this field if you want to consolidate database by <b>keeping</b> entry values of removed/old Language Driver language codes. Entry values of current language codes will not be affected.'), array('class' => 'help')));

			$context['wrapper']->appendChild($group);
		}

		/**
		 * Save options from Preferences page
		 *
		 * @param array $context
		 */
		public function dFLSavePreferences($context){
			$fields = Symphony::Database()->fetch('SELECT `field_id` FROM `tbl_fields_multilingualtag`');

			if( $fields ){
				// Foreach field check multilanguage values foreach language
				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()->fetch("SHOW COLUMNS FROM `{$entries_table}` LIKE 'file-%'");
					}
					catch( DatabaseException $dbe ){
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()->query("DELETE FROM `tbl_fields_multilingualtag` WHERE `field_id` = {$field["field_id"]};");
						continue;
					}

					$columns = array();

					if( $show_columns ){
						foreach( $show_columns as $column ){
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if( ($_POST['settings'][MTF_GROUP]['consolidate'] !== 'yes') && !in_array($lc, $context['new_langs']) ){
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `handle-{$lc}`");
								Symphony::Database()->query("ALTER TABLE  `{$entries_table}` DROP COLUMN `value-{$lc}`");
							} else{
								$columns[] = $column['Field'];
							}
						}
					}

					// Add new fields
					foreach( $context['new_langs'] as $lc ){
						// If columna lang_code dosen't exist in the language drop columns

						if( !in_array('handle-'.$lc, $columns) ){
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `handle-{$lc}` varchar(255) default NULL");
							Symphony::Database()->query("ALTER TABLE  `{$entries_table}` ADD COLUMN `value-{$lc}` varchar(255) default NULL");
						}
					}

				}
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendAssets(){
			if( $this->assets_loaded === false ){
				$this->assets_loaded = true;

				$page = Administration::instance()->Page;

				$page->addStylesheetToHead(URL.'/extensions/'.MTF_GROUP.'/assets/'.MTF_GROUP.'.publish.css', 'screen', null, false);

				// multilingual stuff
				$fl_assets = URL.'/extensions/frontend_localisation/assets/frontend_localisation.multilingual_tabs';
				$page->addStylesheetToHead($fl_assets.'.css', 'screen', null, false);
				$page->addScriptToHead($fl_assets.'_init.js', null, false);
			}
		}

	}
