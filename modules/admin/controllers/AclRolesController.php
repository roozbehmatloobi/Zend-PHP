<?php
/**
 * 
 * Admin AclRole Controller
 *
 * @category     Admin
 * @package      Admin_Controllers
 
 */

/**
 * Include needed classes
 */
require_once 'Base/Controller/Action.php';
require_once 'Zend/Paginator.php';
/**
 * Class for managing ACL Roles
 *
 * @category  Admin
 * @package   Admin_Controllers
 * @copyright Copyright (c) 2009. Globalphotobooks Pty Ltd. (http://www.globalphotobooks.com)
 */
class Admin_AclRolesController extends Base_Controller_Action {

	/**
	 * List of models that is going to be used in this controller.
	 *
	 * @var array $_models
	 * @deprecated Call the model using Base. E.g. Base::getModel('AclRoles')
	 */
	protected $_models = array('AclRoles', 'AclRules');
	
	/**
	 * List of available ACL Resources with modules, controllers, and actions.
	 *
	 * @var array $_acl_resources
	 */
	protected $_acl_resources = null;

	/**
	 * Display list of ACL Roles in the system.
	 */
	public function indexAction() {

		$request  = $this->getRequest();
		$acrModel = Base::getModel('AclRoles');
		$where    = $acrModel->select()->order('id ASC');
		
		$paginator = Zend_Paginator::factory($where);
		$paginator->setItemCountPerPage(10);
		$paginator->setCurrentPageNumber($request->getParam('page'));
		
		$aclroles = $paginator;
		
		$this->view->aclroles = $aclroles;
	}

	/**
	 * Add a new ACL Role
	 */
	public function addAction() {
	
		$request = $this->getRequest();
		$this->view->aclresources = $this->getAclResources();

		
		if ($request->isPost()){
			if (!$saved = $this->AclRoles->process($request->getParams(), 'insert')) {
				$this->addFlash('Error adding new ACL role. Correct form below.', 'errors');
				$this->view->errors = $this->AclRoles->getErrors();
				$this->view->data = $request->getParams();
			
			} else {
				$this->addFlash('Created new ACL Role.', 'notice');
				$this->_redirect($this->view->base_url.'/admin/acl-roles');
			}
		}
		return;
	}

	/**
	 * Display/Update ACL Role record.
	 */
	public function editAction() {
	
		$request   = $this->getRequest();
		$aclRoleId = $this->filter($request->getParam('id'));
		$this->view->aclresources = $this->getAclResources();
		
		if ($request->isPost()) {
			$updated = $this->AclRoles->process($request->getParams(), 'update');
			if (!$updated) {
				$this->addFlash('Error updating ACL role. Correct form below.', 'errors');
				$this->view->errors = $this->AclRoles->getErrors();
				$this->view->data   = $request->getParams();
			
			} else {
				$this->addFlash('ACL Role updated.', 'notice');
				$this->_redirect($this->view->base_url.'/admin/acl-roles');
			}
			
		} else {
			$aclRole = $this->AclRoles->fetchById($aclRoleId);
			if ($aclRole) {
				$roleData = $aclRole->toArray();
				$ruleData = $this->AclRules->rules2Form($aclRoleId);
				$this->view->data = array_merge($roleData, $ruleData);
			
			} else {
				$this->addFlash('Invalid ACL Role.', 'errors');
				$this->_redirect($this->view->base_url.'/admin/acl-roles');
			}
		}
	}

	/**
	 * Delete an ACL Role record. Redirects back to ACL Role index page.
	 */
	public function deleteAction() {
	
		$request   = $this->getRequest();
		$aclRoleId = $this->filter($request->getParam('id'));
		$deleted   = $this->AclRoles->deleteById($aclRoleId);
		
		if ($deleted) {
			$this->addFlash('ACL Role has been deleted.', 'notice');
		
		} else {
			$errors = $this->AclRoles->getErrors();
			$this->addFlash($errors['action']['delete'][0], 'errors');
		}
		
		$this->_redirect($this->view->base_url.'/admin/acl-roles');
	}
	
	/**
	 * Returns available ACL Resources.
	 *
	 * @return array List of ACL resources
	 */
	protected function getAclResources() {
		
		if (!$this->_acl_resources) {
			Zend_Loader::loadClass('Base_Acl_Resources');
			$aclResObj = new Base_Acl_Resources();
			$aclResObj->addIgnoreModule('default');
			$resources = $aclResObj->getResources(APPLICATION_PATH);
			$this->_acl_resources = $resources;
		}
		
		return $this->_acl_resources;
	}

	/**
	 * Controller action view role
	 *
	 * @todo Allow to view role.
	 */
	public function viewAction() {
	
		$request   = $this->getRequest();
		$aclRoleId = $this->filter($request->getParam('id'));
		$this->view->aclresources = $this->getAclResources();
		$aclRole = $this->AclRoles->fetchById($aclRoleId);
		if ($aclRole) {
			$roleData = $aclRole->toArray();
			$ruleData = $this->AclRules->rules2Form($aclRoleId);
			$this->view->data = array_merge($roleData, $ruleData);
		} else {
			$this->addFlash('Invalid ACL Role.', 'errors');
			$this->_redirect($this->view->base_url.'/admin/acl-roles');
		}
	}
}
?>
