<?php
/**
 * Server/bps_api.php
 * ─────────────────────────────────────────────────────────────
 * Integrasi resmi Web API BPS (Badan Pusat Statistik Indonesia)
 *
 * API KEY milik project ini:
 *   4e182f178f0d964814488d42593f2594
 *
 * ENDPOINT YANG DIGUNAKAN:
 *  1. Provinsi  → /v1/api/domain/type/all/prov/00000/key/{KEY}
 *  2. Komoditas → /v1/api/list/model/data/lang/ind/domain/{DOM}/var/2310/th/126/key/{KEY}
 *     - var=2310 = variabel Harga Eceran Rata-Rata Komoditas
 *     - th=126   = kode tahun BPS untuk 2024 (BPS mulai hitung dari 1899)
 *     - domain   = kode wilayah (0000=pusat, 1100=Aceh, 3100=DKI, dst)
 *
 * CARA PAKAI:
 *   $bps = new BPS_API();
 *   $prov = $bps->getProvinces();            // daftar 38 provinsi
 *   $kom  = $bps->getKomoditasHarga('3100'); // harga komoditas DKI Jakarta
 *   $sync = $bps->syncToDatabase($conn);     // sinkronisasi ke DB
 * ─────────────────────────────────────────────────────────────
 */

// ── KONFIGURASI ──────────────────────────────────────────────
define('BPS_API_KEY',  '4e182f178f0d964814488d42593f2594');
define('BPS_BASE_URL', 'https://webapi.bps.go.id/v1/api/');

// Kode variabel BPS
define('BPS_VAR_HARGA',  '2310');  // Harga Eceran Rata-Rata Komoditas
define('BPS_TAHUN_KODE', '126');   // th=126 → tahun 2024

// Timeout untuk request API (detik)
define('BPS_TIMEOUT', 12);

/**
 * BPS_API — class untuk semua interaksi dengan Web API BPS
 */
class BPS_API {

    private string $key;
    private int    $timeout;

    public function __construct(string $key = BPS_API_KEY, int $timeout = BPS_TIMEOUT) {
        $this->key     = $key;
        $this->timeout = $timeout;
    }

    /**
     * ── PRIVATE: HTTP GET ke BPS API ─────────────────────────
     * Menangani SSL, timeout, dan parsing JSON
     */
    private function get(string $path): ?array {
        $url = BPS_BASE_URL . ltrim($path, '/');

        $ctx = stream_context_create([
            'http' => [
                'method'     => 'GET',
                'timeout'    => $this->timeout,
                'user_agent' => 'InfoHarga-Komoditi/4.0 (PHP)',
                'header'     => "Accept: application/json\r\n",
            ],
            'ssl'  => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if (!$raw) return null;

        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) return null;
        if (($data['status'] ?? '') !== 'OK') return null;

        return $data;
    }

    // ────────────────────────────────────────────────────────
    //  1. ENDPOINT PROVINSI
    //     GET /v1/api/domain/type/all/prov/00000/key/{KEY}
    // ────────────────────────────────────────────────────────

    /**
     * getProvinces() — Ambil daftar 38 provinsi dari BPS
     *
     * Response format:
     *   data[0] = info pagination {page, pages, per_page, count, total}
     *   data[1] = array of domains:
     *     [{domain_id:"1100", domain_name:"Aceh", domain_url:"https://aceh.bps.go.id"}, ...]
     *
     * @return array  Array of ['domain_id'=>..., 'domain_name'=>..., 'domain_url'=>...]
     */
    public function getProvinces(): array {
        $res = $this->get("domain/type/all/prov/00000/key/{$this->key}/");
        if (!$res) return [];

        // BPS returns data[1] as the actual list
        $list = $res['data'][1] ?? [];
        if (!is_array($list)) return [];

        return $list;
    }

    /**
     * getProvinceMap() — Kembalikan map domain_name → domain_id
     * Berguna untuk lookup cepat berdasarkan nama provinsi
     *
     * @return array ['Aceh'=>'1100', 'DKI Jakarta'=>'3100', ...]
     */
    public function getProvinceMap(): array {
        $provinces = $this->getProvinces();
        $map = [];
        foreach ($provinces as $p) {
            $name = $p['domain_name'] ?? '';
            $id   = $p['domain_id']   ?? '';
            if ($name && $id) $map[$name] = $id;
        }
        return $map;
    }

    // ────────────────────────────────────────────────────────
    //  2. ENDPOINT DATA HARGA KOMODITAS
    //     GET /v1/api/list/model/data/lang/ind/domain/{DOM}/var/2310/th/126/key/{KEY}
    // ────────────────────────────────────────────────────────

    /**
     * getKomoditasHarga() — Ambil data harga komoditas dari BPS
     *
     * BPS Response structure untuk model=data:
     * {
     *   "status": "OK",
     *   "datacontent": {
     *     "2310126": {          <- key = var+th
     *       "1": 15000,         <- vervar_id: harga (rupiah)
     *       "2": 13500,
     *       ...
     *     }
     *   },
     *   "vervar": [             <- daftar komoditas
     *     {"val":"1", "label":"Beras Kualitas Bawah II"},
     *     {"val":"2", "label":"Beras Kualitas Medium I"},
     *     ...
     *   ],
     *   "var":    [{"val":"2310","label":"Harga Eceran ..."}],
     *   "tahun":  [{"val":"126","label":"2024"}]
     * }
     *
     * @param string $domainId  Kode domain BPS (0000=pusat, 1100=Aceh, 3100=DKI, dst)
     * @return array            Array komoditas: [['nama'=>..., 'harga'=>..., 'satuan'=>...], ...]
     */
    public function getKomoditasHarga(string $domainId = '0000'): array {
        $path = "list/model/data/lang/ind/domain/{$domainId}/var/" . BPS_VAR_HARGA
              . "/th/" . BPS_TAHUN_KODE . "/key/{$this->key}";

        $res = $this->get($path);
        if (!$res) return [];

        // Ambil daftar nama komoditas dari vervar
        $vervar = $res['vervar'] ?? [];
        $namaMap = [];
        foreach ($vervar as $item) {
            $namaMap[$item['val']] = $item['label'] ?? '';
        }

        // Kunci data = var+th = "2310" + "126" = "2310126"
        $dataKey     = BPS_VAR_HARGA . BPS_TAHUN_KODE;
        $datacontent = $res['datacontent'][$dataKey] ?? $res['datacontent'] ?? [];

        if (empty($datacontent) || empty($namaMap)) return [];

        // Bangun array hasil
        $result = [];
        foreach ($datacontent as $varId => $hargaRaw) {
            $nama = $namaMap[$varId] ?? null;
            if (!$nama) continue;

            $harga = (int)$hargaRaw;
            if ($harga <= 0) continue;

            // Tentukan satuan berdasarkan nama komoditas
            $satuan = self::guessSatuan($nama);

            // Kategorikan
            $kategori = self::guessKategori($nama);

            $result[] = [
                'bps_id'   => $varId,
                'nama'     => $nama,
                'harga'    => $harga,
                'satuan'   => $satuan,
                'kategori' => $kategori,
            ];
        }

        return $result;
    }

    // ────────────────────────────────────────────────────────
    //  3. SINKRONISASI KE DATABASE
    // ────────────────────────────────────────────────────────

    /**
     * syncProvincesToDB() — Simpan/update data provinsi ke DB
     * Mengupdate kolom `provinsi` di tabel komoditas yang kosong
     * berdasarkan mapping nama dari BPS
     *
     * @return array ['inserted'=>int, 'errors'=>string[]]
     */
    public function syncProvincesToDB(mysqli $conn): array {
        $provinces = $this->getProvinces();
        $result    = ['count' => count($provinces), 'domains' => []];
        foreach ($provinces as $p) {
            $result['domains'][] = [
                'id'   => $p['domain_id']   ?? '',
                'name' => $p['domain_name'] ?? '',
            ];
        }
        return $result;
    }

    /**
     * syncKomoditasToDB() — Sinkronisasi harga komoditas BPS ke DB
     *
     * Untuk setiap provinsi yang ada di DB:
     *  1. Ambil domain_id BPS yang sesuai
     *  2. Fetch data harga dari BPS
     *  3. Upsert ke tabel komoditas dengan status='approved'
     *
     * @param  mysqli  $conn
     * @param  string  $domainId   Kode domain BPS (default: 0000 = nasional)
     * @param  string  $provinsiName  Nama provinsi Indonesia untuk disimpan
     * @param  string  $lokasiName    Nama kota/lokasi untuk disimpan
     * @return array   ['inserted'=>int, 'updated'=>int, 'errors'=>string[]]
     */
    public function syncKomoditasToDB(
        mysqli $conn,
        string $domainId    = '0000',
        string $provinsiName = 'Nasional',
        string $lokasiName   = 'Indonesia'
    ): array {
        $items    = $this->getKomoditasHarga($domainId);
        $inserted = 0;
        $updated  = 0;
        $errors   = [];

        foreach ($items as $item) {
            try {
                $nama     = $conn->real_escape_string($item['nama']);
                $kategori = $conn->real_escape_string($item['kategori']);
                $lokasi   = $conn->real_escape_string($lokasiName);
                $provinsi = $conn->real_escape_string($provinsiName);
                $satuan   = $conn->real_escape_string($item['satuan']);
                $sekarang = (int)$item['harga'];
                $kemarin  = $sekarang; // BPS tidak sediakan harga kemarin, set sama

                // Cek apakah sudah ada (sama nama + lokasi + source BPS)
                $check = $conn->query(
                    "SELECT id, harga_sekarang, history FROM komoditas
                     WHERE nama='$nama' AND lokasi='$lokasi' AND status='approved'
                     LIMIT 1"
                );

                if ($check && $check->num_rows > 0) {
                    // Update — jadikan harga lama sebagai kemarin
                    $old = $check->fetch_assoc();
                    $kemarin_actual = (int)$old['harga_sekarang'];

                    // Update history JSON
                    $hist = json_decode($old['history'] ?? '[]', true);
                    if (!is_array($hist)) $hist = [];
                    $hist[] = $sekarang;
                    if (count($hist) > 7) array_shift($hist);
                    $histJson = $conn->real_escape_string(json_encode($hist));

                    $conn->query(
                        "UPDATE komoditas SET
                            harga_kemarin=$kemarin_actual,
                            harga_sekarang=$sekarang,
                            history='$histJson',
                            updated_at=NOW()
                         WHERE id={$old['id']}"
                    );
                    $updated++;
                } else {
                    // Insert baru — history 7 titik dengan nilai yang sama
                    $histArr  = array_fill(0, 6, $sekarang);
                    $histArr[] = $sekarang;
                    $histJson = $conn->real_escape_string(json_encode($histArr));

                    $conn->query(
                        "INSERT INTO komoditas
                            (nama, kategori, lokasi, provinsi, satuan,
                             harga_kemarin, harga_sekarang, history, status)
                         VALUES
                            ('$nama','$kategori','$lokasi','$provinsi','$satuan',
                             $kemarin,$sekarang,'$histJson','approved')"
                    );
                    $inserted++;
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        return [
            'inserted' => $inserted,
            'updated'  => $updated,
            'total'    => count($items),
            'errors'   => $errors,
        ];
    }

    // ────────────────────────────────────────────────────────
    //  4. HELPER STATIS
    // ────────────────────────────────────────────────────────

    /**
     * guessSatuan() — Tebak satuan dari nama komoditas BPS
     */
    public static function guessSatuan(string $nama): string {
        $nm = strtolower($nama);
        if (str_contains($nm,'minyak') || str_contains($nm,'bensin')) return 'liter';
        if (str_contains($nm,'telur'))                                 return 'butir';
        if (str_contains($nm,'gula') && str_contains($nm,'pasir'))     return 'kg';
        return 'kg'; // default
    }

    /**
     * guessKategori() — Kategorikan komoditas dari nama BPS
     */
    public static function guessKategori(string $nama): string {
        $nm = strtolower($nama);
        if (str_contains($nm,'beras') || str_contains($nm,'jagung') || str_contains($nm,'kedelai')) return 'Beras & Serealia';
        if (str_contains($nm,'cabai') || str_contains($nm,'tomat')  || str_contains($nm,'bawang'))  return 'Hortikultura';
        if (str_contains($nm,'daging')|| str_contains($nm,'ayam')   || str_contains($nm,'telur'))   return 'Peternakan';
        if (str_contains($nm,'ikan')  || str_contains($nm,'udang')  || str_contains($nm,'bandeng')) return 'Perikanan';
        if (str_contains($nm,'minyak goreng'))                                                      return 'Minyak & Lemak';
        if (str_contains($nm,'gula')  || str_contains($nm,'garam')  || str_contains($nm,'terigu'))  return 'Lainnya';
        return 'Lainnya';
    }

    /**
     * getDomainIdByProvinsi() — Cari domain_id BPS dari nama provinsi Indonesia
     * Menggunakan mapping nama provinsi → kode domain BPS
     */
    public static function getDomainIdByProvinsi(string $provinsiName): string {
        $map = [
            'Aceh'                        => '1100',
            'Sumatera Utara'              => '1200',
            'Sumatera Barat'              => '1300',
            'Riau'                        => '1400',
            'Jambi'                       => '1500',
            'Sumatera Selatan'            => '1600',
            'Bengkulu'                    => '1700',
            'Lampung'                     => '1800',
            'Kepulauan Bangka Belitung'   => '1900',
            'Kepulauan Riau'              => '2100',
            'DKI Jakarta'                 => '3100',
            'Jawa Barat'                  => '3200',
            'Jawa Tengah'                 => '3300',
            'DI Yogyakarta'               => '3400',
            'Jawa Timur'                  => '3500',
            'Banten'                      => '3600',
            'Bali'                        => '5100',
            'Nusa Tenggara Barat'         => '5200',
            'Nusa Tenggara Timur'         => '5300',
            'Kalimantan Barat'            => '6100',
            'Kalimantan Tengah'           => '6200',
            'Kalimantan Selatan'          => '6300',
            'Kalimantan Timur'            => '6400',
            'Kalimantan Utara'            => '6500',
            'Sulawesi Utara'              => '7100',
            'Sulawesi Tengah'             => '7200',
            'Sulawesi Selatan'            => '7300',
            'Sulawesi Tenggara'           => '7400',
            'Gorontalo'                   => '7500',
            'Sulawesi Barat'              => '7600',
            'Maluku'                      => '8100',
            'Maluku Utara'                => '8200',
            'Papua Barat'                 => '9100',
            'Papua'                       => '9400',
            'Papua Selatan'               => '9500',
            'Papua Tengah'                => '9600',
            'Papua Pegunungan'            => '9700',
            'Papua Barat Daya'            => '9800',
        ];
        return $map[$provinsiName] ?? '0000'; // fallback: pusat/nasional
    }

    /**
     * buildApiUrl() — Bangun URL API BPS yang bisa diklik/dibuka browser
     * Berguna untuk debugging atau menampilkan ke user
     */
    public function buildApiUrl(string $type, string $domainId = '0000'): string {
        if ($type === 'province') {
            return BPS_BASE_URL . "domain/type/all/prov/00000/key/{$this->key}/";
        }
        if ($type === 'komoditas') {
            return BPS_BASE_URL . "list/model/data/lang/ind/domain/{$domainId}/var/"
                 . BPS_VAR_HARGA . "/th/" . BPS_TAHUN_KODE . "/key/{$this->key}";
        }
        return '';
    }
}

// ── MAPPING PROVINSI → KOTA/KABUPATEN ────────────────────────
/**
 * PROVINSI_KOTA — Digunakan untuk dropdown dinamis di form.
 * Mengikuti pembagian administratif resmi Indonesia 2024.
 */
define('PROVINSI_KOTA', [

    'DKI Jakarta' => [
        'Jakarta Pusat','Jakarta Utara','Jakarta Barat',
        'Jakarta Selatan','Jakarta Timur','Kepulauan Seribu',
    ],

    'Jawa Barat' => [
        'Kota Bandung','Kota Bekasi','Kota Bogor','Kota Cimahi',
        'Kota Cirebon','Kota Depok','Kota Sukabumi','Kota Tasikmalaya',
        'Kab. Bandung','Kab. Bandung Barat','Kab. Bekasi','Kab. Bogor',
        'Kab. Ciamis','Kab. Cianjur','Kab. Cirebon','Kab. Garut',
        'Kab. Indramayu','Kab. Karawang','Kab. Kuningan','Kab. Majalengka',
        'Kab. Pangandaran','Kab. Purwakarta','Kab. Subang','Kab. Sukabumi',
        'Kab. Sumedang','Kab. Tasikmalaya',
    ],

    'Jawa Tengah' => [
        'Kota Semarang','Kota Solo','Kota Magelang','Kota Pekalongan',
        'Kota Salatiga','Kota Tegal',
        'Kab. Banjarnegara','Kab. Banyumas','Kab. Batang','Kab. Blora',
        'Kab. Boyolali','Kab. Brebes','Kab. Cilacap','Kab. Demak',
        'Kab. Grobogan','Kab. Jepara','Kab. Karanganyar','Kab. Kebumen',
        'Kab. Kendal','Kab. Klaten','Kab. Kudus','Kab. Magelang',
        'Kab. Pati','Kab. Pekalongan','Kab. Pemalang','Kab. Purbalingga',
        'Kab. Purworejo','Kab. Rembang','Kab. Semarang','Kab. Sragen',
        'Kab. Sukoharjo','Kab. Tegal','Kab. Temanggung','Kab. Wonogiri',
        'Kab. Wonosobo',
    ],

    'DI Yogyakarta' => [
        'Kota Yogyakarta','Kab. Bantul','Kab. Gunungkidul',
        'Kab. Kulon Progo','Kab. Sleman',
    ],

    'Jawa Timur' => [
        'Kota Surabaya','Kota Malang','Kota Madiun','Kota Mojokerto',
        'Kota Blitar','Kota Kediri','Kota Pasuruan','Kota Probolinggo','Kota Batu',
        'Kab. Bangkalan','Kab. Banyuwangi','Kab. Blitar','Kab. Bojonegoro',
        'Kab. Bondowoso','Kab. Gresik','Kab. Jember','Kab. Jombang',
        'Kab. Kediri','Kab. Lamongan','Kab. Lumajang','Kab. Madiun',
        'Kab. Magetan','Kab. Malang','Kab. Mojokerto','Kab. Nganjuk',
        'Kab. Ngawi','Kab. Pacitan','Kab. Pamekasan','Kab. Pasuruan',
        'Kab. Ponorogo','Kab. Probolinggo','Kab. Sampang','Kab. Sidoarjo',
        'Kab. Situbondo','Kab. Sumenep','Kab. Trenggalek','Kab. Tuban','Kab. Tulungagung',
    ],

    'Banten' => [
        'Kota Cilegon','Kota Serang','Kota Tangerang','Kota Tangerang Selatan',
        'Kab. Lebak','Kab. Pandeglang','Kab. Serang','Kab. Tangerang',
    ],

    'Bali' => [
        'Kota Denpasar','Kab. Badung','Kab. Bangli','Kab. Buleleng',
        'Kab. Gianyar','Kab. Jembrana','Kab. Karangasem','Kab. Klungkung','Kab. Tabanan',
    ],

    'Aceh' => [
        'Kota Banda Aceh','Kota Langsa','Kota Lhokseumawe','Kota Sabang','Kota Subulussalam',
        'Kab. Aceh Besar','Kab. Aceh Selatan','Kab. Aceh Tengah','Kab. Aceh Timur',
        'Kab. Aceh Utara','Kab. Bireuen','Kab. Pidie','Kab. Pidie Jaya',
    ],

    'Sumatera Utara' => [
        'Kota Medan','Kota Binjai','Kota Gunungsitoli','Kota Padangsidimpuan',
        'Kota Pematangsiantar','Kota Sibolga','Kota Tanjungbalai','Kota Tebing Tinggi',
        'Kab. Asahan','Kab. Deli Serdang','Kab. Karo','Kab. Langkat',
        'Kab. Mandailing Natal','Kab. Nias','Kab. Simalungun','Kab. Tapanuli Selatan',
        'Kab. Tapanuli Tengah','Kab. Tapanuli Utara','Kab. Toba',
    ],

    'Sumatera Barat' => [
        'Kota Padang','Kota Bukittinggi','Kota Padang Panjang',
        'Kota Pariaman','Kota Payakumbuh','Kota Sawahlunto','Kota Solok',
        'Kab. Agam','Kab. Lima Puluh Kota','Kab. Padang Pariaman','Kab. Pasaman',
        'Kab. Pesisir Selatan','Kab. Sijunjung','Kab. Solok','Kab. Tanah Datar',
    ],

    'Riau' => [
        'Kota Dumai','Kota Pekanbaru',
        'Kab. Bengkalis','Kab. Indragiri Hilir','Kab. Indragiri Hulu',
        'Kab. Kampar','Kab. Kuantan Singingi','Kab. Pelalawan',
        'Kab. Rokan Hilir','Kab. Rokan Hulu','Kab. Siak',
    ],

    'Kepulauan Riau' => [
        'Kota Batam','Kota Tanjungpinang',
        'Kab. Bintan','Kab. Karimun','Kab. Kepulauan Anambas','Kab. Lingga','Kab. Natuna',
    ],

    'Jambi' => [
        'Kota Jambi','Kota Sungai Penuh',
        'Kab. Batanghari','Kab. Bungo','Kab. Kerinci','Kab. Merangin',
        'Kab. Muaro Jambi','Kab. Sarolangun','Kab. Tebo',
    ],

    'Bengkulu' => [
        'Kota Bengkulu',
        'Kab. Bengkulu Selatan','Kab. Bengkulu Tengah','Kab. Bengkulu Utara',
        'Kab. Kaur','Kab. Kepahiang','Kab. Lebong','Kab. Mukomuko',
        'Kab. Rejang Lebong','Kab. Seluma',
    ],

    'Sumatera Selatan' => [
        'Kota Lubuklinggau','Kota Pagar Alam','Kota Palembang','Kota Prabumulih',
        'Kab. Banyuasin','Kab. Empat Lawang','Kab. Lahat','Kab. Muara Enim',
        'Kab. Musi Banyuasin','Kab. Musi Rawas','Kab. Ogan Ilir',
        'Kab. Ogan Komering Ilir','Kab. Ogan Komering Ulu',
    ],

    'Kepulauan Bangka Belitung' => [
        'Kota Pangkalpinang',
        'Kab. Bangka','Kab. Bangka Barat','Kab. Bangka Selatan','Kab. Bangka Tengah',
        'Kab. Belitung','Kab. Belitung Timur',
    ],

    'Lampung' => [
        'Kota Bandar Lampung','Kota Metro',
        'Kab. Lampung Barat','Kab. Lampung Selatan','Kab. Lampung Tengah',
        'Kab. Lampung Timur','Kab. Lampung Utara','Kab. Pesawaran',
        'Kab. Pringsewu','Kab. Tanggamus','Kab. Way Kanan',
    ],

    'Kalimantan Barat' => [
        'Kota Pontianak','Kota Singkawang',
        'Kab. Bengkayang','Kab. Kapuas Hulu','Kab. Ketapang','Kab. Kubu Raya',
        'Kab. Landak','Kab. Melawi','Kab. Mempawah','Kab. Sambas',
        'Kab. Sanggau','Kab. Sekadau','Kab. Sintang',
    ],

    'Kalimantan Tengah' => [
        'Kota Palangka Raya',
        'Kab. Barito Selatan','Kab. Barito Timur','Kab. Barito Utara',
        'Kab. Gunung Mas','Kab. Kapuas','Kab. Katingan',
        'Kab. Kotawaringin Barat','Kab. Kotawaringin Timur',
        'Kab. Murung Raya','Kab. Pulang Pisau','Kab. Seruyan','Kab. Sukamara',
    ],

    'Kalimantan Selatan' => [
        'Kota Banjarbaru','Kota Banjarmasin',
        'Kab. Balangan','Kab. Banjar','Kab. Barito Kuala',
        'Kab. Hulu Sungai Selatan','Kab. Hulu Sungai Tengah','Kab. Hulu Sungai Utara',
        'Kab. Kotabaru','Kab. Tabalong','Kab. Tanah Bumbu','Kab. Tanah Laut','Kab. Tapin',
    ],

    'Kalimantan Timur' => [
        'Kota Balikpapan','Kota Bontang','Kota Samarinda',
        'Kab. Berau','Kab. Kutai Barat','Kab. Kutai Kartanegara',
        'Kab. Kutai Timur','Kab. Mahakam Ulu','Kab. Paser','Kab. Penajam Paser Utara',
    ],

    'Kalimantan Utara' => [
        'Kota Tarakan',
        'Kab. Bulungan','Kab. Malinau','Kab. Nunukan','Kab. Tana Tidung',
    ],

    'Sulawesi Utara' => [
        'Kota Bitung','Kota Kotamobagu','Kota Manado','Kota Tomohon',
        'Kab. Bolaang Mongondow','Kab. Kepulauan Sangihe',
        'Kab. Kepulauan Talaud','Kab. Minahasa','Kab. Minahasa Selatan',
        'Kab. Minahasa Tenggara','Kab. Minahasa Utara',
    ],

    'Gorontalo' => [
        'Kota Gorontalo',
        'Kab. Boalemo','Kab. Bone Bolango','Kab. Gorontalo',
        'Kab. Gorontalo Utara','Kab. Pohuwato',
    ],

    'Sulawesi Tengah' => [
        'Kota Palu',
        'Kab. Banggai','Kab. Buol','Kab. Donggala','Kab. Morowali',
        'Kab. Parigi Moutong','Kab. Poso','Kab. Sigi','Kab. Tolitoli',
    ],

    'Sulawesi Barat' => [
        'Kab. Majene','Kab. Mamasa','Kab. Mamuju',
        'Kab. Mamuju Tengah','Kab. Pasangkayu','Kab. Polewali Mandar',
    ],

    'Sulawesi Selatan' => [
        'Kota Makassar','Kota Palopo','Kota Parepare',
        'Kab. Bantaeng','Kab. Barru','Kab. Bone','Kab. Bulukumba',
        'Kab. Enrekang','Kab. Gowa','Kab. Jeneponto','Kab. Luwu',
        'Kab. Luwu Timur','Kab. Luwu Utara','Kab. Maros','Kab. Pinrang',
        'Kab. Sidenreng Rappang','Kab. Sinjai','Kab. Soppeng',
        'Kab. Takalar','Kab. Tana Toraja','Kab. Toraja Utara','Kab. Wajo',
    ],

    'Sulawesi Tenggara' => [
        'Kota Baubau','Kota Kendari',
        'Kab. Bombana','Kab. Buton','Kab. Kolaka','Kab. Konawe',
        'Kab. Konawe Selatan','Kab. Konawe Utara','Kab. Muna','Kab. Wakatobi',
    ],

    'Maluku' => [
        'Kota Ambon','Kota Tual',
        'Kab. Buru','Kab. Kepulauan Aru','Kab. Maluku Tengah',
        'Kab. Maluku Tenggara','Kab. Seram Bagian Barat','Kab. Seram Bagian Timur',
    ],

    'Maluku Utara' => [
        'Kota Ternate','Kota Tidore Kepulauan',
        'Kab. Halmahera Barat','Kab. Halmahera Selatan','Kab. Halmahera Tengah',
        'Kab. Halmahera Timur','Kab. Halmahera Utara','Kab. Kepulauan Sula',
        'Kab. Pulau Morotai','Kab. Pulau Taliabu',
    ],

    'Papua' => [
        'Kota Jayapura',
        'Kab. Asmat','Kab. Biak Numfor','Kab. Boven Digoel','Kab. Jayapura',
        'Kab. Jayawijaya','Kab. Keerom','Kab. Merauke','Kab. Mimika',
        'Kab. Nabire','Kab. Sarmi','Kab. Tolikara','Kab. Waropen','Kab. Yahukimo',
    ],

    'Papua Barat' => [
        'Kota Sorong',
        'Kab. Fakfak','Kab. Kaimana','Kab. Manokwari','Kab. Manokwari Selatan',
        'Kab. Maybrat','Kab. Raja Ampat','Kab. Sorong','Kab. Tambrauw',
        'Kab. Teluk Bintuni','Kab. Teluk Wondama',
    ],

    'Papua Selatan'    => ['Kab. Asmat','Kab. Boven Digoel','Kab. Mappi','Kab. Merauke'],
    'Papua Tengah'     => ['Kab. Deiyai','Kab. Dogiyai','Kab. Intan Jaya','Kab. Mimika','Kab. Nabire','Kab. Paniai','Kab. Puncak','Kab. Puncak Jaya'],
    'Papua Pegunungan' => ['Kab. Jayawijaya','Kab. Lanny Jaya','Kab. Mamberamo Tengah','Kab. Nduga','Kab. Pegunungan Bintang','Kab. Tolikara','Kab. Yahukimo','Kab. Yalimo'],
    'Papua Barat Daya' => ['Kota Sorong','Kab. Maybrat','Kab. Raja Ampat','Kab. Sorong','Kab. Sorong Selatan','Kab. Tambraw'],

    'Nusa Tenggara Barat' => [
        'Kota Bima','Kota Mataram',
        'Kab. Bima','Kab. Dompu','Kab. Lombok Barat','Kab. Lombok Tengah',
        'Kab. Lombok Timur','Kab. Lombok Utara','Kab. Sumbawa','Kab. Sumbawa Barat',
    ],

    'Nusa Tenggara Timur' => [
        'Kota Kupang',
        'Kab. Alor','Kab. Belu','Kab. Ende','Kab. Flores Timur','Kab. Kupang',
        'Kab. Lembata','Kab. Malaka','Kab. Manggarai','Kab. Manggarai Barat',
        'Kab. Manggarai Timur','Kab. Nagekeo','Kab. Ngada','Kab. Rote Ndao',
        'Kab. Sabu Raijua','Kab. Sikka','Kab. Sumba Barat','Kab. Sumba Barat Daya',
        'Kab. Sumba Tengah','Kab. Sumba Timur','Kab. Timor Tengah Selatan',
        'Kab. Timor Tengah Utara',
    ],
]);

/**
 * getKotaByProvinsi() — Ambil daftar kota untuk provinsi tertentu
 */
function getKotaByProvinsi(string $provinsi): array {
    $map = PROVINSI_KOTA;
    return $map[$provinsi] ?? [];
}
