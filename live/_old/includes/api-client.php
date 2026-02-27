<?php

function nebf_api_request_stockfile()
{

    $username = get_option('nebf_api_username');
    $secret   = get_option('nebf_api_secret');
    $testmode = get_option('nebf_api_testmode') === '1' ? 'true' : 'false';

    $endpoint = 'https://www.beautyfort.com/api/soap';

    $nonce   = uniqid();
    $created = date("c");
    $password = base64_encode(sha1($nonce . $created . $secret));

    $xml = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:bf="http://www.beautyfort.com/api/">
    <soap:Header>
        <bf:AuthHeader>
            <bf:Username>' . esc_xml($username) . '</bf:Username>
            <bf:Nonce>' . esc_xml($nonce) . '</bf:Nonce>
            <bf:Created>' . esc_xml($created) . '</bf:Created>
            <bf:Password>' . esc_xml($password) . '</bf:Password>
        </bf:AuthHeader>
    </soap:Header>
    <soap:Body>
        <bf:GetStockFileRequest>
            <bf:TestMode>' . $testmode . '</bf:TestMode>
            <bf:StockFileFormat>XML</bf:StockFileFormat>
            <bf:StockFileFields>
    <bf:StockFileField>Barcode</bf:StockFileField>
    <bf:StockFileField>Brand</bf:StockFileField>
    <bf:StockFileField>BreakBulkReference</bf:StockFileField>
    <bf:StockFileField>Category</bf:StockFileField>
    <bf:StockFileField>Collection</bf:StockFileField>
    <bf:StockFileField>Description</bf:StockFileField>
    <bf:StockFileField>FullName</bf:StockFileField>
    <bf:StockFileField>Gender</bf:StockFileField>
    <bf:StockFileField>HighResImageUrl</bf:StockFileField>
    <bf:StockFileField>ImageLastUpdated</bf:StockFileField>
    <bf:StockFileField>LastPurchasedDate</bf:StockFileField>
    <bf:StockFileField>LastPurchasedPrice</bf:StockFileField>
    <bf:StockFileField>Price</bf:StockFileField>
    <bf:StockFileField>Quantity</bf:StockFileField>
    <bf:StockFileField>Size</bf:StockFileField>
    <bf:StockFileField>StockCode</bf:StockFileField>
    <bf:StockFileField>StockLevel</bf:StockFileField>
    <bf:StockFileField>ThumbnailImageUrl</bf:StockFileField>
    <bf:StockFileField>Type</bf:StockFileField>
    <bf:StockFileField>YourRating</bf:StockFileField>
    <bf:StockFileField>YourStockCode</bf:StockFileField>
</bf:StockFileFields>
            <bf:SortBy>FullName</bf:SortBy>
            <bf:StockFileEncoding>UTF-8</bf:StockFileEncoding>
        </bf:GetStockFileRequest>
    </soap:Body>
</soap:Envelope>';

    $response = wp_remote_post($endpoint, [
        'headers' => [
            'Content-Type' => 'text/xml; charset=UTF-8',
            'Accept'       => 'text/xml',
        ],
        'body'    => $xml,
        'timeout' => 60,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);

    libxml_use_internal_errors(true);

    $xml = simplexml_load_string($body);
    $namespaces = $xml->getNamespaces(true);

    $soap = $xml->children($namespaces['SOAP-ENV']);
    $body = $soap->Body;

    $bf = $body->children($namespaces['ns1']);
    $response = $bf->GetStockFileResponse ?? null;

if (!$response) {
    // Om Fault finns, logga och returnera error
    if (isset($bodyChildren->Fault)) {
        error_log('BeautyFort SOAP Fault: ' . print_r($bodyChildren->Fault, true));
        return new WP_Error('soap_fault', 'BeautyFort API returnerade ett fel.');
    }

    return new WP_Error('no_response', 'Hittade inget GetStockFileResponse i SOAP-svaret.');
}

// Nu vet vi att File finns
$encodedFile = (string) $response->File;
$decodedXml = base64_decode($encodedFile, true);

if ($decodedXml === false) {
    return new WP_Error('nebf_xml_error', 'Kunde inte dekoda Base64 XML från BeautyFort.');
}

$stockXml = simplexml_load_string($decodedXml);
if (!$stockXml) {
    return new WP_Error('xml_error', 'Kunde inte läsa stockfile XML.');
}
return $stockXml;


    // Räcker att verifiera att vi fick ett giltigt stockfile
    if (!isset($stockXml->item)) {
        return new WP_Error(
            'invalid_stockfile',
            'API svarade, men inget giltigt StockFile mottogs.'
        );
    }

    return true;
}
