<?php defined('SYSPATH') or die('No direct script access.');

abstract class FormManager_Core_FormManager
{

	protected $model = null;
	protected $primary_key = null;
	protected $rules = array();
	public $fields = array();
	public $object = null;

	public $method = 'post';
	public $action = '';
	public $submit_text;

	const SUBMIT_STATUS_FAIL = 'fail';
	const SUBMIT_STATUS_SUCCESS = 'success';

	protected $submit_status = null;

	/*
	 * Include these fields in the form
	 */
	protected $include_fields = array();

	/*
	 * Exclude these fields from the form (only used if $include_fields is empty)
	 */
	protected $exclude_fields = array();
	
	protected function setup() {}

	/**
	 * Constructor
	 *
	 * @param int $id Primary key of the model. Ignored unless $this->model is set. 
	 * @return void
	 **/
	public function __construct($id = null) {
		
		$this->submit_text = I18n::get('Save changes');

		if ($this->model) {

			$this->object = ORM::factory($this->model, $id);

			$table_columns = $this->object->table_columns();
			foreach ($table_columns as $table_column) {
				if ($table_column['key'] == 'PRI') {
					$this->primary_key = $table_column['column_name'];
				}
				$this->add_field($table_column['column_name'], $table_column);
			}
			if ($this->include_fields) {
				foreach ($this->fields as $key => $field) {
					if (!in_array($key, $this->include_fields)) {
						unset($this->fields[$key]);
					}
				}
			} else if ($this->exclude_fields) {
				foreach ($this->fields as $key => $field) {
					if (in_array($key, $this->exclude_fields)) {
						unset($this->fields[$key]);
					}
				}
			}

			foreach ($this->fields as $key => $field) {
				$this->fields[$key]['value'] = $this->object->$key;
			}

		}

		$this->setup();

		foreach($this->fields as $key => $field) {
			$this->fields[$key] = $this->configure_field($field);
		}
		
	}
	
	/**
	 * Add a validation rule. These will be in addition to any defined in the model
	 *
	 * @param string $field The field name
	 * @param string $rule The rule type
	 * @param array The rule parameters
	 * @return void
	 **/
	public function rule($field, $rule, array $params = null) {
		$this->rules[] = array(
			'field'  => $field,
			'rule'   => $rule,
			'params' => $params
		);
	}
	
	/**
	 * Set the value of a field
	 *
	 * @param string $key The field name
	 * @param mixed $value The field value
	 * @return void
	 **/
	public function set_value($key, $value) {
		if ($this->model && $key == $this->primary_key) {
			$this->object = ORM::factory($this->model, $value);
		}
		if (isset($this->fields[$key])) {
			$this->fields[$key]['value'] = $value;
			if ($this->model && in_array($key, array_keys($this->object->table_columns()))) {
				if (isset($this->fields[$key]['data_type']) && $this->fields[$key]['data_type'] == 'set') {
					$this->object->$key = implode(',', $value);
				} else {
					$this->object->$key = $value;
				}
			}
		}
	}
	
	/**
	 * Set values all at once
	 *
	 * @param array $values key/value pairs
	 * @return void
	 **/
	public function set_values($values) {
		foreach ($this->fields as $key => $field) {
			if (isset($values[$key])) {
				$this->set_value($key, $values[$key]);
			}
		}
	}
	
	/**
	 * Add a field to the form
	 *
	 * @param string $name Name of the field
	 * @param array $spec Field specification
	 * @param string $position start/end/before/after
	 * @param string $relative The field that $position is relative to (for before/after)
	 * @return void
	 **/
	public function add_field($name, $spec = array(), $position = null, $relative = null) {
		if (!isset($spec['name'])) $spec['name'] = $name;

		if (!$position) { $position = 'end'; }

		$insertion_point = count($this->fields);
		if ($position == 'start') {
			$insertion_point = 0;
		} else if ($position == 'before' && $relative) {
			$insertion_point = array_search($relative, array_keys($this->fields));
		} else if ($position == 'after' && $relative) {
			$insertion_point = array_search($relative, array_keys($this->fields))+1;
		}

		$before = array_slice($this->fields, 0, $insertion_point, true);
		$after  = array_slice($this->fields, $insertion_point, null, true);

		$this->fields = array_merge($before, array($name => $spec), $after);

	}
	
	/**
	 * Remove a field from the form
	 *
	 * @param string $name Field name
	 * @return void
	 **/
	public function remove_field($name) {
		if (isset($this->fields[$name])) {
			unset($this->fields[$name]);
		}
	}
	
	/**
	 * Move a field
	 *
	 * @param string $name Name of the field
	 * @param string $position start/end/before/after
	 * @param string $relative The field that $position is relative to (for before/after)
	 * @return void
	 **/
	public function move_field($name, $position = null, $relative = null) {
		if (isset($this->fields[$name])) {
			$field = $this->fields[$name];
			unset($this->field[$name]);
			$this->add_field($name, $field, $position, $relative);
		}
	}

	/**
	 * Render the form
	 *
	 * @return string
	 **/
	public function render() {
		$bespoke_view = 'formmanager/' . strtolower(get_class($this));
		if (Kohana::find_file('views', $bespoke_view)) {
			$view = View::factory($bespoke_view);
		} else {
			$view = View::factory('formmanager/form');
		}
		
		$view->form = $this;
		return $view->render();
	}
	
	/**
	 * Validate the submitted values
	 *
	 * @param array $values Array of values
	 * @return bool
	 */
	public function submit($values) {

		// If we leave in a blank primary key, then ORM will not set it.
		if (isset($values[$this->primary_key]) && $values[$this->primary_key] == '') {
			$this->remove_field($this->primary_key);
		}

		$this->set_values($values);
		// Validate
		$object_valid = true; // assume so, in case there isn't even an object
		if ($this->object) {
			$object_validation = $this->object->validation();
			$object_valid = $object_validation->check();
			if (!$object_valid) {
				$errors = $object_validation->errors('forms/' . strtolower(get_class($this)));
				foreach($errors as $key => $value) {
					$this->fields[$key]['error'] = true;
					$this->fields[$key]['error_text'] = $value;
				}
			}
		}
		
		// Local validation - to figure out.
		$local_validation = Validation::factory($values);
		foreach ($this->rules as $rule) {
			$local_validation->rule($rule['field'], $rule['rule'], $rule['params']);
		}
		$local_valid = $local_validation->check();
		if (!$local_valid) {
			$errors = $local_validation->errors('forms/' . strtolower(get_class($this)));
			foreach($errors as $key => $value) {
				$this->fields[$key]['error'] = true;
				$this->fields[$key]['error_text'] = $value;
			}
		}
		
		$this->submit_status = ($object_valid && $local_valid) ? self::SUBMIT_STATUS_SUCCESS : self::SUBMIT_STATUS_FAIL;
		
		// Return success or not. By default this is just if the form was valid.
		// Saving is left up to the child form class.
		return $object_valid && $local_valid;
		
	}

	/**
	 * Get the submit status
	 *
	 * @return bool
	 */
	public function submit_status() {
		return $this->submit_status;
	}

	/**
	 * Save the associated object, return the object on success
	 * 
	 * @return mixed
	 */
	public function save_object() {
		if (!$this->object) return false;
		return $this->object->save();
	}


	/**
	 * Add parameters for display.
	 * If we have data in the comment, use that
	 *
	 * @param array $field
	 * @return array $field
	 */
	protected function configure_field($field) {
		if (!isset($field['name'])) $field['name'] = $field['column_name'];
		if (!isset($field['value'])) $field['value'] = '';

		if (!isset($field['label']) || !$field['label']) $field['label'] = ucwords(str_replace('_', ' ', $field['name']));
		$field = $this->set_field_value($field, 'error', false);
		$field = $this->set_field_value($field, 'error_text','');

		$field['attributes'] = array();

		if (isset($field['comment'])) {
			$comment = explode("\n", $field['comment']);
			foreach($comment as $line) {
				if (false !== strpos($line, ':')) {
					list($key, $value) = preg_split('/ *: */', $line, 2);
					$field = $this->set_field_value($field, trim($key), trim($value));
				}
			}
		}

		$field = $this->set_field_value($field, 'help', '');
		
		if (isset($field['options'])) {
			$options = array();
			if ($field['is_nullable']) $options[''] = '';
			foreach ($field['options'] as $option) {
				$options[$option] = $option;
			}
			$field['options'] = $options;
		}

		if ($field['name'] == $this->primary_key) {
			$field = $this->set_field_value($field, 'display_as', 'hidden');
		}

		elseif (isset($field['data_type']) && $field['data_type'] == 'enum') {
			$field = $this->set_field_value($field, 'display_as', 'select');
		}

		elseif (isset($field['data_type']) && $field['data_type'] == 'set') {
			$field = $this->set_field_value($field, 'display_as', 'checkboxes');
			$field['value'] = explode(',', $field['value']);
		}

		elseif (isset($field['data_type']) && $field['data_type'] == 'tinyint' && $field['display'] == '1') {
			$field = $this->set_field_value($field, 'display_as', 'bool');
		}

		elseif (isset($field['type']) && preg_match('/^(.*int|decimal|float)$/', $field['type'])) {
			$field = $this->set_field_value($field, 'display_as', 'text');
			$field = $this->set_field_value($field, 'input_type', 'number');
		}
		
		else {
			$field = $this->set_field_value($field, 'display_as', 'text');
			$field = $this->set_field_value($field, 'input_type', 'text');
			if (isset($field['character_maximum_length'])) {
				$field['attributes']['maxlength'] = $field['character_maximum_length'];
			}
		}

		$field['required'] = $field['attributes']['required'] = isset($field['is_nullable']) && !$field['is_nullable'];



		return $field;

	}

	/**
	 * Set a value into the $field array, not overwriting a previous value by default.
	 *
	 * @param array $field
	 * @param string $key
	 * @param mixed $value
	 * @param bool $override
	 * @return type array
	 */
	protected function set_field_value($field, $key, $value, $override=false) {
		if (!isset($field[$key]) or $override) $field[$key] = $value;
		return $field;
	}



	
	
}