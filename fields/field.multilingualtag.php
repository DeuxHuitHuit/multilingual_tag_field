<?php

	if( !defined('__IN_SYMPHONY__') ) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	require_once(TOOLKIT . '/fields/field.taglist.php');
	require_once(EXTENSIONS . '/frontend_localisation/lib/class.FLang.php');



	final class fieldMultilingualTag extends fieldTagList
	{

		public function __construct(&$parent){
			parent::__construct($parent);

			$this->_name = __('Multilingual Tag List');
		}

		
		
	/*-------------------------------------------------------------------------
		 Setup:
	-------------------------------------------------------------------------*/

		public function createTable(){
			$query = "CREATE TABLE IF NOT EXISTS `tbl_entries_data_{$this->get('id')}` (
	      		`id` int(11) unsigned NOT NULL auto_increment,
	    			`entry_id` int(11) unsigned NOT NULL,
	    			`handle` varchar(255) default NULL,
	    			`value` varchar(255) default NULL,";

			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				$query .= "`handle-{$language_code}` varchar(255) default NULL,
					`value-{$language_code}` varchar(255) default NULL,";
			}

			$query .= "PRIMARY KEY (`id`),
				 KEY `entry_id` (`entry_id`)";

			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				$query .= ",KEY `handle-{$language_code}` (`handle-{$language_code}`)";
				$query .= ",KEY `value-{$language_code}` (`value-{$language_code}`)";
			}

	    $query .= ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

			return Symphony::Database()->query($query);
		}
		

	/*-------------------------------------------------------------------------
		Utilities:
	-------------------------------------------------------------------------*/

		public function findAllTags($language_code = null){
			if(!is_array($this->get('pre_populate_source'))) return;

			$values = array();

			if ($language_code !== null) {
				foreach($this->get('pre_populate_source') as $item){
					$result = Symphony::Database()->fetchCol('value-'.$language_code, sprintf(
						"SELECT DISTINCT `value-".$language_code."` FROM tbl_entries_data_%d ORDER BY `value-".$language_code."` ASC",
						($item == 'existing' ? $this->get('id') : $item)
					));

					if(!is_array($result) || empty($result)) continue;

					$values = array_merge($values, $result);
				}
			}

			return array_unique($values);
		}

		private function __clearEmtpyTags($tags) {

			if (!is_array($tags)) return $tags;

			// Clear empty tag values
			foreach ($tags as $key => $tag)
			{
			    if (empty($tags[$key]))
			    {
			        unset($tags[$key]);
			    }
			}

			return $tags;
		}

		private static function __tagArrayToString(array $tags){
			if(empty($tags)) return NULL;

			sort($tags);

			return implode(', ', $tags);
		}		

	/*-------------------------------------------------------------------------
		 Settings:
	-------------------------------------------------------------------------*/

		public function findDefaults(&$settings){
			
			if( $settings['def_ref_lang'] != 'yes' ){
				$settings['def_ref_lang'] = 'no';
			}
			
			return parent::findDefaults($settings);
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
			parent::displaySettingsPanel($wrapper, $errors);

			foreach( $wrapper->getChildrenByName('label') as $label ){

			if( $label->getAttribute('class') == 'meta' ){
					
					$label->appendChild(
						$this->_appendDefLangValCheckbox()
					);
					
					break;
				}
			}
		}

		private function _appendDefLangValCheckbox() {
			$order = $this->get('sortorder');
		
			$label = Widget::Label();
			$input = Widget::Input("fields[{$order}][def_ref_lang]", 'yes', 'checkbox');
		
			if ($this->get('def_ref_lang') == 'yes') $input->setAttribute('checked', 'checked');
		
			$label->setValue(__('%s Use value from reference language if selected language has empty value.', array($input->generate())));
		
			return $label;
		}

		public function commit(){
			if( !Field::commit() ) return false;

			$id = $this->get('id');

			if( $id === false ) return false;

			$settings = array();

			$settings['field_id'] = $id;
			$settings['pre_populate_source'] = (is_null($this->get('pre_populate_source')) ? NULL : implode(',', $this->get('pre_populate_source')));
			$settings['validator'] = ($settings['validator'] == 'custom' ? NULL : $this->get('validator'));
			$settings['def_ref_lang'] = $this->get('def_ref_lang');

			Symphony::Database()->query("DELETE FROM `tbl_fields_" . $this->handle() . "` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($settings, 'tbl_fields_' . $this->handle());
		}
				
		
	/*-------------------------------------------------------------------------
		 Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = NULL, $flagWithError = NULL, $fieldnamePrefix = NULL, $fieldnamePostfix = NULL){
			$callback = Administration::instance()->getPageCallback();
			
			if(
			// if not Standard Section
					($callback['context']['page'] != 'edit' && $callback['context']['page'] != 'new')
					// and not Custom Preferences
					&& ($callback['pageroot'] != '/extension/custompreferences/preferences/' && $callback['driver'] != 'preferences' )
			) {
				return;
			}
			
			// append Assets
			Administration::instance()->Page->addScriptToHead(URL . '/extensions/multilingual_tag_field/assets/multilingual_tag_field.content.js', 10251842, false);
			Administration::instance()->Page->addStylesheetToHead(URL . '/extensions/multilingual_tag_field/assets/multilingual_tag_field.content.css', "screen");
			
			
			$wrapper->setAttribute('class', $wrapper->getAttribute('class') . ' field-multilingual');

			$container = new XMLElement('div', null, array('class' => 'container'));


			/* Label */

			$label = Widget::Label($this->get('label'));
			$class = 'taglist';
			$label->setAttribute('class', $class);
			if( $this->get('required') != 'yes' ) $label->appendChild(new XMLElement('i', __('Optional')));

			$container->appendChild($label);


			$reference_language = FLang::instance()->referenceLanguage();
			$all_languages = FLang::instance()->ld()->allLanguages();
			$language_codes = FLang::instance()->ld()->languageCodes();


			/* Tabs */

			$ul = new XMLElement('ul');
			$ul->setAttribute('class', 'tabs');

			foreach( $language_codes as $language_code ){
				$class = $language_code . ($language_code == $reference_language ? ' active' : '');
				$li = new XMLElement('li', ($all_languages[$language_code] ? $all_languages[$language_code] : __('Unknown language')));
				$li->setAttribute('class', $class);

				// to use this, Multilingual Text must depend on Frontend Localisation so UX is consistent regarding Language Tabs
				//				if( $language_code == $reference_language ){
				//					$ul->prependChild($li);
				//				}
				//				else{
				$ul->appendChild($li);
				//				}
			}

			$container->appendChild($ul);


			/* Inputs */

			foreach( $language_codes as $language_code ){
				$div = new XMLElement('div', NULL, array('class' => 'taglist tab-panel tab-' . $language_code));

				$label = Widget::Label();

				$value = NULL;

				if(isset($data['value-'.$language_code])){
					$data['value-'.$language_code] = $this->__clearEmtpyTags($data['value-'.$language_code]);
					$value = (is_array($data['value-'.$language_code]) ? self::__tagArrayToString($data['value-'.$language_code]) : $data['value-'.$language_code]);
				}

				$label->appendChild(
					Widget::Input(
						'fields'.$fieldnamePrefix.'['.$this->get('element_name').'][' . $language_code . ']'.$fieldnamePostfix, (strlen($value) != 0 ? General::sanitize($value) : NULL))
				);

				$div->appendChild($label);

				if($this->get('pre_populate_source') != NULL){

					$existing_tags = $this->findAllTags($language_code);

					if(is_array($existing_tags) && !empty($existing_tags)){
						$taglist = new XMLElement('ul');
						$taglist->setAttribute('class', 'tags');

						foreach($existing_tags as $tag) {
							$taglist->appendChild(
								new XMLElement('li', General::sanitize($tag))
							);
						}

						$div->appendChild($taglist);
					}

				}
				$container->appendChild($div);
			}



			if($flagWithError != NULL) 
				$wrapper->appendChild(Widget::wrapFormElementWithError($container, $flagWithError));
			else 
				$wrapper->appendChild($container);
		}

		public function checkPostFieldData($data, &$message, $entry_id = NULL){
			$error = self::__OK__;
			$field_data = $data;

			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){

				$field_message = '';
				$data = $field_data[$language_code];

				$status = parent::checkPostFieldData($data, $field_message, $entry_id);
				
				// if one language fails, all fail
				if( $status != self::__OK__ ){
					$message .= "<br />{$language_code}: {$file_message}";
					$error = self::__ERROR__;
				}
			}

			return $error;
		}

		public function processRawFieldData($data, &$status, $simulate = false, $entry_id = NULL){
			if( !is_array($data) || empty($data) ) return parent::processRawFieldData($data, $status, $simulate, $entry_id);

			$result = array();
			$field_data = $data;
			
			$max_lang_tags = 0;
			foreach ($field_data as $key => $value) {
				$a = split(',', $value);
				$max_lang_tags = (count($a) > $max_lang_tags ? count($a) : $max_lang_tags);
			}

			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){

				$data = $field_data[$language_code];

				// $this->_fakeDefaultFile($language_code, $entry_id);
				$field_result = parent::processRawFieldData($data, $status, $simulate, $entry_id, $language_code);

				// complete array values with empty values to insert same number of fields 
				// for all languages to avoid SQL malfunction avoid in multiple insert generation
				$count = count($field_result['value']);
				for ($i = $max_lang_tags; $i > $count; $i--) {
					$field_result['value'][] = '';
					$field_result['handle'][] = '';
				}
				if( is_array($field_result) ){
					foreach( $field_result as $key => $value ){
						$result[$key.'-'.$language_code] = $value;
					}
				}
			}

			return $result;
		}

		
		
	/*-------------------------------------------------------------------------
		 Output:
	-------------------------------------------------------------------------*/

		public function appendFormattedElement(XMLElement &$wrapper, $data){
			$language_code = FLang::instance()->ld()->languageCode();

			// If called without language_code (search_index) return values of all languages
			if ($language_code === '') {
				foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
					$data['handle'] .= ' '.$data['handle-' . $language_code];
					$data['value'] .= ' '.$data['value-' . $language_code];
				}
			} else {
				// If value is empty for this language, load value from reference language
				if( $this->get('def_ref_lang') == 'yes' && $data['value-'.$language_code] == '' ){
					$language_code = FLang::instance()->referenceLanguage();
				}

				$data['handle'] = $data['handle-' . $language_code];
				$data['value'] = $data['value-' . $language_code];
			}


			parent::appendFormattedElement($wrapper, $data);
		}

		public function prepareTableValue($data, XMLElement $link = NULL, $entry_id = null){
			// default to backend language
			// $language_code = Lang::get();
			$language_code = FLang::instance()->referenceLanguage();

			if(
				// language not supported
				!in_array($language_code, FLang::instance()->ld()->languageCodes())
				// or value is empty for this language
				|| ( $this->get('def_ref_lang') == 'yes' && $data['value-'.$language_code] == '' )
			){
				$language_code = FLang::instance()->referenceLanguage();
			}

			$data['value'] = $this->__clearEmtpyTags($data['value-' . $language_code]);
			$data['handle'] = $this->__clearEmtpyTags($data['handle-' . $language_code]);

			return parent::prepareTableValue($data, $link, $entry_id);
		}

		public function getParameterPoolValue($data){
			$language_code = FLang::instance()->ld()->languageCode();
			
			// If value is empty for this language, load value from reference language
			if( $this->get('def_ref_lang') == 'yes' && $data['value-'.$language_code] == '' ){
				$language_code = FLang::instance()->referenceLanguage();
			}
			
			return $this->__clearEmtpyTags($data['value-'.$language_code]);
		}

		public function getExampleFormMarkup(){

			$fieldname = 'fields[' . $this->get('element_name') . '][value-{$url-language}]';

			$label = Widget::Label($this->get('label') . '
			<!-- ' . __('Modify just current language value') . ' -->
			<input name="fields[' . $this->get('element_name') . '][value-{$url-language}]" type="text" />
			
			<!-- ' . __('Modify all values') . ' -->');

			foreach( FLang::instance()->ld()->languageCodes() as $language_code ){
				$fieldname = 'fields[' . $this->get('element_name') . '][value-' . $language_code . ']';
				$label->appendChild(Widget::Input($fieldname));
			}

			return $label;
		}

		
	/*-------------------------------------------------------------------------
		Filtering
	-------------------------------------------------------------------------*/
		public function buildDSRetrievalSQL($data, &$joins, &$where, $andOperation = false) {
			$language_code = FLang::instance()->ld()->languageCode();
			$field_id = $this->get('id');

			if (self::isFilterRegex($data[0])) {
				$this->_key++;

				if (preg_match('/^regexp:/i', $data[0])) {
					$pattern = preg_replace('/^regexp:\s*/i', null, $this->cleanValue($data[0]));
					$regex = 'REGEXP';
				} else {
					$pattern = preg_replace('/^not-?regexp:\s*/i', null, $this->cleanValue($data[0]));
					$regex = 'NOT REGEXP';
				}

				if(strlen($pattern) == 0) return;

				$joins .= "
					LEFT JOIN
						`tbl_entries_data_{$field_id}` AS t{$field_id}_{$this->_key}
						ON (e.id = t{$field_id}_{$this->_key}.entry_id)
				";
				$where .= "
					AND (
						t{$field_id}_{$this->_key}.value {$regex} '{$pattern}'
						OR t{$field_id}_{$this->_key}.`handle-".$language_code."` {$regex} '{$pattern}'
					)
				";

			} elseif ($andOperation) {
				foreach ($data as $value) {
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
							OR t{$field_id}_{$this->_key}.`handle-".$language_code."` = '{$value}'
						)
					";
				}

			} else {
				if (!is_array($data)) $data = array($data);

				foreach ($data as &$value) {
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
						t{$field_id}_{$this->_key}.`value-".$language_code."` IN ('{$data}')
						OR t{$field_id}_{$this->_key}.`handle-".$language_code."` IN ('{$data}')
					)
				";
			}

			return true;
		}
		
	/*-------------------------------------------------------------------------
		 In-house utilities:
	-------------------------------------------------------------------------*/

	}
