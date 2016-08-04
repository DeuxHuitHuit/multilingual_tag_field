<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT.'/fields/field.taglist.php');
	require_once(EXTENSIONS.'/frontend_localisation/lib/class.FLang.php');



	final class fieldMultilingual_Tag extends fieldTagList
	{

		/*------------------------------------------------------------------------------------------------*/
		/*  Definition  */
		/*------------------------------------------------------------------------------------------------*/

		public function __construct(){
			parent::__construct();

			$this->_name = __('Multilingual Tag List');
		}

		public function createTable(){
			$query = "CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
	      			`id` int(11) unsigned NOT NULL auto_increment,
	    			`entry_id` int(11) unsigned NOT NULL,
	    			`handle` varchar(255) default NULL,
	    			`value` varchar(255) default NULL,";

			foreach( FLang::getLangs() as $lc ){
				$query .= "`handle-{$lc}` varchar(255) default NULL,
					`value-{$lc}` varchar(255) default NULL,";
			}

			$query .= "PRIMARY KEY (`id`),
				 KEY `entry_id` (`entry_id`)";

			foreach(  FLang::getLangs() as $lc ){
				$query .= ",KEY `handle-{$lc}` (`handle-{$lc}`)";
				$query .= ",KEY `value-{$lc}` (`value-{$lc}`)";
			}

			$query .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

			return Symphony::Database()->query($query);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Settings  */
		/*------------------------------------------------------------------------------------------------*/

		public function findDefaults(array &$settings){
			if( $settings['def_ref_lang'] != 'yes' ){
				$settings['def_ref_lang'] = 'no';
			}

			return parent::findDefaults($settings);
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
			parent::displaySettingsPanel($wrapper, $errors);

			$this->__appendDefLangValCheckbox($wrapper);
		}

		private function __appendDefLangValCheckbox(XMLElement &$wrapper){
			$label = Widget::Label();
			$input = Widget::Input("fields[{$this->get('sortorder')}][def_ref_lang]", 'yes', 'checkbox');
			if( $this->get('def_ref_lang') === 'yes' ) $input->setAttribute('checked', 'checked');
			$label->setValue(__('%s Use value from main language if selected language has empty value.', array($input->generate())));

			$wrapper->appendChild($label);
		}

		public function commit(){
			if( !parent::commit() ) return false;

			return Symphony::Database()->query(sprintf("
				UPDATE
					`tbl_fields_%s`
				SET
					`def_ref_lang` = '%s'
				WHERE
					`field_id` = '%s';",
				$this->handle(), $this->get('def_ref_lang'), $this->get('id')
			));
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Publish  */
		/*------------------------------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = NULL, $flagWithError = NULL, $fieldnamePrefix = NULL, $fieldnamePostfix = NULL, $entry_id = NULL){

			// We've been called out of context: Pulblish Filter
			$callback = Administration::instance()->getPageCallback();
			if($callback['context']['page'] != 'edit' && $callback['context']['page'] != 'new') {
				return;
			}

			Extension_Frontend_Localisation::appendAssets();
			Extension_Multilingual_Tag_Field::appendAssets();

			$main_lang = FLang::getMainLang();
			$all_langs = FLang::getAllLangs();
			$langs = FLang::getLangs();

			$wrapper->setAttribute('class', $wrapper->getAttribute('class').' field-multilingual');
			$container = new XMLElement('div', null, array('class' => 'container'));


			/*------------------------------------------------------------------------------------------------*/
			/*  Label  */
			/*------------------------------------------------------------------------------------------------*/

			$label = Widget::Label($this->get('label'));
			if( $this->get('required') != 'yes' ) $label->appendChild(new XMLElement('i', __('Optional')));
			$container->appendChild($label);


			/*------------------------------------------------------------------------------------------------*/
			/*  Tabs  */
			/*------------------------------------------------------------------------------------------------*/

			$ul = new XMLElement('ul', null, array('class' => 'tabs'));
			foreach( $langs as $lc ){
				$li = new XMLElement('li', $all_langs[$lc], array('class' => $lc));
				$lc === $main_lang ? $ul->prependChild($li) : $ul->appendChild($li);
			}

			$container->appendChild($ul);


			/*------------------------------------------------------------------------------------------------*/
			/*  Panels  */
			/*------------------------------------------------------------------------------------------------*/

			foreach( $langs as $lc ){
				$div = new XMLElement('div', NULL, array('class' => 'tab-panel tab-'.$lc));

				$label = Widget::Label();

				$value = null;

				if( isset($data['value-'.$lc]) ){
					$data['value-'.$lc] = $this->__clearEmtpyTags($data['value-'.$lc]);
					$value = (is_array($data['value-'.$lc]) ? self::__tagArrayToString($data['value-'.$lc]) : $data['value-'.$lc]);
				}

				$label->appendChild(
					Widget::Input(
						"fields{$fieldnamePrefix}[{$this->get('element_name')}][{$lc}]{$fieldnamePostfix}",
						(strlen($value) != 0 ? General::sanitize($value) : NULL)
					)
				);

				$div->appendChild($label);

				if( $this->get('pre_populate_source') != NULL ){

					$existing_tags = $this->findAllTags($lc);

					if( is_array($existing_tags) && !empty($existing_tags) ){
						$taglist = new XMLElement('ul');
						$taglist->setAttribute('class', 'tags');
						$taglist->setAttribute('data-interactive', 'data-interactive');

						foreach( $existing_tags as $tag ){
							$taglist->appendChild(
								new XMLElement('li', General::sanitize($tag))
							);
						}

						$div->appendChild($taglist);
					}

				}
				$container->appendChild($div);
			}


			/*------------------------------------------------------------------------------------------------*/
			/*  Errors  */
			/*------------------------------------------------------------------------------------------------*/

			if( $flagWithError != NULL )
				$wrapper->appendChild(Widget::Error($container, $flagWithError));
			else
				$wrapper->appendChild($container);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Input  */
		/*------------------------------------------------------------------------------------------------*/

		public function checkPostFieldData($data, &$message, $entry_id = NULL){
			$error = self::__OK__;
			$field_data = $data;

			foreach( FLang::getLangs() as $lc ){

				$field_message = '';
				$data = $field_data[$lc];

				$status = parent::checkPostFieldData($data, $field_message, $entry_id);

				// if one language fails, all fail
				if( $status != self::__OK__ ){
					$message .= "<br />{$lc}: {$field_message}";
					$error = self::__ERROR__;
				}
			}

			return $error;
		}

		public function processRawFieldData($data, &$status, &$message = NULL, $simulate = false, $entry_id = NULL){
			if( !is_array($data) || empty($data) ) return parent::processRawFieldData($data, $status, $simulate, $entry_id);

			$result = array();
			$field_data = $data;

			$max_lang_tags = 0;
			foreach( $field_data as $value ){
				$a = explode(',', $value);
				$max_lang_tags = (count($a) > $max_lang_tags ? count($a) : $max_lang_tags);
			}

			foreach( FLang::getLangs() as $lc ){

				$data = $field_data[$lc];

				// $this->_fakeDefaultFile($language_code, $entry_id);
				$field_result = parent::processRawFieldData($data, $status, $simulate, $entry_id, $lc);

				// complete array values with empty values to insert same number of fields 
				// for all languages to avoid SQL malfunction avoid in multiple insert generation
				$count = count($field_result['value']);
				for( $i = $max_lang_tags; $i > $count; $i-- ){
					$field_result['value'][] = '';
					$field_result['handle'][] = '';
				}
				if( is_array($field_result) ){
					foreach( $field_result as $key => $value ){
						$result[$key.'-'.$lc] = $value;
					}
				}
			}

			return $result;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Output  */
		/*------------------------------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = NULL, $entry_id = NULL){
			$lang_code = FLang::getLangCode();

			// If called without language_code (search_index) return values of all languages
			if( $lang_code === '' ){
				foreach( FLang::getLangs() as $lang_code ){
					$data['handle'] .= ' '.$data['handle-'.$lang_code];
					$data['value'] .= ' '.$data['value-'.$lang_code];
				}
			} else{
				// If value is empty for this language, load value from main language
				if( $this->get('def_ref_lang') == 'yes' && $data['value-'.$lang_code] == '' ){
					$lang_code = FLang::getMainLang();
				}

				$data['handle'] = $data['handle-'.$lang_code];
				$data['value'] = $data['value-'.$lang_code];
			}


			parent::appendFormattedElement($wrapper, $data);
		}

		public function prepareTableValue($data, XMLElement $link = NULL, $entry_id = null){
			$lang_code = FLang::getMainLang();

			$data['value'] = $this->__clearEmtpyTags($data['value-'.$lang_code]);
			$data['handle'] = $this->__clearEmtpyTags($data['handle-'.$lang_code]);

			return parent::prepareTableValue($data, $link, $entry_id);
		}

		public function getParameterPoolValue(array $data, $entry_id = NULL){
			return $this->__clearEmtpyTags($data['value-'.FLang::getMainLang()]);
		}

		public function getExampleFormMarkup(){
			$label = Widget::Label($this->get('label').'
					<!-- '.__('Modify just current language value').' -->
					<input name="fields['.$this->get('element_name').'][value-{$url-fl-language}]" type="text" />

					<!-- '.__('Modify all values').' -->');

			foreach( FLang::getLangs() as $lc ){
				$label->appendChild(Widget::Input("fields[{$this->get('element_name')}][value-{$lc}]"));
			}

			return $label;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Filtering  */
		/*------------------------------------------------------------------------------------------------*/

		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false){
			$lang_code = FLang::getLangCode();
			$field_id = $this->get('id');

			if( self::isFilterRegex($data[0]) ){
				$this->_key++;

				if( preg_match('/^regexp:/i', $data[0]) ){
					$pattern = preg_replace('/^regexp:\s*/i', null, $this->cleanValue($data[0]));
					$regex = 'REGEXP';
				} else{
					$pattern = preg_replace('/^not-?regexp:\s*/i', null, $this->cleanValue($data[0]));
					$regex = 'NOT REGEXP';
				}

				if( strlen($pattern) === 0 ) return false;

				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{$this->_key}.value {$regex} '{$pattern}'
						OR t{$field_id}_{$this->_key}.`handle-".$lang_code."` {$regex} '{$pattern}'
					)
				";

			} elseif( $andOperation ){
				foreach( $data as $value ){
					$this->_key++;
					$value = $this->cleanValue($value);
					$joins .= "
						LEFT JOIN
							`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
							ON (e.id = t{$field_id}_{$this->_key}.entry_id)
					";
					$where .= "
						AND (
							t{$field_id}_{$this->_key}.value = '{$value}'
							OR t{$field_id}_{$this->_key}.`handle-".$lang_code."` = '{$value}'
						)
					";
				}

			} else{
				if( !is_array($data) ) $data = array($data);

				foreach( $data as &$value ){
					$value = $this->cleanValue($value);
				}

				$this->_key++;
				$data = implode("', '", $data);
				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{$this->_key}.`value-".$lang_code."` IN ('{$data}')
						OR t{$field_id}_{$this->_key}.`handle-".$lang_code."` IN ('{$data}')
					)
				";
			}

			return true;
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  Utilities  */
		/*------------------------------------------------------------------------------------------------*/

		public function findAllTags($lang_code = null){
			if( !is_array($this->get('pre_populate_source')) ) return array();

			$values = array();

			if( $lang_code !== null ){
				foreach( $this->get('pre_populate_source') as $item ){
					try {
						$result = Symphony::Database()->fetchCol('value-'.$lang_code, sprintf(
							"SELECT DISTINCT `value-$lang_code` FROM tbl_entries_data_%d ORDER BY `value-$lang_code` ASC",
							($item == 'existing' ? $this->get('id') : $item)
						));
					}
					catch (Exception $ex) {
						try {
							$result = Symphony::Database()->fetchCol('value', sprintf(
								"SELECT DISTINCT `value` FROM tbl_entries_data_%d ORDER BY `value` ASC",
								($item == 'existing' ? $this->get('id') : $item)
							));
						}
						catch (Exception $ex) {
							$result = null;
						}
					}

					if( !is_array($result) || empty($result) ) continue;

					$values = array_merge($values, $result);
				}
			}

			return array_unique($values);
		}



		/*------------------------------------------------------------------------------------------------*/
		/*  In-house  */
		/*------------------------------------------------------------------------------------------------*/

		private function __clearEmtpyTags($tags){

			if( !is_array($tags) ) return $tags;

			// Clear empty tag values
			foreach( $tags as $key => $tag )
			{
				if( empty($tags[$key]) ){
					unset($tags[$key]);
				}
			}

			return $tags;
		}

		private static function __tagArrayToString(array $tags){
			if( empty($tags) ) return null;

			sort($tags);

			return implode(', ', $tags);
		}

	}
