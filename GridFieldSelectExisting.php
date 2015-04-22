<?php

class GridFieldSelectExisting implements GridField_HTMLProvider, GridField_DataManipulator, GridField_ColumnProvider, GridField_SaveHandler {

	private static $base_path;
	private static $column_name = "Select";

	public static function set_base_path($path) {
		self::$base_path = $path;
	}

	public function getHTMLFragments($grid) {
		
		$directory = Config::inst()->get(__CLASS__, 'Base') ?: self::$base_path;

		Requirements::css($directory . '/GridFieldSelectExisting.css');
		Requirements::javascript($directory . '/GridFieldSelectExisting.js');
		
		return '';
	}

	public function getManipulatedData(GridField $grid, SS_List $data) {
		$config = $grid->getConfig();

		$config->removeComponentsByType('GridFieldDeleteAction');
		$config->removeComponentsByType('GridFieldEditButton');

		$class = $data->dataClass;
		return $class::get();
	}

	public function getColumnsHandled($grid) {
		return [self::$column_name];
	}

	public function augmentColumns($grid, &$cols) {
		if(!in_array(self::$column_name, $cols)) {
			$cols = array_merge([self::$column_name], $cols);
		}
	}

	public function getColumnMetadata($grid, $columnName) {
		if($columnName == self::$column_name) {
			return ['title' => 'Selected'];
		}
	}

	public function getColumnAttributes($grid, $row, $columnName) {
		return [];
	}

	public function getColumnContent($grid, $row, $columnName) {
		
		$list = $grid->getList();

		if ($list instanceof ManyManyList) {
			$class = $row->className;
			$join = "\"{$row->className}\".\"ID\" = \"{$list->joinTable}\".\"{$list->getLocalKey()}\"";
			$list = $class::get()->innerJoin($list->joinTable, $join);
		}

		// filter list to only include row 
		$checked = ($list->filter(["ID" => $row->ID])->count() > 0);

		$checkbox = CheckboxField::create(self::$column_name, self::$column_name, $checked);
		$checkbox->addExtraClass('select-existing');
		$checkbox->setName(sprintf(
			'%s[%s][%s]', $grid->getName(), __CLASS__, $row->ID
		));

		return implode([
			$checkbox->Field(),
			'<span class="ui-icon btn-icon-accept checked"></span>',
			'<span class="ui-icon btn-icon-delete unchecked"></span>'
		]);
	}

	public function handleSave(GridField $grid, DataObjectInterface $record) {

		$list = $grid->getList();
		$value = $grid->Value();

		// clear all existing relations
		$list->removeAll();

		if(!isset($value[__CLASS__]) || !is_array($value[__CLASS__])) {
			return;
		}
		
		// add relations based on list
		$relation = $list->dataClass;

		foreach($value[__CLASS__] as $id => $v) {
			if(!is_numeric($id)) {
				continue;
			}

			$gridfieldItem = DataObject::get_by_id($list->dataClass, $id);

			if (!$gridfieldItem || !$gridfieldItem->canEdit()) {
				continue;
			}

			$record->$relation()->add($gridfieldItem);
			$record->write(null, null, null, false);
		}
	}

	private function relationsFromManyMany(ManyManyList $mml) {
		$relations = explode('_', $mml->joinTable);

		return ArrayData::create([
			'foreign' => $relations[0],
			'local' => $relations[1]
		]);
	}
}