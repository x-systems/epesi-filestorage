<?php

namespace Epesi\FileStorage\Seeds;

use Epesi\FileStorage\Integration\Joints\FileStorageAccessJoint;
use Epesi\FileStorage\Database\Models\File;
use Epesi\Core\Helpers\Utils;
use atk4\ui\Modal;
use Epesi\FileStorage\Database\Models\FileRemoteAccess;
use atk4\ui\jsExpression;
use Illuminate\Support\Facades\Auth;

class FileModal extends Modal
{
	public $defaultPeriod = '1 weeks';
	
	public $periodSelection;
	
	protected $canvas;
	
	protected $file;
	
	public function init()
	{
		$this->title = __('File');
		
		parent::init();
		
		$this->setPeriodSelection();
		
		$this->set(\Closure::fromCallable([$this, 'addContents']));
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
	
	protected function addContents($canvas)
	{
		$this->canvas = $canvas;
		
		if (! $this->file()) {
			$canvas->set(__('Wrong parameters for file'));
			return;
		}
		
		$columns = $canvas->add('Columns');
		
		$column = $columns->addColumn();

		$this->addFileDetails($column);	
				
		$column = $columns->addColumn();
		
		$this->addFileRemoteLinks($column);			
		
		$this->addFileControlButtons();
	}
	
	protected function file()
	{
		return $this->file?: ($this->file = File::get($this->canvas->stickyGET('id')));
	}
	
	protected function addFileDetails($container = null)
	{
		$container = $container?: $this->canvas;
		
		$container->add(['Lister', 'template'=> new \atk4\ui\Template('
						<div class="item" style="margin: 0 15px 15px 0">
							<div class="content"><strong>{$title}</strong>
                				<div class="description">{$descr}</div>
            				</div>
        				</div>{empty}' . __('No file details') . '{/}')])->setSource([
        						['title'=> __('Name'), 'descr'=> $this->file()->name],
        						['title'=> __('Size'), 'descr'=> Utils::bytesToHuman($this->file()->content->size)],
        ]);
	}
	
	protected function addFileRemoteLinks($container = null) {
		$container = $container?: $this->canvas;
		
		foreach ($this->file()->links()->where('created_by', Auth::id())->get() as $link) {
			$container->add(['View', __('Remote access link expiring :expiry', ['expiry' => $link->expires_at])]);
			$linkInput = $container->add([new \atk4\ui\FormField\Input(['readonly' => true, 'iconLeft' => 'linkify'])])->set($link->href)->setStyle(['width' => '100%', 'margin-bottom' => '15px']);
			
			$linkInput->action = new \atk4\ui\Button([
					'Copy', 'iconRight' => 'copy', 'attr' => ['data-clipboard-target' => "#{$linkInput->id}_input", 'title' => __('Click to copy link')], 'class' => ['copy-button']
			]);
			
			$linkInput->add(['Button', 'icon'=>'red x icon', 'class' => ['delete-link']], 'AfterAfterInput')->setAttr(['data-id' => $link->id, 'title' => __('Disable link')]);
		}
		
		$container->js(true, new jsExpression('new ClipboardJS(".copy-button")'));
		
		$container->on('click', '.delete-link', $container->add(['jsCallback', 'postTrigger' => 'link'])->set(function($j, $linkId) {
			if (! $link = FileRemoteAccess::find($linkId)) return;
			
			$link->delete();
			
			return $this->reload();
		}, ['link' => new jsExpression('$(this).data("id")')]));
	}
	
	protected function addFileControlButtons() {
		$urls = FileStorageAccessJoint::getActionUrls($this->file());
		
		if ($urls['preview']?? null) {
			$this->canvas->add(['Button', 'class' => ['basic'], 'icon' => 'file alternate outline'])->set(__('View'))->link($urls['preview'])->setAttr(['target' => '_blank']);
		}
		
		if ($urls['download']?? null) {
			$this->canvas->add(['Button', 'class' => ['basic'], 'icon' => 'file download'])->set(__('Download'))->link($urls['download']);
		}
		
		$linkControl = $this->canvas->add(['View', 'ui' => 'basic buttons']);
		
		$linkButton = $linkControl->add(['Button', 'class' => ['basic'], 'icon' => 'linkify'])->set(__('Get Remote Link'));
		
		$dropdown = $linkControl->add(['DropDownButton', 'class' => ['floating icon button']]);
		
		$dropdown->setSource($this->periodSelection);
		
		$dropdown->onChange(function($value) {
			$period = array_search($value, $this->periodSelection)?: $this->defaultPeriod;
			
			FileRemoteAccess::grant($this->file(), $period);
			
			return $this->reload();
		});
		
		$linkButton->on('click', $dropdown->cb);
	}
	
	protected function reload() {
		return new \atk4\ui\jsReload($this->canvas);
	}
	
	public function show($args = [])
	{
		return parent::show(array_merge([
				'id' => new jsExpression('$(this).data("id")')
		], $args));
	}
}