<?php

namespace Epesi\FileStorage;

use Epesi\Core\System\Integration\Modules\ModuleView;
use Illuminate\Support\Facades\Auth;
use Epesi\Core\Data\Persistence\SQL;
use Epesi\FileStorage\Seeds\FileModal;

class FileStorageList extends ModuleView
{
	protected $label = 'File Storage';
	
	public static function access()
	{
		return Auth::user()->can('modify system settings');
	}
	
	public function body()
	{	
		$this->grid = $this->add([
				'CRUD',
				'canCreate' => false,
				'canUpdate' => false,
				'canDelete' => false,
				'quickSearch' => [
						'name', 'created_by_user', 
				]
		]);

		$this->grid->menu->addItem([__('Back'), 'icon'=>'arrow left'])->link(url('view/system'));
		
		$this->grid->setModel($this->getModel());
		
		$this->grid->addDecorator('name', ['Multiformat', function($row, $column) {
			return [['Template', '<a href="#" class="file-modal" data-id="' . $row['id'] . '">' . $row[$column]  . '</a>']];
		}]);
		
		$modal = $this->add(new FileModal());
		
		$this->grid->on('click', '.file-modal', $modal->show());

		return $this;
	}
	
	public function getModel()
	{
		$atkDb = app()->make(SQL::class);

		$user = new \atk4\data\Model($atkDb, 'users');
		
		$user->addField('name');

		$model = new \atk4\data\Model($atkDb, 'filestorage_files');
		
		$model->addFields([
				['created_at', 'caption' => __('Created At')],
				['name', 'caption' => __('File Name')],
				['link', 'caption' => __('Link')]
		]);
		
		$model->hasOne('created_by', $user)->addTitle(['field' => 'created_by_user', 'caption' => __('Created By')]);
		
		return $model;
	}
}
