<?php
/**
 *
 * AclRules model
 *
 * @category     App
 * @package      App_Models

 */

/**
 * AclRules model class
 *
 * @category App
 * @package  App_Models
 */
class AclRules extends Base_Db_Table {

	/*
	 * Name of the database table we are going to use for this model.
	 *
	 * @var string
	 */
	protected $_name = 'acl_rules';

	/**
	 * Specify a custom Row to be used by default in all instances of a Table class
	 *
	 * @var string $_rowClass Custom row name
	 */
	//protected $_rowClass = 'AclRules_Row';

	/**
	 * Specify a custom Row to be used by default in all instances of a Table class
	 *
	 * @var string $_rowClass Custom row name
	 */
	//protected $_rowsetClass = 'AclRules_Rowset';

	/**
	 * List of class name for each dependent table
	 *
	 * @var array $_dependentTables
	 */
	protected $_dependentTables = array();

	/**
	 * State our moldel relationships
	 *
	 * @var array $_referenceMap
	 */
	protected $_referenceMap = array();


	/**
	 * Validate, filters, and option rules we are applying against the data
	 *
	 * @return array $profile Validation profile
	 */
	public function profile() {

		$profile = array(
			'insert' => array(
				'required' => array(
					'access'      => 'Missing access type',
					'module'      => 'Missing module name',
					'controller'  => 'Missing controller name',
					'action'      => 'Missing controller action',
				),
				'optional'    => array('acl_role_id'),
				'constraints' => array(),
			),
		);

		return $profile;
	}
	
	/**
	 * Description here
	 * Format: resource::{moduleName}::{ControllerName}::{actionName}
	 *
	 * @param string ACL rule ID
	 * @return array Array of associated roles
	 */
	public function rules2Form($role_id) {

		$where   = $this->getAdapter()->quoteInto('acl_role_id = ?', $role_id);
		$rules   = $this->fetchAll($where);
		$results = array();

		foreach ($rules as $rule) {
			$module     = $rule->module;
			$controller = $rule->controller;
			$action     = $rule->action;
			$results["resource::$module::$controller::$action"] = 1;
		}
		return $results;
	}
}
