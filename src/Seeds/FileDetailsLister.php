<?php

namespace Epesi\FileStorage\Seeds;

use atk4\ui\Lister;
use Epesi\Core\Helpers\Utils;
use Epesi\FileStorage\Database\Models\File;

class FileDetailsLister extends Lister
{
	public $fields = ['name', 'size', 'created_by', 'created_at'];
	
	protected $file;

	public function __construct($file)
	{
		$this->file = File::retrieve($file);
	}
	
	public function init()
	{
		$this->template = new \atk4\ui\Template('
						<div class="item" style="margin: 0 15px 15px 0">
							<div class="content"><strong>{$title}</strong>
                				<div class="description">{$descr}</div>
            				</div>
        				</div>{empty}' . __('No file details') . '{/}');
		
		parent::init();
		
		$this->setSource(array_intersect_key($this->getDefaultSource(), array_flip($this->fields)));
	}
	
	public function getDefaultSource()
	{
		return $this->file? [
				'name' => [
						'title'=> __('Name'),
						'descr'=> $this->file['name']
				],
				'size' => [
						'title'=> __('Size'),
						'descr'=> Utils::bytesToHuman($this->file->ref('content')['size'])
				],
				'created_by' => [
						'title'=> __('Stored By'),
						'descr'=> $this->file['created_by_user']
				],
				'created_at' => [
						'title'=> __('Stored At'),
						'descr'=> $this->file['created_at']
				]
		]: [];
	}
}