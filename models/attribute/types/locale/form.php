<?php defined('C5_EXECUTE') or die(_("Access Denied."));

$form = Loader::helper('form');
$htmlId = uniqid('ccm_lang_attr');

$pageSelector = Loader::helper('form/page_selector');
//$pageSelector->selectPage($this->field('value'), $this->request('value'), false);

$assetLibrary = Loader::helper('concrete/asset_library');

?>

<div id="<?php echo $htmlId ?>">
	<?php echo $form->hidden($this->controller->field('oID'), $valueOwnerID); ?>
    <?php echo $form->hidden($this->controller->field('detach'), 0); ?>
    <?php echo $form->hidden($this->controller->field('isBulk'), $isBulk); ?>
    
	<?php echo $form->select($this->controller->field('value'), array_merge(array(''=>t('Choose Language')), $sectionLangs), $value); ?> 
	
    <?php if(!$isBulk && !empty($akcHandle)){ ?>
        <button type="button" class="btn translations"><?php echo t('Manage Translations') ?></button>
        
        <?php //$this->controller->print_pre($this->controller->getRelations()); ?>
        <div class="translations" style="display:none; margin:1em 0; padding:.5em; border:1em solid rgba(0,0,0,.1);">
        
        <?php if(is_array($relations) && count($relations)){ ?>
        <button type="button" class="btn detach"><?php echo t('Detach From Group') ?></button>
        <?php } ?>
        
        <table style="width:100%; margin:.5em 0 0;">        
            <thead>
                <tr>
                    <th colspan="2" style="text-align:left;"><?php echo t('Translations') ?></th>
                    <th style="text-align:center"><button type="button" class="btn add"><?php echo t('Add') ?></button></th>
                </tr>
            </thead>
            <tbody>        
            
            <?php foreach($relations as $index=>$relation){ ?>
            <?php $relationFieldName = $this->controller->field('relation').'['.$relation[$index].']'; ?>
            <tr>
                <td><?php echo $form->select($relationFieldName.'[value]', array_merge(array(''=>t('Choose Language')), $sectionLangs), $relation['value']); ?></td>
                <td style="padding:.25em 1em;">
                <?php 
                if($akcHandle == 'file'){ 
                    echo $assetLibrary->file(uniqid('ccm-select-oID'), $relationFieldName.'[oID]', t('Choose File'), $relation['owner']);
                }else if($akcHandle == 'collection'){
                    echo $pageSelector->selectPage($relationFieldName.'[oID]', $relation['oID']);
                }else{
                    echo $form->text($relationFieldName.'[oID]');
                    echo t('Error: no selector available for "%s" objects.', $akcHandle);	
                }
                ?>
                </td>
                <td style="text-align:center"><button type="button" class="btn remove"><?php echo t('Remove') ?></button></td>
            </tr>
            <?php } ?>
            
            <?php $relationFieldName = $this->controller->field('relation').'[x]'; ?>
            
            <tr class="add-relation">
                <td><?php echo $form->select($relationFieldName.'[value]', array_merge(array(''=>t('Choose Language')), $sectionLangs)); ?></td>
                <td style="padding:.25em 1em;">
                <?php 
                if($akcHandle == 'file'){ 
                    echo $assetLibrary->file(uniqid('ccm-select-oID'), $relationFieldName.'[oID]', t('Choose File'), null);
                }else if($akcHandle == 'collection'){
                    echo $pageSelector->selectPage($relationFieldName.'[oID]', null);
                }else{
                    echo $form->text($relationFieldName.'[oID]');
                    echo t('Error: no selector available for "%s" objects.', $akcHandle);	
                }
                ?>
                </td>
                <td style="text-align:center"><button type="button" class="btn remove">Remove</button></td>
            </tr>
            </tbody>
        </table>
        <p class="ccm-note"><?php echo t('Note: The languages set above will directly alter the language of the respective %s(s).', $akcHandle) ?></p>
       </div>
   <?php }else if(empty($akcHandle)){ ?>

   <?php } ?>
</div>
		
<script>
 $(function(){
	var $wrap = $('#<?php echo $htmlId ?>'),
		addHtml = $wrap.find('tr.add-relation').remove().html();
	
	$wrap.on('click', 'button.translations', function(){
		$wrap.find('div.translations').slideToggle();	
	});
	
	$wrap.on('click', 'button.detach', function(){
		$wrap.find('div.translations,button.translations').remove();
		$wrap.find('[name$="[detach]"]').val(1);	
	});
	
	$wrap.on('click', 'button.remove', function(){
		$(this).closest('tr').remove();//.hide().find('[name$="[value]"]').val('delete');	
	});
	
	$wrap.on('click', 'button.add', function(){
		var newHtml = addHtml
			.replace(/ccm-select-oID/g, 'ccm-select-oID'+$.now())
			.replace(/\[x\]/g, '['+$wrap.find('table tbody tr').length+']'),
			$row = $('<tr>'+newHtml+'</tr>');
			
		$wrap.find('table tbody').prepend($row);		
		
		if(typeof window.ccm_initSelectPage === 'function'){
			ccm_initSelectPage();
		}
		
		//$row.find('a.ccm-sitemap-select-page').dialog(); //necessary for page selector
	});
	
	
 });
 
 <?php echo $htmlId ?>_selectSitemapNode = function(cID, cName) { 
		console.log(arguments);
		var fieldName = $(ccmActivePageField).attr("dialog-sender");
			var par = $(ccmActivePageField).parent().find('.ccm-summary-selected-item-label');
			$(ccmActivePageField).parent().find('.ccm-sitemap-clear-selected-page').show();
			var pari = $(ccmActivePageField).parent().find("[name='"+fieldName+"']");
			par.html(cName);
			pari.val(cID);
 }
</script>
		