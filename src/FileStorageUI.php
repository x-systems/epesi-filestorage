<?php

namespace Epesi\FileStorage;

use Epesi\FileStorage\Models\File;
use Epesi\Core\Helpers\Utils;

class FileStorageUI
{
	
	public static function fileLabel($id, $nolink = false, $icon = true, $action_urls = null, $label = null, $inline = false)
	{
		$fileExists = File::exists($id);
		
		if ($icon) {
			$icon_file = $fileExists ? 'z-attach.png': 'z-attach-off.png';
			$img_src = Base_ThemeCommon::get_template_file(self::module_name(), $icon_file);
			$icon_img = '<img src="' . $img_src . '" style="vertical-align:bottom">';
		}
		else {
			$icon_img = '';
		}
		
		$file = File::get($id);
				
		if (! $fileName = $label) {
			$fileName = ($file['name']?? '') ?: '[' . __('missing file name') . ']';
		}
		
		if ($nolink || ! $file) {
			return $fileName . ($fileExists ? '': ' [' . __('missing file') . ']');
		}
		
		$link_href = '';
		if ($fileExists) {
			$filesize = filesize_hr($file['file']);
			$filetooltip = __('File size: %s', array($filesize)) . '<hr>' .
					__('Uploaded by: %s', array(Base_UserCommon::get_user_label($file['created_by'], true))) . '<br/>' .
					__('Uploaded on: %s', array(Base_RegionalSettingsCommon::time2reg($file['created_on']))) . '<br/>' .
					__('Number of downloads: %d', array(self::get_downloads_count($id)));
					$link_href = Utils_TooltipCommon::open_tag_attrs($filetooltip) . ' '
							. Utils_FileStorage_FileLeightbox::get_file_leightbox($file, $action_urls);
		} else {
			if (isset($file['hash'])) {
				$tooltip_text = __('Missing file: %s', array(substr($file['hash'], 0, 32) . '...'));
				$link_href = Utils_TooltipCommon::open_tag_attrs($tooltip_text);
			}
		}
		
		$ret = '<a ' . $link_href . '>' . $icon_img . '<span class="file_name">' . $fileName . '</span></a>';
		
		return $inline? $ret: '<div class="file_link">'.$ret.'</div>';
	}
}
