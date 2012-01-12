<ul class="inputs-list">
<?php foreach ($field['options'] as $option): ?>
	<li>
		<label>
			<?php echo Form::checkbox($field['name'].'[]', $option, in_array($option, $field['value'])); ?>
			<span><?php echo $option; ?></span>
		</label>
	</li>
<?php endforeach; ?>
</ul>
