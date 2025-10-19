<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Invoice Helper Functions
 */

/**
 * Get invoice management table headers
 */
function get_invoice_manage_table_headers()
{
	$CI =& get_instance();
	
	$headers = array(
		array('field' => 'invoice_id', 'title' => $CI->lang->line('invoice_invoice_id'), 'sortable' => true),
		array('field' => 'invoice_date', 'title' => $CI->lang->line('invoice_date'), 'sortable' => true),
		array('field' => 'branch_name', 'title' => $CI->lang->line('invoice_branch'), 'sortable' => true),
		array('field' => 'items', 'title' => $CI->lang->line('invoice_items'), 'sortable' => false, 'formatter' => 'itemsFormatter'),
		array('field' => 'total_quantity', 'title' => 'Total Quantity', 'sortable' => true, 'formatter' => 'quantityFormatter'),
		array('field' => 'total_amount', 'title' => $CI->lang->line('invoice_total_amount'), 'sortable' => true),
		array('field' => 'status', 'title' => $CI->lang->line('invoice_status'), 'sortable' => true),
		array('field' => 'created_by', 'title' => $CI->lang->line('invoice_created_by'), 'sortable' => true)
	);
	
	return json_encode($headers);
}

/**
 * Get invoice review table headers
 */
function get_invoice_review_table_headers()
{
	$CI =& get_instance();
	
	$headers = array(
		array('field' => 'invoice_id', 'title' => $CI->lang->line('invoice_invoice_id'), 'sortable' => true),
		array('field' => 'invoice_date', 'title' => $CI->lang->line('invoice_date'), 'sortable' => true),
		array('field' => 'branch_name', 'title' => $CI->lang->line('invoice_branch'), 'sortable' => true),
		array('field' => 'items', 'title' => $CI->lang->line('invoice_items'), 'sortable' => false, 'formatter' => 'itemsFormatter'),
		array('field' => 'total_quantity', 'title' => 'Total Quantity', 'sortable' => true, 'formatter' => 'quantityFormatter'),
		array('field' => 'total_amount', 'title' => $CI->lang->line('invoice_total_amount'), 'sortable' => true),
		array('field' => 'status', 'title' => $CI->lang->line('invoice_status'), 'sortable' => true),
		array('field' => 'created_by_name', 'title' => $CI->lang->line('invoice_created_by'), 'sortable' => true),
		array('field' => 'actions', 'title' => '&nbsp', 'sortable' => false, 'escape' => false)
	);
	
	return json_encode($headers);
}

/**
 * Get invoice actions for table
 */
function get_invoice_actions($invoice_id, $status)
{
	$CI =& get_instance();
	
	$actions = '';
	
	// View action
	$actions .= anchor('invoice/view/' . $invoice_id, 
		'<span class="glyphicon glyphicon-eye-open"></span>', 
		array('class' => 'btn btn-info btn-sm', 'title' => $CI->lang->line('common_view'))
	);
	
	// Status-specific actions
	if ($status == 'pending') {
		$actions .= '&nbsp;';
		$actions .= '<button class="btn btn-success btn-sm approve-invoice" data-invoice-id="' . $invoice_id . '" title="Approve Invoice">';
		$actions .= '<span class="glyphicon glyphicon-ok"></span>';
		$actions .= '</button>';
		
		$actions .= '&nbsp;';
		$actions .= '<button class="btn btn-danger btn-sm decline-invoice" data-invoice-id="' . $invoice_id . '" title="Decline Invoice">';
		$actions .= '<span class="glyphicon glyphicon-remove"></span>';
		$actions .= '</button>';
	} else {
		// Show disabled buttons for non-pending invoices
		$actions .= '&nbsp;';
		$actions .= '<button class="btn btn-success btn-sm" disabled title="Already ' . ucfirst($status) . '">';
		$actions .= '<span class="glyphicon glyphicon-ok"></span>';
		$actions .= '</button>';
		
		$actions .= '&nbsp;';
		$actions .= '<button class="btn btn-danger btn-sm" disabled title="Already ' . ucfirst($status) . '">';
		$actions .= '<span class="glyphicon glyphicon-remove"></span>';
		$actions .= '</button>';
	}
	
	return $actions;
}
