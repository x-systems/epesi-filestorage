<?php

namespace Epesi\FileStorage;

use Epesi\Core\System\Integration\Modules\ModuleView;
use Illuminate\Support\Facades\Auth;
use Epesi\Core\Layout\Seeds\ActionBar;

class FileStorageList extends ModuleView
{
	protected $label = 'File Storage';
	
	public static function access()
	{
		return Auth::user()->can('modify system settings');
	}
	
	public function body()
	{
		ActionBar::addButton('back')->link(url('view/system'));

// 		$this->displayGrid();
	}

}
