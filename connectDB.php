<?php
$servername = "localhost"; // if use uic server, change to db.bcrab.cn
$username 	= "root";  // change to your account
$password 	= "";	  // change to your account
$db		    = "hospital";	  // change to your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Global array to track SQL queries
global $sql_queries_log;
$sql_queries_log = [];

/**
 * Execute SQL query with tracking
 * Tracks file name, line number, query type, and execution time
 */
function executeTrackedQuery($conn, $sql) {
    global $sql_queries_log;
    
    // Get caller information
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = isset($backtrace[0]) ? $backtrace[0] : [];
    
    // Extract file name and line number
    $file = isset($caller['file']) ? basename($caller['file']) : 'unknown';
    $line = isset($caller['line']) ? $caller['line'] : 0;
    
    // Detect query type (DML operations)
    $query_type = 'UNKNOWN';
    $sql_upper = strtoupper(trim($sql));
    if (strpos($sql_upper, 'INSERT') === 0) {
        $query_type = 'INSERT';
    } elseif (strpos($sql_upper, 'UPDATE') === 0) {
        $query_type = 'UPDATE';
    } elseif (strpos($sql_upper, 'DELETE') === 0) {
        $query_type = 'DELETE';
    } elseif (strpos($sql_upper, 'SELECT') === 0) {
        $query_type = 'SELECT';
    }
    
    // Execute query and measure time
    $start_time = microtime(true);
    $result = mysqli_query($conn, $sql);
    $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
    
    // Log the query execution
    $sql_queries_log[] = [
        'file' => $file,
        'line' => $line,
        'type' => $query_type,
        'time' => round($execution_time, 2),
        'location' => $file . ':' . $line
    ];
    
    return $result;
}

/**
 * Get all tracked SQL queries
 */
function getTrackedQueries() {
    global $sql_queries_log;
    return $sql_queries_log;
}

/**
 * Clear tracked queries log
 */
function clearTrackedQueries() {
    global $sql_queries_log;
    $sql_queries_log = [];
}

// echo "Connected successfully";
?>
