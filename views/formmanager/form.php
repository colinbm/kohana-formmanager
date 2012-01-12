<form action="<?php echo $form->action?>" method="<?php echo $form->method?>">

	<?php echo View::factory('formmanager/html/fields', array('fields' => $form->fields))->render(); ?>

	<div class="actions">
		<input type="submit" class="btn primary" value="<?php echo $form->submit_text; ?>" />
    </div>

</form>