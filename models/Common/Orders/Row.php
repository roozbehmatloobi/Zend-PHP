<?php
/**
 *
 * @package    Common
 * @subpackage Common_Models
 */

/**
 * Include requred classes
 */
require_once 'Base/Db/Table/Row.php';

/**
 * Handles all aspect of a single order
 *
 * @package    Common
 * @subpackage Common_Models
 */
class Common_Orders_Row extends Base_Db_Table_Row {

	private $user;
	protected $_items;
	private $sub;

	protected $_adjustments = null;
	protected $_shipping = null;
	protected $_payments = null;

	protected $_shippingMethods = null;

	/**
	 * Array of all possible statuses
	 */
	protected $_statuses = array('PENDING', 'ACTIVE', 'HOLD', 'CANCELLED', 'RTF', 'SENT');

	/**
	 * Returns the assosiated Common User this order is done by
	 *
	 * @return object $this->user Common_Users_Row
	 */
	public function getUser() {

		if(empty($this->user)) {
			$users = Base::getModel('Common_Users');
			$this->user = $users->fetchRow('id = '.$this->common_user_id);
		}

		return $this->user;
	}

	public function commonUser() {
		return $this->getUser();
	}

	/**
	 * Returns the current items for this order
	 *
	 * @return object Common_Items_Rowset Rowset of common items for this order
	 */
	public function items($reload = false) {

		if(!$this->_items || $reload == true) {
			$itmModel = Base::getModel('Common_Items');
			$where    = $itmModel->select()
			                     ->where('common_order_id = ?' , $this->id)
								 ->order('common_order_shipping_id');
			
			$this->_items = $itmModel->fetchAll($where);
		}

		return $this->_items;
	}
	
	/**
	 * Returns the current sum items units for this order
	 *
	 * @return integer $sum_items_unit Units for this order
	 */
	public function itemsUnitSum() {
		
		$sum_items_unit = 0;

		foreach($this->items() as $item) {
			$sum_items_unit += $item->copies;
		}
		
		return $sum_items_unit;
	}

	/**
	 * retuns the item amount
	 *
	 * @return float $item_amount The total item amount
	 * @deprecated use item_amount property instead
	 */
	public function itemAmount() {
		return $this->item_amount;
	}

	/**
	 * Returns the sub object the order currently belongs to
	 *
	 * @return object $sub Subs_Row
	 * @todo Should be using Zend reference map. Better performace??
	 */
	public function sub() {

		if(empty($this->sub)) {
			$subModel  = Base::getModel('Subs');
			$where     = $subModel->select()->where('id = ?', $this->sub_id);
			$this->sub = $subModel->fetchRow($where);
			
			if($this->hasDisneyItem()){
				$sub_name = "CONCAT(name,' (Disney)') as name";
				$select   = $subModel->select()->setIntegrityCheck(false)
						    ->from(array('s' => 'subs'), new Zend_Db_Expr("*, $sub_name"))					
						    ->where('s.id = ?', $this->sub_id);
				$this->sub = $subModel->fetchRow($select);
			}
		}

		return $this->sub;
	}

	/**
	 * Returns the list of payments assigned to this order
	 */
	public function payments($catalogue_payment_method_id = null) {

		if (!$this->_payments) {
			
			if ($this->nz_id > 0) {
				$paymentsModel = Base::getModel('Common_Payments_Alternate');
			} else {
				$paymentsModel = Base::getModel('Common_Payments');
			}
			
			$where         = $paymentsModel->select()->where('common_order_id = ?', $this->id);
			if($catalogue_payment_method_id != null){
				$where->where('catalogue_payment_method_id = ? ',$catalogue_payment_method_id);
			}
			$payments      = $paymentsModel->fetchAll($where);

			$this->_payments = $payments;
		}

		return $this->_payments;
	}

	/**
	 * Returns the total amount of payment made to date
	 *
	 * @return float $payment_amount Total payment amount made
	 */
	public function paymentAmount() {

		$payment_amount = 0.00;
		foreach($this->payments() as $payment) {
			$payment_amount += $payment->amount;
		}

		return $payment_amount;
	}

	/**
	 * Returns shipping methods records for this order
	 *
	 * @return object $shipping Common_Orders_Shipping_Rowset object
	 */
	public function shipping($order = null) {

		if (!$this->_shipping) {
			if ($this->nz_id > 0) {
				$shipModel = Base::getModel('Common_Orders_Shipping_Alternate');
			} else {
				$shipModel = Base::getModel('Common_Orders_Shipping');
			}
			$select    = $shipModel->select()->where('common_order_id = ?', $this->id);
			if($order != null)
				$select->order('id '. $order);
			
			$shipping  = $shipModel->fetchAll($select);

			$this->_shipping = $shipping;
		}

		return $this->_shipping;
	}

	/**
	 * Returns the total amount for the shipping
	 *
	 * @return string $amount The total shipping amount
	 */
	public function shippingAmount() {

		$amount = 0.00;
		foreach($this->shipping() as $row) {
			$amount += $row->amount;
		}

		return $amount;
	}
	
	public function shippingCost() {

		$amount = 0.00;
		foreach($this->shipping() as $row) {
			$amount += $row->cost;
		}

		return $amount;
	}

	/**
	 * Returns the due date in a readable format
	 *
	 * @return string $date_string Readable date string
	 */
	public function readableDateDue() {

		return $this->date_due ? date('d-M-Y', strtotime($this->date_due)) : 'n/a';
	}

	/**
	 * Returns the number of days till the due date
	 *
	 * @return integer $days Number of days
	 */
	public function daysToDue() {

		if (!$this->date_due) return 'n/a';

		$date_now = getdate(strtotime("now"));
		$date_due = getdate(strtotime($this->date_due));

		// Now recreate these timestamps, based upon noon on each day. The
		// specific time doesn't matter but it must be the same each day
		$a_new = mktime( 12, 0, 0, $date_now['mon'], $date_now['mday'], $date_now['year'] );
		$b_new = mktime( 12, 0, 0, $date_due['mon'], $date_due['mday'], $date_due['year'] );

		// Subtract these two numbers and divide by the number of seconds in a
		// day. Round the result since crossing over a daylight savings time
		// barrier will cause this time to be off by an hour or two.
		$days = round( abs( $a_new - $b_new ) / 86400 );

		if ($a_new > $b_new) $days = (0 - $days);

		return $days;
	}

	public function daysToDueNice($font_size = '18px', $color_good = '#18C700', $color_bad = '#BB0000') {

		$days_to_due = $this->daysToDue();
		$format      = "<span style=\"font-size:%s; color:%s; font-weight:bold;\">%s%s</span>";

		switch(true) {
			case strtoupper($this->status()) == 'CANCELLED':
				break;
			case strtoupper($this->status()) == 'SENT':
				//return sprintf("", $font_size, $color_good, null, 'Sent');
				break;
			case $days_to_due === 'n/a':
				return sprintf($format, $font_size, $color_good, null, $days_to_due);
				break;
			case $days_to_due >= 15:
				return sprintf($format, $font_size, 'green', '+', abs($days_to_due)); //#00FF00
				break;
			case $days_to_due >= 8:
				return sprintf($format, $font_size, 'blue', '+', abs($days_to_due)); //#0000FF
				break;
			case $days_to_due >= 1:
				return sprintf($format, $font_size, 'magenta', '+', abs($days_to_due)); //#FF00FF
				break;
			case $days_to_due == 0:
				return sprintf($format, $font_size, 'red', null, abs($days_to_due)); //#FF0000
				break;
			case $days_to_due < 0:
				return sprintf($format, $font_size, 'red', '-', abs($days_to_due)); //#FF0000
				break;
			default:
				return sprintf($format, $font_size, $color_bad, '-', abs($days_to_due));
				break;
		}
	}

	/**
	 * update the order status logging the activity
	 *
	 * @param string $status The new order status to change
	 * @param string $note Any note to describe the status change
	 * @param int $user_id The user ID making the changes
	 * @return boolean True on success, otherwise false
	 */
	public function changeStatus($status, $note, $user_id) {
		
		if ($this->status() == 'CANCELLED' || $this->status() == 'HOLD') {
			$last_active_state = $this->lastActiveState();
			if (empty($last_active_state) || $last_active_state == 'PENDING') {
				$this->status = 'PENDING';
				$this->save();
				$status = 'ACTIVE';
			} else {
				$status = $last_active_state;
			}
		}
		
		$current_status = $this->status;
		
		// validate, make sure that the status is a valid one
		if($status == 'ACTIVE' && (empty($this->date_first_active) || $this->date_first_active == '' || $this->date_first_active == '0000-00-00 00:00:00')){
                        $now = date('Y-m-d H:i:s');
                        $this->date_first_active = $now;
                }
		
		$this->status = $status;
		
		$this->save();
		
		// log the change
		$this->logStatusChange($status, $note, $user_id);
		
		// trigger any status changes
		$this->changeStatusEvent($current_status, $status);

		
				
		if($current_status == 'PENDING' && $this->status == 'ACTIVE' && $this->commonUser()->order_notify == 1){
			$status = 'HOLD';
			$this->status = 'HOLD';
			$this->save();
			$this->logStatusChange($status, 'Automatic HOLD status for order till notify.', $user_id);
		}

		return true;
	}
	
	/**
	 * previous state that is not 'CANCELLED' OR 'HOLD'
	 */
	public function lastActiveState() {
		
		$oaModel = Base::getModel('Common_Orders_Activity');
		$select  = $oaModel->select()
		                   ->where('common_order_id = ?', $this->id)
		                   ->where('status != ?','CANCELLED')
		                   ->where('status != ?','HOLD')
		                   ->order('created DESC');

		 $status = $oaModel->fetchRow($select);
		if ($status) {
			return $status->status;
		} else {
			return null;
		}
	}

	/**
	 * Perform task when a status changes
	 */
	public function changeStatusEvent($current_status, $new_status) {
		
		if (($current_status == 'PENDING' || $current_status == 'CANCELLED') && $new_status == 'ACTIVE') {

			// set the due dates
			$this->setDueFromNow();
			
			// trigger BPM for the order items
			$prodPathModel = Base::getModel('Bpm_Production_Paths');
			$itmpartModel  = Base::getModel('Bpm_Item_Parts');
			foreach($this->items() as $item) {
				
				// assure that the item only get started once OR not started again
				// we only want to change the one from pending which could be the
				// item status OR inherited order status
				if ($item->status() == 'ACTIVE') {
					
					if ($item->isGiftPack()) $item->assignGiftPackDiscount();
					
					// handle giftpacks to be sent via PDF
					if ($item->isGiftPack() && $item->hasOption(601)) {
						
						// send PDF email
						$item->emailGiftpack();
							
						// create new item note
						$ntModel   = Base::getModel('Notes');
						$note_data = array(
							'user_code'  => SYSTEM_USER_ID,
							'table_name' => 'common_items',
							'note'       => 'PDF voucher sent via email.',
							'table_id'   => $item->id,
						);
						$created = $ntModel->process($note_data, 'insert');
						
						$item->changeStatus("SENT", "Auto change item status to SENT. Auto Giftpack sent via email.", SYSTEM_USER_ID);						
					
					} elseif (in_array($item->client_product_id, array(1, 2, 3, 4, 6, 7, 8, 9, 10)) || ( $item->client_product_id == 5 && ($item->catalogue_binding_id == 51 || $item->catalogue_binding_id == 61)) ) { //41 = Printed Clam-shell
						$item->changeStatus("RTF", "Auto change item status to RTF for special product", SYSTEM_USER_ID);
					
					} else {
						$item->startBpm();
					}
				}
			}
		
		//} elseif ($current_status == 'CANCELLED' && $new_status == 'ACTIVE') {
		//	// this is for migrated orders
		//	if (empty($order->date_due)) $this->setDueFromNow();
			
			
			

		} elseif ($new_status == 'HOLD') {

			// things to do when held

		} elseif ($new_status == 'CANCELLED') {

			// things to do when the order is cancelled

		}
	}

	/**
	 * Set the due date from now. Uses the value in days completion field.
	 */
	protected function setDueFromNow() {

		// we only want to set the date due IF it's not yet set. #907
		if (empty($this->date_due)) {
			$new_due_date = date('Y-m-d', strtotime('+' . $this->completionDays() . ' days'));
			$this->changeDue($new_due_date, 'Auto system call from PENDING to ACTIVE', SYSTEM_USER_ID);
		}

		return;
	}

	/**
	 * Change the due date of the order
	 *
	 * @param string $date The date in YYYY-MM-DD format
	 * @param string $note Note to acompany the activity history
	 * @param int $user_id The admin user ID
	 */
	public function changeDue($date, $note, $user_id) {

		// validate, make sure that the status is a valid one
		$old_date = $this->date_due;
		$this->date_due = $date;
		$this->save();

		// log the change
		if($old_date == null || $old_date == ''){
			$note = 'New due date set ' . date("d M Y", strtotime($this->date_due)) . '.<br />' . $note;
		}else{
			$note = 'Due Date changed from ' . date("d M Y", strtotime($old_date)) . ' to ' . date("d M Y", strtotime($this->date_due)) . '.<br />' . $note;
		}
		$this->logStatusChange($this->status(), $note, $user_id);

		return true;
	}

	/**
	 * log the status change activity
	 *
	 * @param string $status The new order status to change
	 * @param string $note Any note to describe the status change
	 * @param int $user_id The user ID making the changes
	 * @return boolean True on success, otherwise false
	 */
	public function logStatusChange($status, $note, $user_id) {

		// prepare new activity data
		$data = array(
			'common_order_id' => $this->id,
			'user_id'         => $user_id,
			'status'          => $status,
			'note'            => $note,
		);
		
		$cosModel = Base::getModel('Common_Orders_Activity');
		$created  = $cosModel->process($data, 'insert');

		return true;
	}


	/**
	 * Returns a list of ordered activity on this order
	 *
	 * @return object $activity Common_Orders_Activity_Rowset object
	 */
	public function activity() {

		$activityModel = Base::getModel('Common_Items_Activity');
		$select        = $activityModel->select()
		                               ->setIntegrityCheck(false)
					       ->from(array('cia' => 'common_items_activity'), array('id', 'common_item_id', 'user_id', 'status', 'note', 'created', new Zend_Db_Expr("'Item' as flag")))
					       ->where('common_item_id in (?)', new Zend_Db_Expr("(SELECT id FROM common_items WHERE common_order_id = $this->id)"));
		
		$sql =  "SELECT id, common_order_id, user_id, status, note, created, 'Order' as flag FROM common_orders_activity WHERE common_order_id = $this->id";

		$select      = $activityModel->select()->union(array($select, $sql))->order("created DESC")->order("id DESC");
		//Zend_Debug::dump((string)$select); die();
		$activity =  $activityModel->fetchAll($select);
		return $activity;
	}

	/**
	 * Returns a rowset object of all adjustment made on this order
	 *
	 * @return object $adjustments Common_Orders_Adjustments_Rowset
	 */
	public function adjustments() {

		if (!$this->_adjustments) {
			$adjModel    = Base::getModel('Common_Orders_Adjustments');
			$select      = $adjModel->select()->where('common_order_id = ?', $this->id)->order('created DESC');
			$adjustments = $adjModel->fetchAll($select);

			$this->_adjustments = $adjustments;
		}

		return $this->_adjustments;
	}

	/**
	 * Returns the adjustment amount. Note that this now gets handled by the database using trigger
	 *
	 * @return float $amount The total adjustment amount
	 */
	public function adjustmentAmount() {
		return $this->adjustment_amount;
	}

	public function paymentDueAmount() {
		return $this->payment_due;
	}

	/**
	 * Returns the tax amount of an order. This is a partial fix and assumes that all orders has GST component
	 * Refer to #450.
	 *
	 * return float $tax_amount;
	 */
	public function taxAmount() {
		$gst_percent = (int)$this->sub()->gst;
		return sprintf("%.2f", ($this->total * ($gst_percent/(100+$gst_percent))) );
	}

	/**
	 * Returns the country name from country id
	 *
	 */
	public function getCountry($countryId='') {

		if($countryId) {
			$country = Base::getModel('Countries');
			return $country->fetchById($countryId)->name;
		}
	}

	/**
	 * Returns the payment methods for this sub
	 *
	 * @param boolean $include_inactive OPTIONAL. State weather to include inactive payment methods
	 * @return object Catalogue_PaymentMethods_Rowset
	 */
	public function paymentMethods($include_inactive = false) {

		$ordModel = Base::getModel('Catalogue_PaymentMethods');
		$select   = $ordModel->select()
		                     ->where('sub_id = ?', $this->sub_id)
				     ->order('sort_order ASC');

		// if to inlcude the inactives
		if ($include_inactive != true) {
			$select->where('active = ?', 1);
		}

		return $ordModel->fetchAll($select);
	}

	/**
	 * Returns the payment methods  name from payment method id
	 *
	 */
	public function paymentMethodName($payment_method_id) {
		$cpmModel = Base::getModel('Catalogue_PaymentMethods');
		return $cpmModel->fetchById($payment_method_id)->name;
	}

	/**
	 * add the payment record in table common_payments
	 * @param float $amount paid amount
	 * @param string $status 'paid'
	 * @param string $reference Any reference to describe the payment
	 * @return int $payment_method_id is represent catalogue_payment_method_id (paypal, fax print, credit account)
	 */
	public function addPayment($payment_method_id, $amount, $status = 'paid', $reference='') {
		$created = date('Y-m-d H:i:s');
		// prepare new payment data
		$data = array(
			'common_order_id' 		=> $this->id,
			'catalogue_payment_method_id' 	=> $payment_method_id,
			'amount'         		=> $amount,
			'status'		     	=> $status,
			'reference'            		=> $reference,
			'created'			=> $created
		);

		$cosModel = Base::getModel('Common_Payments');
		$created  = $cosModel->process($data, 'insert');
		return $cosModel->getId();
	}

	/**
	 * get email content
	 *
	 */
	public function emailContent($id) {
		$emailContentModel = Base::getModel('Common_Emailcontent');
		$where    = $emailContentModel->select()
		                     ->where('id = ?', $id);

		return $emailContentModel->fetchRow($where);
	}

	/**
	 * log the email
	 *
	 */
	public function sendEmailData($aData) {

		// prepare new activity data

		$emailDataModel = Base::getModel('Common_EmailSent');
		$created  = $emailDataModel->process($aData, 'insert');

		return true;
	}

	/**
	 *Get all shipping method for this sub
	 *
	 */
	public function shippingMethods(){
		$shipModel    = Base::getModel('Catalogue_ShippingMethods');
		$select      = $shipModel->select()->where('sub_id = ?', $this->sub_id)
						   ->where('active = ?', 1)
						   ->order('name ASC');
		$shippingMethods = $shipModel->fetchAll($select);
		foreach($shippingMethods as $shippingMethod) {
			$shipping_options[$shippingMethod['id']] = $shippingMethod['name'];
		}
		return $shipping_options;
	}


	/**
	 *Get all countries
	 */
	public function countires() {

		$countriesModel = Base::getModel('Countries');
		$countries      = $countriesModel->fetchAll();
		foreach($countries as $country) {
			$countries_options[$country['id']] = $country['name'];
		}
		return $countries_options;
	}


	/**
	 * Cancel this order. This is mainly used in the client area where the client themselves
	 * has requested to cancel the order (client orders index).
	 * NOTE: this method assumes that you have verified that the user has access to this order
	 *
	 * @return boolean True on success, otherwise false
	 */
	public function cancel() {

		// we need to use the system user for the log activity as the request is
		// comming from the client, which is NOT a system user
		$config  = Zend_Registry::get('config');
		$user_id = 3; //$config->app->system_user_id;

		$this->changeStatus('CANCELLED', 'User request cancellation.', $user_id);

		return true;
	}

	/**
	 * Return the current status of the order
	 *
	 * @return string $status The current status of the order
	 */
	public function status() {
		return $this->status;
	}

	/**
	 * Returns true if all the items in the order are RTF
	 */
	public static function isAllItemStatusRTF() {
	}

	/**
	 * Returns the order number in a human readable format. Basically just adding a prefix 'M'
	 *
	 * @return string $human_readable A human readable order number string
	 */
	public function humanReadableOrderNumber() {
		return 'M' . $this->order_number;
	}

	public function barcodeHtml() {
		$value ='Free 3 of 9';
		$fontfamily ='font-family';
		$html='<span style="font-family:\'Free 3 of 9\';font-size:44px;">*'.$this->humanReadableOrderNumber().'*</span>';
		return $html;
	}

	public function hasNotes() {
		return $this->notes() ? true : false;
	}

	/**
	 * Return the notes that belongs to this item
	 *
	 * @param $linebreak OPTIONAL line breaks between notes. Defaults to <br />
	 * @return string $notes
	 */
	public function notes($linebreak = '<br />') {

		$noteModel = Base::getModel('Notes');
		$where	   = $noteModel->select()
		                       ->where('table_id  = ?', $this->id)
							   ->where('table_name = ?', 'common_orders');

		$notes = $noteModel->fetchAll($where);
		$note_string = '';
		foreach($notes as $note) {
			$note_string .= date('d-M-y h:ia', strtotime($note->date)) . ' ' . $note->user()->fullname() . ': ' . htmlspecialchars(nl2br($note->note)) . $linebreak;
		}

		return $note_string;
	}
	
	function itemStatusDominant(){
		if($this->status == 'ACTIVE') {
			foreach($this->items() as $item) {
				if(in_array($item->status, array('RTF','HOLD','CANCELLED'))){
					return "(Some Items not ACTIVE)";
				}
			}
		}
		
		return false;
	}

	/**
	 * Return the current status name mapped to a status colour
	 *
	 * @return string $html HTML string of the current status in specified colour
	 */
	public function statusColor() {
		
		// define the status colours
		$status_colour_map = array(
			'PENDING'   => 'blue',
			'ACTIVE'    => 'green',
			'RTF'       => 'magenta',
			'HOLD'      => 'orange',
			'CANCELLED' => 'red',
		);
		
		$status = $this->status();
		$colour = isset($status_colour_map[$status]) ? $status_colour_map[$status] : 'black';
		$html   = "Order <span style=\"color:{$colour}\">{$status}</span>";
		
		return $html;
	}
	
	/**
	* call mysql procedure to delete order .
	* Probably call from 'library\Modules\Client\Cart.php'
	* delete from common_items_activity, common_items_options, common_items,
	* common_orders_adjustments, common_orders_shipping, common_payments, 	common_orders
	* *****IF you edit any relation ship on DB please update "deleteCommonOrder"
	*/
	public function delete(){
		$sql = "CALL deleteCommonOrder($this->id)";

		$db = Zend_Registry::getInstance()->get('db');
		$stmt = $db->query($sql);
	}
	
	/**
	 * Returns the date and time when the order was first set to ACTIVE status
	 *
	 * @return string $date MySQL date and time format
	 */
	public function firstActiveStatusDate() {
		
		$coaModel = Base::getModel('Common_Orders_Activity');
		$select   = $coaModel->select()
		                     ->where('common_order_id = ?', $this->id)
		                     ->where('status = ?', 'ACTIVE')
		                     ->order('id ASC');
		
		$activity = $coaModel->fetchRow($select);
		if (!$activity) return null;
		
		return $activity->created;
	}
	
	/**
	 * Returns the first occurance date of a particular status of this order
	 *
	 * @return string $date Date string in MySQL date format
	 */
	public function firstStatusDate($status=null) {
		
		$coaModel = Base::getModel('Common_Orders_Activity');
		$select   = $coaModel->select()
		                     ->where('common_order_id = ?', $this->id)
		                     ->order('id ASC');
		
		$activity = $coaModel->fetchRow($select);
		if (!$activity) return null;
		
		return $activity->created;
	}
	
	/**
	 * Returns the last occurance date of a particular status for this order
	 *
	 * @return string $date Date string in MySQL date format
	 */
	public function lastStatusDate($status) {
	}
	
   public function lastStatusChange() {
	   $coaModel = Base::getModel('Common_Orders_Activity');
	   $select   = $coaModel->select()
		                    ->where('common_order_id = ?', $this->id)
							->where('status = ?', $this->status())
		                    ->order('id DESC');
							
	   $activity = $coaModel->fetchRow($select);
	   return $activity;
   }

	/**
	 * Return the last status change date and Due date if order is ACTIVE or HOLD or RTF
     */
   public function orderStatusDescription(){
	  
	   $html = $this->statusColor();
	   if($this->status =='ACTIVE' || $this->status =='HOLD'|| $this->status =='RTF') {
		   $html .=  '<br><span>Start: </span>'
				 . date('d-M-Y', strtotime($this->lastStatusChange()->created))
				 . '<br>Due&nbsp;:&nbsp;'.date('d-M-Y', strtotime($this->date_due));
		}

		return $html;
   }

   public function orderFirstStatus() {
		$coaModel = Base::getModel('Common_Orders_Activity');
		$select   = $coaModel->select()
		                     ->where('common_order_id = ?', $this->id)
							 ->where('status =?', $this->status)
			                 ->order('id ASC');
		
		$activity =	$coaModel->fetchRow($select);
		return $activity;
   }

   public function activatedAndDue($format='d-M-y') {

	  $activated_and_due_dates = array();

	   if ($this->firstActiveStatusDate()) {
				$activated_and_due_dates['Started'] = date($format, strtotime($this->firstActiveStatusDate()));
				$activated_and_due_dates['due'    ] = date($format, strtotime($this->date_due));
	   }else{
		   return null;
	   }

	   return $activated_and_due_dates;
   }
	
  /**
   * Returns firstShipping method for this order
   * 
   * return the first row of common_order_shipping
   */
   public function firstShipping() {
	  
		   $shipModel = Base::getModel('Common_Orders_Shipping');
		   $select    = $shipModel->select()->where('common_order_id = ?', $this->id)->order('id ASC');
		   $firstshipping  = $shipModel->fetchRow($select);
			
		   return $firstshipping;
   }
   
	/**
	* change the sub for this user. we now handle changing of sub via the model to allow
	* us to make other processes. E.g. moving assets. etc...
	*/
       public function changePriorityLog($new_priority, $old_priority, $new_watch, $old_watch) {	       
	       
	       $note='';
	       if ($new_priority != $old_priority){
			$note = $note.'Changed Order priority from <em>' . ((int)$old_priority == 1? 'Priority':'Non-priority')  . '</em> to <em>' . ((int)$new_priority == 1? 'Priority':'Non-priority') . '</em>';
	       }
	       if ($new_watch != $old_watch){
			$note = $note.'Changed Order watch from <em>' . ((int)$old_watch == 1? 'Watch':'Non-watch')  . '</em> to <em>' . ((int)$new_watch == 1? 'Watch':'Non-watch') . '</em>';
	       }
	       // log this event in the user's notes. create the data to be used to create the new note
	       $note = array(
		       'user_code'  => Base::loggedInUser()->id,
		       'table_name' => 'common_orders',
		       'table_id'   => $this->id,
		       'note'       => $note,
	       );

	       // insert the new note
	       $notesModel = Base::getModel('Notes');
	       $created    = $notesModel->process($note, 'insert');
	       
	       return;
       }

	   public function seasonicon() {

		   $html = '';
		   if ($this->sub()->seasonicon_status 
			   && $this->date_due <= $this->sub()->seasonicon_cutoff_date . '23:59:59'
		       && $this->status()!='PENDING' && $this->status()!='CANCELLED' && $this->status()!='SENT') 
			   $html = $this->sub()->previewIcon();			   
		
		   return $html;
	   }
	   
	
	/**
	 * Returns the total copies of the items in the order
	 */
	public function itemsCopies() {
		
		$copies = 0;
		foreach($this->items() as $item) {
			$copies += $item->copies;
		}
		
		return $copies;
	}
	/**
	  * Enhance the orders column in all list views (eg Orders list, Items list, drill downs etc)
	  * So it always shows the last relevant date
	  */ 

		public function lastReleventDate(){
	
			$html ='<div class="left">';
			switch($this->status()) {
					case 'PENDING':
						$html .= '<span><b>Submitted:</b> <span class="dt">'. date('d-M-y h:ia', strtotime($this->date_created)).'</span></span>';
						break;

					case 'ACTIVE':
						$html .= '<span><b> Started: </b> <span class="dt">'
								.date('d-M-y h:ia', strtotime($this->firstActiveStatusDate()))
								.'</span></span><br>'
								.'<span><b> Due: </b>'.date('d-M-y', strtotime($this->date_due))
								.'</span>';
						break;

					case 'RTF':
						$html .= '<span><b> Started: </b> <span class="dt">'
								.date('d-M-y h:ia', strtotime($this->firstActiveStatusDate()))
								.'</span></span><br>'
								.'<span><b> Due: </b>'.date('d-M-y', strtotime($this->date_due))
								.'</span>';
						break;

					case 'HOLD':
						$html .= '<span><b>Started:</b> <span class="dt">'
								.date('d-M-y h:ia', strtotime($this->firstActiveStatusDate()))
								.'</span></span><br>'
								.'<span><b>Due:</b> '
								.date('d-M-y', strtotime($this->date_due))
								.'</span><br>'
								.'<span><b>Held:</b> <span class="dt">'
								.date('d-M-y h:ia', strtotime($this->lastStatusChange()->created))
								.'</span></span>';
						break;	
					
					case 'SENT':
						if ($this->sub_id == 1 || $this->sub_id == 2) {
						$sent_date = $this->lastStatusChange() ? date('d-M-y h:ia', strtotime($this->lastStatusChange()->created)) : null;
						$html .='<span><b> Started: </b> <span class="dt">'
								.date('d-M-y h:ia', strtotime($this->firstActiveStatusDate()))
								.'</span></span><br>'
								.'<span><b>Sent:</b> <span class="dt">'
								.$sent_date
								.'</span></span>';
						}
						break;

				}

				$html.='</div>';
				return $html;
			}

	/**
	 * @todo Figure out if this method is needed. 
	 */
	public function templates() {
       
		// init variables
		$templates = array();
		$temp_path = APPLICATION_PATH . '/../sites/' . $this->commonUser()->sub()->code . '/templates/email';
		
		// test first to see if the directory exists
		if (is_dir($temp_path)) {

			// iterate each content of the directory and get all the templates
			$dh = opendir($temp_path);
			while ($filename = readdir($dh)) { 
				if (preg_match('/^[^.{1,2}](.*)(\.phtml)$/', $filename)) {  
					$file_path = $temp_path . DS . $filename;
					$templates[$filename] = $filename; 
				}
			}
			closedir($dh);
		}

		return $templates;
	}
	
	
	/**
	 * Returns the sum of the previous item count made before this order. This is used mainly
	 * for the custom report on orders/referralOrders
	 *
	 * @return int $sum_copies The total copies made previous to this order
	 */
	public function previousItemCopiesCount() {
		
		return 1;
		
		$common_user_id = $this->common_user_id;
		$order_id       = $this->id;
		$first_active   = $this->firstActiveStatusDate();
		
		$db  = Zend_Registry::get('db');
		$sql = "SELECT
		            SUM(ci.copies) AS ci_copies
			FROM
			    common_orders co
			    LEFT JOIN common_items ci ON (ci.common_order_id = co.id)
			    LEFT JOIN view_common_order_active_date coad ON (co.id = coad.common_order_id)
			WHERE
			    co.status NOT IN ('CANCELLED', 'PENDING')
			    AND co.common_user_id = $common_user_id
			    AND coad.date_activated <= '$first_active'
			    AND co.id != $order_id";
		
		$sum_copies = $db->fetchOne($sql);
		return $sum_copies;
	}
	
	public function paymentStatus() {
		
		if ($this->payment_due > 0){
			$status = "Unpaid";
		}elseif($this->payment_due <= 0){
			$status = "Paid";
		}elseif($this->payment_due > 0 AND $this->payment_amount > 0){
			$status = "Part Paid";
		}elseif($this->payment_due < 0){
			$status = "Over Paid";
		}
			
		return $status;
	}
	
	//return order related to discount code
	public function orderCode($code){
	    
	    $disModel = Base::getModel('Client_Discounts');
	    $select   = $disModel->select()->where(" code = ?", $code);
	    $discount = $disModel->fetchRow($select);
	    
	    return $discount;
	    
	}
	
	/**
	 * return the total order weight. does not include the markup weight
	 *
	 * @param boolean $realtime OPTIONAL Determine if to return real-time weight. default to false
	 * @return float $sum_weight The total weight of the order
	 */
	public function weight($realtime = false) {
		
		$sum_weight = 0.00;
				
		foreach($this->items() as $item) {
			$item_weight = $realtime ? $item->realTimeUnitWeight() : $item->weight;
			$sum_weight += $item_weight * $item->packcopies();
		}
		
		$sum_weight = sprintf("%.2f", $sum_weight);
		
		return $sum_weight;
	}
	
	public function totalWeight($realtime = false) {
		
		$total_weight = $this->weight($realtime) + $this->markupWeight();
		return $total_weight;
	}
	
	/**
	 * returns the calculated the total markup weight of the order
	 *
	 * @return float $sum_weight Total markup weight
	 */
	public function markupWeight() {
		
		$sum_weight = 0;

		foreach($this->items() as $item) {
			$sum_weight += $item->realTimeMarkupWeight() * $item->packcopies();
		}

		$sum_weight = sprintf("%.2f", $sum_weight);
		
		return $sum_weight;
	}
	
	/**
	 * Returns the total amount for the shipping
	 *
	 * @return string $amount The total shipping amount
	 */
	public function tempeParcelShippingAmount() {

		$amount = 0.00;
		
		foreach($this->shipping() as $row) {
			$amount += $row->tempeParcelShippingAmount();
		}
		
		$amount = sprintf("%.2f", $amount);

		return $amount;
	}
	
	/**
	 * determine if this order has a single item that weight more than 5000g.
	 *
	 * @return boolean True if has 5000g+ single item, otherwise false
	 */
	public function hasItemUnitExeceedingWeight($max_weight = 5000) {
		
		foreach ($this->items() as $item) {
            if (($item->totalRealtimeUnitWeight() > $max_weight)) return true;
		}
		
		return false;
	}
	
	public function completionDays(){
		$itemsDataSet = $this->itemsDataSet();
		$completion_days    = new Base_Completiondays();
		$data = $completion_days->calculate($itemsDataSet);
		$standard_days = $data['order']['standard'];
		$priority_days = $data['order']['priority'];
		if($this->priority == 1){
			if($priority_days > 0)
				return $priority_days;
			return $standard_days;
		}
		return $standard_days;
	}
    
	/**
	 * check if the order record is locked. For now only order ID 1183453 is locked.
	 * 
	 * @return boolean True if order is locked, otherwise false
	 */
	public function isLocked() {
	    
	    return ($this->nz_id > 0) ? true : false;
	}
    
	/**
	 * Returns a rowset object of all adjustment made on this order
	 *
	 * @return object $adjustments Common_Orders_Adjustments_Rowset
	 */
	public function tariffAdjustments() {
		//All adjustment types except for User Credits and Gift Vouchers (client_discounts where type = 'certificate' )
		$sql = "SELECT sum(amount) as amount FROM `common_orders_adjustments` AS `coa`
			  WHERE (type IN ('CarbonOffset', 'FileHandling', 'Priority', 'Discount', 'DisplayProduct', 'UserDiscount', 'VolumeDiscount'))
			  AND NOT EXISTS (SELECT 1 FROM client_discounts where type = 'certificate' AND id= coa.reference)
			  AND common_order_id = " . $this->id;

		$db  = Zend_Registry::get('db');
	   
		return sprintf("%.2f", $db->fetchOne($sql));
		
	}
	
	public function lastShipping() {
		$shippings = $this->shipping('DESC');
		foreach($shippings as $shipping) {
			return $shipping;
		}
	}
	
	public function tariff($type = 'itemArr', $amount_type = null, $as_shipable_quantity = true) {
		//$amount_type = Retail, RetailAU, WholeSale, WholeSaleAU
		//$type = itemArr, total
		
		$shipping = $this->lastShipping();
		if($amount_type == null){
			$ship_type = $shipping->ECI_Type();
			if($ship_type == 'CONSOLIDATED'){
				$amount_type = 'WholeSaleAU';
			}elseif($ship_type == 'RETAIL'){
				$amount_type = 'RetailAU';
			}
		}
		$exchange_rate = Base_Configuration::getValue('NZ_CUSTOM_CONVERSION_RATE');
		$wholesale_percent = $shipping->shippingMethod()->getConfig('WholeSalePercent');
		$adjustments =  $this->tariffAdjustments() + $shipping->amount;
		$total = 0.00;
		$itemArr = array();
		foreach($shipping->items() as $item) {
			if ($as_shipable_quantity) $copies = $item->shipableQuantity();
			else $copies = $item->copies;
				    
			$percent = $item->percentageOfOrder();
			$unit_item_amount = $item->unit_price + ($adjustments * $percent / 100);
			$copies_item_amount = sprintf("%01.2f",($unit_item_amount * $copies));
			if($amount_type == 'Retail'){
				$copies_item_amount = $copies_item_amount;
			}else if($amount_type == 'RetailAU'){
				$copies_item_amount = $copies_item_amount * $exchange_rate;
			}else if($amount_type == 'WholeSale'){
				$copies_item_amount = $copies_item_amount * ($wholesale_percent/100) ;
			}else if($amount_type == 'WholeSaleAU'){
				$copies_item_amount = $copies_item_amount * ($wholesale_percent/100) * $exchange_rate;
			}
			$copies_item_amount = sprintf("%01.2f",($copies_item_amount));
			$itemArr[$item->id] = $copies_item_amount;
			$total +=  $copies_item_amount;
		}
		
		if($type == 'total'){
			return $total;
		}else{
			return $itemArr;
		}
	}
    
	/**
	 * returns any un-used shipping records from this order. This is mainly based on any shipping that
	 * belongs to this order but not assigned to any items and not marked as sent.
	 *
	 * @return object $unusedShipping Common_Orders_Shipping_Rowset
	 */
	public function unUsedShipping() {
		    
	    // get all unused shipping
	    $cosModel = Base::getModel('Common_Orders_Shipping');
	    $select   = $cosModel->select()
				 ->where('common_order_id = ?', $this->id)
				 ->where('date_sent IS NULL')
				 ->order('id DESC');
	    
	    // define and exclude assigned item shippings
	    $db  = Zend_Registry::get('db');
	    $sql = $db->quoteInto("SELECT common_order_shipping_id FROM common_items WHERE common_order_id = ?", $this->id);
	    $item_shipping_ids = $db->fetchCol($sql);
	    if (!empty($item_shipping_ids)) $select->where('id NOT IN (?)', $item_shipping_ids);
	    
	    $shippings = $cosModel->fetchAll($select);
	    
	    return $shippings;
	}
    
	/**
	 * Returns the current items for this order
	 *
	 * @return id as comma seperated
	 **/
	public function itemList() {

		$sql = "SELECT GROUP_CONCAT(id) AS ids FROM `common_items` WHERE common_order_id = " . $this->id . " AND (status NOT IN ('CANCELLED') OR status IS NULL) GROUP BY common_order_id";

		$db  = Zend_Registry::get('db');
	   
		return $db->fetchOne($sql);
		
	}
	
	/**
	 * Returns the  shippings for this order
	 *
	 * @return id as comma seperated
	 **/
	public function shippingList() {

		$sql = "SELECT GROUP_CONCAT(id) AS ids FROM `common_orders_shipping` WHERE common_order_id = " . $this->id ;

		$db  = Zend_Registry::get('db');
	   
		return $db->fetchOne($sql);
		
	}
	/**
	 * Returns the total wholesale amount & exchange rate for this order
	 *
	 * @return wholesale_amount, exchange_rate in array 
	 **/
	public function wholesaleAmount() {
		$data = array('wholesale_amount' => '', 'exchange_rate' => '');
		if(count($this->itemList()) == 0) return $data ;

		$sql = "SELECT SUM(wholesale_amount) as wholesale_amount , `exchange_rate` FROM  `common_orders_shipping_tariff` WHERE  `common_item_id` IN ( ". $this->itemList() .")" ;
		$db  = Zend_Registry::get('db');
		return $data = $db->fetchRow($sql);
		
	}
	
	public function itemsDataSet(){
		
		//$data[index] =array('sub_id' => '',
		//		'product_line_id' => '',
		//		'size_id' => '',
		//		'binding_id' =>	'',
		//		'cover_type_id' => '',
		//		'cover_id' => '',
		//		'attr_options' => array(1,2,3),
		//		);
		$item_data = array();
		foreach($this->items() as $item) {
			$options = array();
			if ($item->options()) {
				foreach($item->options() as $option) {
					array_push($options, $option->catalogue_attribute_option_id);
				}
			}
			$item_data[$item->id] = array(
				'sub_id'                => $this->sub_id,
				'product_line_id' 	=> $item->clientProduct()->product_line_id,
				'size_id' 		=> $item->catalogue_size_id,
				'binding_id' 		=> $item->catalogue_binding_id,
				'cover_type_id' 	=> $item->catalogue_cover_type_id,
				'cover_id' 		=> $item->catalogue_cover_id,
				'attr_options'		=> $options,
				);
		}
		return $item_data;
	}

	public function saveDispatchNotes($note) {

		if($note != ''){
			$this->dispatch_notes = $note;
			$this->save();
		}
	}
	
	/**
	 * Returns true if there has any disney item in this order.
	 *
	 * @else return false
	 */
	public function hasDisneyItem() {
		
		$has_disney_item = false;

		foreach($this->items() as $item) {
			if($item->clientProduct()->software_code == 'MOD'){
				$has_disney_item = true;
			}
		}
		
		return $has_disney_item;
	}
	
	public function adjustmentsTotalGroupByType($type) {
		$sql = "SELECT SUM(abs(amount)) as amount FROM `common_orders_adjustments` WHERE  type = '".$type."' AND common_order_id =  $this->id" ;
		$db  = Zend_Registry::get('db');
		$data = $db->fetchOne($sql);
		if($data) return $data;
		return 0;
	}
	
	public function paymentByGiftvoucher(){
		$sql = "SELECT SUM(abs(amount)) as amount FROM `common_payments` WHERE  catalogue_payment_method_id IN (303,313,323,333) AND common_order_id =  $this->id" ;
		$db  = Zend_Registry::get('db');
		$data = $db->fetchOne($sql);
		if($data) return $data;
		return 0;
	}
	
	public function payment($include_gift_voucher = false){
		if($include_gift_voucher == true){
				$sql = "SELECT SUM(amount) as amount FROM `common_payments` WHERE common_order_id =  $this->id" ;
		}else{
				$sql = "SELECT SUM(amount) as amount FROM `common_payments` WHERE catalogue_payment_method_id NOT IN (303,313,323,333) AND common_order_id =  $this->id";
		}
		
		$db  = Zend_Registry::get('db');
		$data = $db->fetchOne($sql);
		if($data) return $data;
		return 0;
	}
}