<?php
/**
 * $Id: Row.php 26512 2014-12-09 03:45:36Z jahangir.alam $
 *
 * Common Users Row
 *
 * @category     Common
 * @package      Common_Controllers
 * @copyright    $Copyright$
 * @version      $Revision: 26512 $
 * @modifiedby   $LastChangedBy: jahangir.alam $
 * @lastmodified $Date: 2014-12-09 14:45:36 +1100 (Tue, 09 Dec 2014) $
 */

/**
 * Include need classes
 */
require_once 'Base/Db/Table/Row.php';
require_once('Common/Users/Credits.php');

/**
 * Row class for common users model
 *
 * @category     Common
 * @package      Common_Controllers
 * @subpackage   Common_Controllers_Row
 */
class Common_Users_Row extends Base_Db_Table_Row {

	/**
	 * Orders from this user
	 *
	 * @var object $orders Common_Orders_Rowset
	 */
	protected $_orders;
	protected $_directory;
	
	protected $_access_stations = null;
	
	protected $_shop_products;
	protected $_public_shop_products;
	
	public function isReseller() {
		return ( ($this->sub_id == 2 || $this->sub_id == 22) ? true : false);
	}
	
	/**
	 * Common_User_Credits object
	 */
	private $_credits;

	/**
	 * Returns the asset path of this user
	 *
	 * @return string $path The full asset path for this user
	 */
	public function assetPath() {

		$path = APPLICATION_PATH . '/../client/' . implode('/', str_split(str_pad($this->id, 9, '0', STR_PAD_LEFT), 3));

		if (!is_dir($path)) {
			$created = mkdir ($path, 0775, true);
			if (!$created) die('Could not create common user asset path.');
		}

		return realpath($path);
	}

	/**
	 * Returns the ID formated in olskool. mainly used for creating a path for workflow 
	 * mirror in the uploader
	 *
	 * @return int Common user ID in OlSkool
	 * @deprecated Now that we have updated workflow, there's no need to reference IDs in olSkool
	 */
	public function idInOlSkool() {
		
		error_log("Deprecated function called 'idInOlSkool'.");
		
		return $this->paddedId();
	}
	
	/**
	 * Returns the user's ID in zero padded 9 characters
	 *
	 * @return int $padded_id ID in 9 characters, left zero padded
	 */
	public function paddedId() {
		
		return str_pad($this->id, 9, '0', STR_PAD_LEFT);
	}

    	/**
	 * Set the last login date of this user to the current time
	 */
	public function setLastLogin() {

		$this->last_login = new Zend_Db_Expr('NOW()');
		$this->save();
    	}

	/**
	 * @deprecated Use orders instead
	 */
	public function getOrders() {

		return $this->orders();
	}

	/**
	 * Returns the total count of orders this user had made
	 *
	 * return int
	 */
	public function orderCount() {

		$db     = Zend_Registry::getInstance()->get('db');
		$sql    = 'SELECT COUNT(1) as val FROM common_orders WHERE common_user_id =' . $this->id;
		$stmt   = $db->query($sql);
		$result = $stmt->fetchAll();
		$count  = array_pop($result);

		return $count['val'];
	}

	public function orders($exclude_deleted = false) {

		if(!$this->_orders) {
			$ordModel = Base::getModel('Common_Orders');
			if (!$exclude_deleted) {
				$where    = $ordModel->select()->where('common_user_id = ?', $this->id);
			} else {
				$where    = $ordModel->select()->where('common_user_id = ?', $this->id)->where('status != "CANCELLED"');
			}
			$orders   = $ordModel->fetchAll($where);

			$this->_orders = $orders;
		}
		

		return $this->_orders;
	}

	/**
	 * Returns the transaction history for this users credit
	 *
	 * @return object $credits Clients_Credits_Rowset
	 */
	public function credits() {
		
		if (!$this->_credits) {
			$this->_credits = new Common_User_Credits($this);
		}
		
		return $this->_credits;
	}

	public function directory() {

		if (!$this->_directory) {
			$dirModel = Base::getModel('Client_Directories');
			$where    = $dirModel->select()->where('common_user_id = ?', $this->id);
			$diretory = $dirModel->fetchRow($where);

			$this->_directory = $diretory;
		}

		return $this->_directory;
	}

	/**
	 * Check weather if this user has a directory record.
	 *
	 * @return boolean True if has a record, otherwise false
	 */
	public function hasDirectory() {	
		return $this->directory() && $this->sub()->allow_client_directories == 1 ? true : false;
	}

	/**
	 * Returns the total credit value for this user.
	 *
	 * @return float Amount of credit
	 */
	public function availableCredit_DEPRECATED() {
		
		return $this->credits()->available();
	}
	
	

	/**
	 * Clear the credit amount. Mainly used in the cart where the use has used it's credit
	 * and we need to put it back to 0.00
	 *
	 * @param int $user_id The system user to use
	 * @param string $comment Comment to accompany the activity history
	 * @return boolean True on success, otherwise false
	 */
	public function clearCredit_DEPRECATED($user_id, $comment = null, $amount = 0) {

		$data = array(
			'user_id'        => $user_id,
			'common_user_id' => $this->id,
			'comment'        => $comment,
			'amount'         => ($amount> 0 ? '-' .  $amount: abs($amount)),
		);

		$crdModel = Base::getModel('Client_Credits');
		$created  = $crdModel->process($data, 'insert');

		return $created ? true : false;
	}

	/**
	 * Returns products by this user
	 *
	 * @return object $products Client_Products_Rowset
	 */
	public function products($limit = null, $exclude_deleted = false) {

		$cprModel = Base::getModel('Client_Products');
		if(!$exclude_deleted){
			$where    = $cprModel->select()->where('common_user_id = ?', $this->id)
									   //->where('date_deleted is null')
									   ->order('id DESC');
		}else{
			$where    = $cprModel->select()->where('common_user_id = ?', $this->id)
										   ->where('date_deleted is null')
									       ->order('id DESC');
		}
		if ($limit) {
			$where->limit($limit);
		}
		//die($where);
		$products = $cprModel->fetchAll($where);

		return $products;
	}

	/**
	 * Returns product rowset that is an orderable product. Note that this is hard coded for
	 * PDFs and JPGs. We should have a better mechanism soon. maybe set on the product line
	 */
	public function orderableProducts() {

		$cprModel = Base::getModel('Client_Products');
		$where    = $cprModel->select()
		                     ->where('common_user_id = ?', $this->id)
		                     ->where('date_deleted IS NULL')
		                     ->where('type NOT IN(?)', array('JPG', 'PDF'))
				     ->order('id DESC');

		$orderable_products = $cprModel->fetchAll($where);

		return $orderable_products;
	}

	/**
	 * Method to generate and store a new authcode for this user. Normally the auth code is used
	 * for password recovery feature.
	 *
	 * @return string $authcode Newly generated authentication code
	 */
	public function newAuthcode() {

		$this->authcode = uniqid();
		$this->save();

		return $this->authcode;
	}

	public function clearAuthcode() {

		$this->authcode = new Zend_Db_Expr('NULL');
		$this->save();

		return;
	}

	/**
	 * Reset this users password
	 */
	public function resetPassword($password) {

		$this->password = sha1($password);
		$this->save();

		return;
	}
	
	/**
	 * Save custom branding
	 */
	public function saveCustomBranding($customBranding) {
		//brand_location ,brand_position, software_status
		$this->brand_location = $customBranding['17'];
		$this->brand_position = $customBranding['18'];
		$this->software_status = 'Requested';
		$this->save();

		return;
	}

	/**
	 * Returns the sub object the user currently belongs to
	 *
	 * @return object $sub Subs_Row object
	 * @todo Should be using Zend reference map instead??
	 */
	public function sub() {

		$subModel = Base::getModel('Subs');
		$where    = $subModel->select()->where('id = ?', $this->sub_id);
		$sub      = $subModel->fetchRow($where);

		return $sub;
	}

	/**
	 * Returns the full name of the common user based on the first_name  & last_name fields
	 *
	 * @return string Full name of the common user
	 */
	public function fullname() {

		return $this->first_name . ' ' . $this->last_name;
	}

	/**
	 * Returns the total count of products this user has
	 *
	 * @return integer Total number of products uploaded
	 */
	public function productCount() {

		$db     = Zend_Registry::getInstance()->get('db');
		$sql    = 'SELECT COUNT(*) as val FROM client_products WHERE common_user_id =' . $this->id;
		$stmt   = $db->query($sql);
		$result = $stmt->fetchAll();
		$count  = array_pop($result);

		return $count['val'];
	}

	/**
	 * Set this common user as the current logged in client. Mainly used for logins and the
	 * auto login feature. also in MOP register form
	 *
	 * @params boolean $increment_login_count OPTIONAL True to increment login counter.
	 *         Defaults to true.
	 */
	public function setAsLoggedIn($increment_login_count = true) {

		Zend_Loader::loadClass('Zend_Session_Namespace');
		$userSession = new Zend_Session_Namespace('userProfileNamespace');
		$userSession->loggedIn = true;
		$userSession->users_id = $this->id;

		if ($increment_login_count) $this->setLastLogin();

		return;
	}

	/**
	 * Set this user the current MOP applicant for a reseller. This is used around the
	 * MOP registration process.
	 */
	public function setAsMopApplicant() {

		Zend_Loader::loadClass('Zend_Session_Namespace');
		$userSession = new Zend_Session_Namespace('userProfileNamespace');
		$userSession->applicant_users_id = $this->id;
	}

	public function unsetAsMopApplicant() {
		Zend_Loader::loadClass('Zend_Session_Namespace');
		$userSession = new Zend_Session_Namespace('userProfileNamespace');
		unset($userSession->applicant_users_id);
	}


	public function questionnaireAnswers($form = null) {

		$cqaModel = Base::getModel('Client_Questionnaires_Answers');
		$select = $cqaModel->select()->where('common_user_id = ?', $this->id);

		// filter form if requested
		if ($form) $select->where('form = ?', $form);

		$answers  = $cqaModel->fetchAll($select);

		return $answers;
	}

	/**
	 * returns a rowset of orders that are unpaid. This is determined by orders that have
	 * a payment due and NOT cancelled
	 *
	 * @return object $orders Common_Orders_Rowset
	 */
	public function unpaidOrders() {

		$ordModel = Base::getModel('Common_Orders');
		$where    = $ordModel->select()
		                     ->where('common_user_id = ?', $this->id)
				     ->where('payment_due > ?', '0.00')
				     ->where('status != ?', 'CANCELLED')
				     ->order('date_created DESC');
                
		$orders = $ordModel->fetchAll($where);
                
		return $orders;
	}
	
	public function commonUsersAssetPath($client_product = null) {
		
		// determine our base path of the file
		$base_path    = BP . '/client';
		$asset_path   = implode('/', str_split(str_pad($this->id, 9, '0', STR_PAD_LEFT), 3));
		
		return "$base_path/$asset_path";
	}
	
	public function nzcommonUsersAssetPath($client_product = null) {
		
		// determine our base path of the file
		$base_path    = BP . '/client';
		$asset_path   = implode('/', str_split(str_pad($this->nz_id, 9, '0', STR_PAD_LEFT), 3));
		
		return "$base_path/$asset_path";
	}
	
	/**
	 * returns a rowset of orders that are current. This is determined by orders having Order
	 * that have ACTIVE', 'HOLD', 'RTF' status
	 *
	 * @return object $orders Common_Orders_Rowset
	 */
	public function currentOrders() {
		
		$ordModel = Base::getModel('Common_Orders');
		$where    = $ordModel->select()
		                     ->where('common_user_id = ?', $this->id)
		                     ->where('status IN (?)', array('ACTIVE', 'HOLD', 'RTF'))
				     ->order('date_created DESC');
			
		$orders = $ordModel->fetchAll($where);

		return $orders;
	}
	
	/**
	 * returns a rowset of orders that are completed. This is determined by orders having
	 * a status 'SENT'.
	 *
	 * @return object $orders Common_Orders_Rowset
	 */
	public function completedOrders() {
		
		$ordModel = Base::getModel('Common_Orders');
		$where    = $ordModel->select()
		                     ->where('common_user_id = ?', $this->id)
		                     ->where('status = ?', 'SENT')
				     ->order('date_created DESC');
		
		$orders = $ordModel->fetchAll($where);

		return $orders;
	}
	
	/**
	 * Returns an assosiative a default status
	 * 
	 * if there are any unpaid orders, then default to that page
	 * if there no unpaid orders, but there are current orders, then default to that page
	 * if there no current orders, but there are completed orders, then default to that page
	 * if there are none of any category, the default to unpaid
	 *
	*/
	public function defaultOrdersList() {
		
		if(count($this->unpaidOrders()) > 0 ||
		  (count($this->currentOrders()) == 0 && count($this->completedOrders()) == 0)){
				$status = 'unpaid';
		}else if(count($this->currentOrders()) > 0){
				$status = 'current';
		}else if(count($this->completedOrders()) > 0){
				$status = 'completed';
		}
		
		return $status;
	}
	
	/**
	 * change the sub for this user. we now handle changing of sub via the model to allow
	 * us to make other processes. E.g. moving assets. etc...
	 */
	public function changeSub($new_sub_id) {
		
		// old sub. need to reference the old sub id for the notes message
		$subModel = Base::getModel('Subs');
		$oldSub   = $subModel->fetchById($this->sub_id);
		$newSub   = $subModel->fetchById($new_sub_id);
		if (!$oldSub || !$newSub) {
			throw new Exception('Invalid new or old sub');
		}
		
		$this->sub_id = $new_sub_id;
		$this->save();
		
		// log this event in the user's notes. create the data to be used to create the new note
		$note = array(
			'user_code'  => Base::loggedInUser()->id,
			'table_name' => 'common_users',
			'table_id'   => $this->id,
			'note'       => 'Changed user Sub from <em>' . $oldSub->name . '</em> to <em>' . $newSub->name . '</em>',
		);

		// insert the new note
		$notesModel = Base::getModel('Notes');
		$created    = $notesModel->process($note, 'insert');
		
		return;
	}

	/**
	 * Returns the total number of times the user has ordered it's OWN product
	 *
	 * @deprecated Now handled in the MySQL triggers
	 */
	public function copies() {
		return $this->copies_count;
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
							   ->where('table_name = ?', 'common_users')
							   ->order('date desc');

		$notes = $noteModel->fetchAll($where);
		$note_string = '';
		foreach($notes as $note) {
			$note_string .= date('d-M-y', strtotime($note->date)) . ' ' . $note->user()->fullname() . ': ' . htmlspecialchars(nl2br($note->note)) . $linebreak;
		}

		return $note_string;
	}
	
	public function genPortalAuthcode() {
		
		//create temp portal generate portal_authcode 
		$portal_authcode = '';
		for ($i = 0; $i < 32; $i++) {
			$portal_authcode .= rand(1, 30) % 2 ? chr(rand(65,90)) : chr(rand(48,57));
		} 
		
		$this->portal_authcode = $portal_authcode;
		$this->save();
		
		return $portal_authcode;
	}
	
	/**
	 * Returns the stations this user has access to.
	 */
	public function accessStations() {
		
		if (!$this->_access_stations) {
			
			$stuModel = Base::getModel('Bpm_Stations_Users');
			$select   = $stuModel->select()->where('user_id = ?', $this->id);
			$userStn  = $stuModel->fetchAll($select);
			
			$stnModel = Base::getModel('Bpm_Stations');
			$select   = $stnModel->select()->where('station_id IN(?)', $userStn->inField('station_id'));
			$stations = $stnModel->fetchAll($select);
			$this->_access_stations = $stations;
		}
		
		return $this->_access_stations;
	}
	
	/**
	 * Returns a client discount record belonging to this user based on
	 * the 'Momento 2010 birthday special'
	 *
	 * @return object $client_discount Client_Discount_Row
	 * @todo This method should be deprecated after 2010-09-30 as it will be no longer used
	 * @deprecated since 1.1.3
	 */
	public function clientDiscount2010BirthdayPromo() {
		
		// only momento users
		if ($this->sub_id != 1) return null;
		
		$cdModel = Base::getModel('Client_Discounts');
		$select  = $cdModel->select()
		                   ->where('description = ?', 'Momento 2010 birthday special')
		                   ->where('common_user_id = ?', $this->id);
		                   
		$client_discount = $cdModel->fetchRow($select);
		
		return $client_discount;
	}

   /**
	* Returns a html content displaying the email to CS dept when Pro app comes in 
	*/
	public function emailTemplate() {
		
		$html = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"					"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
		<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<style type="text/css">
			body {
				font-family: "lucida grande",lucida,tahoma,helvetica,arial,sans-serif;
				font-size: 12px;
			}
			#wrapper {
				width: 593px;
				padding: 
			}
			#content {
				padding: 25px;
			}
			#footer {
				font-size: 10px;
			}
			p.break {
				border-bottom: 1px dotted #ccc;
				margin: 0;
			}
		</style>
	</head>
	<body>
		<div id="wrapper">
		<div id="content">
		<label><b>User ID : </b></label> '    . $this->id.			'<br>'
		.'<label><b>User Name : </b></label> ' . $this->fullname(). '<br>'
        .'<label><b>Company : </b></label> '  . $this->company.		'<br>'
		.'<a href="http://'.$this->sub()->domainShare().'/common/users/edit/id/'.$this->id.'" target="_blank">'.'Edit User</a><br>
		<label>Questionnarie Answers</label><br>';
		
		foreach ($this->questionnaireAnswers() as $answer):
			  $html .=  '<label><b>'.$answer->question()->question.'</b></label> '. $answer->answer . '<br>';
		endforeach;
		
		return $html;

	}
	
	public function emailNewMembershipWithPassword($password = null) {
		
		// init paramaters
		$sub  = $this->sub();
		$user = $this;
		
		// we are going to use output buffering to make the email easier to build
		ob_start();
echo <<< END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<style type="text/css">
			body { font-family: "lucida grande",lucida,tahoma,helvetica,arial,sans-serif; font-size: 12px; }
			#wrapper { width: 593px; padding: 0; }
			#content { padding: 25px; }
			#footer { font-size: 10px; }
			p.break { border-bottom: 1px dotted #ccc; margin: 0; }
		</style>
	</head>
	<body>
		<div id="wrapper">
			<img src="http://{$sub->domain}/images/logo.gif" border="0" />
			<p class="break">&nbsp;</p>
			
			<div id="content">				
				Here are the details of your new Momento membership. Use these to sign in at <a href="http://{$sub->domain}">{$sub->domain}</a> to check on your order.
				<br /><br />
				
				   <strong>Email address:</strong> {$user->email}<br />
				   <strong>Password:</strong> {$password}<br />
				   <br />
				   <br />
				
				For information on creating your own Momento products, go to <a href="http://{$sub->domain}/pages/howto">{$sub->domain}/pages/howto</a>
				<br /><br />
				
				Please keep this email for future reference and contact us at <a href="mailto:{$sub->email}">{$sub->email}</a> if you have any enquiries.
				<br /><br />
				
				<p>Regards,<br /><strong>The {$sub->name()} Team</strong></p>
				
				<p class="break">&nbsp;</p>
				
				<div id="footer">
					&copy; 2010 <a href="http://{$sub->domain}">{$sub->name()}</a>.&nbsp;&nbsp;&nbsp;&nbsp;
					<a href="http://{$sub->domain}/pages/contacts">Contact us</a>&nbsp;&nbsp;&nbsp;&nbsp;
					<a href="mailto:{$sub->email}">{$sub->email}</a>&nbsp;&nbsp;&nbsp;&nbsp;
					<strong>{$sub->phone}</strong>
				</div>
			</div>
		</div>
	</body>
</html>
END;
		$html = ob_get_contents();
		ob_end_clean();
		
		return $html;
        }

	public function country() {
		
		if (empty($this->delivery_country)) return null;
		
		$ctModel = Base::getModel('Countries');
		$select  = $ctModel->select()->where('id = ?', $this->delivery_country);
		$country = $ctModel->fetchRow($select);

		return $country;
	}
	
	public function emptyDeliveryAddress() {
		if (empty($this->delivery_address1) ||
		    empty($this->delivery_city)     ||
		    empty($this->delivery_state)    ||
		    empty($this->delivery_postcode) ||
		    empty($this->delivery_country)) {
			return true;
		}else{
			return false;
		}
	}

	/**
	 * Returns a human readable string of delivery details
	 *
	 * @return string $delivery_details Delivery details
	 */
	public function deliveryAddress($line_break = '<br />') {

		// first check that there is any value
		$company           = !empty($this->company)           ? $this->company           . $line_break : null;
		$address1          = !empty($this->delivery_address1) ? $this->delivery_address1 . $line_break : null;
		$address2          = !empty($this->delivery_address2) ? $this->delivery_address2 . $line_break : null;
		$delivery_city	   = !empty($this->delivery_city)     ? $this->delivery_city     . $line_break : null;
		$delivery_state    = !empty($this->delivery_state)    ? $this->delivery_state    . ', '        : null;
		$delivery_postcode = !empty($this->delivery_postcode) ? $this->delivery_postcode . ', '        : null;
		$delivery_country  = !empty($this->delivery_country)  ? $this->country()->name                 : null;

		// generate the full address
		$delivery_address = $this->fullname().$line_break 
				  . $company
				  . $address1
				  . $address2
				  . $delivery_city
				  . $delivery_state 
				  . $delivery_postcode 
				  . $delivery_country;

		return $delivery_address;
	}
	
	public function addNote($note, $system_user_id) {
		
		// set additial paramaters
		$data = array(
			'note'       => $note,
			'user_code'  => $system_user_id,
			'table_name' => 'common_users',
			'table_id'   => $this->id,
		);
		
		// add the new note
		$ntModel = Base::getModel('Notes');
		$created = $ntModel->process($data, 'insert');
		if (!$created) throw new Exception('Error creating user note');
		
		return;		
	}

	/**
	 * Returns a total amount owing for a customer
	 *
	 * @return float $sum_unpaid Total amount unpaid from customer
	 */
	public function sumUnpaid() {
			
		$sql = "SELECT
		            SUM(payment_due)
			FROM
			    `common_orders` AS `co`
			WHERE
			    (status IN ('ACTIVE', 'HOLD', 'RTF', 'SENT'))
			    AND (payment_due > 0)
			    AND common_user_id = " . $this->id;
			    
		$db         = Zend_Registry::get('db');
		$sum_unpaid = $db->fetchOne($sql);
		
		return $sum_unpaid;
	}

	/**
	 * Returns a most recent upload from that customer excluding the deleted ones
	 *
	 * @return object $product Client_Products_Row
	 */
	public function mostRecentUpload() {
	
		$cpModel = Base::getModel('Client_Products');
		$select  = $cpModel->select()
		                   ->where('common_user_id = ?', $this->id)
		                   ->where('date_deleted IS NULL')
				   ->order('id DESC')
				   ->limit(1);
		
		$product = $cpModel->fetchRow($select);
		
		return $product;
	}
	
	/**
	 * Returns the count of competition entries this user has
	 *
	 * @return int $count Number of competition entries
	 */
	public function competitionEntriesCount() {
		
		$db  = Zend_Registry::get('db');
		$sql = "SELECT
		            COUNT(id)
			FROM
			    client_products
			WHERE
			    common_user_id = '" . $this->id . "'
			    AND competition_entry IS NOT NULL
			    AND date_deleted IS NULL";
		
		$count = $db->fetchOne($sql);
		
		return $count;
	}
	
	/**
	 * Returns the first ordered date
	 *
	 */
	public function firstOrderDate() {
	
		$cpModel = Base::getModel('Common_Orders');
		$select  = $cpModel->select()
		                   ->where('common_user_id = ?', $this->id)
		                   ->where('status != ?', 'CANCELLED')
				   ->order('id ASC')
				   ->limit(1);
		
		$order = $cpModel->fetchRow($select);
		if($order){
			return $order->date_created;
		}
		return null;
	}
	/**
	 * Returns the total ordered amount
	 *
	 */
	public function totalOrderedAmount() {
	
		$sql  = "SELECT SUM(total) as amount FROM common_orders WHERE status !=  'CANCELLED' AND common_user_id = " . $this->id;
		$db   = Zend_Registry::get('db');
		$stmt = $db->query($sql, array());
		$result = $stmt->fetchAll();
		$count  = array_pop($result);

		return $count['amount'];		
	}
	
	/**
	 * Checks if the last uploaded project software version less than latest version on that OS.
	 */	
	public function latestProductLatestApp() {
		
		$cProductModel = Base::getModel('Client_Products');
		$select        = $cProductModel->select()
					       ->where('common_user_id = ?', $this->id)
					       ->order('id DESC')
					       ->limit(1);

		$product = $cProductModel->fetchRow($select);
		if ($product) {
			//check with our latest app version oted in table updater_software
			$client_platform = $product->isMac() ? 'Mac' : 'Windows';
			$client_code     = $product->software_code;
			
			// if we didn't get a software_code (SoftwareCode) there's no point going further
			if (empty($client_code)) return true;
			
			$softwareModel = Base::getModel('Updater_Software');
			$select = $softwareModel->select()
						->where('code = ?', $client_code)
						->where('platform = ?', $client_platform)
						->where('active = ?', 1)
						->order('app_version DESC')
						->order('build DESC')
						->limit(1);
			   
			$software = $softwareModel->fetchRow($select);
			   
			if ($software) {
				//disregard the warnings(e.g. uploads done by Goran) and
				if(preg_match("/^[A-Z]{3}[0-9]+.[0-9]+.[0-9]+.[0-9]+$/", $product->app_version)){
					return true;				
				}
					   
				$product_app_version = preg_replace("/^\D{0,}/", "", $product->app_version);
				//$p_app_version = '5.05.0.00'; //'0.2212.225.999';
				$cmp1 = version_compare($product_app_version,  $software->app_version);
				if($cmp1 == -1){
					return false;
				}
			}			   
		}
		
		return true;
		
	}
		
	public function hasUserDiscount() {
		
		return (strtoupper($this->account) == 'APPROVED' || (int) $this->discount_percentage > 0) ? true : false;
	}
	
	/**
	 * Returns a list of shop products belonging to this user
	 *
	 * @return object $shop_products Shop_Products
	 */
	public function shopProducts() {
		
		if (!$this->_shop_products) {
			$spModel = Base::getModel('Shop_Products');
			$select  = $spModel->select()
			                   ->where('common_user_id = ?', $this->id)
					   ->where('active = ?', 1);
						
			$shop_products = $spModel->fetchAll($select);
			
			$this->_shop_products = $shop_products;
		}
		
		return $this->_shop_products;
	}
	
	public function publicShopProducts() {
		
		if (!$this->_public_shop_products) {
			$spModel = Base::getModel('Shop_Products');
			$select  = $spModel->select()
			                   ->where('common_user_id = ?', $this->id)
					   ->where('active = ?', 1)
					   ->where('public = ?', 1);
						
			$shop_products = $spModel->fetchAll($select);
			
			$this->_public_shop_products = $shop_products;
		}
		
		return $this->_public_shop_products;
	}
	
	/**
	 * determine if this use has shop products
	 *
	 * @return boolean True if has product, otherwise false
	 */
	public function hasShopProducts() {
		
		return $this->shopProducts()->count() > 0 ? true : false;
	}
	

	/**
	 * Returns the users full name based on the fields set on first_name and last_name
	 * if a MOP user then return the company name
	 * @return string Fullname of the user or company name
	 */
	public function copyright() {
		
		if ($this->sub_id == 2) {
			return $this->company;
		}
		
		return $this->first_name . ' ' . $this->last_name;
	}
	/**
	 * Returns the total count of orders this user had made last 12 months and paid order
	 *
	 * return int
	 */
	public function paidLastYearOrderCount() {

		$db     = Zend_Registry::getInstance()->get('db');
		$sql    = 'SELECT COUNT(1) as val FROM common_orders WHERE common_user_id =' . $this->id .' AND item_amount > 0 AND payment_due <= 0 AND date_created between date_sub(curdate(), interval 1 year) and sysdate()';
		$stmt   = $db->query($sql);
		$result = $stmt->fetchAll();
		$count  = array_pop($result);

		return $count['val'];
	}
	
	public function confirmemail($order_id){
		
		$ordModel     =  Base::getModel('Common_Orders');
		$order        = $ordModel->fetchById($order_id);
		$order_number = $order->order_number;
		$mail    = new Zend_Mail();
		$content = 'Please do not reply to this email.<br /><br />'
			  .'You have received this email because user '. $this->fullname() . ' ' . $this->company . ' has been set for order notify.<br />The order would be placed on <strong>HOLD</strong> once it becomes <strong>ACTIVE</strong>.<br /><br />'
			  .'User: '.'<a href=http://'.$order->sub()->domain.'/common/users/edit/id/'.$this->id.'>'.$this->id.'</a><br />'
			  .'Order: '.'<a href=http://'.$order->sub()->domain.'/common/orders/edit/id/'.$order_id.'>'.$order_number.'</a>'
			  .'';
		$subject = 'New order notification - User '.$this->id;
		$mail->setBodyHtml($content);
		$mail->setFrom('no-reply@'.$this->sub()->domain, $this->sub()->name().' Sales');
		$mail->setSubject($subject);
		
		if (APPLICATION_ENV != 'production') {
			$mail->addTo('webdev@globalphotobooks.com', 'Momento Admin');
		} else {
			$mail->addTo($this->sub()->email, 'Momento Admin');
		}
		
		$mail->send();
	}
	
	/**
	 * returns the company name (if any), otherwise return the full name
	 *
	 * @return string $name The company name, otherwise full name
	 */
	public function companyOrFullname() {
		
		return !empty($this->company) ? $this->company : $this->fullname();
	}
	
	public function lastYearDisplayorders($year=false){
		
		$lastyear  = mktime(0, 0, 0, date("m"),   date("d"),   date("Y")-1);
		$lastyear  = date('Y-m-d H:i:s',$lastyear);
		
		$orderModel = Base::getModel('Common_Orders');
		// prepare SQL query
		$db  = Zend_Registry::get('db');
			
		$lastyear='';
		if ($year) {
			$lastyear =" JOIN ( select created, common_order_id from common_orders_activity where status ='ACTIVE' and created between date_sub(curdate(),interval 1 year) and sysdate() group by common_order_id order by created ASC ) as coa ON co.id=coa.common_order_id";
			
		}
		$sql ="SELECT
				SUM(ci.copies) as count
			FROM
				common_items ci
				JOIN common_orders co on co.id=ci.common_order_id
				$lastyear
				WHERE ci.display_item=1
				AND co.status != 'PENDING'
				AND co.status != 'CANCELLED'
				AND (ci.status!='CANCELLED' OR ci.status IS NULL)
				AND co.common_user_id=".$this->id.
			        " GROUP by co.common_user_id";
		
		$orders = $db->fetchAll($sql);
		
		if(empty($orders[0]['count'])){
			return 0;
		}
		
		return $orders[0]['count'];
			     
	}
	
	public function remittance_email($batch_id, $shop_date_remittance){
		
		//generate remittance Invoice
		$this->remittanceInvoice($batch_id, $shop_date_remittance);
	    // send the remittance_email
		$mailto = APPLICATION_ENV != 'production' ? 'webdev@globalphotobooks.com' : $this->email;
		$mail   = new Zend_Mail();
		$mail->setFrom('help@momentoshop.com', 'Momento Shop');
		$mail->addTo($mailto);
		$mail->setSubject('Momento Shop Remittance '.date('d-m-Y'));
		$mail->setBodyHtml($this->emailRemittanceBody($batch_id, $shop_date_remittance));
		$hasGstremittance = $this->hasGstremittance($batch_id, $shop_date_remittance);
		if($hasGstremittance){
			//attachement
			$fileContents = file_get_contents(APPLICATION_PATH.'/../tmp/remittance_invoice/'.$this->id.'-'.$batch_id.'.pdf');
			$file = $mail->createAttachment($fileContents);
			$file->filename = $this->id.'-'.$batch_id.'.pdf';
		}
		$mail->addBcc('geoff@momento.com.au');
		$sent = $mail->send(); 
	}
	
	public function hasGstremittance($batch_id, $shop_date_remittance){
		$user = $this;
		// prepare select
		$spModel = Base::getModel('Shop_Products');
		$select   = $spModel->select()
		                     ->setIntegrityCheck(false)
		                     ->from(array('sp' => 'shop_products'));
		
		//join with common_items
		$select->join(array('ci' => 'common_items'), 'sp.client_product_id = ci.client_product_id', array('sum(ci.copies*ci.shop_markup) as shop_markup,  ci.registered_gst as gst'));
		$select->where('ci.remittance_batch_id = ?', $batch_id);
		$select->where('ci.shop_date_remittance = ?', $shop_date_remittance);
		$select->where('sp.common_user_id = ?', $user->id);
		$select->where('ci.registered_gst = ?', 1);
		//Zend_Debug::dump((string)$select); die();
		$rows = $spModel->fetchAll($select);
		if($rows->count() > 0){
			foreach($rows as $row){
				if((int)$row['id']> 0){
					return true;	
				}
				
			}
		}
		return false;
	}
	
	public function emailRemittanceBody($batch_id, $shop_date_remittance){
		// init paramaters
		$sub  = $this->sub();
		$user = $this;
		// prepare select
		$spModel = Base::getModel('Shop_Products');
		$select   = $spModel->select()
		                     ->setIntegrityCheck(false)
		                     ->from(array('sp' => 'shop_products'));
		
		//join with common_items
		$select->join(array('ci' => 'common_items'), 'sp.client_product_id = ci.client_product_id', array('sum(ci.copies*ci.shop_markup) as shop_markup,  ci.registered_gst as gst'));
		$select->where('ci.remittance_batch_id = ?', $batch_id);
		$select->where('ci.shop_date_remittance = ?', $shop_date_remittance);
		$select->where('sp.common_user_id = ?', $user->id);
		$select->group(array('sp.common_user_id', 'gst'));
		$shop_markup_inc_gst = $shop_markup_exc_gst = $invoice ='';
		$rows = $spModel->fetchAll($select);
		if($rows->count() > 0){
			
			foreach($rows as $row){
				if($row->gst == 1 ){
					$shop_markup_inc_gst = 'Amount transferred: '.sprintf("%.2f", $row->shop_markup) .' including GST <br />';
					$invoice = 'For payments where GST is applicable, please find a Recipient Created Tax Invoice attached.<br /><br />';
				}else{
					$shop_markup_exc_gst = 'Amount transferred: '.sprintf("%.2f", $row->shop_markup) . ' (GST not applicable) <br />';
				}
				
			}
		}else{
			return fasle;
		}
		// we are going to use output buffering to make the email easier to build
		ob_start();
echo <<< END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<style type="text/css">
			body { font-family: "lucida grande",lucida,tahoma,helvetica,arial,sans-serif; font-size: 12px; }
			#wrapper { width: 593px; padding: 0; }
			#content { padding: 25px; }
			#footer { font-size: 10px; }
			p.break { border-bottom: 1px dotted #ccc; margin: 0; }
		</style>
	</head>
	<body>
		<div id="wrapper">
			<div id="content">				
				Dear {$user->first_name}
				<br /><br />
				
				We have recently transferred funds to your bank account for the sale of your products in Momento Shop.
				<br /><br />
				
				Details:
				<br />
				{$shop_markup_inc_gst}
				{$shop_markup_exc_gst}
				Account: BSB {$user->bsb} , Account {$user->account_number}
				<br /><br />
				
				{$invoice}
				
				You can sign in to <a href="http://{$sub->domain}">{$sub->name()}</a> at any time to view how your sales are progressing.
				<br />
				Please feel free to contact us should you have any queries about this payment.
				<br /><br />
				Regards
				<br />
				The Momento Shop team				
			</div>
		</div>
	</body>
</html>
END;
		$html = ob_get_contents();
		ob_end_clean();
		
		return $html;
	}
	
	public function remittanceInvoice($batch_id = 27, $shop_date_remittance = null){
		$user = $this;
		$page_count = 0;
		$footer_added = 0;
		$pdf = new Zend_Pdf();
		$pdf->pages[] = $pdf->newPage(Zend_Pdf_Page::SIZE_A4);
		$page=$pdf->pages[$page_count];
		$style = new Zend_Pdf_Style();
		$style->setLineColor(new Zend_Pdf_Color_Rgb(0,0,0));
		$font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES);
		$style->setFont($font,16);
		$page->setStyle($style);
		$page_height = $page->getHeight();
		$page->drawText('RECIPIENT CREATED TAX INVOICE',150,($page_height-80));
		
		$style->setFont($font,12);
		$page->setStyle($style);

		$company = (trim($user->company) !='' ? $user->company: $user->first_name .' ' .$user->last_name);
		$address1 = !empty($this->delivery_address1) ? $this->delivery_address1 . ' ' : null;
		$address1 .= !empty($this->delivery_address2) ? $this->delivery_address2 . ' ' : null;
		$address1 .= !empty($this->delivery_city) ? $this->delivery_city . ' ' : null;
		$address2 = !empty($this->delivery_state) ? $this->delivery_state . ', ' : null;
		$address2 .= !empty($this->delivery_postcode) ? $this->delivery_postcode   : null;
		$page->drawText($company, 50, ($page->getHeight()-120));
		$page->drawText($address1, 50, ($page->getHeight()-133));
		$page->drawText($address2, 50, ($page->getHeight()-147));
		$page->drawText('ABN # '.$this->abn, 50, ($page->getHeight()-160));
		
		$remittanceDate = !empty($shop_date_remittance) ? date('d/m/Y')  : date('d/m/Y');
		$invoice_number = $this->id . '-' . $batch_id;
		$page->drawText('Remittance Date: ' . $remittanceDate,400,($page->getHeight()-120));
		$page->drawText('Invoice number: ' . $invoice_number,400,($page->getHeight()-133));
		
		$style->setFont($font,10);
		$page->setStyle($style);
		$page->drawText('Note: only items that were sold with GST applicable will appear on this invoice', 100, ($page->getHeight()-188));
		
		$head_row_height = 20;
		$row_height = 20;
		$line_height_start = $page_height - 200 ;
		$total_data_row = 30;
		$page_break_row = 25;
		$textline_height = 12;
		$spModel = Base::getModel('Shop_Products');
		$select   = $spModel->select()
		                     ->setIntegrityCheck(false)
		                     ->from(array('sp' => 'shop_products'));
		
		//join with common_items
		$select->join(array('ci' => 'common_items'), 'sp.client_product_id = ci.client_product_id', array('sum(ci.copies) as copies, sum(ci.copies*ci.shop_markup) as shop_markup,  ci.registered_gst as gst', 'ci.registered_gst'));
		$select->where('ci.remittance_batch_id = ?', $batch_id);
		if(!empty($shop_date_remittance))
			$select->where('ci.shop_date_remittance = ?', $shop_date_remittance);
		$select->where('sp.common_user_id = ?', $user->id);
		$select->where('ci.registered_gst = 1');
		$select->group(array('sp.common_user_id', 'gst'));
		$shop_markup_inc_gst = $shop_markup_exc_gst = '';
		//Zend_Debug::dump((string)$select); die();
		$rows = $spModel->fetchAll($select);
		$total_data_row = $rows->count();

			$i=1; $j = 1;
			$total_copies = 0;
			$total_Ex_GST = $total_Inc_GST = 0.00;
			foreach($rows as $row){
				if($i == 1){
					$font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES_BOLD);
					$style->setFont($font,10);
					$page->setStyle($style);
					
					$page->drawLine(60, $line_height_start, 540, $line_height_start)   // HEAD Bottom
						->drawLine(60, $line_height_start, 60, $line_height_start-$head_row_height)    // MOST Left
						->drawLine(300, $line_height_start, 300, $line_height_start-$head_row_height)    // MOST Left
						->drawLine(360, $line_height_start, 360, $line_height_start-$head_row_height)    // MOST Left
						->drawLine(450, $line_height_start, 450, $line_height_start-$head_row_height)    // MOST Left
						->drawLine(60, $line_height_start-$head_row_height, 540, $line_height_start-$head_row_height)     // MOST Left
						->drawLine(540, $line_height_start, 540, $line_height_start-$head_row_height)  // MOST Right
						->drawText('Description', 68, $line_height_start-$textline_height)   
						->drawText('Copies', 308, $line_height_start-$textline_height)           // Table Headers (402) - 27
						->drawText('Ex-GST Amount', 368, $line_height_start-$textline_height)      // Table Headers
						->drawText('Inc-GST Amount', 458, $line_height_start-$textline_height) ;   // Table Headers
						$line_height_start = $line_height_start-$head_row_height;
					
					$font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES);
					$style->setFont($font,10);
					$page->setStyle($style);	
				}
				$shop_markup = sprintf("%01.2f",$row->shop_markup);
				$shop_markup_len = strlen($shop_markup);
				$num_pad = 5*($shop_markup_len-4);
				$page->drawLine(60, $line_height_start, 540, $line_height_start)   // HEAD Bottom
					->drawLine(60, $line_height_start, 60, $line_height_start-$row_height)    // MOST Left
					->drawLine(300, $line_height_start, 300, $line_height_start-$row_height)    // MOST Left
					->drawLine(360, $line_height_start, 360, $line_height_start-$row_height)    // MOST Left
					->drawLine(450, $line_height_start, 450, $line_height_start-$row_height)    // MOST Left
					->drawLine(60, $line_height_start-$row_height, 540, $line_height_start-$row_height)     // MOST Left
					->drawLine(540, $line_height_start, 540, $line_height_start-$row_height)  // MOST Right
					->drawText($row->title, 68, $line_height_start-$textline_height)   
					->drawText($row->copies, 330, $line_height_start-$textline_height)           // Table Headers (402) - 27
					->drawText(($row->gst == 1? sprintf("%01.2f",($shop_markup - ($shop_markup / 11))) : sprintf("%01.2f",$shop_markup)), (425-$num_pad), $line_height_start-$textline_height)      // Table Headers
					->drawText(sprintf("%01.2f",$shop_markup) , (515-$num_pad), $line_height_start-$textline_height) ;   // Table Headers
					$line_height_start = $line_height_start-$row_height;
				
				$total_copies += $row->copies;
				$total_Inc_GST += $shop_markup;
				if($row->gst == 1)
					$total_Ex_GST += $shop_markup - ($shop_markup / 11);
				else
					$total_Ex_GST += $shop_markup;
				$footer_added = 0;
				if($j == $total_data_row){
					$total_Ex_GST = sprintf("%01.2f",$total_Ex_GST);
					$total_Inc_GST = sprintf("%01.2f",$total_Inc_GST);
					$len = strlen($total_Ex_GST);
					$ex_gst_num_pad = 5*($len-4);
					$len = strlen($total_Inc_GST);
					$inc_gst_num_pad = 5*($len-4);
					$len = strlen($total_copies);
					$copies_num_pad = 5*($len-1);
		
					$font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES_BOLD);
					$style->setFont($font,10);
					$page->setStyle($style);
		
					$page->drawLine(60, $line_height_start, 540, $line_height_start)   // HEAD Bottom
						->drawLine(60, $line_height_start, 60, $line_height_start-$head_row_height)    // MOST Left
						->drawLine(300, $line_height_start, 300, $line_height_start-$head_row_height)    // MOST Left
						->drawLine(360, $line_height_start, 360, $line_height_start-$head_row_height)    // MOST Left
						->drawLine(450, $line_height_start, 450, $line_height_start-$head_row_height)    // MOST Left
						->drawLine(60, $line_height_start-$head_row_height, 540, $line_height_start-$head_row_height)     // MOST Left
						->drawLine(540, $line_height_start, 540, $line_height_start-$head_row_height)  // MOST Right
						->drawText('Total', 68, $line_height_start-$textline_height)   
						->drawText($total_copies, (330-$copies_num_pad), $line_height_start-$textline_height)           // Table Headers (402) - 27
						->drawText($total_Ex_GST, (425-$ex_gst_num_pad), $line_height_start-$textline_height)      // Table Headers
						->drawText($total_Inc_GST, (515-$inc_gst_num_pad), $line_height_start-$textline_height) ;   // Table Headers
						$line_height_start = $line_height_start-$head_row_height;
					$footer_added = 1;
					$font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES);
					$style->setFont($font,10);
					$page->setStyle($style);
					$image = Zend_Pdf_Image::imageWithPath(APPLICATION_PATH.'/../sites/MOP/htdocs/images/logo.jpg'); 
					$page->drawImage($image, 65, 40, 250, 80);
					$page->drawText('PO Box 140, Strawberry Hills NSW 2012', 68, 30);
					
					$image = Zend_Pdf_Image::imageWithPath(APPLICATION_PATH.'/../sites/MOS/htdocs/images/logo.jpg'); 
					$page->drawImage($image, 400, 40, 550, 80);
					
				}

				
				if($i == $page_break_row){
					if($footer_added == 0 ){
						$image = Zend_Pdf_Image::imageWithPath(APPLICATION_PATH.'/../sites/MOP/htdocs/images/logo.jpg'); 
						$page->drawImage($image, 65, 40, 250, 80);
						$page->drawText('PO Box 140, Strawberry Hills NSW 2012', 68, 30);
						
						$image = Zend_Pdf_Image::imageWithPath(APPLICATION_PATH.'/../sites/MOS/htdocs/images/logo.jpg'); 
						$page->drawImage($image, 400, 40, 550, 80);
						$page_count++;
						$pdf->pages[$page_count] = $pdf->newPage(Zend_Pdf_Page::SIZE_A4);
						$page=$pdf->pages[$page_count];
						$style = new Zend_Pdf_Style();
						$style->setLineColor(new Zend_Pdf_Color_Rgb(0,0,0));
						$font = Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_TIMES);
						$style->setFont($font,10);
						$page->setStyle($style);
						$line_height_start = $page_height - 80 ;
						
					}
					$page_break_row = 30;
					$i = 1;
				}else{
					$i++;
				}
				$j++;
			}
			
			if($footer_added == 0 ){
				$image = Zend_Pdf_Image::imageWithPath(APPLICATION_PATH.'/../sites/MOP/htdocs/images/logo.jpg'); 
				$page->drawImage($image, 65, 40, 250, 80);
				$page->drawText('PO Box 140, Strawberry Hills NSW 2012', 68, 30);
				
				$image = Zend_Pdf_Image::imageWithPath(APPLICATION_PATH.'/../sites/MOS/htdocs/images/logo.jpg'); 
				$page->drawImage($image, 400, 40, 550, 80);
			}
			if($total_data_row > 0){			
				$pdf->save(APPLICATION_PATH.'/../tmp/remittance_invoice/'.$invoice_number.'.pdf');
			}else {
				return false;
			}

	}
		
	function completePhoneNumber() {
		
		$phone = $this->phone;
		
		if (!empty($this->phone_areacode)) $phone = $this->phone_areacode . ' ' . $this->phone;
		
		return $phone;
	}
	
	/**
	 * Determine if this user is subscribed to the newsletter
	 *
	 * @return boolean True if subscribed, otherwise false
	 */
	public function isSubscribedToNewsletter() {

		$subscriber = $this->newsletterRecord();
		if ($subscriber && $subscriber->isSubscribed()) return true;
			
		return false;
	}
	
	/**
	 * Returns the newsletter record for this user, if any
	 *
	 * @return object $newsletter Newsletter_Row
	 */
	public function newsletterRecord() {
		
		$nlModel    = Base::getModel('Newsletter');
		$subscriber = $nlModel->fetchByEmail($this->email);
		
		return $subscriber;
	}
	
	/**
	 *for prefill phone number on checkout page
	 *if mobile exist return the mobile number
	 *else return the land number
	 */
	public function phone(){
		if(trim($this->mobile) != ''){
			return $this->mobile;
		}else{
			return $this->phone_areacode . $this->phone;
		}
	}
	
	/**
	 *Update newsletter.sub_id when a common user change the sub
	 */
	public function changeNewsletterSub() {
		if($this->newsletterRecord()){			
			$nlModel    = Base::getModel('Newsletter');
			$subscriber = $nlModel->fetchByEmail($this->email);
			
			$subscriber->changeSub($this->sub_id);
		}
	}
	
    /**
     * subscribe this use to the newsletter
     *
     * @return null
     */
	public function subscribeNewsletter() {
		
		// check if the user is already subscribed
        $subscription = $this->newsletterRecord();
        if ($subscription) {
            $subscription->resubscribe($this->first_name);
        
        } else {
            
            // create a new record
            $data = array(
                'email'          => $this->email,
                'common_user_id' => $this->id,
                'first_name'     => $this->first_name,
                'sub_id'         => $this->sub_id,
            );
            
            $nwlModel = Base::getModel('Newsletter');
            $created  = $nwlModel->process($data, 'insert');
        }
		
		return;
	}
	
    /**
     * unsuscribe this use from the newsletter
     */ 
	public function unSubscribeNewsletter() {
		
        $subscription = $this->newsletterRecord();
        if ($subscription) $subscription->unsubscribe();
	
    }
    
        public function newsletterStatus() {
		
		// check if the user is already subscribed
		$subscription = $this->isSubscribedToNewsletter();
		
		if ($subscription) {
			echo '<span style="color:green">subscribed</span>';	
		} else {
			echo '<span style="color:red">not subscribed</span>';
		}
	}
}
