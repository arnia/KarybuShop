<?php foreach($this->getParameters() as $key => $value) :?>
	<?php if($value) :?>
	<input type="hidden" name="<?php echo strtoupper($key); ?>" value="<?php echo htmlspecialchars($value); ?>"  />
	<?php endif?>
<?php endforeach?>
<input type="hidden" name="SHASIGN" value="<?php echo $this->getShaSign()?>" />

<?php if($this->showSubmitButton) :?>
	<input name="ogonesubmit" type="submit" value="Submit" id="ogonesubmit" />
<?php endif?>
