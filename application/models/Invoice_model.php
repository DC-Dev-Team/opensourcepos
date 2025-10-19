<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Invoice Model
 * 
 * Handles all database operations for the invoice functionality
 */
class Invoice_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Create a new invoice
	 */
	public function create_invoice($invoice_data)
	{
		$this->db->trans_start();

		try {
			// Insert invoice record
			$invoice_record = array(
				'invoice_date' => $invoice_data['invoice_date'],
				'branch_location_id' => $invoice_data['branch_location_id'],
				'notes' => $invoice_data['notes'],
				'created_by' => $invoice_data['created_by'],
				'status' => 'pending',
				'created_at' => date('Y-m-d H:i:s')
			);

			$this->db->insert('ospos_invoices', $invoice_record);
			$invoice_id = $this->db->insert_id();

			// Insert invoice items
			$total_amount = 0;
			foreach ($invoice_data['items'] as $item) {
				if (!empty($item['item_id']) && !empty($item['quantity'])) {
					// Use provided unit_price and total_price from frontend
					$unit_price = isset($item['unit_price']) ? $item['unit_price'] : 0;
					$total_price = isset($item['total_price']) ? $item['total_price'] : ($unit_price * $item['quantity']);
					
					$invoice_item = array(
						'invoice_id' => $invoice_id,
						'item_id' => $item['item_id'],
						'quantity' => $item['quantity'],
						'unit_price' => $unit_price,
						'total_price' => $total_price
					);

					$this->db->insert('ospos_invoice_items', $invoice_item);
					$total_amount += $total_price;

					// Note: Stock deduction will happen only after HQ approval
					// This is handled in the approve_invoice() method
				}
			}

			// Update invoice total
			$this->db->where('invoice_id', $invoice_id);
			$this->db->update('ospos_invoices', array('total_amount' => $total_amount));

			$this->db->trans_complete();

			return $this->db->trans_status() !== FALSE ? $invoice_id : FALSE;

		} catch (Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}
	}

	/**
	 * Approve an invoice and deduct stock from HQ
	 */
	public function approve_invoice($invoice_id, $approved_by)
	{
		$this->db->trans_start();

		try {
			// Get invoice details
			$invoice = $this->get_invoice($invoice_id);
			if (!$invoice || $invoice['status'] !== 'pending') {
				return FALSE;
			}

			// Get invoice items
			$items = $this->get_invoice_items($invoice_id);
			
			// Deduct stock from HQ for each item
			foreach ($items as $item) {
				$this->deduct_stock_from_hq($item['item_id'], $item['quantity']);
			}

			// Update invoice status to approved
			$this->db->where('invoice_id', $invoice_id);
			$this->db->update('ospos_invoices', array(
				'status' => 'approved',
				'approved_by' => $approved_by,
				'approved_at' => date('Y-m-d H:i:s')
			));

			$this->db->trans_complete();
			return $this->db->trans_status() !== FALSE;

		} catch (Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}
	}

	/**
	 * Decline an invoice
	 */
	public function decline_invoice($invoice_id, $declined_by, $reason = '')
	{
		$this->db->trans_start();

		try {
			// Update invoice status to declined
			$this->db->where('invoice_id', $invoice_id);
			$this->db->update('ospos_invoices', array(
				'status' => 'declined',
				'declined_by' => $declined_by,
				'declined_at' => date('Y-m-d H:i:s'),
				'decline_reason' => $reason
			));

			$this->db->trans_complete();
			return $this->db->trans_status() !== FALSE;

		} catch (Exception $e) {
			$this->db->trans_rollback();
			return FALSE;
		}
	}

	/**
	 * Get invoice details
	 */
	public function get_invoice($invoice_id)
	{
		$this->db->from('ospos_invoices');
		$this->db->where('invoice_id', $invoice_id);
		$query = $this->db->get();
		return $query->row_array();
	}

	/**
	 * Get invoice items
	 */
	public function get_invoice_items($invoice_id)
	{
		$this->db->select('ii.*, i.name as item_name, i.item_number, i.category as category');
		$this->db->from('ospos_invoice_items ii');
		$this->db->join('ospos_items i', 'i.item_id = ii.item_id');
		$this->db->where('ii.invoice_id', $invoice_id);
		$query = $this->db->get();
		return $query->result_array();
	}

	/**
	 * Get all invoices for Bootstrap Table
	 */
	public function get_all_invoices()
	{
		$this->db->select('i.*, sl.location_name as branch_name, CONCAT(p.first_name, " ", p.last_name) as created_by');
		$this->db->from('ospos_invoices i');
		$this->db->join('ospos_stock_locations sl', 'sl.location_id = i.branch_location_id');
		$this->db->join('ospos_people p', 'p.person_id = i.created_by');
		$this->db->order_by('i.invoice_date', 'DESC');
		
		$invoices = $this->db->get()->result_array();
		
		// Add item details and total quantity for each invoice
		foreach ($invoices as &$invoice) {
			$invoice['items'] = $this->get_invoice_items($invoice['invoice_id']);
			$invoice['total_quantity'] = $this->calculate_total_quantity($invoice['items']);
		}
		
		return $invoices;
	}

	/**
	 * Get invoices by branch location
	 */
	public function get_invoices_by_branch($branch_id)
	{
		$this->db->select('i.*, sl.location_name as branch_name, CONCAT(p.first_name, " ", p.last_name) as created_by');
		$this->db->from('ospos_invoices i');
		$this->db->join('ospos_stock_locations sl', 'sl.location_id = i.branch_location_id');
		$this->db->join('ospos_people p', 'p.person_id = i.created_by');
		$this->db->where('i.branch_location_id', $branch_id);
		$this->db->order_by('i.invoice_date', 'DESC');
		
		$invoices = $this->db->get()->result_array();
		
		// Add item details and total quantity for each invoice
		foreach ($invoices as &$invoice) {
			$invoice['items'] = $this->get_invoice_items($invoice['invoice_id']);
			$invoice['total_quantity'] = $this->calculate_total_quantity($invoice['items']);
		}
		
		return $invoices;
	}

	/**
	 * Get all invoices for HQ review
	 */
	public function get_all_for_review()
	{
		$this->db->select('i.*, sl.location_name as branch_name, CONCAT(p.first_name, " ", p.last_name) as created_by_name');
		$this->db->from('ospos_invoices i');
		$this->db->join('ospos_stock_locations sl', 'sl.location_id = i.branch_location_id');
		$this->db->join('ospos_people p', 'p.person_id = i.created_by');
		// Removed status filter to show all invoices
		$this->db->order_by('i.invoice_date', 'DESC');
		
		$query = $this->db->get();
		$invoices = $query->result_array();
		
		// Format data for Bootstrap Table
		$data = array();
		foreach ($invoices as $invoice) {
			// Get items for this invoice
			$items = $this->get_invoice_items($invoice['invoice_id']);
			$total_quantity = $this->calculate_total_quantity($items);
			
			$data[] = array(
				'invoice_id' => $invoice['invoice_id'],
				'invoice_date' => $invoice['invoice_date'],
				'branch_name' => $invoice['branch_name'],
				'total_amount' => number_format($invoice['total_amount'], 2),
				'status' => ucfirst($invoice['status']),
				'created_by_name' => $invoice['created_by_name'],
				'items' => $items,
				'total_quantity' => $total_quantity,
				'actions' => get_invoice_actions($invoice['invoice_id'], $invoice['status'])
			);
		}
		
		return $data;
	}


	/**
	 * Mark invoice as fulfilled
	 */
	public function mark_fulfilled($invoice_id)
	{
		$this->db->where('invoice_id', $invoice_id);
		return $this->db->update('ospos_invoices', array(
			'status' => 'fulfilled',
			'fulfilled_at' => date('Y-m-d H:i:s')
		));
	}

	/**
	 * Calculate total quantity for invoice items
	 */
	private function calculate_total_quantity($items)
	{
		$total = 0;
		foreach ($items as $item) {
			$total += (int)$item['quantity'];
		}
		return $total;
	}

	/**
	 * Get item details
	 */
	private function get_item_details($item_id)
	{
		$this->db->select('unit_price');
		$this->db->from('ospos_items');
		$this->db->where('item_id', $item_id);
		
		$result = $this->db->get()->row();
		if (!$result) {
			return array('unit_price' => 0);
		}
		return (array)$result;
	}

	/**
	 * Deduct stock from HQ
	 */
	private function deduct_stock_from_hq($item_id, $quantity)
	{
		// Check if item exists in HQ stock
		$this->db->select('quantity');
		$this->db->from('ospos_item_quantities');
		$this->db->where('item_id', $item_id);
		$this->db->where('location_id', 1); // HQ location
		
		$current_stock = $this->db->get()->row();
		
		if (!$current_stock) {
			// Insert new stock record (negative quantity)
			$this->db->insert('ospos_item_quantities', array(
				'item_id' => $item_id,
				'location_id' => 1,
				'quantity' => -$quantity
			));
			return;
		}

		// Update existing stock
		$new_quantity = $current_stock->quantity - $quantity;
		$this->db->where('item_id', $item_id);
		$this->db->where('location_id', 1);
		$this->db->update('ospos_item_quantities', array('quantity' => $new_quantity));
	}

	/**
	 * Get stock levels for items
	 */
	public function get_item_stock_levels()
	{
		// Only get items that have stock records in HQ location (location_id = 1)
		// This ensures we only show items that are available from HQ
		$this->db->select('i.item_id, i.name, i.item_number, i.unit_price, COALESCE(iq.quantity, 0) as stock_quantity, i.category as category');
		$this->db->from('ospos_items i');
		$this->db->join('ospos_item_quantities iq', 'iq.item_id = i.item_id AND iq.location_id = 1', 'inner');
		$this->db->where('i.deleted', 0);
		$this->db->order_by('i.name');
		
		return $this->db->get()->result();
	}
}
