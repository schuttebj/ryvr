<?php
/**
 * Debug functionality for Ryvr AI Platform
 *
 * @package    Ryvr
 * @subpackage Ryvr/Core
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Debug log class for Ryvr
 */
class Ryvr_Debug {
    /**
     * The single instance of the class.
     *
     * @var Ryvr_Debug
     */
    protected static $_instance = null;

    /**
     * Logging levels
     *
     * @var array
     */
    private $levels = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    ];

    /**
     * Current log level setting
     *
     * @var string
     */
    private $current_level = 'info';

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file = '';

    /**
     * Main Debug Instance.
     *
     * Ensures only one instance of the debug logger is loaded.
     *
     * @return Ryvr_Debug - Main instance.
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->setup();
    }

    /**
     * Set up the debugging environment
     */
    private function setup() {
        // Create logs directory if it doesn't exist
        if ( ! is_dir( RYVR_LOGS_DIR ) ) {
            wp_mkdir_p( RYVR_LOGS_DIR );
            
            // Add an index.php file to prevent directory listing
            file_put_contents( RYVR_LOGS_DIR . 'index.php', '<?php // Silence is golden' );
            
            // Add .htaccess to protect logs
            file_put_contents( RYVR_LOGS_DIR . '.htaccess', 'Deny from all' );
        }

        // Set log file path
        $this->log_file = RYVR_LOGS_DIR . 'debug-' . date( 'Y-m-d' ) . '.log';

        // Get log level from settings
        $debug_level = get_option( 'ryvr_debug_level', 'info' );
        if ( array_key_exists( $debug_level, $this->levels ) ) {
            $this->current_level = $debug_level;
        }
    }

    /**
     * Add a log entry
     *
     * @param string $message The log message
     * @param string $level The log level
     * @param string $component The component that generated the log
     * @param array  $context Additional context
     * @return bool Whether the entry was added
     */
    public function log( $message, $level = 'info', $component = 'core', $context = [] ) {
        // Check if we should log this message
        if ( $this->levels[ $level ] > $this->levels[ $this->current_level ] ) {
            return false;
        }

        // Format timestamp
        $timestamp = date( 'Y-m-d H:i:s' );

        // Format context if provided
        $context_string = '';
        if ( ! empty( $context ) ) {
            $context_string = ' | ' . json_encode( $context );
        }

        // Format stack trace if requested and this is an error level
        $trace_string = '';
        if ( in_array( $level, ['emergency', 'alert', 'critical', 'error'] ) && defined( 'RYVR_DEBUG_TRACE' ) && RYVR_DEBUG_TRACE ) {
            $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 );
            $trace_formatted = [];
            
            foreach ( $trace as $i => $call ) {
                if ( $i === 0 ) continue; // Skip this function call
                
                $trace_formatted[] = sprintf(
                    '%s%s%s:%d',
                    isset( $call['class'] ) ? $call['class'] : '',
                    isset( $call['type'] ) ? $call['type'] : '',
                    $call['function'],
                    isset( $call['line'] ) ? $call['line'] : 0
                );
            }
            
            $trace_string = ' | Trace: ' . implode(' > ', $trace_formatted);
        }

        // Format log entry
        $log_entry = sprintf(
            "[%s] [%s] [%s] %s%s%s\n",
            $timestamp,
            strtoupper( $level ),
            $component,
            $message,
            $context_string,
            $trace_string
        );

        // Write to log file
        $result = file_put_contents( $this->log_file, $log_entry, FILE_APPEND );

        return $result !== false;
    }

    /**
     * Emergency log
     *
     * @param string $message The log message
     * @param string $component The component that generated the log
     * @param array  $context Additional context
     * @return bool Whether the entry was added
     */
    public function emergency( $message, $component = 'core', $context = [] ) {
        return $this->log( $message, 'emergency', $component, $context );
    }

    /**
     * Alert log
     *
     * @param string $message The log message
     * @param string $component The component that generated the log
     * @param array  $context Additional context
     * @return bool Whether the entry was added
     */
    public function alert( $message, $component = 'core', $context = [] ) {
        return $this->log( $message, 'alert', $component, $context );
    }

    /**
     * Critical log
     *
     * @param string $message The log message
     * @param string $component The component that generated the log
     * @param array  $context Additional context
     * @return bool Whether the entry was added
     */
    public function critical( $message, $component = 'core', $context = [] ) {
        return $this->log( $message, 'critical', $component, $context );
    }

    /**
     * Error log
     *
     * @param string $message The log message
     * @param string $component The component that generated the log
     * @param array  $context Additional context
     * @return bool Whether the entry was added
     */
    public function error( $message, $component = 'core', $context = [] ) {
        return $this->log( $message, 'error', $component, $context );
    }

    /**
     * Warning log
     *
     * @param string $message The log message
     * @param string $component The component that generated the log
     * @param array  $context Additional context
     * @return bool Whether the entry was added
     */
    public function warning( $message, $component = 'core', $context = [] ) {
        return $this->log( $message, 'warning', $component, $context );
    }

    /**
     * Notice log
     *
     * @param string $message The log message
     * @param string $component The component that generated the log
     * @param array  $context Additional context
     * @return bool Whether the entry was added
     */
    public function notice( $message, $component = 'core', $context = [] ) {
        return $this->log( $message, 'notice', $component, $context );
    }

    /**
     * Info log
     *
     * @param string $message The log message
     * @param string $component The component that generated the log
     * @param array  $context Additional context
     * @return bool Whether the entry was added
     */
    public function info( $message, $component = 'core', $context = [] ) {
        return $this->log( $message, 'info', $component, $context );
    }

    /**
     * Debug log
     *
     * @param string $message The log message
     * @param string $component The component that generated the log
     * @param array  $context Additional context
     * @return bool Whether the entry was added
     */
    public function debug( $message, $component = 'core', $context = [] ) {
        return $this->log( $message, 'debug', $component, $context );
    }

    /**
     * Get the log file content
     *
     * @param string $date Date in Y-m-d format or 'today'
     * @return string The log file content
     */
    public function get_log_content( $date = 'today' ) {
        if ( $date === 'today' ) {
            $date = date( 'Y-m-d' );
        }

        $log_file = RYVR_LOGS_DIR . 'debug-' . $date . '.log';

        if ( ! file_exists( $log_file ) ) {
            return '';
        }

        return file_get_contents( $log_file );
    }
    
    /**
     * Clear log file
     *
     * @param string $date Date in Y-m-d format or 'today'
     * @return bool Whether the file was cleared
     */
    public function clear_log( $date = 'today' ) {
        if ( $date === 'today' ) {
            $date = date( 'Y-m-d' );
        }

        $log_file = RYVR_LOGS_DIR . 'debug-' . $date . '.log';

        if ( ! file_exists( $log_file ) ) {
            return true;
        }

        return file_put_contents( $log_file, '' ) !== false;
    }
    
    /**
     * Get available log files
     *
     * @return array The list of available log files
     */
    public function get_log_files() {
        $files = glob( RYVR_LOGS_DIR . 'debug-*.log' );
        $logs = [];
        
        foreach ( $files as $file ) {
            $filename = basename( $file );
            // Extract date from filename
            if ( preg_match( '/debug-(\d{4}-\d{2}-\d{2})\.log/', $filename, $matches ) ) {
                $logs[] = $matches[1];
            }
        }
        
        // Sort by date descending
        rsort( $logs );
        
        return $logs;
    }
}

/**
 * Get the debug instance
 *
 * @return Ryvr_Debug
 */
function ryvr_debug() {
    return Ryvr_Debug::instance();
}

/**
 * Helper function to log a message
 *
 * @param string $message The log message
 * @param string $level The log level
 * @param string $component The component that generated the log
 * @param array  $context Additional context
 * @return bool Whether the entry was added
 */
function ryvr_log_debug( $message, $level = 'info', $component = 'core', $context = [] ) {
    return ryvr_debug()->log( $message, $level, $component, $context );
}

/**
 * Dump and log a variable
 *
 * @param mixed  $var The variable to dump
 * @param string $component The component that generated the log
 * @return bool Whether the entry was added
 */
function ryvr_dump( $var, $component = 'debug' ) {
    ob_start();
    var_dump( $var );
    $output = ob_get_clean();
    return ryvr_debug()->log( $output, 'debug', $component );
}

/**
 * Log API requests and responses
 *
 * @param string $service Service name
 * @param string $endpoint API endpoint
 * @param array  $request Request data
 * @param mixed  $response Response data
 * @return bool Whether the entry was added
 */
function ryvr_log_api( $service, $endpoint, $request, $response ) {
    $context = [
        'endpoint' => $endpoint,
        'request' => $request,
        'response' => $response,
    ];
    
    return ryvr_debug()->log(
        sprintf( 'API Call: %s - %s', $service, $endpoint ),
        'debug',
        'api',
        $context
    );
} 