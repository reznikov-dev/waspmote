<?php
/*
 *  Copyright (C) 2008 Libelium Comunicaciones Distribuidas S.L.
 *  http://www.libelium.com
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *                                                        )[            ....   
                                                       -Swj[        _swmQQWC   
                                                        -4Qm    ._wmQWWWW!'    
                                                         -QWL_swmQQWBVY"~.____ 
                                                         _dQQWTY+vsawwwgmmQWV! 
                                        1isas,       _mgmQQQQQmmQWWQQWVY!"-    
                                       .s,. -?ha     -9WDWU?9Qz~- -- -         
                                       -""?Ya,."h,   <!`_mT!2-?5a,             
                                       -Swa. Yg.-Q,  ~ ^`  /`   "Sa.           
     aac  <aa, aa/                aac  _a,-4c ]k +m               "1           
    .QWk  ]VV( QQf   .      .     QQk  )YT`-C.-? -Y  .                         
    .QWk       WQmymmgc  <wgmggc. QQk       wgz  = gygmgwagmmgc                
    .QWk  jQQ[ WQQQQQQW;jWQQ  QQL QQk  ]WQ[ dQk  ) QF~"WWW(~)QQ[               
    .QWk  jQQ[ QQQ  QQQ(mWQ9VVVVT QQk  ]WQ[ mQk  = Q;  jWW  :QQ[               
     WWm,,jQQ[ QQQQQWQW')WWa,_aa. SQm,,]WQ[ dQm,sj Q(  jQW  :QW[               
     -TTT(]YT' TTTYUH?^  ~TTB8T!` -TYT[)YT( -?9WTT T'  ]TY  -TY(               
                     
                          www.libelium.com

*  Libelium Comunicaciones Distribuidas SL
*  Author: Carlos Arilla, Esteban Gutierrez
*
*/
require_once('/var/www/ManagerSystem/core/API/phpMQTT.php');
require_once('/var/www/ManagerSystem/core/API/Config/Lite.php');
require_once('/var/www/ManagerSystem/core/API/Logger.class.php');
require_once('/var/www/ManagerSystem/core/globals/config_files.php');

global $LOCALDB_CONFIG_FILE;

$CLOUD_SECTION = "mqtt"; //section name in config file (setup.ini)
$CLOUD_MSG_FILE='/mnt/lib/cfg/mqtt/message_template';
$CLOUD_TOPIC_FILE='/mnt/lib/cfg/mqtt/topic_template';
$CLOUD_LOG_FILE='/mnt/user/logs/mqtt.log';
$CLOUD_CONFIG_FILE='/mnt/lib/cfg/mqtt/setup.ini';
$CLOUD_SYNC_MASK=64;

function get_DB_setup($file){
    global $logger;
    try {
        $DB_local_config = new Config_Lite($file);
        return array(
            'host'       => $DB_local_config->get('DB', 'host','localhost'),
            'port'       => $DB_local_config->get('DB', 'port','3306'),
            'dbname'     => $DB_local_config->get('DB', 'name','MeshliumDB'),
            'username'   => $DB_local_config->get('DB', 'user','root'),
            'password'   => $DB_local_config->get('DB', 'pass','libelium2007'),
            'table'      => $DB_local_config->get('DB', 'parser_table','sensorParser')
            );
    }
    catch(Exception $e) {
        //Error getting setup file
        $logger->addError("Error getting DB setup from file:\n".$e);
        exit();
    }
}

function get_setup($file){
    global $logger, $CLOUD_SECTION;
    try {
        $MQTT_config = new Config_Lite($file);
        return array(
            'host'       => $MQTT_config->get($CLOUD_SECTION, 'mqttserver','localhost'),
            'port'       => $MQTT_config->get($CLOUD_SECTION, 'mqttport','1883'),
            'user'       => $MQTT_config->get($CLOUD_SECTION, 'mqttuser',NULL),
            'pass'       => $MQTT_config->get($CLOUD_SECTION, 'mqttpassword',NULL),
            'qos'        => $MQTT_config->get($CLOUD_SECTION, 'qos' ,'0'),
            'log'        => $MQTT_config->get($CLOUD_SECTION, 'log_level' ,'0'),
            'limit'      => $MQTT_config->get($CLOUD_SECTION, 'limit' ,'200'),
            'interval'   => $MQTT_config->get($CLOUD_SECTION, 'interval' ,'60')
            );
    }
    catch(Exception $e)
    {
        //Error getting setup file
        $logger->addError("Error getting mqtt setup from file:\n".$e);
        exit();
    }
}

function get_template_files(){
    global $CLOUD_MSG_FILE, $CLOUD_TOPIC_FILE;

    $message_template = @file_get_contents($CLOUD_MSG_FILE);
    if ($message_template === FALSE) {
       
        $reply['message'] = "#SENSOR#|#VALUE#|#TIMESTAMP#";
    }
    else {
        $reply['message'] = $message_template;
    }
    $topic_template = @file_get_contents($CLOUD_TOPIC_FILE);

    if ($topic_template === FALSE){
      
        $reply['topic'] = "/#MESHLIUM#/#ID_WASP#";
    }
    else {
        $reply['topic'] = $topic_template;
    }
    return $reply;
}

function template_replace($template,$values){
    $hostname = exec("hostname");
    $keywords = array('#ID#', '#ID_WASP#', "#ID_SECRET#", "#FRAME_TYPE#", "#FRAME_NUMBER#", "#SENSOR#", "#VALUE#", "#MESHLIUM#");
    $replacement = array($values['id'], $values['id_wasp'], $values['id_secret'], $values['frame_type'], $values['frame_number'], $values['sensor'], $values['value'], $hostname);
    $replaced = str_replace($keywords, $replacement, $template);
    //give timestamp proper format
    $timestamp = strtotime($values['timestamp']);
    preg_match_all("/#TS\\(.+?\\\"\\)#/",$template,$matches);
    $ts_replace_by = array();
    $ts_to_replace = array();
    foreach ($matches[0] as $ts)
    {
        $date_pattern = substr($ts,5,-3);
        $ts_formatted =  date($date_pattern,$timestamp);
        $ts_to_replace[] = $ts;
        $ts_replace_by[] = $ts_formatted;
    }
    $replaced_ts = str_replace($ts_to_replace, $ts_replace_by, $replaced);
    return $replaced_ts;
}

//Mark as MQTT synchronized
function mark_as_synced($row){
    global $logger;
    global $DB_config, $localdb, $CLOUD_SYNC_MASK;
    try {
        //Mark as synced:
        $update = $localdb->prepare("UPDATE " . $DB_config['table'] . " SET sync= ? WHERE id= ?");
        $update->bindValue(1, $row['sync']|$CLOUD_SYNC_MASK);
        $update->bindValue(2, $row['id']);
        $update->execute();
    }
    catch (PDOException $e) {
        //Failed updating sync field
        //echo "Error marking as synced data number ". $row['id'] .":\n$e\n";
        $logger->addError("Error marking as synced data number ". $row['id'] .":\n$e\n");
    }
}


/////////////////// Main body

global $CLOUD_LOG_FILE, $CLOUD_CONFIG_FILE, $LOCALDB_CONFIG_FILE, $CLOUD_SYNC_MASK;


$logger = new Logger($CLOUD_LOG_FILE);

//Get setup
if( !isset($CLOUD_CONFIG_FILE) ){ 
    $logger->addError("No Cloud config file\n");
    exit;
}
if( !isset($LOCALDB_CONFIG_FILE) ){ 
    $logger->addError("No LocalDB config file\n");
    exit;
}
if( !isset($CLOUD_SYNC_MASK) ){ 
    $logger->addError("No SyncMask\n");
    exit;
}

$MQTT_config = get_setup($CLOUD_CONFIG_FILE);
$logger->setLogLevelThreshold($MQTT_config['log']);
$DB_config = get_DB_setup($LOCALDB_CONFIG_FILE);

//Get templates
$templates = get_template_files();

//Connect to DB and get a batch of data
$localdsn = 'mysql:host='. $DB_config['host'] . ';port=' . $DB_config['port'] . ';dbname=' . $DB_config['dbname'];
$logger->addInfo("Init...\n");
try {
    //create PDO for local DB
    $localdb = new PDO($localdsn, $DB_config['username'], $DB_config['password']);
    $localdb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    //Send data to MQTT broker
    $host = $MQTT_config['host'];
    $port = $MQTT_config['port'];

    $username = $MQTT_config['user'];
    $password = $MQTT_config['pass'];
    $qos = $MQTT_config['qos'];

    $username = (isset($username)) ? $username : NULL;
    $password = (isset($password)) ? $password : NULL;

    try {
        $MQTT_client = "Meshlium" . rand(10000, 99999);
        $logger->addInfo("MQTT: ". $host . " - " . $port . " - " . $MQTT_client . "\n");
        $MQTT = new phpMQTT($host, $port, $MQTT_client);

        //Select last $gps_limit inserts of the DB
        $select_query = "SELECT * FROM ".$DB_config['table']." WHERE sync & " . $CLOUD_SYNC_MASK ." = FALSE ORDER BY timestamp DESC LIMIT ".$MQTT_config['limit'];
        $logger->addInfo("$select_query\n");
        $stmt = $localdb->prepare($select_query);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $logger->addInfo("MQTT connect: ". $username . " - " . $password . "\n");
        if ($MQTT->connect(true, NULL, $username, $password)) {
            $logger->addInfo("Connected\n");
            foreach ($rows as $row) {
                $message = template_replace($templates['message'], $row);
                $topic = str_replace("\r\n","",template_replace($templates['topic'], $row));
                $logger->addInfo("Publishing\n");
                $MQTT->publish($topic, $message, $qos);
                $logger->addInfo("Mark sync...\n");
                mark_as_synced($row);
                $logger->addInfo("Published topic: " . $topic . " - Message: " . $message . "\n");
            }
            $logger->addInfo("*********Sleeping ".$MQTT_config['interval']." seconds*********\n");
        } else {
            //Connection to MQTT broker failed
            //echo "Error connecting to broker";
            $logger->addError("Error connecting to broker - Host: " . $host . " - Port: " . $port . "\n");
        }
        $MQTT->close();
    }
    catch (Exception $e) {
        //echo "exception - Error connecting to broker - Host: " . $host . " - Port: " . $port . "\n";
        $logger->addError("Error connecting to broker - Host: " . $host . " - Port: " . $port . "\n");
    }
}
catch (PDOException $e) {
    //Failed SELECT from local database
    $logger->addError("Error getting DB data:\n" . $e . "\n");
}
