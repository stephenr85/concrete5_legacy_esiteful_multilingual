<?php  defined('C5_EXECUTE') or die(_("Access Denied."));

/**
 * Provides a way to associate files and pages with their translated equivalents, and a variety of other internationalization tools.
 * @package eSiteful Multilingual
 * @author Stephen Rushing, eSiteful
 * @category Packages
 * @copyright  Copyright (c) 2015 Stephen Rushing. (http://www.esiteful.com)
 */
class EsitefulMultilingualPackage extends Package {

	protected $pkgHandle = 'esiteful_multilingual';
	protected $appVersionRequired = '5.6.1';
	protected $pkgVersion = '1.0.1';
	
	public function getPackageDescription() {
		return t("Provides a way to associate files and pages with their translated equivalents, and a variety of other internationalization tools.");
	}
	
	public function getPackageName() {
		return t("eSiteful Multilingual");
	}

	public function on_start(){		
		$env = Environment::get();
		Events::extend('on_page_add', 'EsitefulMultilingualPagesHelper', 'on_page_add', $env->getPath('helpers/esiteful_multilingual/pages.php', $this->pkgHandle));
		Events::extend('on_page_delete', 'EsitefulMultilingualPagesHelper', 'on_page_delete', $env->getPath('helpers/esiteful_multilingual/pages.php', $this->pkgHandle));
		Events::extend('on_page_duplicate', 'EsitefulMultilingualPagesHelper', 'on_page_duplicate', $env->getPath('helpers/esiteful_multilingual/pages.php', $this->pkgHandle));
		Events::extend('on_page_move', 'EsitefulMultilingualPagesHelper', 'on_page_move', $env->getPath('helpers/esiteful_multilingual/pages.php', $this->pkgHandle));
		Events::extend('on_multilingual_page_relate', 'EsitefulMultilingualPagesHelper', 'on_multilingual_page_relate', $env->getPath('helpers/esiteful_multilingual/pages.php', $this->pkgHandle));
	}
	
	public function install() {
		$pkg = parent::install();
		$this->configurePackage($pkg);
	}
	
	public function upgrade(){
		$pkg = $this;
		parent::upgrade();
		$this->configurePackage($pkg);
	}

	public function configurePackage($pkg){
		//TODO: Verify Concrete5 Multilingual package is installed
		$this->configureAttributeTypes($pkg);
		$this->configureAttributeKeys($pkg);
		$this->configureJobs($pkg);
	}

	public function configureAttributeTypes($pkg){
		$db = Loader::db();
		$at = AttributeType::getByHandle('language');
		if(!is_object($at)) {
			$at = AttributeType::add('language', t('Language'), $pkg);
			$col = AttributeKeyCategory::getByHandle('file');
			$col->associateAttributeKeyType(AttributeType::getByHandle('language'));
		}else{
			$db->Execute('update AttributeTypes set pkgID = ? where atID = ?', array($pkg->getPackageID(), $at->atID));
			Package::installDB(__DIR__.'/models/attribute/types/language/db.xml');
		}
	}

	public function configureAttributeKeys($pkg){
		$db = Loader::db();
		$ak = FileAttributeKey::getByHandle('language');
		if(!is_object($ak)) {
			FileAttributeKey::add('language',array('akHandle' => 'language', 'akName' => t('Language'), 'akIsSearchable' => true), $pkg);
		}else if($ak->pkgID != $pkg->getPackageID()){
			$db->Execute('update AttributeKeys set pkgID = ? where akID = ?', array($pkg->getPackageID(), $ak->akID));
		}

		$ak = CollectionAttributeKey::getByHandle('language');
		if(!is_object($ak)) {
			CollectionAttributeKey::add('language',array('akHandle' => 'language', 'akName' => t('Language'), 'akIsSearchable' => true), $pkg);
		}else if($ak->pkgID != $pkg->getPackageID()){
			$db->Execute('update AttributeKeys set pkgID = ? where akID = ?', array($pkg->getPackageID(), $ak->akID));
		}		
	}

	public function configureJobs($pkg){
		Loader::model("job");
		
		$job = Job::getByHandle('esiteful_multilingual_page_sync');
		if(!$job){
			Job::installByPackage("esiteful_multilingual_page_sync", $pkg);	
		}
	}
}