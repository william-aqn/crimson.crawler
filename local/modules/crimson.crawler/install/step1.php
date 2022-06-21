<?=bitrix_sessid_post()?>
<?
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);
global $errors;

if(is_array($errors) && count($errors)>0):
	foreach($errors as $val)
		$alErrors .= $val."<br>";
	echo \CAdminMessage::ShowMessage(Array("TYPE"=>"ERROR", "MESSAGE" =>Loc::getMessage("MOD_INST_ERR"), "DETAILS"=>$alErrors, "HTML"=>true));
else:
	echo \CAdminMessage::ShowNote(Loc::getMessage("MOD_INST_OK"));
endif;
?>
<form action="<?echo $APPLICATION->GetCurPage()?>">
	<input type="hidden" name="lang" value="<?echo LANG?>">
	<input type="submit" name="" value="<?echo Loc::getMessage("MOD_BACK")?>">
<form>