<?php
/**
 *
 * @package    Common
 * @subpackage Common_Controllers
 * @revision   $Id$
 */

/**
 * Include required classes
 */
require_once 'Modules/Common/Controller/Action.php';
require_once 'Zend/Paginator.php';

/**
 * Order controller class for the Common module
 *
 * @package    Common
 * @subpackage Common_Controller
 */
class Common_OrdersController extends Modules_Common_Controller_Action {

	/**
	 * Array of all possible statuses
	 */
	protected $_statuses = array('PENDING', 'ACTIVE', 'HOLD', 'CANCELLED', 'RTF', 'SENT');

	/**
	 * Controller action to display all orders availiable to the logged in user
	 *
	 * @todo Assure that the orders list are accessible to the logged in user
	 */
	public function indexAction() {

		// init variables
		$request = $this->getRequest();
		$user    = $this->loggedInUser();

		// prepare base select
		$ordModel = Base::getModel('Common_Orders');
		$select   = $ordModel->select()
		                     ->setIntegrityCheck(false)
		                     ->from(array('co' => 'common_orders'));
		$join_client_products = false;
		// customs searches
		if ($custom = $request->getParam('custom')) {
			
			switch($custom) {
				// filter orders that have been activated based
				case 'first_active':
					$first_active_date = $request->getParam('date');
					$select->joinLeft(array('coad' => 'view_common_order_active_date'), 'coad.common_order_id = co.id', null)
					       ->where('DATE(coad.date_activated) = ?', $first_active_date);

					// filter product line
					$product_line_id = $request->getParam('product_line_id');
					$select->joinLeft(array('ci' => 'common_items'), 'ci.common_order_id = co.id', null)
					       ->joinLeft(array('cp' => 'client_products'), 'ci.client_product_id = cp.id', null)
					       ->where('cp.product_line_id = ?', $product_line_id);
					       
					// filter sub
					if((int)$request->getParam('sub_id') > 0)
						$select->where('co.sub_id = ?', $request->getParam('sub_id'));
					
					
					break;

				// filter orders that have been sent based
				case 'date_sent':
					$sent_date = $request->getParam('date');
					$select->joinLeft(array('cosd' => 'view_common_order_sent_date'), 'cosd.common_order_id = co.id', null)
					       ->where('DATE(cosd.date_sent) = ?', $sent_date);
					
					// filter product line
					$product_line_id = $request->getParam('product_line_id');
					$select->joinLeft(array('ci' => 'common_items'), 'ci.common_order_id = co.id', null)
					       ->joinLeft(array('cp' => 'client_products'), 'ci.client_product_id = cp.id', null)
					       ->where('cp.product_line_id = ?', $product_line_id);
					$join_client_products = true;     
					// filter sub
					//if ($sub_id = $request->getParam('sub_id') != 'all') {
					//	$select->where('co.sub_id = ?', $sub_id);
					//}
					if((int)$request->getParam('sub_id') > 0)
						$select->where('co.sub_id = ?', $request->getParam('sub_id'));
					break;
				case 'api_overdue_orders':
					$select->where("co.date_due < CURDATE()")
					->where("co.status NOT IN ('SENT', 'CANCELLED', 'PENDING')");
					break;
				case'api_almostoverdueorders':
					$select->where("(round( abs(NOW()- co.date_due) / 86400) < 3)")
	                                ->where("(round( abs(NOW()- co.date_due) / 86400) >= 0)")                                                        
					->where("co.status NOT IN ('SENT', 'CANCELLED', 'PENDING')");
			}
			
		} else {
		
			// filter: keywords
			if ($keywords_orders = $request->getParam('keywords_orders')) {
	
				// filter leading and trailing spaces
				$keywords_orders = trim($keywords_orders);
	
				// search numeric
				if (is_numeric($keywords_orders)) {
					if (is_float($keywords_orders)) {
						$select->where('co.amount = ?', $keywords_orders); // AMOUNT search
	
					} else {
						$select->where('co.order_number = ?', $keywords_orders); // order number search
					}
	
				} else if (strpos($keywords_orders, '-') !== false) {
					$select->where('co.date_created LIKE ?', "%$keywords_orders%"); // date created
	
	
				} else {
					//->joinInner(array('ci' => 'common_items'), 'co.id = ci.common_order_id', null)
					$select->joinInner(array('cu' => 'common_users'), 'cu.id = co.common_user_id', null);
					//->joinInner(array('cp' => 'client_products'), 'cp.common_user_id = cu.id', null);
	
					if (strpos($keywords_orders, '@') !== false) {
						$select->where('cu.email LIKE ?', "%$keywords_orders%"); // email search
	
	
					} else { // everything else
						$select->orWhere('cu.first_name LIKE ?', "%$keywords_orders%")
						->orWhere('cu.last_name LIKE ?', "%$keywords_orders%")
						->orWhere('cu.company LIKE ?', "%$keywords_orders%");
	
						// only with spaces
						if (strrpos($keywords_orders, ' ') !== false) {
							$select->orWhere('CONCAT_WS(\' \', cu.first_name, cu.last_name) LIKE ?', "%$keywords_orders%");
						}
						//an order is usually start M(in old system), remove M before search
						$search_order_number 	 = preg_replace("/^[^1-9]*(.*?)$/","$1", $keywords_orders);
						$select->orwhere('co.order_number = ?', $search_order_number); // order number search
					}
				}
			}

			// filter subs based on the logged in user
//			$select->where('co.sub_id IN (?)', $user->subs()->inField('id'));
			
			// filter sub
			//$sub_id = $request->getParam('sub_id');
			//if ($sub_id) {
			//	$select->where('co.sub_id = ?', $sub_id);
			//
			//} else {
			//	$select->where('co.sub_id IN (?)', $user->subs()->inField('id'));
			//}
			
			$sub_id = $request->getParam('sub_id');
			if($sub_id && $sub_id == 'MOD'){
				if($join_client_products == false){
					$select->joinLeft(array('ci' => 'common_items'), 'ci.common_order_id = co.id', null)
					->joinLeft(array('cp' => 'client_products'), 'ci.client_product_id = cp.id', null);
				}
				$select->where('cp.software_code = ?', $sub_id);
			}else if ($sub_id && $sub_id != 'all') {
				$subIds = $user->subIds($sub_id);
				if(count($subIds) == 0)
					$subIds = 0;
				$select->where('co.sub_id IN (?)', $subIds);				
			} else {
				$select->where('co.sub_id IN (?)', $user->subs()->inField('id'));
			}
			
			// filter status
			$status = $request->getParam('status');
			if ($status) {
				$select->where('status = ?', $status);
			}
			
			// filters
			if ($client_id = $request->getParam('client-id')) {
				$select->joinInner(array('cu' => 'common_users'), 'cu.id = co.common_user_id', null)
				->where('cu.id = ?', $client_id);
			}


		}
		
		$orderby = $request->getParam('orderby');
		if ($orderby) {
			$select->order($orderby);
		} else {
			$select->order('id DESC');
		}
		
		$select->group('co.id');
		
		$this->view->filter = true;
		$page      = $request->getParam('page', 1);
		$paginator = Zend_Paginator::factory($select);
		$paginator->setItemCountPerPage(20);
		$paginator->setCurrentPageNumber($page);
		$this->view->subs = $user->subs();
		$this->view->status = array('' => 'All', 'PENDING' => 'Order PENDING', 'ACTIVE' => 'Order ACTIVE', 'HOLD' => 'Order HOLD', 'CANCELLED' => 'Order CANCELLED', 'RTF' => 'Order RTF', 'SENT' => 'Order SENT');
		$this->view->orders = $paginator;
		$this->view->title = 'Orders : Page '.$page;
		$this->view->data = $request->getParams();
	}

	/**
	 * Controller action to edit an order accessible to the logged in user
	 */
	public function editAction() {

		// initialise action varibles
		$request    = $this->getRequest();
		$user       = $this->loggedInUser();
		$this->view->user = $user;
		$this->view->sub   = Base::siteSub();
		// get the requested order. Also checks that the logged in user has access
		$order_id = $request->getParam('id');
		$ordModel = Base::getModel('Common_Orders');
		$where    = $ordModel->select()->where('id = ?', $order_id)->where('sub_id IN (?)', $user->subs()->inField('id'));
		$order    = $ordModel->fetchRow($where);
		if (!$order) {
			$this->addFlash('Invalid order', 'errors');
			$this->_redirect('/common/orders');
			return;
		}
		
		if ($order->isLocked()){
			$this->addFlash('This order is locked.', 'warning');
		}
		
		if (!$user->access($order->commonUser()->sub()->id)) {
			$this->addFlash('You do not have access to this record. Please contact site administrator.', 'notice');
			$this->_redirect('/common/orders', array('exit' => true));
		}
	
		$this->view->order = $order;
		$this->view->source = 'Order '. $order->order_number ;
		$this->view->statuses = array_combine($this->_statuses, $this->_statuses); // use same key & value to satisfy the select
		$this->view->status3 = array('' => '', 'ACTIVE' => 'Order ACTIVE', 'HOLD' => 'Order HOLD', 'CANCELLED' => 'Order CANCELLED');
		$notesModel = Base::getModel('Notes');
		$notes_data = array(
			'common_orders'		=> $order->id,
			'common_users'		=> $order->commonUser()->id,
			'common_items'		=> '',
			'client_products'	=> '',
		);

		$this->view->notes = $notesModel->fetchNotes($notes_data);	
		$this->view->users = $this->internalUsers();
		
		// form has been submitted
		if ($request->isPost()) {
			
			// last minute
			$request->setParam('id', $order->id);

			$updated = $ordModel->process($request->getParams(), 'update', array('id', 'priority', 'gift', 'watch', 'internal', 'dispatch_notes'));
			if ($updated) {
				// add log if priority changed
				if ($request->getParam('priority') != $order->priority ||
				    $request->getParam('watch') != $order->watch) {
					$order->changePriorityLog($request->getParam('priority'), $order->priority, $request->getParam('watch'), $order->watch);
				}
				
				$this->addFlash('Order updated.', 'notice');
				$this->_redirect('/common/orders/edit/id/' . $order->id, array('exit' => true));
				return;

			} else {
				$this->addFlash('Error updating order. Correct form below.', 'errors');
				$this->view->errors = $ordModel->getErrors();
				$this->view->data   = $request->getParams();

				return;
			}
		}

		// first time loading form
		$this->view->data  = $order->toArray();
	}

	/**
	 * Controller action to edit an order accessible to the logged in user
	 */
	public function invoiceAction() {

		// initialise action varibles
		$request    = $this->getRequest();
		$user       = $this->loggedInUser();
		
		// get the requested order. Also checks that the logged in user has access
		$order_id = $request->getParam('id');
		$ordModel = Base::getModel('Common_Orders');
		$select   = $ordModel->select()
		                     ->where('id = ?', $order_id)
				     ->where('sub_id IN (?)', $user->subs()->inField('id'));
		
		$order    = $ordModel->fetchRow($select);
		
		if (!$order) {
			$this->addFlash('Invalid order', 'errors');
			$this->_redirect('/common/orders', array('exit' => true));
		}

		$this->view->order =  $order;
		$date = new DateTime();
		$this->view->currentYear  = $date->format('Y');

	}

	/**
	 *This method call by ajax
	 * Delete an adjustment
	 * get the new value of paymentDueAmount
	 * get the adjustment total amount
	 */
	public function adjustmentDeleteAction() {
		$result  = array('errcode' => 0, 'errmsg' => null); // default response

		// init params
		$request  = $this->getRequest();
		$order_id = $request->getParam('order_id');
		$adjustment_id = $request->getParam('adjustment_id');
		$user    = $this->loggedInUser();
		
		// get the order object
		$adjModel = Base::getModel('Common_Orders_Adjustments');
		$adjustment_row =  $adjModel->fetchById($adjustment_id);
		$note_data = array(
						'user_code'  => $user->id,
						'table_name' => 'common_orders',
						'note'       => 'Adjustment Deleted,   Type: ' . $adjustment_row->type . ', Amount: ' .$adjustment_row->currency_code .''.$adjustment_row->amount . ', Note:  ' . $adjustment_row->note,
						'table_id'   => $order_id,
					);
		$noteModel = Base::getModel('Notes');
		$created   = $noteModel->process($note_data, 'insert');
		$note_data['id'] = $noteModel->getId();
		$note_data['user'] = $user->first_name . ' ' .$user->last_name;
		$note_data['date_time'] = date('d-M-Y h:ia');
		
		$where   = $adjModel->getAdapter()->quoteInto('id = ?', $adjustment_id);
		$adjModel->delete($where);

		$ordModel = Base::getModel('Common_Orders');
		$order    = $ordModel->fetchById($order_id);
		$result['adjustment_amount'] =  sprintf("%01.2f", $order->adjustment_amount);
		$result['payment_due'] =  sprintf("%01.2f", $order->payment_due);
		$result['note_data'] =  $note_data;

		$this->_helper->viewRenderer->setNoRender(true);
		$this->getResponse()->setHeader('Content-Type','text/html');
		$this->getResponse()->setBody(Zend_Json::encode($result));
	}

	public function addShippingAction(){
		$result  = array('errcode' => 0, 'errmsg' => null); // default response

		// init params
		$request  = $this->getRequest();
		$shipping = $request->getParam('shipping');
		$shipping['amount'] = (float)$shipping['amount'];
		if (empty($shipping['catalogue_shipping_method_id'])) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing shipping method<br />";
		}
		if (empty($shipping['amount'])) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing Amount<br />";
		}
		if (empty($shipping['first_name'])) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing first name<br />";
		}
		if (empty($shipping['last_name'])) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing last name<br />";
		}
		if (empty($shipping['address1'])) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing Address<br />";
		}
		if (empty($shipping['city'])) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing city<br />";
		}
		if (empty($shipping['state'])) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing state<br />";
		}
		if (empty($shipping['postcode'])) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing postcode<br />";
		}
		if (empty($shipping['country_id'])) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing country<br />";
		}

        //save shipping data
		if($result['errcode'] == 0){
			$shipModel     = Base::getModel('Common_Orders_Shipping');
			if((int)$shipping['id'] > 0){
				$created = $shipModel->process($shipping, 'update');
			}else{
				$created = $shipModel->process($shipping, 'insert');
				$common_order_shipping_id = $shipModel->getId();
				//$result['amount']  = $shipping['amount'];
				//$result['shipping_method']  = $this->getShippingName($shipping['catalogue_shipping_method_id']);
			}
		}


		$this->_helper->viewRenderer->setNoRender(true);
		$this->getResponse()->setHeader('Content-Type','text/html');
		$this->getResponse()->setBody(Zend_Json::encode($result));
	}

	/**
	 * Controller action to make orders adjustment
	 */
	public function adjustmentsAction() {
			
		// init params
		$request  = $this->getRequest();
		$user     = $this->loggedInUser();

		// get the order object. has additional condition to make sure
		// that the user making an adjustment has sub access
		$common_order_id = $request->getParam('common_order_id');
		$ordModel        = Base::getModel('Common_Orders');
		$select          = $ordModel->select()->where('id = ?', $common_order_id)->where('sub_id IN (?)', $user->subs()->inField('id'));
		$order           = $ordModel->fetchRow($select);
		if (!$order) {
			$this->addFlash('Invalid order', 'errors');
			$this->_redirect('/common/orders', array('exit' => true));
			return;
		}
		$this->view->order = $order;

		if ($request->isPost()) {

			$request->setParam('user_id', $user->id);

			$adjModel = Base::getModel('Common_Orders_Adjustments');
			$created  = $adjModel->process($request->getParams(), 'insert');
			if ($created) {
				$this->addFlash('Added new payment adjustment.', 'notice');
				$this->_redirect('/common/orders/edit/id/' . $order->id, array('exit' => true));

			} else {
				$this->addFlash('Error processing form. Please correct form below.', 'errors');
				$this->view->errors = $adjModel->getErrors();
				$this->view->data   = $request->getParams();
			}

			return;
		}
	}

	/**
	 * Get all status activity on an order
	 *
	 * @return json $result
	 */
	public function statusActivityAction() {

		// init variables
		$request = $this->getRequest();
		$user    = $this->loggedInUser();
		$result  = array('errcode' => 0, 'errmsg' => null); // default response

		// get the order object
		$order_id = $request->getParam('id');
		$ordModel = Base::getModel('Common_Orders');
		$order    = $ordModel->fetchById($order_id);

		// manage the data
		$activity = array();
		foreach($order->activity() as $row) {
			$activity[] = array(
				date("d M Y h:ia", strtotime($row->created)),
				$row->status,
				$row->user()->fullname(),
				$row->note,
				$row->flag,
				$row->itemNumber()
			);
		}

		$result['data']['activity'] = $activity;

		$this->_helper->viewRenderer->setNoRender(true);
		$this->getResponse()->setHeader('Content-Type','text/html');
		$this->getResponse()->setBody(Zend_Json::encode($result));
	}

	/**
	 * Update the order status via ajax
	 *
	 * @todo Just to be safe, we should again check if the logged in user has access
	 */
	public function statusUpdateAction() {

		// init variables
		$request = $this->getRequest();
		$user    = $this->loggedInUser();
		$result  = array('errcode' => 0, 'errmsg' => 'Ok.'); // default response

		// get the order object
		$order_id = $request->getParam('id');
		$ordModel = Base::getModel('Common_Orders');
		$order    = $ordModel->fetchById($order_id);

		// validate the new status
		$status = $request->getParam('status');
		if (!in_array($status, $this->_statuses)) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Invalid status<br />";
		}

		$note = $request->getParam('note');
		if (empty($note)) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing note<br />";
		}
		
		if ($result['errcode'] == 0) {
			$order->changeStatus($status, $note, $user->id);
			$due_date = date('d-M-Y', strtotime($order->date_due));
			if (empty($order->date_due)) {
				$due_date = 'n/a';	
			}
		
			$start_date = $order->firstActiveStatusDate() 
						  ? date('d-M-Y', strtotime($order->firstActiveStatusDate())) : 'n/a';
			
			$result['data'] = array(
				'start_date'     => $start_date,
				'due_date'       => $due_date,
				'remaining_days' => $order->daysToDueNice(),
				'status'	 => $order->status(),
				'itemStatusDominant' => $order->itemStatusDominant(),
				'statusColor' => $order->statusColor(),
			);
		}
		
		$this->_helper->viewRenderer->setNoRender(true);
		$this->getResponse()->setHeader('Content-Type','text/html');
		$this->getResponse()->setBody(Zend_Json::encode($result));
	}

	/**
	 * Update the due date via ajax
	 */
	public function dueUpdateAction() {

		// init variables
		$request = $this->getRequest();
		$user    = $this->loggedInUser();
		$result  = array('errcode' => 0, 'errmsg' => null); // default response

		// get the order object
		$order_id = $request->getParam('id');
		$ordModel = Base::getModel('Common_Orders');
		$order    = $ordModel->fetchById($order_id);

		// validate the new due date
		$due    = $request->getParam('due_date');
		$patern = '/^(0[1-9]|[1-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\/[0-9]{4}$/';
		if (!preg_match($patern, $due)) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Invalid date<br />";
		}

		$note = $request->getParam('due_note');
		if (empty($note)) {
			$result['errcode']  = 1;
			$result['errmsg' ] .= " - Missing note<br />";
		}

		if ($result['errcode'] == 0) {
			$parts      = explode('/', $due);
			$mysql_date = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
			$order->changeDue($mysql_date, $note, $user->id);

			$result['data'] = array(
				'due_date'       => date("d M Y", strtotime($order->date_due)),
				'remaining_days' => $order->daysToDueNice(),
			);
		}

		$this->_helper->viewRenderer->setNoRender(true);
		$this->getResponse()->setHeader('Content-Type','text/html');
		$this->getResponse()->setBody(Zend_Json::encode($result));
	}

	/**
	 *get shipping name from id
	 */
	private function getShippingName($shipping_method_id){
		$shipModel = Base::getModel('catalogue_shipping_methods');
		$where    = $ordModel->select()
		                     ->where('id = ?', $shipping_method_id);

		$ship = $shipModel->fetchRow($where);
		return $ship['name'];
	}

	/**
	 *get Common_Orders_Shipping row by id
	 *
	 */
	public function getShippingDetailAction(){

		$request = $this->getRequest();
		$common_orders_shipping_id = $request->getParam('id');

		$shipModel = Base::getModel('Common_Orders_Shipping');
		$shipping_detail    = $shipModel->fetchById($common_orders_shipping_id);

		$this->_helper->viewRenderer->setNoRender(true);
		$this->getResponse()->setHeader('Content-Type','text/html');
		$this->getResponse()->setBody(Zend_Json::encode($shipping_detail->toArray()));
	}
	
	/**
	 * Controller action to get user delivery address of the common user 
	 */
	public function shippingDetailAction() {

		// initialise variables
		$request  = $this->getRequest();
		$shipping_id  = $request->getParam('shipping_id');
		$ordModel = Base::getModel('Common_Orders_Shipping');
		$shipping = $ordModel->fetchById($shipping_id);
		$this->view->shipping = $shipping;

	}
	
	/**
	 * Controller action to get user delivery address of the common user 
	 */
	public function nzshippingDetailAction() {

		// initialise variables
		$request  = $this->getRequest();
		
		$common_order_shipping_ids = $request->getParam('shipping_ids');
		$common_order_shipping_ids = explode(",",$common_order_shipping_ids);
		
		$this->view->type = $request->getParam('type');
		if($request->getParam('type') == '') {
			$this->view->type = 'image';
		}
		
		$ordShpModel 	= Base::getModel('Common_Orders_Shipping');
		$where	   = $ordShpModel->select()->where('id IN (?)', $common_order_shipping_ids);
		
		$order_shipping	   = $ordShpModel->fetchAll($where);
		$this->view->order_shipping = $order_shipping;

	}
	
	public function shippingDetailsAction() {
		
		// init paramaters
		$request  = $this->getRequest();
		
		$common_order_shipping_id = $request->getParam('shipping_id');
		//$this->view->type = $request->getParam('type');
		//if($request->getParam('type') == '') {
		//	$this->view->type = 'image';
		//}
		
		$ordShpModel    = Base::getModel('Common_Orders_Shipping');
		$order_shipping = $ordShpModel->fetchById($common_order_shipping_id);
		$this->view->order_shipping = $order_shipping;

		// determine the total weight. if passed by reference use that otherwise
		// use the stored weight
		if ($request->has('reference')) {
			$reference = $request->getParam('reference');
			$this->view->total_weight = sprintf("%.2f",($reference / 1000));
			
		} else {
			$this->view->total_weight = $order_shipping->storedWeightInKg();
		}


		
		// get eparcel class
		$this->view->eparcel = $order_shipping->eparcel();
	}
	
	/**
	 * manually fulfil an order. set the order status to SENT and set the attached items
	 * to NULL status (which will inherit the SENT status from the order).this is an
	 * Note this is an admin tool and will not follow any automated processes triggered
	 * by status changes. This will just set the status records of the order and items
	 */
	public function fulfilAction() {
		
		// init paramaters
		$request = $this->getRequest();
		
		// make sure that the logged in user is an admin
		if (!$this->loggedInUser()->isSuperAdmin())
			throw new Exception('Requires super admin to perform task.');
		
		// get the order records
		$order_id = $request->getParam('id');
		$coModel  = Base::getModel('Common_Orders');
		$order    = $coModel->fetchById($order_id);
		if (!$order) throw new Exception('Trying to fulfill and invalid order.');
		
		
		// set the items to SENT status records first
		foreach ($order->items() as $item) {
			$item->status = 'SENT';
			$item->save();
		}
        
        // set the order status
		$order->status = 'SENT';
		$order->save();
        
        $this->addFlash('Manually fulfilled order', 'notice');
        $this->_redirect('/common/orders/edit/id/' . $order->id, array('exit' => true));
	}
	
	public function duplicateAction() {
		// init paramaters
		$request = $this->getRequest();
		
		$user    = $this->loggedInUser();
		
		// get the order records
		$order_number = $request->getParam('order_number');
		if($order_number){
			$ordModel = Base::getModel('Common_Orders');
			$select = $ordModel->select()->where('order_number = ?', $order_number); 
			
			$order    = $ordModel->fetchRow($select);
			if (!$order) {
				$this->addFlash('Invalid order', 'errors');
				return;
			}
			//get available delivery methods
			$this->view->order = $order;
			$order_shipping = $order->shipping()->toArray();
			$order_shipping_first = $order_shipping[0]['id'];
			$order_shipping_first_method_id = $order_shipping[0]['catalogue_shipping_method_id'];
			$this->view->currency_code = $order->sub()->currency_code;
			
			$cart  = Modules_Client_Cart::getInstance();
			$cart->setPublic();
			$cart->setStorage('session');
			$cart->setSub($order->sub_id);
			$cart->setCommonUserId($order->common_user_id);
			$duplicate_order_params= $cart->getDuplicateOrderParams();
			if(isset($duplicate_order_params['common_order_number'])){
				$cart_order_number = $duplicate_order_params['common_order_number'];
			}			
			
			if($cart->isEmpty() || $order_number != $cart_order_number ){
				$cart->clear();
				$cart->setPublic();
				$cart->setStorage('session');
				$cart->setSub($order->sub_id);
				$cart->setCommonUserId($order->common_user_id);
				$cart->setDuplicateOrderParams(array('common_user_id'=>$order->common_user_id, 'common_order_number' => $order_number));
				foreach($order->items() as $item) {
					$cart->addItemViaCommonItem($item);			
				}
				$shipping_methods               = $cart->availableShippingMethods();
				$shippingData = $shipping_methods->asCheckoutData($cart);
				$match_default_shipping = false;
				foreach($shippingData['options'] as $shipping_row){
					if($order_shipping_first_method_id == $shipping_row[0]){
						$shipping['catalogue_shipping_method_id'] = $shipping_row[0];
						$shipping['common_order_id'] = 1;
						$shipping['amount'         ] = $shipping_row[3];
						$match_default_shipping = true;
						break;
					}
				}
				
				if($match_default_shipping == false){
					$shipping['catalogue_shipping_method_id'] = $shippingData['options'][0][0];
					$shipping['common_order_id'] = 1;
					$shipping['amount'         ] = $shippingData['options'][0][3];
				}
				//die();
				$cart->addShipping($shipping);
				$cart->setDuplicateOrderParams(array('common_order_shipping_id' => $order_shipping_first));
				//$cart = Modules_Client_Cart::getInstance();
				$this->_redirect('/common/orders/duplicate/order_number/'.$order_number, array('exit' => true));
				return;
			}
			
			$shipping_methods               = $cart->availableShippingMethods();
			$shippingData = $shipping_methods->asCheckoutData($cart);
			$this->view->shipping_json      = Zend_Json::encode($shippingData);
			
			foreach($cart->items() as $i => $item):
				$cart_items[$item->itemId()] = $item;
				//echo $item->itemId().'-';
			endforeach;
			$this->view->cart_items = $cart_items;
			
			$duplicate_order_params= $cart->getDuplicateOrderParams();
			if(isset($duplicate_order_params['common_order_shipping_id'])){
				 $this->view->common_order_shipping_id = $duplicate_order_params['common_order_shipping_id'];
			}
			$ship_options_with_price = array();
			foreach($shippingData['options'] as $shipData){
				$ship_options_with_price[$shipData[0]] =  $shipData[1];
			}
			$this->view->shipping_methods   = $ship_options_with_price;
		
			$this->view->cart = $cart;
		}
	}
	
	
	
	public function updateCartCommonItemsAction($order){
		// init paramaters
		$request = $this->getRequest();
		
		$user    = $this->loggedInUser();
		
		// get the order records
		$order_number = $request->getParam('order_number');
		if($order_number){
			$ordModel = Base::getModel('Common_Orders');
			$select = $ordModel->select()->where('order_number = ?', $order_number); 
			
			$order    = $ordModel->fetchRow($select);
			if (!$order) {
				$this->addFlash('Invalid order', 'errors');
				return;
			}
		}
		$common_order_shipping_id = (int)$request->getParam('common_order_shipping_id');
		$cart  = Modules_Client_Cart::getInstance();
		//$cart->setPublic();
		$cart->setStorage('session');
		$cart->setCommonUserId($order->common_user_id);
		$cart->setDuplicateOrderParams(array('common_user_id'=>$order->common_user_id, 'common_order_number' => $order_number, 'common_order_shipping_id' => $common_order_shipping_id));
			
		//remove all items from cart
		foreach($cart->items() as $i => $item):
			$cart->removeItem($item->index());			
		endforeach;
				
		//Add in cart
		$itmModel = Base::getModel('Common_Items');
		$common_items = $request->getParam('common_item');
		$copies = $request->getParam('copies');
		foreach($common_items as $k =>$common_item_id):			
			$item    = $itmModel->fetchById($common_item_id);
			$cart->addItemViaCommonItem($item, $copies[$common_item_id]);
			//echo '<hr>'.$common_item_id.'-'. $copies[$common_item_id] .'<br>';
		endforeach;
		
		
		$catalogue_shipping_method = $request->getParam('catalogue_shipping_method');
		echo $catalogue_shipping_method_id = $catalogue_shipping_method[$common_order_shipping_id];
		if($catalogue_shipping_method_id > 0 ){
			$shipping['catalogue_shipping_method_id'] = $catalogue_shipping_method_id;
			$shipping['common_order_id'] = 1;
			$shipping['amount'         ] = 0;
			$cart->addShipping($shipping);
			$cart = Modules_Client_Cart::getInstance();
		}
		
		if((int)$request->getParam('priority') != (int)$cart->isPriority()){
			$priority = $request->getParam('priority') == 1 ? true : false;
			$cart->setPriority($priority);
		}
		
		$create_order = $request->getParam('create_order');
		if($create_order != ''){
			$this->_redirect('/common/orders/create-duplicate-order/order_number/'.$order_number, array('exit' => true));
		}else{
			$this->_redirect('/common/orders/duplicate/order_number/'.$order_number, array('exit' => true));
		}
		return;
	}
	
	function createDuplicateOrderAction(){
		// init paramaters
		$request = $this->getRequest();
		
		$user    = $this->loggedInUser();
		
		// get the order records
		$order_number = $request->getParam('order_number');
		
		if($order_number){
			$ordModel = Base::getModel('Common_Orders');
			$select = $ordModel->select()->where('order_number = ?', $order_number); 
			
			$order    = $ordModel->fetchRow($select);
			if (!$order) {
				$this->addFlash('Invalid order', 'errors');
				return;
			}
		}
		
		$cart  = Modules_Client_Cart::getInstance();		
		$cart->setStorage('session');
		$cart->setCommonUserId($order->common_user_id);
		$duplicate_order_params= $cart->getDuplicateOrderParams();
		if(isset($duplicate_order_params['common_user_id'])){
			$cuModel = Base::getModel('Common_Users');				
			$common_user = $cuModel->fetchById($duplicate_order_params['common_user_id']);
		}
		$order_id    = $cart->toOrder();
		
		
		
		if($common_user->order_notify == 1){
			
			$common_user->confirmemail($order_id);
		}
		//$this->log()->log('Order ID: ' .$order_id . ' : ' . $_SERVER['HTTP_USER_AGENT'] , Zend_Log::INFO);
		
		// clear the cart here
		$cart->clear();
		
		// redirect to payment option page
		$this->_redirect('/common/orders/edit/id/' . $order_id, array('exit' => true));
	}
}
