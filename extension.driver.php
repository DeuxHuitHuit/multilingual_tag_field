<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');



	define_safe(MTF_NAME, 'Field: Multilingual Tag List');
	define_safe(MTF_GROUP, 'multilingual_tag_field');



	class Extension_Multilingual_Tag_Field extends Extension
	{
		const FIELD_TABLE = 'tbl_fields_multilingual_tag';

		protected static $assets_loaded = false;



		/*------------------------------------------------------------------------------------------------*/
		/*  Installation  */
		/*------------------------------------------------------------------------------------------------*/

		public function install(){
			return Symphony::Database()
				->create(self::FIELD_TABLE)
				->ifNotExists()
				->charset('utf8')
				->collate('utf8_unicode_ci')
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'field_id' => 'int(11)',
					'validator' => 'varchar(50)',
					'pre_populate_source' => 'varchar(15)',
					'def_ref_lang' => [
						'type' => 'enum',
						'values' => ['yes','no'],
						'default' => 'no',
					],
				])
				->keys([
					'id' => 'primary',
					'field_id' => 'key',
				])
				->execute()
				->success();
		}

		public function update($previousVersion = false){
			if( version_compare($previousVersion, '1.2', '<') ){
				Symphony::Database()
					->rename('tbl_fields_multilingualtag')
					->to(self::FIELD_TABLE)
					->execute()
					->success();

				Symphony::Database()
					->update('tbl_fields')
					->set([
						'type' => 'multilingual_tag',
					])
					->where(['type' => 'multilingualtag'])
					->execute()
					->success();
			}

			return true;
		}

		public function uninstall(){
			return Symphony::Database()
				->drop(self::FIELD_TABLE)
				->ifExists()
				->execute()
				->success();
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
			$group->appendChild(new XMLElement('legend', __(MTF_NAME)));

			$label = Widget::Label(__('Consolidate entry data'));
			$label->prependChild(Widget::Input('settings['.MTF_GROUP.'][consolidate]', 'yes', 'checkbox', array('checked' => 'checked')));
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
			$fields = Symphony::Database()
				->select(['field_id'])
				->from(self::FIELD_TABLE)
				->execute()
				->rows();

			if( is_array($fields) && !empty($fields) ){
				$consolidate = $context['context']['settings'][MTF_GROUP]['consolidate'];

				// Foreach field check multilanguage values foreach language
				foreach( $fields as $field ){
					$entries_table = 'tbl_entries_data_'.$field["field_id"];

					try{
						$show_columns = Symphony::Database()
							->showColumns()
							->from($entries_table)
							->like('handle-%%')
							->execute()
							->rows();
					}
					catch( DatabaseException $dbe ){
						// Field doesn't exist. Better remove it's settings
						Symphony::Database()
							->delete(self::FIELD_TABLE)
							->where(['field_id' => $field['field_id']])
							->execute()
							->success();

						continue;
					}

					$columns = array();

					// Remove obsolete fields
					if( is_array($show_columns) && !empty($show_columns) )

						foreach( $show_columns as $column ){
							$lc = substr($column['Field'], strlen($column['Field']) - 2);

							// If not consolidate option AND column lang_code not in supported languages codes -> Drop Column
							if( ($consolidate !== 'yes') && !in_array($lc, $context['new_langs']) )
								Symphony::Database()
									->alter($entries_table)
									->drop([
										'handle-' . $lc,
										'value-' . $lc,
									])
									->execute()
									->success();
							else
								$columns[] = $column['Field'];
						}

					// Add new fields
					foreach( $context['new_langs'] as $lc ) {
						if( !in_array('handle-'.$lc, $columns) ) {
							Symphony::Database()
								->alter($entries_table)
								->add([
									'handle-' . $lc => [
										'type' => 'varchar(255)',
										'null' => true,
									],
									'value-' . $lc => [
										'type' => 'varchar(50)',
										'null' => true,
									],
								])
								->execute()
								->success();
						}
					}
				}
			}
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Public utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public static function appendAssets(){
			if( self::$assets_loaded === false
				&& class_exists('Administration')
				&& Administration::instance() instanceof Administration
				&& Administration::instance()->Page instanceof HTMLPage ){

				self::$assets_loaded = true;

				$page = Administration::instance()->Page;
			}
		}

	}
