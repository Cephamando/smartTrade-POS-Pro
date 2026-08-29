<?php

class ZraVsdcClient
{
    private PDO $pdo;
    private string $baseUrl;
    private string $tpin;
    private string $bhfId;
    private string $dvcSrlNo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->tpin = getenv('ZRA_TPIN') ?: '1004784338';
        $this->bhfId = getenv('ZRA_BHF_ID') ?: '000';
        $this->dvcSrlNo = getenv('ZRA_DVC_SRL_NO') ?: 'STPOS001V2';
        $this->baseUrl = rtrim(getenv('ZRA_VSDC_URL') ?: 'http://vsdc:8080/sandboxvsdc', '/');
    }

    private function post(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 45
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return ['resultCd' => '999', 'resultMsg' => "CURL Error: $error", 'data' => null];
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['resultCd' => (string)$httpCode, 'resultMsg' => $response, 'data' => null];
    }

    public function initialize(): array
    {
        $payload = [
            'tpin'     => $this->tpin,
            'bhfId'    => $this->bhfId,
            'dvcSrlNo' => $this->dvcSrlNo
        ];

        $res = $this->post('initializer/selectInitInfo', $payload);

        if (($res['resultCd'] ?? '') === '000' && !empty($res['data']['info'])) {
            $info = $res['data']['info'];
            $settings = [
                'vsdc_sdcId'        => $info['sdcId'] ?? '',
                'vsdc_mrcNo'        => $info['mrcNo'] ?? '',
                'vsdc_taxprNm'      => $info['taxprNm'] ?? '',
                'vsdc_last_sale_no' => (string)($info['lastSaleInvcNo'] ?? 0),
                'vsdc_last_invc_no' => (string)($info['lastInvcNo'] ?? 0)
            ];

            $stmt = $this->pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($settings as $k => $v) {
                $stmt->execute([$k, (string)$v]);
            }
        }

        return $res;
    }

    public function syncStandardCodes(string $lastReqDt = '20231215000000'): array
    {
        $payload = [
            'tpin'      => $this->tpin,
            'bhfId'     => $this->bhfId,
            'lastReqDt' => $lastReqDt
        ];

        $res = $this->post('code/selectCodes', $payload);

        if (($res['resultCd'] ?? '') === '000' && !empty($res['data']['clsList'])) {
            $stmt = $this->pdo->prepare("INSERT INTO zra_standard_codes (code_class, code_class_name, code, code_name, user_defined_name)
                                        VALUES (?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE code_name = VALUES(code_name), user_defined_name = VALUES(user_defined_name)");

            foreach ($res['data']['clsList'] as $cls) {
                $cdCls = $cls['cdCls'] ?? '';
                $cdClsNm = $cls['cdClsNm'] ?? '';
                foreach ($cls['dtlList'] ?? [] as $dtl) {
                    $stmt->execute([
                        $cdCls,
                        $cdClsNm,
                        $dtl['cd'] ?? '',
                        $dtl['cdNm'] ?? '',
                        $dtl['userDfnNm1'] ?? null
                    ]);
                }
            }
        }

        return $res;
    }
}
