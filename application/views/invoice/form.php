<script type="text/javascript">
$(document).ready(function()
{
	var invoice_items = [];
	var item_counter = 0;

	// Load items for autocomplete
	$.ajax({
		url: "<?php echo site_url('invoice/get_items'); ?>",
		type: "GET",
		dataType: "json",
		success: function(data) {
			var items = [];
			$.each(data, function(index, item) {
				items.push({
					label: item.name + ' (' + item.category + ')',
					value: item.item_id,
					unit_price: item.unit_price,
					stock_quantity: item.stock_quantity
				});
			});
			
			$("#item_search").autocomplete({
				source: items,
				select: function(event, ui) {
					add_item_to_invoice(ui.item);
					$(this).val('');
					return false;
				}
			});
		},
		error: function(xhr, status, error) {
			// Initialize autocomplete with empty source if AJAX fails
			$("#item_search").autocomplete({
				source: [],
				select: function(event, ui) {
					add_item_to_invoice(ui.item);
					$(this).val('');
					return false;
				}
			});
		}
	});

	function add_item_to_invoice(item) {
		// Check if item already exists
		var existing_item = invoice_items.find(function(i) { return i.item_id === item.value; });
		
		if (existing_item) {
			existing_item.quantity += 1;
			existing_item.total_price = parseFloat(existing_item.unit_price) * existing_item.quantity; // Fix: Convert to number and recalculate
			update_item_row(existing_item);
		} else {
			var new_item = {
				id: item_counter++,
				item_id: item.value,
				item_name: item.label,
				unit_price: parseFloat(item.unit_price), // Fix: Convert to number
				quantity: 1,
				total_price: parseFloat(item.unit_price), // Fix: Convert to number
				stock_quantity: parseFloat(item.stock_quantity) // Fix: Convert to number
			};
			
			invoice_items.push(new_item);
			add_item_row(new_item);
		}
		
		update_totals();
	}

	function add_item_row(item) {
		// Handle stock quantity validation - if stock is 0, allow any quantity (for backorders)
		var minQuantity = item.stock_quantity > 0 ? 1 : 0;
		var maxQuantity = item.stock_quantity > 0 ? item.stock_quantity : '';
		var stockDisplay = item.stock_quantity > 0 ? item.stock_quantity + ' available' : 'Out of stock';
		var stockClass = item.stock_quantity > 0 ? 'text-info' : 'text-warning';
		
		var row = '<tr id="item_' + item.id + '">' +
			'<td>' + item.item_name + '</td>' +
			'<td><input type="number" class="form-control quantity-input" value="' + item.quantity + '" min="' + minQuantity + '"' + (maxQuantity ? ' max="' + maxQuantity + '"' : '') + '></td>' +
			'<td class="unit-price">' + parseFloat(item.unit_price).toFixed(2) + '</td>' +
			'<td class="total-price">' + parseFloat(item.total_price).toFixed(2) + '</td>' +
			'<td><span class="' + stockClass + '">' + stockDisplay + '</span></td>' +
			'<td><button type="button" class="btn btn-danger btn-sm remove-item">Remove</button></td>' +
		'</tr>';
		
		$('#invoice_items tbody').append(row);
	}

	function update_item_row(item) {
		var row = $('#item_' + item.id);
		var quantityInput = row.find('.quantity-input');
		
		// Update validation attributes based on stock
		var minQuantity = item.stock_quantity > 0 ? 1 : 0;
		var maxQuantity = item.stock_quantity > 0 ? item.stock_quantity : '';
		
		quantityInput.attr('min', minQuantity);
		if (maxQuantity) {
			quantityInput.attr('max', maxQuantity);
		} else {
			quantityInput.removeAttr('max');
		}
		
		quantityInput.val(item.quantity);
		row.find('.total-price').text(parseFloat(item.total_price).toFixed(2));
		
		// Update stock display
		var stockDisplay = item.stock_quantity > 0 ? item.stock_quantity + ' available' : 'Out of stock';
		var stockClass = item.stock_quantity > 0 ? 'text-info' : 'text-warning';
		var stockSpan = row.find('td:nth-child(5) span');
		stockSpan.removeClass('text-info text-warning').addClass(stockClass).text(stockDisplay);
	}

	function update_totals() {
		var total = 0;
		$.each(invoice_items, function(index, item) {
			total += parseFloat(item.total_price) || 0; // Fix: Ensure we're adding numbers
		});
		$('#total_amount').text(total.toFixed(2));
	}

	function remove_item_from_invoice(item_id) {
		// Remove from array
		invoice_items = invoice_items.filter(function(item) { return item.id != item_id; });
		
		// Remove from DOM
		$('#item_' + item_id).remove();
		
		// Update totals
		update_totals();
	}

	// Handle quantity changes
	$(document).on('change', '.quantity-input', function() {
		var row = $(this).closest('tr');
		var item_id = row.attr('id').replace('item_', '');
		var newQuantity = parseInt($(this).val());
		var item = invoice_items.find(function(i) { return i.id == item_id; });
		
		if (item) {
			// Validate quantity based on stock availability
			if (item.stock_quantity > 0 && newQuantity > item.stock_quantity) {
				$(this).val(item.quantity);
				return;
			}
			
			if (newQuantity < 0) {
				$(this).val(item.quantity);
				return;
			}
			
			if (newQuantity === 0) {
				// Remove item if quantity is 0
				remove_item_from_invoice(item_id);
				return;
			}
			
			item.quantity = newQuantity;
			item.total_price = parseFloat(item.unit_price) * item.quantity;
			update_item_row(item);
			update_totals();
		}
	});

	// Handle item removal
	$(document).on('click', '.remove-item', function() {
		var row = $(this).closest('tr');
		var item_id = row.attr('id').replace('item_', '');
		remove_item_from_invoice(item_id);
	});

	// Handle form submission
	$('#invoice_form').on('submit', function(e) {
		e.preventDefault();
		
		if (invoice_items.length === 0) {
			alert('Please add at least one item to the invoice.');
			return;
		}
		
		// Transform items data to match model expectations
		var transformedItems = invoice_items.map(function(item) {
			return {
				item_id: item.item_id,
				quantity: item.quantity,
				unit_price: item.unit_price,
				total_price: item.total_price,
				stock_quantity: item.stock_quantity
			};
		});
		
		// Add items to form data
		var itemsJson = JSON.stringify(transformedItems);
		
		// Check if hidden input exists
		var itemsInput = $('#items_input');
		
		// If hidden input doesn't exist, create it
		if (itemsInput.length === 0) {
			$('#invoice_form').append('<input type="hidden" name="items" id="items_input" value="">');
			itemsInput = $('#items_input');
		}
		
		// Set the value
		itemsInput.val(itemsJson);
		
		// Small delay to ensure the hidden input is set
		var form = $(this);
		setTimeout(function() {
			// Submit form using AJAX
			var formData = form.serialize();
		
			$.ajax({
				url: form.attr('action'),
				type: 'POST',
				data: formData,
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						// Close modal and refresh page
						if (typeof BootstrapDialog !== 'undefined') {
							BootstrapDialog.closeAll();
						}
						window.location.reload();
					} else {
						alert('Error creating invoice: ' + (response.message || 'Unknown error'));
					}
				},
				error: function(xhr, status, error) {
					alert('Error creating invoice. Please try again.');
				}
			});
		}, 100); // 100ms delay
	});
});
</script>

<?php echo form_open('invoice/save', array('id' => 'invoice_form', 'class' => 'form-horizontal')); ?>
		<?php echo form_hidden('items', '', 'id="items_input"'); ?>
		
		<div class="form-group">
			<label for="invoice_date" class="col-sm-3 control-label"><?php echo $this->lang->line('invoice_date'); ?></label>
			<div class="col-sm-9">
				<?php echo form_input(array(
					'name' => 'invoice_date',
					'id' => 'invoice_date',
					'class' => 'form-control',
					'value' => date('Y-m-d'),
					'required' => 'required'
				)); ?>
			</div>
		</div>

		<div class="form-group">
			<label for="branch_location_id" class="col-sm-3 control-label"><?php echo $this->lang->line('invoice_branch'); ?></label>
			<div class="col-sm-9">
				<?php 
				// Show branch name as read-only, with ID as hidden input
				echo form_input(array(
					'name' => 'branch_display',
					'id' => 'branch_display',
					'class' => 'form-control',
					'value' => $current_location_name,
					'readonly' => 'readonly'
				));
				?>
				<input type="hidden" name="branch_location_id" value="<?php echo $current_location; ?>" />
			</div>
		</div>

		<div class="form-group">
			<label for="item_search" class="col-sm-3 control-label"><?php echo $this->lang->line('invoice_add_item'); ?></label>
			<div class="col-sm-9">
				<?php echo form_input(array(
					'name' => 'item_search',
					'id' => 'item_search',
					'class' => 'form-control',
					'placeholder' => 'Search and select items...'
				)); ?>
			</div>
		</div>

		<div class="form-group">
			<div class="col-sm-12">
				<table id="invoice_items" class="table table-striped table-bordered">
					<thead>
						<tr>
							<th><?php echo $this->lang->line('invoice_item'); ?></th>
							<th><?php echo $this->lang->line('invoice_quantity'); ?></th>
							<th><?php echo $this->lang->line('invoice_unit_price'); ?></th>
							<th><?php echo $this->lang->line('invoice_total_price'); ?></th>
							<th><?php echo $this->lang->line('invoice_stock'); ?></th>
							<th><?php echo $this->lang->line('invoice_actions'); ?></th>
						</tr>
					</thead>
					<tbody>
						<!-- Items will be added dynamically -->
					</tbody>
				</table>
			</div>
		</div>

		<div class="form-group">
			<div class="col-sm-9 col-sm-offset-3">
				<strong>Total Amount:<span id="total_amount">0.00</span></strong>
			</div>
		</div>

		<div class="form-group">
			<label for="notes" class="col-sm-3 control-label"><?php echo $this->lang->line('invoice_notes'); ?></label>
			<div class="col-sm-9">
				<?php echo form_textarea(array(
					'name' => 'notes',
					'id' => 'notes',
					'class' => 'form-control',
					'rows' => 3,
					'placeholder' => 'Additional notes for this invoice...'
				)); ?>
			</div>
		</div>

		<div class="form-group">
			<div class="col-sm-9 col-sm-offset-3">
				<?php echo form_submit(array(
					'name' => 'submit',
					'id' => 'submit',
					'value' => $this->lang->line('invoice_create_invoice'),
					'class' => 'btn btn-primary'
				)); ?>
				<?php echo anchor('invoice', $this->lang->line('common_cancel'), array('class' => 'btn btn-default')); ?>
			</div>
		</div>

		<?php echo form_close(); ?>
