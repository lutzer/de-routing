<?php

    require 'config.php';
    require 'Slim/Slim.php';
    \Slim\Slim::registerAutoloader();

    $app = new \Slim\Slim();

    
        
    //get routes
    $app->get('/explorations/', 'getExplorations');
    $app->get('/explorations/:id', 'getExploration');
    $app->get('/records/', function() use ($app) {
        $req = $app->request->params('exploration_id');
        getRecords($req); 
    });
    $app->get('/records/:arg', 'getRecord');

    //post routes
    $app->post('/records/submit/', 'submitRecord');
    $app->post('/records/upload/', 'uploadRecordFile');

    $app->run();

    
    /* Exploration Methods */

    function getExploration($arg) {
        try {
            $db = getConnection();
            $stmt = $db->prepare("SELECT * FROM ".DB_TABLE_EXPLORATIONS." WHERE id = :id");
            $stmt->execute(array(':id' => $arg));$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $db = null;
            if (sizeof($result)<1)
                _sendData("No data found for this id.",404);
            _convertJsonColumns($result,array('json'));
            _sendData($result,200,true);
        } catch(PDOException $e) {
            _sendData($e->getMessage(),500); 
        }
    }

    function getExplorations() {
        try {
            $db = getConnection();
            $stmt = $db->query("SELECT * FROM ".DB_TABLE_EXPLORATIONS." WHERE visible = 1 ORDER BY created_time");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $db = null;
            _convertJsonColumns($result,array('json'));
            _sendData($result);
        } catch(PDOException $e) {
            _sendData($e->getMessage(),500); 
        }
    }

    /* Record Methods */

    function getRecord($arg) {
        try {
            $db = getConnection();
            $stmt = $db->prepare("SELECT * FROM ".DB_TABLE_RECORDS." WHERE id = :id");
            $stmt->execute(array('id' => $arg));
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $db = null;
            _convertJsonColumns($result,array('tasks','route','area'));

            // attach file paths
            //foreach($result as $row)
            //    if (!empty($row['tasks']))
            //        _attachUrl($row['tasks'],$row['id']);

            _sendData($result,200,true);
        } catch(PDOException $e) {
            _sendData($e->getMessage(),500); 
        }
    }

    function getRecords($arg = null) {
        try {
            $db = getConnection();
            if ($arg != null) {
                $stmt = $db->prepare("SELECT * FROM ".DB_TABLE_RECORDS." WHERE exploration_id = :exploration_id AND submitted = 1");
                $stmt->execute(array('exploration_id' => $arg));
            } else
                _sendData();
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $db = null;
            _convertJsonColumns($result,array('tasks','route','area'));

            // attach file paths
            //foreach($result as $row)
            //    if (!empty($row['tasks']))
            //        _attachUrl($row['tasks'],$row['id']);
            
            _sendData($result);
        } catch(PDOException $e) {
            _sendData($e->getMessage(),500); 
        }
    }
 
    function submitRecord() {
        try {
            // get and decode JSON request body
            $app = \Slim\Slim::getInstance();
            $request = $app->request();
            $record = $request->post('record');
            $record = json_decode($record,true);

            //check which files need to be uploaded
            $files = array();
            foreach ($record['tasks'] as &$task) {
                foreach ($task['subtasks'] as &$subtask) {
                    if (array_key_exists("result",$subtask) && array_key_exists("resultType",$subtask) && $subtask['resultType'] == "file") {
                        $subtask['fileName'] = basename($subtask["result"]);
                        $files[] = array( "uri" => $subtask["result"],  "name" => $subtask['fileName']);
                    }
                }
            }

            //check if record already exists, if not create it in the database
            try {
                $db = getConnection();
                $stmt = $db->prepare("REPLACE INTO ".DB_TABLE_RECORDS." 
                    (id,exploration_id,explorer,completed,submitted,tasks,route)
                    VALUES (:id,:exploration_id,:explorer,:completed,:submitted,:tasks,:route)");

                $insertData = array(
                    "id" => $record['id'],
                    "exploration_id" => $record['exploration_id'],
                    "explorer" => $record['explorer'],
                    "completed" => 1,
                    "submitted" => 1,
                    "tasks" => json_encode($record['tasks']),
                    "route" => json_encode($record['route'])
                );

                $stmt->execute($insertData);
                $db = null;
            } catch (Exception $e) {
                _sendData($e->getMessage(),500);
            }
            

            //check if upload dir exists, else create it
            $upload_dir = DIR_RECORD_FILES.'/'.$record['id'];
                if(!is_dir($upload_dir))  {
                    mkdir($upload_dir,0755);
                    chmod($upload_dir,0777);
                }

            //compare with the files already uploaded
            foreach ($files as $key => $file) {
                if (file_exists($upload_dir.'/'.$file['name']))
                    unset($files[$key]);
            }

            //tell app which files need to be uploaded
            _sendData(array("files" => array_values($files)));
        } catch (Exception $e) {
            _sendData($e->getMessage(),500); 
        }
    }

    function uploadRecordFile() {
        // get and decode JSON request body
        $app = \Slim\Slim::getInstance();
        $request = $app->request();
        $record_id = $request->post('record_id');
       
        //check if entry already exists in database
        try {
            $db = getConnection();
            $stmt = $db->prepare("SELECT * FROM ".DB_TABLE_RECORDS." WHERE id = :id");
            $stmt->execute(array('id' => $record_id));
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $db = null;
            if (sizeof($result) < 1)
                _sendData("Record entry for upload file does not exist",404);
        } catch (Exception $e) {
            _sendData($e->getMessage(),500);
        }

        // check if any file got submitted
        if (!isset($_FILES['file']))
            sendData("No file submitted",404);
        $file = $_FILES['file'];

        //move file to records directory
        $upload_dir = DIR_RECORD_FILES.'/'.$record_id;
        if (is_uploaded_file($file['tmp_name'])) {
            //_sendData($file['tmp_name']." uploaded successfully.",500);
            move_uploaded_file($file['tmp_name'], $upload_dir.'/'.$file['name']);
            _sendData($file['name']." uploaded successfully.");
        } else
            _sendData("File got not uploaded.",404);
    }

    /* Helper Methods */

    function _convertJsonColumns(&$data,$columns) {
        foreach($data as $key => &$row) {
            foreach($columns as $col)
                $row[$col] = json_decode($row[$col]);
                //echo stripslashes($row[$col]);
        }
    }

    function _attachUrl($array,$record_id) {

        foreach ($array as $key => &$value) {

            if (is_array($value) || is_object($value))
                _attachUrl($value,$record_id);
            else if ($key === 'fileName') {
                $value = DIR_RECORD_URL.$record_id.'/'.$value;
                
            }
        }
    }

    //sends a message with the data
    function _sendData($data = array(), $status = 200, $onlyFirst = false) {
        // headers for not caching the results
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

        // allow all requests
        header("Access-Control-Allow-Orgin: *");
        header("Access-Control-Allow-Methods: *");

        // headers to tell that result is JSON
        header('Content-type: application/json');

        //send status
        header("HTTP/1.1 " . $status . " " . _requestStatus($status));

        // send the result now
        if ($onlyFirst && !empty($data))
            echo json_encode($data[0]);
        else
            echo json_encode($data);

        //end script
        exit(); 
    }

    function _requestStatus($code) {
        $status = array(  
            200 => 'OK',
            404 => 'Not Found',   
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
        ); 
        return ($status[$code])?$status[$code]:$status[500]; 
    }

    function getConnection() {
        $dbhost=DB_SERVER;
        $dbuser=DB_USER;
        $dbpass=DB_PASSWORD;
        $dbname=DB_DATABASE;
        $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);  
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $dbh;
    }
