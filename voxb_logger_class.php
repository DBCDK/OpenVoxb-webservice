<?php

class voxb_logger {
  var $caller;  // Owner object
  var $method;
  var $userId = 0;
  var $timestamp; // float
  var $p1 = 0;
  var $p2 = 0;
  var $p3 = 0;
  var $p4 = 0;
  var $p5 = 0;
  var $p6 = 0;
  var $p7 = 0;
  var $text = "";
  var $error = 0;

  function __construct($caller, $method_name) {
    $this->caller = $caller;
    $this->method = $method_name;
    $this->timestamp = microtime(true); 
  }
  
  function __destruct() {
    $elapsed = microtime(true) - $this->timestamp;
    self::log($elapsed);
  }

  function log($elapsed=0) {
    if (empty($this->caller->oci)) {
      verbose::log(FATAL, "Voxb Error $this->error in $this->method, $elapsed");
    } else {
			$logline="method:".$this->method."userId:".$this->userId." p1:".$this->p1." p2:".$this->p2." p3:".$this->p3." p4:".$this->p4." p5:".$this->p5." p6:".$this->p6." p7:".$this->p7." text:".$this->text." error:".$this->error." elapsed:".$elapsed;
      verbose::log(STAT, $logline);
    }
  }

  function set_error($value) { $this->error = is_int($value) ? $value : 0; }
  function set_userId($value) { $this->userId = is_int($value) ? $value : 0; }
  function add_p1($value) { $this->p1 += $value; }
  function add_p2($value) { $this->p2 += $value; }
  function add_p3($value) { $this->p3 += $value; }
  function add_p4($value) { $this->p4 += $value; }
  function add_p5($value) { $this->p5 += $value; }
  function add_p6($value) { $this->p6 += $value; }
  function add_p7($value) { $this->p7 += $value; }
  function set_text($value) { $this->text = $value; }

}

?>