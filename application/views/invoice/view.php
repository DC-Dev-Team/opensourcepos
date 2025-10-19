<?php $this->load->view("partial/header"); ?>

<div class="panel panel-piluku">
	<div class="panel-heading">
		<h3 class="panel-title">
			<span class="glyphicon glyphicon-file">&nbsp</span><?php echo $this->lang->line('invoice_view_invoice'); ?> #<?php echo $invoice->invoice_id; ?>
		</h3>
	</div>
	<div class="panel-body">
		<div class="row">
			<div class="col-sm-6">
				<table class="table table-striped">
					<tr>
						<td><strong><?php echo $this->lang->line('invoice_invoice_id'); ?>:</strong></td>
						<td>#<?php echo $invoice->invoice_id; ?></td>
					</tr>
					<tr>
						<td><strong><?php echo $this->lang->line('invoice_date'); ?>:</strong></td>
						<td><?php echo date('Y-m-d', strtotime($invoice->invoice_date)); ?></td>
					</tr>
					<tr>
						<td><strong><?php echo $this->lang->line('invoice_branch'); ?>:</strong></td>
						<td><?php echo $invoice->branch_name; ?></td>
					</tr>
					<tr>
						<td><strong><?php echo $this->lang->line('invoice_status'); ?>:</strong></td>
						<td>
							<?php 
							$badge_class = $invoice->status === 'pending' ? 'warning' : ($invoice->status === 'fulfilled' ? 'success' : 'danger');
							?>
							<span class="label label-<?php echo $badge_class; ?>">
								<?php echo ucfirst($invoice->status); ?>
							</span>
						</td>
					</tr>
					<tr>
						<td><strong><?php echo $this->lang->line('invoice_created_by'); ?>:</strong></td>
						<td><?php echo $invoice->created_by_name; ?></td>
					</tr>
					<tr>
						<td><strong><?php echo $this->lang->line('invoice_created_at'); ?>:</strong></td>
						<td><?php echo date('Y-m-d H:i:s', strtotime($invoice->created_at)); ?></td>
					</tr>
					<?php if ($invoice->fulfilled_at): ?>
					<tr>
						<td><strong><?php echo $this->lang->line('invoice_fulfilled_at'); ?>:</strong></td>
						<td><?php echo date('Y-m-d H:i:s', strtotime($invoice->fulfilled_at)); ?></td>
					</tr>
					<?php endif; ?>
				</table>
			</div>
			<div class="col-sm-6">
				<?php if ($invoice->notes): ?>
				<div class="panel panel-default">
					<div class="panel-heading">
						<h4 class="panel-title"><?php echo $this->lang->line('invoice_notes'); ?></h4>
					</div>
					<div class="panel-body">
						<?php echo nl2br($invoice->notes); ?>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="row">
			<div class="col-sm-12">
				<h4><?php echo $this->lang->line('invoice_items'); ?></h4>
				<table class="table table-striped table-bordered">
					<thead>
						<tr>
							<th><?php echo $this->lang->line('invoice_item_number'); ?></th>
							<th><?php echo $this->lang->line('invoice_item_name'); ?></th>
							<th><?php echo $this->lang->line('invoice_quantity'); ?></th>
							<th><?php echo $this->lang->line('invoice_unit_price'); ?></th>
							<th><?php echo $this->lang->line('invoice_total_price'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($invoice->items as $item): ?>
						<tr>
							<td><?php echo $item['item_number']; ?></td>
							<td><?php echo $item['item_name']; ?></td>
							<td><?php echo $item['quantity']; ?></td>
							<td><?php echo number_format($item['unit_price'], 2); ?></td>
							<td><?php echo number_format($item['total_price'], 2); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="4" class="text-right"><?php echo $this->lang->line('invoice_total_amount'); ?>:</th>
							<th><?php echo number_format($invoice->total_amount, 2); ?></th>
						</tr>
					</tfoot>
				</table>
			</div>
		</div>

		<div class="row">
			<div class="col-sm-12">
				<?php echo anchor('invoice', $this->lang->line('common_back'), array('class' => 'btn btn-default')); ?>
				<?php if ($invoice->status === 'pending'): ?>
					<?php echo anchor('invoice/fulfill/' . $invoice->invoice_id, $this->lang->line('invoice_mark_fulfilled'), array('class' => 'btn btn-success')); ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php $this->load->view("partial/footer"); ?>
