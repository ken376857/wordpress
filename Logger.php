<?php

class Logger {
    private $logLevel;
    private $logLevels = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3
    ];
    
    public function __construct($logLevel = 'info') {
        $this->logLevel = $logLevel;
    }
    
    public function debug($message, $context = []) {
        $this->log('debug', $message, $context);
    }
    
    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }
    
    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }
    
    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }
    
    public function log($level, $message, $context = []) {
        if (!isset($this->logLevels[$level])) {
            $level = 'info';
        }
        
        if ($this->logLevels[$level] < $this->logLevels[$this->logLevel]) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextString}";
        
        // Write to error log
        error_log($logEntry);
        
        // Write to file log if possible
        $logFile = $this->getLogFile();
        if ($logFile && is_writable(dirname($logFile))) {
            file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
    
    private function getLogFile() {
        $logDir = sys_get_temp_dir() . '/chatgpt-wordpress-logs';
        
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        return $logDir . '/app-' . date('Y-m-d') . '.log';
    }
    
    public function getRecentLogs($lines = 100) {
        $logFile = $this->getLogFile();
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $logs = [];
        $handle = fopen($logFile, 'r');
        
        if ($handle) {
            $logLines = [];
            while (($line = fgets($handle)) !== false) {
                $logLines[] = trim($line);
            }
            fclose($handle);
            
            // Get last N lines
            $logs = array_slice($logLines, -$lines);
        }
        
        return $logs;
    }
    
    public function clearLogs() {
        $logFile = $this->getLogFile();
        
        if (file_exists($logFile)) {
            return unlink($logFile);
        }
        
        return true;
    }
}
?>