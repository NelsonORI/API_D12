<?php
// MpesaService.php - M-Pesa API Integration Class

class MpesaService {
    private $consumerKey;
    private $consumerSecret;
    private $shortCode;
    private $passkey;
    private $environment;
    
    public function __construct() {
        // Load configuration
        $confFile = __DIR__ . '/../conf.php';
        if (!file_exists($confFile)) {
            throw new Exception('Configuration file not found: ' . $confFile);
        }
        
        // Include the file and access the global $conf
        require_once $confFile;
        
        // Access the global $conf array
        global $conf;
        
        // Check if $conf array exists
        if (!isset($conf) || !is_array($conf)) {
            throw new Exception('Configuration array not found in conf.php. Make sure $conf is defined as a global array.');
        }
        
        $this->consumerKey = $conf['mpesa_consumer_key'] ?? '';
        $this->consumerSecret = $conf['mpesa_consumer_secret'] ?? '';
        $this->shortCode = $conf['mpesa_shortcode'] ?? '';
        $this->passkey = $conf['mpesa_passkey'] ?? '';
        $this->environment = $conf['mpesa_environment'] ?? 'sandbox';
        
        // Validate required configuration
        if (empty($this->consumerKey) || empty($this->consumerSecret)) {
            throw new Exception('M-Pesa consumer key and secret are required. Check your conf.php file.');
        }
        
        // Debug: Verify we have the credentials
        error_log("M-Pesa Config Loaded - Key exists: " . (!empty($this->consumerKey) ? 'Yes' : 'No'));
    }
    
    // [Keep all the other methods the same as before...]
    /**
     * Get access token from M-Pesa API
     */
    private function getAccessToken() {
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);
        
        $ch = curl_init();
        $url = $this->environment === 'production' 
            ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
            : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
            
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Failed to get access token. HTTP Code: ' . $httpCode . ' Response: ' . $response);
        }
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    /**
     * Format phone number to M-Pesa format (2547XXXXXXXX)
     */
    public function formatPhoneNumber($phone) {
        $phone = preg_replace('/\D/', '', $phone);
        
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            return '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            return '254' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Validate phone number format
     */
    public function validatePhoneNumber($phone) {
        $phone = $this->formatPhoneNumber($phone);
        return strlen($phone) === 12 && substr($phone, 0, 3) === '254';
    }
    
    /**
     * Initiate STK Push
     */
    public function initiateSTKPush($phone, $amount, $accountReference, $callbackUrl) {
        // Validate inputs
        if (!$this->validatePhoneNumber($phone)) {
            throw new Exception('Invalid phone number format');
        }
        
        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception('Invalid amount');
        }
        
        // Get access token
        $accessToken = $this->getAccessToken();
        
        // Prepare STK push request
        $timestamp = date('YmdHis');
        $password = base64_encode($this->shortCode . $this->passkey . $timestamp);
        
        $stkPayload = [
            'BusinessShortCode' => $this->shortCode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => $this->shortCode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => 'Payment for Order'
        ];
        
        $ch = curl_init();
        $url = $this->environment === 'production'
            ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
            : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
            
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: ' . 'application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($stkPayload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new Exception('CURL Error: ' . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('STK Push request failed. HTTP Code: ' . $httpCode . ' Response: ' . $response);
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['ResponseCode']) || $data['ResponseCode'] !== '0') {
            $errorMessage = $data['ResponseDescription'] ?? 'STK Push failed';
            throw new Exception($errorMessage);
        }
        
        return $data;
    }
}

// For testing without M-Pesa credentials
class MockMpesaService {
    public function formatPhoneNumber($phone) {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            return '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            return '254' . $phone;
        }
        return $phone;
    }
    
    public function validatePhoneNumber($phone) {
        $phone = $this->formatPhoneNumber($phone);
        return strlen($phone) === 12 && substr($phone, 0, 3) === '254';
    }
    
    public function initiateSTKPush($phone, $amount, $accountReference, $callbackUrl) {
        // Simulate successful STK push
        return [
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
            'MerchantRequestID' => 'mock_' . time() . '_' . rand(1000, 9999),
            'CheckoutRequestID' => 'wsco_mock_' . time() . '_' . rand(1000, 9999),
            'CustomerMessage' => 'Success'
        ];
    }
}

// Auto-detect which service to use based on configuration
function getMpesaService() {
    $confFile = __DIR__ . '/../conf.php';
    if (!file_exists($confFile)) {
        return new MockMpesaService();
    }
    
    require_once $confFile;
    global $conf;
    
    // Use mock service if M-Pesa credentials are not configured
    if (empty($conf['mpesa_consumer_key']) || empty($conf['mpesa_consumer_secret'])) {
        return new MockMpesaService();
    }
    
    return new MpesaService();
}
?>