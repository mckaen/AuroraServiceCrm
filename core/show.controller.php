<?php
class ShowController {
	public function execute($params){
		$model = Model::getModel();
		//if (empty($params[5065])) {
			$action = $model->getAction($params[5058]); //5058.Действие
		echo '<script language ="JavaScript">var data = []; data[5058] = '.$action->items[5048].'</script>';
			$formId = $action->items[5065];             //5065.Форма
		//} else $formId = $params[5065];
		$filters[5058]='%COLUMN%='.$action->items[5048];
		$orders[504]=1;
		$actionFilters = $model->getResource(163, $filters, $orders);
		if (!empty($actionFilters)) {
			echo ' <script language ="JavaScript">var filters = []; data[5095] = filters;</script>';
			foreach ($actionFilters as $aFilter) {
				//$filters5[5048] = $aFilter->items[5082];
				//echo $filters5[5048];
				//$property = $model->getResource(50,$filters5);
				//echo $property[0]->items[501];
				echo $aFilter->items[501];
				$filters4[5048] = '%COLUMN% = 148';
				$contr = $model->getResource(14, $filters4);
				//print_r($contr);
				//echo substr($contr->items[503],5);
				require_once(substr($contr[0]->items[503], 5));   //503.Местоположение
				//require_once('filter.controller.php');   //503.Местоположение
				$contr = new $contr[0]->items[501]; //501.Название
				$params2[5048] = $aFilter->items[5095];
				$contr->execute($params2);

				$filters2[5048] = '%COLUMN%=' . $aFilter->items[5094];
				//echo $aFilter->items[5094];
				$defValue = $model->getResource(162, $filters2);
				$filters3[$aFilter->items[5082]] = $defValue[0]->items[5096];
				//echo $aFilter->items[5082];
				//print_r($defValue);
				//echo $defValue[0]->items[5096];
			}
			echo '<a onclick="sendData(2316);">Применить</a>';
		}

		require_once('set.controller.php');
		$contr = new SetController();
		$params[5095]=$filters3;
		$contr->execute($params);
/*

		$columns = $model->getColumns2($formId);
		if ($params[5091] == 1) $id = 1; else $id = null; //5091.Флаг поиска
		$table = $model->getDataSet($columns, $formId,$id,$filters3);
		//$val = $model->getShowData($_REQUEST['elem']);
		
		echo '<div class="table-responsive" id="dop_form_interface"><table class="table table-striped table-hover table-condensed"><thead><tr>';
        // Выводим результаты в html
		$colNum = 0;
        foreach ($table->cols as $col_value) {
		  if ($col_value->type == 0) {
			  //$elemId = $model->getEShowElement($col_value->domain);
			  //if (!empty($elemId)) $link[$colNum] = $elemId;
		  }
          echo "<th>".$col_value->name.$link[$col_value->alias]."</th>";
		  $colNum++;
        }
        echo "</tr></thead><tbody>";
        
        if (!empty($table->data)) foreach ($table->data as $val) {
            echo "<tr>";
			$colNum = 0;
            foreach ($val as $col_value) {
				$params[5055] = $table->cols[$colNum]->domain;
				$viewer = $model->getViewer($table->cols[$colNum]->viewer,$params,$this);
				//echo $viewer;
	  
				echo '<td>'.$viewer->show($col_value,$params).'</td>';
				/*
				if (!empty($link[$colNum])) $col_value->value = '<a href="index.php?action=2315&id='.$col_value->value.'">'.$col_value->value.'</a>';
                echo "<td>".$col_value->value."</td>";

				$colNum++;
            }
            echo "</tr>";
        }
        
        echo "</tbody></table></div>";
*/
	}
}
?>