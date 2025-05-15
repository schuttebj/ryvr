<?php
/**
 * TOTP Authentication Library
 *
 * A simple implementation of RFC 6238 Time-Based One-Time Password Algorithm
 * for the Ryvr AI Platform.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Lib
 */

namespace TOTP;

/**
 * TOTP class for generating and validating Time-Based One-Time Passwords.
 *
 * @package    Ryvr
 * @subpackage Ryvr/Lib
 */
class TOTP {

    /**
     * The secret key
     *
     * @var string
     */
    private $secret;

    /**
     * Number of digits in the OTP
     *
     * @var int
     */
    private $digits = 6;

    /**
     * Time step in seconds
     *
     * @var int
     */
    private $period = 30;

    /**
     * Window of time to allow (periods before/after current)
     *
     * @var int
     */
    private $window = 1;

    /**
     * Hash algorithm to use
     *
     * @var string
     */
    private $algorithm = 'sha1';

    /**
     * Constructor
     *
     * @param string $secret Optional secret key
     */
    public function __construct( $secret = null ) {
        if ( $secret ) {
            $this->secret = $secret;
        }
    }

    /**
     * Generate a random secret key
     *
     * @param int $length Length of the secret key
     * @return string
     */
    public function generateSecret( $length = 16 ) {
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $validCharsLength = strlen( $validChars );
        
        $secret = '';
        
        for ( $i = 0; $i < $length; $i++ ) {
            $secret .= $validChars[ random_int( 0, $validCharsLength - 1 ) ];
        }
        
        $this->secret = $secret;
        return $secret;
    }

    /**
     * Generate a TOTP code for the current time
     *
     * @param int $timestamp Optional timestamp (default: current time)
     * @return string
     */
    public function generateCode( $timestamp = null ) {
        if ( !$this->secret ) {
            return false;
        }
        
        if ( $timestamp === null ) {
            $timestamp = time();
        }
        
        // Calculate counter value based on current time and period
        $counter = floor( $timestamp / $this->period );
        
        // Convert counter to binary (64-bit)
        $binary = pack( 'N*', 0, $counter );
        
        // Base32 decode the secret
        $secretKey = $this->base32Decode( $this->secret );
        
        // Calculate HMAC hash
        $hash = hash_hmac( $this->algorithm, $binary, $secretKey, true );
        
        // Extract a 4-byte dynamic binary code based on the hash
        $offset = ord( $hash[ strlen( $hash ) - 1 ] ) & 0x0F;
        $code = (
            ( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 ) |
            ( ( ord( $hash[ $offset + 1 ] ) & 0xFF ) << 16 ) |
            ( ( ord( $hash[ $offset + 2 ] ) & 0xFF ) << 8 ) |
            ( ord( $hash[ $offset + 3 ] ) & 0xFF )
        ) % pow( 10, $this->digits );
        
        // Ensure code has the correct number of digits
        return str_pad( $code, $this->digits, '0', STR_PAD_LEFT );
    }

    /**
     * Verify a TOTP code
     *
     * @param string $code The code to verify
     * @param int $timestamp Optional timestamp (default: current time)
     * @return bool
     */
    public function verify( $code, $timestamp = null ) {
        if ( !$this->secret ) {
            return false;
        }
        
        if ( $timestamp === null ) {
            $timestamp = time();
        }
        
        // Normalize the code (remove spaces, etc.)
        $code = preg_replace( '/[^0-9]/', '', $code );
        
        // Check if code matches expected format
        if ( strlen( $code ) != $this->digits ) {
            return false;
        }
        
        // Check codes within window
        for ( $i = -$this->window; $i <= $this->window; $i++ ) {
            $checkTime = $timestamp + ( $i * $this->period );
            $expectedCode = $this->generateCode( $checkTime );
            
            if ( hash_equals( $expectedCode, $code ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Set the number of digits
     *
     * @param int $digits Number of digits (6 or 8)
     * @return self
     */
    public function setDigits( $digits ) {
        if ( $digits == 6 || $digits == 8 ) {
            $this->digits = $digits;
        }
        return $this;
    }

    /**
     * Set the time period
     *
     * @param int $period Time period in seconds
     * @return self
     */
    public function setPeriod( $period ) {
        $this->period = max( 1, (int) $period );
        return $this;
    }

    /**
     * Set the window size
     *
     * @param int $window Window size (number of periods before/after)
     * @return self
     */
    public function setWindow( $window ) {
        $this->window = max( 0, (int) $window );
        return $this;
    }

    /**
     * Set the hash algorithm
     *
     * @param string $algorithm Hash algorithm (sha1, sha256, sha512)
     * @return self
     */
    public function setAlgorithm( $algorithm ) {
        $validAlgorithms = [ 'sha1', 'sha256', 'sha512' ];
        if ( in_array( $algorithm, $validAlgorithms ) ) {
            $this->algorithm = $algorithm;
        }
        return $this;
    }

    /**
     * Generate a URL for QR code
     *
     * @param string $accountName Account name (usually email)
     * @param string $issuer Issuer name (app or company name)
     * @return string
     */
    public function getQRCodeUrl( $accountName, $issuer = 'Ryvr Platform' ) {
        if ( !$this->secret ) {
            return false;
        }
        
        $issuer = rawurlencode( $issuer );
        $accountName = rawurlencode( $accountName );
        $secret = rawurlencode( $this->secret );
        
        $url = "otpauth://totp/{$issuer}:{$accountName}?secret={$secret}&issuer={$issuer}";
        $url .= "&algorithm=" . strtoupper( $this->algorithm );
        $url .= "&digits={$this->digits}";
        $url .= "&period={$this->period}";
        
        // Return a Google Chart API URL for the QR code
        return "https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . rawurlencode( $url );
    }

    /**
     * Base32 decode function
     *
     * @param string $string The string to decode
     * @return string
     */
    private function base32Decode( $string ) {
        $string = strtoupper( $string );
        $string = str_replace( '=', '', $string );
        
        $mapping = [
            'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7,
            'I' => 8, 'J' => 9, 'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
            'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
            'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27, '4' => 28, '5' => 29, '6' => 30, '7' => 31
        ];
        
        $binary = '';
        $bits = '';
        
        for ( $i = 0; $i < strlen( $string ); $i++ ) {
            $char = $string[ $i ];
            if ( !isset( $mapping[ $char ] ) ) {
                continue;
            }
            
            $bits .= str_pad( decbin( $mapping[ $char ] ), 5, '0', STR_PAD_LEFT );
        }
        
        for ( $i = 0; $i + 8 <= strlen( $bits ); $i += 8 ) {
            $byte = substr( $bits, $i, 8 );
            $binary .= chr( bindec( $byte ) );
        }
        
        return $binary;
    }
} 