<?php
/**
 * Created by PhpStorm.
 * User: pasi
 * Date: 5/22/2016
 * Time: 6:55 PM
 * @param $url
 */

function performToriRequest($url, $postData = false, $headers = [], $requestType = false)
{
    $adRequest = curl_init($url);


    curl_setopt($adRequest, CURLOPT_SSLCERT, "tori.pem");
    curl_setopt($adRequest, CURLOPT_SSLCERTPASSWD, "T0r1+=?");
    curl_setopt($adRequest, CURLOPT_CAINFO, "server.pem");
    curl_setopt($adRequest, CURLOPT_RETURNTRANSFER, true);

    if($requestType) {
        curl_setopt($adRequest, CURLOPT_CUSTOMREQUEST, $requestType);
    }

    if($postData) {
        if(!$requestType) {
            curl_setopt($adRequest, CURLOPT_CUSTOMREQUEST, "POST");
        }
        curl_setopt($adRequest, CURLOPT_POSTFIELDS, $postData);
    }


    curl_setopt($adRequest, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($adRequest);

$handle = fopen("log.txt", "a");
fwrite($handle, "url: " . $url . "\npostData: " . json_encode($postData) . "\nheaders: " . http_build_query($headers) . "\nrequestType: " . $requestType . "\nresult:" . $result . "\n");
fclose($handle);

    curl_close($adRequest);

    return $result;
}

function deleteAdByListingId($listingId, $accessToken) {

    $resultAds = getAdByListingId($listingId);


    if(sizeof($resultAds->list_ads) > 0) {
        $resultAd = $resultAds->list_ads[0];
        
        $rawAdIdString = $resultAd->ad->ad_id;

        $adId = substr($rawAdIdString, strrpos($rawAdIdString, '/') + 1);

        $accountId = $resultAd->ad->account->code;

        $deletePostFields = [
            "delete_reason" => [
                "code" => (string)4
            ],
            "rating" => 6
        ];


        $response = performToriRequest("https://api.tori.fi/api/v1.1/private/accounts/" . $accountId . "/ads/" . $adId, json_encode($deletePostFields), ['Content-Type: application/json', 'Authorization: tag:scmcoord.com,2013:api ' . $accessToken], 'DELETE');

        echo $response;
    } else {
        echo json_encode(createAdNotFoundError());
    }
}

function duplicateAdByListingId($listingId, $accessToken) {
    $resultAds = getAdByListingId($listingId);

    //var_dump($resultAds);

    //exit;

    if(sizeof($resultAds->list_ads) > 0) {
        $resultAd = $resultAds->list_ads[0];

        $accountId = $resultAd->ad->account->code;

        unset($resultAd->labelmap);
        unset($resultAd->spt_metadata);


        unset($resultAd->ad->account);
        unset($resultAd->ad->account_ads);
        unset($resultAd->ad->list_id);
        unset($resultAd->ad->list_time);
        unset($resultAd->ad->phone_hidden);
        unset($resultAd->ad->phones);
        unset($resultAd->ad->share_link);
        unset($resultAd->ad->thumbnail);
        unset($resultAd->ad->user);
        unset($resultAd->ad->ad_id);
        unset($resultAd->ad->company_ad);

if(strpos($resultAd->ad->icon, "/img/nga/") != -1) {
	unset($resultAd->ad->icon);
//	var_dump($resultAd);
}


        unsetLabelsRecursively($resultAd->ad);

        $resultAd->category_suggestion = false;
        $resultAd->commit = true;

        $response = performToriRequest("https://api.tori.fi/api/v1.1/private/accounts/" . $accountId . "/ads", json_encode($resultAd), ['Content-Type: application/json', 'Authorization: tag:scmcoord.com,2013:api ' . $accessToken]);

        echo $response;

    } else {
        echo json_encode(createAdNotFoundError());
    }
}

/**
 * @param $listingId
 * @return mixed
 */
function getAdByListingId($listingId)
{
    return json_decode(performToriRequest("https://api.tori.fi/api/v1.1/public/ads?id=" . $listingId . "&lim=1"));
}

/**
 * @return array
 */
function createAdNotFoundError()
{
    return [
        'error' => [
            'causes' => [
                [
                    'code' => 'ERROR_AD_NOT_FOUND',
                    'label' => 'Ilmoitusta ei löytynyt!'
                ]
            ],
            'code' => 'VALIDATION_FAILED'
        ]
    ];
}

function unsetLabelsRecursively($object) {
    if(is_object($object)) {
        if(isset($object->label)) {
            unset($object->label);
        }


        foreach($object as $key => $value) {
            unsetLabelsRecursively($object->$key);
        }
    } else if(is_array($object)) {
        foreach($object as $key => $value) {
            unsetLabelsRecursively($object[$key]);
        }
    }
}

header("Access-Control-Allow-Origin: *");

$listingId = $_POST['listing_id'];
$accessToken = $_POST['access_token'];


switch($_POST['action']) {
    case 'duplicate':
        duplicateAdByListingId($listingId, $accessToken);
        //echo json_encode(createAdNotFoundError());
        break;
    case 'delete':
        deleteAdByListingId($listingId, $accessToken);
        break;
}
