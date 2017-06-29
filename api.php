<?php

	try {
		date_default_timezone_set('Europe/Athens');

		if (!file_exists('db/api.db')) {
			mkdir('db');
		}
		$pdo = new PDO('sqlite:db/api.db');
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->exec('create table if not exists notes(id integer primary key, tag text not null, entry text not null unique, active integer default 1)');

		$api = new Api($pdo);

		switch ($api->method()) {
			case 'GET':
				$api->read();
			break;
			case 'PATCH':
				$api->update();
			break;
			case 'PUT':
				$api->create();
			break;
			case 'DELETE':
				$api->update();
			break;
			default:
				echo json_encode(array('message' => 'Method not allowed'), JSON_UNESCAPED_UNICODE) . PHP_EOL;
				http_response_code(405);
			break;
		}
	}
	catch(Exception $e)
	{
		echo json_encode(array('message' => $e->getMessage()), JSON_UNESCAPED_UNICODE) . PHP_EOL;
		http_response_code(500);
	}


	class Api {

		private $pdo = null;

		public function __construct(PDO $pdo) {
			$this->pdo 	= $pdo;
			$this->method  	= $_SERVER['REQUEST_METHOD'];
			$this->path    	= preg_replace('/[^a-z0-9_]+/i', '', explode('/', @trim($_SERVER['PATH_INFO'],'/')));
			$this->table   	= array_shift($this->path);
			$this->id      	= array_shift($this->path)+0;
			$this->input   	= file_get_contents('php://input');
			$this->data   	= json_decode($this->input, true);

			if(!$this->table) throw new Exception("Table name is missing");
			if(json_last_error() !== 0) throw new Exception("Invalid JSON format");
			if(strpos($this->input, '[' ) === 0) throw new Exception("Multiple items are not allowed");
		}

		public function method(){
			return $this->method;
		}

		public function read() {

			$rows 		= array();
			$fields 	= "*";
			$where 		= 'active = 1' .($this->id ? ' AND id=:id' : '');
			$bind   	= $this->id ? array(':id' => $this->id) : null;

			$sql 		= "SELECT " . $fields . " FROM " . $this->table . (!empty($where) ? " WHERE " . $where : "");
			$stmt 		= $this->pdo->prepare($sql);

			$stmt->execute($bind);
			while($row = $stmt->fetch(PDO::FETCH_ASSOC))
				$rows[] = $row;
			self::render($rows, array('message'=>'No record'));
		}

		public function create() {

			$fields 	= array();
			$bind 		= array();

			foreach($this->data as $key=>$value){

				$fields[":$key"] 	= $key;
				$bind[":$key"] 		= $value;
			}
			$sql 		= "INSERT INTO " . $this->table . " (" . implode($fields, ', ') . ") VALUES (:" . implode($fields, ', :') . ");";
			$stmt 		= $this->pdo->prepare($sql);

			$stmt->execute($bind);
			self::render($this->pdo->lastInsertId(), array('message'=>'Insert failed'));
		}

		public function update() {

			$set 		= array();
			$this->data	= $this->method == 'DELETE' ? array('active'=>'0') : $this->data;
			$where	   	= 'id=:id AND active=1';
			$bind   	= array( ':id' => $this->id);

			foreach($this->data as $key=>$value){
				
				$bind[":$key"]		= $value;
				$set[]			= $key. '= :' .$key;
			}
			$sql 		= "UPDATE " . $this->table . " SET " .(implode(', ', $set)). " WHERE " . $where;
			$stmt 		= $this->pdo->prepare($sql);

			$stmt->execute($bind);
			self::render($stmt->rowCount(), array('message'=>'Update failed'));
		}

		private function render($out, $error) {

			if ($out){
				echo json_encode($out, JSON_UNESCAPED_UNICODE) . PHP_EOL;
				$this->method == 'PUT' ? http_response_code(201) : http_response_code(200);
			}
			else {
				echo json_encode($error, JSON_UNESCAPED_UNICODE) . PHP_EOL;
				http_response_code(202);
			}
			exit(1);
		}
	}
