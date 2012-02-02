<?php foreach ($field['options'] as $option): ?>
		<label class="checkbox">
			<?php echo Form::checkbox($field['field_name'].'[]', $option, in_array($option, $field['value'])); ?>
			<?php echo $option; ?>
		</label>
<?php endforeach; ?>

