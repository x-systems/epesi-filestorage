<?php 

namespace Epesi\FileStorage\Integration;

use Epesi\Core\System\Integration\Joints\SystemSettingsJoint;

class FileStorageSystemSettings extends SystemSettingsJoint
{
	public function section()
	{
		return __('Data');
	}
	
	public function label()
	{
		return __('Files');
	}

	public function icon()
	{
		return 'file';
	}
	
	public function link() {
		return ['filestorage'];
	}
}