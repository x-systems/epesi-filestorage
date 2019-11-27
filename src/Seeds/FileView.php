<?php

namespace Epesi\FileStorage\Seeds;

use atk4\ui\jsExpression;
use Epesi\FileStorage\Database\Models\FileRemoteAccess;
use Epesi\FileStorage\Integration\Joints\FileStorageAccessJoint;
use atk4\ui\View;
use Epesi\FileStorage\Database\Models\File;

class FileView extends View
{
	public $defaultPeriod = '1 weeks';
	
	public $periodSelection = [];
	
	public $disableActions = [];
	
	protected $file;

	public function __construct($file)
	{
		$this->file = is_numeric($file)? File::get($file): $file;
	}
	
	public function renderView()
	{
		$this->setPeriodSelection();
		
		$this->addContents();
		
		parent::renderView();
	}
	
	protected function setPeriodSelection()
	{
		if (! $this->periodSelection) {
			$this->periodSelection = [];
			foreach ([1, 2, 3, 4] as $count) {
				$this->periodSelection[$count . ' weeks'] = trans_choice('{1} :count week |[2,*] :count weeks', $count);
			}
		}
		
		foreach ($this->periodSelection as $key => $period) {
			$default = $key == $this->defaultPeriod? ' (' . __('Default') . ')': '';
			
			$this->periodSelection[$key] = $period . $default;
		}
	}
	
	protected function addContents()
	{
		if (! $this->file) {
			$this->set(__('Wrong parameters for file'));
			return;
		}
		
		$columns = $this->add('Columns');
		
		$column = $columns->addColumn();
		
		$this->addFileDetails($column);
		
		$column = $columns->addColumn();
		
		$this->addFileRemoteLinks($column);
		
		$this->addFileControlButtons();
	}
	
	protected function addFileDetails($container = null)
	{
		$container = $container?: $this;
		
		$container->add([new FileDetailsLister($this->file)]);
	}
	
	protected function addFileRemoteLinks($container = null) {
		$container = $container?: $this;
		
		foreach ($this->file->userActiveLinks()->get() as $link) {
			$container->add(['View', __('Remote access link expiring :expiry', ['expiry' => $link->expires_at])]);
			$linkInput = $container->add([new \atk4\ui\FormField\Input(['readonly' => true, 'iconLeft' => 'linkify'])])->set($link->href)->setStyle(['width' => '100%', 'margin-bottom' => '15px']);
			
			$linkInput->action = new \atk4\ui\Button([_('Copy'), 'iconRight' => 'copy', 'attr' => ['data-clipboard-target' => "#{$linkInput->id}_input", 'title' => __('Click to copy link')], 'class' => ['basic copy-button']]);
			
			$linkInput->add(['Button', 'icon'=>'red x icon', 'class' => ['basic delete-link']], 'AfterAfterInput')->setAttr(['data-id' => $link->id, 'title' => __('Disable link')]);
		}
		
		$container->js(true, new jsExpression('new ClipboardJS(".copy-button")'));
		
		$container->on('click', '.delete-link', $this->add(['jsCallback', 'postTrigger' => 'link'])->set(function($j, $linkId) {
			if (! $link = FileRemoteAccess::find($linkId)) return;
			
			$link->delete();
			
			return $this->reload();
		}, ['link' => new jsExpression('$(this).data("id")')]));
	}
	
	protected function addFileControlButtons() {
		$urls = FileStorageAccessJoint::getActionUrls($this->file);
		
		if (! $this->actionDisabled('preview') && ($urls['preview']?? null)) {
			$this->add(['Button', 'class' => ['basic'], 'icon' => 'file alternate outline'])->set(__('View'))->link($urls['preview'])->setAttr(['target' => '_blank']);
		}
		
		if (! $this->actionDisabled('download') && ($urls['download']?? null)) {
			$this->add(['Button', 'class' => ['basic'], 'icon' => 'file download'])->set(__('Download'))->link($urls['download']);
		}
		
		if (! $this->actionDisabled('history')) {
			$this->add(['Button', 'class' => ['basic'], 'icon' => 'history'])->set(__('Access History'))->link(url('view/filestorage:file-access-history/body') . '?' . http_build_query(['id' => $this->file->id]));
		}
		
		if (! $this->actionDisabled('remote')) {
			$linkControl = $this->add(['View', 'ui' => 'basic buttons']);
			
			$linkButton = $linkControl->add(['Button', 'class' => ['basic'], 'icon' => 'linkify'])->set(__('Get Remote Link'));
			
			$dropdown = $linkControl->add(['DropDownButton', 'class' => ['floating icon button']]);
			
			$dropdown->setSource($this->periodSelection);
			
			$dropdown->onChange(function($value) {
				$period = array_search($value, $this->periodSelection)?: $this->defaultPeriod;
				
				FileRemoteAccess::grant($this->file, $period);
				
				return $this->reload();
			});
		}
			
		$linkButton->on('click', $dropdown->cb);
	}
	
	protected function actionDisabled($action) 
	{
		return in_array($action, (array) $this->disableActions);
	}
	
	protected function reload() {
		return new \atk4\ui\jsReload($this);
	}
}