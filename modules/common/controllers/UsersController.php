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

/**
 * Index controller class
 *
 * @package    Common
 * @subpackage Common_Controllers
 */
class Common_UsersController extends Modules_Common_Controller_Action {

	/**
	 * Controller action to show all common users the current logged in user has access to
	 */
	public function indexAction() {

		$request = $this->getRequest();
		$user    = $this->loggedInUser();

		// prepare select
		$cuModel = Base::getModel('Common_Users');
		$select  = $cuModel->select();

		// filter: keywords
		if ($keywords_users = $request->getParam('keywords_users')) {
			
			// filter leading and trailing spaces
			$keywords_users = trim($keywords_users);
		
			if (is_numeric($keywords_users)) {
				$select->where('id = ?', $keywords_users); // ID search

			} else if (strpos($keywords_users, '@') !== false) {
				$select->where('email LIKE ?', "%$keywords_users%"); // email search

			} else if (strpos($keywords_users, '-') !== false) {
				$select->where('date_created LIKE ?', "%$keywords_users%"); // date created
			
			} else if (strpos($keywords_users, 'platinum_account:') !== false) {
				$user_search = str_replace('platinum_account:', '', $keywords_users);
				$select->where('platinum_account = ?', "$user_search");
				
			} else if (strpos($keywords_users, 'account:') !== false) {
				$user_search = str_replace('account:', '', $keywords_users);
				if (empty($user_search)) {
					$select->where("account = '' OR account IS NULL");
				} else {
					$select->where('account = ?', "$user_search");
				}
			} else { // everything else
				
				$select = ' (first_name LIKE "%'.$keywords_users.'%" OR last_name LIKE  "%'.$keywords_users.'%"'
					  .' OR company LIKE  "%'.$keywords_users.'%")';
				
				$name_parts = explode(" ", $keywords_users, 2);
				
				if (sizeof($name_parts) > 1) {
					$select .= ' OR (first_name LIKE \'' . $name_parts[0] . '%\' AND last_name LIKE \'' . $name_parts[1] . '%\')';
				}
				
				$platinum = $request->getParam('platinum');
				if (isset($platinum)){
					$select .= ' AND platinum_account = '. $request->getParam('platinum');
					
				}
				
			}
		}
		
		$order_notify = $request->getParam('order_notify');
		if ($order_notify) {
			$select->where('order_notify = ?', $order_notify);	
		}
		
		// filter sub
		if (gettype($select)=="string") {
			
			$sub_id = $request->getParam('sub_id');
			if ($sub_id) {
				$subIds = $user->subIds($sub_id);
				if(count($subIds) == 0)
					$subIds = 0;
				$select .= ' AND (sub_id IN ('. implode(",", $subIds).'))';				
			}else {
				$select .= ' AND sub_id IN ('. implode(",", $user->subs()->inField('id')).')';
			}			
		}else {
			$sub_id = $request->getParam('sub_id');
			if ($sub_id) {
				$subIds = $user->subIds($sub_id);
				if(count($subIds) == 0)
					$subIds = 0;
				$select->where('sub_id IN (?)', $subIds);				
			}else {
				$select->where('sub_id IN (?)', $user->subs()->inField('id'));
			}
			
			// set the order
			$select->order('id DESC');
		}
		
		// Handle pagination
		$this->view->filter = true;
		$page      = $request->getParam('page', 1);
		
		if (gettype($select)=="string") {
			$select = $cuModel->select()->where($select)->order('id DESC');
		}

		$paginator = Zend_Paginator::factory($select);
		$paginator->setItemCountPerPage(20);
		$paginator->setCurrentPageNumber($page);
		
		$this->view->title = "Users : Page ".$page;
		$this->view->common_users = $paginator;
		
		// subs
		$this->view->subs = $user->subs();
		
		$this->view->data = $request->getParams();
	}
	
	public function cartActiveAction() {
		
		$this->_helper->viewRenderer->setNoRender(true);
		
		echo "Checking cart...<br />";
		
		$cuModel = Base::getModel('Common_Users');
		$select  = $cuModel->select()
		                   ->where('cart_data != ?', '')
		                   ->where('cart_data IS NOT NULL');
		
		$common_users = $cuModel->fetchAll($select);
		
		foreach($common_users as $common_user) {
			
			$cart_array = unserialize($common_user->cart_data);
			if(count($cart_array['items']) > 0) {
				
				$found = false;
				
				foreach($cart_array['items'] as $cart_item) {
				
					$product_id    = $cart_item['product_id'];
					$size_id       = $cart_item['size_id'];
					$cover_type_id = $cart_item['cover_type_id'];
				
					if (empty($size_id) || empty($cover_type_id) || empty($product_id)) {
						$found = 1;
					}
					
					
					
				}
				
				if($found) {
					echo "{$common_user->id}<br />";
					//Zend_Debug::dump(unserialize($common_user->cart_data));
					//echo "------------------------------------------------------------------<br /><br />";
				}
			}
		}
		
		echo "DONE...<br />";

		
	}

	/**
	 * Controller action to edit or update a common user
	 */
	public function editAction() {

		// initialise action varibles
		$request     = $this->getRequest();
		$system_user = $this->loggedInUser();

		$cdModel = Base::getModel('Client_Directories');
		
		// get the common user object
		$common_user_id = $request->getParam('id');
		$cuModel        = Base::getModel('Common_Users');
		$common_user    = $cuModel->fetchById($common_user_id);
		
		if ($common_user->sub_id != $request->getParam('sub_id') && $request->getParam('sub_id')!=null){
			$common_user->cart_data = '';
			$common_user->sub_id = $request->getParam('sub_id');
			$common_user->save();
		}
		
		if (!$common_user) {
			$this->addFlash('Invalid user.', 'notice');
			$this->_redirect('/common/users', array('exit' => true));		
		}
		$this->view->profile = $common_user;
		$this->view->source  = 'User '. $common_user->id ;
		$this->view->data    = $common_user->toArray();
			
		// validate that the logged in user has access to this user via sub
		if (!$system_user->access($common_user->sub_id)) {
			$this->addFlash('You do not have access to this record. Please contact site administrator.', 'notice');
			$this->_redirect('/common/users', array('exit' => true));
		}
		$this->view->subs = $system_user->subs();
					
		$this->view->data['directory'] = $common_user->directory();
		
		// users for sales people id
		//$customers_service[0] = 'None';
		// include 'none' option because not all will have a sales_people_id
		//$usrModel = Base::getModel('Users')
					  
		$this->view->customers_service_users = $this->getInternalUsers(5);
		
		//if there is a CS assigned, and not in the active CS list,
		// add to customers_service_users list
		if(!array_key_exists($common_user->users_salespeople_id, $this->getInternalUsers(5)) && $common_user->users_salespeople_id != NULL) {
			$usrModel = Base::getModel('Users');
			$user	  = $usrModel->fetchById($common_user->users_salespeople_id);
			if ($user) {
				$this->view->customers_service_users[$common_user->users_salespeople_id] = $user->fullname();	
			}
		}
		
		$this->view->customers_service = $this->InternalUsers();
		$this->view->pro_applications = array(
			''              => '',
			'Applied'       => 'Applied',
			'Approved'      => 'Approved',
			'Rejected'      => 'Rejected',
			'De-activated'  => 'De-activated',
			'Contacted'     => 'Contacted',
			'Awaiting info' => 'Awaiting info',
		);
		
		// get all the questionnaire question from the sub the common user belongs to
		//$cqqModel  = Base::getModel('Client_Questionnaires_Questions');
		//$where     = $cqqModel->select()->where('sub_id = ?', $common_user->sub()->id);
		//$questions = $cqqModel->fetchAll($where);
		//$this->view->questions = $questions;
		$this->view->answers = $common_user->questionnaireAnswers();
		
		// get notes for this user
		$notes = Base::getModel('Notes');
		$notes_data = array('common_users' => $common_user->id);
		$this->view->notes = $notes->fetchNotes($notes_data);
		$this->view->sub   = Base::siteSub();
		
		// get custom branding information
		$aData = array();
		$OptionModel = Base::getModel('Catalogue_Attributes_Options');
		$select      = $OptionModel->select()->where('catalogue_attribute_id in(17,18)');
			
		$Options 	 = $OptionModel->fetchAll($select);
			
		if($Options){
			$aData = array(17 =>array(''=>''), 18 => array(''=>''));
			foreach($Options as $Option){
				if(isset($aData[$Option['catalogue_attribute_id']])){
					$aData[$Option['catalogue_attribute_id']][$Option['name']] = $Option['name'];
				} else {
					$aData[$Option['catalogue_attribute_id']] = array($Option['name'] => $Option['name']);
				}
			}
		}
		$this->view->custom_branding = $aData;
		$this->view->prostatus = array('Applied'=>'Applied','Approved'=>'Approved','Rejected'=>'Rejected',
					       'De-activated'=>'De-activated','Contacted'=>'Contacted');
		
		// select options for 'No ABN reason'
		$arrReasonNoABN = array(
			'Select...',
			'You do not conduct a business or enterprise in Australia',
			'These transactions are private or domestic in nature',
			'These transactions are part of a hobby or recreational pursuit',
			'None of the above reasons apply'
		);
		$this->view->reason_no_abn = $arrReasonNoABN;
		
		// form has been submitted
		if ($request->isPost()) {
			
			$data = $request->getParams();
			
			$bsb  = $request->getParam('bsb');
			if (empty($bsb)) unset($data['bsb']);
	
			$account_number = $request->getParam('account_number');		
			if (empty($account_number)) unset($data['account_number']);
			
			// last minute values
			$data['directory']['common_user_id'] = $common_user->id;

			// handle questionnaire answers if there is any
			if (isset($data['answers'])) {
				$cqaModel = Base::getModel('Client_Questionnaires_Answers');
				foreach ($data['answers'] as $key => $answer) {
					$answer_data['id'    ] = $key;
					$answer_data['answer'] = $answer;
					$updated = $cqaModel->process($answer_data, 'update');
				}
			}
			
			// validate user information
			
			$valid_common_user = $cuModel->validate($data, 'update');
			
			// validate directory
			//if ($common_user->hasDirectory()) {
			//	
			//	$data['directory']['id'] = $common_user->directory()->id;
			//	// special case where it's possible that the data here is partial from the
			//	// registration form. we still want to be able to update the form without
			//	// removing validations.
			//	foreach($data['directory'] as $key => $value) {
			//		if ($data['directory'][$key] == '') unset($data['directory'][$key]);
			//	}
			//
			//	
			//	$valid_client_directory = $cdModel->validate($data['directory'], 'update', array_keys($data['directory']));
			//}
			
			$valid_client_directory = $common_user->hasDirectory() ? $cdModel->validate($data['directory'], 'update') : true;
			
			
			if ($valid_common_user && $valid_client_directory) {
				
				// change status where required
				if ($data['sub_id'] != $common_user->sub_id) {
					$common_user->changeSub($data['sub_id']);
					$common_user    = $cuModel->fetchById($common_user_id);
					$common_user->changeNewsletterSub();
				}
				
				$updated_common_user = $cuModel->process($data, 'update');
                
                // set newsletter subscriptions if pro application has changed
                if ($data['pro_application'] != $common_user->pro_application && $updated_common_user) {
                    
                    // subscribe to the newsletter
                    if ($data['pro_application'] == 'Approved') {
                        $common_user->subscribeNewsletter();
                        $this->addFlash('User has been subscribed to the newsletter .', 'notice');
                    
                    // un-subscribe to the newsletter
                    } else if ($data['pro_application'] == 'De-activated') {
                        $common_user->unSubscribeNewsletter();
                        $this->addFlash('User has been un-subscribed to the newsletter ', 'notice');
                    }                    
                }                

                $updated_client_directory = $common_user->hasDirectory() ? $cdModel->process($data['directory'], 'update', array_keys($data['directory'])) : true;
				
				// update the directory listings if the user has one
				if ($updated_common_user && $updated_client_directory) {
					$this->addFlash('Client record updated.', 'notice');

					// redirect to itself
					$this->_redirect('/common/users/edit/id/' . $common_user->id, array('exit' => true));

				} else {
					// we should never get to this point. just to be safe we'll throw an exception.
					throw new Exception('Fatal error in common user edit.');
				}

			} else {
				$this->addflash('error updating client. correct form below.', 'errors');
				$this->view->errors = $cuModel->getErrors();
				$this->view->errors['directory'] = $cdModel->getErrors();
				$this->view->data   = $request->getparams();
				return;
			}
		}

	}

	/**
	 * Controller action to autologin to a common users account
	 */
	public function autologinAction() {

		$request   = $this->getRequest();
		$id        = $request->getParam('id');
		$userModel = Base::getModel('Common_Users');
		$user      = $userModel->fetchById($id);

		// validation when a system user uses the client auto login. This makes sure that the
		// currently logged in user has access to the client trying to access based on the sub
		$user_sub = $this->loggedInUser()->subs()->inField('code');
		if (!$user || !in_array($user->sub()->code, $user_sub)) {
			die('Invalid user.');
		}
		
		$portal_authcode = $user->genPortalAuthcode();
		$redirect_url    = 'http://' . $user->sub()->domain . '/client/auth/portal/uid/' . $user->id . '/auth/' . $portal_authcode;
		$this->_redirect($redirect_url, array('exit' => true));
		exit();
	}

	/**
	 * Controller action to reset a common user password
	 */
	public function resetpasswordAction() {

		// Initialise action variables
		$request   = $this->getRequest();
		$id      = $request->getParam('id');
		$password_reset  = $request->getParam('password_reset');

		// make sure that there is a cuid and authcode paramaters
		if (!$password_reset) die('Error. Missing paramaters.');

		// get the common user
		$userModel = Base::getModel('Common_Users');
		$where     = $userModel->select()->where('id = ?' , $id);
		$user      = $userModel->fetchRow($where);
		$reseted   = $userModel->process($request->getParams(), 'resetpassword');

		if (!$reseted) {
			$json = Zend_Json::encode(array('1', $userModel->getErrorsInJs(), null));

		} else {
			$json = Zend_Json::encode(array('0', 'Ok', $user->resetPassword($password_reset)));
		}

		// display on screen
		$this->_helper->viewRenderer->setNoRender(true);
		$this->getResponse()->setHeader('Content-Type','text/html');
		$this->getResponse()->setBody($json);
	}

	/**
	 * Controller action to make adjustments to a common users credit
	 */
	public function adjustCreditAction() {

		// init variables
		$request    = $this->getRequest();
		$user       = $this->loggedInUser();
		$crdModel   = Base::getModel('Client_Credits');
		$comModel   = Base::getModel('Common_Users');
		$commonUser = $comModel->fetchById($request->getParam('id'));

		if (!$commonUser) die('invalid common user');

		$amount   = $request->getParam('amount');		
		$comment  = $request->getParam('comment');		
		$variance = $request->getParam('variance');
		if ($variance == '+') {
			$commonUser->credits()->add($amount, $comment, $user->id);
		
		} else {
			$commonUser->credits()->deduct($amount, $comment, $user->id);
		}
		$json = Zend_Json::encode(array('0', 'Ok', $commonUser->credits()->available()));

		 // display on screen
		 $this->_helper->viewRenderer->setNoRender(true);
		 $this->getResponse()->setHeader('Content-Type','text/html');
		 $this->getResponse()->setBody($json);
	 }

	/**
	 * Controller action to get the credit history from a common user using JSon
	 */
	public function creditsJsonAction() {

		// initialise variables
		$request    = $this->getRequest();
		$user       = $this->loggedInUser();
		$crdModel   = Base::getModel('Client_Credits');
		$comModel   = Base::getModel('Common_Users');
		$commonUser = $comModel->fetchById($request->getParam('id'));

		if (!$commonUser) die('invalid common user');

		// prepare the array to be converted to JSon
		foreach($commonUser->credits()->transactions() as $credit) {
			$results[] = array(
				$credit->id,
				sprintf("$%.2f", $credit->amount),
				sprintf("<span style=\"color:red\">$%.2f</span>", $credit->remaining),
				date("d M Y h:ia", strtotime($credit->created)),				
				$credit->user()->fullname(),
				nl2br($credit->comment),
				
			);
		}

		 // display on screen
		 $this->_helper->viewRenderer->setNoRender(true);
		 $this->getResponse()->setHeader('Content-Type','text/json');
		 $this->getResponse()->setBody(Zend_Json::encode($results));
	 }

	/**
	 * Controller action to get user delivery address of the common user 
	 */
	 public function deliveryAddressAction() {

		 // initialise variables
		$request  = $this->getRequest();
		$user_id  = $request->getParam('user_id');
		$usrModel = Base::getModel('Common_Users');
		$user	  = $usrModel->fetchById($user_id);
		$this->view->common_user = $user;
	 }
	 
	 public function clearCartAction() {
		
		$request  = $this->getRequest();
		$user_id  = $request->getParam('user_id');
		$cuModel  = Base::getModel('Common_Users');
		$select   = $cuModel->select()->where('id = ?', $user_id);
		$common_user = $cuModel->fetchRow($select);
		$common_user->cart_data = '';
		$common_user->sub_id = $request->getParam('sub_id');
		$common_user->save();
		
		$this->addFlash('The cart data is cleared.', 'notice');
		$this->_redirect('/common/users/edit/id/'.$user_id, array('exit' => true));
		
	 }
}
