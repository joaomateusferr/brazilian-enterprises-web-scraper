<?php

    set_time_limit(0);

    $QueryFilePath = isset($argv[1]) ? $argv[1] : false;
    $DebugMode = isset($argv[2]) ? $argv[2] : false;
    $UseSleep = $DebugMode ? false : true;

    $ProjectPath = explode("/", dirname(__FILE__));
	unset($ProjectPath[array_key_last($ProjectPath)]);
	$ProjectPath = implode("/", $ProjectPath);

    $GLOBALS['CookiePath'] = $ProjectPath.'/../brazilian-enterprise-details-web-scraper-cookie-content.txt';
    $GLOBALS['UserAgent'] = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:35.0) Gecko/20100101 Firefox/35.0';

    $ApiSearchUrl = 'https://api.casadosdados.com.br/v2/public/cnpj/search';
    $SitepageUrl = 'https://casadosdados.com.br/solucao/cnpj/';

    function apiRequest (string $Url, array $Query, int $Page = 0) : array {

        if(!empty($Page))
            $Query['page'] = $Page;

        $Query = json_encode($Query);

        $Curl = curl_init();

        curl_setopt_array($Curl, [
            CURLOPT_URL => $Url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_COOKIEJAR => $GLOBALS['CookiePath'],
            CURLOPT_COOKIEFILE => $GLOBALS['CookiePath'],
            CURLOPT_USERAGENT => $GLOBALS['UserAgent'],
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $Query,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]
        );

        $Response = curl_exec($Curl);

        if($Response === false){
            curl_close($Curl);
            echo 'Api curl error: '.curl_error($Curl)."\n";
            exit(1);
        }

        $HttpCode = curl_getinfo($Curl, CURLINFO_HTTP_CODE);

        curl_close($Curl);

        if(empty($HttpCode)){
            echo "Api empty http code!";
            exit(1);
        }

        if($HttpCode != 200){
            echo "Api http code error $HttpCode\n";
            exit(1);
        }

        return json_decode($Response, true);

    }

    function validateCookie (string $CookieFilePath) : bool {

        if(!file_exists($CookieFilePath))
            return false;

        $Cookie = file_get_contents($CookieFilePath);

        if(empty($Cookie))
            return false;

        return true;

    }

    function siteRequest (string $Url) : string {

        $Curl = curl_init();

        curl_setopt_array($Curl, [
            CURLOPT_URL => $Url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_COOKIEJAR => $GLOBALS['CookiePath'],
            CURLOPT_COOKIEFILE => $GLOBALS['CookiePath'],
            CURLOPT_USERAGENT => $GLOBALS['UserAgent'],
            CURLOPT_CUSTOMREQUEST => 'GET'
            ]
        );

        $Response = curl_exec($Curl);

        if($Response === false){
            curl_close($Curl);
            echo 'Site curl error: '.curl_error($Curl)."\n";
            echo $Url."\n";
            return '';
        }

        $HttpCode = curl_getinfo($Curl, CURLINFO_HTTP_CODE);

        curl_close($Curl);

        if(empty($HttpCode)){
            echo "Site empty http code!\n";
            echo $Url."\n";
            return '';
        }

        if($HttpCode != 200){
            echo "Site http code error $HttpCode\n";
            echo $Url."\n";
            return '';
        }

        return $Response;
    }

    function parsePage (string $Page) : array {

        $Document = new DOMDocument();
        @$Document->loadHTML($Page);
        $XPath = new DOMXPath($Document);

        $MainDiv = $XPath->query("//div[contains(@class, 'column is-9')]");

        $PageInfos = [];

        foreach ($MainDiv as $MainDivContent) {

            $Div = $XPath->query(".//div[contains(@class, 'column is-narrow')]", $MainDivContent);

            foreach ($Div as $DivContent) {

            $Key = $XPath->query(".//p[@class='has-text-weight-bold']", $DivContent);
            $Value = $XPath->query(".//p[not(@class='has-text-weight-bold')]", $DivContent);

            if ($Key->length > 0 && $Value->length > 0) {

                $PageInfos[$Key[0]->nodeValue] = [];

                foreach($Value as $Info){

                $PageInfos[$Key[0]->nodeValue][] = $Info->nodeValue;

                }

            }

            }

        }

        return $PageInfos;

    }

    function createCsv (array $Coluns, array $Infos) : string {

        $Data[0] = $Coluns;

        foreach($Infos as $InfoKey => $Info){

            $Details = [];

            foreach($Coluns as $ColunKey => $Colun){

                if($Colun == 'cnpj'){

                    $Details[$ColunKey] = $InfoKey;

                } else {

                    if(empty($Info[$Colun]))
                        $Details[$ColunKey] = '';
                    elseif(is_array($Info[$Colun]))
                        $Details[$ColunKey] = implode(', ', $Info[$Colun]);
                    else
                        $Details[$ColunKey] = $Info[$Colun];

                }

            }

            $Data[] = $Details;

        }

        $Filename = 'brazilian-enterprise-details-web-scraper-'.uniqid().'-'.time().'.csv';

        $CsvPath = '/tmp/'.$Filename;

        $File = fopen($CsvPath, 'w');

        if ($File === false)
            die('Error opening the file ' . $Filename);

        foreach ($Data as $Row) {
            fputcsv($File, $Row);
        }

        fclose($File);

        return $CsvPath;

    }

    function getQuery (string $QueryFilePath) : array {

        $QueryFile = file_get_contents($QueryFilePath);
        unlink($QueryFilePath);
        $Query = json_decode($QueryFile, true);
        return $Query;

    }

    $IsCookieValid = validateCookie($GLOBALS['CookiePath']);

    if(!$IsCookieValid){
        echo "Invalid cookie!";
        exit(1);
    }

    $ApiQuery = getQuery($QueryFilePath);

    $ApiPage = 1;
    $PreviousPage = 0;
    $Empresas = [];

    if($DebugMode)
        echo "Scanning API pages:\n";

    do{

        $ApiResponse = apiRequest($ApiSearchUrl, $ApiQuery, $ApiPage);

        $PreviousPage = $ApiPage -1;

        if(empty($ApiResponse['data']['cnpj']) || empty($ApiResponse['page']['current']))
            break;

        foreach($ApiResponse['data']['cnpj'] as $Empresa){

            $RazaoSocialString = $Empresa['razao_social'];
            $RazaoSocialString = str_replace(' - ', '-', $RazaoSocialString);
            $RazaoSocialString = str_replace(' ', '-', $RazaoSocialString);
            $RazaoSocialString = str_replace('/', '', $RazaoSocialString);
            $RazaoSocialString = str_replace(',', '', $RazaoSocialString);
            $RazaoSocialString = strtolower($RazaoSocialString);

            $URL = $SitepageUrl.$RazaoSocialString.'-'.$Empresa['cnpj'];

            $Empresas[$Empresa['cnpj']] = ['url' => $URL];
        }

        $ApiPage++;

        if($DebugMode)
            echo $ApiResponse['page']['current']."\n";

        if($UseSleep)
            sleep(rand(1, 2));

    }while($ApiResponse['page']['current'] > $PreviousPage);

    if($DebugMode)
        echo "Scanning CNPJs:\n";

    foreach($Empresas as $Cnpj => $Empresa){

        $Page = siteRequest($Empresa['url']);

        if(empty($Page))
            continue;

        $PageInfo = parsePage($Page);

        if(isset($PageInfo['Quadro Societário']))
            $Empresas[$Cnpj]['quadro_societario'] = $PageInfo['Quadro Societário'];

        if(isset($PageInfo['E-MAIL']))
            $Empresas[$Cnpj]['email'] = $PageInfo['E-MAIL'];

        if(isset($PageInfo['Telefone']))
            $Empresas[$Cnpj]['telefone'] = $PageInfo['Telefone'];

        if($UseSleep)
            sleep(rand(1, 3));

        if($DebugMode)
            echo $Cnpj."\n";

    }

    $CsvColuns = ['cnpj', 'quadro_societario', 'email', 'telefone', 'url'];

    $CsvPath = createCsv($CsvColuns, $Empresas);

    echo $CsvPath;