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
	
	// Initialize Bootstrap Table for HQ review
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
		
		// Handle approve button clicks
		$(document).on('click', '.approve-invoice', function() {
			var invoiceId = $(this).data('invoice-id');
			if (confirm('Are you sure you want to approve this invoice? Stock will be deducted from HQ.')) {
				$.ajax({
					url: '<?php echo site_url("invoice/approve"); ?>/' + invoiceId,
					type: 'POST',
					dataType: 'json',
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('Error: ' + response.message);
						}
					},
					error: function() {
						alert('Error approving invoice. Please try again.');
					}
				});
			}
		});
		
		// Handle decline button clicks
		$(document).on('click', '.decline-invoice', function() {
			var invoiceId = $(this).data('invoice-id');
			var reason = prompt('Please provide a reason for declining this invoice:');
			if (reason !== null) {
				$.ajax({
					url: '<?php echo site_url("invoice/decline"); ?>/' + invoiceId,
					type: 'POST',
					data: { reason: reason },
					dataType: 'json',
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('Error: ' + response.message);
						}
					},
					error: function() {
						alert('Error declining invoice. Please try again.');
					}
				});
			}
		});
});
</script>

<div class="panel panel-piluku">
	<div class="panel-heading">
		<h3 class="panel-title">
			<span class="glyphicon glyphicon-list-alt">&nbsp</span><?php echo $this->lang->line('invoice_hq_review'); ?>
		</h3>
	</div>
	<div class="panel-body">
		<p class="text-info">
			<i class="glyphicon glyphicon-info-sign"></i>
			<?php echo $this->lang->line('invoice_hq_review_description'); ?>
		</p>
		
		<div id="table_holder">
			<table id="table"></table>
		</div>
	</div>
</div>

<div id="feedback_bar"></div>

<?php $this->load->view("partial/footer"); ?>
