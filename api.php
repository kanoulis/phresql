<?php

	try {
		/* Set timezone and character encoding */
		
		date_default_timezone_set('Europe/Athens');		
		mb_internal_encoding('UTF-8');
		mb_http_output('UTF-8');
		mb_http_input('UTF-8');
		mb_language('uni');
		mb_regex_encoding('UTF-8');
		ob_start('mb_output_handler');
		
		/* Set database */
		
		if (!file_exists('db/api.db')) {
			mkdir('db');
		}
		$pdo = new PDO('sqlite:db/api.db');
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$pdo->exec('create table if not exists notes(id integer primary key, tag text not null, entry text not null unique, active integer default 1)');
		
		/* Enable Cross-Origin Resource Sharing for selected http methods */
		
		$cors_enable = array('PATCH','DELETE','PUT');
		
		/* Serve the request */
		
		$api = new Api($pdo, $cors_enable);

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
				$api->delete();
			break;
			default:
				$api->method_not_allowed();
			break;
		}
	}
	catch(Exception $e)
	{
		$api->error($e->getMessage());
	}


	class Api {

		private $pdo = null;

		public function __construct(PDO $pdo, $cors_enable=false) {
			
			$this->pdo 		= $pdo;			
			$this->method		= $_SERVER['REQUEST_METHOD'];
			$this->cors_enable	= $cors_enable;
			
			if ($this->method == 'OPTIONS' && is_array($this->cors_enable)) {
				if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) && in_array($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'], $this->cors_enable)){
					header('Access-Control-Allow-Origin: *');
					header("Access-Control-Allow-Credentials: true");
					header('Access-Control-Allow-Headers: X-Requested-With');
					header('Access-Control-Allow-Headers: Content-Type');
					header('Access-Control-Allow-Methods: '.implode(',', $this->cors_enable).', GET, OPTIONS');
					header('Access-Control-Max-Age: 86400');
				}
				http_response_code(204);
				exit(0);
			}
			
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

			$rows		= array();
			$fields 	= "*";
			$where 		= $this->id ? 'id=:id' : '';
			$bind   	= $this->id ? array(':id' => $this->id) : null;

			$sql 		= "SELECT " . $fields . " FROM " . $this->table . (!empty($where) ? " WHERE " . $where : "");
			$stmt 		= $this->pdo->prepare($sql);

			$stmt->execute($bind);
			while($row = $stmt->fetch(PDO::FETCH_ASSOC))
				$rows[] = $row;
			self::render($rows ? array('status'=>200, 'message'=>$rows) : array('status'=>202, 'message'=>'No record'));
			
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
			self::render($this->pdo->lastInsertId() ? array('status'=>201, 'message'=>$this->pdo->lastInsertId()) : array('status'=>400, 'message'=>'Insert failed'));
		}

		public function update() {

			$set 		= array();
			$where	   	= 'id=:id';
			$bind   	= array( ':id' => $this->id);

			foreach($this->data as $key=>$value){
				
				$bind[":$key"]		= $value;
				$set[]			= $key. '= :' .$key;
			}
			$sql 		= "UPDATE " . $this->table . " SET " .(implode(', ', $set)). " WHERE " . $where;
			$stmt 		= $this->pdo->prepare($sql);

			$stmt->execute($bind);
			self::render($stmt->rowCount() ? array('status'=>200, 'message'=>$stmt->rowCount()) : array('status'=>400, 'message'=>'Update failed'));
		}
		
		public function delete() {

			$set 		= array();
			$where	   	= 'id=:id';
			$bind   	= array( ':id' => $this->id);

			$sql 		= "DELETE FROM " . $this->table . " WHERE " . $where;
			$stmt 		= $this->pdo->prepare($sql);

			$stmt->execute($bind);
			self::render($stmt->rowCount() ? array('status'=>200, 'message'=>$stmt->rowCount()) : array('status'=>400, 'message'=>'Delete failed'));
		}
		
		public function method_not_allowed(){
			
			self::render(array('status'=>405, 'message'=>'Method not allowed'));
		}
		
		public function error($message){

			self::render(array('status'=>500, 'message'=>$message));
		}

		private function render($output) {

			echo json_encode($output['message'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
			http_response_code($output['status']);
			exit(0);
		}
	}
