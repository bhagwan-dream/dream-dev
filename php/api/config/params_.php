<?php
$is_api_prod = true;
$arr = [
    'adminEmail' => 'admin@example.com',
    // 'consent_desktop_base_url' => 'http://ic.zigbu.com/'
    // 'consent_desktop_base_url' => 'http://192.168.1.7:10002/'
    'consent_desktop_base_url' => 'http://iconsentweb.merckmiddleast.com/' ,
    
    "API_URL" => []
];

if( $is_api_prod ){
    $arr[ "API_URL" ] = [
        "ONE_KEY_SEARCH" => [
            "url" => "https://okws.ok.imshealth.com/ok/search/2697" ,
            "header" => [
                'Content-type: application/json; ' ,
                'charset=UTF-8; ' ,
                'Authorization: Basic MjY5N19NV0VCOlM4TTdCSlM2'
            ]
        ],
        
        "VR_SUBMIT" => [
            "url" => "https://okws.ok.imshealth.com/vr/submit/2697" ,
            "header" => [
                'Content-type: application/json; ' ,
                'charset=UTF-8; ' ,
                'Authorization: Basic MjY5N19NV0VCOlM4TTdCSlM2'
            ]
        ],
        
        "VR_TRACE" => [
            "url" => "https://okws.ok.imshealth.com/vr/trace/2697" ,
            "header" => [
                "Content-type: application/json;" ,
                "charset=UTF-8; " ,
                "Authorization: Basic MjY5N19NV0VCOlM4TTdCSlM2" ,
            ]
        ],
        
        "POP_SUBMIT" => [
            "url" => "https://okws.ok.imshealth.com/pop/submitKey/2697" ,
            "header" => [
                "Content-type: application/json;" ,
                "charset=UTF-8; " ,
                "Authorization: Basic MjY5N19NV0VCOlM4TTdCSlM2" ,
            ]
        ],
        "MI_INDIVIDUAL" => [
            "url" => 'https://mdigital-emea-mi.sfe.iqvia.com/MobileIntelligence/v1/Individual_API?$filter=CLIENT_INDIVIDUAL_IDENTIFIER+eq+' ,
            "header" => [ ]
        ],
        
        "MI_CONCENT" => [
            "url" => "https://mdigital-emea-mi.sfe.iqvia.com/MobileIntelligence/v1/CustomerConsent_API" ,
            "header" => [ ]
        ]
    ];
}else{
    $arr[ "API_URL" ] = [
        "ONE_KEY_SEARCH" => [
            "url" => "https://okws-qa.ok.imshealth.com/ok/search/1361" ,
            "header" => [
                'Content-type: application/json; ' ,
                'charset=UTF-8; ' ,
                'Authorization: Basic MTM2MV9NV0VCOjIwNEc4NUU3'
            ]
        ],
        
        "VR_SUBMIT" => [
            "url" => "https://okws-qa.ok.imshealth.com/vr/submit/1361" ,
            "header" => [
                'Content-type: application/json; ' ,
                'charset=UTF-8; ' ,
                'Authorization: Basic MTM2MV9NV0VCOjIwNEc4NUU3'
            ]
        ],
        
        "VR_TRACE" => [
            "url" => "https://okws-qa.ok.imshealth.com/vr/trace/1361" ,
            "headers" => [
                "Content-type: application/json;" ,
                "charset=UTF-8; " ,
                "Authorization: Basic MTM2MV9NV0VCOjIwNEc4NUU3" ,
            ]
        ],
        
        "POP_SUBMIT" => [
            "url" => "https://okws-qa.ok.imshealth.com/pop/submitKey/1361" ,
            "headers" => [
                "Content-type: application/json;" ,
                "charset=UTF-8; " ,
                "Authorization: Basic MTM2MV9NV0VCOjIwNEc4NUU3" ,
            ]
        ],
        
        "MI_INDIVIDUAL" => [
            "url" => 'https://test-mdigital-emea-mi.sfe.iqvia.com/MobileIntelligence/v1/Individual_API?$filter=CLIENT_INDIVIDUAL_IDENTIFIER+eq+' ,
            "header" => [ ]
        ],
        
        "MI_CONCENT" => [
            "url" => "https://test-mdigital-emea-mi.sfe.iqvia.com/MobileIntelligence/v1/CustomerConsent_API" ,
            "header" => [ ]
        ]
    ];
}



function returnAuth($countryCode, $isProd) {
    switch ($countryCode) {
        case "TR":
            if ($isProd==true) {
                return "Basic TUVSQ0tBUElUUjpNZXJjayoxMjM0";
            } else {
                return "Basic ZnVsbGhvOjEyMzQ1NkFzZCo=";
            }
            break;
        case "SA":
            if ($isProd==true) {
                return "Basic bWVyY2thcGlzYTpNZXJjayoxMjM0";
            } else {
                return "Basic bWVyY2thcGlzYTpNZXJjayoxMjM0";
            }
            break;
        case ($countryCode == "YM" || $countryCode == "AE" || $countryCode == "QA" || $countryCode == "OM" || $countryCode == "KW" || $countryCode == "BH"):
            if ($isProd==true) {
                return "";
            } else {
                return "Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx";
            }
            break;
        case ($countryCode == "PS" || $countryCode == "SY" || $countryCode == "LB" || $countryCode == "JO" || $countryCode == "IQ"):
            if ($isProd==true) {
                return "";
            } else {
                return "Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==";
            }
            break;
        case "EG":
            if ($isProd==true) {
                return "";
            } else {
                return "Basic bWVyY2thcGllZzptZXJja2FwaWVnMQ==";
            }
            break;
    }
    
    return "Nothing found";
}

$country_codes = [ "TR" , "SA" , "YM" , "AE" , "QA" , "OM" , "KW" , "BH" , "PS" , "SY" , "LB" , "JO" , "IQ" , "EG" ];

foreach ( $country_codes as $code ) {
    $authStr = returnAuth( $code , $is_api_prod );
    
    $header = [
        'Content-Type:application/json;IEEE754Compatible=true' ,
        'Authorization: ' . $authStr,
    ];
    
    $arr[ "API_URL" ][ "MI_INDIVIDUAL" ][ "header" ][ $code ]   = $header;
    $arr[ "API_URL" ][ "MI_CONCENT" ][ "header" ][ $code ]      = $header;
}

return $arr;
