<?php
class TextViewerFabrica{
	public function getViewer($params, $model){
		return new TextViewer();
	}
}
class TextViewer{
	public function show($cell, $params){
		if ($params[5042] == 0) $editable = 'disabled';
		return "<input onchange='editProperty(".$params[50129].",".$params[5013].",".$params[5082].",".$params[5088].",this.value);alert(this.value);' value='".$cell->value."' ".$editable."/>";
	}
}