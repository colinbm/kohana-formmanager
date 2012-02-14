<form action="<?php echo $form->action?>" method="<?php echo $form->method?>" enctype="multipart/form-data" class="form-horizontal">
	
	<?php if ($form->fieldsets): ?>
		<?php foreach ($form->fields as $field): if ($field['display_as'] == 'hidden'): ?>
			<?php echo View::factory('formmanager/html/hidden', array('field' => $field))->render(); ?>
		<?php endif; endforeach; ?>
		<?php echo View::factory('formmanager/html/fieldsets', array('fieldsets' => $form->fieldsets))->render(); ?>
	<?php else: ?>
		<?php echo View::factory('formmanager/html/fields', array('fields' => $form->fields))->render(); ?>
	<?php endif; ?>

	<div class="form-actions">
		<input type="submit" class="btn btn-primary" value="<?php echo $form->submit_text; ?>" />
    </div>

</form>