<?php

namespace Epesi\FileStorage;

use Epesi\Core\System\Integration\Modules\ModuleView;
use Illuminate\Support\Facades\Auth;
use Epesi\Core\Layout\Seeds\ActionBar;
use Epesi\Base\CommonData\Database\Models\CommonData;
use Epesi\Core\System\Seeds\Form;
use atk4\ui\jsExpression;
use atk4\ui\jQuery;
use atk4\ui\jsModal;

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

// 		$this->displayGrid();
	}
	
	public function displayGrid()
	{		
		$this->grid = $this->add([
				'CRUD',
				'itemCreate' => ActionBar::addButton('add'),
				'formCreate' => $this->getNodeForm(),
				'formUpdate' => $this->getNodeForm(),
				'fieldsCreate' => ['key', 'value'],
				'fieldsUpdate' => ['key', 'value'],
				'notifyDefault' => ['jsNotify', 'content' => __('Data is saved!'), 'color' => 'green'],
				'canUpdate' => false, //use custom update routine
				'canDelete' => false, //use custom delete routine
				'paginator' => false,
				'menu' => false,
		]);
		
		$this->grid->table->addClass('selectable')->addStyle('cursor', 'pointer');

		$this->grid->setModel($model = $this->getModel());
		
		if ($model->action('count')->getOne() ) {
			$this->grid->on('click', 'td', new jsExpression(
					'document.location=\'?parentId=\'+[]',
					[(new jQuery())->closest('tr')->data('id')]
			));
		}
		
		$this->grid->addDragHandler()->onReorder(function ($order) {
			$result = true;
			foreach ($this->nodes()->get() as $node) {
				$node->position = array_search($node->id, $order);
				
				$result &= $node->save();
			}
			
			$notifier = $result? $this->notify(__('Items reordered!')): $this->notifyError(__('Error saving order!'));
			
			return $this->grid->jsSave($notifier);
		});
		
		$this->addContolButton('update', $this->getUpdateModal(), 'edit');
			
		$this->addContolButton('delete', function ($jschain, $id) {
			CommonData::find($id)->delete();
				
			return $jschain->closest('tr')->transition('fade left');
		}, 'red trash');
			
		$this->grid->addColumn('actions', ['Multiformat', function($row) {		
			return [['Template', $this->getControlButtonsHtml($row)]];
		}, 'caption' => ' ']);
		
		return $this;
	}
	
	public function getControlButtonsHtml($row)
	{
		if ($row['readonly']) return '';
		
		$ret = '';
		foreach ($this->buttons as $button) {
			$ret .= $button->getHtml();
		}
		
		return $ret;;
	}
	
	public function getUpdateModal()
	{
		$grid = $this->grid;
		
		$grid->pageUpdate = $grid->add($grid->pageUpdate ?: $grid->pageDefault, ['short_name'=>'edit']);
		
		$modal = new jsModal(__('Edit'), $grid->pageUpdate, [$grid->name => $grid->jsRow()->data('id'), $grid->name.'_sort' => $grid->getSortBy()]);
		
		$grid->pageUpdate->set(function () {
			$grid = $this->grid;
			
			$grid->model->load($grid->app->stickyGet($grid->name));
			
			// Maybe developer has already created form
			if (!is_object($grid->formUpdate) || !$grid->formUpdate->_initialized) {
				$grid->formUpdate = $grid->pageUpdate->add($grid->formUpdate ?: $grid->formDefault);
			}
			
			$grid->formUpdate->setModel($grid->model, $grid->fieldsUpdate ?: $grid->fieldsDefault);
			
			if ($sortBy = $grid->getSortBy()) {
				$grid->formUpdate->stickyGet($grid->name . '_sort', $sortBy);
			}
			
			// set save handler with reload trigger
			// adds default submit hook if it is not already set for this form
			if (!$grid->formUpdate->hookHasCallbacks('submit')) {
				$grid->formUpdate->onSubmit(function ($form) {
					$form->model->save();
					
					return $this->grid->jsSaveUpdate();
				});
			}
		});
		
		return $modal;
	}
	
	public function addContolButton($name, $callback, $icon = null)
	{
		$class = "button_$name";
		
		$this->grid->on('click', ".$class", $callback, [$this->grid->table->jsRow()->data('id')]);
		
		$this->buttons[$name] = new \atk4\ui\Button($icon? compact('icon'): $name);
		
		$this->buttons[$name]->app = $this->app;
		
		$this->buttons[$name]->addClass("compact $class");
	}
	
	public function nodes()
	{
		return CommonData::where('parent_id', $this->parentId);
	}
	
	public function getModel()
	{
		$rows = [];
		foreach ($this->nodes()->orderBy('position')->get() as $node) {
			$rows[] = [
					'id' => $node->id,
					'position' => $node->position,
					'key' => $node->key,
					'value' => $node->value,
					'readonly' => $node->readonly
			];
		}
		
		$rowsEmpty = [];
		
		$model = new \atk4\data\Model($rows? new \atk4\data\Persistence_Static($rows): new \atk4\data\Persistence_Array($rowsEmpty));
		
		$captions = [
				'position' => __('Position'),
				'key' => __('Key'),
				'value' => __('Value'),
				'readonly' => __('Read Only')
		];
		
		foreach ($captions as $key => $caption) {
			$field = $rows? $model->hasField($key): $model->addField($key);
			
			if ($key === 'readonly') {
				$field->setDefaults(['ui' => ['visible' => false]]);
				
				continue;
			}
			
			$field->setDefaults(compact('caption'));
		}
		
		return $model;
	}
	
	public function getNodeForm()
	{
		if (! $this->form) {
			$this->form = new Form(['buttonSave' => ['Button', __('Save'), 'primary']]);
			
			$this->form->addHook('submit', function($form) {
				$values = $form->getValues();
				
				if ($id = $values['id']?? null) {
					CommonData::find($id)->update($values);
					
					return $this->grid->jsSaveUpdate();
				}
				
				CommonData::create(array_merge($values, [
						'position' => $this->nodes()->max('position') + 1
				]), $this->parentId? CommonData::find($this->parentId): null);
				
				return $this->grid->jsSaveCreate();
			});
		}
		
		return $this->form;
	}
}
