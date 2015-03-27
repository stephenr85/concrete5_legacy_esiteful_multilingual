<?php defined('C5_EXECUTE') or die("Access Denied.");
class EsitefulMultilingualPagesHelper {



	public function autoLanguageAttribute($page, $parent=NULL){
		Loader::model('section', 'multilingual');
		$section = MultilingualSection::getBySectionOfSite(empty($parent) ? $page : $parent);		
		if(!is_object($section)) return;

		$sectionLang = $section->getLanguage();
		$pageLang = $page->getAttribute('language');
		
		if(empty($pageLang)){
			//If it doesn't already have the attribute, all we have to do is set it
			$page->setAttribute('language', $sectionLang);

		}else if($pageLang != $sectionLang){
			//If it does have a value, but doesn't match the new section, detach it from its previous group
			$attrValue = $page->getAttributeValueObject('language');
			$attrKey = $attrValue->getAttributeKey();
			Log::addEntry(t('Auto-setting language attribute (%s -> %s): %s', $pageLang, $sectionLang, $page->getCollectionPath()), __CLASS__);
			$attrKey->getController()->deleteRelation();
			$page->setAttribute($attrKey, $sectionLang);
		}
	}

	//Make sure each page only has one relationship set
	public function limitPageRelationIDs($attrKey){
		$db = Loader::db();
		//TODO
	}

	public function on_page_add($page){
		Log::addEntry(t('on_page_add: %s', $page->getCollectionPath()), __CLASS__);
		$helper = Loader::helper('esiteful_multilingual/pages', 'esiteful_multilingual');
		$helper->autoLanguageAttribute($page);
	}

	public function on_page_delete($page){
		Log::addEntry(t('on_page_delete: %s', $page->getCollectionPath()), __CLASS__);
		$helper = Loader::helper('esiteful_multilingual/pages', 'esiteful_multilingual');

		$akID = CollectionAttributeKey::getByHandle('language')->getAttributeKeyID();
		$cID = $page->getCollectionID();
		Loader::db()->Execute("delete from atLanguageRelations where akID = ? and oID = ?", array($akID, $oID));
	}

	

	public function on_page_duplicate($newPage, $oldPage){
		Log::addEntry(t('on_page_duplicate: %s', $newPage->getCollectionPath()), __CLASS__);
		$helper = Loader::helper('esiteful_multilingual/pages', 'esiteful_multilingual');
		$helper->autoLanguageAttribute($newPage);
		$db = Loader::db();
		$newSection = MultilingualSection::getBySectionOfSite($newPage);
		$oldSection = MultilingualSection::getBySectionOfSite($oldPage);
		if($newSection->getLanguage() != $oldSection->getLanguage()){
			//Set association between pages, if different languages
			$attrValue = $oldPage->getAttributeValueObject('language');	
			$existingRelationID = $db->GetOne('select cID from MultilingualPageRelations where mpLocale = ? and mpRelationID = (select mpRelationID from MultilingualPageRelations where cID = ? limit 1)', array($newSection->getLocale(), $oldPage->getCollectionID()));	
			if(is_object($attrValue) && !$existingRelationID){
				$attrKey = $attrValue->getAttributeKey();
				$attrController = $attrKey->getController();
				$attrController->setAttributeValue($attrValue);
				//$attrController->getAttributeValue()->setCollection($oldPage);
				$attrController->addRelation($newPage->getCollectionID());
			}
		}
		
	}

	public function on_page_move($page, $oldParent, $newParent){
		Log::addEntry(t('on_page_move: %s', $page->getCollectionPath()), __CLASS__);
		$helper = Loader::helper('esiteful_multilingual/pages', 'esiteful_multilingual');
		$db = Loader::db();

		if (strpos($newParent->getCollectionPath(), TRASH_PAGE_PATH) !== FALSE) {
			//Detatch pages from their groupings
			$pl = new PageList();
			$pl->filter('p1.cID', array($page->getCollectionID()) + $page->getCollectionChildrenArray());
			$pages = $pl->get();
			$akID = CollectionAttributeKey::getByHandle('language')->getAttributeKeyID();
			foreach($pages as $page){
				if(is_object($attrValue = $page->getAttributeValueObject('language'))){
					Log::addEntry(t('on_page_move (to trash): %s', $page->getCollectionPath()), __CLASS__);
					$db->Execute("delete from atLanguageRelations where akID = ? and oID = ?", array($akID, $oID));
				}				
			}
			
		}else{
			$helper->autoLanguageAttribute($page, $newParent);
		}
	}

	public function on_multilingual_page_relate($page, $locale){
		Log::addEntry(t('on_multilingual_page_relate: %s', $page->getCollectionPath()), __CLASS__);
		$helper = Loader::helper('esiteful_multilingual/pages', 'esiteful_multilingual');
		
		$attrValue = $page->getAttributeValueObject('language', true);
		$attrValue->setCollection($page);
		$attrController = $attrValue->getAttributeKey()->getController();
		$attrController->setAttributeValue($attrValue);
		$attrController->pullPageRelations();	
	}


}