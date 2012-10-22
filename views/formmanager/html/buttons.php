<div class="form-actions">
<?php
foreach($buttons as $button) {
	echo Form::input($button['name'], $button['text'], $button['attributes']), "\n";
}
?>
</div>