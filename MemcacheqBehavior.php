<?php

/**
 * CakePHP MemcacheQ Behavior
 * 
 * Copyright (c) 2012, M@kSEO (http://makseo.ru)
 * 
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 * 
 * @author M@kSEO
 * @copyright Copyright (c) 2012, M@kSEO (http://makseo.ru)
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 */

class MemcacheqBehavior extends ModelBehavior
{
  /**
   * MemcacheQ Settings
   * 
   * @var array 
   */
  public $settings = array
  (
      'hostname' => 'localhost',
      'port' => '22201',
      'timeout' => 15,
      'pconnect' => false,
  );
  
  /**
   * MemcacheQ Instance
   * 
   * @var mixed 
   */
  public $mc;
  
  /**
   * MemcacheQ Errors List
   * 
   * @var array
   */
  public $errors = array('END', 'ERROR', 'NOT_FOUND', 'DELETED');
  
  /**
   * Setup
   * 
   * @param mixed $Model
   * @param array $settings 
   */
  public function setup(&$Model, $settings = array())
  {  
      $this->settings = array_merge($this->settings, $settings);
      
       if ($this->settings['pconnect'])
       {
            $this->mc = memcache_pconnect($this->settings['hostname'], $this->settings['port'], $this->settings['timeout']) or die ("Could not pconnect to MemcacheQ");
       }
       else
       {    
           $this->mc = memcache_connect($this->settings['hostname'], $this->settings['port'], $this->settings['timeout']) or die ("Could not connect to MemcacheQ");
       }
  }
  
  /**
   * Destruct
   */
  public function __destruct()
  {
      // Close connection with MemcacheQ
      memcache_close($this->mc);
  }
  
  /**
   * Return List of Queues
   * 
   * @param mixed $Model
   * @param bool $sort - sort flag
   * @return array
   */
  public function queue_list(&$Model, $sort = false)
  {
      $stats = $this->queue_send_command($Model, 'stats queue');

      $return = array();

      foreach($stats as $key => $stat)
      {
          preg_match('/STAT\s(.*)\s(\d+)\/(\d+)/is', $stat, $matches);
          
          // queue name
          $queue_name = $matches[1];
          
          // total number
          $return[$queue_name][] = $matches[2];
          
          // number of processed
          $return[$queue_name][] = $matches[3];
          
          // number of remaining
          $return[$queue_name][] = $matches[2] - $matches[3];
      }

      if ($sort) ksort($return);
      
      return $return;
  }
  
  /**
   * Return MemcacheQ Statistic
   *  
   * @param mixed $Model
   * @return array 
   */
  public function queue_stats(&$Model)
  {
      return memcache_get_stats($this->mc);
  }
  
  /**
   * Delete Queue
   * 
   * @param mixed $Model
   * @param string $queue_name
   * @return bool 
   */
  public function queue_delete(&$Model, $queue_name)
  {
      return $this->queue_send_command($Model, "delete {$queue_name}");
  }
  
  /**
   * Add Task to Queue
   * 
   * @param mixed $Model
   * @param string $queue_name
   * @param array $task 
   * @return bool
   */
  public function queue_set(&$Model, $queue_name, $task)
  {
      // Convert to JSON
      $task_json = json_encode($task);
      
      return memcache_set($this->mc, $queue_name, $task_json, 0, 0);
  }
  
  /**
   * Get Task form Queue
   * 
   * @param mixed $Model
   * @param string $queue_name
   * @return bool
   */
  public function queue_get(&$Model, $queue_name)
  {
      $task = memcache_get($this->mc, $queue_name);

      return (!$task) ? false : json_decode($task);
  }
  
  /**
   * Define Tasks Count in Queue
   * 
   * @param mixed $Model
   * @param string $queue_name 
   */
  public function queue_count(&$Model, $queue_name)
  {
      $queues = $this->queue_list($Model);
      
      return (isset($queues[$queue_name][2])) ? $queues[$queue_name][2]: 0;
  }
  
  
  /**
   * Flush all Queues
   *
   */
  public function queue_flush(&$Model)
  {
      $queues = $this->queue_list($Model);
      
      foreach($queues as $queue_name => $array)
      {
          $this->queue_delete($Model, $queue_name);
      }
  }
  
  
  /**
   * Send a command to MemcacheQ
   * 
   * @param mixed $model
   * @param string $command
   * @return array 
   */
  private function queue_send_command(&$Model, $command)
  {
      $fp = @fsockopen($this->settings['hostname'], $this->settings['port'], $errno, $errstr, 10);

      $response = array();

      fwrite($fp, $command."\n");

      while(!feof($fp))
      {
          $data = fgets($fp);

          if($data == false)
          {
            $response = false;
            break;
          }

          if(in_array(trim($data), $this->errors)) break;

          $response[] = $data;
      }

      fclose($fp);

      return $response;
  }
}