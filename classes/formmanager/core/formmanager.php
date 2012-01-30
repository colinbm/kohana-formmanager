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
	protected $expected_input;

	public $submit_text;

	const SUBMIT_STATUS_FAIL = 'fail';
	const SUBMIT_STATUS_SUCCESS = 'success';

	protected $submit_status = null;

	protected $container;

	public $custom_view;

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
	public function __construct($id = null, $parent_container = null) {
		
		$this->submit_text = I18n::get('Save changes');

		$form_name = preg_replace('/^form_/', '', strtolower(get_class($this)));

		$this->custom_view = 'formmanager/' . $form_name;

		if ($parent_container) {
			$this->container = $parent_container . '[' . $form_name . ']';
		} else {
			$this->container = $form_name;
		}

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

			if ($column = $this->object->created_column()) {
				$this->remove_field($column['column']);
			}

			if ($column = $this->object->updated_column()) {
				$this->remove_field($column['column']);
			}

		}

		$this->setup();
		
		// Relations.
		
		if (isset($this->object) && $belongs_to = $this->object->belongs_to()) {
			foreach ($belongs_to as $alias => $config) {
				$model = isset($config['model']) ? $config['model'] : $alias;
				$foreign_key = isset($config['foreign_key']) ? $config['foreign_key'] : $model . '_id';

				if (isset($this->fields[$foreign_key])) {
					$model = ORM::factory($model);
					$this->fields[$foreign_key]['options'] = array();
					foreach($model->find_all() as $row) {
						$this->fields[$foreign_key]['options'][$row->{$model->primary_key()}] = isset($this->fields[$foreign_key]['foreign_name']) ? $row->{$this->fields[$foreign_key]['foreign_name']} : $row->{$model->primary_key()};
					}
					$this->set_field_value($foreign_key, 'display_as', 'select');
					$this->set_field_value($foreign_key, 'dont_reindex_options', true);
					#$this->fields[$foreign_key]['dont_reindex_options'] = true;
				}
			}
		}

		foreach($this->fields as $key => $field) {
			$this->configure_field($key);
		}

		if ($this->method == 'post') {
			$this->expected_input = $_POST;
		} elseif ($this->method == 'get') {
			$this->expected_input = $_GET;
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
					if ($value === '' && $this->fields[$key]['is_nullable']) $value = null;
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
	 * Remove a field from display in the form
	 * but don't stop it being written to the
	 * model. It'll need set elsewhere if it's
	 * not nullable.
	 *
	 * @param string $name Field name
	 */
	public function disable_field($name) {
		$this->set_field_value($name, 'disabled', true, true);
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
		if (Kohana::find_file('views', $this->custom_view)) {
			$view = View::factory($this->custom_view);
		} else {
			$view = View::factory('formmanager/form');
		}

		$view->form = $this;
	
		foreach($view->form->fields as $key => $field) {
			if ($field['disabled']) {
				unset($view->form->fields[$key]);
			}
		}

		return $view->render();
	}
	
	/**
	 * Validate the submitted values
	 *
	 * @param array $values Array of values
	 * @return bool
	 */
	public function submit() {
		
		$values = $this->get_input();
		if (!$values) {
			return false;
		}

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
	 * Check to see if this form is submitted
	 *
	 * @return bool
	 */
	public function is_submitted() {
		return isset($this->expected_input[$this->container]);
	}

	/**
	 * Return the submitted data, or false if not submitted
	 *
	 * @return mixed
	 */
	public function get_input() {
		if ($this->is_submitted()) {
			$input = $this->expected_input[$this->container];
			foreach($this->fields as $key => $field) {
				if ($field['display_as'] == 'bool' && !isset($input[$key])) {
					$input[$key] = 0;
				}
			}
			return $input;
		}
		return array();
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
		$this->set_field_value($field, 'disabled', false);

		$this->set_field_value($field, 'name', $field);
		$this->set_field_value($field, 'field_name', $this->container . '[' . $this->fields[$field]['name'] . ']');
		$this->set_field_value($field, 'field_id', trim(str_replace(array('[', ']'), '_', $this->fields[$field]['field_name']), '_'));

		$this->set_field_value($field, 'value', '');

		#if (!isset($this->fields[$field]['label']) || !$this->fields[$field]['label']) $this->fields[$field]['label'] = ucwords(str_replace('_', ' ', $this->fields[$field]['name']));
		$this->set_field_value($field, 'label', ucwords(str_replace('_', ' ', $this->fields[$field]['name'])));

		$this->set_field_value($field, 'error', false);
		$this->set_field_value($field, 'error_text','');

		$attributes = array('id' => $this->fields[$field]['field_id']);

		if (isset($this->fields[$field]['comment'])) {
			$comment = explode("\n", $this->fields[$field]['comment']);
			foreach($comment as $line) {
				if (false !== strpos($line, ':')) {
					list($field, $value) = preg_split('/ *: */', $line, 2);
					$this->set_field_value($field, trim($field), trim($value));
				}
			}
		}

		$this->set_field_value($field, 'help', '');
		
		if (isset($this->fields[$field]['options'])) {
			if (isset($this->fields[$field]['dont_reindex_options'])) {
				if ($this->fields[$field]['is_nullable']) $this->fields[$field]['options'] = array('' => '') + $this->fields[$field]['options'];
			} else {
				$options = array();
				if ($this->fields[$field]['is_nullable']) $options[''] = '';
				foreach ($this->fields[$field]['options'] as $option) {
					$options[$option] = $option;
				}
				$this->fields[$field]['options'] = $options;
			}
		}

		if ($this->fields[$field]['name'] == $this->primary_key) {
			$this->set_field_value($field, 'display_as', 'hidden');
		}

		elseif (isset($this->fields[$field]['data_type']) && $this->fields[$field]['data_type'] == 'enum') {
			$this->set_field_value($field, 'display_as', 'select');
		}

		elseif (isset($this->fields[$field]['data_type']) && $this->fields[$field]['data_type'] == 'set') {
			$this->set_field_value($field, 'display_as', 'checkboxes');
			$this->fields[$field]['value'] = explode(',', $this->fields[$field]['value']);
		}

		elseif (isset($this->fields[$field]['data_type']) && $this->fields[$field]['data_type'] == 'tinyint' && $this->fields[$field]['display'] == '1') {
			$this->set_field_value($field, 'display_as', 'bool');
		}

		elseif (isset($this->fields[$field]['type']) && preg_match('/^(.*int|decimal|float)$/', $this->fields[$field]['type'])) {
			$this->set_field_value($field, 'display_as', 'text');
			$this->set_field_value($field, 'input_type', 'number');
		}

		elseif (isset($this->fields[$field]['data_type']) && preg_match('/.*text$/', $this->fields[$field]['data_type'])) {
			$this->set_field_value($field, 'display_as', 'textarea');
		}
		
		else {
			$this->set_field_value($field, 'display_as', 'text');
			$this->set_field_value($field, 'input_type', 'text');
			if (isset($this->fields[$field]['character_maximum_length'])) {
				$attributes['maxlength'] = $this->fields[$field]['character_maximum_length'];
			}
		}

		$required = isset($this->fields[$field]['is_nullable']) && !$this->fields[$field]['is_nullable'];
		$this->set_field_value($field, 'required', $required);
		# $attributes['required'] = $required; // this triggers browser behaviour, which doesn't seem quite ready yet.

		$this->set_field_value($field, 'attributes', $attributes);



	}

	/**
	 * Set a value into the $field array, not overwriting a previous value by default.
	 *
	 * @param array $field
	 * @param string $key
	 * @param mixed $value
	 * @param bool $override
	 */
	protected function set_field_value($field, $key, $value, $override=false) {
		if (!isset($this->fields[$field])) return false;
		if (!isset($this->fields[$field][$key]) or $override) $this->fields[$field][$key] = $value;
	}



	
	
}