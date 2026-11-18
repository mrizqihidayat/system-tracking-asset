<?php
/**
 * Agen sinkron pegawai — Asset Tracking System Departemen SHD
 *
 * Mengambil data pegawai dari API pegawai di jaringan internal perusahaan, menyaring hanya kolom di EMPLOYEE_FIELDS,
 * lalu mengirimnya lewat HTTPS ke aplikasi Asset Tracking. Dijalankan terjadwal tiap 23.59 oleh cron (Linux) atau
 * Task Scheduler (Windows). Panduan lengkap ada di README.md.
 *
 *   php sync.php              ambil data pegawai dan kirim ke aplikasi
 *   php sync.php --dry-run    uji: ambil data dan cek koneksi + token ke aplikasi, TANPA mengirim data pegawai
 *   php sync.php --env=PATH   pakai file konfigurasi lain (bawaan: .env di folder ini)
 *   php sync.php --help
 *
 * Exit code: 0 = berhasil, 1 = gagal, 2 = konfigurasi salah.
 *
 * Berdiri sendiri: tidak butuh composer, framework, database, atau file lain selain .env di folder ini.
 * Tidak pernah mencetak API key, token, atau data pegawai ke layar maupun log.
 */

const AGENT_VERSION = '1.0';

/**
 * Kolom yang diambil dari API pegawai dan dikirim ke aplikasi, dengan nama persis seperti di API pegawai.
 * Kolom lain (tglLahir, jenkel, agama, phone, tglMPP, tglPensiun, dan seterusnya) sengaja TIDAK dikirim karena tidak
 * dipakai aplikasi. Bila suatu saat dibutuhkan: tambahkan namanya di sini DAN kolomnya di tabel employees aplikasi.
 */
const EMPLOYEE_FIELDS = ['nik', 'nama', 'grup', 'divisi', 'departemen', 'seksi', 'jabatan', 'email'];

/** Alamat penerima di aplikasi, ditambahkan di belakang BACKEND_URL. */
const PUSH_PATH = '/api/internal/employees/push';

/** Jeda (detik) sebelum mencoba ulang kiriman yang gagal karena jaringan atau server sibuk: total 3 percobaan. */
const RETRY_DELAYS = [10, 30];

/** Batas body request di Vercel 4,5 MB; kiriman di atas ini akan ditolak (HTTP 413). */
const PAYLOAD_WARN_BYTES = 4000000;

const EXIT_OK = 0;
const EXIT_FAILED = 1;
const EXIT_CONFIG = 2;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (PHP_VERSION_ID < 70400) {
    fwrite(STDERR, 'Agen sinkron butuh PHP 7.4 atau lebih baru (terpasang: '.PHP_VERSION.').'.PHP_EOL);
    exit(EXIT_CONFIG);
}

/**
 * Kegagalan yang sudah dijelaskan. getMessage() = rincian untuk log lokal; $report = pesan singkat yang aman dikirim
 * ke aplikasi (tanpa nama host, isi respons, atau rahasia).
 */
class AgentFailure extends RuntimeException
{
    /** @var string */
    public $report;

    public function __construct(string $detail, string $report = '')
    {
        parent::__construct($detail);
        $this->report = $report !== '' ? $report : $detail;
    }
}

/** Konfigurasi atau opsi salah; pesan bisa lebih dari satu baris. */
class ConfigError extends RuntimeException
{
}

/** Log ke layar (STDOUT/STDERR) dan, bila LOG_FILE diisi, ke file. */
final class AgentLog
{
    /** @var string|null */
    private static $file = null;

    public static function toFile(?string $path): void
    {
        self::$file = $path;
    }

    public static function info(string $message): void
    {
        self::write('INFO', $message, STDOUT);
    }

    public static function warn(string $message): void
    {
        self::write('PERINGATAN', $message, STDOUT);
    }

    public static function error(string $message): void
    {
        self::write('GAGAL', $message, STDERR);
    }

    /**
     * @param resource $stream
     */
    private static function write(string $level, string $message, $stream): void
    {
        $line = '['.date('Y-m-d H:i:s').'] '.$level.' '.$message;
        fwrite($stream, $line.PHP_EOL);

        if (self::$file !== null && @file_put_contents(self::$file, $line.PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            fwrite(STDERR, '['.date('Y-m-d H:i:s').'] PERINGATAN Tidak bisa menulis log ke '.self::$file.'; log hanya tampil di layar.'.PHP_EOL);
            self::$file = null;
        }
    }
}

exit(main($argv));

function main(array $argv): int
{
    date_default_timezone_set('Asia/Jakarta');

    try {
        $options = parse_args(array_slice($argv, 1));

        if ($options['help']) {
            echo usage();

            return EXIT_OK;
        }

        if ($options['version']) {
            echo 'employee-sync-agent '.AGENT_VERSION.PHP_EOL;

            return EXIT_OK;
        }

        $missing = array_filter(['curl' => 'curl_init', 'json' => 'json_encode'], function ($fn) {
            return ! function_exists($fn);
        });

        if ($missing !== []) {
            throw new ConfigError('Ekstensi PHP belum aktif: '.implode(', ', array_keys($missing)).'. Aktifkan di php.ini (mis. extension=curl), lalu cek dengan `php -m`.');
        }

        $config = load_config($options['env']);
    } catch (ConfigError $e) {
        foreach (explode("\n", $e->getMessage()) as $line) {
            AgentLog::error($line);
        }

        return EXIT_CONFIG;
    }

    AgentLog::toFile($config['log_file'] !== '' ? $config['log_file'] : null);

    return $options['dry_run'] ? dry_run($config) : sync($config);
}

/**
 * Sinkron sungguhan: ambil, saring, kirim. Bila API pegawai gagal, kegagalannya dilaporkan ke aplikasi supaya
 * terlihat di Data Master > Departemen (data lama di aplikasi tetap dipakai).
 */
function sync(array $config): int
{
    AgentLog::info('Mulai sinkronisasi pegawai (agen v'.AGENT_VERSION.').');

    try {
        $result = fetch_employees($config);
    } catch (AgentFailure $e) {
        AgentLog::error($e->getMessage());
        report_failure($config, $e->report);

        return EXIT_FAILED;
    }

    try {
        $json = encode_json(['employees' => $result['rows']]);
        AgentLog::info(sprintf('%s pegawai terambil dari API pegawai (%s).', number(count($result['rows'])), size($json)));
        warn_about_data($result, $json);

        AgentLog::info('Mengirim ke aplikasi...');
        $response = send_to_backend($config, $json);
    } catch (AgentFailure $e) {
        AgentLog::error($e->getMessage());

        return EXIT_FAILED;
    }

    if ($response['status'] !== 200) {
        AgentLog::error(backend_problem($response['status'], $response['data']));

        return EXIT_FAILED;
    }

    $data = $response['data'];
    $status = isset($data['status']) && is_string($data['status']) ? $data['status'] : '';

    if ($status === '') {
        AgentLog::error(backend_problem($response['status'], $data));

        return EXIT_FAILED;
    }

    if ($status === 'berhasil') {
        AgentLog::info(sprintf(
            'Sinkronisasi berhasil: %s pegawai (%s baru, %s diperbarui, %s dinonaktifkan, %s dilewati).',
            number((int) ($data['total'] ?? 0)),
            number((int) ($data['created'] ?? 0)),
            number((int) ($data['updated'] ?? 0)),
            number((int) ($data['deactivated'] ?? 0)),
            number((int) ($data['skipped'] ?? 0))
        ));

        return EXIT_OK;
    }

    AgentLog::error('Sinkronisasi '.($status !== '' ? $status : 'tidak diketahui').': '.backend_message($data, 'tanpa pesan.'));

    return EXIT_FAILED;
}

/**
 * Uji tanpa mengubah data di aplikasi: ambil data dari API pegawai (tidak dikirim), lalu kirim body kosong `{}` dengan
 * token. Aplikasi mengecek token sebelum isi, jadi token benar dijawab 422 (isi kosong ditolak) dan tidak ada yang berubah.
 */
function dry_run(array $config): int
{
    $ok = true;
    AgentLog::info('Uji coba (--dry-run, agen v'.AGENT_VERSION.'): data pegawai TIDAK dikirim ke aplikasi.');

    AgentLog::info('1/2 Mengambil data dari API pegawai ('.host($config['api_url']).')...');

    try {
        $result = fetch_employees($config);
        $json = encode_json(['employees' => $result['rows']]);
        AgentLog::info(sprintf('OK: %s pegawai, ukuran kiriman %s.', number(count($result['rows'])), size($json)));
        AgentLog::info('Kolom yang akan dikirim: '.implode(', ', EMPLOYEE_FIELDS).'.');
        warn_about_data($result, $json);

        if ($result['rows'] === []) {
            $ok = false;
        }
    } catch (AgentFailure $e) {
        AgentLog::error($e->getMessage());
        $ok = false;
    }

    AgentLog::info('2/2 Mengecek koneksi dan token ke aplikasi ('.host($config['backend_url']).')...');

    try {
        $response = send_to_backend($config, '{}');

        if ($response['status'] === 422 && isset($response['data']['errors']['employees'])) {
            AgentLog::info('OK: aplikasi terjangkau dan token diterima.');
        } else {
            AgentLog::error(backend_problem($response['status'], $response['data']));
            $ok = false;
        }
    } catch (AgentFailure $e) {
        AgentLog::error($e->getMessage());
        $ok = false;
    }

    if ($ok) {
        AgentLog::info('Uji coba selesai: semua siap. Jalankan `php sync.php` (tanpa --dry-run) untuk sinkron sungguhan.');

        return EXIT_OK;
    }

    AgentLog::error('Uji coba selesai dengan masalah; perbaiki baris GAGAL di atas lalu ulangi.');

    return EXIT_FAILED;
}

/**
 * Ambil semua pegawai dari API pegawai internal dan saring hanya kolom EMPLOYEE_FIELDS (nilai bertingkat dibuang).
 *
 * @return array{rows: list<array<string, mixed>>, missing_fields: list<string>, incomplete: int}
 *
 * @throws AgentFailure
 */
function fetch_employees(array $config): array
{
    $headers = ['Accept: application/json'];

    if ($config['api_key'] !== '') {
        $headers[] = $config['api_key_header'].': '.$config['api_key'];
    }

    // API pegawai ada di jaringan internal: proxy internet sengaja tidak dipakai ('' = tanpa proxy).
    $response = http_request('GET', $config['api_url'], $headers, null, $config['api_timeout'], '', $config['ca_bundle']);

    if ($response['error'] !== '') {
        throw new AgentFailure('Tidak bisa menghubungi API pegawai: '.$response['error'].network_hint($response['errno'], 'api'), 'API pegawai tidak bisa dihubungi.');
    }

    $status = $response['status'];

    if ($status >= 300 && $status < 400) {
        throw new AgentFailure("API pegawai mengalihkan ke alamat lain (HTTP {$status}). Periksa EMPLOYEE_API_URL.", "API pegawai menjawab HTTP {$status}.");
    }

    if ($status < 200 || $status >= 300) {
        $hint = in_array($status, [401, 403], true) ? ' Periksa EMPLOYEE_API_KEY dan EMPLOYEE_API_KEY_HEADER.' : '';

        throw new AgentFailure("API pegawai menjawab HTTP {$status}.{$hint}", "API pegawai menjawab HTTP {$status}.");
    }

    $body = json_decode($response['body'], true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
    $dataKey = $config['api_data_key'];
    $rows = $dataKey !== '' && is_array($body) ? ($body[$dataKey] ?? null) : $body;

    if (! is_array($rows) || ! is_list_array($rows)) {
        $hint = $dataKey !== ''
            ? " Respons tidak punya daftar di kunci \"{$dataKey}\"; periksa EMPLOYEE_API_DATA_KEY."
            : ' Bila respons dibungkus objek (mis. {"data": [...]}), isi EMPLOYEE_API_DATA_KEY.';

        throw new AgentFailure('Bentuk respons API pegawai tidak dikenal (bukan daftar pegawai).'.$hint, 'Bentuk respons API pegawai tidak dikenal (bukan daftar pegawai).');
    }

    $picked = [];
    $seen = [];
    $incomplete = 0;

    foreach ($rows as $row) {
        if (! is_array($row)) {
            $incomplete++;

            continue;
        }

        $item = [];

        foreach (EMPLOYEE_FIELDS as $field) {
            if (array_key_exists($field, $row)) {
                $item[$field] = is_scalar($row[$field]) ? $row[$field] : null;
                $seen[$field] = true;
            }
        }

        if (is_blank($item['nik'] ?? null) || is_blank($item['nama'] ?? null)) {
            $incomplete++;
        }

        $picked[] = $item;
    }

    return [
        'rows' => $picked,
        'missing_fields' => array_values(array_diff(EMPLOYEE_FIELDS, array_keys($seen))),
        'incomplete' => $incomplete,
    ];
}

/**
 * Kirim body JSON ke penerima di aplikasi, dengan coba ulang bila jaringan putus atau server sibuk (cold start).
 *
 * @return array{status: int, data: array<string, mixed>}
 *
 * @throws AgentFailure jika aplikasi tidak bisa dihubungi sama sekali
 */
function send_to_backend(array $config, string $json): array
{
    $url = $config['backend_url'].PUSH_PATH;
    $headers = ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer '.$config['push_token']];
    $proxy = $config['proxy'] !== '' ? $config['proxy'] : null;
    $attempt = 0;

    while (true) {
        $response = http_request('POST', $url, $headers, $json, $config['backend_timeout'], $proxy, $config['ca_bundle']);
        $retryable = $response['error'] !== '' || in_array($response['status'], [429, 500, 502, 503, 504], true);

        if (! $retryable || $attempt >= count(RETRY_DELAYS)) {
            break;
        }

        $wait = RETRY_DELAYS[$attempt];
        $attempt++;
        $problem = $response['error'] !== '' ? $response['error'] : 'HTTP '.$response['status'];
        AgentLog::warn("Percobaan {$attempt} ke aplikasi gagal ({$problem}). Mencoba lagi dalam {$wait} detik.");
        sleep($wait);
    }

    if ($response['error'] !== '') {
        throw new AgentFailure('Tidak bisa menghubungi aplikasi: '.$response['error'].network_hint($response['errno'], 'backend'));
    }

    $data = json_decode($response['body'], true);

    return ['status' => $response['status'], 'data' => is_array($data) ? $data : []];
}

/** Laporkan ke aplikasi bahwa API pegawai gagal diambil, supaya tercatat sebagai sinkron gagal. */
function report_failure(array $config, string $report): void
{
    try {
        $response = send_to_backend($config, encode_json(['error' => $report]));
    } catch (AgentFailure $e) {
        AgentLog::warn('Kegagalan tidak bisa dilaporkan ke aplikasi: '.$e->getMessage());

        return;
    }

    if ($response['status'] === 200) {
        AgentLog::info('Kegagalan sudah dicatat di aplikasi (Data Master > Departemen); data pegawai lama tetap dipakai.');
    } else {
        AgentLog::warn('Kegagalan tidak bisa dilaporkan ke aplikasi: '.backend_problem($response['status'], $response['data']));
    }
}

/**
 * @param list<string> $headers
 *
 * @return array{status: int, body: string, error: string, errno: int}
 */
function http_request(string $method, string $url, array $headers, ?string $body, int $timeout, ?string $proxy, string $caBundle): array
{
    $curl = curl_init($url);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(30, $timeout),
        // Tidak mengikuti redirect: header API key/token tidak boleh terkirim ke alamat lain.
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'employee-sync-agent/'.AGENT_VERSION,
        CURLOPT_ENCODING => '',
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }

    if ($proxy !== null) {
        $options[CURLOPT_PROXY] = $proxy;
    }

    if ($caBundle !== '') {
        $options[CURLOPT_CAINFO] = $caBundle;
    }

    curl_setopt_array($curl, $options);
    $result = curl_exec($curl);
    $response = [
        'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
        'body' => is_string($result) ? $result : '',
        'error' => $result === false ? curl_error($curl) : '',
        'errno' => $result === false ? curl_errno($curl) : 0,
    ];

    if (PHP_VERSION_ID < 80000) {
        curl_close($curl);
    }

    return $response;
}

/** Saran perbaikan untuk galat jaringan curl yang umum. */
function network_hint(int $errno, string $target): string
{
    if (in_array($errno, [35, 51, 53, 58, 60, 77, 83], true)) {
        return ' (Sertifikat HTTPS tidak bisa diverifikasi: isi CA_BUNDLE, lihat README bagian "Masalah umum".)';
    }

    if ($errno === 5) {
        return ' (Alamat proxy tidak dikenal: periksa PROXY_URL.)';
    }

    if (in_array($errno, [6, 7, 28], true)) {
        return $target === 'api'
            ? ' (Pastikan server ini berada di jaringan internal perusahaan dan EMPLOYEE_API_URL benar.)'
            : ' (Bila server butuh proxy untuk ke internet, isi PROXY_URL; pastikan firewall mengizinkan koneksi keluar HTTPS ke BACKEND_URL.)';
    }

    return '';
}

/** Penjelasan jawaban aplikasi yang bukan sukses. */
function backend_problem(int $status, array $data): string
{
    if ($status >= 200 && $status < 300) {
        return "Jawaban aplikasi tidak dikenal (HTTP {$status}, bukan jawaban penerima data pegawai): periksa BACKEND_URL, harus alamat backend, bukan alamat tampilan web.";
    }

    if ($status >= 300 && $status < 400) {
        return "Aplikasi mengalihkan ke alamat lain (HTTP {$status}): periksa BACKEND_URL (pakai https:// dan alamat yang benar).";
    }

    switch ($status) {
        case 401:
            return 'Aplikasi menolak token (HTTP 401): EMPLOYEE_PUSH_TOKEN tidak sama dengan yang diisi di aplikasi.';
        case 404:
            return 'Alamat penerima tidak ditemukan (HTTP 404): periksa BACKEND_URL, atau EMPLOYEE_PUSH_TOKEN belum diisi di aplikasi.';
        case 413:
            return 'Data terlalu besar untuk aplikasi (HTTP 413).';
        case 422:
            return 'Aplikasi menolak isi kiriman (HTTP 422): '.backend_message($data, 'tanpa pesan.');
        case 429:
            return 'Terlalu banyak kiriman (HTTP 429): '.backend_message($data, 'coba lagi beberapa menit lagi.');
        case 503:
            return 'Aplikasi sedang sibuk (HTTP 503): '.backend_message($data, 'coba lagi beberapa menit lagi.');
        default:
            return "Aplikasi menjawab HTTP {$status}: ".backend_message($data, 'tanpa pesan.');
    }
}

function backend_message(array $data, string $fallback): string
{
    if (! isset($data['message']) || ! is_string($data['message']) || trim($data['message']) === '') {
        return $fallback;
    }

    return substr((string) preg_replace('/\s+/', ' ', trim($data['message'])), 0, 300);
}

/**
 * @param array{rows: list<array<string, mixed>>, missing_fields: list<string>, incomplete: int} $result
 */
function warn_about_data(array $result, string $json): void
{
    if ($result['rows'] === []) {
        AgentLog::warn('API pegawai mengembalikan daftar kosong; aplikasi akan membatalkan sinkronisasi dan memakai data lama.');

        return;
    }

    if ($result['missing_fields'] !== []) {
        AgentLog::warn('Kolom tidak ada di respons API pegawai: '.implode(', ', $result['missing_fields']).'. Nama kolom di API mungkin berbeda; lihat EMPLOYEE_FIELDS di sync.php.');
    }

    if ($result['incomplete'] > 0) {
        AgentLog::warn(number($result['incomplete']).' baris tanpa nik atau nama akan dilewati aplikasi.');
    }

    if (strlen($json) > PAYLOAD_WARN_BYTES) {
        AgentLog::warn('Ukuran kiriman '.size($json).' mendekati atau melewati batas aplikasi (4,5 MB di Vercel).');
    }
}

/**
 * @throws AgentFailure
 */
function encode_json(array $payload): string
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json === false) {
        throw new AgentFailure('Gagal menyusun data JSON: '.json_last_error_msg());
    }

    return $json;
}

/**
 * @return array{dry_run: bool, help: bool, version: bool, env: string}
 *
 * @throws ConfigError
 */
function parse_args(array $args): array
{
    $options = ['dry_run' => false, 'help' => false, 'version' => false, 'env' => __DIR__.DIRECTORY_SEPARATOR.'.env'];

    foreach ($args as $arg) {
        if ($arg === '--dry-run') {
            $options['dry_run'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
        } elseif ($arg === '--version') {
            $options['version'] = true;
        } elseif (strpos($arg, '--env=') === 0 && strlen($arg) > 6) {
            $options['env'] = substr($arg, 6);
        } else {
            throw new ConfigError("Opsi tidak dikenal: {$arg}. Lihat `php sync.php --help`.");
        }
    }

    return $options;
}

/**
 * @return array{api_url: string, api_key: string, api_key_header: string, api_data_key: string, api_timeout: int, backend_url: string, push_token: string, backend_timeout: int, proxy: string, ca_bundle: string, log_file: string}
 *
 * @throws ConfigError
 */
function load_config(string $envPath): array
{
    $env = read_env_file($envPath);
    $get = function (string $key, string $default = '') use ($env): string {
        return isset($env[$key]) && $env[$key] !== '' ? $env[$key] : $default;
    };
    $dir = dirname($envPath);
    $errors = [];

    $config = [
        'api_url' => $get('EMPLOYEE_API_URL'),
        'api_key' => $get('EMPLOYEE_API_KEY'),
        'api_key_header' => $get('EMPLOYEE_API_KEY_HEADER', 'X-API-Key'),
        'api_data_key' => $get('EMPLOYEE_API_DATA_KEY'),
        'api_timeout' => positive_int($get('EMPLOYEE_API_TIMEOUT', '60'), 'EMPLOYEE_API_TIMEOUT', $errors),
        'backend_url' => rtrim($get('BACKEND_URL'), '/'),
        'push_token' => $get('EMPLOYEE_PUSH_TOKEN'),
        'backend_timeout' => positive_int($get('BACKEND_TIMEOUT', '120'), 'BACKEND_TIMEOUT', $errors),
        'proxy' => $get('PROXY_URL'),
        'ca_bundle' => resolve_path($get('CA_BUNDLE'), $dir),
        'log_file' => resolve_path($get('LOG_FILE'), $dir),
    ];

    if (! is_http_url($config['api_url'])) {
        $errors[] = 'EMPLOYEE_API_URL wajib diisi dengan URL lengkap API pegawai (http:// atau https://).';
    }

    if (! preg_match('/^[A-Za-z0-9-]+$/', $config['api_key_header'])) {
        $errors[] = 'EMPLOYEE_API_KEY_HEADER hanya boleh berisi huruf, angka, dan tanda hubung.';
    }

    if (! is_http_url($config['backend_url'])) {
        $errors[] = 'BACKEND_URL wajib diisi dengan alamat aplikasi (https://...).';
    } elseif (! is_secure_backend($config['backend_url'])) {
        $errors[] = 'BACKEND_URL wajib memakai https:// (http:// hanya untuk localhost saat uji).';
    }

    if (strlen($config['push_token']) < 32) {
        $errors[] = 'EMPLOYEE_PUSH_TOKEN wajib diisi (minimal 32 karakter) dan sama persis dengan yang diisi di aplikasi.';
    }

    if ($config['ca_bundle'] !== '' && ! is_file($config['ca_bundle'])) {
        $errors[] = 'CA_BUNDLE menunjuk file yang tidak ada: '.$config['ca_bundle'];
    }

    if ($errors !== []) {
        throw new ConfigError(implode("\n", $errors));
    }

    return $config;
}

/**
 * Baca file .env sederhana: NAMA=nilai per baris, # untuk komentar, nilai boleh diapit tanda kutip.
 *
 * @return array<string, string>
 *
 * @throws ConfigError
 */
function read_env_file(string $path): array
{
    if (! is_file($path) || ! is_readable($path)) {
        throw new ConfigError("File konfigurasi tidak ditemukan atau tidak bisa dibaca: {$path}\nSalin .env.example menjadi .env di folder agen, lalu isi.");
    }

    $values = [];

    foreach ((array) file($path, FILE_IGNORE_NEW_LINES) as $index => $line) {
        if ($index === 0) {
            $line = (string) preg_replace('/^\xEF\xBB\xBF/', '', $line);
        }

        $line = trim($line);

        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $eq = strpos($line, '=');

        if ($eq === false) {
            throw new ConfigError('Baris '.($index + 1).' di file konfigurasi tidak berformat NAMA=nilai.');
        }

        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        $quote = $value !== '' ? $value[0] : '';

        if (($quote === '"' || $quote === "'") && strlen($value) >= 2 && substr($value, -1) === $quote) {
            $value = substr($value, 1, -1);
        } elseif (($comment = strpos($value, ' #')) !== false) {
            $value = rtrim(substr($value, 0, $comment));
        }

        $values[$key] = $value;
    }

    return $values;
}

function positive_int(string $value, string $name, array &$errors): int
{
    if (! preg_match('/^\d+$/', $value) || (int) $value < 1) {
        $errors[] = "{$name} harus berupa angka detik (lebih dari 0).";

        return 1;
    }

    return (int) $value;
}

function resolve_path(string $path, string $baseDir): string
{
    if ($path === '' || preg_match('#^([A-Za-z]:)?[\\\\/]#', $path)) {
        return $path;
    }

    return $baseDir.DIRECTORY_SEPARATOR.$path;
}

function is_http_url(string $url): bool
{
    return (bool) preg_match('#^https?://[^\s/]+#i', $url);
}

function is_secure_backend(string $url): bool
{
    if (stripos($url, 'https://') === 0) {
        return true;
    }

    return in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['localhost', '127.0.0.1', '[::1]'], true);
}

function is_list_array(array $value): bool
{
    return $value === [] || array_keys($value) === range(0, count($value) - 1);
}

/**
 * Kosong, hanya spasi, atau "-" (dianggap kosong oleh aplikasi).
 *
 * @param mixed $value
 */
function is_blank($value): bool
{
    return $value === null || (is_string($value) && (trim($value) === '' || trim($value) === '-'));
}

function host(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);

    return is_string($host) ? $host : '?';
}

function number(int $value): string
{
    return number_format($value, 0, ',', '.');
}

function size(string $data): string
{
    $bytes = strlen($data);

    return $bytes >= 1048576
        ? number_format($bytes / 1048576, 1, ',', '.').' MB'
        : number_format(max(1, (int) round($bytes / 1024)), 0, ',', '.').' KB';
}

function usage(): string
{
    return <<<'TXT'
Agen sinkron pegawai - Asset Tracking System Departemen SHD

Pemakaian:
  php sync.php              ambil data pegawai dari API pegawai dan kirim ke aplikasi
  php sync.php --dry-run    uji: ambil data dan cek koneksi + token, TANPA mengirim data pegawai
  php sync.php --env=PATH   pakai file konfigurasi lain (bawaan: .env di folder ini)
  php sync.php --version    tampilkan versi agen
  php sync.php --help       tampilkan bantuan ini

Exit code: 0 = berhasil, 1 = gagal, 2 = konfigurasi salah.
Panduan lengkap: README.md di folder ini.

TXT;
}
