<?php

namespace Epesi\FileStorage\Seeds;

use atk4\ui\Lister;
use Epesi\Core\Helpers\Utils;
use Epesi\Core\System\User\Database\Models\User;
use Epesi\FileStorage\Database\Models\File;

class FileDetailsLister extends Lister
{
	public $fields = ['name', 'size', 'created_by', 'created_at'];
	
	protected $file;

	public function __construct($file)
	{
		$this->file = is_numeric($file)? File::get($file): $file;
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
						'descr'=> $this->file->name
				],
				'size' => [
						'title'=> __('Size'),
						'descr'=> Utils::bytesToHuman($this->file->content->size)
				],
				'created_by' => [
						'title'=> __('Created By'),
						'descr'=> User::find($this->file->created_by)->name
				],
				'created_at' => [
						'title'=> __('Created At'),
						'descr'=> $this->file->created_at->format('Y-m-d H:i:s')
				]
		]: [];
	}
}