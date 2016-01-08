<?php
/**
 *
 * Admin Brands Controller
 *
 * @category     Admin
 * @package      Admin_Controllers
 */

/**
 * Include need classes
 */
require_once 'Base/Controller/Action.php';
require_once 'Zend/Paginator.php';

/**
 * Controller class to administer brands
 *
 * @category Admin
 * @package  Admin_Controllers
 */
class Admin_BrandsController extends Base_Controller_Action {
	
	/**
	 * Initialise controller
	 */
	public function controllerInit() {
		
		// assign common view data
		$this->view->licenses = $this->getModel('Licenses')->fetchList(array('id', 'name'));
	}
	
	/**
	 * Controller action to list brand code
	 */
	public function indexAction() {
		
		$user     = $this->loggedInUser();
		$request  = $this->getRequest();
		$brnModel = Base::getModel('Brands');
		$select   = $brnModel->select();

		$license_id = $request->getParam('license-id');
		if ($license_id) {
			$select->where('license_id = ?', $license_id);
		}
		
		$paginator = Zend_Paginator::factory($select);
		$paginator->setItemCountPerPage(10);
		$paginator->setCurrentPageNumber($request->getParam('page'));
		
		$brands  = $paginator;

		$this->view->brands   = $brands;
		$this->view->licenses = $user->licenses();

		// generate the license options
		$this->view->license_options = array_merge(array('' => 'All'), $user->licenses()->inSelect());
	}

	/**
	 * Controller action to add a brand code
	 */
	public function addAction() {
		
		// Initialise action variables
		$request  = $this->getRequest();
		$brnModel = $this->getModel('Brands');

		if ($request->isPost()) {
			
			// create a new background element
			$created = $brnModel->process($request->getParams(), 'insert');
			if ($created) {
				$this->addFlash('Created new brand.', 'notice');
				$this->_redirect('/admin/brands');

			} else {
				$this->addFlash('Error creating new brand. Correct form below.', 'errors');
				$this->view->errors = $brnModel->getErrors();
				$this->view->data   = $request->getParams();
			}
		}
	}

	/**
	 * Controller action to edit a brand code
	 */
	public function editAction() {
		
		// Initialise action variables
		$request  = $this->getRequest();
		$brandId  = $this->filter($request->getParam('id'));
		$brnModel = $this->getModel('Brands');
		$brand    = $brnModel->fetchById($brandId);

		if ($request->isPost()) {
			$request->setParam('code', $brand->code );
			$request->setParam('license_id', $brand->license()->id);
			//$request->setParam('id', $page->id);
			//die();
            //$request->seParam('code', $brand->code);
            
			$updated = $brnModel->process($request->getParams(), 'update');
			if ($updated) {
				$this->addFlash('Brand updated.', 'notice');
				$this->_redirect('/admin/brands');

			} else {
				$this->addFlash('Error updating brand. Correct form below.', 'errors');
				$this->view->errors = $brnModel->getErrors();
				$this->view->data   = $request->getParams();
			}
			
			return;
		}
		
		// first time loaded
		if ($brand) {
			$this->view->data = $brand->toArray();
			$this->view->brands = $brand;
		} else {
			$this->addFlash('Invalid brand.', 'errors');
			$this->_redirect('/admin/brands');
		}
	}

	/**
	 * Controller action to delete a brand code
	 */
	public function deleteAction() {
		
		$request  = $this->getRequest();
		$brandId  = $this->filter($request->getParam('id'));
		$brnModel = $this->getModel('Brands');
		$deleted  = $brnModel->deleteById($brandId);
		if ($deleted) {
			$this->addFlash('Brand has been deleted.', 'notice');
		}
		
		$this->_redirect('/admin/brands');
	}
}
?>
