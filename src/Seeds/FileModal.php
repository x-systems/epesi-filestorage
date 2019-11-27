<?php

namespace Epesi\FileStorage\Seeds;

use atk4\ui\Modal;
use atk4\ui\jsExpression;

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

		$this->set(\Closure::fromCallable([$this, 'addContents']));
	}
	
	protected function addContents($canvas)
	{
		$canvas->add([
				new FileView($canvas->stickyGET('id')),
				'defaultPeriod' => $this->defaultPeriod,
				'periodSelection' => $this->periodSelection
		]);
	}
	
	public function show($args = [])
	{
		return parent::show(array_merge([
				'id' => new jsExpression('$(this).data("id")')
		], $args));
	}
}