<?php

namespace Epesi\FileStorage;

use Epesi\Core\System\Integration\Modules\ModuleView;
use Epesi\Core\Data\Persistence\SQL;
use Epesi\FileStorage\Seeds\FileView;
use Epesi\Core\Layout\Seeds\ActionBar;

class FileAccessHistory extends ModuleView
{
	protected $label = 'File Access History';
	
	public function body()
	{
		ActionBar::addButton('back')->link(url()->previous());
		
		$this->displayFileDetails();
		
		$this->displayAccessHistoryGrid();
	}
	
	public function displayFileDetails()
	{
		$this->add(['View', ['ui' => 'segment']])->add([new FileView($this->stickyGet('id')), 'disableActions' => 'history']);
	}
	
	public function displayAccessHistoryGrid()
	{	
		$grid = $this->add([
				'CRUD',
				'canCreate' => false,
				'canUpdate' => false,
				'canDelete' => false,
				'quickSearch' => [
						'accessed_by_user', 'action', 'ip_address', 'host_name'
				]
		]);

		$grid->addItemsPerPageSelector([10, 25, 50, 100], __('Items per page') . ':');
		
		$grid->setModel($this->getModel($this->stickyGet('id')));
	}
	
	public function getModel($fileId)
	{
		$atkDb = app()->make(SQL::class);

		$user = new \atk4\data\Model($atkDb, 'users');
		
		$user->addField('name');

		$model = new \atk4\data\Model($atkDb, 'filestorage_access_log');
		
		$model->hasOne('accessed_by', $user)->addTitle(['field' => 'accessed_by_user', 'caption' => __('Accessed By')]);
		
		$model->addFields([
				'file_id',
				['accessed_at', 'caption' => __('Accessed At')],
				['action', 'caption' => __('Action')],
				['ip_address', 'caption' => __('IP Address')],
				['host_name', 'caption' => __('Host Name')]
		]);
				
		$model->addCondition('file_id', $fileId);
		
		$model->setOrder('accessed_at desc');
		
		return $model;
	}
}
