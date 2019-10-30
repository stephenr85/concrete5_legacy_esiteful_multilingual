<?php  
defined('C5_EXECUTE') or die("Access Denied.");

Loader::library('3rdparty/Zend/Locale');
Loader::library('content_localization', 'multilingual');

/*
Note that the inconsistency of locale/lang in this attribute is due to the inconsistency of the behavior from the multilingual package, when it was created. Eventually, locale will be king.
*/

class LanguageAttributeTypeController extends AttributeTypeController  {

	protected $searchIndexFieldDefinition = 'C 11 DEFAULT NULL NULL';

	public function getValue() {		
		$db = Loader::db();
		$value = $db->GetOne("select value from atLanguage where avID = ?", array($this->getAttributeValueID()));
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

	public function getExportValue(){
		$vals = [$this->getValue()];		
		$relations = $this->getRelations($expensive = true);
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		foreach($relations as $relation){
			if($akcHandle == 'collection'){
				$relation['oID'] = ContentExporter::replacePageWithPlaceHolder($relation['oID']);
			} else if($akcHandle == 'file'){
				$relation['oID'] = ContentExporter::replaceFileWithPlaceHolder($relation['oID']);
			}
			$vals[] = $relation['value'] . '=' . $relation['oID'];
		}
		return implode(',', $vals);
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
		$owner = NULL;

		//First, look for it in the AttributeValue
		$valueObj = $this->getAttributeValue();
		
		if(is_object($valueObj)){
			if($attrCategoryHandle == 'collection'){
				$owner = $valueObj->c;
			}else if($attrCategoryHandle == 'file'){
				$owner = $valueObj->f;
			}else if($attrCategoryHandle == 'user'){
				$owner = $valueObj->u;
			}
			if(is_object($owner)) return $owner;
		}

		//If We still don't have it, let getValueOwnerID try to find the ID, then use that to get an object
		$oID = $this->getValueOwnerID();
		
		if($attrCategoryHandle == 'collection'){
			$owner = Page::getByID($oID);
		}else if($attrCategoryHandle == 'file'){
			$owner = File::getByID($oID);
		}else if($attrCategoryHandle == 'user'){
			$owner = UserInfo::getByID($oID);	
		}

		return $owner;
	}
	
	//Get the ID of the owner object
	public function getValueOwnerID(){
		if($this->valueOwnerID){
			return $this->valueOwnerID;	
		}
		
		$attrCategoryHandle = $this->getAttributeKeyCategoryHandle();		
		//First, look for it in the AttributeValue
		$valueObj = $this->getAttributeValue();

		//Shouldn't have to do this statically, in case more collection types are added, but I'm not able to find the ID otherwise...
		if($attrCategoryHandle == 'collection'){
			$oID = is_object($valueObj) && is_object($valueObj->c) ? $valueObj->c->getCollectionID() : $_REQUEST['cID'];
		}else if($attrCategoryHandle == 'file'){
			$oID = is_object($valueObj) && is_object($valueObj->f) ? $valueObj->f->getFileID() : $_REQUEST['fID'];
		}else if($attrCategoryHandle == 'user'){
			$oID = is_object($valueObj) && is_object($valueObj->u) ? $valueObj->u->getUserID() : $_REQUEST['uID'];
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
		//Determine what type of owner object we're working with
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$this->set('akcHandle', $akcHandle);
		
		$ValueOwnerClass = $this->getValueOwnerClass();
		$this->set('ValueOwnerClass', $ValueOwnerClass);
		
		$this->set('valueOwnerID', $this->getValueOwnerID());
		
		$this->set('valueOwner', $this->getValueOwner());		
			
		if (is_object($this->attributeValue)) {
			$value = $this->getAttributeValue()->getValue();			
		}
		
		$pkg = Package::getByHandle('multilingual');
		
		$langs = MultilingualContentLocalization::getLanguages();
		asort($langs);
		$this->set('locales', $langs);

		$sections = MultilingualSection::getList();
		$sectionLangs = array();
		foreach($sections as $section){
		    $sectionLangs[$section->msLanguage] = $langs[$section->msLanguage];
		}
		if(!isset($sectionLangs[$value])){
			$sectionLangs[$value] = $value;
		}
		$this->set('sectionLangs', $sectionLangs);

		$this->set('defaultLanguage', $pkg->config('DEFAULT_LANGUAGE'));
		$this->set('value', $value);
		$this->set('relations', $this->getRelations(true));
	}
	
	public function validateForm($p) {
		return $p['value'] != 0;
	}

	public function saveValue($value) {
		$db = Loader::db();
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		if($akcHandle === 'collection'){
			$section = MultilingualSection::getBySectionOfSite($this->getValueOwner());
			$value = $section->msLanguage;
		}
		$db->Replace('atLanguage', array('avID' => $this->getAttributeValueID(), 'value' => $value), 'avID', true);
	}
	
	public function saveRelations($relations){
		
		$valueOwner = $this->getValueOwner();
		$valueOwnerID = $this->getValueOwnerID();
		$ValueOwnerClass = $this->getValueOwnerClass();
		$valueOwnerLang = $this->getValue();
		$ak = $this->getAttributeKey();
		$akID = $ak->getAttributeKeyID();
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$relationID = $this->getRelationID();
		$db = Loader::db();

		Loader::model('section', 'multilingual');
		$transPageHelper = Loader::helper('translated_pages', 'multilingual');

		if(!$valueOwner){
			Log::addEntry(print_r($this, true), __CLASS__);
			throw new Exception(t("Unable to set the relationships for the \"%s\" attribute. Could not determine the owner for the attribute value.", $ak->getAttributeKeyHandle()));
		}
		
		$db->Execute("delete from atLanguageRelations where relationID = ?", array($relationID));
		
		if(count($relations)){

			$db->Replace(
				'atLanguageRelations', 
				array(
					'oID' => $valueOwnerID, 
					'akID' => $akID,
					'relationID' => $relationID				
				),
				array('oID','akID'),
				true
			);
			
			foreach($relations as $relation){
				if(!$relation['oID'] || $relation['value'] == $valueOwnerLang) continue;
				
				$relationOwner = $ValueOwnerClass::getByID($relation['oID']);

				$relationLocale = $relation['value'];
				if($akcHandle == 'collection'){
					//Get locale from Multilingual section, for rigidity
					$section = MultilingualSection::getBySectionOfSite($relationOwner);
					if($section){
						$relationLocale = $section->getLocale();
					}
				}
				$relationLang = !empty($relationLocale) ? reset(explode('_', $relationLocale)) : NULL;

				if($relation['delete'] == 'delete'){
					//do nothing, we already deleted them above
					
				}else{
					//Tie these objects together
					$this->addRelation($relation['oID']);

					//Set the attribute on the other object
					if($relationLang){
						$relationOwner->setAttribute($this->getAttributeKey(), $relationLang);					
					}
				}

				
			}
		}

		if($akcHandle == 'collection'){
			$this->pushPageRelations();
		}
			
	}

	public function addRelation($otherID){
		$valueOwnerID = $this->getValueOwnerID();
		$relationID = $this->getRelationID(true);
		$ak = $this->getAttributeKey();
		$akID = $ak->getAttributeKeyID();
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		$db = Loader::db();
		$db->Execute('delete from atLanguageRelations where oID = ? and akID = ?', array($otherID, $akID));

		$db->Replace(
			'atLanguageRelations', 
			array(
				'oID' => $otherID, 
				'akID' => $akID,
				'relationID' => $relationID				
			),
			array('oID','akID'),
			true
		);
	}
	
	public function deleteKey() {
		$db = Loader::db();
		$arr = $this->attributeKey->getAttributeValueIDList();
		foreach($arr as $id) {
			$db->Execute('delete from atLanguage where avID = ?', array($id));
		}
		$akID = $this->getAttributeKey()->getAttributeKeyID();
		$db->Execute('delete from atLanguageRelations where akID = ?', array($akID));
	}
	
	public function saveForm($data) {
		$db = Loader::db();
		$this->saveValue($data['value']);
		$KeyClass = get_class($this->getAttributeKey());
		$ValueClass = get_class($this->getAttributeValue());
		$akcHandle = $this->getAttributeKeyCategoryHandle();
		
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
		$db->Execute('delete from atLanguage where avID = ?', array($this->getAttributeValueID()));
		$this->deleteRelation();
	}
	
	public function deleteRelation($oID = NULL){
		if(is_null($oID)) $oID = $this->getValueOwnerID();
		if($oID){
			$akID = $this->getAttributeKey()->getAttributeKeyID();
			$akcHandle = $this->getAttributeKeyCategoryHandle();
			$db = Loader::db();
			$db->Execute('delete from atLanguageRelations where oID = ? and akID = ?', array($oID, $akID));

			if($akcHandle == 'collection'){
				$db->Execute('delete from MultilingualPageRelations where cID = ?', array($oID));
			}
		}
	}
	
	public function getRelationID($autoCreate = true){
		$db = Loader::db();
		$valueOwnerID = $this->getValueOwnerID();
		$akID = $this->getAttributeKey()->getAttributeKeyID();
		$relationID = $db->GetOne("select relationID from atLanguageRelations where akID = ? and oID = ?", array($akID, $valueOwnerID));	
		if($relationID) {
			return $relationID;	
		}else if($autoCreate){
			$db->Execute('insert into atLanguageRelations (akID, oID) values(?, ?)', array($akID, $valueOwnerID));
			return $db->Insert_ID();	
		}
	}

	//Push the relationships from this attribute to the Multilingual package
	//This doesn't fire the "on_multilingual_page_relate", for fear of creating an infinite loop.
	public function pushPageRelations(){
		$db = Loader::db();
		$valueOwner = $this->getValueOwner();
		$ownerID = $valueOwner->getCollectionID();

		$valueOwnerSection = MultilingualSection::getBySectionOfSite($valueOwner);
		if(!$valueOwnerSection) return;

		$relationID = $this->getRelationID();
		if(!$relationID) return;

		$relationOwnerIDs = $db->GetCol("select oID from atLanguageRelations where relationID = '$relationID'");

		$mpRelationID = $db->GetOne("select mpRelationID from MultilingualPageRelations where cID = '$ownerID'");

		if (!$mpRelationID) {
			$mpRelationID = $db->GetOne('select max(mpRelationID) as mpRelationID from MultilingualPageRelations');
			if (!$mpRelationID) {
				$mpRelationID = 1;
			} else {
				$mpRelationID++;
			}	
			$v = array($mpRelationID, $valueOwner->getCollectionID(), $valueOwnerSection->getLanguage(), $valueOwnerSection->getLocale());
			$db->Execute('insert into MultilingualPageRelations (mpRelationID, cID, mpLanguage, mpLocale) values (?, ?, ?, ?)', $v);
		}
		
		
		//Delete Multilingual package relationships
		$db->Execute("delete from MultilingualPageRelations where mpRelationID = ?", array($mpRelationID));
		$db->Execute("delete from MultilingualPageRelations where cID in (". implode(',', $relationOwnerIDs) .");");

		$takenLocales = array();
		//Add to Multilingual package relationships
		foreach($relationOwnerIDs as $relationOwnerID){
			$relationOwner = Page::getByID($relationOwnerID);
			$section = MultilingualSection::getBySectionOfSite($relationOwner);
			if($section){
				$relationLang = $section->getLanguage();
				$relationLocale = $section->getLocale();
				if(!in_array($relationLocale, $takenLocales)){
					$db->Execute("insert into MultilingualPageRelations (cID, mpRelationID, mpLanguage, mpLocale) values (?,?,?,?)", array($relationOwnerID, $mpRelationID, $relationLang, $relationLocale));
					$takenLocales[] = $relationLocale;
				}			
			}
		}
		 
	}

	//Get the relationships as defined by the Multilingual package
	public function pullPageRelations(){
		$db = Loader::db();
		$relationID = $this->getRelationID();
		$akID = $this->getAttributeKey()->getAttributeKeyID();
		$db->Execute("delete from atLanguageRelations where relationID = ?", array($relationID));

		$mpRelationID = $db->GetOne("select mpRelationID from MultilingualPageRelations where cID = ?", array($this->getValueOwnerID()));
		if($mpRelationID){
			$db->Execute("INSERT INTO atLanguageRelations (oID, relationID, akID) SELECT DISTINCT(cID) AS cID, $relationID, $akID FROM MultilingualPageRelations WHERE mpRelationID = '$mpRelationID'");
		}
	}
	
	public function getRelations($expensive=false){
		$db = Loader::db();	
		$valueOwnerID = $this->getValueOwnerID();
		$akID = $this->getAttributeKey()->getAttributeKeyID();
		
		$rows = $db->GetAll("select oID, relationID from atLanguageRelations where relationID = (select relationID from atLanguageRelations where oID = ? limit 1) and oID != ?", array($valueOwnerID, $valueOwnerID));
	
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

	public function exportValue(SimpleXMLElement $akv) {
			$val = $this->getExportValue();
			if (is_object($val)) {
				$val = (string) $val;
			}

			$cnode = $akv->addChild('value');
			$node = dom_import_simplexml($cnode);
			$no = $node->ownerDocument;
			$node->appendChild($no->createCDataSection($val));
	 		return $cnode;
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
class LanguageAttributeValueOwner extends Object {
	
	
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