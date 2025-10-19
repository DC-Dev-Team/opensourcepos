<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once("Secure_Controller.php");

/**
 * Invoice Controller
 * 
 * This controller handles the invoice functionality for branch orders.
 * Branches can create invoices for their orders, which automatically deducts
 * stock from HQ and creates orders for HQ to fulfill.
 */
class Invoice extends Secure_Controller
{
	public function __construct()
	{
		parent::__construct('invoice');
		$this->load->helper('invoice');
		$this->lang->load('invoice');
	}

	/**
	 * Default method - shows the invoice interface
	 */
	public function index()
	{
		// Check if user has invoice_view permission
		$logged_in_employee_info = $this->Employee->get_logged_in_employee_info();
		if (!$this->Employee->has_grant('invoice_view', $logged_in_employee_info->person_id)) {
			redirect('no_access/invoice/invoice_view');
		}
		
		$this->load->model('Invoice_model');
		
		// Get current user's branch location
		$this->load->model('Stock_location');
		$current_branch_id = $this->Stock_location->get_default_location_id('items');
		
		$data['table_headers'] = $this->xss_clean(get_invoice_manage_table_headers());
		$data['invoices'] = $this->Invoice_model->get_invoices_by_branch($current_branch_id);
		
		$this->load->view('invoice/manage', $data);
	}

	/**
	 * Create a new invoice for branch orders
	 */
	public function create()
	{
		// Check if user has invoice_create permission
		$logged_in_employee_info = $this->Employee->get_logged_in_employee_info();
		if (!$this->Employee->has_grant('invoice_create', $logged_in_employee_info->person_id)) {
			redirect('no_access/invoice/invoice_create');
		}
		
		$data = array();
		
		// Get all stock locations (branches)
		$this->load->model('Stock_location');
		$data['stock_locations'] = $this->Stock_location->get_all()->result_array();
		
		// Get all items
		$this->load->model('Item');
		$data['items'] = $this->Item->get_all()->result_array();
		
		// Get current user's location
		$current_location_id = $this->Stock_location->get_default_location_id('items');
		$data['current_location'] = $current_location_id;
		$data['current_location_name'] = $this->Stock_location->get_location_name($current_location_id);
		
		$this->load->view('invoice/form', $data);
	}

	/**
	 * Process the invoice creation
	 */
	public function save($data_item_id = -1)
	{
		// Check if user has invoice_create permission
		$logged_in_employee_info = $this->Employee->get_logged_in_employee_info();
		if (!$this->Employee->has_grant('invoice_create', $logged_in_employee_info->person_id)) {
			echo json_encode(array(
				'success' => false,
				'message' => 'Access denied. You do not have permission to create invoices.'
			));
			return;
		}
		
		// Set content type to JSON
		$this->output->set_content_type('application/json');
		
		$items_json = $this->input->post('items');
		$items_array = json_decode($items_json, true);
		
		$invoice_data = array(
			'invoice_date' => $this->input->post('invoice_date'),
			'branch_location_id' => $this->input->post('branch_location_id'),
			'items' => $items_array,
			'notes' => $this->input->post('notes'),
			'created_by' => $this->session->userdata('person_id')
		);
		
		// Validate the data
		if (empty($invoice_data['branch_location_id']) || empty($invoice_data['items'])) {
			echo json_encode(array(
				'success' => false,
				'message' => 'Please select a branch and add items to the invoice.'
			));
			return;
		}

		// Process the invoice
		$this->load->model('Invoice_model');
		$result = $this->Invoice_model->create_invoice($invoice_data);

		if ($result) {
			echo json_encode(array(
				'success' => true,
				'message' => 'Invoice request created successfully. Awaiting HQ approval before stock deduction.'
			));
		} else {
			echo json_encode(array(
				'success' => false,
				'message' => 'Failed to create invoice. Please try again.'
			));
		}
	}

	/**
	 * View invoice details
	 */
	public function view($data_item_id = -1)
	{
		// Check if user has invoice_view permission
		$logged_in_employee_info = $this->Employee->get_logged_in_employee_info();
		if (!$this->Employee->has_grant('invoice_view', $logged_in_employee_info->person_id)) {
			redirect('no_access/invoice/invoice_view');
		}
		
		$invoice_id = $data_item_id;
		
		$this->load->model('Invoice_model');
		$invoice_data = $this->Invoice_model->get_invoice($invoice_id);
		
		if (!$invoice_data) {
			show_404();
		}
		
		// Get invoice with all related data
		$this->db->select('i.*, sl.location_name as branch_name, CONCAT(p.first_name, " ", p.last_name) as created_by_name');
		$this->db->from('ospos_invoices i');
		$this->db->join('ospos_stock_locations sl', 'sl.location_id = i.branch_location_id');
		$this->db->join('ospos_people p', 'p.person_id = i.created_by');
		$this->db->where('i.invoice_id', $invoice_id);
		$query = $this->db->get();
		$invoice = $query->row();
		
		if (!$invoice) {
			show_404();
		}
		
		// Get invoice items
		$invoice->items = $this->Invoice_model->get_invoice_items($invoice_id);
		
		$data['invoice'] = $invoice;
		
		$this->load->view('invoice/view', $data);
	}

	/**
	 * List all invoices for HQ review
	 */
	public function review()
	{
		// Check if user has invoice_review permission
		$logged_in_employee_info = $this->Employee->get_logged_in_employee_info();
		if (!$this->Employee->has_grant('invoice_review', $logged_in_employee_info->person_id)) {
			redirect('no_access/invoice/invoice_review');
		}
		
		$this->load->model('Invoice_model');
		
		$data['table_headers'] = $this->xss_clean(get_invoice_review_table_headers());
		$data['invoices'] = $this->Invoice_model->get_all_for_review();
		
		$this->load->view('invoice/review', $data);
	}

	/**
	 * Mark invoice as fulfilled
	 */
	public function fulfill($data_item_id = -1)
	{
		$invoice_id = $data_item_id;
		
		$this->load->model('Invoice_model');
		$result = $this->Invoice_model->mark_fulfilled($invoice_id);
		
		if ($result) {
			$this->session->set_flashdata('success', 'Invoice marked as fulfilled.');
		} else {
			$this->session->set_flashdata('error', 'Failed to mark invoice as fulfilled.');
		}
		
		redirect('invoice/review');
	}


	/**
	 * Get items for autocomplete
	 */
	public function get_items()
	{
		$this->load->model('Invoice_model');
		$items = $this->Invoice_model->get_item_stock_levels();
		
		$data = array();
		foreach ($items as $item) {
			$data[] = array(
				'item_id' => $item->item_id,
				'name' => $item->name,
				'item_number' => $item->item_number,
				'unit_price' => $item->unit_price,
				'stock_quantity' => $item->stock_quantity,
                'category' => $item->category
			);
		}
		
		echo json_encode($data);
	}

	/**
	 * Approve an invoice (HQ only)
	 */
	public function approve($invoice_id)
	{
		// Check if user has invoice_review permission
		$logged_in_employee_info = $this->Employee->get_logged_in_employee_info();
		if (!$this->Employee->has_grant('invoice_review', $logged_in_employee_info->person_id)) {
			echo json_encode(array(
				'success' => false,
				'message' => 'Access denied. You do not have permission to approve invoices.'
			));
			return;
		}
		
		$this->output->set_content_type('application/json');
		$this->load->model('Invoice_model');
		
		$result = $this->Invoice_model->approve_invoice($invoice_id, $this->session->userdata('person_id'));
		
		if ($result) {
			echo json_encode(array(
				'success' => true,
				'message' => 'Invoice approved successfully. Stock has been deducted from HQ.'
			));
		} else {
			echo json_encode(array(
				'success' => false,
				'message' => 'Failed to approve invoice. Please try again.'
			));
		}
	}

	/**
	 * Decline an invoice (HQ only)
	 */
	public function decline($invoice_id)
	{
		// Check if user has invoice_review permission
		$logged_in_employee_info = $this->Employee->get_logged_in_employee_info();
		if (!$this->Employee->has_grant('invoice_review', $logged_in_employee_info->person_id)) {
			echo json_encode(array(
				'success' => false,
				'message' => 'Access denied. You do not have permission to decline invoices.'
			));
			return;
		}
		
		$this->output->set_content_type('application/json');
		$this->load->model('Invoice_model');
		
		$reason = $this->input->post('reason') ?: '';
		$result = $this->Invoice_model->decline_invoice($invoice_id, $this->session->userdata('person_id'), $reason);
		
		if ($result) {
			echo json_encode(array(
				'success' => true,
				'message' => 'Invoice declined successfully.'
			));
		} else {
			echo json_encode(array(
				'success' => false,
				'message' => 'Failed to decline invoice. Please try again.'
			));
		}
	}

}
