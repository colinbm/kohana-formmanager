<form action="<?php echo $form->action?>" method="<?php echo $form->method?>" class="form-horizontal">

	<?php if ($form->fieldsets): ?>
		<?php echo View::factory('formmanager/html/fieldsets', array('fieldsets' => $form->fieldsets))->render(); ?>
	<?php else: ?>
		<?php echo View::factory('formmanager/html/fields', array('fields' => $form->fields))->render(); ?>
	<?php endif; ?>

	<div class="actions">
		<input type="submit" class="btn btn-primary" value="<?php echo $form->submit_text; ?>" />
    </div>

</form>