<?php

class PaynetEcomAPI
{
    const API_VERSION = "Version 1.2";

    /**
     * Paynet merchant code.
     */
    private $merchant_code;

    /**
     * Paynet merchant secret key.
     */
    private $merchant_secret_key;

    /**
     * Paynet merchant user for access to API.
     */
    private $merchant_user;

    /**
     * Paynet merchant user's password.
     */
    private $merchant_user_password;

    /**
     * The base URL to API.
     */
    private $api_base_url;
    private $base_ui_url;
    private $base_ui_server_url;
    private $expiry_hours;
    private $adapting_hours;

    const DEFAULT_EXPIRY_DATE_HOURS = 4;
    const DEFAULT_ADAPTING_HOURS = 1;

    public function __construct(
        $merchant_code = null,
        $merchant_secret_key = null,
        $merchant_user = null,
        $merchant_user_password = null,
        $environment = 'test',
        $config = []
    )
    {
        $this->merchant_code = $merchant_code;
        $this->merchant_secret_key = $merchant_secret_key;
        $this->merchant_user = $merchant_user;
        $this->merchant_user_password = $merchant_user_password;

        $endpoints = $config['endpoints'][$environment] ?? $config['endpoints']['test'] ?? [];
        $this->base_ui_url = $endpoints['ui_url'] ?? 'https://test.paynet.md/acquiring/setecom';
        $this->base_ui_server_url = $endpoints['ui_server_url'] ?? 'https://test.paynet.md/acquiring/getecom';
        $this->api_base_url = $endpoints['api_url'] ?? 'https://test.paynet.md:4446';
        $this->expiry_hours = $config['expiry_hours'] ?? self::DEFAULT_EXPIRY_DATE_HOURS;
        $this->adapting_hours = $config['adapting_hours'] ?? self::DEFAULT_ADAPTING_HOURS;
    }

    public function Version()
    {
        return self::API_VERSION;
    }

    public function TokenGet($addHeader = false)
    {
        $path = '/auth';
        $params = [
            'grant_type' => 'password',
            'username' => $this->merchant_user,
            'password' => $this->merchant_user_password
        ];

        $tokenReq = $this->callApi($path, 'POST', $params);
        $result = new PaynetResult();

        if ($tokenReq->Code == PaynetCode::SUCCESS) {
            $tokenData = is_array($tokenReq->Data) ? $tokenReq->Data : [];
            if (array_key_exists('access_token', $tokenData)) {
                $result->Code = PaynetCode::SUCCESS;
                if ($addHeader)
                    $result->Data = ["Authorization: Bearer " . $tokenData['access_token']];
                else
                    $result->Data = $tokenData['access_token'];
            } else {
                $result->Code = PaynetCode::USERNAME_OR_PASSWORD_WRONG;
                if (array_key_exists('Message', $tokenData))
                    $result->Message = $tokenData['Message'];
                if (array_key_exists('error', $tokenData))
                    $result->Message = $tokenData['error'];
            }
        } else {
            $result->Code = $tokenReq->Code;
            $result->Message = $tokenReq->Message;
        }
        return $result;
    }

    public function PaymentGet($externalID)
    {
        $path = '/api/Payments';
        $params = [
            'ExternalID' => $externalID
        ];

        $tokenReq = $this->TokenGet(true);
        $result = new PaynetResult();

        if ($tokenReq->IsOk()) {
            $resultCheck = $this->callApi($path, 'GET', null, $params, $tokenReq->Data);
            if ($resultCheck->IsOk()) {
                $result->Code = $resultCheck->Code;

                $checkData = is_array($resultCheck->Data) ? $resultCheck->Data : [];
                if (array_key_exists('Code', $checkData)) {
                    $result->Code = $checkData['Code'];
                    $result->Message = $checkData['Message'] ?? null;
                } else {
                    $result->Data = $resultCheck->Data;
                }

            } else
                $result = $resultCheck;
        } else {
            $result->Code = $tokenReq->Code;
            $result->Message = $tokenReq->Message;
        }
        return $result;
    }

    public function FormCreate($pRequest)
    {
        $result = new PaynetResult();
        $result->Code = PaynetCode::SUCCESS;

        //----------------- preparing a service  ----------------------------
        $_service_name = '';
        $product_line = 0;
        $_service_item = "";
        //-------------------------------------------------------------------
        $pRequest->ExpiryDate = $this->ExpiryDateGet($this->expiry_hours);

        $amount = 0;
        foreach ($pRequest->Service["Products"] as $item) {
            $_service_item .= '<input type="hidden" name="Services[0][Products][' . $product_line . '][LineNo]" value="' . htmlspecialchars_decode((string)($item['LineNo'] ?? '')) . '"/>';
            $_service_item .= '<input type="hidden" name="Services[0][Products][' . $product_line . '][Code]" value="' . htmlspecialchars_decode((string)($item['Code'] ?? '')) . '"/>';
            $_service_item .= '<input type="hidden" name="Services[0][Products][' . $product_line . '][BarCode]" value="' . htmlspecialchars_decode((string)($item['Barcode'] ?? '')) . '"/>';
            $_service_item .= '<input type="hidden" name="Services[0][Products][' . $product_line . '][Name]" value="' . htmlspecialchars_decode((string)($item['Name'] ?? '')) . '"/>';
            $_service_item .= '<input type="hidden" name="Services[0][Products][' . $product_line . '][Description]" value="' . htmlspecialchars_decode((string)($item['Description'] ?? $item['Descrption'] ?? '')) . '"/>';
            $_service_item .= '<input type="hidden" name="Services[0][Products][' . $product_line . '][Quantity]" value="' . htmlspecialchars_decode((string)($item['Quantity'] ?? 0)) . '"/>';
            $_service_item .= '<input type="hidden" name="Services[0][Products][' . $product_line . '][UnitPrice]" value="' . htmlspecialchars_decode((string)($item['UnitPrice'] ?? 0)) . '"/>';
            $product_line++;
            $amount += (($item['Quantity'] ?? 0) / 100) * ($item['UnitPrice'] ?? 0);
        }

        $pRequest->Service["Amount"] = $amount;
        $signature = $this->SignatureGet($pRequest);
        $pp_form = '<form method="POST" action="' . $this->base_ui_url . '">' .
            '<input type="hidden" name="ExternalID" value="' . htmlspecialchars_decode((string)$pRequest->ExternalID) . '"/>' .
            '<input type="hidden" name="Services[0][Description]" value="' . htmlspecialchars_decode((string)($pRequest->Service["Description"] ?? '')) . '"/>' .
            '<input type="hidden" name="Services[0][Name]" value="' . htmlspecialchars_decode((string)($pRequest->Service["Name"] ?? '')) . '"/>' .
            '<input type="hidden" name="Services[0][Amount]" value="' . $amount . '"/>' .
            $_service_item .
            '<input type="hidden" name="Currency" value="' . $pRequest->Currency . '"/>' .
            '<input type="hidden" name="Merchant" value="' . $this->merchant_code . '"/>' .
            '<input type="hidden" name="Customer.Code"   value="' . htmlspecialchars_decode((string)($pRequest->Customer['Code'] ?? '')) . '"/>' .
            '<input type="hidden" name="Customer.Name"   value="' . htmlspecialchars_decode((string)($pRequest->Customer['Name'] ?? '')) . '"/>' .
            '<input type="hidden" name="Customer.Address"   value="' . htmlspecialchars_decode((string)($pRequest->Customer['Address'] ?? '')) . '"/>' .
            '<input type="hidden" name="Payer.Email"   value="v.bragari@ggg.md"/>' .
            '<input type="hidden" name="Payer.Name"   value="Oleg"/>' .
            '<input type="hidden" name="Payer.Surname"   value="Stoianov"/>' .
            '<input type="hidden" name="Payer.Mobile"   value="37360000000"/>' .
            '<input type="hidden" name="ExternalDate" value="' . htmlspecialchars_decode($this->ExternalDate()) . '"/>' .
            '<input type="hidden" name="LinkUrlSuccess" value="' . htmlspecialchars_decode((string)$pRequest->LinkSuccess) . '"/>' .
            '<input type="hidden" name="LinkUrlCancel" value="' . htmlspecialchars_decode((string)$pRequest->LinkCancel) . '"/>' .
            '<input type="hidden" name="ExpiryDate"   value="' . htmlspecialchars_decode((string)$pRequest->ExpiryDate) . '"/>' .
            '<input type="hidden" name="Signature" value="' . $signature . '"/>' .
            '<input type="hidden" name="Lang" value="' . $pRequest->Lang . '"/>' .
            '<input type="submit" value="GO to a payment gateway of paynet" />' .
            '</form>';
        $result->Data = $pp_form;
        return $result;
    }

    public function PaymentReg($pRequest)
    {
        $path = '/api/Payments/Send';
        $pRequest->ExpiryDate = $this->ExpiryDateGet($this->expiry_hours);
        //------------- calculating total amount
        foreach ($pRequest->Service[0]['Products'] as $item) {

            $pRequest->Service[0]['Amount'] = ($pRequest->Service[0]['Amount'] ?? 0) + (($item['Quantity'] ?? 0) / 100) * ($item['UnitPrice'] ?? 0);
        }

        $params = [
            'Invoice' => $pRequest->ExternalID,
            'MerchantCode' => $this->merchant_code,
            'LinkUrlSuccess' => $pRequest->LinkSuccess,
            'LinkUrlCancel' => $pRequest->LinkCancel,
            'Customer' => $pRequest->Customer,
            'Payer' => $pRequest->Customer,
            'Currency' => 498,
            'ExternalDate' => $this->ExternalDate(),
            'ExpiryDate' => $this->ExpiryDateGet($this->expiry_hours),
            'Services' => $pRequest->Service,
            'Lang' => $pRequest->Lang
        ];

        $tokenReq = $this->TokenGet(true);
        $result = new PaynetResult();

        if ($tokenReq->IsOk()) {
            //	print_r($tokenReq);
            //	echo "<br>";
            //	print_r($path); 			echo "<br>";
            //	print_r($params); 			echo "<br>";
            //print_r($tokenReq->Data[0]);
            //return;
            $resultCheck = $this->callApi($path, 'POST', $params, [], $tokenReq->Data);
            if ($resultCheck->IsOk()) {
                $result->Code = $resultCheck->Code;

                $checkData = is_array($resultCheck->Data) ? $resultCheck->Data : [];
                if (array_key_exists('Code', $checkData)) {
                    $result->Code = $checkData['Code'];
                    $result->Message = $checkData['Message'] ?? null;
                } else {
                    //print_r($resultCheck->Data);
                    //print_r($pRequest);
                    $pp_form = '<form method="POST" action="' . $this->base_ui_server_url . '">' .
                        '<input type="hidden" name="operation" value="' . htmlspecialchars_decode((string)($checkData['PaymentId'] ?? '')) . '"/>' .
                        '<input type="hidden" name="LinkUrlSucces" value="' . htmlspecialchars_decode((string)$pRequest->LinkSuccess) . '"/>' .
                        '<input type="hidden" name="LinkUrlCancel" value="' . htmlspecialchars_decode((string)$pRequest->LinkCancel) . '"/>' .
                        '<input type="hidden" name="ExpiryDate"   value="' . htmlspecialchars_decode((string)$pRequest->ExpiryDate) . '"/>' .
                        '<input type="hidden" name="Signature" value="' . htmlspecialchars_decode((string)($checkData['Signature'] ?? '')) . '"/>' .
                        '<input type="hidden" name="Lang" value="' . $pRequest->Lang . '"/>' .
                        '<input type="submit" value="GO to a payment gateway of paynet" />' .
                        '</form>';
                    $result->Data = $pp_form;
                }

            } else
                $result = $resultCheck;
        } else {
            $result->Code = $tokenReq->Code;
            $result->Message = $tokenReq->Message;
        }
        return $result;
    }

    private function callApi($path, $method = 'GET', $params = [], $query_params = [], $headers = [])
    {
        $result = new PaynetResult();

        $url = $this->api_base_url . $path;

        if (is_array($query_params) && count($query_params) > 0) {
            $url .= '?' . http_build_query($query_params);
        }

        $ch = curl_init($url);
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($method != 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params ?? []));// json_encode($params));
        }

        $json_response = curl_exec($ch);

        if ($json_response === false) {
            /*
             * If an error occurred, remember the error
             * and return false.
             */
            $result->Message = curl_error($ch) . ', ' . curl_errno($ch);
            $result->Code = PaynetCode::CONNECTION_ERROR;
            //print_r(curl_errno($ch));

            // Remember to close the cURL object
            curl_close($ch);
            return $result;
        }

        /*
         * No error, just decode the JSON response, and return it.
         */
        $result->Data = json_decode($json_response, true);

        // Remember to close the cURL object
        curl_close($ch);
        $result->Code = PaynetCode::SUCCESS;
        return $result;
    }

    private function ExpiryDateGet($addHours)
    {
        $date = strtotime("+" . $addHours . " hour");
        return date('Y-m-d', $date) . 'T' . date('H:i:s', $date);
    }

    public function ExternalDate($addHours = null)
    {
        $addHours = $addHours ?? $this->adapting_hours;
        $date = strtotime("+" . $addHours . " hour");
        return date('Y-m-d', $date) . 'T' . date('H:i:s', $date);
    }

    private function SignatureGet($request)
    {
        $_sing_raw = $request->Currency;
        $_sing_raw .= $request->Customer['Address'] . $request->Customer['Code'] . $request->Customer['Name'];
        $_sing_raw .= $request->ExpiryDate . strval($request->ExternalID) . $this->merchant_code;
        $_sing_raw .= $request->Service['Amount'] . $request->Service['Name'] . $request->Service['Description'];
        $_sing_raw .= $this->merchant_secret_key;

        return base64_encode(md5($_sing_raw, true));
    }

    public function __get($name)
    {
        return $this->$name ?? null;
    }
}
