<?php
/**
* msync : MYSQL SYNC  0.0.1
* Developed By : Shabeer Ali M & Harish U Warrier
* 
*/
class mSync{
	private $hostname;
    private $conn;
    private $db;
    public $is_force_delete;
    public $new_table;
    public $new_field;
    public $alter;
    public $delete_table;
    public $delete_field;
    public $schema;
    public $host='';
    function __construct($host="")
    {
        $this->is_force_delete=false;
        require_once("config.php");
		if(trim($host)=="")
			$this->hostname=current($cfg)['host'];
		else if(isset($cfg[$host]))
			$this->hostname=$host;
		else
			die('Invalid host, Please provide valid host name which you provided in `config.php`!');
        $this->conn=@new mysqli($cfg[$this->hostname]['host'], $cfg[$this->hostname]['user'], $cfg[$this->hostname]['password']);
		if ($this->conn->connect_error) {
			die('Connect Error: ' . $this->conn->connect_error);
		}
        $this->db=$cfg[$this->hostname]['database'];
        if(file_exists('schema.json')){
            $this->schema=json_decode(file_get_contents('schema.json'),true);
        }
    }

	
    public function db_check()
    {
        $this->conn->query('CREATE DATABASE IF NOT EXISTS '.$this->db.';');
        $this->conn->select_db($this->db);
    }
    /*getting table updats*/
    public function update()
    {
        $schema=$this->schema;
        foreach($schema as $table=>$cols){
            
            $res=$this->conn->query('DESCRIBE `'.$table.'`');

            //echo $table.'<br/>';
			$struct=array();
			while ($struct[] = mysqli_fetch_array($res,MYSQLI_ASSOC));
			array_pop($struct);
			//print_r($struct);
			//exit(0);
            /*associating field name*/
            $struct=$this->assoc_prime($struct,array("key"=>"Field"),true);
            $cols=$this->assoc_prime($cols,array("key"=>"Field"),true);
            /*get new field*/
            $fc=array_diff(array_keys($cols),array_keys($struct));
            if(count($fc)>0){
                foreach($fc as $f){
                    $this->new_field[$table][$f]=$cols[$f];
                }
            }
            /*get field to be deleted*/
            $fd=array_diff(array_keys($struct),array_keys($cols));
            if(count($fd)>0){
                foreach($fd as $f){
                   $this->delete_field[$table][$f]=$struct[$f];
                }
            }
            /*detect changed fields */
            foreach($struct as $st=>$s){
                foreach($cols as $co=>$c){
                    if($st==$co){
                        $change=array_diff($c,$s);
                        if(count($change)>0){
                            $this->alter[$table][$co]=$c;
                        }
                    }
                }
            }
        }
    }
	
    /*create table. first process to create new tables*/
    function create_table(){
        $schema=  $this->schema;
        foreach($schema as $table=>$cols){
            $keys="";
            $sql="CREATE TABLE IF NOT EXISTS `$table` (";
            foreach ($cols as $c){
                if(isset($c['Key']) && $c['Key']!=""){
                    if($c['Key']=="PRI"){
                        $keys.=" PRIMARY KEY  (`".$c['Field']."`) ";   
                    }else if($c['Key']=="UNI"){
                        if($keys!=""){
                            $keys.=", ";
                        }
                        $keys.=" UNIQUE (`".$c['Field']."`) "; 
                    }else if($c['Key']=="MUL"){
                        if($keys!=""){
                            $keys.=", ";
                        }
                        $keys.=" INDEX (`".$c['Field']."`) ";  
                    }

                }
                $null=" NOT NULL ";
                if(isset($c['Null'])){
                    if($c['Null']=="YES"){
                        $null=" NULL ";
                    }else if($c['Null']=="NO"){
                        $null=" NOT NULL ";
                    }
                }
                $default="";
                if(isset($c['Default']) && $c['Default']!=NULL){
                    if($c['Default']=='CURRENT_TIMESTAMP'){
                      $default=" default ".$c['Default']." ";

                    }else{
                        $default=" default '".$c['Default']."' ";
                    }
                    
                }
                $extra="";
                 if(isset($c['Extra'])){
                     $extra=$c['Extra'];
                 }
                $sql.="`".$c['Field']."` ".$c['Type'].$null.$default.$extra.", ";
            }
            $sql.=$keys." );";
			
			$result=$this->conn->query("SHOW TABLES LIKE  \"$table\"");
			
			$row=$result->fetch_array(MYSQLI_NUM);
			if(!isset($row[0]))
            {
				if(!$this->conn->query($sql)){
					echo  mysqli_error($this->conn);
					echo "\n".$sql;
					exit ;
				}
				else{
					echo "Table Created : ".$table."\n";
					error_log(PHP_EOL.$sql, 3, "mSync.log");
				}
			}
        }
		$result=$this->conn->query("SHOW TABLES FROM ".$this->db);
		while ($row = mysqli_fetch_assoc($result)) {
			$row=array_values($row);
			if(!isset($this->schema[$row[0]])){
                
                if(!$this->is_force_delete)
                {
				    echo "Do you want to delete table - `".$row[0]."` (y/n):";
				    $line = fgets(STDIN);
                }
                else
                    $line="y";

				if(trim($line)=="y"){
					$this->conn->query("DROP TABLE ".$row[0]);
					echo "Table deleted : ".$row[0]."\n";
				}	
			}	
		}
    }
	
    /* Alter Table*/
    function alter_table($tables,$type="CHANGE"){
        if(count($tables)<=0){
            return;
        }
        foreach ($tables as $tab=>$col){
            $sql=" ALTER TABLE `$tab`";
            $keys="";
            foreach ($col as $c){
                if(isset($c['Key']) && $c['Key']!=""){
                    if($c['Key']=="PRI"){
                        $keys.=" PRIMARY KEY  (`".$c['Field']."`) ";   
                    }else if($c['Key']=="UNI"){
                        if($keys!=""){
                            $keys.=", ";
                        }
                        $keys.=" UNIQUE (`".$c['Field']."`) "; 
                    }else if($c['Key']=="MUL"){
                        if($keys!=""){
                            $keys.=", ";
                        }
                        $keys.=" INDEX (`".$c['Field']."`) ";  
                    }
                }
                $null=" NOT NULL ";
                if(isset($c['Null'])){
                    if($c['Null']=="YES"){
                        $null=" NULL ";
                    }else if($c['Null']=="NO"){
                        $null=" NOT NULL ";
                    }
                }
                $default="";
                if(isset($c['Default']) && $c['Default']!=NULL){
                    if($c['Default']=='CURRENT_TIMESTAMP'){
                        $default=" default ".$c['Default']." ";
                    }else{
                        $default=" default '".$c['Default']."' ";
                    }
                }
                $extra="";
                if(isset($c['Extra'])){
                    $extra=$c['Extra'];
                }
                $rename="";
                if($type=="CHANGE"){
                    $rename=$c['Field'];
                    if(isset($c['Rename']) && $c['Rename']!=""){
                        $rename=$c['Rename'];
                    }
                    $sql.=" CHANGE `".$c['Field']."` `$rename` ".$c['Type'].$null.$default.$extra." ";
					echo "Table : $tab , Change Field : ".$c['Field']."\n";
                }elseif($type=="DROP"){
                    
                    if(!$this->is_force_delete)
                    {
					   echo "Do you want to drop field `".$c['Field']."` from table `".$tab."` (y/n):";
					   $line = fgets(STDIN);
                    }
                    else
                        $line = "y";
                           
					if(trim($line)=="y"){
						$sql.=" DROP `".$c['Field']."` ";
						echo "Table : $tab , Drop Field : ".$c['Field']."\n";
					}
                }else {
                    $sql.=" $type `".$c['Field']."` $rename ".$c['Type'].$null.$default.$extra." ";
					echo "Table : $tab , Added Field : ".$c['Field']."\n";
                }
                if(end($col)!=$c){
                    $sql.=", ";
                }
            }
            $sql.=" ;";
            error_log(PHP_EOL.$sql, 3, "mSync.log");
            $this->conn->query($sql);
        }
    }
    /*associate array with a key*/
    function assoc_prime($array,$keyval,$multi=FALSE){
        $output=array();
        if($multi){
            for($i=0;$i<count($array);$i++){
                $output[$array[$i][$keyval['key']]]=$array[$i];
            }
        }else{
            for($i=0;$i<count($array);$i++){
                $output[$array[$i][$keyval['key']]]=$array[$i][$keyval['value']];
            }
        }
        return $output;
    }
    /*process*/
    function process(){
		error_log(PHP_EOL.PHP_EOL.date("F j, Y, g:i a"), 3, "mSync.log");
        $this->create_table();
        $this->update();
        $this->alter_table($this->alter, "CHANGE");
        $this->alter_table($this->new_field, "ADD");
        $this->alter_table($this->delete_field, "DROP");
    }
}
