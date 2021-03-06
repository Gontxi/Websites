<?php
class Fieldtype_replicator extends Fieldtype
{

	/**
	 * Render the field
	 * 
	 * @return string
	 */
	public function render()
	{
		$vars = array(
			'set_types'     => $this->setTypes(),
			'set_templates' => $this->setTemplates(),
			'sets'          => $this->existingSets(),
			'field_id'      => $this->field_id
		);

		$template = File::get($this->getAddonLocation() . 'views/fieldtype.html');
		
		return Parse::template($template, $vars);
	}


	/**
	 * Build `sets` from config without any data
	 * 
	 * @return array
	 */
	private function setTypes()
	{
		foreach ($this->field_config['sets'] as $set_name => $set) {
			$this->set_types[] = array(
				'display'    => array_get($set, 'display', Slug::prettify($set_name)),
				'name'       => $set_name,
				'fieldtypes' => $this->fieldtypeTemplates($set_name, $set['fields'])
			);
		}

		return $this->set_types;
	}


	/**
	 * Generate the set templates
	 * 
	 * @return string  JSON encoded string of templates
	 */
	private function setTemplates()
	{
		$template = File::get($this->getAddonLocation() . 'views/templates.html');
		
		foreach ($this->set_types as $set) {
			$templates[] = array(
				'type' => $set['name'],
				'html' => Parse::template($template, $set)
			);
		}
		
		return json_encode($templates);
	}


	/**
	 * Build sets from front matter data
	 * 
	 * @return array
	 */
	private function existingSets()
	{
		$sets = array();

		if ($this->field_data) {
			$i = 0;
			foreach ($this->field_data as $data) {
				$set_name = $data['type'];
				unset($data['type']);
				$set = $this->field_config['sets'][$set_name];
				$sets[] = array(
					'display'    => array_get($set, 'display', Slug::prettify($set_name)),
					'name'       => $set_name,
					'fieldtypes' => $this->fieldtypes($i, $set, $data)
				);
				$i++;
			}
		}

		return $sets;
	}


	/**
	 * Build fieldtypes without any data
	 * 
	 * @param  string $set_name The name of the set type
	 * @param  array  $fields     Fields inside the set
	 * @return array
	 */
	private function fieldtypeTemplates($set_name, $fields)
	{
		foreach ($fields as $field_name => $field) {
			$key = "[yaml][{$this->field}][%%replicator_index%%]";
			$id = $field_name . '_%%replicator_index%%';
			$type = array_get($field, 'type', 'text');

			$field['display'] = array_get($field, 'display', Slug::prettify($field_name));

			if ($type == 'grid') {
				$field_name = "{$this->field}][%%replicator_index%%][$field_name";
			}

			$fieldtypes[] = array(
				'type' => $type,
				'field_name' => $key,
				'fieldtype' => Fieldtype::render_fieldtype($type, $field_name, $field, null, null, $key, $id)
			);
		}

		return $fieldtypes;
	}


	/**
	 * Build fieldtypes with data from front matter
	 * 
	 * @param  int    $i     The index of the field in the set
	 * @param  array  $set The set's field config
	 * @param  array  $data  This set's front matter data
	 * @return array
	 */
	private function fieldtypes($i, $set, $data)
	{
		foreach($set['fields'] as $field_name => $field) {
			$key = "[yaml][{$this->field}][$i]";
			$id = $field_name .'_'. $i;
			$field_data = array_get($data, $field_name);
			
			$field_config = $set['fields'][$field_name];
			$field_config['display'] = array_get($field_config, 'display', Slug::prettify($field_name));

			if ($field['type'] == 'grid') {
				$field_name = "{$this->field}][$i][$field_name";
			}

			$fieldtypes[] = array(
				'type' => $field['type'],
				'field_name' => $key,
				'fieldtype' => Fieldtype::render_fieldtype($field['type'], $field_name, $field_config, $field_data, null, $key, $id)
			);
		}

		return $fieldtypes;
	}


	/**
	 * Process data after submission
	 * 
	 * @return $array
	 */
	public function process()
	{
		// Process fieldtypes		
		foreach ($this->field_data as $set_index => $set_data) {
			$set_name = $set_data['type'];
			$set_fields = $this->settings['sets'][$set_name]['fields'];
			unset($set_data['type']);
			foreach ($set_data as $set_field_name => $set_field_data) {
				$set_field_settings = $set_fields[$set_field_name];
				$the_field_type = array_get($set_field_settings, 'type', 'text');
				$field_name = ($the_field_type == 'grid') 
				              ? "$this->fieldname:$set_index:$set_field_name"
				              : $this->fieldname;
				$this->field_data[$set_index][$set_field_name] = Fieldtype::process_field_data(
					$the_field_type, 
					$set_field_data, 
					$set_field_settings, 
					$field_name
				);
			}
		}

		// JavaScript will move the fields around.
		// We just want the indexes to be reset.
		return array_values($this->field_data);
	}

}