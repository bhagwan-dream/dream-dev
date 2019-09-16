<?php
return [
    'adminEmail' => 'admin@example.com',
    // 'consent_desktop_base_url' => 'http://ic.zigbu.com/'
    // 'consent_desktop_base_url' => 'http://192.168.1.7:10002/'
    'consent_desktop_base_url' => 'http://ic.zigbu.com/' ,
    
    /* "API_URL" => [
        "MI_INDIVIDUAL" => [ 
            "url" => 'https://test-mdigital-emea-mi.sfe.iqvia.com/MobileIntelligence/v1/Individual_API?$filter=CLIENT_INDIVIDUAL_IDENTIFIER+eq+' ,
            "header" => [
                "turkey" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic ZnVsbGhvOjEyMzQ1NkFzZCo=',
                ] ,
                "other" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlzYTpNZXJjayoxMjM0'
                ]
            ]
        ],
        
        "MI_CONCENT" => [
            "url" => "https://test-mdigital-emea-mi.sfe.iqvia.com/MobileIntelligence/v1/CustomerConsent_API" ,
            "header" => [
                "turkey" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic ZnVsbGhvOjEyMzQ1NkFzZCo=',
                ] ,
                "other" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlzYTpNZXJjayoxMjM0'
                ]
            ]
        ],
        
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
        ]
    ] */
    
    "API_URL" => [
        "MI_INDIVIDUAL" => [
            "url" => 'https://test-mdigital-emea-mi.sfe.iqvia.com/MobileIntelligence/v1/Individual_API?$filter=CLIENT_INDIVIDUAL_IDENTIFIER+eq+' ,
            "header" => [
                "turkey" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic ZnVsbGhvOjEyMzQ1NkFzZCo=',
                ] ,
                "bahrain" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "ksa" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlzYTpNZXJjayoxMjM0',
                ] ,
                "kuwait" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "oman" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "qatar" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "united arab emirates" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "iraq" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "jordan" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "lebanon" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "syria" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "palestine" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "egypt" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGllZzptZXJja2FwaWVnMQ=='
                ],
                "other" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic'
                ]
            ]
        ],
        
        "MI_CONSENT" => [
            "url" => "https://test-mdigital-emea-mi.sfe.iqvia.com/MobileIntelligence/v1/CustomerConsent_API" ,
            "header" => [
                "turkey" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic ZnVsbGhvOjEyMzQ1NkFzZCo=',
                ] ,
                "bahrain" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "ksa" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlzYTpNZXJjayoxMjM0',
                ] ,
                "kuwait" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "oman" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "qatar" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "united arab emirates" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlnbGY6bWVyY2thcGlnbGYx',
                ] ,
                "iraq" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "jordan" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "lebanon" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "syria" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "palestine" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGlsZXY6bWVyY2thcGlsZXYxIQ==',
                ] ,
                "egypt" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic bWVyY2thcGllZzptZXJja2FwaWVnMQ=='
                ],
                "other" => [
                    'Content-Type:application/json;IEEE754Compatible=true' ,
                    'Authorization: Basic'
                ]
            ]
        ],
        
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
            "header" => [
                "Content-type: application/json;" ,
                "charset=UTF-8; " ,
                "Authorization: Basic MTM2MV9NV0VCOjIwNEc4NUU3" ,
            ]
        ],
        
        "POP_SUBMIT" => [
            "url" => "https://okws-qa.ok.imshealth.com/pop/submitKey/1361" ,
            "header" => [
                "Content-type: application/json;" ,
                "charset=UTF-8; " ,
                "Authorization: Basic MTM2MV9NV0VCOjIwNEc4NUU3" ,
            ]
        ]
    ]
];
