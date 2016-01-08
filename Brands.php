<?php
/**
 * 
 * Brands model
 *
 * @category     App
 * @package      App_Models
 */

/**
 * Include need classes
 */
require_once 'Base/Db/Table.php';
require_once 'Zend/Json.php';

/**
 * Model class for Brands
 *
 * @category App
 * @package  App_Models
 */
class Brands extends Base_Db_Table {

	/**
	 * Name of the database table we are going to use for this model
	 *
	 * @var string $_name Name of the table this model uses
	 */
	protected $_name = 'brands';

	/**
	 * Specify a custom Row to be used by default in all instances of a Table class
	 *
	 * @var string $_rowClass Custom row name
	 */
	protected $_rowClass = 'Brands_Row';
	
	/**
	 * Specify a custom Row to be used by default in all instances of a Table class
	 *
	 * @var string $_rowClass Custom row name
	 */
	protected $_rowsetClass = 'Brands_Rowset';

	/**
	 * List of class name for each dependent table
	 *
	 * @var array $_dependentTables
	 */
	protected $_dependentTables = array();

	/**
	 * HasA relationships
	 *
	 * @var array $_referenceMap
	 */
	protected $_referenceMap = array(
		'License' => array(
			'columns'       => array('license_id'),
			'refTableClass' => 'Licenses',
			'refColumns'    => array('id')
		),
	);

	/**
	 * Model profile
	 *
	 * @return array $profile Validation profile
	 */
	public function profile() {

		// init variables
		$profile = array();

		$profile['insert'] = array(
			'required' => array(
				'license_id'         => 'Select license',
				'name'               => 'Enter name',
				'code'               => 'Enter software code',
				'dpi'                => 'Enter dpi',
				'encryption_key'     => 'Enter encryption key',
				'preview_size'       => 'Enter preview size',
				'canvas_size'        => 'Enter canvas size',
				'print_image_scale'  => 'Enter print image size',
				'print_given_scale'  => 'Enter print given scale',
				'max_image_size'     => 'Enter maximum image size',
				'frame_size'         => 'Enter frame size',
				'description'        => 'Enter description',
			),
			'optional' => array('id', 'image'),
			'constraints' => array(),
		);

		// the same for now
		$profile['update'] = $profile['insert'];

		return $profile;
	}

	/**
	 * Returns all brands in Json format
	 *
	 * @param array $fields OPTIONAL. Array of fields we only want to include. If empty, use all
	 * @return object Zend_Json
	 * @depricate Move to Brands_Rowset to keep codes clean. Users can use $brand->fetchAll()->inJson();
	 */
	public function inJson($fields = array()) {

		$select = $this->select()->order('name ASC');
		$brands = $this->fetchAll($select);
		$json   = array();
		foreach($brands as $brand) {
			if (empty($fields)) {
				$json[] = $brand->toArray();

			} else {
				foreach($fields as $field) {
					$data[$field] = $brand->__get($field);
				}
				$json[] = $data;
				unset($data);
			}
		}
		return Zend_Json::encode($json);
	}
	
	/**
	 * Stuff to do after validation
	 */
	public function postValidate() {
		
		$image       = $_FILES['image'];
		$allowed_ext = array('jpg', 'jpeg', 'gif', 'png');
		if ($image['tmp_name'] > '') { 
			$image_name = $image['name'];
			$parts      = explode(".", strtolower($image_name));
			$extension  = array_pop($parts);
			if (!in_array($extension, $allowed_ext)) { 
				$this->setError('image', 'Invalid image', 'invalid');
				return false;
			}
			
			$upload_dir  = realpath(APPLICATION_PATH . '/../sites/affiliate/htdocs/uploads/images');
			$filename    = uniqid('brands_', true) . '.' . $extension;
			$file        = $upload_dir . DS . $filename;

			// OK
			$success = move_uploaded_file($image['tmp_name'], $file);
			
			// if we cannot move the file there's probably a permission problem. better to 
			// throw an exception as this would be happening to everyone else.
			if (!$success) throw new Exception('Unable to move temp uploaded file.');
			
			$this->setValidated('image', $filename);

			return $success;
		}

		return true;
	}
}
