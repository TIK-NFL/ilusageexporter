<?php
require __DIR__ . '/vendor/autoload.php';

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

class IliasExporter
{

    private $con = null;

    private $ildbuser;

    private $ildbpassword;

    private $ildb;

    public function __construct()
    {
        $this->ildbuser = getenv('ildbuser') ? getenv('ildbuser') : 'root';
        $this->ildbpassword = getenv('ildbpassword') ? getenv('ildbpassword') : '';
        $this->ildb = getenv('ildb') ? getenv('ildb') : 'ilias';
    }

    private function connectdb()
    {
        $this->con = new mysqli('localhost', $this->ildbuser, $this->ildbpassword, $this->ildb);
        if ($this->con->connect_error) {
            die('Connect Error (' . $this->con->connect_errno . ') ' . $this->con->connect_error);
        }
    }

    private function execute_sessions($registry)
    {
        $result = $this->con->query("select count(distinct user_id) from usr_session where `expires` > UNIX_TIMESTAMP( NOW( ) ) AND user_id != 0");
        $usrs = $result->fetch_row()[0];
        $gauge = $registry->getOrRegisterGauge('ilias', 'session', 'ILIAS Session', [
            'ilsessions'
        ]);
        $gauge->set($usrs, [
            'ilsessions'
        ]);
    }

    private function execute_10minavg($registry)
    {
        $result = $this->con->query("select count(distinct user_id) from usr_session where 10 * 60 > UNIX_TIMESTAMP( NOW( ) ) - ctime AND user_id != 0");
        $usrs = $result->fetch_row()[0];
        $gauge = $registry->getOrRegisterGauge('ilias', '10minavg', 'ILIAS 10 avg', [
            'il10minavg'
        ]);
        $gauge->set($usrs, [
            'il10minavg'
        ]);
    }

    private function execute_60minavg($registry)
    {
        $result = $this->con->query("select count(distinct user_id) from usr_session where 60 * 60 > UNIX_TIMESTAMP( NOW( ) ) - ctime AND user_id != 0");
        $usrs = $result->fetch_row()[0];
        $gauge = $registry->getOrRegisterGauge('ilias', '60minavg', 'ILIAS 60 avg', [
            'il60minavg'
        ]);
        $gauge->set($usrs, [
            'il60minavg'
        ]);
    }

    private function execute_total1day($registry)
    {
        $result = $this->con->query("select count(usr_id) FROM  `usr_data` WHERE last_login >= DATE_SUB( NOW( ) , INTERVAL 1 DAY )");
        $usrs = $result->fetch_row()[0];
        $gauge = $registry->getOrRegisterGauge('ilias', 'total1day', 'Users in 24h', [
            'iltotal1day'
        ]);
        $gauge->set($usrs, [
            'iltotal1day'
        ]);
    }

    private function execute_total90days($registry)
    {
        $result = $this->con->query("select count(usr_id) FROM  `usr_data` WHERE last_login >= DATE_SUB( NOW( ) , INTERVAL 90 DAY )");
        $usrs = $result->fetch_row()[0];
        $gauge = $registry->getOrRegisterGauge('ilias', 'total90days', 'Users in 90 Days', [
            'iltotal90days'
        ]);
        $gauge->set($usrs, [
            'iltotal90days'
        ]);
    }
    
    public function run()
    {
        $adapter = new Prometheus\Storage\InMemory();
        
        $registry = new CollectorRegistry($adapter);
        
        $this->connectdb();
        $this->execute_sessions($registry);
        $this->execute_10minavg($registry);
        $this->execute_60minavg($registry);
        $this->execute_total1day($registry);
        $this->execute_total90days($registry);
        
        if ($this->con) {
            $this->con->close();
        }
        
        $renderer = new RenderTextFormat();
        $result = $renderer->render($registry->getMetricFamilySamples());
        header('Content-type: ' . RenderTextFormat::MIME_TYPE);
        echo $result;
    }
}

$exporter = new IliasExporter();
$exporter->run();
