<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');
	
	Class fieldReferenceLink extends Field{

/***********************************************
		DEFINITION
***********************************************/
		
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Reference Link';
			$this->_required = true;

			// SET DEFAULT
			$this->set('show_column', 'no'); 
			$this->set('required', 'yes');
		}

		function findDefaults(&$fields){
			if(!isset($fields['allow_multiple_selection'])) $fields['allow_multiple_selection'] = 'no';
			if(!isset($fields['field_type'])) $fields['field_type'] = 'select';
		}

		public function createTable(){

			return $this->_engine->Database->query(

				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`relation_id` int(11) unsigned NOT NULL,
					PRIMARY KEY  (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `relation_id` (`relation_id`)
				) TYPE=MyISAM;"
			);
		}

    public function canToggle(){
      return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
    }

		public function canFilter(){
			return true;
		}

		public function allowDatasourceOutputGrouping(){
			return true;
		}

/***********************************************
		SETTINGS
***********************************************/

		function displaySettingsPanel(&$wrapper, $errors=NULL){		

			parent::displaySettingsPanel($wrapper, $errors);

			$div = new XMLElement('div', NULL, array('class' => 'group'));

			// BUILD OPTIONS

			$label = Widget::Label('Options');
			$sectionManager = new SectionManager($this->_engine);
			$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$field_groups = array();
			
			if(is_array($sections) && !empty($sections))
				foreach($sections as $section) $field_groups[$section->get('id')] = array('fields' => $section->fetchFields(), 'section' => $section);
			
			$options = array();
			
			foreach($field_groups as $group){
				if(!is_array($group['fields'])) continue;
				$fields = array();
				foreach($group['fields'] as $f){
					if($f->get('id') != $this->get('id') && $f->canPrePopulate()) $fields[] = array($f->get('id'), ($this->get('related_field_id') == $f->get('id')), $f->get('label'));
				}
				
				if(is_array($fields) && !empty($fields)) $options[] = array('label' => $group['section']->get('name'), 'options' => $fields);
			}

			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][related_field_id]', $options));
			$div->appendChild($label);

			if(isset($errors['related_field_id'])) $wrapper->appendChild(Widget::wrapFormElementWithError($div, $errors['related_field_id']));
			else $wrapper->appendChild($div);

			// SET FIELD DISPLAY

			$label = Widget::Label('Display As');
			$type_options = array(array('select', ($this->get('field_type') == 'select'), 'Select Box'),array('autocomplete', ($this->get('field_type') == 'autocomplete'), 'Autocomplete Input'));
			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][field_type]', $type_options));
			$div->appendChild($label);
						
			// ALLOW MULTIPLE SELECTION

			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');			
			$label->setValue($input->generate() . ' Allow selection of multiple options');
			$wrapper->appendChild($label);
			
			$this->appendShowColumnCheckbox($wrapper);
			$this->appendRequiredCheckbox($wrapper);
						
		}

		function commit(){

			if(!parent::commit()) return false;
			$id = $this->get('id');
			if($id === false) return false;
			
			$fields = array();
			
			$fields['field_id'] = $id;
			if($this->get('related_field_id') != '') $fields['related_field_id'] = $this->get('related_field_id');
			$fields['allow_multiple_selection'] = ($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');
			$fields['field_type'] = ($this->get('field_type') ? $this->get('field_type') : 'select');

			$this->Database->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id'");
			
			if(!$this->Database->insert($fields, 'tbl_fields_' . $this->handle())) return false;

			$sections = $this->get('related_field_id');

			$this->removeSectionAssociation($id);
			
			$section_id = $this->Database->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '".$fields['related_field_id']."' LIMIT 1");

			$this->createSectionAssociation(NULL, $id, $this->get('related_field_id'));
			
			return true;
					
		}

/***********************************************
		PUBLISH
***********************************************/

		function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL){

			// BUILD OPTIONS

			$states = $this->findOptions();
			$options = array();

			if($this->get('required') != 'yes') $options[] = array(NULL, false, NULL);

				if(is_array($data['relation_id'])) {
					$entry_id = array();
					foreach($data['relation_id'] as $value) { 
						$entry_id[] = $value;
					}
					foreach($states as $id => $v){
						if (in_array($id, $entry_id)) {
							$options[] = array($id, TRUE, $v);}
						else { $options[] = array($id, FALSE, $v); }
					}
				}
				else {
					$entry_id = $data['relation_id'];
					foreach($states as $id => $v){
						$options[] = array($id, $id == $entry_id, $v);
					}
			}

			// BUILD LABEL AND INPUT

			$fieldname = 'fields'.$fieldnamePrefix.'['.$this->get('element_name').']'.$fieldnamePostfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';

			$html_options = array();
			if($this->get('allow_multiple_selection') == 'yes') $html_options['multiple'] = 'multiple';
			if($this->get('field_type') == 'autocomplete') $html_options['class'] = 'replace';
			$html_options['id'] = $fieldname;

			$labeltext = $this->get('label');
			if($this->get('field_type') == 'autocomplete') $labeltext .= " <em>(Enter text for suggestions)</em>";
			
			$label = Widget::Label($labeltext);
			$label->appendChild(Widget::Select($fieldname, $options, $html_options));
			
			if($flagWithError != NULL) $wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			else $wrapper->appendChild($label);		
		}

/*****************************************
		INPUT
*****************************************/

		function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL){

			$status = self::__OK__;

			if($this->get('field_type') == 'autocomplete') {
				if($this->get('allow_multiple_selection') == 'yes') {
					$list = $data[0];
					$ids = explode(", ", $list);
					foreach($ids as $id) { 
						if($id != '') {
							$result['relation_id'][] = $id;
						}
					}
					return $result;
				}
				else {
					return array('relation_id' => $data);
				}
			}
			else {
			if(!is_array($data)) return array('relation_id' => $data);

			if(empty($data)) return NULL;
			
			$result = array();

			foreach($data as $a => $value) { 
				$result['relation_id'][] = $data[$a];
			}
			
			return $result;
			}
		}

/*****************************************
		OUTPUT
*****************************************/

		function appendFormattedElement(&$wrapper, $data, $encode=false){

			if(!is_array($data) || empty($data)) return;
			
			$list = new XMLElement($this->get('element_name'));
			if(!is_array($data['relation_id'])) $data['relation_id'] = array($data['relation_id']);
			
			foreach($data['relation_id'] as $value){

				$primary_field = $this->__findPrimaryFieldValueFromRelationID($value);    
				$section = $this->_engine->Database->fetchRow(0, "SELECT `id`, `handle` FROM `tbl_sections` WHERE `id` = '".$primary_field['parent_section']."' LIMIT 1");

				$item_handle = Lang::createHandle($primary_field['value']);

				$list->appendChild(new XMLElement('item', ($encode ? General::sanitize($value) : $primary_field['value']), array('handle' => $item_handle, 'id' => $value)));
			}

			$wrapper->appendChild($list);
		} 

		function prepareTableValue($data, XMLElement $link=NULL){

			$label = $primary_field['value'];

			if($link){
				$link->setValue(General::sanitize($label));
				return $link->generate();
			}

			else{

				// IF THERE ARE MULTIPLE RELATED ENTRIES

				if(is_array($data['relation_id'])) {
					$result = array();
					foreach($data['relation_id'] as $value){
						if(!$value || !$primary_field = $this->__findPrimaryFieldValueFromRelationID($value)) return parent::prepareTableValue(NULL);
						$entry_id = $value;
						$link = Widget::Anchor($primary_field['value'], URL . '/symphony/publish/'.$primary_field['section_handle'].'/edit/' . $entry_id . '/');
						$result[] = $link->generate();
					}
					foreach($result as $value){ 
						$output .= " " . $value; }
					return $output;
				}

				// IF THERE IS ONLY ONE RELATED ENTRY

				else {
					if(!$data['relation_id'] || !$primary_field = $this->__findPrimaryFieldValueFromRelationID($data['relation_id'])) {
						return parent::prepareTableValue(NULL);
					}
					$entry_id = $data['relation_id'];
					$link = Widget::Anchor($primary_field['value'], URL . '/symphony/publish/'.$primary_field['section_handle'].'/edit/' . $entry_id . '/');
					return $link->generate(); 
					}
				}
		}

/*****************************************
		RELATIONSHIPS
*****************************************/

		private function __findPrimaryFieldValueFromRelationID($id){

			$primary_field = $this->Database->fetchRow(0,

				"SELECT `f`.*, `s`.handle AS `section_handle`
				 FROM `tbl_fields` AS `f`
				 INNER JOIN `tbl_sections` AS `s` ON `s`.id = `f`.parent_section
				 WHERE `f`.id = '".$this->get('related_field_id')."'
				 ORDER BY `f`.sortorder ASC "
			);

			if(!$primary_field) return NULL;

			$field = $this->_Parent->create($primary_field['type']);

			$data = $this->Database->fetchRow(0, 
				"SELECT *
				 FROM `tbl_entries_data_".$this->get('related_field_id')."`
				 WHERE `entry_id` = '$id' ORDER BY `id` DESC"
			);

			if(empty($data)) return NULL;

			$primary_field['value'] = $field->prepareTableValue($data);	

			return $primary_field;
		}

		function fetchAssociatedEntrySearchValue($data){
			if(!is_array($data)) return $data;

			// NEED TO UPDATE THIS TO SEARCH BY ID ?

			$searchvalue = $this->_engine->Database->fetchRow(0, 
				"SELECT
					`entry_id`
				FROM
					`tbl_entries_data_".$this->get('related_field_id')."`
				WHERE 
					`handle` = '".addslashes($data['handle'])."' LIMIT 1"
			);

			return $searchvalue['entry_id'];
		}

		function fetchAssociatedEntryCount($value){
			return $this->_engine->Database->fetchVar('count', 0, "SELECT count(*) AS `count` FROM `tbl_entries_data_".$this->get('id')."` WHERE `relation_id` = '$value'");
		}

		function fetchAssociatedEntryIDs($value){
			return $this->_engine->Database->fetchCol('entry_id', "SELECT `entry_id` FROM `tbl_entries_data_".$this->get('id')."` WHERE `relation_id` = '$value'");
		}		

		public function getParameterPoolValue($data){
			return $data['relation_id'];
		}		
		
		function findOptions(){
			$values = array();

			$sql = 
				"SELECT DISTINCT 
					`value`, `entry_id` 
				FROM 
					`tbl_entries_data_".$this->get('related_field_id')."`
				ORDER BY `value` 
				DESC";

			if($results = $this->Database->fetch($sql)){
				foreach($results as $r){
					$values[$r['entry_id']] = $r['value'];
				}
			}

			return $values;
		}

/*****************************************
		FILTERING
*****************************************/

		function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false){

			$field_id = $this->get('id');

			if($andOperation):

				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND `t$field_id$key`.relation_id = '$bit' ";
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.relation_id IN ('".@implode("', '", $data)."') ";

			endif;

			return true;
		}

/*****************************************
		SORTING
*****************************************/

		function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC'){
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (strtolower($order) == 'random' ? 'RAND()' : "`ed`.`relation_id` $order");
		}

/*****************************************
		GROUPING
*****************************************/

		function groupRecords($records){

			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));

				$value = $data['relation_id'];

				$primary_field = $this->__findPrimaryFieldValueFromRelationID($data['relation_id']);

				if(!isset($groups[$this->get('element_name')][$value])) {
					$groups[$this->get('element_name')][$value] = array(
						'attr' => array(
							'link-id' => $data['relation_id'],
							'link-handle' => Lang::createHandle($primary_field['value'])
							),
							'records' => array(), 
							'groups' => array()
					);	
				}	

				$groups[$this->get('element_name')][$value]['records'][] = $r;
			}

			return $groups;
		}

	}