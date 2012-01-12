<div class="clearfix <?php if ($field['error']) echo 'error'; ?>">
	<?php echo View::factory('formmanager/html/label', array('field' => $field))->render(); ?>
	<div class="input">