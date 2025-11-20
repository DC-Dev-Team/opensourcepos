<?php $this->load->view("partial/header"); ?>

<style>
.items-list {
	max-width: 300px;
}
.item-row {
	margin-bottom: 5px;
	padding: 3px 0;
	border-bottom: 1px solid #eee;
}
.item-row:last-child {
	border-bottom: none;
}
.badge {
	font-size: 0.8em;
}
</style>

<script type="text/javascript">
// Formatter function for items column
function itemsFormatter(value, row, index) {
	if (!value || value.length === 0) {
		return '<span class="text-muted">No items</span>';
	}
	
	var itemsHtml = '<div class="items-list">';
	for (var i = 0; i < value.length; i++) {
		var item = value[i];
		itemsHtml += '<div class="item-row">';
		itemsHtml += '<strong>' + item.item_name + '</strong>';
		itemsHtml += ' <span class="text-muted">(' + item.category + ')</span>';
		itemsHtml += '</div>';
	}
	itemsHtml += '</div>';
	
	return itemsHtml;
}

// Formatter function for quantity column
function quantityFormatter(value, row, index) {
	if (!value || value === 0) {
		return '<span class="text-muted">0</span>';
	}
	
	return '<span class="badge badge-primary">' + value + '</span>';
}

$(document).ready(function()
{
	<?php $this->load->view('partial/bootstrap_tables_locale'); ?>
	
	// Initialize Bootstrap Table
	$('#table')
		.addClass("table-striped")
		.addClass("table-bordered")
		.bootstrapTable({
			columns: <?php echo $table_headers; ?>,
			stickyHeader: true,
			stickyHeaderOffsetLeft: $('#table').offset().left + 'px',
			stickyHeaderOffsetRight: $('#table').offset().right + 'px',
			pageSize: <?php echo $this->config->item('lines_per_page'); ?>,
			sortable: true,
			showExport: true,
			exportDataType: 'all',
			exportTypes: ['json', 'xml', 'csv', 'txt', 'sql', 'excel', 'pdf'],
			pagination: true,
			showColumns: true,
			data: <?php echo json_encode($invoices); ?>,
			iconSize: 'sm',
			paginationVAlign: 'bottom',
			escape: false,
			search: true
		});
		
		// Initialize modal dialogs after table is ready
		dialog_support.init("a.modal-dlg");
});
</script>

<div id="title_bar" class="btn-toolbar">
	<a class='btn btn-info btn-sm pull-right modal-dlg' data-btn-submit='<?php echo $this->lang->line('common_submit') ?>' data-href='<?php echo site_url("invoice/create"); ?>' title='<?php echo $this->lang->line('invoice_create_invoice'); ?>'>
		<span class="glyphicon glyphicon-plus">&nbsp</span><?php echo $this->lang->line('invoice_create_invoice'); ?>
	</a>
</div>

<div id="toolbar">
	<?php 
	// Check if user has invoice_review permission
	$logged_in_employee_info = $this->Employee->get_logged_in_employee_info();
	if ($this->Employee->has_grant('invoice_review', $logged_in_employee_info->person_id)): 
	?>
	<a href="<?php echo site_url('invoice/review'); ?>" class="btn btn-primary">
		<span class="glyphicon glyphicon-eye-open">&nbsp</span><?php echo $this->lang->line('invoice_hq_review'); ?>
	</a>
	<?php endif; ?>
</div>

<div id="table_holder">
	<table id="table"></table>
</div>

<div id="feedback_bar"></div>

<?php $this->load->view("partial/footer"); ?>
