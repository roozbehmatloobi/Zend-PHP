<?php
/**
 *
 * @category     App
 * @package      App_Models
 * @subpackage   App_Models_Row
 */
require_once 'Base/Db/Table/Row.php';

/**
 * Row class for Brands model
 *
 * @category   App
 * @package    App_Models
 * @subpackage App_Models_Row
 */
class Brands_Row extends Base_Db_Table_Row {

	/**
	 * Subs belonging to this brand
	 *
	 * @var object $_subs Subs_Row
	 */
	protected $_subs = null;

	/**
	 * Returns an object of Subs_Rowset of subs belonging to this brand
	 *
	 * @return object $this->_subs Subs_Rowset
	 */
	public function subs() {

		if (!$this->_subs) {
			$subModel    = Base::getModel('Subs');
			$select      = $subModel->select()->where('brand_id = ?', $this->id);
			$this->_subs = $subModel->fetchAll($select);
		}

		return $this->_subs;
	}

	 /**
	  * Check if this brand has any subs
	  *
	  * @returns boolean True if this brand has subs, otherwise false
	  */
	 public function hasSubs() {
		 return $this->subs()->count() > 0 ? true : false;
	 }

	 /**
	  * Returns the License object this brand belongs to
	  *
	  * @return object License_Row
	  */
	 public function license() {
		 return $this->findParentRow('Licenses');
	 }
	 
	public function hasPreview() {
		return (!$this->image || $this->image == '') ? false : true;
	}

	public function previewImage($width = '50', $height = '50') {
		
		$image = $this->hasPreview() ? $this->image : 'spacer.gif';
		$url   = '/affiliate/uploads/thumbs/?src=/images/' . $image . '&w='.$width.'&h='.$height .'&zc=0';
		return $url;
	}
}
