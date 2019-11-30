<?php

namespace Epesi\FileStorage;

use Epesi\Core\System\Integration\Modules\ModuleView;
use Illuminate\Support\Facades\Auth;
use Epesi\FileStorage\Seeds\FileModal;
use Epesi\Core\Layout\Seeds\ActionBar;
use Epesi\FileStorage\Database\Models\File;

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
				]
		]);

		$grid->setModel(File::create(['read_only' => true]));
		
		$grid->addDecorator('name', ['Multiformat', function($row, $column) {
			return [['Template', '<a href="#" class="file-modal" data-id="' . $row['id'] . '">' . $row[$column]  . '</a>']];
		}]);

		$grid->on('click', '.file-modal', $this->add(new FileModal())->show());
	}
}
