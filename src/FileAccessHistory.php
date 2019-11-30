<?php

namespace Epesi\FileStorage;

use Epesi\Core\System\Integration\Modules\ModuleView;
use Epesi\FileStorage\Seeds\FileView;
use Epesi\Core\Layout\Seeds\ActionBar;
use Epesi\FileStorage\Database\Models\FileAccessLog;

class FileAccessHistory extends ModuleView
{
	protected $label = 'File Access History';
	
	public function body()
	{
		ActionBar::addButton('back')->link(url()->previous());
		
		$this->displayFileDetails();
		
		$this->displayFileAccessHistory();
	}
	
	public function displayFileDetails()
	{
		$this->add(['View', ['ui' => 'segment']])->add([new FileView($this->stickyGet('id')), 'disableActions' => 'history']);
	}
	
	public function displayFileAccessHistory()
	{	
		$grid = $this->add([
				'CRUD',
				'quickSearch' => [
						'accessed_by_user', 'action', 'ip_address', 'host_name'
				]
		]);

		$grid->addItemsPerPageSelector([10, 25, 50, 100], __('Items per page') . ':');
		
		$grid->setModel(FileAccessLog::create(['read_only' => true])->addCondition('file_id', $this->stickyGet('id'))->setOrder('accessed_at desc'));
	}
}
