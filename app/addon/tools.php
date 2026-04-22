<?php

/**
 * Error Logger Class
 * 
 * Handles comprehensive error logging with filesystem access checks
 */
class ErrorLogger
{
    private $logDir;
    private $canWriteToFilesystem;

    public function __construct($logDir = null)
    {
        $this->logDir = $logDir ?: (getcwd() . '/logs');
        $this->canWriteToFilesystem = $this->checkFilesystemWriteAccess();

        if ($this->canWriteToFilesystem && !is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }
    }

    /**
     * Check if filesystem is writable
     * 
     * @return bool True if filesystem is writable, false otherwise
     */
    private function checkFilesystemWriteAccess()
    {
        $testFile = $this->logDir . '/test_write_' . uniqid() . '.tmp';

        // Try to create directory first
        if (!is_dir($this->logDir)) {
            if (!@mkdir($this->logDir, 0755, true)) {
                return false;
            }
        }

        // Test write access
        if (@file_put_contents($testFile, 'test') !== false) {
            @unlink($testFile);
            return true;
        }

        return false;
    }

    /**
     * Log error message
     * 
     * @param string $message Error message
     * @param string $level Error level (ERROR, WARNING, CRITICAL)
     * @param array $context Additional context data
     */
    public function logError($message, $level = 'ERROR', $context = [])
    {
        if (!$this->canWriteToFilesystem) {
            return; // Silently fail if can't write to filesystem
        }

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ];

        $logLine = json_encode($logEntry) . "\n";
        $logFile = $this->logDir . '/ip_geolocation_errors_' . date('Y-m-d') . '.log';

        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Cache Manager Class
 * 
 * Handles caching with multiple fallback mechanisms: SQLite -> Filesystem -> Cookies
 */
class CacheManager
{
    private $cacheType;
    private $cacheDir;
    private $sqliteDb;
    private $canWriteToFilesystem;
    private $isCliMode;
    private $maxRetries;
    private $retryDelay;

    const CACHE_SQLITE = 'sqlite';
    const CACHE_FILESYSTEM = 'filesystem';
    const CACHE_COOKIES = 'cookies';
    const CACHE_TTL = 3600 * 24 * 7; // 1 week default TTL
    const SQLITE_BUSY_TIMEOUT = 30000; // 30 seconds in milliseconds
    const MAX_RETRIES = 3;
    const RETRY_DELAY_MS = 100; // 100ms base delay

    public function __construct($cacheDir = null, $maxRetries = self::MAX_RETRIES, $retryDelay = self::RETRY_DELAY_MS)
    {
        $this->cacheDir = $cacheDir ?: getcwd() . '/cache';
        $this->isCliMode = php_sapi_name() === 'cli';
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
        $this->canWriteToFilesystem = $this->checkFilesystemWriteAccess();
        $this->determineCacheType();
        $this->initializeCache();
    }

    /**
     * Check filesystem write access
     * 
     * @return bool
     */
    private function checkFilesystemWriteAccess()
    {
        if (!is_dir($this->cacheDir)) {
            if (!@mkdir($this->cacheDir, 0755, true)) {
                return false;
            }
        }

        $testFile = $this->cacheDir . '/test_write_' . uniqid() . '.tmp';
        if (@file_put_contents($testFile, 'test') !== false) {
            @unlink($testFile);
            return true;
        }

        return false;
    }

    /**
     * Determine the best available cache type
     */
    private function determineCacheType()
    {
        // Try SQLite first
        if (extension_loaded('sqlite3') && $this->canWriteToFilesystem) {
            $this->cacheType = self::CACHE_SQLITE;
            return;
        }

        // Try PDO SQLite
        if (extension_loaded('pdo_sqlite') && $this->canWriteToFilesystem) {
            $this->cacheType = self::CACHE_SQLITE;
            return;
        }

        // Try filesystem
        if ($this->canWriteToFilesystem) {
            $this->cacheType = self::CACHE_FILESYSTEM;
            return;
        }

        // Fallback to cookies (only if not CLI and max 2 per browser)
        if (!$this->isCliMode) {
            $this->cacheType = self::CACHE_COOKIES;
            return;
        }

        // No caching available
        $this->cacheType = null;
    }

    /**
     * Initialize cache based on determined type
     */
    private function initializeCache()
    {
        if ($this->cacheType === self::CACHE_SQLITE) {
            $this->initializeSqliteCache();
        }
    }

    /**
     * Initialize SQLite cache with production-ready settings for high concurrency
     */
    private function initializeSqliteCache()
    {
        try {
            $dbPath = $this->cacheDir . '/ip_cache.sqlite';

            if (extension_loaded('sqlite3')) {
                $this->sqliteDb = new SQLite3($dbPath);
                $this->configureSqlite3ForProduction();
            } elseif (extension_loaded('pdo_sqlite')) {
                $this->sqliteDb = new PDO('sqlite:' . $dbPath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 30
                ]);
                $this->configurePdoSqliteForProduction();
            }

            $this->createOptimizedSchema();
        } catch (Exception $e) {
            error_log("SQLite cache initialization failed: " . $e->getMessage());
            // Fallback to filesystem if SQLite fails
            $this->cacheType = $this->canWriteToFilesystem ? self::CACHE_FILESYSTEM : self::CACHE_COOKIES;
        }
    }

    /**
     * Configure SQLite3 for production use with high concurrency
     */
    private function configureSqlite3ForProduction()
    {
        // Set busy timeout to handle concurrent access
        $this->sqliteDb->busyTimeout(self::SQLITE_BUSY_TIMEOUT);

        // Enable WAL mode for better concurrency (readers don't block writers)
        $this->sqliteDb->exec('PRAGMA journal_mode = WAL');

        // Set synchronous to NORMAL for better performance while maintaining durability
        $this->sqliteDb->exec('PRAGMA synchronous = NORMAL');

        // Increase cache size for better performance (negative value = KB)
        $this->sqliteDb->exec('PRAGMA cache_size = -64000'); // 64MB cache

        // Set temp store to memory for faster operations
        $this->sqliteDb->exec('PRAGMA temp_store = MEMORY');

        // Optimize for faster writes
        $this->sqliteDb->exec('PRAGMA wal_autocheckpoint = 1000');

        // Set page size for better performance (must be set before any tables are created)
        $this->sqliteDb->exec('PRAGMA page_size = 4096');

        // Enable foreign key constraints
        $this->sqliteDb->exec('PRAGMA foreign_keys = ON');
    }

    /**
     * Configure PDO SQLite for production use with high concurrency
     */
    private function configurePdoSqliteForProduction()
    {
        // Enable WAL mode for better concurrency
        $this->sqliteDb->exec('PRAGMA journal_mode = WAL');

        // Set synchronous to NORMAL for better performance
        $this->sqliteDb->exec('PRAGMA synchronous = NORMAL');

        // Increase cache size for better performance
        $this->sqliteDb->exec('PRAGMA cache_size = -64000'); // 64MB cache

        // Set temp store to memory
        $this->sqliteDb->exec('PRAGMA temp_store = MEMORY');

        // Optimize for faster writes
        $this->sqliteDb->exec('PRAGMA wal_autocheckpoint = 1000');

        // Set page size for better performance
        $this->sqliteDb->exec('PRAGMA page_size = 4096');

        // Enable foreign key constraints
        $this->sqliteDb->exec('PRAGMA foreign_keys = ON');

        // Set busy timeout
        $this->sqliteDb->exec('PRAGMA busy_timeout = ' . self::SQLITE_BUSY_TIMEOUT);
    }

    /**
     * Create optimized database schema with proper indexes
     */
    private function createOptimizedSchema()
    {
        $createTable = "
            CREATE TABLE IF NOT EXISTS ip_cache (
                ip TEXT PRIMARY KEY,
                data TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                expires_at INTEGER NOT NULL
            ) WITHOUT ROWID
        ";

        // Create indexes for better query performance
        $createExpiresIndex = "CREATE INDEX IF NOT EXISTS idx_expires_at ON ip_cache(expires_at)";
        $createCreatedIndex = "CREATE INDEX IF NOT EXISTS idx_created_at ON ip_cache(created_at)";

        if ($this->sqliteDb instanceof SQLite3) {
            $this->sqliteDb->exec($createTable);
            $this->sqliteDb->exec($createExpiresIndex);
            $this->sqliteDb->exec($createCreatedIndex);
        } else {
            $this->sqliteDb->exec($createTable);
            $this->sqliteDb->exec($createExpiresIndex);
            $this->sqliteDb->exec($createCreatedIndex);
        }
    }

    /**
     * Get cached data for IP
     * 
     * @param string $ip IP address
     * @return array|null Cached data or null if not found/expired
     */
    public function get($ip)
    {
        if (!$this->cacheType) {
            return null;
        }

        switch ($this->cacheType) {
            case self::CACHE_SQLITE:
                return $this->getFromSqlite($ip);
            case self::CACHE_FILESYSTEM:
                return $this->getFromFilesystem($ip);
            case self::CACHE_COOKIES:
                return $this->getFromCookies($ip);
        }

        return null;
    }

    /**
     * Check if the exception is a database busy/locked error
     * 
     * @param Exception $e The exception to check
     * @return bool True if it's a busy/locked error, false otherwise
     */
    private function isDatabaseBusyError($e)
    {
        $message = strtolower($e->getMessage());
        return strpos($message, 'database is locked') !== false ||
            strpos($message, 'database is busy') !== false ||
            strpos($message, 'sqlite_busy') !== false;
    }

    /**
     * Store data in cache
     * 
     * @param string $ip IP address
     * @param array $data Data to cache
     * @param int $ttl Time to live in seconds
     */
    public function set($ip, $data, $ttl = self::CACHE_TTL)
    {
        if (!$this->cacheType) {
            return;
        }

        switch ($this->cacheType) {
            case self::CACHE_SQLITE:
                $this->setInSqlite($ip, $data, $ttl);
                break;
            case self::CACHE_FILESYSTEM:
                $this->setInFilesystem($ip, $data, $ttl);
                break;
            case self::CACHE_COOKIES:
                $this->setInCookies($ip, $data, $ttl);
                break;
        }
    }

    /**
     * Get data from SQLite cache with retry logic for high concurrency
     */
    private function getFromSqlite($ip)
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                $now = time();

                if ($this->sqliteDb instanceof SQLite3) {
                    $stmt = $this->sqliteDb->prepare('SELECT data FROM ip_cache WHERE ip = ? AND expires_at > ?');
                    $stmt->bindValue(1, $ip, SQLITE3_TEXT);
                    $stmt->bindValue(2, $now, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $row = $result->fetchArray(SQLITE3_ASSOC);
                } else {
                    $stmt = $this->sqliteDb->prepare('SELECT data FROM ip_cache WHERE ip = ? AND expires_at > ?');
                    $stmt->execute([$ip, $now]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if ($row) {
                    return json_decode($row['data'], true);
                }

                // If we get here, no data was found (not an error)
                return null;
            } catch (Exception $e) {
                $attempt++;

                // Check if it's a database busy/locked error
                if ($this->isDatabaseBusyError($e) && $attempt < $this->maxRetries) {
                    // Wait with exponential backoff
                    $delay = $this->retryDelay * pow(2, $attempt - 1);
                    usleep($delay * 1000); // Convert to microseconds
                    continue;
                }

                // Log the error for debugging
                error_log("SQLite cache read error (attempt $attempt): " . $e->getMessage());

                // If it's not a busy error or we've exhausted retries, return null
                break;
            }
        }

        return null;
    }

    /**
     * Set data in SQLite cache with retry logic for high concurrency
     */
    private function setInSqlite($ip, $data, $ttl)
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                $now = time();
                $expiresAt = $now + $ttl;
                $jsonData = json_encode($data);

                if ($this->sqliteDb instanceof SQLite3) {
                    $stmt = $this->sqliteDb->prepare('INSERT OR REPLACE INTO ip_cache (ip, data, created_at, expires_at) VALUES (?, ?, ?, ?)');
                    $stmt->bindValue(1, $ip, SQLITE3_TEXT);
                    $stmt->bindValue(2, $jsonData, SQLITE3_TEXT);
                    $stmt->bindValue(3, $now, SQLITE3_INTEGER);
                    $stmt->bindValue(4, $expiresAt, SQLITE3_INTEGER);
                    $stmt->execute();
                } else {
                    $stmt = $this->sqliteDb->prepare('INSERT OR REPLACE INTO ip_cache (ip, data, created_at, expires_at) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$ip, $jsonData, $now, $expiresAt]);
                }

                // If we get here, the operation was successful
                return true;
            } catch (Exception $e) {
                $attempt++;

                // Check if it's a database busy/locked error
                if ($this->isDatabaseBusyError($e) && $attempt < $this->maxRetries) {
                    // Wait with exponential backoff
                    $delay = $this->retryDelay * pow(2, $attempt - 1);
                    usleep($delay * 1000); // Convert to microseconds
                    continue;
                }

                // Log the error for debugging
                error_log("SQLite cache write error (attempt $attempt): " . $e->getMessage());

                // If it's not a busy error or we've exhausted retries, break
                break;
            }
        }

        return false;
    }

    /**
     * Get data from filesystem cache
     */
    private function getFromFilesystem($ip)
    {
        $cacheFile = $this->cacheDir . '/' . md5($ip) . '.cache';

        if (file_exists($cacheFile)) {
            $content = @file_get_contents($cacheFile);
            if ($content !== false) {
                $cacheData = json_decode($content, true);
                if ($cacheData && $cacheData['expires_at'] > time()) {
                    return $cacheData['data'];
                } else {
                    @unlink($cacheFile); // Remove expired cache
                }
            }
        }

        return null;
    }

    /**
     * Set data in filesystem cache
     */
    private function setInFilesystem($ip, $data, $ttl)
    {
        $cacheFile = $this->cacheDir . '/' . md5($ip) . '.cache';
        $cacheData = [
            'data' => $data,
            'created_at' => time(),
            'expires_at' => time() + $ttl
        ];

        @file_put_contents($cacheFile, json_encode($cacheData), LOCK_EX);
    }

    /**
     * Get data from cookies (max 2 per browser)
     */
    private function getFromCookies($ip)
    {
        $cookieName1 = 'ip_cache_1';
        $cookieName2 = 'ip_cache_2';

        // Check both cookie slots
        foreach ([$cookieName1, $cookieName2] as $cookieName) {
            if (isset($_COOKIE[$cookieName])) {
                $cookieData = json_decode($_COOKIE[$cookieName], true);
                if ($cookieData && $cookieData['ip'] === $ip && $cookieData['expires_at'] > time()) {
                    return $cookieData['data'];
                }
            }
        }

        return null;
    }

    /**
     * Set data in cookies (max 2 per browser)
     */
    private function setInCookies($ip, $data, $ttl)
    {
        $cookieData = [
            'ip' => $ip,
            'data' => $data,
            'expires_at' => time() + $ttl
        ];

        $cookieValue = json_encode($cookieData);
        $expiry = time() + $ttl;

        // Try to use first slot, then second slot
        $cookieName1 = 'ip_cache_1';
        $cookieName2 = 'ip_cache_2';

        if (
            !isset($_COOKIE[$cookieName1]) ||
            (isset($_COOKIE[$cookieName1]) && json_decode($_COOKIE[$cookieName1], true)['expires_at'] <= time())
        ) {
            setcookie($cookieName1, $cookieValue, $expiry, '/');
        } elseif (
            !isset($_COOKIE[$cookieName2]) ||
            (isset($_COOKIE[$cookieName2]) && json_decode($_COOKIE[$cookieName2], true)['expires_at'] <= time())
        ) {
            setcookie($cookieName2, $cookieValue, $expiry, '/');
        } else {
            // Both slots occupied, replace the oldest one
            $cookie1Data = json_decode($_COOKIE[$cookieName1], true);
            $cookie2Data = json_decode($_COOKIE[$cookieName2], true);

            if ($cookie1Data['expires_at'] < $cookie2Data['expires_at']) {
                setcookie($cookieName1, $cookieValue, $expiry, '/');
            } else {
                setcookie($cookieName2, $cookieValue, $expiry, '/');
            }
        }
    }

    /**
     * Get cache type being used
     * 
     * @return string|null Current cache type
     */
    public function getCacheType()
    {
        return $this->cacheType;
    }

    /**
     * Clear expired cache entries with optimized batch operations
     */
    public function clearExpired()
    {
        if ($this->cacheType === self::CACHE_SQLITE && $this->sqliteDb) {
            $this->clearExpiredFromSqlite();
        } elseif ($this->cacheType === self::CACHE_FILESYSTEM) {
            $this->clearExpiredFromFilesystem();
        }
    }

    /**
     * Clear expired entries from SQLite with retry logic and optimization
     */
    private function clearExpiredFromSqlite()
    {
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                $currentTime = time();

                // Use a transaction for better performance when deleting many rows
                if ($this->sqliteDb instanceof SQLite3) {
                    $this->sqliteDb->exec('BEGIN IMMEDIATE');
                    $stmt = $this->sqliteDb->prepare('DELETE FROM ip_cache WHERE expires_at <= ?');
                    $stmt->bindValue(1, $currentTime, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $this->sqliteDb->exec('COMMIT');

                    // Log cleanup statistics
                    $changes = $this->sqliteDb->changes();
                    if ($changes > 0) {
                        error_log("SQLite cache cleanup: removed $changes expired entries");
                    }
                } else {
                    $this->sqliteDb->beginTransaction();
                    $stmt = $this->sqliteDb->prepare('DELETE FROM ip_cache WHERE expires_at <= ?');
                    $stmt->execute([$currentTime]);
                    $this->sqliteDb->commit();

                    // Log cleanup statistics
                    $changes = $stmt->rowCount();
                    if ($changes > 0) {
                        error_log("SQLite cache cleanup: removed $changes expired entries");
                    }
                }

                // If we get here, the operation was successful
                return true;
            } catch (Exception $e) {
                // Rollback transaction on error
                try {
                    if ($this->sqliteDb instanceof SQLite3) {
                        $this->sqliteDb->exec('ROLLBACK');
                    } else {
                        $this->sqliteDb->rollback();
                    }
                } catch (Exception $rollbackException) {
                    // Ignore rollback errors
                }

                $attempt++;

                // Check if it's a database busy/locked error
                if ($this->isDatabaseBusyError($e) && $attempt < $this->maxRetries) {
                    // Wait with exponential backoff
                    $delay = $this->retryDelay * pow(2, $attempt - 1);
                    usleep($delay * 1000); // Convert to microseconds
                    continue;
                }

                // Log the error for debugging
                error_log("SQLite cache cleanup error (attempt $attempt): " . $e->getMessage());

                // If it's not a busy error or we've exhausted retries, break
                break;
            }
        }

        return false;
    }

    /**
     * Clear expired entries from filesystem cache
     */
    private function clearExpiredFromFilesystem()
    {
        $deletedCount = 0;
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $cacheData = json_decode($content, true);
                if ($cacheData && $cacheData['expires_at'] <= time()) {
                    if (@unlink($file)) {
                        $deletedCount++;
                    }
                }
            }
        }
        if ($deletedCount > 0) {
            error_log("Cache cleanup: Removed {$deletedCount} expired files from filesystem cache");
        }
    }
}

/**
 * IP Geolocation Data Class
 * 
 * Represents geolocation data for an IP address with convenient access methods
 */
class IPData
{
    /**
     * @var string The IP address that was queried
     */
    public $ip;

    /**
     * @var string Status of the API response ('success', 'fail', etc.)
     */
    public $status;

    /**
     * @var string Country name
     */
    public $country;

    /**
     * @var string Two-letter country code (ISO 3166-1 alpha-2)
     */
    public $countryCode;

    /**
     * @var string Region/state short code
     */
    public $region;

    /**
     * @var string Region/state full name
     */
    public $regionName;

    /**
     * @var string City name
     */
    public $city;

    /**
     * @var string ZIP/postal code
     */
    public $zip;

    /**
     * @var float Latitude coordinate
     */
    public $lat;

    /**
     * @var float Longitude coordinate
     */
    public $lon;

    /**
     * @var string Timezone identifier (e.g., "America/New_York")
     */
    public $timezone;

    /**
     * @var string Internet Service Provider name
     */
    public $isp;

    /**
     * @var string Organization name
     */
    public $org;

    /**
     * @var string Autonomous System information
     */
    public $as;

    /**
     * Constructor to initialize IP data
     * 
     * @param array $data Associative array containing IP geolocation data
     */
    public function __construct(array $data)
    {
        $this->ip = $data['query'] ?? '';
        $this->status = $data['status'] ?? 'unknown';
        $this->country = $data['country'] ?? '';
        $this->countryCode = $data['countryCode'] ?? '';
        $this->region = $data['region'] ?? '';
        $this->regionName = $data['regionName'] ?? '';
        $this->city = $data['city'] ?? '';
        $this->zip = $data['zip'] ?? '';
        $this->lat = (float)($data['lat'] ?? 0);
        $this->lon = (float)($data['lon'] ?? 0);
        $this->timezone = $data['timezone'] ?? '';
        $this->isp = $data['isp'] ?? '';
        $this->org = $data['org'] ?? '';
        $this->as = $data['as'] ?? '';
    }

    /**
     * Convert IP data to PHP array with comprehensive field documentation
     * 
     * @return array {
     *     Complete geolocation data array
     *     
     *     @type string $ip           The IP address that was queried
     *     @type string $status       Status of the API response ('success', 'fail', 'unknown')
     *     @type string $country      Full country name (e.g., "United States")
     *     @type string $countryCode  Two-letter ISO 3166-1 alpha-2 country code (e.g., "US")
     *     @type string $region       Region/state short code (e.g., "CA", "NY")
     *     @type string $regionName   Full region/state name (e.g., "California", "New York")
     *     @type string $city         City name (e.g., "Los Angeles", "New York City")
     *     @type string $zip          ZIP/postal code (e.g., "90210", "10001")
     *     @type float  $lat          Latitude coordinate in decimal degrees (-90 to 90)
     *     @type float  $lon          Longitude coordinate in decimal degrees (-180 to 180)
     *     @type string $timezone     Timezone identifier (e.g., "America/Los_Angeles", "America/New_York")
     *     @type string $isp          Internet Service Provider name (e.g., "Comcast Cable", "Verizon")
     *     @type string $org          Organization name (e.g., "Google LLC", "Amazon.com")
     *     @type string $as           Autonomous System information including ASN and organization
     * }
     */
    public function toPHP()
    {
        return [
            'ip' => $this->ip,
            'status' => $this->status,
            'country' => $this->country,
            'countryCode' => $this->countryCode,
            'region' => $this->region,
            'regionName' => $this->regionName,
            'city' => $this->city,
            'zip' => $this->zip,
            'lat' => $this->lat,
            'lon' => $this->lon,
            'timezone' => $this->timezone,
            'isp' => $this->isp,
            'org' => $this->org,
            'as' => $this->as
        ];
    }

    /**
     * Convert IP data to JSON string
     * 
     * @param bool $prettyPrint Whether to format JSON with indentation for readability
     * @return string JSON representation of the IP geolocation data
     */
    public function toJson($prettyPrint = true)
    {
        $flags = $prettyPrint ? JSON_PRETTY_PRINT : 0;
        return json_encode($this->toPHP(), $flags);
    }

    /**
     * Check if the IP lookup was successful
     * 
     * @return bool True if the lookup was successful, false otherwise
     */
    public function isSuccess()
    {
        return $this->status === 'success';
    }

    /**
     * Get formatted location string
     * 
     * @return string Formatted location (e.g., "Los Angeles, CA, United States")
     */
    public function getFormattedLocation()
    {
        $parts = array_filter([$this->city, $this->regionName, $this->country]);
        return implode(', ', $parts);
    }

    /**
     * Get coordinates as array
     * 
     * @return array Array with 'lat' and 'lon' keys
     */
    public function getCoordinates()
    {
        return [
            'lat' => $this->lat,
            'lon' => $this->lon
        ];
    }
}

class IPGeolocation
{
    private $primaryApiUrl = 'http://ip-api.com/json/';
    private $backupApiUrl = 'http://ipwho.is/';
    private $errorLogger;
    private $cacheManager;

    /**
     * Constructor
     * Automatically clears expired cache entries on initialization
     * 
     * @param CacheManager|string|null $cacheManagerOrLogDir CacheManager instance or custom log directory path
     * @param string|null $cacheDir Custom cache directory path (only used if first param is string)
     */
    public function __construct($cacheManagerOrLogDir = null, $cacheDir = null)
    {
        // Handle different parameter types for backward compatibility
        if ($cacheManagerOrLogDir instanceof CacheManager) {
            // Use provided CacheManager instance
            $this->cacheManager = $cacheManagerOrLogDir;
            $this->errorLogger = new ErrorLogger();
        } else {
            // Traditional constructor with log and cache directories
            $this->errorLogger = new ErrorLogger($cacheManagerOrLogDir);
            $this->cacheManager = new CacheManager($cacheDir);
        }

        // Automatically clear expired cache entries on initialization
        // This is very fast since expires_at is indexed in SQLite
        $this->clearExpiredCache();
    }

    /**
     * Get geolocation data for an IP address
     * 
     * @param string $ip The IP address to lookup
     * @return IPData|null Geolocation data object with convenient access methods or null if failed
     */
    public function getIp(string $ip)
    {
        // Validate IP address
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $error = "Invalid IP address: $ip";
            $this->errorLogger->logError($error, 'ERROR', ['ip' => $ip]);
            return null;
        }

        // Check cache first
        $cachedData = $this->cacheManager->get($ip);
        if ($cachedData !== null) {
            return new IPData($cachedData);
        }

        // Try primary API first
        try {
            $data = $this->fetchFromPrimaryApi($ip === '127.0.0.1' ? '' : $ip);
            // Cache successful result
            $this->cacheManager->set($ip, $data);
            return new IPData($data);
        } catch (Exception $e) {
            $this->errorLogger->logError("Primary API failed for IP: $ip", 'WARNING', [
                'ip' => $ip,
                'error' => $e->getMessage(),
                'api' => 'primary'
            ]);

            // If primary fails, try backup API
            try {
                $data = $this->fetchFromBackupApi($$ip === '127.0.0.1' ? '' : $ip);
                // Cache successful result
                $this->cacheManager->set($ip, $data);
                return new IPData($data);
            } catch (Exception $backupException) {
                $this->errorLogger->logError("Both APIs failed for IP: $ip", 'CRITICAL', [
                    'ip' => $ip,
                    'primary_error' => $e->getMessage(),
                    'backup_error' => $backupException->getMessage()
                ]);
                return null;
            }
        }
    }

    /**
     * Fetch data from primary API (ip-api.com)
     * 
     * @param string $ip IP address to lookup
     * @return array Formatted geolocation data
     * @throws Exception if API request fails
     */
    private function fetchFromPrimaryApi($ip)
    {
        $url = $this->primaryApiUrl . $ip . '?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query';

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'PHP IP Geolocation Class'
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            $error = "Failed to fetch data from primary API for IP: $ip";
            $this->errorLogger->logError($error, 'ERROR', [
                'ip' => $ip,
                'url' => $url,
                'api' => 'primary'
            ]);
            throw new Exception("Failed to fetch data from primary API");
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = "Invalid JSON response from primary API for IP: $ip";
            $this->errorLogger->logError($error, 'ERROR', [
                'ip' => $ip,
                'json_error' => json_last_error_msg(),
                'response' => substr($response, 0, 500), // Log first 500 chars of response
                'api' => 'primary'
            ]);
            throw new Exception("Invalid JSON response from primary API");
        }

        // Check if the API returned an error
        if (isset($data['status']) && $data['status'] === 'fail') {
            $error = "Primary API Error: " . ($data['message'] ?? 'Unknown error');
            $this->errorLogger->logError($error, 'WARNING', [
                'ip' => $ip,
                'api_response' => $data,
                'api' => 'primary'
            ]);
            throw new Exception($error);
        }

        return $this->formatPrimaryApiResponse($data);
    }

    /**
     * Fetch data from backup API (ipwho.is)
     * 
     * @param string $ip IP address to lookup
     * @return array Formatted geolocation data
     * @throws Exception if API request fails
     */
    private function fetchFromBackupApi($ip)
    {
        $url = $this->backupApiUrl . $ip;

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'PHP IP Geolocation Class'
            ]
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            $error = "Failed to fetch data from backup API for IP: $ip";
            $this->errorLogger->logError($error, 'ERROR', [
                'ip' => $ip,
                'url' => $url,
                'api' => 'backup'
            ]);
            throw new Exception("Failed to fetch data from backup API");
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $error = "Invalid JSON response from backup API for IP: $ip";
            $this->errorLogger->logError($error, 'ERROR', [
                'ip' => $ip,
                'json_error' => json_last_error_msg(),
                'response' => substr($response, 0, 500), // Log first 500 chars of response
                'api' => 'backup'
            ]);
            throw new Exception("Invalid JSON response from backup API");
        }

        // Check if the API returned an error
        if (isset($data['success']) && $data['success'] === false) {
            $error = "Backup API Error: " . ($data['message'] ?? 'Unknown error');
            $this->errorLogger->logError($error, 'WARNING', [
                'ip' => $ip,
                'api_response' => $data,
                'api' => 'backup'
            ]);
            throw new Exception($error);
        }

        return $this->formatBackupApiResponse($data);
    }

    /**
     * Format the primary API response (ip-api.com) to match the desired structure
     * 
     * @param array $data Raw API response data from ip-api.com
     * @return array Formatted response
     */
    private function formatPrimaryApiResponse($data)
    {
        return [
            'query' => $data['query'] ?? '',
            'status' => $data['status'] ?? 'unknown',
            'country' => $data['country'] ?? '',
            'countryCode' => $data['countryCode'] ?? '',
            'region' => $data['region'] ?? '',
            'regionName' => $data['regionName'] ?? '',
            'city' => $data['city'] ?? '',
            'zip' => $data['zip'] ?? '',
            'lat' => (float)($data['lat'] ?? 0),
            'lon' => (float)($data['lon'] ?? 0),
            'timezone' => $data['timezone'] ?? '',
            'isp' => $data['isp'] ?? '',
            'org' => $data['org'] ?? '',
            'as' => $data['as'] ?? ''
        ];
    }

    /**
     * Format the backup API response (ipwho.is) to match the desired structure
     * 
     * @param array $data Raw API response data from ipwho.is
     * @return array Formatted response
     */
    private function formatBackupApiResponse($data)
    {
        // Map ipwho.is fields to our standard format
        $status = (isset($data['success']) && $data['success'] === true) ? 'success' : 'fail';

        // Extract ISP and org from connection object
        $isp = $data['connection']['isp'] ?? '';
        $org = $data['connection']['org'] ?? '';
        $asn = isset($data['connection']['asn']) ? 'AS' . $data['connection']['asn'] . ' ' . $org : '';

        return [
            'query' => $data['ip'] ?? '',
            'status' => $status,
            'country' => $data['country'] ?? '',
            'countryCode' => $data['country_code'] ?? '',
            'region' => $data['region_code'] ?? '',
            'regionName' => $data['region'] ?? '',
            'city' => $data['city'] ?? '',
            'zip' => $data['postal'] ?? '',
            'lat' => (float)($data['latitude'] ?? 0),
            'lon' => (float)($data['longitude'] ?? 0),
            'timezone' => $data['timezone']['id'] ?? '',
            'isp' => $isp,
            'org' => $org,
            'as' => $asn
        ];
    }

    /**
     * Get geolocation data as JSON string
     * 
     * @param string $ip The IP address to lookup
     * @return string JSON formatted geolocation data or error message
     */
    public function getLocationDataAsJson($ip)
    {
        $ipData = $this->getIp($ip);

        if ($ipData === null) {
            // Error already logged in getIp method
            return json_encode(['error' => 'Failed to retrieve geolocation data'], JSON_PRETTY_PRINT);
        }

        return $ipData->toJson();
    }

    /**
     * Get cache type being used
     * 
     * @return string|null Current cache type
     */
    public function getCacheType()
    {
        return $this->cacheManager->getCacheType();
    }

    /**
     * Clear expired cache entries
     */
    public function clearExpiredCache()
    {
        $this->cacheManager->clearExpired();
    }

    /**
     * Get system information about caching and logging capabilities
     * 
     * @return array System information
     */
    public function getSystemInfo()
    {
        return [
            'cache_type' => $this->cacheManager->getCacheType(),
            'sqlite3_available' => extension_loaded('sqlite3'),
            'pdo_sqlite_available' => extension_loaded('pdo_sqlite'),
            'is_cli_mode' => php_sapi_name() === 'cli',
            'php_version' => PHP_VERSION
        ];
    }
}
