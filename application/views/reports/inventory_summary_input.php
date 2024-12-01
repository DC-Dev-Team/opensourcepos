<?php $this->load->view("partial/header"); ?>

<script type="text/javascript">
	dialog_support.init("a.modal-dlg");
</script>


<div id="page_title"><?php echo $this->lang->line('reports_report_input'); ?></div>

<?php
if(isset($error))
{
	echo "<div class='alert alert-dismissible alert-danger'>".$error."</div>";
}
?>

<?php echo form_open('#', array('id'=>'item_form', 'enctype'=>'multipart/form-data', 'class'=>'form-horizontal')); ?>

	<div class="form-group form-group-sm">
		<?php echo form_label($this->lang->line('reports_stock_location'), 'reports_stock_location_label', array('class'=>'required control-label col-xs-2')); ?>
		<?php				
		// Check if there is only two stock location containing all as the first key
		if (
			count($stock_locations) == 2 
			&& array_key_exists('all', $stock_locations)
			): 	
			// Remove the 'all' option from the array
			unset($stock_locations['all']);
			// Get the single stock location key and value
			$location_key = key($stock_locations);
			$location_value = reset($stock_locations);
		?>
			<!-- Show the single stock location as a non-editable field -->
			<input type="hidden" name="stock_location" value="<?php echo $location_key; ?>" id="location_id" />
			<p class="form-control-static"><?php echo $location_value; ?></p>
			<?php else: ?>
			<div id='report_stock_location' class="col-xs-3">
				<!-- Show the dropdown if there are multiple stock locations -->
				<?php echo form_dropdown('stock_location', $stock_locations, 'all', array('id'=>'location_id', 'class'=>'form-control')); ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="form-group form-group-sm">
		<?php echo form_label($this->lang->line('reports_item_count'), 'reports_item_count_label', array('class'=>'required control-label col-xs-2')); ?>
		<div id='report_item_count' class="col-xs-3">
			<?php echo form_dropdown('item_count',$item_count,'all','id="item_count" class="form-control"'); ?>
		</div>
	</div>

	<?php
	echo form_button(array(
		'name'=>'generate_report',
		'id'=>'generate_report',
		'content'=>$this->lang->line('common_submit'),
		'class'=>'btn btn-primary btn-sm')
	);
	?>
<?php echo form_close(); ?>

<?php $this->load->view("partial/footer"); ?>

<script type="text/javascript">
$(document).ready(function()
{
	$("#generate_report").click(function()
	{
		window.location = [window.location, $("#location_id").val(), $("#item_count").val()].join("/");
	});
});
</script>