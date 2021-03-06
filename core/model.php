<?php
class Model {
	private static $model;
	
	private $db_host = '127.0.0.1';
	private $db_user = 'root';
	private $db_passwd = 'admin611root';
	private $link;
	
	private $perm;         //Массив доступа [b_action_id][domain][action/perm/contr]
	private $actions;      //Массив действий [action_id]
	private $controllers;  //Массив контроллеров [contr_id]
	private $entities;
  
	public $user_id;
  
	private $viewers;      //Массив отображателей [viewer_id]
	
	function __construct(){
		if (empty($this->user_id)) $this->user_id = $_SESSION['id'];
		//echo 'UserId"'.$this->user_id;
		$this->link = mysqli_connect($this->db_host, $this->db_user, $this->db_passwd, 'kb1');
		mysqli_query($this->link,"SET NAMES 'utf8'");
		mysqli_query($this->link,"SET CHARACTER SET 'utf8'");
		mysqli_query($this->link,"SET SESSION collation_connection = 'utf8_general_ci'");
		if (!$this->link) die('Ошибка соединения: ' . mysqli_error());  
		$this->loadVievers();
		$this->loadActions();
		$this->loadControllers();
		$this->estimateControllers();
		$this->loadEntities();
	}

	public function checkPermission($actionId, $domain = null){
		$actId = $this->actions[$actionId]->items[5062];//5062.Идентификатор действия
		if (empty($domain)) $domain = $this->actions[$actionId]->items[5055];
		//echo 'domain'.$domain.','.$actId.','.$actionId.',';
		if ($this->perm[$actId][$domain]['perm'] == '1') return 1;
		//echo 'Permision:'.$this->perm['2342']['132']['perm'];
		if ($this->perm[$actId][132]['perm'] == '1') return 1;//132.Ресурс

		return 0;
	}

	public function compare($resource1, $resource2, $formId = null, $localFlag = 0, $type = null){
		//print_r($resource2);
		if (!empty($formId)){
			$filters[5065] = '%COLUMN%='.$formId;
			$formProps = $this->getResources(112,$filters);
			foreach ($formProps as $formProp){
				$formPropsIds[$formProp->items[5087]] = 1; //5087.Свойство сущности
			}
		}
		if (empty($type)) $type = $this->getResProperty($resource1->items[5048][0],5051);//5051.Тип
		$props = array();
		$this->getClassPropertiesTransitive($type,$props);
		//$compareFlag = 1;
		foreach ($props as $prop){
			if ($localFlag && ($prop->items[5084]==1)) continue; //5084.Флаг внешнего свойства
			if ((empty($formId)) || ($formPropsIds[$prop->items[5048]])) {
				$propId = $prop->items[5082]; //5082.Идентификатор свойства

				if ($prop->items[50111] == 1) {//50111.Кардинальность
					//echo '<br>' . $propId . ',' . $resource1->items[$propId][0] . ',' . $resource2->items[$propId][0] . '<br>';
					if ($resource1->items[$propId][0] != $resource2->items[$propId][0]) return 0;
				} else {

					if (!empty($resource1->items[$propId])) foreach ($resource1->items[$propId] as $valueCounter1 => $propValue1) {
						$compareFlag = 0;
						if (!empty($resource2->items[$propId])) foreach($resource2->items[$propId] as $valueCounter2 => $propValue2){
							//echo '<br>' . $propId . ',' . $resource1->items[$propId][$valueCounter1] . ',' . $resource2->items[$propId][$valueCounter2] . '<br>';
							if ($resource1->items[$propId][$valueCounter1] == $resource2->items[$propId][$valueCounter2]) {$compareFlag = 1; break;}
						}
						if ($compareFlag == 0) return 0;
					}
					if (!empty($resource2->items[$propId])) foreach ($resource2->items[$propId] as $valueCounter1 => $propValue1) {
						$compareFlag = 0;
						if (!empty($resource1->items[$propId])) foreach($resource1->items[$propId] as $valueCounter2 => $propValue2){
							//echo '<br>' . $propId . ',' . $resource2->items[$propId][$valueCounter1] . ',' . $resource1->items[$propId][$valueCounter2] . '<br>';
							if ($resource2->items[$propId][$valueCounter1] == $resource1->items[$propId][$valueCounter2]) {$compareFlag = 1; break;}
						}
						if ($compareFlag == 0) return 0;
					}
				}

			}
		}
		return 1;
	}

	public function compareProperties($props1,$props2){
		$lineNum = 0;
		if (!empty($props1)) foreach ($props1 as $value1){
			$existFlag = 0;
			if (!empty($props2)) foreach ($props2 as $value2){
				if ($value1 == $value2) {$existFlag = 1; break;}
			}
			if ($existFlag == 0){
				//echo 'Compare:'.$lineNum.',' .$value1.'<br>';
				if (!empty($value1)) $ret[$lineNum] = $value1;
				$lineNum++;
			}
		}
		return $ret;
	}

	private function estimateControllers(){
		foreach ($this->actions as $action){
			if (!empty($action->items[5057]) && empty($action->items[5063])) $action->items[5063] = $this->findController($action->items[5057]);
		}

		foreach ($this->actions as $action){
			$this->perm[$action->items[5062]][$action->items[5055]]['contr']=$this->controllers[$action->items[5063]];
		}
	}

	public function find($text, $domain){
		$text = strtoupper($text);
		$query = 'delete from find';
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$query = 'insert into find(id,type) select id,type from dim_resource where Upper(name) LIKE "%'.$text.'%" and type = '.$domain;
		//echo $query;
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
	}

	private function findController($actionId){
		if (!empty($this->actions[$actionId]->items[5063])) return $this->actions[$actionId]->items[5063];
		else if (!empty($this->actions[$actionId]->items[5057])) return $this->findController($this->actions[$actionId]->items[5057]);
		else return '';
	}

	public function generateCode($length=6) {
		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPRQSTUVWXYZ0123456789";
		$code = "";
		$clen = strlen($chars) - 1;
		while (strlen($code) < $length) {
			$code .= $chars[mt_rand(0,$clen)];
		}
		return $code;
	}

	//Получение модели
	public static function getModel(){
		if (empty(Model::$model)) Model::$model = new Model();
		return Model::$model;
	}
  
	public function getViewer($id, $params = null, $model = null){
		chdir ($_SERVER['DOCUMENT_ROOT']);
		//echo getcwd();
		require_once($this->viewers[$id]->items[503]);
		$className = $this->viewers[$id]->items[501].'Fabrica';
		$viewerFactory = new $className;
		$viewer = $viewerFactory->getViewer($params,$model);
		return $viewer;
	}

	public function getId($domain){
		$next = (++$this->entities[$domain]->items[5090]);
		$id = $this->entities[$domain]->items[5089].$next;
		$query = 'update entities set counter = '.$next.' where id='.$domain;
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		return $id;
	}

	public function getAction($actionId){
		return $this->actions[$actionId];
	}

	private function getChildColumns($columnId){
		$query =
			"select alias from sColumns
         where pid = $columnId order by CAST(position AS SIGNED)";
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$columns = '';
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$columns = $columns . ',' .$line['alias'];
		}
		mysqli_free_result($result);
		$columns = substr($columns,1);
		return $columns;
	}

	//Получение массива свойств класса (Transitive)
	public function getClassPropertiesTransitive($classId,&$props){
		$filters[5083]='%COLUMN% = '.$classId;
		//$ret = $this->getResource(1511, $filters);
		//print_r($ret->items[5048][0]);
		//echo '-----';
		$ret = $this->getClassProperties($classId);
		//print_r($ret->items[5048][0]);
		$props = array_merge($props,$ret);
		$pClassId = $this->getResProperty($classId,5061,0); //5061.Подкласс
		if (!empty($pClassId)){
			$this->getClassPropertiesTransitive($pClassId,$props);
		}
		//return $props;
	}

	public function getClassProperties($classId){
		$query = "select id, ent_id, prop_id, alias, domain, external,virtual, editable, cardinal, ne_create_flag
		from ent_properties
		where ent_id = $classId";
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};

		$propNum = 0;
		$props = array();
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$props[$propNum] = new Resource();
			$props[$propNum]->items[5048] = $line['id'];
			$props[$propNum]->items[5083] = $line['ent_id'];
			$props[$propNum]->items[5082] = $line['prop_id'];
			$props[$propNum]->items[506] = $line['alias'];
			$props[$propNum]->items[5055] = $line['domain'];
			$props[$propNum]->items[5084] = $line['external'];
			$props[$propNum]->items[50108] = $line['auto'];
			$props[$propNum]->items[5052] = $line['editable'];
			$props[$propNum]->items[50110] = $line['virtual'];
			$props[$propNum]->items[50111] = $line['cardinal'];
			$props[$propNum]->items[50216] = $line['ne_create_flag'];
			$propNum++;
		}
		//print_r($props);
		return $props;
	}

	public function getColumns2($formId) {
		$lineNum=0;
		$retval = new Table();

		$filters[5065] = '%COLUMN%='.$formId; $orders[504] = 1;
		$columns = $this->getResourcesOpt(112, $filters, $orders);
		if (!empty($columns)) foreach ($columns as $line){
			$col = new Column($line->items['5048'][0],$line->items['501'][0],$line->items['504'][0],null,null,null,$line->items['5051'][0],$line->items['5085'][0],$line->items['5052'][0],null,$line->items['5057'][0],null);
			$col->res = $line;
			$filters2[5048] = '%COLUMN%='.$line->items[5087][0];
			$property = $this->getResourcesOpt(1511, $filters2);
			$col->prop = $property[0];
			$col->property = $property[0]->items[5082][0];
			$col->alias = $property[0]->items[506][0];
			$col->domain = $property[0]->items[5055][0];
			//echo 'domain'.$col->domain;
			$col->external = $property[0]->items[5084][0];
			//echo 'external'.$col->external;
			$col->viewer = $line->items['5086'][0];
			$col->value_template = $line->items['50112'][0];
			$retval->cols[$lineNum]=$col;
			$lineNum++;
		}
		return $retval;
	}

	function getComboItems($boxId){
		$query = "select r.id id, r.name from triplets t
                  join dim_resource r on t.subj_id = r.id
                where t.obj_id = $boxId and t.prop_id = 502";
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$lineNum = 0;
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$items[$lineNum] = new Cell($line[id], $line[name]);
			$lineNum++;
		}
		mysqli_free_result($result);
		return $items;
	}

	public function getController($actionId){
		$actId = $this->actions[$actionId]->items[5062];
		$domain = $this->actions[$actionId]->items[5055];
		return $this->perm[$actId][$domain]['contr'];
	}

	public function getController2($id){
		chdir ($_SERVER['DOCUMENT_ROOT']);
		//echo getcwd();
		$contr = $this->controllers[$id];
		//print_r($this->controllers);
		//echo 'CONTR='.$contr->items[503];
		//echo 'ID='.$id;
		require_once($contr->items[503]);
		$className = $contr->items[501];
		$contr = new $className;
		return $contr;
	}

	public function getDataSet($table, $formId, $id, $filters = null){
		$entId = $this->getResProperty($formId,507,0); //507.Представление
		$tableName = $this->getResProperty($entId,503,0,0); //503.Местоположение
		if ($this->isSubclassOf($entId,1612)) $where2 = ' and '.$tableName.'.end_date = "9999-01-01"'; //1612.Версионная сущность
		$columns = '';
		foreach ($table->cols as $line)  {
			if (!empty($filters[$line->property])){
				$filter = str_replace('%COLUMN%',$tableName.".".$line->alias,$filters[$line->property]);
				$where2 = $where2 ." and ". $filter;
			}
			if ($line->external == '0'){
				if ($line->type == 0){
					$childCols = $this->getChildColumns($line->id);
					$location = $this->getResProperty($line->domain,503);
					if (!empty($childCols)) $columns = $columns . ", " . "(select concat($childCols) from $location where $location .id = $tableName.$line->alias and $location.end_date='9999-01-01') ".$line->alias."_value";
					else $columns = $columns . ", " .$tableName.".".$line->alias . " ".$line->alias."_value";
				}
				if ($line->type == 3) $column = "DATE_FORMAT(".$line->alias.",'".$line->template."') ".$line->alias; else $column = $line->alias;
				$columns = $columns . ", " . $tableName.".".$column;
			} else {

				if ($line->external == 1) {$relation = 'subj_id'; $relation2 = 'obj_id'; $property = $line->property;} else {$relation = 'obj_id'; $relation2 = 'subj_id'; $property = $this->getResProperty($line->property,5049,0);} //5049.Явдяется обратным
				if (!empty($id)) $where = "and triplets.".$relation." = ".$id; else $where = '';
				$childCols = $this->getChildColumns($line->id);
				if (empty($childCols)) $childCols = "id";
				//echo $query;
				$location = $this->getResProperty($line->domain,503);
				//echo $line->domain.$location;
				$query = "select triplets.$relation id ,triplets.$relation2 ext_id,".$location.".id val_id, concat(".$childCols.") value from triplets
                    join $location on ".$location.".id  = triplets.$relation2
                    where triplets.prop_id = $property ".$where;
				//echo $query.'<br>'.$line->property;
				$this->log("QUERY:".$query);
				$result = mysqli_query($this->link, $query);
				if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
				$valNum = 0;
				while ($res = mysqli_fetch_array($result, MYSQL_ASSOC)) {
					if ($prevProp != $line->property) $valNum = 0; else $valNum++;
					$cell = new Cell($res[val_id],$res[value]); $cell->id = $res[ext_id];
					$table->ext[$res[id]][$line->property][$valNum] = $cell;
					$prevProp = $line->property;
				}
			}
		}
		$filter = null;
		$filterNum = 0;
		if (!empty($id)) {
			if ($id == 1) $filter[$filterNum] = "id in (select id from find) "; else $filter[$filterNum] = "id = '".$id."'";
			$filterNum++;
		}
		$classId = $this->getResProperty($formId,507,0);
		$tableClassId = $this->getStorageClass($tableName);
		if (!empty($tableClassId) && $classId != $tableClassId) {
			$filter[$filterNum] = $tableName."."."type = '".$classId."'";
			$filterNum++;
		}
		//if ($filterNum != 0) {
			$where = $tableName.'.end_date="9999-01-01"'." and ";
			if ($filterNum != 0) foreach ($filter as $condition) {
				$where = $where . $condition ." and ";
		//	}
		//echo ('Where2='.$where2);
			
		}
		if (empty($where2)) $where = substr($where,0,strlen($where)-5);
		//if (!empty($where2)) if (!empty($where))$where = $where . ' and '. substr($where2,5); else $where = ' where '.substr($where2,5);
		$where = ' where '.$where.substr($where2,5);
		//if (!empty($where)) $where = $where . ' and '. substr($where2,5); else if (!empty($where2)) $where = ' where '.substr($where2,5);
		$columns = substr($columns,2);
		if (!empty($filters[50147])) $limit = ' limit '.$filters[50147]; else $limit = '';
		//echo 'LIMIT='.$filters[50147];
		if (!empty($filters[50100])) {
			$join = " join dim_resource r on r.id = ".$tableName.".id ";
			$where = $where." and ".str_replace('%COLUMN%',"r.search_name",$filters[50100])." ";
		}
		$query = "select SQL_CALC_FOUND_ROWS ". $columns. " from ".$tableName." ".$join.$where . $limit;

		file_put_contents("log_sql","\n$query",FILE_APPEND);
		//echo $query;
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$lineNum = 0;
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$flag=1;
			$colNum = 0;
			foreach ($line as $col_value) {
				if (($table->cols[$colNum]->type == 0) && ($flag == 0)) {
					$table->data[$lineNum][$colNum]->id = $col_value;
					$flag = 1; $colNum++;
				} else {

					$table->data[$lineNum][$colNum]=new Cell($col_value,$col_value);
					if($table->cols[$colNum]->type != 0) $colNum++; else $flag = 0;
					if($table->cols[$colNum]->external > 0) $colNum++;
				}
			}
			$lineNum++;
		}
		$query = "SELECT FOUND_ROWS() count" ;
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$line = mysqli_fetch_array($result, MYSQL_ASSOC);
		$table->numRow=$line["count"];
		//echo 'Rows:'.$return[3];

		mysqli_free_result($result);
		return $table;
	}

	function getForm($actionId, $domain = null){
		if (empty($domain)) $action = $this->getAction($actionId);
		else $action = $this->getLeafAction($actionId,$domain);
		return $action->items[5065];
	}

	function getFormProps($formId){
		$filters[5065] = '%COLUMN%=' . $formId;
		$formProps = $this->getResources(112, $filters);
		foreach ($formProps as $formProp) {
			//echo '';
			$formPropsIds[$formProp->items[5087]] = 1; //5087.Свойство сущности
		}
		return $formPropsIds;
	}

	public function getLeafAction($actionId,$domain){
		$realAction = $this->getRealAction($actionId, $domain);
		return $this->perm[$realAction->items[5062]][$domain]['action'];
	}

	public function generate($contrId,$newResource){
		$contr = $this->getController2($contrId);
		$params[5013] = $newResource;
		$newResource = $contr->execute($params);
		//$newResource->items[5066][0]="'$contrId'";
		return $newResource;
	}
	
	public function getRealAction($actionId, $domain){
		$action = $this->getAction($actionId);
		$realAction = $this->perm[$action->items[5062]][$domain]['action'];
		return $realAction;
	}


	public function getResProperty($id,$propId,$direct = 0,$resource = 1){
		$res = $this->getResProperty2($id,$propId,$direct,$resource);
		return $res[0];
	}

	//Получение значений свойств ресурса включая значения свойств родителя
	public function getResPropertyTransitive($id,$propId,$direct = 0,$resource = 1,&$props){
		$ret = $this->getResProperty2($id,$propId,$direct,$resource);
		$props = array_merge($props,$ret);
		//$props = $this->getResource(1511, $filters);
		$pClassIds = $this->getResProperty2($id,5061,0); //5061.Подкласс
		if (!empty($pClassId)) foreach ($pClassIds as $pClassId){
			$this->getResPropertyTransitive($pClassId,$propId,$direct,$resource,$props);
		};
		return $props;
	}

	//Получение значений свойств ресурса
	public function getResProperty2($id,$propId,$direct = 0,$resource = 1){
		if ($propId == 5051){ //5051 - Тип
			$query = 
			"select type res
			from dim_resource
			where id = $id";
		} else if ($propId == 503){ //503.Местоположение
			$query = "select location res from entities e where e.id = $id";
		} else if ($propId == 50215) {
			$query = "select save_contr res from entities e where e.id = $id";
		}
		else {
			if ($direct == 0) {$relation1 = 'subj_id'; $relation2 = 'obj_id';} else {$relation1 = 'obj_id'; $relation2 = 'subj_id';}
			if ($resource == 0) $relation2 = 'value';
			$query = 
			"select $relation2 res
			from triplets
			where $relation1 = $id and prop_id = $propId and end_date='9999-01-01'";
		}
		//echo $query;
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$colNum = 0;
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$ret[$colNum] = $line['res'];
			$colNum++;
		}
		mysqli_free_result($result);
		return $ret;
	}

	public function getResPropertyGen($id,$propId,$valIsResource = 1){
		$query = "select obj_id, value from triplets where subj_id = $id and prop_id = $propId and end_date='9999-01-01'";
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$colNum = 0;
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			if ($valIsResource == 1) $ret[$colNum] = $line['obj_id'];
			else $ret[$colNum] = $line['value'];
			$colNum++;
		}
		mysqli_free_result($result);
		return $ret;
	}


  
  //Получение идентификатора элемента интерфейса для просмотра единичной сущности по идентификатору класса
	/*
	function getEShowElement($classId){
	$query = "select t.subj_id elem_id from triplets t 
                join triplets t2 on t2.subj_id = t.subj_id and t2.prop_id = 507
              where t.obj_id = 115 and t.prop_id = 5015 and t2.obj_id = $classId";
	//echo $query;
	$result = mysqli_query($this->link, $query) or die('Запрос не удался: ' . mysqli_error());
	$line = mysqli_fetch_array($result, MYSQL_ASSOC);
	mysqli_free_result($result);
	return $line['elem_id'];
  }
	*/

  

  /*
	function getControllerName($elemId){
	if (empty($elemId)) return $retval;
	$retval = $this->getResProperty($elemId,5014,0,0); //5014.Исполнитель
	if (empty($retval)){
		$query = "select t2.value from dim_resource r
		join triplets t1 on t1.subj_id = r.id and t1.prop_id = 5015 -- Представитель
		join triplets t2 on t2.subj_id = t1.obj_id and t2.prop_id = 5014 -- Исполнитель
		where r.id = $elemId";
		$result = mysqli_query($this->link, $query) or die('Запрос не удался: ' . mysqli_error());
		$line = mysqli_fetch_array($result, MYSQL_ASSOC);
		$retval = $line['value'];
	}
	return $retval;
	}
  */

	public function getResource($resourceId, $type = null){
		if (empty($type)) $type = $this->getResProperty($resourceId,5051);
		$filters[5048] = '%COLUMN% = '.$resourceId;
		$filters[50114] = '%COLUMN% = "9999-01-01"';
		$resources = $this->getResources($type,$filters);
		return $resources[0];
	}

	public function getResourceOpt($resourceId, $type = null){
		if (empty($type)) $type = $this->getResProperty($resourceId,5051);
		$filters[5048] = '%COLUMN% = '.$resourceId;
		$resources = $this->getResourcesOpt($type,$filters);
		return $resources[0];
	}

	public function getResource2($resourceId, $type = null){
		if (empty($type)) $type = $this->getResProperty($resourceId,5051);
		$filters[5048] = '%COLUMN% = '.$resourceId;
		$filters[50114] = '%COLUMN% = "9999-01-01"';
		$resources = $this->getResources2($type,$filters);
		return $resources[0];
	}

	public function getResource2Opt($resourceId, $type = null){
		if (empty($type)) $type = $this->getResProperty($resourceId,5051);
		$filters[5048] = '%COLUMN% = '.$resourceId;
		$filters[50114] = '%COLUMN% = "9999-01-01"';
		$resources = $this->getResourcesOpt($type,$filters);

		$entProps = array();
		$this->getClassPropertiesTransitive($type, $entProps); //1511.Свойство сущности
		if (!empty($resources[0])) foreach ($entProps as $entProp) {
			if ($entProp->items[5084] == 1) { //5084.Флаг внешнего свойства
				$propId = $entProp->items[5082];
				$domain = $entProp->items[5055];
				$propValues = $this->getResPropertyGen($resourceId,$propId,!$this->isSubclassOf($domain,133));
				$resources[0]->items[$propId]=$propValues;
			}
		}

		return $resources[0];
	}

	public function getCurrentResource2($resourceId, $type = null){
		if (empty($type)) $type = $this->getResProperty($resourceId,5051);
		$filters[5048] = '%COLUMN% = '.$resourceId;
		$filters[50114] = '%COLUMN%="9999-01-01"';//50114.Дата закрытия записи
		$resources = $this->getResources2($type,$filters);
		return $resources[0];
	}

	//todo
	function getResourcesGen($filters = null, $type = null, $orders = null){
		if (!empty($type)) {
			$result = array();
			$this->getSubClass($type,$result);
			$whereType = 'and type in('.$type;
			if (!empty($result)) foreach ($result as $subClass){
				$whereType = $whereType.','.$subClass;
			}
			$whereType = $whereType.')';
		}
		//$props = array(5048,501,50100);
		if (!empty($filters[5048])) $filterId = 'and '.str_replace('%COLUMN%', 'id', $filters[5048]);
		if (!empty($filters[501])) $filterName = 'and '.str_replace('%COLUMN%', 'name', $filters[501]);
		if (!empty($filters[50100])) $filterSearchName = 'and '.str_replace('%COLUMN%', 'search_name', $filters[50100]);
		
		if (!empty($orders[5048])) $order = $order . "," . 'id';
		if (!empty($orders[501])) $order = $order . "," . 'name';
		if (!empty($orders[50100])) $order = $order . "," . 'search_name';

		$where = substr($whereType.$filterId.$filterName.$filterSearchName,0);
		$where = ' where end_date="9999-01-01" '.$where;
		if (!empty( $order))  $order = ' order by '.substr($order,1);

		$query = "select id,name,search_name,present_name,name_template,search_name_template,present_name_template,type from dim_resource ".$where.$order;
		$this->log("QUERY:".$query);
		//file_put_contents("log",$query,FILE_APPEND);
		//echo $query;
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$lineNum = 0;
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$ret[$lineNum] = new Resource();
			$ret[$lineNum]->items[5048] = $line['id'];
			$ret[$lineNum]->items[501] = $line['name'];
			$ret[$lineNum]->items[50100] = $line['search_name'];
			$ret[$lineNum]->items[5051] = $line['type'];
			//$ret[$lineNum]->items[50133] = $line['name_template'];
			//$ret[$lineNum]->items[50134] = $line['search_name_template'];
			//$ret[$lineNum]->items[50135] = $line['present_name_template'];
			$ret[$lineNum]->items[50136] = $line['present_name'];
			$lineNum++;
		}
		return $ret;

	}

	//Получение массива ресурсов
	function getResources($entId, $filters = null, $orders = null, $resultSet = 0){
		$tableName = $this->getResProperty($entId,503,0,0); //503.Местоположение
		if (empty($tableName)) return null;

		$entProps = array();
		$this->getClassPropertiesTransitive($entId, $entProps); //1511.Свойство сущности
		$propNum = 0; $where = ''; $order = ''; $columns = '';
		$innerVirtualFlag = 0;
		foreach ($entProps as $entProp) {
			if ($entProp->items[5084] == 0) { //5084.Флаг внешнего свойства
				if ($entProp->items[50110] == 1) { //50110.Флаг виртуального свойства
					$innerVirtualFlag=1;
				} else {
					$propId = $entProp->items[5082]; //5082.Идентификатор свойства
					$propAlias = $entProp->items[506]; //506.Псевдоним
					$props[$propNum]['id'] = $propId;
					$props[$propNum]['alias'] = $propAlias;
					if (!empty($filters[$propId])) {
						//echo $filters[$line[prop_id]];
						$filter = str_replace('%COLUMN%', $propAlias, $filters[$propId]);
						//if (($line[domain] == 134) || ($line[domain] == 136)) $filter = $line[alias]." = '".$filters[$line[prop_id]]."'"; else $filter = $line[alias]." = ".$filters[$line[prop_id]];
						$where = $where . " and " . $filter;
					}

					if (!empty($orders[$propId])) {
						$order = $order . "," . $propAlias;
					}
					$columns = $columns . ',' . $propAlias;
					$propNum++;
				}
			}
		}
		if (!empty($filters[50114])) $where = $where . " and end_date = '9999-01-01'";
		$columns = substr($columns,1);
		if (!empty($where)) $where = " where ".substr($where,5); else $where = "";
		if (!empty($order)) $order = " order by ".substr($order,1); else $order = "";
		$query = "select ".$columns." from ".$tableName.$where.$order;
		//echo $query;
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$lineNum = 0;
		if ($innerVirtualFlag == 1) $resultSet = 1;
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$res = new Resource();
			foreach ($props as $prop) $res->items[$prop['id']] = $line[$prop['alias']];
			if ($resultSet == 1) $ret[$res->items[5048]] = $res;//5048.Идентификатор
			else $ret[$lineNum] = $res;
			$lineNum++;
		}
		if ($innerVirtualFlag == 1){
			foreach ($entProps as $entProp) {
				if ($entProp->items[50110] == 1) { //50110.Флаг виртуального свойства
					//echo $entProp->items[5082];
					$inversePropId = $this->getResProperty($entProp->items[5082],5049); //5082.Идентификатор свойства, 5049.Является обратным
					$propId = $entProp->items[5082];
					//echo 'PropId = '.$propId.'<BR>';
					//$inversePropId = $inverseProp[0];
					//echo 'InverseProp = '.$inversePropId.'<BR>';
					foreach ($ret as $line){
						//echo 'ParentId = '.$line->items[$inversePropId].', ChildId = '.$line->items[5048].'<br>';
						$prop = $ret[$line->items[$inversePropId]]->items[$propId];
						$ret[$line->items[$inversePropId]]->items[$propId][count($prop)] = $line->items[5048] ;//5082.Идентификатор свойства,5048.Идентификатор
					}
				}
			}
		}
		mysqli_free_result($result);
		return $ret;
	}

	//Получение массива ресурсов со всеми свойствами
	public function getResources2($entId, $filters = null, $orders = null){
		//echo $entId;
		$resources1 = $this->getResources($entId, $filters, $orders);
		//print_r($resources1);
		//print_r($resources1);
		$resources2 = array();
		$lineCounter = 0;
		if (!empty($resources1)) foreach ($resources1 as $resource1){
			foreach ($resource1->items as $propId => $propValue) {
				$resources2[$lineCounter]->items[$propId][0] = $propValue;
			}
			$lineCounter++;
		}

		$lineNum = 0;
		$entProps = array();
		$this->getClassPropertiesTransitive($entId, $entProps); //1511.Свойство сущности
		foreach ($resources2 as $key => $resource2) {
			/*
			$query = "select id, ent_id, prop_id, alias, domain, external,virtual
			from ent_properties
			where ent_id = $entId and external = 1";
			$result = mysqli_query($this->link, $query) or die('Запрос не удался: ' . mysqli_error());
			*/
			foreach ($entProps as $extProp){
				if ($extProp->items[5084] == 1) { //5084.Флаг внешнего свойства
					if ($extProp->items[50110] == 1) {//50110.Флаг виртуального свойства
						$direct = 1;
						$propId = $this->getResProperty($extProp->items[5082], 5049, 0); //5082.Идентификатор свойства, 5049.Явдяется обратным
					} else {
						$direct = 0;
						$propId = $extProp->items[5082]; //5082.Идентификатор свойства
					}
					//echo 'propId = '.$extProp->items[5082];
					$values = $this->getResProperty2($resource2->items[5048][0], $propId, $direct,1);
					$valCounter = 0;
					if (!empty($values)) foreach ($values as $value) {
						//echo 'propId = '.$extProp->items[5082].' value = '. $value;
						$resources2[$lineNum]->items[$extProp->items[5082]][$valCounter] = $value; //5082.Идентификатор свойства
						$valCounter++;
					}

				}
			}
			$lineNum++;
		}
		return $resources2;
	}

	public function getResources2Opt($type, $filters = null, $orders = null, $formId = null){
		$resources1 = $this->getResourcesOpt($type, $filters, $orders, $formId);

		$tableName = $this->getResProperty($type,503,0,0); //503.Местоположение
		$tableCounter = 1;
		$firstTableAlias = "l".$tableCounter;
		$select = ",".$firstTableAlias.".id '5048'";
		$from = " from ".$tableName." ".$firstTableAlias;
		$entProps = array();
		$this->getClassPropertiesTransitive($type, $entProps); //1511.Свойство сущности
		$searchFlag = 0;
		foreach ($entProps as $entProp) {
			if ($entProp->items[5084] == 1) { //5084.Флаг внешнего свойства
				$searchFlag = 1;
				$propId = $entProp->items[5082];
				$tableCounter++;
				$tableAlias = "l".$tableCounter;
				$select = $select.",".$tableAlias.".obj_id '".$propId."'";
				$from = $from." left join triplets ".$tableAlias." on ".$tableAlias.".subj_id=".$firstTableAlias.".id and ".$tableAlias.".prop_id=".$propId." and ".$tableAlias.".end_date='9999-01-01'";
			}
		}
		if ($searchFlag == 0) return $resources1;

		if (!empty($filters[50114])) $where = " where ".$firstTableAlias.".end_date = '9999-01-01'";

		$query = "select ".substr($select,1).$from.$where;
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$oldId = null;
		$valueCounter = null;
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			$id = $line[5048];
			//echo 'id = '.$id.','.$oldId.'<br>';
			if ($id != $oldId) $valueCounter = null;
			$oldId = $id;
			foreach ($entProps as $entProp) {
				if ($entProp->items[5084] == 1) { //5084.Флаг внешнего свойства
					$propId = $entProp->items[5082];
					if (!empty($line[$propId])) {
						//echo 'valueCounter[$propId]='.$valueCounter[$propId].'<br>';
						if (isset($valueCounter[$propId])) $valueCounter[$propId]++; else $valueCounter[$propId] = 0;
						//echo 'valueCounter[$propId]='.$valueCounter[$propId].'<br>';
						$extProps[$id][$propId][$valueCounter[$propId]] = $line[$propId];
					}
				}
			}
		}
		//echo '<br><br>';
		//print_r($extProps);
		//echo '<br><br>';

		if (!empty($resources1)) foreach ($resources1 as $resource1){
			$id = $resource1->items[5048][0];
			foreach ($entProps as $entProp) {
				if ($entProp->items[5084] == 1) { //5084.Флаг внешнего свойства
					$valueCounter = 0;
					$propId = $entProp->items[5082];
					if (!empty($extProps[$id][$propId])) foreach ($extProps[$id][$propId] as $value) {
						//echo $value;
						$resource1->items[$propId][$valueCounter] = $value;
						$valueCounter++;
					}
				}
			}
		}

		//echo $query;
		return $resources1;
	}

	public function getResourcesOpt($type, $filters = null, $orders = null, $formId = null){
		$tableName = $this->getResProperty($type,503,0,0); //503.Местоположение
		$tableCounter = 1;
		$firstTableAlias = "l".$tableCounter;
		$entProps =  $this->getClassProperties($type);
		if (!empty($formId)) $formPropsIds = $this->getFormProps($formId);
		$propList = $this->generatePropList($firstTableAlias,$entProps,$filters, $orders,$formPropsIds);
		$select = $propList[0];
		$where = $propList[1];
		$order = $propList[2];
		$from = " from ".$tableName." ".$firstTableAlias;

		$pClasses = $this->getResProperty2($type,5061,0);
		if (!empty($pClasses)) foreach ($pClasses as $pClass){
			$tableName = $this->getResProperty($pClass,503,0,0); //503.Местоположение
			$entProps =  $this->getClassProperties($pClass);
			$tableCounter++;
			$tableAlias = "l".$tableCounter;
			$propList = $this->generatePropList($tableAlias,$entProps,$filters, $orders,$formPropsIds);
			$select = $select.$propList[0];
			$where = $where.$propList[1];
			$order = $order.$propList[2];
			if (!empty($filters[50114])) $where = $where . " and ".$tableAlias.".end_date = '9999-01-01'";
			if (!empty($propList[0])||!empty($propList[1])||!empty($propList[2])) $from = $from." join ".$tableName." ".$tableAlias." on ".$tableAlias.".id=".$firstTableAlias.".id";
		}
		if (!empty($filters[50114])) $where = $where . " and ".$firstTableAlias.".end_date = '9999-01-01'";
		if (!empty($where)) $where = " where ".substr($where,5);
		if (!empty($order)) $order = " order by ".substr($order,1);
		$query = "select ".substr($select,1).$from.$where.$order;
		//file_put_contents("log_sql","\n".$query."\n",FILE_APPEND);
		//echo "<br>".$query."<br>";
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$lineNum = 0;
		$entProps = array();
		$this->getClassPropertiesTransitive($type, $entProps); //1511.Свойство сущности
		while ($line = mysqli_fetch_array($result, MYSQL_ASSOC)) {
			foreach ($entProps as $entProp) {
				if ($entProp->items[5084] == 0) { //5084.Флаг внешнего свойства
					$propId = $entProp->items[5082];
					$items[$propId][0]=$line[$propId];
				}
			}

			$res = new Resource2($items);
			$ret[$lineNum]=$res;
			$lineNum++;
		}
		return $ret;

	}

	public function getResourceValue($resourceId,$template){
		$this->log("Model.getResourceValue: START: ResourceId=".$resourceId.", Template=".$template);
		if (empty($resourceId)) return '';
		$type = $this->getResProperty($resourceId,5051);
		$resource = $this->getResource($resourceId, $type);
		$props = array();
		$this->getClassPropertiesTransitive($type, $props);

		foreach ($props as $prop){
			//echo 'step';
			if ($prop->items[5084] == 0) { //5084.Флаг внешнего свойства
				$propId = $prop->items[5082];//5082.Идентификатор свойства
				//echo ','.$propId.',';
				$template = str_replace('%' . $propId . '%', $resource->items[$propId], $template);//5082.Идентификатор свойства
			}
		}
		$this->log("Model.getResourceValue: END: Template=".$template);
		return $template;
	}

	//Получение класса хранимых сущностей таблицы
	function getStorageClass($tableName){
		$query = "select class_id from sTables t
              where t.name = '".$tableName."'";
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$line = mysqli_fetch_array($result, MYSQL_ASSOC);
		mysqli_free_result($result);
		return $line['class_id'];
	}

	//Получить все подклассы данного класса
	public function getSubClass($resourceId, &$result){
		$ret = $this->getResProperty2($resourceId,5061,1);
		if (!empty($ret)) $result = array_merge($result,$ret);
		if (!empty($ret)) foreach($ret as $child){
			$this->getSubClass($child,$result);
		}
	}

	public function generatePropList($tableAlias, $entProps, $filters = null, $orders = null, $formPropsIds = null){
		$selectList = '';
		$whereList = '';
		$orderList = '';
		foreach ($entProps as $prop) {
			if ($prop->items[5084]==0) {
				$propId = $prop->items[5082]; //5082.Идентификатор свойства
				$alias = $prop->items[506];//506.Псевдоним
				$selectList = $selectList . ',' . $tableAlias . '.' . $alias . ' "' . $propId.'"';
				if (!empty($filters[$propId])) $whereList = $whereList . ' and ' . str_replace('%COLUMN%', $tableAlias.".".$alias, $filters[$propId]);
				if (!empty($orders[$propId])) {
					if ($orders[$propId]==1) $direction = ' ASC'; else $direction = ' DESC';
					$orderList = $orderList . ',' . $tableAlias . '.' . $alias.$direction;
				}
			}
		}
		$ret[0] = $selectList;
		$ret[1] = $whereList;
		$ret[2] = $orderList;
		return $ret;
	}

	public function generateName($resourceId, $type){
		//echo 'ResourceId = '.$resourceId;
		$resource2 = $this->getResource2($resourceId,$type);
		//print_r($resource2);
		$typeResource = $this->getResource($type,10);
		$nameTemplate = $typeResource->items[50133];//15133.Шаблон имени ресурса
		$searchNameTemplate = $typeResource->items[50134];//15134.Шаблон строки поиска
		$presentNameTemplate = $typeResource->items[50135];//15135.Шаблон строки  представления
		//echo 'Templates:'.$nameTemplate.$searchNameTemplate.$presentNameTemplate;
		$props = array();
		$this->getClassPropertiesTransitive($type,$props);
		foreach ($props as $prop){
			$propId = $prop->items[5082]; //5082.Идентификатор свойства
			$domain = $prop->items[5055];
			if (($domain == 134) || ($domain == 135) || ($domain == 136)) $resourceFlag = 0;
			else $resourceFlag = 1;
			if ($resourceFlag && $propId != 5048  && !empty($resource2->items[$propId][0])){
				$filters[5048] = '%COLUMN%='.$resource2->items[$propId][0];
				$valueResource = $this->getResourcesGen($filters,$domain);
				$value = $valueResource[0]->items[501];
			} else $value = $resource2->items[$propId][0];

			$nameTemplate = str_replace('%'.$propId.'%', $value, $nameTemplate);
			$searchNameTemplate = str_replace('%'.$propId.'%', $value, $searchNameTemplate);
			$presentNameTemplate = str_replace('%'.$propId.'%', $value, $presentNameTemplate);

		}
		$query = "update dim_resource set name = '".$nameTemplate."', search_name = '".$searchNameTemplate."', present_name = '".$presentNameTemplate."' where id = ".$resource2->items[5048][0];
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		//echo 'Templates:'.$nameTemplate.$searchNameTemplate.$presentNameTemplate;

	}

	//Создание сущности
	public function insert($resource2, $type = null, $start_date = null, $formId = null){
		if (!empty($formId)) $formPropsIds = $this->getFormProps($formId);

		if (empty($type)) $type = $resource2->items[5055][0];
		if (empty($type)) $type = $resource2->items[5055];
		if (empty($start_date)) $start_date = date("Y-m-d H:i:s");
		$resource2->items[50113][0]=$start_date;

		$versionFlag = $this->isSubclassOf($type,1612);//1612.Версионная сущность
		if ($versionFlag) {
			$newState = $this->getId(1615);
			$resource2->items[50131][0]=$newState;//50131.Версионное состояние
		}

		$resourceId = $this->getId($type);
		$resource2->items[5048][0] = $resourceId;
		//audit START
		$params[5062] = 2345; //2345.Создание сущности
		$params[5013] = $resourceId;
		$params[5055] = $type;
		$params[50123] = null;//50123.Начальное состояние
		$params[50124] = $newState; //50124.Конечное состояние
		$params[5057] = null;
		$executeId = $this->insertAudit($params);
		//audit END
		$entProps = array();
		$this->getClassPropertiesTransitive($type,$entProps);
		if (empty($resource2->items[5048][0])) $resource2->items[5048][0] = $this->getId($type);
		$tableName = $this->getResProperty($type,503,0,0);
		//исполнение дополнительных действий и проставление формата даты START
		foreach ($entProps as $propId => $prop) {

			if ($prop->items[5055] == 136) {
				if (!empty($resource2->items[$prop->items[5082]][0])) {
					$date_array = date_parse($resource2->items[$prop->items[5082]][0]);
					$date = date('Y-m-d H:i:s', mktime($date_array['hour'], $date_array['minute'], $date_array['second'], $date_array['month'], $date_array['day'], $date_array['year']));
				}
				if (empty($date) && ($prop->items[50108] == 1)) $date = date("Y-m-d H:i:s");
				$this->log("DATE:".$date);
				$resource2->items[$prop->items[5082]][0] = $date;
				$this->log("DATE2:".$resource2->items[$prop->items[5082]][0]);
			}

			if ($prop->items[50216] == 1) {
				$items2[5051][0] = $prop->items[5055];
				$nEntInst = new Resource2($items2);
				$nEntInstId = $this->insert($nEntInst, $prop->items[5055]);
				$resource2->items[$prop->items[5082]][0] = $nEntInstId;
			}
		}
		//исполнение дополнительных действий END
		$this->insertBody($resource2,$type,$formPropsIds,$entProps,$tableName);
		$this->insertDimResource($resourceId,$type,$start_date);

		foreach ($entProps as $propId => $prop) {
			if (($prop->items[5084] == 1) && (($formPropsIds[$prop->items[5048]] == 1) || (empty($formPropsIds)))) {
				$propId = $prop->items[5082];
				if (!empty($resource2->items[$propId])) foreach ($resource2->items[$propId] as $value) {
					$isResource = !$this->isSubclassOf($prop->items[5055],133);
					if ($prop->items[50110] == 1) $this->insertTriplets();
					else $this->insertTriplets(null, $resourceId, $propId,$value,$isResource,$start_date);
				}
			}
		}

		$end_time = date("Y-m-d H:i:s:u");
		if (!empty($executeId)) $this->updateProperty2($executeId,50122,$end_time,1614,null);//50122.Время окончания,1614.Исполнение действия

		return $resource2->items[5048][0];
	}

	public function insertAudit($params){
		$executeId = $this->getId(1614);//1614.Исполнение действия
		$items[5048][0]=$executeId;
		$items[5079][0]=$this->user_id;//5079.Сотрудник
		$items[5062][0]=$params[5062];//5062.Действие
		$items[5013][0]=$params[5013];//5013.Объект
		$items[5055][0]=$params[5055]; if (empty($items[5055][0])) $items[5055][0]=$params[5055][0];//5055.Домен
		$items[50123][0]=$params[50123];//50123.Состояние 1
		$items[50124][0]=$params[50124];//50124.Состояние 2
		$items[5057][0]=$params[5057];//5057.Идентификатор родителя
		$items[50121][0]=date("Y-m-d H:i:s:u");
		$items[50122][0]=null;
		$execute = new Resource2($items);
		$entProps = array();
		$this->getClassPropertiesTransitive(1614,$entProps);
		$tableName = $this->getResProperty(1614,503,0,0);
		$this->insertBody($execute,1614,null,$entProps,$tableName);//1614.Исполнение действия
		return $executeId;
	}

	public function insertBody($resource2, $type, $formPropsIds, $entProps, $tableName){
		foreach ($entProps as $propId => $prop){
			/*
			if ($prop->items[50216][0]==1) {
				$items2[5051][0] = $prop->items[5055];
				$nEntInst = new Resource2($items2);
				$nEntInstId = $this->insert($nEntInst,$prop->items[5055]);
				$resource2->items[$prop->items[5082]][0]=$nEntInstId;
			}
			*/
			if (//($prop->items[50108] != 1) &&
				($prop->items[50110] != 1) &&
				($prop->items[5084] != 1) &&
				(($formPropsIds[$prop->items[5048]] == 1)||(empty($formPropsIds))) &&
				(!empty($resource2->items[$prop->items[5082]][0]))){//5084.Флаг внешнего свойства,50110.Флаг виртуального свойства
				$columns = $columns.','.$prop->items[506];
				$values = $values.','.$this->quotation($resource2->items[$prop->items[5082]][0],$prop->items[5055]);
			}
		}
		if ($this->isSubclassOf($type,1612)){
			$columns = $columns.',start_date';
			$values = $values.','."'".$resource2->items[50113][0]."'";
		}
		$columns = substr($columns,1);
		$values = substr($values,1);
		$query = 'insert into '.$tableName.' ('.$columns.') values('.$values.')';
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		return $resource2->items[5048][0];
	}

	public function insertDimResource($id,$type,$startDate){
		$query = 'insert into dim_resource(id,type,start_date) values ('.$id.','.$type.',"'.$startDate.'")';
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$this->generateName($id, $type); //Генерация имен ресурса
	}


	public function insertTriplets($id, $subjId,$propId,$value,$isResource = 1, $startDate = null){
		$this->log("Model->insertTriplets");
		if (!empty($value)) {
			if ($isResource) $value_name = 'obj_id'; else $value_name = 'value';

			if (empty($startDate)) $startDate = date("Y-m-d H:i:s");
			$query = "insert into triplets(subj_id,prop_id," . $value_name . ",start_date,end_date) values(" . $subjId . "," . $propId . "," . $value . ",'" . $startDate . "','9999-01-01')";
			$this->log("QUERY:" . $query);
			$result = mysqli_query($this->link, $query);
			if (!$result) {
				$this->log('ERROR: QUERY:' . $query . mysqli_error());
				die('ERROR: QUERY:' . $query . mysqli_error());
			};
		}
		return $startDate;
	}

	//todo
	public function tripletsInsert($id, $subj_id, $prop_id, $obj_id, $value, $is_resource, $start_date){

	}

	public function log($text){
		$date = date("Y-m-d H:i:s");
		file_put_contents("log_sql","\n".$date.' '.$text,FILE_APPEND);
	}
	
	public function isSubclassOf($classId,$pClassId){
		if ($pClassId == 133) {if ($classId == 134 || $classId == 135 || $classId == 136) return 1; else return 0;}
		if ($pClassId == 1612) {if (($classId == 103) || ($classId == 109)|| ($classId == 1010) || ($classId == 1011) || ($classId == 1012)|| ($classId == 1026)|| ($classId == 1016)) return 1; else return 0;};
	}

	public function closeCurrentVersion($resourceId, $type = null, $table = null){
		if (empty($table)) {
			if (empty($type)) $type = $this->getResProperty($resourceId,5051,0,0);//5051.Тип
			$table = $this->getResProperty($type,503,0,0); //503.Местоположение
		}
		$close_date = date("Y-m-d H:i:s");
		//echo $close_date;
		$query = "update ".$table." set end_date = '".$close_date."' where id = ".$resourceId." and end_date = '9999-01-01'";
		//echo $query;
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		return $close_date;
	}

	public function closeCurrentVersionTriplet($subj_id,$prop_id, $value, $isResource=1, $close_date = null){
		if (empty($close_date)) $close_date = date("Y-m-d H:i:s");
		if ($isResource) $value_name = 'obj_id'; else {$value_name = 'value'; $value = '\''.$value.'\'';}
		$query = "update triplets set end_date = '".$close_date."' where subj_id = ".$subj_id." and prop_id = ".$prop_id." and ".$value_name."=".$value." and end_date = '9999-01-01'";
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		return $close_date;
	}


	//Сохраняет изменения в переданной сущности
	public function update($resource2, $formId, $type=null){
		$resourceId = $resource2->items[5048][0];//5048.Идентификатор
		if (empty($type)) $type = $this->getResProperty($resourceId,5051);//5051.Тип
		$tableName = $this->getResProperty($type,503,0,0); //503.Местоположение
		$saveContr = $this->getResProperty($type,50215,0,0); //50215.Контроллер сохранения
		$oldResource = $this->getResource2Opt($resource2->items[5048][0],$type);


		//echo 'OLDID='.$resource2->items[5048][0];
		//echo 'OldState:'.$oldResource->items[50131][0];
		//echo 'FormId:'.$formId;
		$compareFlag = $this->compare($resource2,$oldResource, $formId,0,$type);
		$localCompareFlag = $this->compare($resource2,$oldResource, $formId,1,$type);
		if ($localCompareFlag != 1) {
			//audit START
			if ($this->isSubclassOf($type,1612)) $newState = $this->getId(1615);
			$params[5062] = 2334; //2334.Изменение сущности
			$params[5013] = $resourceId;
			$params[5055] = $type;
			$params[50123] = $oldResource->items[50131][0];
			$params[50124] = $newState;
			$params[5057] = null;
			$executeId = $this->insertAudit($params);
			//audit END
			$close_date = $this->closeCurrentVersion($resourceId, $type);
			//$close_date = date("Y-m-d H:i:s");
			$close_date = new DateTime($close_date);
			$close_date->add(new DateInterval('PT1S'));
			$close_date = $close_date->format("Y-m-d H:i:s");

			//Проставление свойств которых нет в форме START
			if (!empty($formId)) $formPropsIds = $this->getFormProps($formId);
			$entProps = array();
			$this->getClassPropertiesTransitive($type,$entProps);
			foreach($entProps as $entProp){
				//Формат для полей со значением даты START
				if ($entProp->items[5055] == 136) {
					$date = strtotime($resource2->items[$entProp->items[5082]][0]);
					$resource2->items[$entProp->items[5082]][0] = date("Y-m-d H:i:s", $date);
				}
				//Формат для полей со значением даты END

				$propId = $entProp->items[5082];//5082.Идентификатор свойства
				if ($formPropsIds[$entProp->items[5048]] != 1){//5048.ID
					if (!empty($oldResource->items[$propId])) foreach ($oldResource->items[$propId] as $propKey => $propValue){
						$resource2->items[$propId][$propKey] = $propValue;
					}
				}
			}
			//Проставление свойств которых нет в форме END

			$resource2->items[50113][0] = $close_date; //50113.StartDate
			$resource2->items[50114][0] = '9999-01-01'; //50114.CloseDate
			$resource2->items[50131][0] = $newState; //50131.Версионное состояние

			if (!empty($saveContr)) $resource2 = $this->generate($saveContr,$resource2);

			$this->insertBody($resource2,$type,null,$entProps,$tableName);
		}
		if ($compareFlag != 1){
			$entProps = array();
			$this->getClassPropertiesTransitive($type,$entProps);
			$this->updateExternalProperties($resource2,$oldResource,$entProps,$formPropsIds);
		}
		$this->generateName($resourceId, $type); //Генерация имен ресурса
		$end_time = date("Y-m-d H:i:s:u");
		if (!empty($executeId)) $this->updateProperty2($executeId,50122,$end_time,1614,null);//50122.Время окончания,1614.Исполнение действия
		//echo 'CompareFlag='.$compareFlag;
	}

	public function updateExternalProperties($newResource2,$oldResource2,$entProps,$formPropsIds,$closeDate = null){
		//echo 'UpdateExternal';
		$this->log("Model->updateExternalProperties, ID=".$newResource2->items[5048][0]);
		if (empty($closeDate)) $closeDate = date("Y-m-d H:i:s");
		$resourceId = $newResource2->items[5048][0];
		foreach ($entProps as $propId => $prop) {
			if (($prop->items[5084] == 1) && (($formPropsIds[$prop->items[5048]] == 1) || (empty($formPropsIds)))) {
				$propId = $prop->items[5082];
				//echo 'Value='.$oldResource2->items[$propId][1];
				if (!empty($oldResource2->items[$propId])) foreach ($oldResource2->items[$propId] as $oldValue) {
					$isResource = !$this->isSubclassOf($prop->items[5055],133);
					$findFlag = 0;
					if (!empty($newResource2->items[$propId])) foreach ($newResource2->items[$propId] as $newValue) {
						//echo 'OldValue='.$oldValue.',NewValue='.$newValue.'<br>';
						if ($oldValue == $newValue) {$findFlag = 1; break 1;}
					}
					if ($findFlag == 0) $this->closeCurrentVersionTriplet($resourceId,$propId,$oldValue,$isResource,$closeDate);
				}
				if (!empty($newResource2->items[$propId])) foreach ($newResource2->items[$propId] as $newValue) {
					$isResource = !$this->isSubclassOf($prop->items[5055],133);
					$findFlag = 0;
					if (!empty($oldResource2->items[$propId])) foreach ($oldResource2->items[$propId] as $oldValue) {
						//echo 'OldValue='.$oldValue.',NewValue='.$newValue.'<br>';
						if ($oldValue == $newValue) {$findFlag = 1; break 1;}
					}
					if ($findFlag == 0) $this->insertTriplets(null,$resourceId,$propId,$newValue,$isResource,$closeDate);
				}
			}
		}
	}


	//Изменение свойства сущности
	public function updateProperty($entId,$prop,$propValue,$type = null){
		if (empty($type)) $type = $this->getResProperty($entId,5051,0,0);//5051.Тип
		$tableName = $this->getResProperty($type,503,0,0); //503.Местоположение
		if (($prop->items[5055] == 134)||($prop->items[5055] == 136)) $value = '"'.$propValue.'"'; else $value = $propValue;
		if ($prop->items[5084] == 0) {
			$query = 'update '.$tableName.' set '.$prop->items[506].' = '.$value.' where id = '.$entId;
			$this->log("QUERY:".$query);
			$result = mysqli_query($this->link, $query);
			if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		}
	}
	//Изменение свойства сущности с передачей идентификатора свойства
	public function updateProperty2($entId,$propId,$propValue,$type = null){
		if (empty($type)) $type = $this->getResProperty($entId,5051,0,0);//5051.Тип
		$filters[5082] = '%COLUMN%='.$propId;
		$filters[5083] = '%COLUMN%='.$type;
		$prop = $this->getResources(1511, $filters);//1511.Свойство сущности
		$this->updateProperty($entId,$prop[0],$propValue,$type);
	}

	private function quotation($value, $domain){
		//echo $value;
		if (($domain == 134)||($domain == 136)) $value = '"'.$value.'"';
		$this->log('VALUE:'.$value);
		return $value;
	}

	private function loadEntities(){
		//$filters[5051] = 12;
		$entities = $this->getResources(10);
		foreach ($entities as $entity){
			$this->entities[$entity->items[5048]]=$entity;
		}
		//print_r($this->entities);
	}

	private function loadVievers(){
		$filters[5051] = '%COLUMN%='.'12';
		$viewers = $this->getResources(12, $filters);
		foreach ($viewers as $viewer){
			$this->viewers[$viewer->items[5048]]=$viewer;
		}
	}

	private function loadActions(){
		$actions = $this->getResources(23, null);
		foreach ($actions as $action){
			$this->actions[$action->items[5048]] = $action;
			$this->perm[$action->items[5062]][$action->items[5055]]['action']=$action;
		}
		//print_r($actions);
	}

	private function loadControllers(){
		$filters[5051] = '%COLUMN%='.'14';
		$controllers = $this->getResources(14, $filters);
		foreach ($controllers as $controller){
			$this->controllers[$controller->items[5048]]=$controller;
			//$this->actions[]
		}
		//print_r($controllers);
	}

	public function loadPermissions(){
		$apacheSessionId = session_id();
		//echo 'SessionId:'.$apacheSessionId;
		$perms = $this->getResProperty2($this->user_id,5011,0); //5011.Право
		if (!empty($perms)) foreach ($perms as $perm){
			$domain = $this->actions[$perm]->items[5055];
			$actionId = $this->actions[$perm]->items[5062];
			$this->perm[$actionId][$domain]['perm'] = 1;
			//if (empty($domain)) $this->perm[$actionId][132]['perm'] = 1;
		}
		//print_r($this->perm[2315]);
	}

	public function CheckLogin(){
		$uid=$_SESSION['id'];
		$uhash=$_SESSION['hash'];

		if ($uhash=="") { $uhash='432423423';}
		$query = "select hash from users where id = '".$uid."'";
		$this->log("QUERY:".$query);
		$result = mysqli_query($this->link, $query);
		if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
		$line = mysqli_fetch_array($result, MYSQL_ASSOC);
		$this->user_id = $uid;

		return ($uhash==$line['hash']);
	}

	public function LogIn(){
		$ulogin=$_POST['login'];
		$upswd=$_POST['pswd'];
		if (isset($_POST['submit'])){
		if (($ulogin=="") or ($upswd=="")){
			return "101"; // Ошибка - пустой логин или пароль
		} else {
			$upswd=md5(md5($upswd));
			$query = "select id,paswd from users where login='".$ulogin."'";
			$this->log("QUERY:".$query);
			$result = mysqli_query($this->link, $query);
			if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
			$row_cnt = $result->num_rows;
			$line = mysqli_fetch_array($result, MYSQL_ASSOC);
			//Maxim Chernov 2015-10-15 START
			$apacheSessionId = session_id();
			$session = new Resource2(null);
			$session->items[50103][0]=$apacheSessionId;
			$session->items[5079][0]=$line['id'];
			//echo $apacheSessionId.' '.$line['id'];
			$session->items[5055][0]=168;
			$_SESSION['session_id'] = $sessionId = $this->insert($session);
			//Maxim Chernov 2015-10-15 END
			if (($upswd!=$line['paswd']) or ($row_cnt<1)){
				return "102"; // Ошибка - неправильный логин или пароль
			} else {
                $uid=$line['id'];
				$uhash=$this->generateCode(10);
				$query = "update users set hash='".$uhash."' where id = $uid";
				$this->log("QUERY:".$query);
				$result = mysqli_query($this->link, $query);
				if (!$result) {$this->log('ERROR: QUERY:'.$query . mysqli_error()); die('ERROR: QUERY:'.$query . mysqli_error());};
				$_SESSION['id']=$uid;
				$_SESSION['hash']=$uhash;
				$this->user_id=$uid;
				return "100"; // Вход без ошибок
			}
		}
	}

	}

	public function ClearSession(){
		unset($_SESSION['id']);
		unset($_SESSION['hash']);
	}
}

class Table{
	public $cols;
	public $data;
	public $colNum;
	public $ext;
	public $numRow;
}

class Column{
	public $id;
	public $name;
	public $position;
	public $property;
	public $alias;
	public $domain;
	public $type;
	public $template;
	public $editable;
	public $location;
	public $parentId;
	public $external;
	public $value_template;
	public $prop;
	
	public $viewer;
	public $res;
	
	function __construct($id,$name,$position,$property,$alias,$domain,$type,$template,$editable,$location,$parentId,$external){
		$this->id = $id;
		$this->name = $name;
		$this->position = $position;
		$this->property = $property;
		$this->alias = $alias;
		$this->domain = $domain;
		$this->type = $type;
		$this->template = $template;
		$this->editable = $editable;
		$this->location = $location;
		$this->parentId = $parentId;
		$this->external = $external;
	}


}

class Cell{
	public $id;
	//public $extId;
	public $value;

	function __construct($id,$value){
		$this->id = $id;
		$this->value = $value;
	}
}

class Resource{
	public $items;
}

class Resource2{
	public $items;
	function __construct($items){
		$this->items = $items;
	}
}

?>