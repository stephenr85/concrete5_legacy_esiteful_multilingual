<?php  
defined('C5_EXECUTE') or die("Access Denied.");

Loader::library('3rdparty/Zend/Locale');
Loader::library('content_localization', 'multilingual');

class LocaleAttributeTypeController extends AttributeTypeController  {

	protected $searchIndexFieldDefinition = 'C 11 DEFAULT NULL NULL';

	public function getValue() {		
		$db = Loader::db();
		$value = $db->GetOne("select value from atLocale where avID = ?", array($this->getAttributeValueID()));
		return $value;
	}
	
	public function getDisplayValue(){
		$lang = $this->getValue();
		$languages = MultilingualContentLocalization::getLanguages();
		return $languages[$lang];	
	}
	
	public function getDisplaySanitizedValue(){
		return $this->getDisplayValue();	
	}
	

	public function searchForm($list) {
		$lang = $this->request('value');
		$list->filterByAttribute($this->attributeKey->getAttributeKeyHandle(), $lang, '=');
		return $list;
	}
	
	public function search() {
		print t('<input type="text" name="%s" value="%s">', $this->field('value'), $this->request('value'));
	}
	
	
	
	public function getAttributeKeyCategory(){
		return AttributeKeyCategory::getByID($this->getAttributeKey()->getAttributeKeyCategoryID());	
	}
	
	public function getAttributeKeyCategoryHandle(){
		return $this->getAttributeKeyCategory()->getAttributeKeyCategoryHandle();
	}
	
	//Get the type of class that owns this attribute 
	public function getValueOwnerClass(){
		//Check the actual owner first
		$valueOwner = $this->getValueOwner();
		if(is_object($valueOwner)){
			return get_class($valueOwner);
		}
		//Fallback to attribute category
		$attrCategoryHandle = $this->getAttributeKeyCategoryHandle();		
		if($attrCategoryHandle == 'collection'){
			$ValueOwnerClass = Page;
		}else if($attrCategoryHandle == 'file'){
			$ValueOwnerClass = File;
		}else if($attrCategoryHandle == 'user'){
			$ValueOwnerClass = UserInfo;	
		}
		return $ValueOwnerClass;
	}
	
	//Get the object that owns this attribute
	public function getValueOwner(){
		$attrCategoryHandle = $this->getAttributeKeyCategoryHandle();
		$oID = $this->getValueOwnerID();
		
		
		if($attrCategoryHandle == 'collection'){
			return Page::getByID($oID);
		}else if($attrCategoryHandle == 'file'){
			return File::getByID($oID);
		}else if($attrCategoryHandle == 'user'){
			return UserInfo::getByID($oID);	
		}
		
		/*$valueObj = $this->getAttributeValue();
		
		if($attrCategoryHandle == 'collection'){
			return $valueObj->c;
		}else if($attrCategoryHandle == 'file'){
			return $valueObj->f;
		}else if($attrCategoryHandle == 'user'){
			return $valueObj->u;	
		}*/
	}
	
	//Get the ID of the owner object
	public function getValueOwnerID(){
		if($this->valueOwnerID){
			return $this->valueOwnerID;	
		}
		
		$attrCategoryHandle = $this->getAttributeKeyCategoryHandle();		
		$valueObj = $this->getAttributeValue();
		
		//if(!is_object($valueObj)) return; //nothin'
		
		//Shouldn't have to do this statically, in case more collection types are added, but I'm not able to find the ID otherwise...
		if($attrCategoryHandle == 'collection'){
			$oID = $_REQUEST['cID'];
		}else if($attrCategoryHandle == 'file'){
			$oID = $_REQUEST['fID'];
		}else if($attrCategoryHandle == 'user'){
			$oID = $_REQUEST['uID'];
		}
		if(is_array($oID)){
			$this->set('isBulk', true);
			$oID = reset($oID);	
		}else{
			$this->set('isBulk', false);
		}
		return $oID;
	}
	
	public function form() {
		
		$pkgMultilingual = Package::getByHandle('multilingual');		

		$this->set('akcHandle', $akcHandle = $this->getAttributeKeyCategoryHandle());
		
		$this->set('ValueOwnerClass', $ValueOwnerClass = $this->getValueOwnerClass());
		
		$this->set('valueOwnerID', $valueOwnerID = $this->getValueOwnerID());
		
		$this->set('valueOwner', $valueOwner = $this->getValueOwner());		
		
		$this->set('defaultLocale', $defaultLanguage = $pkgMultilingual->config('DEFAULT_LANGUAGE'));

		if (is_object($this->attributeValue)) {
			$value = $this->getAttributeValue()->getValue();			
		}

		$sections = MultilingualSection::getList();
		$locales = array();
		foreach($sections as $section){
		    $locales[$section->getLocale()] = $section->getLanguageText();
		}
		$this->set('locales', $locales);

		
		$this->set('value', $value);
		$this->set('relations', $this->getRelations(true));
	}
	
	public function validateForm($p) {
		return $p['value'] != 0;
	}

	public function saveValue($value) {
		$db = Loader::db();
		$db->Replace('atLocale', array('avID' => $this->getAttributeValueID(), 'value' => $value), 'avID', true);
	}
	
	public function saveRelations($relations){
		
		$ValueOwnerClass = $this->getValueOwnerClass();
		$akID = $this->getAttributeKey()->getAttributeKeyID();
		$akHandle = $this->getAttributeKeyCategoryHandle();
		$relationID = $this->getRelationID();
		$db = Loader::db();
		
		$db->Execute("delete from atLocaleRelations where relationID = ?", array($relationID));
		
		if(count($relations)){
			
			$db->Replace(
				'atLocaleRelations', 
				array(
					'oID' => $this->getValueOwnerID(), 
					'akID' => $akID,
					'relationID' => $relationID				
				),
				array('oID','akID'),
				true
			);
			
			foreach($relations as $relation){
				if(!$relation['oID']) continue;
				
				$relationOwner = $ValueOwnerClass::getByID($relation['oID']);
				
				if($relation['delete'] == 'delete'){
					//do nothing, we already deleted them above
				}else{
					$db->Replace(
						'atLocaleRelations', 
						array(
							'oID' => $relation['oID'], 
							'akID' => $akID,
							'relationID' => $relationID				
						),
						array('oID','akID'),
						true
					);
					if(isset($relation['value'])){
						$relationOwner->setAttribute($this->getAttributeKey(), $relation['value']);
					}
				}

				//Set standard Multilingual page associations
				if($akHandle == 'collection'){
					//TODO:
				}
			}
		}
			
	}
	
	public function deleteKey() {
		$db = Loader::db();
		$arr = $this->attributeKey->getAttributeValueIDList();
		foreach($arr as $id) {
			$db->Execute('delete from atLocale where avID = ?', array($id));
		}
	}
	
	public function saveForm($data) {
		$db = Loader::db();
		$this->saveValue($data['value']);
		$KeyClass = get_class($this->getAttributeKey());
		$ValueClass = get_class($this->getAttributeValue());
		
		//Log::addEntry(print_r($data, true));
		
		if($data['oID']){
			$this->valueOwnerID = $data['oID'];	
		}
		
		if($data['detach'] == 1){
			$this->deleteRelation(); //Remove this from the group
			
		}else if(!$data['isBulk']){
			$relations = is_array($data['relation']) ? $data['relation'] : array();
			$this->saveRelations($relations);		
		}
		
	}
	
	public function deleteValue() {
		$db = Loader::db();
		$db->Execute('delete from atLocale where avID = ?', array($this->getAttributeValueID()));
		$this->deleteRelation();
	}
	
	public function deleteRelation(){
		$valueOwnerID = $this->getValueOwnerID();
		if($valueOwnerID){
			$akID = $this->getAttributeKey()->getAttributeKeyID();
			$db = Loader::db();
			$db->Execute('delete from atLocaleRelations where oID = ? and akID = ?', array($valueOwnerID, $akID));	
		}
	}
	
	public function getRelationID($autoCreate = true){
		$db = Loader::db();
		$valueOwnerID = $this->getValueOwnerID();
		$akID = $this->getAttributeKey()->getAttributeKeyID();
		$relationID = $db->GetOne("select relationID from atLocaleRelations where akID = ? and oID = ?", array($akID, $valueOwnerID));	
		if($relationID) {
			return $relationID;	
		}else if($autoCreate){
			$db->Execute('insert into atLocaleRelations (akID, oID) values(?, ?)', array($akID, $valueOwnerID));
			return $db->Insert_ID();	
		}
	}
	
	public function getRelations($expensive=false){
		$db = Loader::db();	
		$valueOwnerID = $this->getValueOwnerID();
		$akID = $this->getAttributeKey()->getAttributeKeyID();
		
		$rows = $db->GetAll("select oID, relationID from atLocaleRelations where relationID = (select relationID from atLocaleRelations where oID = ?) and oID != ?", array($valueOwnerID, $valueOwnerID));
	
		if($expensive){
			$ValueOwnerClass = $this->getValueOwnerClass();
		
			foreach($rows as &$row){	
				$owner = $ValueOwnerClass::getByID($row['oID']);
				$lang = $owner->getAttribute($this->getAttributeKey()->getAttributeKeyHandle());
				$row['value'] = $lang;
				$row['owner'] = $owner;
			}
		
		}
		return $rows;
	}
	
	public function getRelationsOwnerHash(){
		$rows = $this->getRelations(true);
		
		$relations = array();
		
		foreach($rows as &$row){	
			$relations[$row['value']] = $row['owner'];
		}
	
		return $relations;
	}
	
	public function print_pre($thing, $return=false){
		$out = '<pre style="white-space:pre;">';
		$out .= print_r($thing, true);
		$out .= '</pre>';
		if(!$return){
			echo $out;
			return;	
		}
		return $thing;
	}
	
}


//TODO: clean up the "value owner" mess in the controller above
class LocaleAttributeValueOwner extends Object {
	
	
	public function getAttributeKey($ak){
		
	}
	
	public function setAttributeKey($ak){
		
		if(is_numeric($ak)){
			$ak = AttributeKey::getByID($ak);	
		}else if(is_string($ak)){
			$ak = AttributeKey::getByHandle($ak);	
		}
		
		$this->attributeKey = $ak;
	}
		
}