<?php

namespace Epesi\FileStorage;

use Epesi\Core\System\Modules\ModuleView;
use Illuminate\Support\Facades\Auth;
use Epesi\FileStorage\View\FileModal;
use Epesi\Core\Layout\View\ActionBar;
use Epesi\FileStorage\Models\File;

class FileStorageList extends ModuleView
{
	protected $label = 'File Storage';
	
	public static function access()
	{
		return Auth::user()->can('modify system settings');
	}
	
	public function body()
	{	
		ActionBar::addItemButton('back')->link(url('view/system'));
		
		$grid = $this->add([
				'CRUD',
				'displayFields' => [
						'name',
						'created_at',
						'created_by_user',						
						'link',
				],
				'quickSearch' => [
						'name', 'created_by_user',
				],
		        'menu' => ActionBar::instance(),
		]);

		$grid->setModel(File::create(['read_only' => true]));
		
		$grid->addDecorator('name', ['Multiformat', function($row, $column) {
			return [['Template', $this->app->getTag('a', ['href' => '#', 'class'=>'file-modal'], $row[$column])]];
		}]);

		$grid->on('click', '.file-modal', $this->add(new FileModal())->show());
	}
}
