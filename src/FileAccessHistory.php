<?php

namespace Epesi\FileStorage;

use Epesi\Core\System\Modules\ModuleView;
use Epesi\FileStorage\Seeds\FileView;
use Epesi\Core\Layout\Seeds\ActionBar;
use Epesi\FileStorage\Models\FileAccessLog;
use atk4\ui\GridFilterPlugin;
use atk4\core\SessionTrait;

class FileAccessHistory extends ModuleView
{
	use SessionTrait;
	
	protected $label = 'File Access History';
	
	protected $fileId;
	
	public function body()
	{
		ActionBar::addButton('back')->link(url()->previous());
		
		$this->fileId = $this->stickyGet('id', $this->recall('fileId'));
		
		$this->memorize('fileId', $this->fileId);
		
		$this->displayFileDetails();
		
		$this->displayFileAccessHistory();
	}
	
	public function displayFileDetails()
	{
		$this->add(['View', ['ui' => 'segment']])->add([new FileView($this->fileId), 'disableActions' => 'history']);
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

		$grid->setModel(FileAccessLog::create(['read_only' => true])->addCondition('file_id', $this->fileId)->setOrder('accessed_at', 'desc'));

		$grid->addFilterColumn();
	}
}
