Kohana FormManager
==================

This module allows you to create reusable forms that are self contained. You can tie it to a ORM model, or roll your own.

Examples
--------

Once you've created your form class (see below), you can display it with:

```php
<?php
$form = new Form_Login();
echo $form->render();
```

You'll likely want to pass the form into the view where you can call ->render() or assign the result of ->render() to a view variable.

### Roll your own

```php
<?php
class Form_Login extends FormManager {
	
	protected function setup() {
		$this->add_field('username');
		$this->add_field('password');
	}
	
	public function submit($values) {
		$auth = Auth::instance();
		$success = $auth->login($values['username'], $values['password']);
		if (!$success) {
			$this->values['username']['error'] = true;
			$this->values['username']['error_text'] = 'Your username or password were not recognised.';
		}
	}
	
}
```

### Associated with a model

The basics of what you require to create a form for a model is

```php
<?php
class Form_Profile extends FormManager {
	protected $model = 'user';
}
```

However you're likely to want to tweak the form a bit, and do something with the data that's submitted.

```php
<?php
class Form_Profile extends FormManager {

	protected $model = 'user';

	// These fields are irrelevant in the form
	protected $exclude_fields = array('logins', 'last_login');

	protected function setup() {
		$this->add_field('password_confirm', array('display_as' => 'password'), 'after', 'password');
		$this->fields['password']['display_as'] = 'password';
		$this->rule('password_confirm', 'matches', array(':validation', 'password', ':field'));
		$this->set_value('password', '');
		$this->set_value('password_confirm', '');

	}

	public function submit($values) {

		// Ensure current user has permissions to edit
		$auth = Auth::instance();
		$user = $auth->get_user();
		if ($user->id != $this->object->id) {
			return false;
		}

		$success = parent::submit($values);

		if ($success) {
			$this->object->save();
		}

		return $success;

	}

}
```

Note; the FormManager class does not automatically call ->save() during ->submit().