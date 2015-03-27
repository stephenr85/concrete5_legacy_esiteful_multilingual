<?php defined('C5_EXECUTE') or die("Access Denied.");
class TranslatedFilesHelper {
	
	public function getDefaultLanguage(){
		$pkg = Package::getByHandle('multilingual');
		return reset(explode('_', $pkg->config('DEFAULT_LANGUAGE')));
	}
	
	public function getSessionLanguage(){
		$locale = Loader::helper('default_language', 'multilingual')->getSessionDefaultLocale();
		return reset(explode('_', $locale));
	}
	
	public function getFileLocale($file) {
		if(is_numeric($file)){
			$file = File::getByID($file);
		}
		if (is_object($file) && $file->getAttribute('language')) {
			return $file->getAttribute('language');
		}
		return $this->getDefaultLanguage();
	}

	public function getTranslatedFiles($file, $sans=false) {
		if(is_numeric($file)){
			$file = File::getByID($file);	
		}
		$attrValue = $file->getAttributeValueObject(FileAttributeKey::getByHandle('language'));
		
		if($attrValue){
			$attrController = $attrValue->getAttributeKey()->getController();
			$attrController->valueOwnerID = $file->getFileID();			
			$translations = $attrController->getRelationsOwnerHash();
			return $translations;
		}
	}
	
	
	public function filterFileList($fileList, $lang=NULL, $includeNulls=NULL, $attrHandle='language'){
		$defaultLang = $this->getDefaultLanguage();
		
		if(empty($lang)){
			$sessionLang = $this->getSessionLanguage();

			if(is_object($section = MultilingualSection::getCurrentSection())){
				$lang = $section->getLanguage();
			}else{
				$lang = $sessionLang;
			}
		}
		
		//if explicit, or the specified language is the default language, return nulls automatically	
		if($includeNulls === TRUE || ($includeNulls === NULL && strpos($defaultLang, $lang) === 0)){
			$fileList->filter(false, "(ak_$attrHandle is NULL or ak_$attrHandle = '' or ak_$attrHandle = '0' or ak_$attrHandle like '$lang')");	
		}else{
			$fileList->filter(false, "ak_$attrHandle like '$lang%'");
		}
		
		return $fileList;
	}

	

}