<?php
/**
 *
 * @category  Common
 * @package   Common_Models
 */

/**
 * Users model class
 *
 * @category Common
 * @package  Common_Models
 */
class Common_Users extends Base_Db_Table {

	/**
	 * Name of the database table we are going to use for this model
	 *
	 * @var string $_name Name of the table this model uses
	 */
	protected $_name = 'common_users';

	/**
	 * List of class name for each dependent table
	 *
	 * @var array $_dependentTables
	 */
	protected $_dependentTables = array();

	/**
	 * Specify a custom Row to be used by default in all instances of a Table class
	 *
	 * @var string $_rowClass Custom row name
	 */
	protected $_rowClass = 'Common_Users_Row';

	/**
	 * Specify a custom Rowset to be used by default in all instances of a Table class
	 *
	 * @var string $_rowsetClass Custom row name
	 */
	protected $_rowsetClass = 'Common_Users_Rowset';

	/**
	 * Search for any records that match a string
	 */
	public function searchFor($search) {

		$sql = "SELECT u.*
				FROM common_orders o
				JOIN common_users u ON (o.common_user_id = u.id)
				WHERE u.first_name LIKE '%$search%'
				OR u.last_name LIKE '%$search%'
				OR u.company LIKE '%$search%'
				GROUP BY u.id
				";


		$db = Zend_Registry::getInstance()->get('db');
		$stmt = $db->query($sql);
		Zend_Loader::loadClass('Common_Users_Rowset');
		
		return new Common_Users_Rowset(array('table'=> $this, 'rowClass'=>$this->_rowClass, 'data'=>$stmt->fetchAll() ));
	}

	/**
	 * Search for any records that match a string. Same as searchFor but for users. Not going to change
	 * it until it's determined that it's wrong
	 */
	public function searchForCustom($search) {

		$sql = "SELECT u.*
		        FROM common_users u
		        WHERE
		            u.id = '$search'
		            OR u.first_name LIKE '%$search%'
		            OR u.email LIKE '%$search%'
		            OR u.last_name LIKE '%$search%'
		            OR u.company LIKE '%$search%'
		        GROUP BY u.id";


		$db   = Zend_Registry::getInstance()->get('db');
		$stmt = $db->query($sql);
		Zend_Loader::loadClass('Common_Users_Rowset');
		return new Common_Users_Rowset(array('table'=> $this, 'rowClass'=>$this->_rowClass, 'data'=>$stmt->fetchAll() ));

	}

	/**
	 * Returns an instance of a JoinedSelect object used to query multiple joined tables.
	 *
	 * @return Zend_Db_Table_Select
	 */

	public function subSelect($modelRef) {//withFromPart = self::SELECT_WITHOUT_FROM_PART
		require_once 'Common/Useres/SubSelect.php';
		$select = new Common_Users_SubSelect($modelRef);
		if (true){//$withFromPart == self::SELECT_WITH_FROM_PART) {
			$select->from($modelRef->info(self::NAME), Zend_Db_Table_Select::SQL_WILDCARD, $this->info(self::SCHEMA));
		}
		return $select;
	}

	/**
	 * Common user profile for validation
	 *
	 * @return array $profile Model profile
	 */
	public function profile() {

		$data     = $this->getData();
		$id       = isset($data['id']) ? $data['id'] : null;
		$password = isset($data['password']) ? $data['password'] : null;
		
		$profile = array(
			'insert' => array(
				'required' => array(
					'sub_id'     => 'Missing Sub ID',
					'first_name' => 'Enter your first name',
					'last_name'  => 'Enter your last name',
					'email'      => 'Enter your email address',
					'password'   => 'Choose a safe and secure password',
					//'phone'      => 'Enter your phone number',
					'terms'      => 'You must read and agree to the Terms and Conditions',
				),
				'optional' => array(
					'users_salespeople_id',
					'code',
					'platinum_account',
					'account',
					'discount_percentage',
					'company',
					'abn',
					'reason_no_abn',
					'phone_areacode',
					'mobile',
					'delivery_address2',
					'alternate_name',
					'alternate_phone',
					'alternate_email',
					'alternate_position',
					'subscribed_newsletter',
					'delivery_address1',
					'delivery_city',
					'delivery_state',
					'delivery_postcode',
					'delivery_country',
					'website',
					'newsletter',
					'password_confirm',					
					'trans_logo',
					'software_status',
					'brand_location',
					'brand_position',
					'logo_stamp',
					'stamp_align',
					'swatch_status',
					'swatch_count',
					'swatch_date',
					'pro_application',
					'internal',
					'account_name',
					'bsb',
					'account_number',
					'registered_gst',
					'order_notify',
					'allow_promotion',
					'phone',
					'ip_address'
				),
				'constraints' => array(
					'email' => array(
						array('EmailAddress', 'Invalid email address'),
						array('Db_NoRecordExists', 'Email address already exist', array('table' => 'common_users', 'field' => 'email')),
					),
					'password' => array(
						array('StringLength', 'Your password must be between 6 to 20 characters', array(6, 20)),
					),
					'password_confirm' => array(
						array('InArray', 'Confirm password does not match', array(array($password))),
					),
					'terms' => array(
						array('InArray', 'You must read and agree to the Terms and Conditions', array(array('1'))),
					),
//					'phone' => array(
//						array('Regex', 'Invalid phone number', array('pattern' => '/[^0-9\+]/')),
//					),
//					'phone_areacode' => array(
//						array('Regex', 'Invalid phone areacode', array('pattern' => '/[^0-9\+]/')),
//					),
				),

			),

			// update
			'update' => array(
				'required' => array(
					'id'         => 'Missing common user ID',
					'email'      => 'Enter your email address',
					'first_name' => 'Enter your first name',
					'last_name'  => 'Enter your last name',
				),
				'optional' => array(
				    	'users_salespeople_id',
					'code',
					'platinum_account',
					'account',
					'discount_percentage',
					'company',
					'abn',
					'phone',
					'phone_areacode',
					'mobile',
					'delivery_address1',
					'delivery_address2',
					'delivery_city',
					'delivery_state',
					'delivery_postcode',
					'delivery_country',
					'password',
					'reason_no_abn',
					'password_confirm',
					'alternate_name',
					'alternate_phone',
					'alternate_email',
					'alternate_position',
					'website',
					'stamp_align',
					'trans_logo',
					'software_status',
					'brand_location',
					'brand_position',
					'logo_stamp',
					'stamp_align',
					'swatch_status',
					'swatch_count',
					'swatch_date',
					'facebook_user_id',
					'facebook_product_id',
					'pro_application',
					'internal',
					'account_name',
					'bsb',
					'account_number',
					'registered_gst',
					'order_notify',
					'allow_promotion',
					'ip_address',
				),
				'constraints' => array(
					'email' => array(
						array('EmailAddress', 'Invalid email address'),
						array('Db_NoRecordExists', 'Email address already exist', array(
							'table'   => 'common_users',
							'field'   => 'email',
							'exclude' => array(
								'field' => 'id',
								'value' => $id,
							),
						)),
					),
					'discount_percentage'=> array(
						array('Between', 'Must be percentage value', array(0, 100)),
						array('Digits', 'Must be numeric value'),
					),
				),
			),
		);
		
		// conditional constraints
		if(isset($data['account_number'])){
			$profile['update']['constraints']['account_number'] = array(
				array('Digits', 'Invalid account number'),
			);
		}
		
		if(isset($data['bsb'])){
			$profile['update']['constraints']['bsb'] = array(
				array('StringLength', 'BSB must be 6 digit', array(6, 6)),
				array('Digits', 'Invalid BSB number'),
			);
		}
		
		if(isset($data['change_password']) && $data['change_password'] == 1 ) {
			$profile['update']['constraints']['password'] = array(
				array('StringLength', 'Your password must be 6 characters or more', array(6, 15)),
			);
			$profile['update']['constraints']['password_confirm'] = array(
				array('InArray', 'Confirm password does not match', array(array($password))),
			);
		}
		
		
		if(isset($data['mobile']) && strlen($data['mobile']) > 0){
			$profile['update']['constraints']['mobile'] = array(
				array('Digits', 'Only numbers allowed'),
			);
		}
		if(isset($data['phone_areacode']) && strlen($data['phone_areacode']) > 0){
			$profile['update']['constraints']['phone_areacode'] = array(
				array('Digits', 'Only numbers allowed'),
			);
		}
		if(isset($data['phone']) && strlen($data['phone']) > 0){
			$profile['update']['constraints']['phone'] = array(
				array('Digits', 'Only numbers allowed'),
			);
		}
		if(isset($data['alternate_phone']) && strlen($data['alternate_phone']) > 0){
			$profile['update']['constraints']['alternate_phone'] = array(
				array('Digits', 'Only numbers allowed'),
			);
		}
		
		return $profile;
	}

	/**
	 * Stuff to do after validation
	 */
	public function postValidate() {
		
		$password = $this->getValidated('password');
		if (isset($password)) {
			if ($password == '') {
				$this->unsetValidated('password');

			} else {
				$this->setValidated('password', sha1($password));
			}
		}
		return true;
	}

	/**
	 * Fetch a user record by auth (email & password). Note performs SHA1 on the password
	 *
	 * @param string $email The user's email address
	 * @param string $password The user's password in clear text
	 * @return mixed $user Common_Users_Row object, otherwise null
	 */
	public function fetchByAuth($email, $password) {

		if (empty($email) || empty($password)) return null;

		$select = $this->select()
		               ->where('email = ?', $email)
		               ->where('password = ?', sha1($password));
		$user = $this->fetchRow($select);

		return $user;
	}

	/**
	 * Get the newly created MOM user that is an applicant for MOP. Mainly used in the
	 * Mometo Pro registration page.
	 *
	 * @return object $user Common_Users_Row
	 */
	public function fetchMopApplicant() {

		Zend_Loader::loadClass('Zend_Session_Namespace');
		$userSession = new Zend_Session_Namespace('userProfileNamespace');
		$id   = $userSession->applicant_users_id;
		$user = $this->fetchById($id);

		return $user;
	}
	
	/**
	 * Return the total user count.
	 *
	 * @param array $sub_ids OPTIONAL an array of sub ids
	 */
	public function registeredCount($sub_ids = array()) {
		
		$db     = Zend_Registry::get('db');
		$sql    = "SELECT COUNT(id) FROM common_users";
		
		// filter the sub ids if requested
		if (!empty($sub_ids)) {
			$sql .= " WHERE sub_id IN (" . implode(",", $sub_ids) . ")";
		}
		
		$count = $db->fetchOne($sql);
		
		return $count;
	}
	/**
	 * returns the count of newly added subscribers between dates
	 *
	 * @param string $date_start YYYY-MM-DD date format
	 * @param string $date_end YYYY-MM-DD date format
	 * @param array $sub_ids OPTIONAL filter sub ids
	 * @param boolean $internal OPTIONAL filter internal
	 */
	public function registeredBetween($date_start, $date_end, $sub_ids = array(), $internal = null) {
		
		// include the hours, minutes and seconds on the date range
		$date_start = $date_start . ' 00:00:00';
		$date_end   = $date_end   . ' 23:59:59';
		
		// handle caching. Only if NOT within the current date
		$cache     = Zend_Registry::get('cache');
		$key       = __METHOD__ . '_' . $date_start . '_' . $date_end . '_' . implode("_", $sub_ids) . '_' . $internal;
		$cache_key = Base_Utils_String::slug($key);
		if ($date_end < date('Y-m-d', time()) && $data = $cache->load($cache_key)) {
			return $data;
		}
		// get database adapter
		$db = Zend_Registry::get('db');
		

		// define select query
		$select = $db->select()
		             ->from(array('cu' => 'common_users'), array('count' => 'COUNT(cu.id)'))
		             ->where('cu.date_created >= ?', $date_start)
		             ->where('cu.date_created <= ?', $date_end);
		
		if (!empty($sub_ids)) {
			$select->where('cu.sub_id IN (?)', $sub_ids);
		}
		
		if($internal != '') {
			$select->where('cu.internal = ?', $internal);
		}
		
		$count = $db->fetchOne($select);
		$cache->save($count, $cache_key);
		
		return $count;
	}	 
}
