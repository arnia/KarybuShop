<?php foreach($this->getParameters() as $key => $value) :?>
	<?php if($value) :?>
	<input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>"  />
	<?php endif?>
<?php endforeach?>

<?php if($this->showSubmitButton) :?>
	<input name="adyensubmit" type="submit" value="Submit" id="adyensubmit" />
<?php endif?>
